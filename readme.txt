=== Media Library Manager ===
Contributors: biliplugins
Author URI: https://biliplugins.com/
Tags: media, media library, duplicates, attachments, file management
Requires at least: 6.1
Tested up to: 6.9.4
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A WordPress plugin to index, detect, and remove duplicate media files - with restore and background processing.

== Description ==

**Media Library Manager** gives you full control over your WordPress media library. It scans your uploaded files, identifies exact duplicates using SHA-256 file hashing, replaces all references to duplicate files with the canonical (kept) file, and moves duplicates to a plugin-managed trash. Everything runs in the background via Action Scheduler, so large libraries are handled without timeouts.

**Key features:**

* **Index your media library** — hashes every attachment (SHA-256) for fast, accurate duplicate detection.
* **Detect duplicates** — groups files by hash and shows all duplicate sets in a paginated admin list.
* **Remove duplicates** — replaces every post content reference (image IDs, URLs, all size variants) from post content and featured image with the canonical file, then moves duplicates to plugin trash.
* **Restore-aware trash** — restoring a trashed attachment automatically reverts post content and featured images back to that attachment, without losing any edits made after deduplication.
* **Permanent deletion** — empty the trash to permanently delete files and their WordPress records via background jobs.
* **Background processing** — all indexing, deduplication, and deletion runs via Action Scheduler; no page-timeout risk on large sites.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/media-library-manager` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Navigate to **Media → Media Library Manager** in the WordPress admin.
4. Click **Index Media** to hash your existing attachments.
5. Once indexing is complete, click **Remove Duplicates** to begin deduplication.

== Frequently Asked Questions ==

= Will removing duplicates break my posts or pages? =

No. Before any file is trashed, the plugin replaces every reference to the duplicate attachment — post content image IDs, image URLs (all registered sizes), and featured image (`_thumbnail_id`) — with the equivalent reference to the canonical (kept) file. Your posts and pages will continue to display images correctly.

= Can I undo a deduplication? =

Yes. Trashed attachments can be restored from the Trash view under Media Library Manager. Restoring an attachment automatically reverts **only post content and featured images** back to that attachment, without affecting any other edits made to those posts after deduplication ran.

= Is it safe to use on a live site? =

It is recommended to take a full backup before running bulk deduplication on a production site, as with any operation that modifies post content in bulk. The plugin is designed to be safe and reversible (via restore), but a backup is always a good practice.

= What happens when I empty the trash? =

All trashed attachments are queued for permanent deletion via background jobs. Then files are removed from the server and their WordPress records are deleted. This action cannot be undone, so make sure you have reviewed the trash before emptying it.

= Does indexing affect site performance? =

Indexing runs in the background via Action Scheduler and does not run on page load. It processes attachments in small batches (50 at a time) to keep server load minimal.

== Changelog ==

= 1.0.0 =

- File indexing
- File deduplication
- File restoration
