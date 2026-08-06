<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
// Views/page_shell_open.php — Outer page shell: header, sidebar nav, form open
?>
<div class="uwb-dashboard-wrap">
    <?php
    if ( isset( $_GET['uwb_msg'] ) || isset( $_GET['settings-updated'] ) ) : ?>
    <div class="uwb-global-notices" style="margin-bottom: 24px;">
        <?php
        if ( isset( $_GET['uwb_msg'] ) ) {
            $msg = sanitize_text_field( $_GET['uwb_msg'] );
            $cnt = isset( $_GET['count'] ) ? intval( $_GET['count'] ) : 0;
            if ( $msg === 's3_asset_cleared' ) {
                echo '<div class="notice notice-success is-dismissible" style="padding:14px 18px; margin-bottom:12px; font-weight:600; background:#f0fdf4; border-left:4px solid #22c55e; color:#15803d; border-radius:8px;"><p style="margin:0; font-size:13.5px;">&#x2601;&#xFE0F; <strong>S3 Asset Cache &amp; Index Cleared!</strong> Successfully cleared <code>cdn_uploaded_assets.json</code> index and removed <strong>' . $cnt . '</strong> asset file(s) from S3/R2 storage.</p></div>';
            } elseif ( $msg === 'cdn_zone_cleared' ) {
                echo '<div class="notice notice-success is-dismissible" style="padding:14px 18px; margin-bottom:12px; font-weight:600; background:#fefce8; border-left:4px solid #eab308; color:#a16207; border-radius:8px;"><p style="margin:0; font-size:13.5px;">&#x1F310; <strong>Cloudflare CDN Zone Cache Purged!</strong> Successfully executed Purge Everything on Cloudflare Edge Cache.</p></div>';
            } elseif ( $msg === 'cdn_zone_error' ) {
                $err = isset( $_GET['err'] ) ? esc_html( urldecode( $_GET['err'] ) ) : 'Missing API Credentials';
                echo '<div class="notice notice-error is-dismissible" style="padding:14px 18px; margin-bottom:12px; font-weight:600; background:#fef2f2; border-left:4px solid #ef4444; color:#b91c1c; border-radius:8px;"><p style="margin:0; font-size:13.5px;">&#x274C; <strong>Cloudflare CDN Zone Purge Error:</strong> ' . $err . '</p></div>';
            } elseif ( $msg === 'cache_cleared' ) {
                echo '<div class="notice notice-success is-dismissible" style="padding:14px 18px; margin-bottom:12px; font-weight:600; background:#eff6ff; border-left:4px solid #3b82f6; color:#1d4ed8; border-radius:8px;"><p style="margin:0; font-size:13.5px;">&#x26A1; <strong>Static Page Cache Cleared!</strong> All HTML page cache files purged successfully.</p></div>';
            } elseif ( $msg === 'flush_all_preload_started' || $msg === 'preload_started' ) {
                echo '<div class="notice notice-success is-dismissible" style="padding:14px 18px; margin-bottom:12px; font-weight:600; background:#f0fdf4; border-left:4px solid #10b981; color:#047857; border-radius:8px;"><p style="margin:0; font-size:13.5px;">&#x1F680; <strong>Flush All &amp; Preload Started!</strong> Successfully purged Page Cache, Cloudflare CDN Zone, S3 Asset Cache, OPCache, and Object Cache! Preload crawler restarted.</p></div>';
            }
        }
        settings_errors();
        ?>
    </div>
    <?php endif; ?>

    <div class="uwb-header">
        <div class="uwb-header-title">
            <h1>Ultimate WordPress Booster v<?php echo esc_html( UWB_VERSION ); ?></h1>
            <p>Optimize website loading speed with ultra-fast Static Page Caching.</p>
        </div>
        <div class="uwb-header-actions" style="display: flex; align-items: center; gap: 12px;">
            <span id="uwb-github-update-status" style="font-size: 13px; font-weight: 600; color: rgba(255, 255, 255, 0.9);"></span>
            <button type="button" id="uwb-github-update-btn" class="uwb-btn-purge" style="cursor: pointer; border: 1px solid rgba(255, 255, 255, 0.3); outline: none;">
                <svg class="uwb-git-icon" viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" style="color: inherit;"><path fill="currentColor" d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"></path></svg>
                <span class="uwb-btn-text">Update Plugin</span>
                <span class="uwb-spinner" style="display: none; margin-left: 6px;"></span>
            </button>
        </div>
    </div>

    <div class="uwb-layout">
        <div class="uwb-sidebar-nav">
            <div class="uwb-sidebar-toggle" id="uwb-toggle-sidebar" title="Thu gon / Mo rong">
                <svg class="toggle-icon-collapse" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                <svg class="toggle-icon-expand" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="display:none;"><polyline points="9 18 15 12 9 6"/></svg>
            </div>
            <div class="uwb-nav-item active" data-tab="url_status" title="Dashboard">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
                <span>Dashboard</span>
            </div>
            <div class="uwb-nav-item" data-tab="cache_settings" title="Cache Settings">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                <span>Cache Module</span>
            </div>
            <div class="uwb-nav-item" data-tab="page_optimizes" title="Page Optimizes">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/><line x1="20" y1="20" x2="4" y2="4"/></svg>
                <span>Page Optimizes Module</span>
            </div>
            <div class="uwb-nav-item" data-tab="preload_settings" title="Preload Module">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                <span>Preload Module</span>
            </div>
            <div class="uwb-nav-item" data-tab="advanced_tools" title="Advanced / Tools">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                <span>Advanced Tools</span>
            </div>
            <div class="uwb-nav-item" data-tab="import_export" title="Import / Export">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <span>Import / Export</span>
            </div>
        </div>

        <div class="uwb-content-panel">
            <form method="post" action="options.php" novalidate>
                <?php settings_fields( 'uwb_settings_group' ); ?>