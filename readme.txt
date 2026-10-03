=== Ultimate WP Booster ===
Contributors: tuend-work
Tags: cache, speed, optimization, database, cdn, preload, static cache, page cache, redis, memcached, cloudflare, s3
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 5.4.23
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Ultra-fast Static Cache, Database Optimization, CDN Offload, and Sitemap Preloader for WordPress. High-compatibility with rocket-nginx.

== Description ==

Ultimate WP Booster is a comprehensive WordPress performance plugin designed to drastically improve page speed, reduce server response times (TTFB), and optimize core web vitals.

= Key Features =
* **Static Page Caching**: Fast file-based caching serving HTML immediately.
* **Database Optimizer**: Safely cleans revisions, spam comments, transients, and optimizes database tables.
* **CDN Offload**: Seamlessly offloads media files to Cloudflare R2 or Amazon S3.
* **Runtime Plugin Manager**: Selectively disable plugins on a per-request basis for optimal performance.
* **Critical CSS & Above-the-Fold Image Optimization**: Improves FCP and LCP scores.
* **Preloader**: Sitemap crawling preloader to warm cache before visitors arrive.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/ultimate-wp-booster` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Configure the settings under the 'WP Booster' menu in your WordPress dashboard.

== Changelog ==

= 5.4.23 =
* Fix: Strictly adhere to configured Cache Lifespan (minutes to seconds conversion) in LiteSpeed Cache-Control headers across CacheSubscriber and advanced-cache.php.
* Fix: Support dedicated XML sitemaps and PHP page lifespans for LiteSpeed Web Server.
* UI: Display exact active Page Cache Lifespan on Admin Dashboard cache pipeline card.

= 5.4.22 =
* Feature: LiteSpeed Server-Only Cache Storage mode — bypasses writing static HTML cache files to wp-content/cache/ on LiteSpeed/OpenLiteSpeed web servers, eliminating disk space and inode consumption while delivering sub-5ms cached responses directly from LiteSpeed server RAM/swap.
* Fix: Restore missing rules and marker definitions in update_litespeed_htaccess.
* Fix: Ensure unlimited cache lifespan (0) sends long-duration max-age instead of no-cache to LiteSpeed.

= 5.4.21 =
* Fix: Strictly isolate .htaccess edits to plugin block and preserve other rewrite blocks.

= 5.4.20 =
* Fix: Prevent wiping out .htaccess rewrite rules and eliminate race condition during file writes.

= 5.4.19 =
* Fix: Prevent duplicate HTML output on bypassed pages by silencing early buffer flush in advanced-cache.php callback.

= 5.4.18 =
* Fix: Add missing log() method to Preloader to prevent fatal errors during server load throttling.

= 5.4.17 =
* Fix: Replace error_log calls in advanced-cache.php with dedicated log file when debug mode is enabled.

= 5.4.16 =
* Fix: Do not write cache purge logs to default error_log.

= 5.4.15 =
* Fix PHP Fatal Error: move namespace declarations to the top of all dependency files.

= 5.4.14 =
* Keep CDN rewriting enabled for all media and favicon links while preventing blank favicon overrides.

= 5.4.13 =
* Fix: Prevent blank favicon injection from overriding site favicons.

= 5.4.12 =
* Secure input sanitization in AJAX endpoints.
* Fixed SSL verification for API security.
* General compatibility fixes and cleanups.
