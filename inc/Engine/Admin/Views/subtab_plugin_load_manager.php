<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
// Views/subtab_plugin_load_manager.php — Plugin Load Manager
?>
                            <div id="subtab-plugin_load_manager" class="uwb-subtab-content">
                                <h3 style="margin-top:0; display:flex; align-items:center; gap:10px; font-size: 16px; font-weight: 700;">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--uwb-primary)" stroke-width="2.5"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                                    Plugin Load Manager (Runtime)
                                </h3>
                                <p style="color:var(--uwb-text-muted); margin-bottom:24px; font-size: 13px;">Giảm thời gian bootstrap WordPress bằng cách kiểm soát chính xác plugin nào được load theo ngữ cảnh request (URL, UserRole, Device, WooCommerce, AJAX/REST). Rule được biên dịch thành Lookup Table — Runtime chỉ mất vài micro giây.</p>

                                <!-- Status Bar -->
                                <div id="uro-status-bar" style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:20px; margin-bottom:24px; display:flex; gap:24px; flex-wrap:wrap; align-items:center;">
                                    <div style="flex:1; min-width:200px;">
                                        <div style="font-size:12px; color:var(--uwb-text-muted); margin-bottom:4px;">RUNTIME STATUS</div>
                                        <div id="uro-status-runtime" style="font-weight:700; font-size:14px;">⏳ Loading...</div>
                                    </div>
                                    <div style="flex:1; min-width:200px;">
                                        <div style="font-size:12px; color:var(--uwb-text-muted); margin-bottom:4px;">COMPILED</div>
                                        <div id="uro-status-compiled" style="font-weight:700; font-size:14px;">—</div>
                                    </div>
                                    <div style="flex:1; min-width:200px;">
                                        <div style="font-size:12px; color:var(--uwb-text-muted); margin-bottom:4px;">RULES</div>
                                        <div id="uro-status-rules" style="font-weight:700; font-size:14px;">—</div>
                                    </div>
                                    <div style="flex:1; min-width:200px;">
                                        <div style="font-size:12px; color:var(--uwb-text-muted); margin-bottom:4px;">COMPILE TIME</div>
                                        <div id="uro-status-time" style="font-weight:700; font-size:14px;">—</div>
                                    </div>
                                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                        <button id="btn-uro-enable" type="button" class="button button-primary" style="padding:9px 18px; height:auto; border-radius:8px; font-weight:600; cursor:pointer;">⚡ Enable Runtime</button>
                                        <button id="btn-uro-disable" type="button" class="button" style="padding:9px 18px; height:auto; border-radius:8px; font-weight:600; cursor:pointer; display:none;">🔴 Disable Runtime</button>
                                        <button id="btn-uro-rebuild" type="button" class="button" style="padding:9px 18px; height:auto; border-radius:8px; font-weight:600; cursor:pointer;">🔨 Rebuild</button>
                                    </div>
                                </div>

                                <div id="uro-result-msg" style="display:none; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-weight:600; font-size:13px;"></div>

                                <!-- Sub-tabs -->
                                <div class="uwb-sub-tabs-nav" style="margin-bottom:24px;">
                                    <div class="uwb-sub-tab-item active" data-subtab="uro_rules">Rules Editor</div>
                                    <div class="uwb-sub-tab-item" data-subtab="uro_plugins">Plugin List</div>
                                    <div class="uwb-sub-tab-item" data-subtab="uro_analyzer">Analyzer</div>
                                </div>

                                <!-- SUB-TAB: Rules Editor -->
                                <div id="subtab-uro_rules" class="uwb-subtab-content active">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
                                        <div>
                                            <strong style="font-size:14px;">Rule Editor</strong>
                                            <p style="margin:4px 0 0; color:var(--uwb-text-muted); font-size:12.5px;">Tạo rules để disable plugin trên từng loại trang. Thứ tự = Priority (nhỏ = ưu tiên cao).</p>
                                        </div>
                                        <button id="btn-uro-add-rule" type="button" class="button button-primary" style="padding:9px 18px; height:auto; border-radius:8px; font-weight:600; cursor:pointer;">+ Add Rule</button>
                                    </div>
                                    <div id="uro-rules-list" style="display:flex; flex-direction:column; gap:16px; margin-bottom:20px;">
                                        <div style="text-align:center; padding:40px; color:var(--uwb-text-muted); border:2px dashed var(--uwb-border); border-radius:12px;" id="uro-no-rules">
                                            ⚡ Chưa có rule nào. Bấm <strong>Add Rule</strong> để bắt đầu.
                                        </div>
                                    </div>
                                    <div style="display:flex; gap:12px; flex-wrap:wrap;">
                                        <button id="btn-uro-save-rules" type="button" class="button button-primary" style="padding:10px 24px; height:auto; border-radius:8px; font-weight:600; cursor:pointer;">💾 Save &amp; Compile</button>
                                        <button id="btn-uro-scan-plugins" type="button" class="button" style="padding:10px 18px; height:auto; border-radius:8px; font-weight:600; cursor:pointer;">🔍 Refresh Plugin List</button>
                                    </div>
                                </div>

                                <!-- SUB-TAB: Plugin List -->
                                <div id="subtab-uro_plugins" class="uwb-subtab-content">
                                    <div style="margin-bottom:16px;">
                                        <strong style="font-size:14px;">Installed Plugins (Stable IDs)</strong>
                                        <p style="margin:4px 0 0; color:var(--uwb-text-muted); font-size:12.5px;">Plugin ID được gán cố định theo thứ tự alphabet. ID không thay đổi khi bạn activate/deactivate plugin khác.</p>
                                    </div>
                                    <div id="uro-plugin-list-table" style="overflow:auto;">
                                        <table style="width:100%; border-collapse:collapse; font-size:13px;">
                                            <thead><tr style="background:var(--uwb-bg); border-bottom:2px solid var(--uwb-border);">
                                                <th style="padding:10px 12px; text-align:left; font-weight:700;">ID</th>
                                                <th style="padding:10px 12px; text-align:left; font-weight:700;">Plugin Name</th>
                                                <th style="padding:10px 12px; text-align:left; font-weight:700;">File</th>
                                                <th style="padding:10px 12px; text-align:left; font-weight:700;">Version</th>
                                            </tr></thead>
                                            <tbody id="uro-plugin-tbody"><tr><td colspan="4" style="padding:20px; text-align:center; color:var(--uwb-text-muted);">Loading...</td></tr></tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- SUB-TAB: Analyzer -->
                                <div id="subtab-uro_analyzer" class="uwb-subtab-content">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
                                        <div>
                                            <strong style="font-size:14px;">Page Load Analyzer</strong>
                                            <p style="margin:4px 0 0; color:var(--uwb-text-muted); font-size:12.5px;">Phân tích plugin nào đang load trên từng trang — hooks, memory, execution time.</p>
                                        </div>
                                        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                                            <label style="display:flex; align-items:center; gap:8px; font-size:13px; cursor:pointer;">
                                                <input type="checkbox" id="uro-analyzer-toggle" style="width:16px; height:16px;"> Enable Analyzer
                                            </label>
                                            <button id="btn-uro-get-log" type="button" class="button" style="padding:9px 18px; height:auto; border-radius:8px; font-weight:600; cursor:pointer;">📊 Refresh Log</button>
                                            <button id="btn-uro-clear-log" type="button" class="button" style="padding:9px 18px; height:auto; border-radius:8px; font-weight:600; cursor:pointer; color:#dc2626;">🗑 Clear</button>
                                        </div>
                                    </div>
                                    <div id="uro-analyzer-recs" style="display:none; background:#fffbeb; border:1px solid #fcd34d; border-radius:10px; padding:16px; margin-bottom:20px;">
                                        <strong style="font-size:13px;">💡 Recommendations:</strong>
                                        <ul id="uro-analyzer-recs-list" style="margin:8px 0 0; padding-left:20px; font-size:13px;"></ul>
                                    </div>
                                    <div id="uro-analyzer-log" style="font-size:12.5px; max-height:500px; overflow:auto;">
                                        <p style="color:var(--uwb-text-muted);">Enable Analyzer và duyệt web để thu thập dữ liệu.</p>
                                    </div>
                                </div>
                            </div>
        <!-- Rule Modal Template -->
        <div id="uro-rule-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:99999; align-items:center; justify-content:center;">
            <div style="background:#fff; border-radius:16px; width:90%; max-width:720px; max-height:90vh; overflow:auto; padding:32px; box-shadow:0 25px 60px rgba(0,0,0,0.35);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
                    <h3 style="margin:0; font-size:18px;" id="uro-modal-title">Edit Rule</h3>
                    <button id="uro-modal-close" style="background:none; border:none; cursor:pointer; font-size:22px; color:var(--uwb-text-muted);">✕</button>
                </div>
                <div style="display:flex; flex-direction:column; gap:16px;">
                    <div><label style="font-size:13px; font-weight:600; display:block; margin-bottom:6px;">Rule Name</label>
                        <input id="uro-rule-name" type="text" style="width:100%; padding:10px; border:1px solid var(--uwb-border); border-radius:8px; font-size:13px;" placeholder="e.g. Disable Elementor on non-editor pages">
                    </div>
                    <div style="display:flex; gap:16px; flex-wrap:wrap;">
                        <div style="flex:1; min-width:150px;"><label style="font-size:13px; font-weight:600; display:block; margin-bottom:6px;">Action</label>
                            <select id="uro-rule-action" style="width:100%; padding:10px; border:1px solid var(--uwb-border); border-radius:8px; font-size:13px;">
                                <option value="deny">🔴 Deny (Disable plugins)</option>
                                <option value="allow">🟢 Allow (Keep plugins)</option>
                            </select>
                        </div>
                        <div style="flex:1; min-width:150px;"><label style="font-size:13px; font-weight:600; display:block; margin-bottom:6px;">Priority</label>
                            <input id="uro-rule-priority" type="number" value="10" min="1" max="100" style="width:100%; padding:10px; border:1px solid var(--uwb-border); border-radius:8px; font-size:13px;">
                        </div>
                        <div style="flex:1; min-width:150px; display:flex; align-items:flex-end; padding-bottom:2px;">
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:13px; font-weight:600;">
                                <input type="checkbox" id="uro-rule-enabled" checked style="width:16px; height:16px;"> Enabled
                            </label>
                        </div>
                    </div>
                    <div><label style="font-size:13px; font-weight:600; display:block; margin-bottom:6px;">Plugins to Apply</label>
                        <div id="uro-rule-plugins-list" style="border:1px solid var(--uwb-border); border-radius:8px; max-height:220px; overflow:auto; padding:10px; display:flex; flex-direction:column; gap:6px;">
                            <span style="color:var(--uwb-text-muted); font-size:12px;">Loading plugin list...</span>
                        </div>
                    </div>
                    <hr style="border:none; border-top:1px solid var(--uwb-border);">
                    <strong style="font-size:13px;">Conditions <span style="font-weight:400; color:var(--uwb-text-muted);">(bỏ trống = áp dụng mọi điều kiện)</span></strong>
                    <div><label style="font-size:13px; font-weight:600; display:block; margin-bottom:6px;">URL Patterns <span style="font-weight:400;">(mỗi dòng 1 pattern, hỗ trợ /shop/*, /blog/)</span></label>
                        <textarea id="uro-rule-url" style="width:100%; padding:10px; border:1px solid var(--uwb-border); border-radius:8px; font-size:13px; height:70px;" placeholder="/shop/*&#10;/blog/"></textarea>
                    </div>
                    <div style="display:flex; gap:16px; flex-wrap:wrap;">
                        <div style="flex:1; min-width:150px;"><label style="font-size:13px; font-weight:600; display:block; margin-bottom:6px;">User Role</label>
                            <select id="uro-rule-role" multiple style="width:100%; padding:8px; border:1px solid var(--uwb-border); border-radius:8px; font-size:13px; height:100px;">
                                <option value="any">Any (all)</option>
                                <option value="guest">Guest (not logged in)</option>
                                <option value="logged_in">Logged In</option>
                                <option value="administrator">Administrator</option>
                                <option value="editor">Editor</option>
                                <option value="author">Author</option>
                                <option value="subscriber">Subscriber</option>
                            </select>
                        </div>
                        <div style="flex:1; min-width:150px;"><label style="font-size:13px; font-weight:600; display:block; margin-bottom:6px;">Device</label>
                            <select id="uro-rule-device" multiple style="width:100%; padding:8px; border:1px solid var(--uwb-border); border-radius:8px; font-size:13px; height:100px;">
                                <option value="any">Any</option>
                                <option value="desktop">Desktop</option>
                                <option value="tablet">Tablet</option>
                                <option value="mobile">Mobile</option>
                            </select>
                        </div>
                        <div style="flex:1; min-width:150px;"><label style="font-size:13px; font-weight:600; display:block; margin-bottom:6px;">WooCommerce</label>
                            <select id="uro-rule-woo" multiple style="width:100%; padding:8px; border:1px solid var(--uwb-border); border-radius:8px; font-size:13px; height:100px;">
                                <option value="any">Any</option>
                                <option value="shop">Shop</option>
                                <option value="product">Product</option>
                                <option value="cart">Cart</option>
                                <option value="checkout">Checkout</option>
                                <option value="account">My Account</option>
                            </select>
                        </div>
                    </div>
                    <div style="display:flex; gap:16px; flex-wrap:wrap;">
                        <div style="flex:1; min-width:150px;"><label style="font-size:13px; font-weight:600; display:block; margin-bottom:6px;">Post Types <span style="font-weight:400;">(comma-separated)</span></label>
                            <input id="uro-rule-post-type" type="text" style="width:100%; padding:10px; border:1px solid var(--uwb-border); border-radius:8px; font-size:13px;" placeholder="product, post">
                        </div>
                        <div style="flex:1; min-width:150px;"><label style="font-size:13px; font-weight:600; display:block; margin-bottom:6px;">Taxonomies <span style="font-weight:400;">(comma-separated)</span></label>
                            <input id="uro-rule-taxonomy" type="text" style="width:100%; padding:10px; border:1px solid var(--uwb-border); border-radius:8px; font-size:13px;" placeholder="product_cat, category">
                        </div>
                    </div>
                    <div style="display:flex; gap:16px; flex-wrap:wrap;">
                        <div style="display:flex; align-items:center; gap:24px; flex-wrap:wrap;">
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:13px;">
                                <select id="uro-rule-is-ajax" style="padding:8px 12px; border:1px solid var(--uwb-border); border-radius:8px; font-size:13px;">
                                    <option value="">AJAX: Any</option>
                                    <option value="1">AJAX Only</option>
                                    <option value="0">Non-AJAX Only</option>
                                </select>
                            </label>
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:13px;">
                                <select id="uro-rule-is-rest" style="padding:8px 12px; border:1px solid var(--uwb-border); border-radius:8px; font-size:13px;">
                                    <option value="">REST: Any</option>
                                    <option value="1">REST API Only</option>
                                    <option value="0">Non-REST Only</option>
                                </select>
                            </label>
                        </div>
                    </div>
                    <div><label style="font-size:13px; font-weight:600; display:block; margin-bottom:6px;">Callback <span style="font-weight:400;">(optional PHP function name)</span></label>
                        <input id="uro-rule-callback" type="text" style="width:100%; padding:10px; border:1px solid var(--uwb-border); border-radius:8px; font-size:13px;" placeholder="my_custom_check_function">
                    </div>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:24px;">
                    <button id="uro-modal-cancel" class="button" style="padding:10px 20px; height:auto; border-radius:8px; font-weight:600; cursor:pointer;">Cancel</button>
                    <button id="uro-modal-save" class="button button-primary" style="padding:10px 24px; height:auto; border-radius:8px; font-weight:600; cursor:pointer;">Save Rule</button>
                </div>
            </div>
        </div>

