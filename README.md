# Ultimate WordPress Booster

A lightweight and powerful WordPress plugin designed for **Static Page Caching** and **Sitemap Preloading**, fully compatible with **Rocket-Nginx**.

## 🚀 Features

1. **Sitemap Preloader**:
   - Automatically detects or accepts custom XML sitemaps.
   - Extracts all URLs and adds them to a database-backed queue table (`{wp_prefix}ultimate_wp_booster_queue`).
   - Premium dashboard featuring a real-time progress bar with Start, Pause, and Clear Queue actions.
   - Background preloader running via WP-Cron in small batches to prevent CPU spikes.

2. **Advanced Configurations**:
   - **Cache for Logged-in Users**: Enable/disable caching specifically for logged-in sessions.
   - **Priority URLs**: Input URL patterns that should be crawled first.
   - **Excluded URLs**: Exclude specific pages or RegEx patterns (e.g., `/cart(.*)`, `/checkout(.*)`) from caching or preloading.
   - **Cache Lifespan**: Customize how many hours cache files remain valid before expiration.

3. **Rocket-Nginx Compatibility**:
   - Cache files are stored in the exact format and folder structure as WP Rocket: `wp-content/cache/wp-rocket/[domain]/[request_uri]/index-https.html`.
   - Generates pre-compressed Gzip static assets (`index-https.html_gzip`) to enable rapid loading.
   - Works out-of-the-box with [Rocket-Nginx](https://github.com/satellitewp/rocket-nginx) configurations, allowing Nginx to serve static files directly without loading PHP or Database.

4. **WordPress Toolbar Shortcuts**:
   - Displays a dedicated "WP Booster" menu node in the admin status bar.
   - Quick access links: "Purge This URL" (from frontend), "Clear & Preload Cache", and "Settings".

5. **GitHub Updates**:
   - Native integration with the public repository: `https://github.com/tuend-work/ultimate-wp-booster`.
   - Update checking and manual installation button located in the plugin's "Updates" tab.

---

## 🛠️ Installation & Setup

1. Upload the `ultimate-wp-booster` folder to the `wp-content/plugins/` directory.
2. Activate the plugin through the WordPress admin panel.
3. Navigate to **Settings > Ultimate WP Booster** to configure caching settings, crawler settings, and preloading.

### 🌐 Nginx Configuration (Optional but Recommended)
To serve static files directly using Nginx, set up **Rocket-Nginx**:

1. Clone the Rocket-Nginx repository:
   ```bash
   cd /etc/nginx/
   git clone https://github.com/satellitewp/rocket-nginx.git
   ```
2. Generate the configuration file following the project instructions, and `include` it inside your Nginx server block:
   ```nginx
   include /etc/nginx/rocket-nginx/default.conf;
   ```
3. Restart Nginx:
   ```bash
   sudo systemctl restart nginx
   ```

---

## 📂 Source Code Structure

- [ultimate-wp-booster.php](file:///f:/DEV/ultimate-wp-booster/ultimate-wp-booster.php): Main plugin bootstrap file containing hooks, cron filters, and global Admin Bar actions.
- [github-updater.php](file:///f:/DEV/ultimate-wp-booster/github-updater.php): Handles automated update notifications and downloading from the GitHub repository.
- [advanced-cache.php](file:///f:/DEV/ultimate-wp-booster/advanced-cache.php): Drop-in caching file copied to `wp-content/advanced-cache.php` to intercept early page requests.
- [includes/class-uwb-activator.php](file:///f:/DEV/ultimate-wp-booster/includes/class-uwb-activator.php): Table creation, default settings, and enabling `WP_CACHE` in `wp-config.php`.
- [includes/class-uwb-deactivator.php](file:///f:/DEV/ultimate-wp-booster/includes/class-uwb-deactivator.php): Cleanup routine on deactivation.
- [includes/class-uwb-cache.php](file:///f:/DEV/ultimate-wp-booster/includes/class-uwb-cache.php): Caching engine managing file writing, directory structuring, and post-update cache purges.
- [includes/class-uwb-preloader.php](file:///f:/DEV/ultimate-wp-booster/includes/class-uwb-preloader.php): Crawling queue database controller and XML sitemap parser.
- [includes/class-uwb-admin.php](file:///f:/DEV/ultimate-wp-booster/includes/class-uwb-admin.php): Premium options panel styling, settings registration, and ajax tracker scripts.