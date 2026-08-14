=== Ultimate WP Booster ===
Contributors: tuend-work
Tags: cache, speed, optimization, database, cdn, preload, static cache, page cache, redis, memcached, cloudflare, s3
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 5.4.14
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

= 5.4.14 =
* Keep CDN rewriting enabled for all media and favicon links while preventing blank favicon overrides.

= 5.4.13 =
* Fix: Prevent blank favicon injection from overriding site favicons.

= 5.4.12 =
* Secure input sanitization in AJAX endpoints.
* Fixed SSL verification for API security.
* General compatibility fixes and cleanups.
