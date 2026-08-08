<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
// Views/subtab_opt_media.php — Media & File
?>
                            <div id="subtab-opt_media" class="uwb-subtab-content">
                                <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-bottom:24px;">
                                    <?php $this->render_submodule_header( 'uwb_module_media_opt_enabled', 'Image Optimization Settings', '🖼️' ); ?>
                                    <?php
                                    $this->render_toggle_switch( 'uwb_media_lazy_load_images', 'Lazy Load Images', 'Delay image loading until visible in viewport.' );
                                    $this->render_toggle_switch( 'uwb_media_optimize_viewport_images', 'Optimize Viewport Images (Above The Fold)', 'Automatically disable lazy loading and add fetchpriority="high" to images detected in the first screen (viewport) to improve LCP.' );
                                    $this->render_toggle_switch( 'uwb_media_compress_viewport_images', 'Compress Viewport Images (Above The Fold)', 'Automatically trigger compression/optimization for the detected above-the-fold images to reduce their byte size and LCP time.' );
                                    $this->render_toggle_switch( 'uwb_media_lazy_load_iframes', 'Lazy Load Iframes / Videos', 'Delay iframe (YouTube/Vimeo) and HTML5 video loading until visible in viewport.' );
                                    $this->render_toggle_switch( 'uwb_media_add_missing_sizes', 'Add Missing Sizes', 'Automatically add width and height attributes to images.' );
                                    $this->render_textarea_setting( 'uwb_media_lazy_load_excludes', 'Lazy Load Image Excludes', "/wp-content/uploads/logo.png\nimage-class-name", 'URLs or class names of images to exclude from lazy loading (one per line).' );
                                    $this->render_textarea_setting( 'uwb_media_lazy_load_class_excludes', 'Lazy Load Class Excludes', "skip-lazy\n.hero-section\nsection.banner-wrap", 'Specify CSS class names or parent container selectors (e.g. <code>skip-lazy</code>, <code>.hero-section</code>, <code>section.banner-wrap</code>, <code>div.no-lazy-container</code>) to exclude all nested images/iframes/videos inside those parent blocks from lazy loading (one per line).' );
                                    ?>

                                    <!-- Section: Optimize Image (Compress & Convert WebP/AVIF) -->
                                    <div style="background:#fff; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-top:24px; margin-bottom:24px;">
                                        <h3 style="margin-top:0; margin-bottom:16px; font-size:15px; display:flex; align-items:center; gap:8px;">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                            Optimize Image (Compress, WebP / AVIF &amp; Meta Flags)
                                        </h3>
                                        <p style="font-size:13px; color:var(--uwb-text-muted); margin-bottom:20px;"></p>

                                        <?php
                                        $this->render_toggle_switch( 'uwb_media_opt_enabled', 'Enable Automatic Image Optimization & Conversion', 'Tự động nén chất lượng ảnh và convert định dạng khi upload hoặc gọi ảnh trong Thư viện Media.' );
                                        $this->render_toggle_switch( 'uwb_media_opt_backup_bak', 'Backup ảnh gốc thành đuôi .bak?', 'Tự động tạo bản sao lưu file ảnh gốc dạng filename.jpg.bak trước khi nén hoặc convert.' );
                                        $is_opt_active = (bool) get_option( 'uwb_media_opt_enabled', 0 );
                                        ?>

                                        <!-- Event Actions for Image Optimization -->
                                        <div class="uwb-img-opt-events-wrap" style="margin-top:16px; margin-bottom:20px; background:#fff; border:1px solid var(--uwb-border); border-radius:10px; padding:16px; <?php echo $is_opt_active ? '' : 'display:none;'; ?>">
                                            <h5 style="margin:0 0 14px 0; font-size:12px; font-weight:700; color:var(--uwb-text); text-transform:uppercase; letter-spacing:0.5px;">Sự kiện tự động tối ưu ảnh (Event Actions)</h5>
                                            <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap; padding:10px 14px; background:#f8fafc; border:1px solid var(--uwb-border); border-radius:8px;">
                                                <div style="min-width:180px; font-weight:700; font-size:13px; color:var(--uwb-text); flex-shrink:0;">
                                                    Tự động tối ưu ảnh khi:
                                                </div>
                                                <div style="display:flex; align-items:center; gap:18px; flex-wrap:wrap; flex:1;">
                                                    <label style="display:inline-flex; align-items:center; gap:6px; font-size:13px; font-weight:600; cursor:pointer; color:#334155;">
                                                        <input type="hidden" name="uwb_img_opt_event_upload" value="0" />
                                                        <input type="checkbox" name="uwb_img_opt_event_upload" value="1" <?php checked( get_option( 'uwb_img_opt_event_upload', 1 ), 1 ); ?> />
                                                        upload
                                                    </label>
                                                    <label style="display:inline-flex; align-items:center; gap:6px; font-size:13px; font-weight:600; cursor:pointer; color:#334155;">
                                                        <input type="hidden" name="uwb_img_opt_event_edit" value="0" />
                                                        <input type="checkbox" name="uwb_img_opt_event_edit" value="1" <?php checked( get_option( 'uwb_img_opt_event_edit', 0 ), 1 ); ?> />
                                                        edit
                                                    </label>
                                                    <label style="display:inline-flex; align-items:center; gap:6px; font-size:13px; font-weight:600; cursor:pointer; color:#334155;">
                                                        <input type="hidden" name="uwb_img_opt_event_get_url" value="0" />
                                                        <input type="checkbox" name="uwb_img_opt_event_get_url" value="1" <?php checked( get_option( 'uwb_img_opt_event_get_url', 0 ), 1 ); ?> />
                                                        get_url
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:20px; margin-top:16px; margin-bottom:20px; background:#fff; padding:20px; border:1px solid var(--uwb-border); border-radius:10px;">
                                            <!-- Field 1: Quality Percentage -->
                                            <div class="uwb-form-group">
                                                <label for="uwb_media_opt_quality" style="font-weight:700; font-size:13px; color:var(--uwb-text); display:block; margin-bottom:6px;">
                                                    Mức độ nén chất lượng (%) (Compression Quality)
                                                </label>
                                                <div style="display:flex; align-items:center; gap:10px;">
                                                    <input type="number" min="1" max="100" name="uwb_media_opt_quality" id="uwb_media_opt_quality" value="<?php echo esc_attr( get_option( 'uwb_media_opt_quality', 80 ) ); ?>" style="width:100px; padding:8px 12px; border:1px solid var(--uwb-border); border-radius:6px; font-size:13.5px; font-weight:700; text-align:center;" />
                                                    <span style="font-size:12.5px; color:var(--uwb-text-muted);">(Mặc định 80%. Mức 75% - 85% cho tỷ lệ nén dung lượng tối ưu nhất)</span>
                                                </div>
                                            </div>

                                            <!-- Field 2: Target Format -->
                                            <div class="uwb-form-group">
                                                <label for="uwb_media_opt_format" style="font-weight:700; font-size:13px; color:var(--uwb-text); display:block; margin-bottom:6px;">
                                                    Chuyển đổi định dạng (Convert Format)
                                                </label>
                                                <?php
                                                $opt_format = get_option( 'uwb_media_opt_format', 'original' );
                                                $webp_ok = \Ultimate_WP_Booster\Engine\Optimization\Media\ImageOptimizer::is_webp_supported();
                                                $avif_ok = \Ultimate_WP_Booster\Engine\Optimization\Media\ImageOptimizer::is_avif_supported();
                                                ?>
                                                <select name="uwb_media_opt_format" id="uwb_media_opt_format" style="width:100%; border:1px solid var(--uwb-border); border-radius:6px; padding:9px 12px; font-size:13.5px; background:#fff;">
                                                    <option value="original" <?php selected( $opt_format, 'original' ); ?>>Giữ nguyên định dạng gốc (Original format)</option>
                                                    <option value="webp" <?php selected( $opt_format, 'webp' ); ?> <?php disabled( ! $webp_ok ); ?>>Convert sang WebP (<?php echo $webp_ok ? 'Supported' : 'PHP GD/Imagick Not Supported'; ?>)</option>
                                                    <option value="avif" <?php selected( $opt_format, 'avif' ); ?> <?php disabled( ! $avif_ok ); ?>>Convert sang AVIF (<?php echo $avif_ok ? 'Supported' : 'PHP GD/Imagick Not Supported'; ?>)</option>
                                                </select>
                                            </div>

                                            <!-- Field 3: Conversion Methods & Output Modes (Checkboxes) -->
                                            <div class="uwb-form-group" style="grid-column:1 / -1;">
                                                <label style="font-weight:700; font-size:13px; color:var(--uwb-text); display:block; margin-bottom:10px;">
                                                    Phương thức ghi file (Conversion Output Modes)
                                                </label>
                                                <div style="display:flex; flex-direction:column; gap:12px; background:#f8fafc; border:1px solid var(--uwb-border); border-radius:8px; padding:14px 16px;">
                                                    <label style="display:inline-flex; align-items:flex-start; gap:10px; cursor:pointer;">
                                                        <input type="hidden" name="uwb_media_opt_mode_sidecar" value="0" />
                                                        <input type="checkbox" name="uwb_media_opt_mode_sidecar" value="1" <?php checked( get_option( 'uwb_media_opt_mode_sidecar', 1 ), 1 ); ?> style="margin-top:2px;" />
                                                        <div>
                                                            <strong style="font-size:13px; color:var(--uwb-text); display:block;">Create Sidecar File (Default)</strong>
                                                            <span style="font-size:12px; color:var(--uwb-text-muted);">Tạo file WebP/AVIF mới song song (ví dụ: <code>image.webp</code> hoặc <code>image.jpg.webp</code>)</span>
                                                        </div>
                                                    </label>

                                                    <label style="display:inline-flex; align-items:flex-start; gap:10px; cursor:pointer;">
                                                        <input type="hidden" name="uwb_media_opt_mode_overwrite" value="0" />
                                                        <input type="checkbox" name="uwb_media_opt_mode_overwrite" value="1" <?php checked( get_option( 'uwb_media_opt_mode_overwrite', 0 ), 1 ); ?> style="margin-top:2px;" />
                                                        <div>
                                                            <strong style="font-size:13px; color:var(--uwb-text); display:block;">Overwrite File Content In-Place</strong>
                                                            <span style="font-size:12px; color:var(--uwb-text-muted);">Ghi đè dữ liệu nhị phân (binary data) WebP/AVIF trực tiếp lên file gốc giữ nguyên đuôi mở rộng cũ</span>
                                                        </div>
                                                    </label>

                                                    <label style="display:inline-flex; align-items:flex-start; gap:10px; cursor:pointer;">
                                                        <input type="hidden" name="uwb_media_opt_mode_replace_ext" value="0" />
                                                        <input type="checkbox" name="uwb_media_opt_mode_replace_ext" value="1" <?php checked( get_option( 'uwb_media_opt_mode_replace_ext', 0 ), 1 ); ?> style="margin-top:2px;" />
                                                        <div>
                                                            <strong style="font-size:13px; color:var(--uwb-text); display:block;">Replace &amp; Change File Extension</strong>
                                                            <span style="font-size:12px; color:var(--uwb-text-muted);">Chuyển đổi định dạng và đổi trực tiếp đuôi file thành <code>.webp</code> / <code>.avif</code>, cập nhật database WordPress</span>
                                                        </div>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Batch Optimizer Button -->
                                        <div style="border-top:1px solid var(--uwb-border); padding-top:16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                                            <div>
                                                <strong style="font-size:13px; color:var(--uwb-text); display:block;">Batch Optimize Media Library</strong>
                                                <span style="font-size:12px; color:var(--uwb-text-muted);">Nén &amp; convert toàn bộ ảnh hiện có trong Thư viện Media (Tự động lưu cờ status để không nén lặp lại).</span>
                                            </div>
                                            <button type="button" id="btn-batch-optimize-images" class="button button-secondary" style="font-weight:600; padding:8px 16px; border-radius:6px; cursor:pointer;">
                                                Optimize &amp; Convert Existing Media
                                            </button>
                                        </div>
                                        <div id="uwb-optimize-progress-wrap" style="margin-top:14px; display:none;">
                                            <div class="uwb-progress-bar-wrap" style="margin-bottom:6px;">
                                                <div class="uwb-progress-bar-fill" id="uwb-optimize-progress-fill" style="width:0%;"></div>
                                            </div>
                                            <div id="uwb-optimize-status-text" style="font-size:12px; font-weight:600; color:var(--uwb-text);">Processing batch optimization...</div>
                                        </div>
                                    </div>
                                    <?php

                                    $this->render_cdn_distribution_card(
                                        'Cloudflare R2 / S3 CDN Distribution for Media Library & Files',
                                        'uwb_cdn_distribute_media',
                                        'Phân phối Media Library & Tập tin qua S3 CDN?',
                                        'Đồng bộ và phân phối toàn bộ tập tin hình ảnh, tài liệu và thumbnails trong thư viện Media WordPress qua S3/R2 CDN.',
                                        array(
                                            'Upload to S3 when:' => array(
                                                'uwb_cdn_auto_upload_attachment'     => 'upload',
                                                'uwb_cdn_auto_rewrite_attachment_url' => 'get_url',
                                            ),
                                            'Update file to S3 when:' => array(
                                                'uwb_cdn_auto_update_attachment'     => 'edit',
                                            ),
                                            'Delete From S3 when:' => array(
                                                'uwb_cdn_auto_delete_attachment'     => 'Delete file',
                                            ),
                                            'Delete From Local when:' => array(
                                                'uwb_cdn_delete_local'               => 'Uploaded to S3',
                                            ),
                                        )
                                    );
                                    $this->render_page_optimizer_tools_section( 'Media Tools' );
                                    ?>
                                    <?php $this->render_module_banner_end(); ?>
                                </div>
                            </div>

