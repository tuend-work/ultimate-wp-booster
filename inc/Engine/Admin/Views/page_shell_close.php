<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
// Views/page_shell_close.php — Close form + content panel + layout + dashboard wrap
?>
                <!-- Form Submit -->
                <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--uwb-border); display: none; gap: 12px;" id="uwb-submit-row">
                    <input type="submit" name="submit" id="submit" class="button button-primary" style="background:var(--uwb-primary); border-color:var(--uwb-primary); padding:8px 20px; height:auto; font-weight:600; border-radius:6px; box-shadow: 0 4px 6px rgba(99, 102, 241, 0.2);" value="Save Changes" />
                </div>
            </form>

            <!-- TAB: Import & Export Settings -->
            <?php include __DIR__ . '/tab_import_export.php'; ?>

        </div><!-- /.uwb-content-panel -->
    </div><!-- /.uwb-layout -->
</div><!-- /.uwb-dashboard-wrap -->