<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
// Views/subtab_opt_database.php — Database Optimizer
?>
                            <div id="subtab-opt_database" class="uwb-subtab-content">
                                <div style="background:#f8fafc; border:1px solid var(--uwb-border); border-radius:12px; padding:24px; margin-bottom:24px;">
                                    <?php $this->render_submodule_header( 'uwb_module_database_enabled', 'Database Optimization & Cleanup', '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>' ); ?>
                                    <p style="font-size:13px; color:var(--uwb-text-muted); margin-bottom:20px;">Clean up database overhead, remove post revisions, trashed posts/comments, and transients to improve database query speeds.</p>

                                    <table class="wp-list-table widefat fixed striped" style="border:none; box-shadow:none; background:transparent; margin-bottom:20px;">
                                        <thead>
                                            <tr>
                                                <th style="width: 40px; padding: 10px;">Select</th>
                                                <th style="padding: 10px;">Optimization Target</th>
                                                <th style="padding: 10px; text-align: right;">Status / Count</th>
                                            </tr>
                                        </thead>
                                        <tbody id="uwb-db-stats-tbody">
                                            <tr>
                                                <td style="padding: 12px 10px;"><input type="checkbox" class="uwb-db-clean-opt" value="revisions" checked /></td>
                                                <td style="padding: 12px 10px;"><strong>Post Revisions</strong><br><span class="description">Stored versions of posts/pages. Deleting revisions won't affect current content.</span></td>
                                                <td style="padding: 12px 10px; text-align: right; font-weight: bold;" id="uwb-db-stat-revisions">Loading...</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 12px 10px;"><input type="checkbox" class="uwb-db-clean-opt" value="auto_drafts" checked /></td>
                                                <td style="padding: 12px 10px;"><strong>Auto Drafts</strong><br><span class="description">Automatically saved draft posts during editing.</span></td>
                                                <td style="padding: 12px 10px; text-align: right; font-weight: bold;" id="uwb-db-stat-auto_drafts">Loading...</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 12px 10px;"><input type="checkbox" class="uwb-db-clean-opt" value="trash_posts" /></td>
                                                <td style="padding: 12px 10px;"><strong>Trashed Posts</strong><br><span class="description">Posts, pages, and custom post types in the trash.</span></td>
                                                <td style="padding: 12px 10px; text-align: right; font-weight: bold;" id="uwb-db-stat-trash_posts">Loading...</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 12px 10px;"><input type="checkbox" class="uwb-db-clean-opt" value="spam_comments" checked /></td>
                                                <td style="padding: 12px 10px;"><strong>Spam Comments</strong><br><span class="description">Comments marked as spam by moderators or antispam plugins.</span></td>
                                                <td style="padding: 12px 10px; text-align: right; font-weight: bold;" id="uwb-db-stat-spam_comments">Loading...</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 12px 10px;"><input type="checkbox" class="uwb-db-clean-opt" value="trash_comments" checked /></td>
                                                <td style="padding: 12px 10px;"><strong>Trashed Comments</strong><br><span class="description">Comments in the trash folder.</span></td>
                                                <td style="padding: 12px 10px; text-align: right; font-weight: bold;" id="uwb-db-stat-trash_comments">Loading...</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 12px 10px;"><input type="checkbox" class="uwb-db-clean-opt" value="expired_transients" checked /></td>
                                                <td style="padding: 12px 10px;"><strong>Expired Transients</strong><br><span class="description">Temporary cache options that have already expired.</span></td>
                                                <td style="padding: 12px 10px; text-align: right; font-weight: bold;" id="uwb-db-stat-expired_transients">Loading...</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 12px 10px;"><input type="checkbox" class="uwb-db-clean-opt" value="optimize_tables" checked /></td>
                                                <td style="padding: 12px 10px;"><strong>Database Tables Overhead</strong><br><span class="description">Defragment database tables to reclaim unused storage database space.</span></td>
                                                <td style="padding: 12px 10px; text-align: right; font-weight: bold;" id="uwb-db-stat-tables">Loading...</td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <div style="display: flex; gap: 12px; align-items: center;">
                                        <button type="button" id="btn-refresh-db-stats" class="button button-secondary" style="padding:10px 18px; font-weight:600; height:auto; border-radius:8px; cursor:pointer;">
                                            Refresh Stats
                                        </button>
                                        <button type="button" id="btn-optimize-db-now" class="button button-primary" style="background:var(--uwb-primary); border-color:var(--uwb-primary); padding:10px 20px; height:auto; border-radius:8px; font-weight:600; cursor:pointer;">
                                            Optimize Database Now
                                        </button>
                                    </div>
                                    <div id="uwb-db-opt-result" style="margin-top:16px; display:none; padding: 16px; border-radius: 8px;"></div>
                                    <?php $this->render_module_banner_end(); ?>
                                </div>
                            </div>
                            <?php $this->render_module_banner_end(); ?>
                        </div>
