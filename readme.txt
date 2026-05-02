=== Media Library Manager ===
Contributors: biliplugins
Donate link: https://buymeacoffee.com/wpbunty
Author URI: https://biliplugins.com/
Tags: remove duplicates, media, media library, optimize media, images
Requires at least: 6.1
Tested up to: 6.9.4
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An easy-to-use plugin to detect and manage duplicate media files in WordPress with background indexing, without affecting site performance.

== Description ==

Media Library Manager is an easy-to-use plugin that helps you clean up and organize your WordPress media library by detecting duplicate files.

The plugin scans your media library using SHA-256 file hashing to identify exact duplicates. All processing runs in the background, ensuring your site performance is not affected—even on large media libraries.

When you remove duplicates, the plugin helps maintain site integrity by automatically updating references. **It replaces duplicate image URLs in post content and updates featured images to use the original file, so your content continues to display correctly after cleanup.**

Duplicates are first moved to trash before permanent removal, giving you a safety layer. You can also restore any item from trash if needed.

== How it works ==

1. Go to Media → Media Library Manager
2. Index your media library from (runs in the background)
3. Detect duplicate files based on file hash
4. Review duplicate files in a clean and easy-to-use admin interface
5. Remove duplicates manually, one by one (moved to trash first)
6. Restore media from trash if something goes wrong

This approach ensures you stay in full control while safely cleaning up your media library.

== Features ==

* **Easy-to-use interface** — simple workflow for reviewing and managing duplicates
* **Accurate duplicate detection** using SHA-256 hashing
* **Background indexing** — no timeouts or impact on site performance
* **Duplicate grouping view** with pagination
* **Manual duplicate removal** with full control
* **Safe workflow** — review files before removing
* **Trash system** — duplicates are moved to trash before permanent deletion
* **Restore option** — easily restore media from trash if needed

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/media-library-manager` directory, or install via the WordPress Plugins screen.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Media → Media Library Manager**.
4. Click **Index Media** to start scanning your media library.
5. Review duplicate groups and remove unwanted files.

== Screenshots ==
1. Scan Media
2. Scan in processes
2. Scan Completed
3. Duplicates List
4. Rmove Duplicate Media to Trash
5. Restore or permanently delete Media from Trash

== Frequently Asked Questions ==

= Can I remove duplicates? =

Yes. You can review duplicate groups and remove files individually. Removed files are first moved to trash, so you can restore them if needed.

= Will deleting duplicates break my site? =

The plugin is designed to help you safely manage duplicates by updating references automatically. However, it is always recommended to take a backup before making changes.

= How is the original file selected? =

The plugin keeps the **oldest uploaded file** as the original and treats newer identical files as duplicates.

= Does indexing affect site performance? =

No. Indexing runs in the background via Action Scheduler and processes files in small batches, ensuring your site's performance is not affected.

= Does this plugin work with large media libraries? =

Yes. Background processing ensures stable performance even on large sites.

== Credits ==

This plugin uses the Action Scheduler library for background processing.

== Changelog ==

= 1.0.0 =
* Background media indexing
* SHA-256 based duplicate detection
* Duplicate grouping interface
* Manual duplicate removal
* Trash and restore duplicate media