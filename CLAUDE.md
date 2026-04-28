# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Plugin Overview

**Media Library Manager** is a WordPress plugin (namespace `BiliPlugins\MediaLibraryManager`) that indexes, deduplicates, and manages WordPress media attachments. It adds a submenu page under Media > Media Library Manager in the WordPress admin.

## Build & Development Commands

```bash
# Install dependencies
pnpm install
composer install

# Development (watch mode — runs webpack + file watcher for asset sync)
pnpm run start

# Production build (webpack → copy assets → minify CSS)
pnpm run build

# Manually sync built assets from build/ to assets/
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

The build pipeline: `wp-scripts build` (webpack) outputs to `build/`, then `sync:assets` copies to `assets/` and minifies CSS. The PHP plugin loads from `assets/`.

## Architecture

### Layers

| Layer | Directory | Purpose |
|-------|-----------|---------|
| Domain logic | `src/Core/` | File hashing, indexing, deduplication, SQL queries |
| Admin UI hooks | `src/Admin/` | WP admin menu, list table, attachment hooks |
| REST API | `src/Rest/V1/` | Endpoints consumed by React frontend |
| Background jobs | `src/Scheduler/` | ActionScheduler workers (batch processing) |
| React frontend | `admin-ui/` | Admin page UI compiled to `assets/admin.js` |

### Data Flow

1. Attachments are hashed (SHA-256) on upload via `AdminHooks` (`add_attachment`, `edit_attachment`)
2. Bulk indexing is triggered via REST API → enqueues ActionScheduler jobs
3. `MediaIndexer` batches unindexed attachments (batch size: 50) → `IndexMediaJob` hashes each
4. `MediaDataProvider` queries for duplicate groups by hash
5. `MediaDeduplicator` replaces all post references to duplicates with the canonical file, then trashes duplicates
6. `DeleteTrashedMediaJob` permanently deletes plugin-trashed media

### Key Meta Keys

Stored on attachment posts:
- `blp_mlm_file_hash` — SHA-256 of the file
- `blp_mlm_file_size` — file size in bytes
- `blp_mlm_trash` — marks attachment as plugin-trashed (pending deletion)

### REST API

Namespace: `blp-mlm` (v1). All endpoints require `upload_files` capability.

- `GET/POST /blp-mlm/indexing/...` — start indexing, poll progress
- `GET/POST /blp-mlm/duplicates/...` — query/remove duplicate groups
- `POST /blp-mlm/trash/queue` — enqueue permanent deletion

### Background Jobs (ActionScheduler)

Three custom action hooks handled by workers in `src/Scheduler/`:
- `blp_mlm_index_media` — hash a single attachment
- `blp_mlm_process_deduplicate` — remove one duplicate group
- `blp_mlm_delete_trashed` — permanently delete one trashed file

### Frontend

React components in `admin-ui/` styled with Tailwind CSS (Preflight disabled to avoid conflicts with WP admin styles). Webpack entry: `admin-ui/index.js`. Compiled output: `assets/admin.js` + `assets/admin.css`.

## WordPress Integration Notes

- Plugin bootstraps via `MediaLibraryManager::instance()` (singleton)
- Admin-only classes loaded conditionally inside `if (is_admin())` in `plugin_loader()`
- `AdminHooks` (hash generation) intentionally runs on all contexts — not just admin — to catch frontend uploads
- Scripts are enqueued at priority 100 on `admin_enqueue_scripts` to load after WP core
- PHPCS config (`phpcs.xml.dist`) uses WordPress coding standards; filename snake_case rules are excluded
