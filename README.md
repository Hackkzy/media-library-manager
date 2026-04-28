# Media Library Manager

**Contributors:** [biliplugins](https://profiles.wordpress.org/biliplugins/)
**Author URI:** https://biliplugins.com/
**Tags:** media, media library, duplicates, attachment, file management
**Requires at least:** 6.1
**Requires PHP:** 7.4
**License:** GPLv2 or later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

A WordPress plugin to index, detect, and remove duplicate media files - with restore and background processing.

---

## Description

**Media Library Manager** gives you full control over your WordPress media library. It scans your uploaded files, identifies exact duplicates using SHA-256 file hashing, replaces all references to duplicate files with the canonical (kept) file, and moves duplicates to a plugin-managed trash. Everything runs in the background via Action Scheduler, so large libraries are handled without timeouts.

**Key features:**

- **Index your media library** — hashes every attachment (SHA-256) for fast, accurate duplicate detection.
- **Detect duplicates** — groups files by hash; shows all duplicate sets in a paginated list.
- **Remove duplicates** — replaces every post content reference (image IDs, URLs, all size variants) and featured image (`_thumbnail_id`) with the canonical file, then moves duplicates to trash.
- **Restore-aware trash** — restoring a trashed attachment automatically reverts post content and featured images back to that attachment, without losing any edits made after deduplication.
- **Permanent deletion** — empty the trash to permanently delete files and their WordPress records via background jobs.
- **Background processing** — all indexing, deduplication, and deletion runs via Action Scheduler; no page-timeout risk on large sites.
- **React admin UI** — clean, responsive interface under **Media → Media Library Manager**.

---

## Architecture

| Layer | Directory | Purpose |
|-------|-----------|---------|
| Domain logic | `src/Core/` | File hashing, indexing, deduplication, SQL queries |
| Admin UI hooks | `src/Admin/` | WP admin menu, list table, attachment hooks |
| REST API | `src/Rest/V1/` | Endpoints consumed by the React frontend |
| Background jobs | `src/Scheduler/` | Action Scheduler workers (batch processing) |
| React frontend | `admin-ui/` | Admin page UI compiled to `assets/admin.js` |

### Data Flow

1. Attachments are hashed (SHA-256) on upload via `AdminHooks`.
2. Bulk indexing is triggered via REST API → enqueues Action Scheduler jobs.
3. `MediaIndexer` batches unindexed attachments (batch size: 50) → `IndexMediaJob` hashes each one.
4. `MediaDataProvider` queries for duplicate groups by hash (only non-trashed items count toward the group size).
5. `MediaDeduplicator` replaces all post content and postmeta references to duplicates with the canonical file, records which posts were affected, then moves duplicates to plugin trash.
6. `DeleteTrashedMediaJob` permanently deletes plugin-trashed media in the background.

### Meta Keys

Stored on attachment posts (`wp_postmeta`):

| Meta key | Purpose |
|----------|---------|
| `blp_mlm_file_hash` | SHA-256 hash of the file — used for duplicate detection |
| `blp_mlm_trash` | Marks attachment as plugin-trashed; payload includes `trashed_at`, `trashed_by`, `canonical_id`, `affected_post_ids` |

Stored on **affected posts** during deduplication:

| Meta key | Purpose |
|----------|---------|
| `blp_mlm_content_backup` | Tracks which trashed attachments changed `post_content` or `_thumbnail_id` on this post, enabling restore |

### REST API

Namespace: `blp-mlm` (v1). All endpoints require `upload_files` capability.

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/blp-mlm/indexing/progress` | Poll indexing progress |
| POST | `/blp-mlm/indexing/start` | Start/continue bulk indexing |
| GET | `/blp-mlm/duplicates/progress` | Poll duplicate removal progress |
| POST | `/blp-mlm/duplicates/remove-all` | Queue a batch of duplicate-removal jobs |
| POST | `/blp-mlm/trash/queue` | Enqueue permanent deletion for one trashed item |
| POST | `/blp-mlm/trash/empty` | Queue permanent deletion for all trashed items |

### Background Jobs (Action Scheduler)

| Hook | Worker | Purpose |
|------|--------|---------|
| `blp_mlm_index_media` | `IndexMediaJob` | Hash a single attachment |
| `blp_mlm_process_deduplicate` | `RemoveDuplicatesJob` | Remove one duplicate group |
| `blp_mlm_delete_trashed_media` | `DeleteTrashedMediaJob` | Permanently delete one trashed file |

---

## Build & Development

### Prerequisites

- [Node.js / NPM](https://nodejs.org/)
- [pnpm](https://pnpm.io/)
- [Composer](https://getcomposer.org/)

### Setup

```bash
pnpm install
composer install
```

### Development (watch mode)

```bash
pnpm run start
```

Runs webpack in watch mode and syncs built assets from `build/` to `assets/`.

### Production Build

```bash
pnpm run build
```

Runs webpack → copies assets → minifies CSS. The PHP plugin loads from `assets/`.

### Other Commands

```bash
# Manually sync built assets
pnpm run sync:assets

# Create distributable .zip
pnpm run plugin-zip

# Lint
pnpm run lint:js
pnpm run lint:css
composer phpcs          # PHP linting via phpcs.xml.dist

# Format
pnpm run format
```

---

## Installation

1. Upload the plugin to `/wp-content/plugins/media-library-manager/`, or install via the WordPress plugins screen.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Navigate to **Media → Media Library Manager** in the WordPress admin.
4. Click **Index Media** to hash your existing attachments.
5. Once indexing is complete, click **Remove Duplicates** to begin deduplication.

---

## Changelog

### 1.0.0
* Initial release.
* SHA-256 file hashing on upload and via bulk indexer.
* Duplicate detection by hash group (only non-trashed attachments count).
* Deduplication replaces post content and postmeta references (IDs, all image size URLs, featured images) with the canonical attachment.
* Plugin-managed trash with restore support — restoring a file also reverts post content and featured images without losing edits made after deduplication.
* Permanent deletion via background Action Scheduler jobs.
* Empty Trash empties all plugin-trashed items in one action.
* React admin UI under Media → Media Library Manager.
* REST API for indexing, duplicate removal, and trash management.
