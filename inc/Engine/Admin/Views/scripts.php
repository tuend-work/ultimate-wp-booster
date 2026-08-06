<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
// Views/scripts.php — All JavaScript for Admin panel
?>
        <script>
        (function($) {
            var uroNonce = '<?php echo esc_js( wp_create_nonce( "uwb_uro_nonce" ) ); ?>';
            var uroRules = [];
            var uroPlugins = [];
            var editingRuleIdx = -1;

            // ── Status Loader ────────────────────────────────────────────────
            function uroLoadStatus() {
                $.post(ajaxurl, { action: 'uwb_uro_get_status', nonce: uroNonce }, function(r) {
                    if (!r.success) return;
                    var d = r.data;
                    var runtimeOn = d.mu_plugin && d.mu_plugin.installed;
                    $('#uro-status-runtime').html(runtimeOn
                        ? '<span style="color:#16a34a;">✅ Active (MU Plugin installed)</span>'
                        : '<span style="color:#dc2626;">🔴 Inactive</span>');
                    $('#uro-status-compiled').text(d.compiled ? '✅ Rules compiled' : '❌ Not compiled');
                    $('#uro-status-rules').text(d.metadata ? d.metadata.rule_count + ' rule(s)' : '—');
                    var ts = d.metadata ? new Date(d.metadata.compile_time * 1000).toLocaleString() : '—';
                    $('#uro-status-time').text(ts);
                    if (runtimeOn) { $('#btn-uro-enable').hide(); $('#btn-uro-disable').show(); }
                    else { $('#btn-uro-enable').show(); $('#btn-uro-disable').hide(); }
                    // Parse saved rules
                    try {
                        var raw = JSON.parse(d.rules_json || '[]');
                        if (Array.isArray(raw)) { uroRules = raw; renderRulesList(); }
                    } catch(e) {}
                    // Analyzer toggle
                    $('#uro-analyzer-toggle').prop('checked', d.analyzer_on);
                });
            }

            // ── Scan Plugins ─────────────────────────────────────────────────
            function uroLoadPlugins(cb) {
                $.post(ajaxurl, { action: 'uwb_uro_scan_plugins', nonce: uroNonce }, function(r) {
                    if (r.success && Array.isArray(r.data)) {
                        uroPlugins = r.data;
                        renderPluginTable();
                        if (cb) cb(uroPlugins);
                    }
                });
            }

            function renderPluginTable() {
                var html = '';
                uroPlugins.forEach(function(p, i) {
                    html += '<tr style="border-bottom:1px solid var(--uwb-border);">'
                        + '<td style="padding:8px 12px; font-weight:600; color:var(--uwb-primary);">' + i + '</td>'
                        + '<td style="padding:8px 12px;">' + $('<span>').text(p.name).html() + '</td>'
                        + '<td style="padding:8px 12px; font-family:monospace; font-size:12px; color:var(--uwb-text-muted);">' + $('<span>').text(p.file).html() + '</td>'
                        + '<td style="padding:8px 12px; font-size:12px;">' + $('<span>').text(p.version || '—').html() + '</td>'
                        + '</tr>';
                });
                $('#uro-plugin-tbody').html(html || '<tr><td colspan="4" style="padding:20px; text-align:center; color:var(--uwb-text-muted);">No plugins found.</td></tr>');
            }

            // ── Rules List ───────────────────────────────────────────────────
            function renderRulesList() {
                if (uroRules.length === 0) {
                    $('#uro-no-rules').show();
                    $('#uro-rules-list > .uro-rule-card').remove();
                    return;
                }
                $('#uro-no-rules').hide();
                var html = '';
                uroRules.forEach(function(rule, i) {
                    var pluginCount = (rule.plugins || []).length;
                    var statusColor = rule.enabled !== false ? '#16a34a' : '#dc2626';
                    var statusText  = rule.enabled !== false ? 'Enabled' : 'Disabled';
                    var actionBadge = rule.action === 'deny'
                        ? '<span style="background:#fee2e2; color:#b91c1c; padding:2px 8px; border-radius:99px; font-size:11px; font-weight:700;">DENY</span>'
                        : '<span style="background:#d1fae5; color:#065f46; padding:2px 8px; border-radius:99px; font-size:11px; font-weight:700;">ALLOW</span>';
                    html += '<div class="uro-rule-card" style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:10px; padding:16px; display:flex; align-items:center; gap:16px; flex-wrap:wrap;">'
                        + '<div style="flex:1; min-width:200px;">'
                        + '<div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">'
                        + actionBadge
                        + '<strong style="font-size:14px;">' + $('<span>').text(rule.name || 'Rule #' + (i+1)).html() + '</strong>'
                        + '</div>'
                        + '<div style="font-size:12px; color:var(--uwb-text-muted);">Priority: ' + (rule.priority || 10) + ' · ' + pluginCount + ' plugin(s) · <span style="color:' + statusColor + ';">' + statusText + '</span></div>'
                        + '</div>'
                        + '<div style="display:flex; gap:8px;">'
                        + '<button type="button" class="button uro-btn-edit-rule" data-idx="' + i + '" style="padding:7px 14px; height:auto; border-radius:8px; font-weight:600; cursor:pointer; font-size:12px;">✏️ Edit</button>'
                        + '<button type="button" class="button uro-btn-delete-rule" data-idx="' + i + '" style="padding:7px 14px; height:auto; border-radius:8px; font-weight:600; cursor:pointer; font-size:12px; color:#dc2626;">🗑 Delete</button>'
                        + '</div>'
                        + '</div>';
                });
                $('#uro-rules-list').html(html);
            }

            // ── Show Result Message ──────────────────────────────────────────
            function uroMsg(txt, ok, errors) {
                var $el = $('#uro-result-msg');
                var content = txt;
                if (!ok && Array.isArray(errors) && errors.length > 0) {
                    content += '<ul style="margin:8px 0 0 16px; padding:0; list-style-type:disc; font-size:12px; font-weight:normal; text-align:left; line-height:1.4;">';
                    errors.forEach(function(err) {
                        content += '<li>' + $('<span>').text(err).html() + '</li>';
                    });
                    content += '</ul>';
                }
                $el.css({ background: ok ? '#d1fae5' : '#fee2e2', color: ok ? '#065f46' : '#b91c1c', border: '1px solid ' + (ok ? '#6ee7b7' : '#fca5a5') })
                    .html(content).slideDown();
                var delay = ok ? 6000 : 15000;
                if (window.uroMsgTimeout) clearTimeout(window.uroMsgTimeout);
                window.uroMsgTimeout = setTimeout(function() { $el.slideUp(); }, delay);
            }

            // ── Modal ────────────────────────────────────────────────────────
            function openModal(idx) {
                editingRuleIdx = idx;
                var rule = idx >= 0 ? uroRules[idx] : {};
                $('#uro-modal-title').text(idx >= 0 ? 'Edit Rule' : 'Add New Rule');
                $('#uro-rule-name').val(rule.name || '');
                $('#uro-rule-action').val(rule.action || 'deny');
                $('#uro-rule-priority').val(rule.priority || 10);
                $('#uro-rule-enabled').prop('checked', rule.enabled !== false);

                // URL
                var urls = (rule.conditions && rule.conditions.url) ? rule.conditions.url : [];
                $('#uro-rule-url').val(urls.join('\n'));

                // Post Type / Taxonomy
                $('#uro-rule-post-type').val( ((rule.conditions || {}).post_type || []).join(', ') );
                $('#uro-rule-taxonomy').val( ((rule.conditions || {}).taxonomy || []).join(', ') );

                // Role (multi-select)
                var roles = (rule.conditions || {}).user_role || [];
                $('#uro-rule-role option').each(function() {
                    $(this).prop('selected', roles.indexOf($(this).val()) !== -1);
                });

                // Device
                var devs = (rule.conditions || {}).device || [];
                $('#uro-rule-device option').each(function() {
                    $(this).prop('selected', devs.indexOf($(this).val()) !== -1);
                });

                // WooCommerce
                var woos = (rule.conditions || {}).woocommerce || [];
                $('#uro-rule-woo option').each(function() {
                    $(this).prop('selected', woos.indexOf($(this).val()) !== -1);
                });

                // AJAX / REST
                var ajax = (rule.conditions || {}).is_ajax;
                $('#uro-rule-is-ajax').val(ajax === true ? '1' : ajax === false ? '0' : '');
                var rest = (rule.conditions || {}).is_rest;
                $('#uro-rule-is-rest').val(rest === true ? '1' : rest === false ? '0' : '');

                // Callback
                $('#uro-rule-callback').val( (rule.conditions || {}).callback || '' );

                // Plugins checkboxes
                var selPlugins = rule.plugins || [];
                var plugHtml = '';
                uroPlugins.forEach(function(p) {
                    var chk = selPlugins.indexOf(p.file) !== -1 ? 'checked' : '';
                    plugHtml += '<label style="display:flex; align-items:center; gap:8px; cursor:pointer; padding:4px 0; border-bottom:1px solid #f1f5f9;">'
                        + '<input type="checkbox" class="uro-plugin-chk" value="' + $('<span>').text(p.file).html() + '" ' + chk + ' style="width:15px; height:15px;">'
                        + '<span style="font-size:12.5px;"><strong>' + $('<span>').text(p.name).html() + '</strong> <span style="color:var(--uwb-text-muted);">(' + $('<span>').text(p.file).html() + ')</span></span>'
                        + '</label>';
                });
                $('#uro-rule-plugins-list').html(plugHtml || '<span style="color:var(--uwb-text-muted); font-size:12px;">No plugins scanned yet. Use "Refresh Plugin List" button.</span>');

                $('#uro-rule-modal').css('display', 'flex');
            }

            function closeModal() { $('#uro-rule-modal').hide(); }

            function collectRule() {
                var urls = $('#uro-rule-url').val().split('\n').map(function(u) { return u.trim(); }).filter(Boolean);
                var ptRaw = $('#uro-rule-post-type').val().trim();
                var taxRaw = $('#uro-rule-taxonomy').val().trim();
                var plugins = [];
                $('.uro-plugin-chk:checked').each(function() { plugins.push($(this).val()); });
                var roles = [], devs = [], woos = [];
                $('#uro-rule-role option:selected').each(function() { roles.push($(this).val()); });
                $('#uro-rule-device option:selected').each(function() { devs.push($(this).val()); });
                $('#uro-rule-woo option:selected').each(function() { woos.push($(this).val()); });
                var ajaxVal = $('#uro-rule-is-ajax').val();
                var restVal = $('#uro-rule-is-rest').val();
                return {
                    id: (editingRuleIdx >= 0 && uroRules[editingRuleIdx].id) ? uroRules[editingRuleIdx].id : 'rule-' + Date.now(),
                    name: $('#uro-rule-name').val() || 'Rule',
                    enabled: $('#uro-rule-enabled').is(':checked'),
                    priority: parseInt($('#uro-rule-priority').val()) || 10,
                    action: $('#uro-rule-action').val(),
                    plugins: plugins,
                    conditions: {
                        url: urls,
                        post_type: ptRaw ? ptRaw.split(',').map(function(s) { return s.trim(); }) : [],
                        taxonomy: taxRaw ? taxRaw.split(',').map(function(s) { return s.trim(); }) : [],
                        woocommerce: woos,
                        user_role: roles,
                        device: devs,
                        is_ajax: ajaxVal === '1' ? true : ajaxVal === '0' ? false : null,
                        is_rest: restVal === '1' ? true : restVal === '0' ? false : null,
                        callback: $('#uro-rule-callback').val().trim() || null,
                    }
                };
            }

            // ── Event Listeners ──────────────────────────────────────────────
            $(document).ready(function() {
                uroLoadStatus();

                // Load plugins when switching to that sub-tab
                $('[data-subtab="uro_plugins"]').on('click', function() { uroLoadPlugins(); });
                $('[data-subtab="uro_analyzer"]').on('click', function() { uroFetchAnalyzerLog(); });

                // Enable / Disable Runtime
                $('#btn-uro-enable').on('click', function() {
                    $(this).prop('disabled', true).text('Installing...');
                    $.post(ajaxurl, { action: 'uwb_uro_toggle_runtime', nonce: uroNonce, enable: 1 }, function(r) {
                        $('#btn-uro-enable').prop('disabled', false).text('⚡ Enable Runtime');
                        if (r.success || r.runtime_enabled) { uroMsg('✅ Runtime enabled! MU Plugin installed & compiled.', true); uroLoadStatus(); }
                        else { uroMsg('❌ ' + (r.message || r.data || 'Error'), false, r.errors); }
                    });
                });
                $('#btn-uro-disable').on('click', function() {
                    if (!confirm('Disable Runtime Optimizer? MU Plugin sẽ bị xóa.')) return;
                    $.post(ajaxurl, { action: 'uwb_uro_toggle_runtime', nonce: uroNonce, enable: 0 }, function(r) {
                        uroMsg('Runtime Optimizer disabled.', true); uroLoadStatus();
                    });
                });

                // Rebuild
                $('#btn-uro-rebuild').on('click', function() {
                    var $btn = $(this).prop('disabled', true).text('🔨 Compiling...');
                    $.post(ajaxurl, { action: 'uwb_uro_rebuild', nonce: uroNonce }, function(r) {
                        $btn.prop('disabled', false).text('🔨 Rebuild');
                        uroMsg(r.success ? '✅ ' + r.message : '❌ ' + (r.message || r.data), r.success, r.errors);
                        if (r.success) uroLoadStatus();
                    });
                });

                // Save & Compile
                $('#btn-uro-save-rules').on('click', function() {
                    var $btn = $(this).prop('disabled', true).text('⏳ Saving...');
                    $.post(ajaxurl, { action: 'uwb_uro_save_rules', nonce: uroNonce, rules: JSON.stringify(uroRules) }, function(r) {
                        $btn.prop('disabled', false).text('💾 Save & Compile');
                        uroMsg(r.success ? '✅ ' + r.message : '❌ ' + (r.message || r.data), r.success, r.errors);
                        if (r.success) uroLoadStatus();
                    });
                });

                // Scan Plugins
                $('#btn-uro-scan-plugins').on('click', function() {
                    $(this).prop('disabled', true).text('Scanning...');
                    var $btn = $(this);
                    uroLoadPlugins(function() { $btn.prop('disabled', false).text('🔍 Refresh Plugin List'); });
                });

                // Add Rule
                $('#btn-uro-add-rule').on('click', function() {
                    if (uroPlugins.length === 0) {
                        uroLoadPlugins(function() { openModal(-1); });
                    } else { openModal(-1); }
                });

                // Edit Rule
                $(document).on('click', '.uro-btn-edit-rule', function(e) {
                    e.preventDefault();
                    var idx = parseInt($(this).data('idx'));
                    if (uroPlugins.length === 0) { uroLoadPlugins(function() { openModal(idx); }); }
                    else { openModal(idx); }
                });

                // Delete Rule
                $(document).on('click', '.uro-btn-delete-rule', function(e) {
                    e.preventDefault();
                    var idx = parseInt($(this).data('idx'));
                    if (!confirm('Delete this rule?')) return;
                    uroRules.splice(idx, 1);
                    renderRulesList();
                });

                // Modal Save
                $('#uro-modal-save').on('click', function() {
                    var rule = collectRule();
                    if (!rule.plugins.length) { alert('Please select at least one plugin for this rule.'); return; }
                    if (editingRuleIdx >= 0) { uroRules[editingRuleIdx] = rule; }
                    else { uroRules.push(rule); }
                    closeModal(); renderRulesList();
                });

                $('#uro-modal-close, #uro-modal-cancel').on('click', closeModal);
                $('#uro-rule-modal').on('click', function(e) { if ($(e.target).is('#uro-rule-modal')) closeModal(); });

                // Analyzer
                $('#uro-analyzer-toggle').on('change', function() {
                    $.post(ajaxurl, { action: 'uwb_uro_toggle_analyzer', nonce: uroNonce, enable: $(this).is(':checked') ? 1 : 0 });
                });
                $('#btn-uro-get-log').on('click', uroFetchAnalyzerLog);
                $('#btn-uro-clear-log').on('click', function() {
                    if (!confirm('Clear all analyzer logs?')) return;
                    $.post(ajaxurl, { action: 'uwb_uro_clear_analyzer', nonce: uroNonce }, function() { uroFetchAnalyzerLog(); });
                });
            });

            function uroFetchAnalyzerLog() {
                $('#uro-analyzer-log').html('<p style="color:var(--uwb-text-muted);">Loading...</p>');
                $.post(ajaxurl, { action: 'uwb_uro_get_analyzer_log', nonce: uroNonce }, function(r) {
                    if (!r.success) return;
                    var log = r.data.log || [];
                    var recs = r.data.recommendations || [];
                    // Recommendations
                    if (recs.length) {
                        var rHtml = '';
                        recs.forEach(function(rec) { rHtml += '<li>' + $('<span>').text(rec).html() + '</li>'; });
                        $('#uro-analyzer-recs-list').html(rHtml);
                        $('#uro-analyzer-recs').slideDown();
                    } else { $('#uro-analyzer-recs').hide(); }
                    // Log table
                    if (!log.length) { $('#uro-analyzer-log').html('<p style="color:var(--uwb-text-muted);">No data yet.</p>'); return; }
                    var html = '<table style="width:100%; border-collapse:collapse; font-size:12px;">'
                        + '<thead><tr style="background:var(--uwb-bg); border-bottom:2px solid var(--uwb-border);">'
                        + '<th style="padding:8px; text-align:left;">URL</th><th style="padding:8px;">Time (ms)</th><th style="padding:8px;">Peak Mem</th><th style="padding:8px;">Plugins</th><th style="padding:8px;">PostType</th>'
                        + '</tr></thead><tbody>';
                    log.forEach(function(entry, idx) {
                        html += '<tr class="uro-analyzer-row" data-idx="' + idx + '" style="border-bottom:1px solid var(--uwb-border); cursor:pointer;" title="Click to view details">'
                            + '<td style="padding:8px; max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' + $('<span>').text(entry.url || '').html() + '</td>'
                            + '<td style="padding:8px; text-align:center;">' + (entry.duration_ms || '—') + '</td>'
                            + '<td style="padding:8px; text-align:center;">' + ((entry.peak_memory || 0) / 1048576).toFixed(1) + ' MB</td>'
                            + '<td style="padding:8px; text-align:center;">' + (entry.plugins_loaded || 0) + '</td>'
                            + '<td style="padding:8px; text-align:center;">' + $('<span>').text(entry.post_type || '—').html() + '</td>'
                            + '</tr>';
                    });
                    html += '</tbody></table>';
                    $('#uro-analyzer-log').html(html);

                    // Click handler to toggle individual plugin load times
                    $(document).off('click', '.uro-analyzer-row').on('click', '.uro-analyzer-row', function() {
                        var idx = $(this).data('idx');
                        var entry = log[idx];
                        var $next = $(this).next();
                        if ($next.hasClass('uro-details-row')) {
                            $next.toggle();
                            return;
                        }

                        var pTimes = entry.plugin_load_times || {};
                        var pList = entry.plugin_list || [];

                        // Map and sort plugins by load duration (slowest first)
                        var sortedPlugins = pList.map(function(p) {
                            return { file: p, time: pTimes[p] || 0 };
                        });
                        sortedPlugins.sort(function(a, b) { return b.time - a.time; });

                        var listHtml = '';
                        sortedPlugins.forEach(function(p) {
                            var tStr = p.time > 0 ? ' <span style="color:#ef4444; font-weight:600; float:right;">' + p.time.toFixed(1) + 'ms</span>' : ' <span style="color:#94a3b8; float:right;">—</span>';
                            listHtml += '<div style="padding:8px 12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; font-size:12px; margin-bottom:6px; overflow:hidden; zoom:1;">'
                                     + '<span style="font-weight:600; color:#0f172a;">' + p.file + '</span>'
                                     + tStr
                                     + '</div>';
                        });

                        var detailsHtml = '<tr class="uro-details-row" style="background:#f8fafc;"><td colspan="5" style="padding:20px; border-bottom:1px solid var(--uwb-border);">'
                                        + '<div style="display:grid; grid-template-columns: 1.2fr 0.8fr; gap:24px;">'
                                        + '<div>'
                                        + '<strong style="font-size:13px; display:block; margin-bottom:12px; color:#1e293b;">Loaded Plugins &amp; Individual Load Times:</strong>'
                                        + '<div style="max-height:280px; overflow-y:auto; padding-right:8px;">' 
                                        + (listHtml || '<p style="color:#64748b;">No plugins detected.</p>')
                                        + '</div>'
                                        + '</div>'
                                        + '<div>'
                                        + '<strong style="font-size:13px; display:block; margin-bottom:12px; color:#1e293b;">Request Profile Details:</strong>'
                                        + '<div style="display:flex; flex-direction:column; gap:10px; font-size:12.5px; color:#475569;">'
                                        + '<div><strong>Requested At:</strong> ' + entry.time + '</div>'
                                        + '<div><strong>Total Initialisation:</strong> ' + entry.duration_ms + ' ms</div>'
                                        + '<div><strong>Peak Memory Usage:</strong> ' + ((entry.peak_memory || 0) / 1048576).toFixed(2) + ' MB</div>'
                                        + '<div><strong>Post Type / Context:</strong> ' + (entry.post_type || '—') + '</div>'
                                        + '</div>'
                                        + '</div>'
                                        + '</div>'
                                        + '</td></tr>';

                        $(this).after(detailsHtml);
                    });
                });
            }
        })(jQuery);
        </script>

        <script>
        jQuery(document).ready(function($) {
            // Async load Redis memory info for Object Cache dashboard node
            if ($('#uwb-oc-mem-inline').length > 0) {
                var adminNonce = '<?php echo esc_js( wp_create_nonce( "uwb_admin_nonce" ) ); ?>';
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'uwb_get_redis_memory_info',
                        nonce: adminNonce
                    },
                    success: function(resp) {
                        if (resp.success && resp.data && resp.data.text) {
                            $('#uwb-oc-mem-inline').html('— ' + resp.data.text);
                        } else {
                            $('#uwb-oc-mem-inline').html('');
                        }
                    },
                    error: function() {
                        $('#uwb-oc-mem-inline').html('');
                    }
                });
            }
            $('#uwb_auto_collect_params').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#uwb-collected-params-group').slideDown();
                } else {
                    $('#uwb-collected-params-group').slideUp();
                }
            });

            // Show/hide heartbeat interval based on control mode
            $('#uwb_heartbeat_control').on('change', function() {
                if ($(this).val() === 'reduce') {
                    $('#uwb-heartbeat-interval-row').show();
                } else {
                    $('#uwb-heartbeat-interval-row').hide();
                }
            });

            // Show/hide Delay JS exclusions textarea based on toggle
            $('input[name="uwb_delay_js"]').on('change', function() {
                var enabled = $('input[name="uwb_delay_js"]:checked').val() === '1';
                $('.uwb-opt-disabled').toggle(!enabled);
            });

            // Show/hide Lazy Load Elements textareas based on toggle
            $('input[name="uwb_html_lazy_load_elements_enabled"]').on('change', function() {
                var val = $('input[name="uwb_html_lazy_load_elements_enabled"]:checked').val();
                if (val === '1') {
                    $('#uwb-lazy-elements-textarea-wrap').slideDown();
                } else {
                    $('#uwb-lazy-elements-textarea-wrap').slideUp();
                }
            });

            // CDN Provider switch toggle (Cloudflare R2 vs Other S3)
            $('#uwb_cdn_provider').on('change', function() {
                var val = $(this).val();
                if (val === 'cloudflare_r2') {
                    $('.uwb-cdn-cf-field, .uwb-cdn-cf-guide').slideDown();
                    $('.uwb-cdn-s3-field').slideUp();
                } else {
                    $('.uwb-cdn-cf-field, .uwb-cdn-cf-guide').slideUp();
                    $('.uwb-cdn-s3-field').slideDown();
                }
            });

            // Test CDN Connection
            $('#btn-test-cdn-connection').on('click', function() {
                var $btn = $(this);
                var $res = $('#uwb-cdn-test-result');
                var nonce = '<?php echo esc_js( wp_create_nonce( "uwb_admin_nonce" ) ); ?>';

                $btn.prop('disabled', true).text('Testing Connection...');
                $res.hide().html('');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'uwb_test_cdn_connection',
                        nonce: nonce,
                        provider: $('#uwb_cdn_provider').val(),
                        account_id: $('#uwb_cdn_account_id').val(),
                        access_key: $('#uwb_cdn_access_key').val(),
                        secret_key: $('#uwb_cdn_secret_key').val(),
                        bucket: $('#uwb_cdn_bucket').val(),
                        endpoint: $('#uwb_cdn_endpoint').val(),
                        region: $('#uwb_cdn_region').val(),
                        custom_domain: $('#uwb_cdn_custom_domain').val()
                    },
                    success: function(resp) {
                        $btn.prop('disabled', false).html('<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle; margin-right:4px;"><polyline points="20 6 9 17 4 12"/></svg> Test CDN Connection');
                        if (resp.success) {
                            var html = '<div>✅ ' + resp.data.message + '</div>';
                            if (resp.data.file_url) {
                                html += '<div style="margin-top:8px; font-weight:normal; word-break:break-all;">🔗 Direct File URL: <a href="' + resp.data.file_url + '" target="_blank" rel="noopener noreferrer" style="color:#047857; text-decoration:underline; font-weight:700;">' + resp.data.file_url + ' &rarr;</a></div>';
                            }
                            $res.css({'padding':'12px 16px', 'background':'#d1fae5', 'color':'#065f46', 'border':'1px solid #6ee7b7', 'border-radius':'8px', 'font-size':'13px', 'font-weight':'600'}).html(html).slideDown();
                        } else {
                            $res.css({'padding':'12px 16px', 'background':'#fee2e2', 'color':'#991b1b', 'border':'1px solid #fca5a5', 'border-radius':'8px', 'font-size':'13px', 'font-weight':'600'}).html('❌ Error: ' + resp.data).slideDown();
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text('Test CDN Connection');
                        $res.css({'padding':'12px 16px', 'background':'#fee2e2', 'color':'#991b1b', 'border':'1px solid #fca5a5', 'border-radius':'8px', 'font-size':'13px', 'font-weight':'600'}).html('❌ Server request failed.').slideDown();
                    }
                });
            });

            // Sync Media Library to CDN Batch Handler
            $('#btn-sync-media-cdn').on('click', function() {
                var $btn = $(this);
                var $progressWrap = $('#uwb-sync-cdn-progress-wrap');
                var $progressFill = $('#uwb-sync-cdn-progress-fill');
                var $statusText = $('#uwb-sync-cdn-status-text');
                var nonce = '<?php echo esc_js( wp_create_nonce( "uwb_admin_nonce" ) ); ?>';

                if (!confirm('Start batch syncing all Media Library files to CDN S3/R2 storage?')) {
                    return;
                }

                $btn.prop('disabled', true).text('Syncing Media...');
                $progressWrap.slideDown();
                $progressFill.css('width', '5%');
                $statusText.text('Starting media sync batch 1...');

                function processBatch(paged) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'uwb_sync_media_to_cdn',
                            nonce: nonce,
                            paged: paged
                        },
                        success: function(resp) {
                            if (resp.success) {
                                var d = resp.data;
                                if (d.total > 0) {
                                    var pct = Math.min(100, Math.round(((d.paged - 1) * 20 / d.total) * 100));
                                    $progressFill.css('width', pct + '%');
                                } else {
                                    $progressFill.css('width', '100%');
                                }
                                $statusText.text(d.message);

                                if (!d.completed) {
                                    processBatch(d.paged);
                                } else {
                                    $progressFill.css('width', '100%');
                                    $btn.prop('disabled', false).text('Sync Existing Media Library to CDN');
                                    alert('🎉 ' + d.message);
                                }
                            } else {
                                $btn.prop('disabled', false).text('Sync Existing Media Library to CDN');
                                alert('❌ Error: ' + resp.data);
                            }
                        },
                        error: function() {
                            $btn.prop('disabled', false).text('Sync Existing Media Library to CDN');
                            alert('❌ Batch sync failed due to server error.');
                        }
                    });
                }

                processBatch(1);
            });
        });
        </script>


        <script>
        jQuery(document).ready(function($) {
            // Tab Switcher Logic
            $('.uwb-nav-item').on('click', function() {
                var tabId = $(this).data('tab');
                
                $('.uwb-nav-item').removeClass('active');
                $(this).addClass('active');
                
                $('.uwb-tab-content').removeClass('active');
                $('#tab-' + tabId).addClass('active');

                localStorage.setItem('uwb_active_tab', tabId);

                // Hide submit row on non-settings tabs (these tabs have their own forms)
                if (['url_status', 'import_export'].indexOf(tabId) !== -1) {
                    $('#uwb-submit-row').hide();
                } else if (tabId === 'advanced_tools') {
                    var sub = $('#tab-advanced_tools').find('.uwb-sub-tab-item.active').data('subtab');
                    if (sub === 'plugin_load_manager') {
                        $('#uwb-submit-row').hide();
                    } else {
                        $('#uwb-submit-row').show();
                    }
                } else {
                    $('#uwb-submit-row').show();
                }

                // Load URL table on first visit
                if ((tabId === 'url_status' || tabId === 'preload_settings') && !uwbUrlTableLoaded) {
                    uwbUrlTableLoaded = true;
                    loadUrlTable();
                }
            });

            // Sub-tabs Switcher Logic
            $('.uwb-sub-tab-item').on('click', function() {
                var subtabId = $(this).data('subtab');
                var parentTab = $(this).closest('.uwb-tab-content');
                
                $(this).siblings('.uwb-sub-tab-item').removeClass('active');
                $(this).addClass('active');
                
                $(this).parent().siblings('.uwb-subtab-content').removeClass('active');
                $('#subtab-' + subtabId).addClass('active');

                var tabId = parentTab.attr('id').replace('tab-', '');
                localStorage.setItem('uwb_subtab_' + tabId, subtabId);

                if (subtabId === 'preload_status' && !uwbUrlTableLoaded) {
                    uwbUrlTableLoaded = true;
                    loadUrlTable();
                }

                // Hide submit row on Plugin Load Manager sub-tab, show on others
                if (subtabId === 'plugin_load_manager') {
                    $('#uwb-submit-row').hide();
                } else if (tabId === 'advanced_tools') {
                    $('#uwb-submit-row').show();
                }
            });

            // Toggle Switches interactive handler
            $(document).on('change', '.uwb-toggle-input', function() {
                var $container = $(this).closest('.uwb-toggle-container');
                $container.find('.uwb-toggle-btn').removeClass('active');
                $(this).closest('.uwb-toggle-btn').addClass('active');
            });

            // Module toggle switches interactive handler (Segmented ON/OFF)
            $(document).on('change', '.uwb-module-toggle-cb', function() {
                var $radio = $(this);
                var val = parseInt($radio.val());
                var isEnabled = (val === 1);
                var $banner = $radio.closest('[data-content-id]');
                var contentId = $banner.data('content-id');
                var $wrapper = $('#' + contentId);

                // Update active state in container
                var $container = $radio.closest('.uwb-toggle-container');
                $container.find('.uwb-toggle-btn').removeClass('active');
                $radio.closest('.uwb-toggle-btn').addClass('active');

                if (isEnabled) {
                    $wrapper.stop().slideDown(250);
                } else {
                    $wrapper.stop().slideUp(250);
                }
            });

            // Sidebar Collapse/Expand Toggle
            $('#uwb-toggle-sidebar').on('click', function() {
                $('.uwb-layout').toggleClass('collapsed');
                if ($('.uwb-layout').hasClass('collapsed')) {
                    $('.toggle-icon-collapse').hide();
                    $('.toggle-icon-expand').show();
                    localStorage.setItem('uwb_sidebar_collapsed', '1');
                } else {
                    $('.toggle-icon-collapse').show();
                    $('.toggle-icon-expand').hide();
                    localStorage.setItem('uwb_sidebar_collapsed', '0');
                }
            });
            
            // Restore sidebar state
            if (localStorage.getItem('uwb_sidebar_collapsed') === '1') {
                $('.uwb-layout').addClass('collapsed');
                $('.toggle-icon-collapse').hide();
                $('.toggle-icon-expand').show();
            }

            // Preloader Live Tracker
            var checkInterval;
            var nonce = '<?php echo esc_js( wp_create_nonce( "uwb_admin_nonce" ) ); ?>';
            var uwbUrlTableLoaded = false;
            var uwbUrlPage = 1;
            var uwbUrlStatus = '';
            var uwbUrlSearch = '';
            var uwbUrlWc = 0;
            var uwbUrlOrderby = 'priority';
            var uwbUrlOrder = 'ASC';

            function updatePreloadStatus() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'uwb_get_preload_status',
                        nonce: nonce
                    },
                    success: function(res) {
                        if (res.success) {
                            var data = res.data;
                            $('#queue-pending').text(data.pending);
                            $('#queue-processing').text(data.processing);
                            $('#queue-completed').text(data.completed);
                            $('#queue-failed').text(data.failed);

                            var total = data.total;
                            var processed = data.completed + data.failed + data.processing;
                            
                            $('#preload-progress-nums').text(processed + ' / ' + total + ' URLs');
                            
                            var pct = 0;
                            if (total > 0) {
                                pct = Math.round((processed / total) * 100);
                            }
                            
                            $('#preload-progress-pct').text('Progress: ' + pct + '%');
                            $('#preload-progress-fill').css('width', pct + '%');

                            if (data.running === 1) {
                                $('#btn-start-preload').hide();
                                $('#btn-stop-preload').show();
                            } else {
                                if (total > 0 && processed >= total && data.pending === 0 && data.processing === 0) {
                                    // Done! Stop polling.
                                    if (checkInterval) {
                                        clearInterval(checkInterval);
                                        checkInterval = null;
                                    }
                                }
                                $('#btn-start-preload').show();
                                $('#btn-stop-preload').hide();
                            }

                            if (data.log !== undefined) {
                                var $logTextarea = $('#uwb-preload-log');
                                if ($logTextarea.length) {
                                    $logTextarea.val(data.log);
                                    // Auto scroll to bottom
                                    $logTextarea.scrollTop($logTextarea[0].scrollHeight);
                                }
                            }
                        }
                    }
                });
            }

            // Start Preload Click
            $('#btn-start-preload').on('click', function(e) {
                e.preventDefault();
                var btn = $(this);
                btn.prop('disabled', true).text('Parsing Sitemap...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'uwb_start_preload',
                        nonce: nonce
                    },
                    success: function(res) {
                        btn.prop('disabled', false).html('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg> Start Preloading');
                        if (res.success) {
                            updatePreloadStatus();
                            if (!checkInterval) {
                                checkInterval = setInterval(updatePreloadStatus, 15000);
                            }
                        } else {
                            alert(res.data.message);
                        }
                    },
                    error: function() {
                        btn.prop('disabled', false).html('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg> Start Preloading');
                        alert('Server connection error.');
                    }
                });
            });

            // Stop Preload Click
            $('#btn-stop-preload').on('click', function(e) {
                e.preventDefault();
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'uwb_stop_preload',
                        nonce: nonce
                    },
                    success: function(res) {
                        updatePreloadStatus();
                        if (checkInterval) {
                            clearInterval(checkInterval);
                            checkInterval = null;
                        }
                    }
                });
            });

            // Clear Preload Click
            $('#btn-clear-preload').on('click', function(e) {
                e.preventDefault();
                if (confirm('Are you sure you want to clear the preloading queue?')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'uwb_clear_preload',
                            nonce: nonce
                        },
                        success: function(res) {
                            updatePreloadStatus();
                            if (checkInterval) {
                                clearInterval(checkInterval);
                                checkInterval = null;
                            }
                        }
                    });
                }
            });

            // Clear Preloader Logs
            $(document).on('click', '#uwb-clear-preload-log-btn', function() {
                if (confirm('Are you sure you want to clear the logs?')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'uwb_clear_preload_log',
                            nonce: nonce
                        },
                        success: function(res) {
                            if (res.success) {
                                $('#uwb-preload-log').val('No logs available. Start a preload run to generate logs.');
                            }
                        }
                    });
                }
            });

            // Init status on load
            updatePreloadStatus();
            // Poll status only for display; cron or WP-CLI performs the actual preload work.
            checkInterval = setInterval(updatePreloadStatus, 15000);

            // Toggle Redis configuration fields
            function toggleRedisFields() {
                var connType = $('#uwb_redis_conn_type').val();
                if (connType === 'socket') {
                    $('#redis-tcp-settings').hide();
                    $('#redis-socket-settings').show();
                } else {
                    $('#redis-tcp-settings').show();
                    $('#redis-socket-settings').hide();
                }
            }
            $('#uwb_redis_conn_type').on('change', toggleRedisFields);
            toggleRedisFields();

            // Toggle Object Cache fields depending on type (None, Redis, Memcached)
            function toggleObjectCacheFields() {
                var ocType = $('#uwb_redis_enabled').val();
                if (ocType === '0') {
                    $('#uwb-oc-conn-type-group').hide();
                    $('#redis-tcp-settings').hide();
                    $('#redis-socket-settings').hide();
                    $('#uwb-oc-db-group').hide();
                    $('#uwb-oc-password-group').hide();
                    $('#uwb-oc-settings-test-group').hide();
                } else if (ocType === '2') {
                    // Memcached: show Host/Port, hide others
                    $('#uwb-oc-conn-type-group').hide();
                    if ($('#uwb_redis_port').val() === '6379') {
                        $('#uwb_redis_port').val('11211');
                    }
                    $('label[for="uwb_redis_host"]').text('Memcached Host');
                    $('label[for="uwb_redis_port"]').text('Memcached Port');
                    $('#redis-tcp-settings').show();
                    $('#redis-socket-settings').hide();
                    $('#uwb-oc-db-group').hide();
                    $('#uwb-oc-password-group').hide();
                    $('#uwb-oc-settings-test-group').show();
                } else {
                    // Redis
                    $('#uwb-oc-conn-type-group').show();
                    $('#uwb-oc-db-group').show();
                    $('#uwb-oc-password-group').show();
                    $('label[for="uwb_redis_host"]').text('Redis Host');
                    $('label[for="uwb_redis_port"]').text('Redis Port');
                    if ($('#uwb_redis_port').val() === '11211') {
                        $('#uwb_redis_port').val('6379');
                    }
                    toggleRedisFields();
                    $('#uwb-oc-settings-test-group').show();
                }
            }
            $('#uwb_redis_enabled').on('change', toggleObjectCacheFields);
            toggleObjectCacheFields();

            // Toggle Custom Cron instructions
            function toggleCronFields() {
                var preloadEnabled = $('#uwb_preload_enabled').val();
                if (preloadEnabled === '2') {
                    $('#uwb-custom-cron-info').slideDown(250);
                    $('#uwb-litespeed-cron-info').slideUp(250);
                } else if (preloadEnabled === '3') {
                    $('#uwb-custom-cron-info').slideUp(250);
                    $('#uwb-litespeed-cron-info').slideDown(250);
                } else {
                    $('#uwb-custom-cron-info').slideUp(250);
                    $('#uwb-litespeed-cron-info').slideUp(250);
                }
            }
            $('#uwb_preload_enabled').on('change', toggleCronFields);
            toggleCronFields();



            // Clear Critical CSS Cache via AJAX
            $('#btn-clear-critical-css').on('click', function(e) {
                e.preventDefault();
                var btn = $(this);
                btn.prop('disabled', true).text('Clearing...');
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'uwb_clear_critical_css_cache',
                        nonce: '<?php echo wp_create_nonce("uwb_admin_nonce"); ?>'
                    },
                    success: function(res) {
                        btn.prop('disabled', false).text('Clear Critical CSS Cache');
                        if (res.success) {
                            alert(res.data.message || '⚡ Đã xóa Critical CSS Cache thành công!');
                        } else {
                            alert('Có lỗi xảy ra: ' + (res.data ? res.data.message : 'Unknown error'));
                        }
                    },
                    error: function() {
                        btn.prop('disabled', false).text('Clear Critical CSS Cache');
                        alert('Lỗi kết nối máy chủ!');
                    }
                });
            });

            // Toggle individual category lifespan fields
            $('.uwb-bc-cat-toggle').on('change', function() {
                var wrap = $(this).closest('div').find('.uwb-bc-lifespan-wrap');
                if ($(this).val() === '1') {
                    wrap.css('display', 'flex');
                } else {
                    wrap.hide();
                }
            });

            // Toggle Logged-in Cache Lifespan fields
            function toggleLoggedInFields() {
                var cacheLoggedIn = $('#uwb_cache_logged_in').val();
                if (cacheLoggedIn === '2') {
                    $('#uwb-logged-in-lifespan-group').show();
                    $('#uwb-logged-in-divider').show();
                } else {
                    $('#uwb-logged-in-lifespan-group').hide();
                    $('#uwb-logged-in-divider').hide();
                }
            }
            $('#uwb_cache_logged_in').on('change', toggleLoggedInFields);
            toggleLoggedInFields();

            // Toggle XML Sitemap Cache fields
            function toggleXMLFields() {
                var cacheXML = $('#uwb_cache_xml_sitemaps').val();
                if (cacheXML === '1') {
                    $('#uwb-xml-sitemaps-lifespan-group').show();
                    $('#uwb-xml-sitemaps-divider').show();
                } else {
                    $('#uwb-xml-sitemaps-lifespan-group').hide();
                    $('#uwb-xml-sitemaps-divider').hide();
                }
            }
            $('#uwb_cache_xml_sitemaps').on('change', toggleXMLFields);
            toggleXMLFields();

            // Toggle PHP Cache fields
            function togglePHPFields() {
                var cachePHP = $('#uwb_cache_php').val();
                if (cachePHP === '1') {
                    $('#uwb-php-lifespan-group').show();
                    $('#uwb-php-divider').show();
                } else {
                    $('#uwb-php-lifespan-group').hide();
                    $('#uwb-php-divider').hide();
                }
            }
            $('#uwb_cache_php').on('change', togglePHPFields);
            togglePHPFields();

            // Click to copy and auto-fill lifespan conversion helper values
            $('.uwb-copy-val').on('click', function(e) {
                e.preventDefault();
                var $code = $(this);
                var val = $code.text().trim();
                
                // Copy to clipboard
                var $temp = $("<input>");
                $("body").append($temp);
                $temp.val(val).select();
                document.execCommand("copy");
                $temp.remove();

                // Auto-fill the corresponding input
                var $group = $code.closest('.uwb-form-group');
                var $input = $group.find('input[type="number"]');
                if ($input.length) {
                    $input.val(val).trigger('change');
                }

                // Show toast/notification
                var $toast = $('#uwb-url-toast');
                if ($toast.length) {
                    $toast.text('Copied and applied: ' + val + ' minutes').fadeIn(200).delay(1500).fadeOut(200);
                }
            });

            // Copy Cron Job to clipboard
            $('.uwb-copy-cron').on('click', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var text = $btn.data('clipboard-text');
                
                // Copy text using a temporary input element
                var $temp = $("<input>");
                $("body").append($temp);
                $temp.val(text).select();
                document.execCommand("copy");
                $temp.remove();
                
                // Show copied feedback
                var originalHtml = $btn.html();
                $btn.html('<span style="color:var(--uwb-success); font-weight:bold; font-size:11px;">✓ Copied</span>');
                setTimeout(function() {
                    $btn.html(originalHtml);
                }, 1500);
            });

            // GitHub manual update click handler in Header
            $('#uwb-github-update-btn').on('click', function(e) {
                e.preventDefault();
                var btn = $(this);
                var status = $('#uwb-github-update-status');
                var spinner = btn.find('.uwb-spinner');
                var btnText = btn.find('.uwb-btn-text');
                
                btn.prop('disabled', true);
                btnText.text('Updating...');
                spinner.show();
                status.text('Downloading latest version from GitHub...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    timeout: 120000,
                    data: {
                        action: 'uwb_github_manual_update',
                        nonce: '<?php echo esc_js( $update_nonce ); ?>'
                    },
                    success: function(res) {
                        spinner.hide();
                        btn.prop('disabled', false);
                        btnText.text('Update Plugin');
                        if (res.success) {
                            status.css('color', '#a7f3d0').text('✓ Plugin updated to latest version. Reloading...');
                            alert('Plugin updated successfully! The page will now reload.');
                            location.reload();
                        } else {
                            status.css('color', '#fca5a5').text('✗ Error: ' + (res.data.message || 'Unknown error.'));
                            alert('Update failed: ' + (res.data.message || 'Unknown error.'));
                        }
                    },
                    error: function(xhr, textStatus, errorThrown) {
                        spinner.hide();
                        btn.prop('disabled', false);
                        btnText.text('Update Plugin');
                        var detail = errorThrown || textStatus;
                        if (xhr.responseText) {
                            var preview = xhr.responseText.replace(/<[^>]+>/g, '').trim().substring(0, 300);
                            detail = '(HTTP ' + xhr.status + ') ' + preview;
                        }
                        status.css('color', '#fca5a5').text('✗ Server error.');
                        alert('Server error: ' + detail);
                    }
                });
            });

            // Clear CDN Cache click handler
            $(document).on('click', '.btn-trigger-clear-cdn-cache', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var oldHtml = $btn.html();
                
                if (!confirm('Bạn có chắc chắn muốn xóa toàn bộ Cache CDN R2/S3 và tập tin vết CDN?')) {
                    return;
                }

                $btn.prop('disabled', true).html('☁️ Đang xóa...');

                $.post(ajaxurl, {
                    action: 'uwb_clear_cdn_cache',
                    nonce: nonce
                }, function(res) {
                    $btn.prop('disabled', false).html(oldHtml);
                    if (res.success) {
                        alert(res.data.message || '☁️ Đã xóa CDN Cache thành công!');
                        location.reload();
                    } else {
                        alert('Lỗi: ' + (res.data ? res.data.message : 'Xóa CDN Cache thất bại'));
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).html(oldHtml);
                    alert('Có lỗi AJAX xảy ra.');
                });
            });

            // Test Redis Connection
            $('#btn-test-redis').on('click', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var $result = $('#redis-test-result');
                
                $btn.prop('disabled', true).text('Testing...');
                $result.hide().removeClass('notice-success notice-error').css({'background': '', 'color': '', 'border': ''});
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'uwb_test_redis_connection',
                        nonce: nonce,
                        is_stored: 1
                    },
                    success: function(res) {
                        $btn.prop('disabled', false).text('Test Connection');
                        $result.show();
                        if (res.success) {
                            $result.css({
                                'background': '#d1fae5',
                                'color': '#065f46',
                                'border': '1px solid #6ee7b7'
                            }).text(res.data.message);
                        } else {
                            $result.css({
                                'background': '#fee2e2',
                                'color': '#991b1b',
                                'border': '1px solid #fca5a5'
                            }).text(res.data.message);
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text('Test Connection');
                        $result.show().css({
                            'background': '#fee2e2',
                            'color': '#991b1b',
                            'border': '1px solid #fca5a5'
                        }).text('Server error occurred during the test.');
                    }
                });
            });

            // Test Redis Connection (Settings Page)
            $('#btn-test-redis-settings').on('click', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var $result = $('#redis-test-result-settings');
                
                $btn.prop('disabled', true).text('Testing...');
                $result.hide().removeClass('notice-success notice-error').css({'background': '', 'color': '', 'border': ''});
                
                var ocType = $('#uwb_redis_enabled').val();
                var connType = $('#uwb_redis_conn_type').val();
                var host = $('#uwb_redis_host').val();
                var port = $('#uwb_redis_port').val();
                var socket = $('#uwb_redis_socket').val();
                var password = $('#uwb_redis_password').val();
                var db = $('#uwb_redis_db').val();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'uwb_test_redis_connection',
                        nonce: nonce,
                        type: ocType,
                        conn_type: connType,
                        host: host,
                        port: port,
                        socket: socket,
                        password: password,
                        db: db
                    },
                    success: function(res) {
                        $btn.prop('disabled', false).text('Test Connection');
                        $result.show();
                        if (res.success) {
                            $result.css({
                                'background': '#d1fae5',
                                'color': '#065f46',
                                'border': '1px solid #6ee7b7'
                            }).text(res.data.message);
                        } else {
                            $result.css({
                                'background': '#fee2e2',
                                'color': '#991b1b',
                                'border': '1px solid #fca5a5'
                            }).text(res.data.message);
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text('Test Connection');
                        $result.show().css({
                            'background': '#fee2e2',
                            'color': '#991b1b',
                            'border': '1px solid #fca5a5'
                        }).text('Server error occurred during the test.');
                    }
                });
            });

            // Test Cloudflare API Connection
            $('#btn-test-cf-connection').on('click', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var $result = $('#uwb-cf-test-result');

                var zoneId = $('#uwb_cf_zone_id').val();
                var token = $('#uwb_cf_api_token').val();

                $btn.prop('disabled', true).text('Testing API...');
                $result.hide();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'uwb_test_cf_connection',
                        nonce: nonce,
                        zone_id: zoneId,
                        api_token: token
                    },
                    success: function(res) {
                        $btn.prop('disabled', false).text('Test Cloudflare API Connection');
                        $result.show();
                        if (res.success) {
                            $result.css({
                                'background': '#d1fae5',
                                'color': '#065f46',
                                'border': '1px solid #6ee7b7',
                                'padding': '12px',
                                'border-radius': '8px',
                                'font-size': '13px',
                                'font-weight': '600'
                            }).html('✓ ' + res.data.message);
                        } else {
                            $result.css({
                                'background': '#fee2e2',
                                'color': '#991b1b',
                                'border': '1px solid #fca5a5',
                                'padding': '12px',
                                'border-radius': '8px',
                                'font-size': '13px',
                                'font-weight': '600'
                            }).html('✕ Error: ' + (res.data.message || 'Connection failed'));
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text('Test Cloudflare API Connection');
                        $result.show().css({
                            'background': '#fee2e2',
                            'color': '#991b1b',
                            'border': '1px solid #fca5a5',
                            'padding': '12px',
                            'border-radius': '8px',
                            'font-size': '13px',
                            'font-weight': '600'
                        }).html('✕ Server error testing Cloudflare connection.');
                    }
                });
            });

            // Purge Cloudflare Zone Cache Now
            $('#btn-purge-cf-cache').on('click', function(e) {
                e.preventDefault();
                if (!confirm('Purge all Cloudflare CDN Edge Cache for this zone now?')) {
                    return;
                }
                var $btn = $(this);
                var $result = $('#uwb-cf-test-result');

                var zoneId = $('#uwb_cf_zone_id').val();
                var token = $('#uwb_cf_api_token').val();

                $btn.prop('disabled', true).text('Purging CDN...');
                $result.hide();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'uwb_purge_cf_cache',
                        nonce: nonce,
                        zone_id: zoneId,
                        api_token: token
                    },
                    success: function(res) {
                        $btn.prop('disabled', false).text('Purge Cloudflare Zone Cache Now');
                        $result.show();
                        if (res.success) {
                            $result.css({
                                'background': '#d1fae5',
                                'color': '#065f46',
                                'border': '1px solid #6ee7b7',
                                'padding': '12px',
                                'border-radius': '8px',
                                'font-size': '13px',
                                'font-weight': '600'
                            }).html('✓ ' + res.data.message);
                        } else {
                            $result.css({
                                'background': '#fee2e2',
                                'color': '#991b1b',
                                'border': '1px solid #fca5a5',
                                'padding': '12px',
                                'border-radius': '8px',
                                'font-size': '13px',
                                'font-weight': '600'
                            }).html('✕ Error: ' + (res.data.message || 'Purge failed'));
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text('Purge Cloudflare Zone Cache Now');
                        $result.show().css({
                            'background': '#fee2e2',
                            'color': '#991b1b',
                            'border': '1px solid #fca5a5',
                            'padding': '12px',
                            'border-radius': '8px',
                            'font-size': '13px',
                            'font-weight': '600'
                        }).html('✕ Server error purging Cloudflare cache.');
                    }
                });
            });

            // Toggle Lazy Load Elements Textarea visibility
            $('input[name="uwb_html_lazy_load_elements_enabled"]').on('change', function() {
                var val = $(this).val();
                if (val == '1') {
                    $('#uwb-lazy-elements-textarea-wrap').slideDown(250);
                } else {
                    $('#uwb-lazy-elements-textarea-wrap').slideUp(250);
                }
            });

            function loadDbStats() {
                var $tbody = $('#uwb-db-stats-tbody');
                $tbody.find('td[id^="uwb-db-stat-"]').text('Loading...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'uwb_get_database_stats',
                        nonce: nonce
                    },
                    success: function(res) {
                        if (res.success) {
                            $('#uwb-db-stat-revisions').text(res.data.revisions);
                            $('#uwb-db-stat-auto_drafts').text(res.data.auto_drafts);
                            $('#uwb-db-stat-trash_posts').text(res.data.trash_posts);
                            $('#uwb-db-stat-spam_comments').text(res.data.spam_comments);
                            $('#uwb-db-stat-trash_comments').text(res.data.trash_comments);
                            $('#uwb-db-stat-expired_transients').text(res.data.expired_transients);
                            $('#uwb-db-stat-tables').text(res.data.overhead_formatted + ' (' + res.data.tables_to_optimize + ' tables)');
                        } else {
                            $tbody.find('td[id^="uwb-db-stat-"]').text('Error fetching stats');
                        }
                    },
                    error: function() {
                        $tbody.find('td[id^="uwb-db-stat-"]').text('Network error');
                    }
                });
            }

            // Load on tab click
            $('.uwb-sub-tab-item[data-subtab="opt_database"]').on('click', function() {
                loadDbStats();
            });

            // If active tab is already database
            if ($('.uwb-sub-tab-item[data-subtab="opt_database"]').hasClass('active')) {
                loadDbStats();
            }

            $('#btn-refresh-db-stats').on('click', function(e) {
                e.preventDefault();
                loadDbStats();
            });

            $('#btn-optimize-db-now').on('click', function(e) {
                e.preventDefault();
                var selectedOptions = {};
                var anyChecked = false;
                $('.uwb-db-clean-opt').each(function() {
                    var val = $(this).val();
                    var checked = $(this).is(':checked') ? 1 : 0;
                    selectedOptions[val] = checked;
                    if (checked) {
                        anyChecked = true;
                    }
                });

                if (!anyChecked) {
                    alert('Please select at least one optimization target.');
                    return;
                }

                if (!confirm('Are you sure you want to run the selected database optimization and cleanups? This will permanently delete the items.')) {
                    return;
                }

                var $btn = $(this);
                var $result = $('#uwb-db-opt-result');
                $btn.prop('disabled', true).text('Optimizing...');
                $result.hide();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'uwb_optimize_database',
                        nonce: nonce,
                        options: selectedOptions
                    },
                    success: function(res) {
                        $btn.prop('disabled', false).text('Optimize Database Now');
                        $result.show();
                        if (res.success) {
                            var detailsHtml = '<ul style="margin: 8px 0 0 16px; padding:0; list-style-type:disc;">';
                            detailsHtml += '<li>Deleted Revisions: ' + res.data.details.revisions + '</li>';
                            detailsHtml += '<li>Deleted Auto Drafts: ' + res.data.details.auto_drafts + '</li>';
                            detailsHtml += '<li>Deleted Trashed Posts: ' + res.data.details.trash_posts + '</li>';
                            detailsHtml += '<li>Deleted Spam Comments: ' + res.data.details.spam_comments + '</li>';
                            detailsHtml += '<li>Deleted Trashed Comments: ' + res.data.details.trash_comments + '</li>';
                            detailsHtml += '<li>Cleared Expired Transients: ' + res.data.details.expired_transients + '</li>';
                            detailsHtml += '<li>Optimized Tables: ' + res.data.details.optimized_tables + '</li>';
                            detailsHtml += '</ul>';

                            $result.css({
                                'background': '#d1fae5',
                                'color': '#065f46',
                                'border': '1px solid #6ee7b7',
                                'padding': '16px',
                                'border-radius': '8px',
                                'font-size': '13px',
                                'font-weight': '600'
                            }).html('<strong>✓ Optimization Complete!</strong>' + detailsHtml);
                            
                            // Refresh stats
                            loadDbStats();
                        } else {
                            $result.css({
                                'background': '#fee2e2',
                                'color': '#991b1b',
                                'border': '1px solid #fca5a5',
                                'padding': '16px',
                                'border-radius': '8px',
                                'font-size': '13px',
                                'font-weight': '600'
                            }).html('✕ Error: ' + (res.data.message || 'Optimization failed'));
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text('Optimize Database Now');
                        $result.show().css({
                            'background': '#fee2e2',
                            'color': '#991b1b',
                            'border': '1px solid #fca5a5',
                            'padding': '16px',
                            'border-radius': '8px',
                            'font-size': '13px',
                            'font-weight': '600'
                        }).html('✕ Server error running database optimization.');
                    }
                });
            });

            // Flush Redis Cache
            $('#btn-flush-redis, #btn-flush-redis-tree').on('click', function(e) {
                e.preventDefault();
                if (!confirm('Are you sure you want to flush the persistent object cache?')) {
                    return;
                }
                var $btn = $(this);
                $btn.prop('disabled', true).text('Flushing...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'uwb_flush_redis_cache',
                        nonce: nonce
                    },
                    success: function(res) {
                        $btn.prop('disabled', false).text('Flush Cache');
                        if (res.success) {
                            showToast(res.data.message);
                            // Reload page after a delay to update stats
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            showToast(res.data.message, true);
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text('Flush Cache');
                        showToast('Server error flushing cache.', true);
                    }
                });
            });

            /* =====================================================
               URL STATUS TABLE
            ===================================================== */

            var uwbSearchTimer = null;

            function showToast(msg, isError) {
                var $t = $('#uwb-url-toast');
                $t.text(msg).css('background', isError ? '#dc2626' : '#1e293b').fadeIn(200);
                setTimeout(function() { $t.fadeOut(400); }, 3000);
            }

            function statusBadge(s) {
                var colors = {
                    pending:    'background:#fef9c3; color:#92400e; border:1px solid #fcd34d;',
                    processing: 'background:#dbeafe; color:#1e40af; border:1px solid #93c5fd;',
                    completed:  'background:#d1fae5; color:#065f46; border:1px solid #6ee7b7;',
                    failed:     'background:#fee2e2; color:#991b1b; border:1px solid #fca5a5;'
                };
                var style = colors[s] || '';
                return '<span style="' + style + ' padding:3px 10px; border-radius:20px; font-size:11.5px; font-weight:700; white-space:nowrap;">' + s + '</span>';
            }

            function loadUrlTable() {
                $('#uwb-url-tbody').html('<tr><td colspan="6" style="text-align:center; padding:32px; color:var(--uwb-text-muted);">Loading...</td></tr>');
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action:  'uwb_get_url_table',
                        nonce:   nonce,
                        status:  uwbUrlStatus,
                        search:  uwbUrlSearch,
                        orderby: uwbUrlOrderby,
                        order:   uwbUrlOrder,
                        page:    uwbUrlPage,
                        is_woocommerce: uwbUrlWc
                    },
                    success: function(res) {
                        if (!res.success) { showToast('Failed to load table.', true); return; }
                        renderUrlTable(res.data);
                    },
                    error: function() { showToast('Server error loading table.', true); }
                });
            }

            function renderUrlTable(data) {
                var rows = data.rows;
                var $tbody = $('#uwb-url-tbody');
                if (!rows || rows.length === 0) {
                    $tbody.html('<tr><td colspan="5" style="text-align:center; padding:32px; color:var(--uwb-text-muted);">No URLs found.</td></tr>');
                } else {
                    var html = '';
                    $.each(rows, function(i, r) {
                        var rowBg = (i % 2 === 0) ? '#ffffff' : '#f8fafc';
                        var priorityLabel = '<span style="font-weight:600; color:var(--uwb-text);">' + r.priority + '</span>';
                        var lastAttempt = r.last_attempt ? r.last_attempt : '—';
                        var uri = r.url.replace(/^https?:\/\/[^\/]+/i, '');
                        if (uri === '') { uri = '/'; }
                        
                        html += '<tr style="background:' + rowBg + '; border-bottom:1px solid #f1f5f9; transition:background 0.15s;" onmouseover="this.style.background=\'#eef2ff\'" onmouseout="this.style.background=\'' + rowBg + '\'">';
                        html += '<td style="padding:10px 14px; text-align:center;">' + priorityLabel + '</td>';
                        html += '<td style="padding:10px 14px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="' + $('<div>').text(r.url).html() + '"><a href="' + $('<div>').text(r.url).html() + '" target="_blank" style="color:var(--uwb-primary); text-decoration:none; font-size:12.5px;">' + $('<div>').text(uri).html() + '</a></td>';
                        html += '<td style="padding:10px 14px; text-align:center;">' + statusBadge(r.status) + '</td>';
                        html += '<td style="padding:10px 14px; text-align:center; color:var(--uwb-text-muted); font-size:12px;">' + lastAttempt + '</td>';
                        html += '<td style="padding:10px 14px; text-align:center; white-space:nowrap;">';
                        html += '<button class="uwb-act-process" data-id="' + r.id + '" style="background:#6366f1; color:#fff; border:none; border-radius:5px; padding:5px 10px; font-size:11.5px; font-weight:600; cursor:pointer; margin:2px;" title="Process this URL now">▶ Now</button>';
                        html += '<button class="uwb-act-exclude" data-id="' + r.id + '" style="background:#f1f5f9; border:1px solid #cbd5e1; color:#475569; border-radius:5px; padding:5px 10px; font-size:11.5px; font-weight:600; cursor:pointer; margin:2px;" title="Add to Exclude list">✕ Exclude</button>';
                        
                        var isImportant = (r.priority == 0);
                        var btnText = isImportant ? '★ Important' : '☆ Important';
                        var btnStyle = isImportant ? 'background:#fef9c3; border:1px solid #fcd34d; color:#92400e;' : 'background:#f1f5f9; border:1px solid #cbd5e1; color:#475569;';
                        var btnTitle = isImportant ? 'Remove from Important URLs' : 'Add to Important URLs';
                        html += '<button class="uwb-act-priority" data-id="' + r.id + '" style="' + btnStyle + ' border-radius:5px; padding:5px 10px; font-size:11.5px; font-weight:600; cursor:pointer; margin:2px;" title="' + btnTitle + '">' + btnText + '</button>';
                        html += '</td></tr>';
                    });
                    $tbody.html(html);
                }

                // Pagination
                var totalPages = data.total_pages;
                var currentPage = data.page;
                var from = (currentPage - 1) * data.per_page + 1;
                var to = Math.min(currentPage * data.per_page, data.total);
                var paginHtml = '<span>Showing ' + from + '–' + to + ' of ' + data.total + ' URLs</span>';
                
                $('#uwb-url-pagination').data('total-pages', totalPages);
                
                paginHtml += '<div style="display:flex; gap:6px; align-items:center;">';
                
                var firstDisabled = (currentPage > 1) ? '' : ' disabled style="opacity:0.5; cursor:not-allowed;"';
                var prevDisabled = (currentPage > 1) ? '' : ' disabled style="opacity:0.5; cursor:not-allowed;"';
                paginHtml += '<button id="uwb-page-first"' + firstDisabled + ' style="border:1px solid var(--uwb-border); background:#fff; border-radius:6px; padding:5px 12px; cursor:pointer; font-weight:600;">First</button>';
                paginHtml += '<button id="uwb-page-prev"' + prevDisabled + ' style="border:1px solid var(--uwb-border); background:#fff; border-radius:6px; padding:5px 12px; cursor:pointer; font-weight:600;">Prev</button>';

                var range = [];
                if (totalPages <= 7) {
                    for (var i = 1; i <= totalPages; i++) {
                        range.push(i);
                    }
                } else {
                    range.push(1);
                    range.push(2);

                    var start = Math.max(3, currentPage - 1);
                    var end = Math.min(totalPages - 2, currentPage + 1);

                    for (var i = start; i <= end; i++) {
                        range.push(i);
                    }

                    range.push(totalPages - 1);
                    range.push(totalPages);
                }

                range = range.filter(function(item, pos, self) {
                    return self.indexOf(item) == pos;
                }).sort(function(a, b) { return a - b; });

                var lastNum = 0;
                $.each(range, function(idx, p) {
                    if (lastNum > 0) {
                        if (p - lastNum > 1) {
                            paginHtml += '<span style="padding:5px 8px; color:var(--uwb-text-muted);">...</span>';
                        }
                    }
                    var active = (p === currentPage) ? 'background:var(--uwb-primary);color:#fff;' : 'background:#fff;';
                    paginHtml += '<button class="uwb-page-btn" data-page="' + p + '" style="border:1px solid var(--uwb-border);' + active + 'border-radius:6px; padding:5px 12px; cursor:pointer; font-weight:600;">' + p + '</button>';
                    lastNum = p;
                });

                var nextDisabled = (currentPage < totalPages) ? '' : ' disabled style="opacity:0.5; cursor:not-allowed;"';
                var lastDisabled = (currentPage < totalPages) ? '' : ' disabled style="opacity:0.5; cursor:not-allowed;"';
                paginHtml += '<button id="uwb-page-next"' + nextDisabled + ' style="border:1px solid var(--uwb-border); background:#fff; border-radius:6px; padding:5px 12px; cursor:pointer; font-weight:600;">Next</button>';
                paginHtml += '<button id="uwb-page-last"' + lastDisabled + ' style="border:1px solid var(--uwb-border); background:#fff; border-radius:6px; padding:5px 12px; cursor:pointer; font-weight:600;">Last</button>';

                paginHtml += '</div>';
                $('#uwb-url-pagination').html(paginHtml);
            }

            // Sort headers
            $(document).on('click', '.uwb-sortable', function(e) {
                e.preventDefault();
                var col = $(this).data('col');
                if (uwbUrlOrderby === col) {
                    uwbUrlOrder = (uwbUrlOrder === 'ASC') ? 'DESC' : 'ASC';
                } else {
                    uwbUrlOrderby = col;
                    uwbUrlOrder = 'ASC';
                }
                $('.uwb-sort-icon').text('↕');
                $(this).find('.uwb-sort-icon').text(uwbUrlOrder === 'ASC' ? '↑' : '↓');
                uwbUrlPage = 1;
                loadUrlTable();
            });

            // Filter buttons
            $(document).on('click', '.uwb-filter-btn', function(e) {
                e.preventDefault();
                $('.uwb-filter-btn').css('outline', '').removeClass('active');
                $(this).css('outline', '2px solid var(--uwb-primary)').addClass('active');
                uwbUrlStatus = $(this).data('status');
                uwbUrlPage = 1;
                loadUrlTable();
            });

            // Filter WooCommerce
            $('#uwb-filter-wc').on('click', function(e) {
                e.preventDefault();
                $(this).toggleClass('active');
                uwbUrlWc = $(this).hasClass('active') ? 1 : 0;
                uwbUrlPage = 1;
                loadUrlTable();
            });

            // Search
            $('#uwb-url-search').on('input', function() {
                clearTimeout(uwbSearchTimer);
                var val = $(this).val();
                uwbSearchTimer = setTimeout(function() {
                    uwbUrlSearch = val;
                    uwbUrlPage = 1;
                    loadUrlTable();
                }, 400);
            });

            // Refresh button
            $('#uwb-url-refresh').on('click', function(e) { e.preventDefault(); loadUrlTable(); });

            // Pagination
            $(document).on('click', '#uwb-page-first', function(e) {
                e.preventDefault();
                if ($(this).prop('disabled')) return;
                if (uwbUrlPage > 1) { uwbUrlPage = 1; loadUrlTable(); }
            });
            $(document).on('click', '#uwb-page-prev', function(e) {
                e.preventDefault();
                if ($(this).prop('disabled')) return;
                if (uwbUrlPage > 1) { uwbUrlPage--; loadUrlTable(); }
            });
            $(document).on('click', '#uwb-page-next', function(e) {
                e.preventDefault();
                if ($(this).prop('disabled')) return;
                var totalPages = parseInt($('#uwb-url-pagination').data('total-pages') || 1);
                if (uwbUrlPage < totalPages) { uwbUrlPage++; loadUrlTable(); }
            });
            $(document).on('click', '#uwb-page-last', function(e) {
                e.preventDefault();
                if ($(this).prop('disabled')) return;
                var totalPages = parseInt($('#uwb-url-pagination').data('total-pages') || 1);
                if (uwbUrlPage < totalPages) { uwbUrlPage = totalPages; loadUrlTable(); }
            });
            $(document).on('click', '.uwb-page-btn', function(e) {
                e.preventDefault();
                uwbUrlPage = parseInt($(this).data('page'));
                loadUrlTable();
            });

            // Row action: Process Now
            $(document).on('click', '.uwb-act-process', function(e) {
                e.preventDefault();
                var id = $(this).data('id');
                var $btn = $(this).prop('disabled', true).text('...');
                $.ajax({
                    url: ajaxurl, type: 'POST',
                    data: { action: 'uwb_process_url_now', nonce: nonce, id: id },
                    success: function(res) {
                        $btn.prop('disabled', false).text('▶ Now');
                        if (res.success) {
                            showToast('Done! Status: ' + res.data.status);
                            loadUrlTable();
                        } else {
                            showToast(res.data.message, true);
                        }
                    },
                    error: function() { $btn.prop('disabled', false).text('▶ Now'); showToast('Error.', true); }
                });
            });

            // Row action: Add to Exclude
            $(document).on('click', '.uwb-act-exclude', function(e) {
                e.preventDefault();
                var id = $(this).data('id');
                var $btn = $(this).prop('disabled', true).text('...');
                $.ajax({
                    url: ajaxurl, type: 'POST',
                    data: { action: 'uwb_add_to_exclude', nonce: nonce, id: id },
                    success: function(res) {
                        $btn.prop('disabled', false).text('✕ Exclude');
                        if (res.success) { showToast(res.data.message); }
                        else { showToast(res.data.message, true); }
                    },
                    error: function() { $btn.prop('disabled', false).text('✕ Exclude'); showToast('Error.', true); }
                });
            });

            // Row action: Add to Important / Remove from Important
            $(document).on('click', '.uwb-act-priority', function(e) {
                e.preventDefault();
                var id = $(this).data('id');
                var $btn = $(this).prop('disabled', true).text('...');
                $.ajax({
                    url: ajaxurl, type: 'POST',
                    data: { action: 'uwb_add_to_priority', nonce: nonce, id: id },
                    success: function(res) {
                        if (res.success) { 
                            showToast(res.data.message); 
                            if (res.data.urls !== undefined) {
                                $('#uwb_priority_urls').val(res.data.urls);
                            }
                            loadUrlTable(); 
                        }
                        else { $btn.prop('disabled', false).text('Important'); showToast(res.data.message, true); }
                    },
                    error: function() { $btn.prop('disabled', false).text('Important'); showToast('Error.', true); }
                });
            });

            // Restore active tab and subtab state
            var savedTab = localStorage.getItem('uwb_active_tab');
            if (savedTab) {
                $('.uwb-nav-item').removeClass('active');
                var $targetTabBtn = $('.uwb-nav-item[data-tab="' + savedTab + '"]');
                $targetTabBtn.addClass('active');
                
                $('.uwb-tab-content').removeClass('active');
                $('#tab-' + savedTab).addClass('active');
                
                if (['url_status', 'import_export'].indexOf(savedTab) !== -1) {
                    $('#uwb-submit-row').hide();
                } else if (savedTab === 'advanced_tools') {
                    var sub = localStorage.getItem('uwb_subtab_advanced_tools');
                    if (sub === 'plugin_load_manager') {
                        $('#uwb-submit-row').hide();
                    } else {
                        $('#uwb-submit-row').show();
                    }
                } else {
                    $('#uwb-submit-row').show();
                }
                
                if (savedTab === 'url_status') {
                    uwbUrlTableLoaded = true;
                    loadUrlTable();
                }
            } else {
                // Default: Load URL table on load since it is the default tab (Dashboard)
                uwbUrlTableLoaded = true;
                loadUrlTable();
            }
            
            // Restore sub-tab active state for each tab
            $('.uwb-tab-content').each(function() {
                var tabId = $(this).attr('id').replace('tab-', '');
                var savedSub = localStorage.getItem('uwb_subtab_' + tabId);
                if (savedSub) {
                    var $subtabBtn = $(this).find('.uwb-sub-tab-item[data-subtab="' + savedSub + '"]');
                    if ($subtabBtn.length) {
                        $subtabBtn.siblings('.uwb-sub-tab-item').removeClass('active');
                        $subtabBtn.addClass('active');
                        
                        $subtabBtn.parent().siblings('.uwb-subtab-content').removeClass('active');
                        $('#subtab-' + savedSub).addClass('active');
                    }
                }
            });

            // Toggle event action checkboxes on CDN distribution switch change
            $(document).on('change', 'input[name="uwb_cdn_distribute_css"], input[name="uwb_cdn_distribute_js"], input[name="uwb_cdn_distribute_html"], input[name="uwb_cdn_distribute_media"], input[name="uwb_cdn_distribute_font"]', function() {
                var $card = $(this).closest('.uwb-cdn-distribution-card');
                var $wrap = $card.find('.uwb-cdn-events-wrap');
                var $mediaDomainWrap = $card.find('.uwb-cdn-media-custom-domain-wrap');
                var isON = ($card.find('input[type="radio"]:checked').val() === '1');
                if (isON) {
                    $wrap.slideDown(200);
                    $mediaDomainWrap.slideDown(200);
                } else {
                    $wrap.slideUp(200);
                    $mediaDomainWrap.slideUp(200);
                }
            });

            // Toggle image optimization event actions box
            $(document).on('change', 'input[name="uwb_media_opt_enabled"]', function() {
                var $wrap = $('.uwb-img-opt-events-wrap');
                if ($(this).is(':checked')) {
                    $wrap.slideDown(200);
                } else {
                    $wrap.slideUp(200);
                }
            });

            // Batch Image Optimization AJAX Handler
            $(document).on('click', '#btn-batch-optimize-images', function() {
                var $btn = $(this);
                var $wrap = $('#uwb-optimize-progress-wrap');
                var $fill = $('#uwb-optimize-progress-fill');
                var $status = $('#uwb-optimize-status-text');

                $btn.prop('disabled', true).text('Optimizing...');
                $wrap.slideDown(200);
                $fill.css('width', '0%');
                $status.text('Starting image optimization batch...');

                function runBatch(paged) {
                    $.post(ajaxurl, {
                        action: 'uwb_batch_optimize_images',
                        paged: paged,
                        nonce: '<?php echo wp_create_nonce( 'uwb_admin_nonce' ); ?>'
                    }, function(res) {
                        if (res.success) {
                            var pct = Math.round((res.data.paged / Math.max(1, res.data.max_pages)) * 100);
                            $fill.css('width', pct + '%');
                            $status.text(res.data.message);

                            if (!res.data.is_done) {
                                runBatch(res.data.paged + 1);
                            } else {
                                $btn.prop('disabled', false).text('Optimize & Convert Existing Media');
                            }
                        } else {
                            $status.text('Error: ' + (res.data ? res.data.message : 'Batch optimization failed.'));
                            $btn.prop('disabled', false).text('Optimize & Convert Existing Media');
                        }
                    }).fail(function() {
                        $status.text('AJAX error occurred during batch image optimization.');
                        $btn.prop('disabled', false).text('Optimize & Convert Existing Media');
                    });
                }

                runBatch(1);
            });
        });
        </script>
