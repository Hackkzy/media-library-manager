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

**Media Library Manager** helps you organize your WordPress media library. It scans uploaded files and identifies exact duplicates using SHA-256 file hashing. All indexing runs in the background via Action Scheduler, so large libraries are handled without timeouts.

**Key features:**

* **Index your media library** — hashes every attachment (SHA-256) for fast, accurate duplicate detection.
* **Detect duplicates** — groups files by hash and shows all duplicate sets in a paginated admin list.
* **Background processing** — indexing runs via Action Scheduler; no page-timeout risk on large sites.
* **Free + Pro workflow** — in free, you can review duplicate groups and manually trash attachments via row actions; bulk remove and empty trash are available in Pro.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/media-library-manager` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Navigate to **Media → Media Library Manager** in the WordPress admin.
4. Click **Index Media** to hash your existing attachments.
5. Review indexed results in the Media Library Manager page and identify duplicate groups.

== Frequently Asked Questions ==

= Can I remove duplicates in the free version? =

Yes, but manually. In the free version, you can trash individual attachments using row actions. Bulk duplicate removal is available in the Pro version.

= Is empty trash available in the free version? =

No. Empty trash is available in the Pro version.

= Is it safe to use on a live site? =

It is recommended to take a full backup before performing any bulk media changes on a production site. A backup is always a good practice.

= Does indexing affect site performance? =

Indexing runs in the background via Action Scheduler and does not run on page load. It processes attachments in small batches (50 at a time) to keep server load minimal.

== Changelog ==

= 1.0.0 =

- File indexing
- Duplicate identification
- Initial free release
