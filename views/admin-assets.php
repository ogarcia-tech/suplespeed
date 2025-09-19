<?php
/**
 * Legacy view wrapper.
 */

if (!defined('ABSPATH')) {
    exit;
}


$current_settings = $this->get_current_settings();
$compat_module = function_exists('suple_speed') ? suple_speed()->compat : null;
$assets_module = function_exists('suple_speed') ? suple_speed()->assets : null;
$excluded_by_compat = [];

if ($compat_module && method_exists($compat_module, 'get_excluded_handles')) {
    $excluded_by_compat = $compat_module->get_excluded_handles();
}

$manual_exclusions = $current_settings['assets_exclude_handles'] ?? [];
$manual_groups = [];
$bundle_status = ['css' => [], 'js' => []];
$asset_group_labels = [
    'A' => __('Core / Theme', 'suple-speed'),
    'B' => __('WooCommerce / Security', 'suple-speed'),
    'C' => __('Elementor', 'suple-speed'),
    'D' => __('Third-party / Misc', 'suple-speed')
];

if ($assets_module) {
    if (method_exists($assets_module, 'get_manual_groups')) {
        $manual_groups = $assets_module->get_manual_groups();
    }

    if (method_exists($assets_module, 'get_bundle_status')) {
        $bundle_status = $assets_module->get_bundle_status();
    }

    if (method_exists($assets_module, 'get_asset_group_labels')) {
        $asset_group_labels = $assets_module->get_asset_group_labels();
    }
}
?>

<div class="suple-speed-admin">

    <div class="suple-speed-header">
        <h1><?php _e('Assets', 'suple-speed'); ?></h1>
        <p><?php _e('Optimize CSS and JavaScript delivery across your pages.', 'suple-speed'); ?></p>
    </div>

    <?php include SUPLE_SPEED_PLUGIN_DIR . 'views/partials/admin-nav.php'; ?>

    <div class="suple-grid suple-grid-2 suple-mt-2">
        <div class="suple-card">
            <h3><?php _e('Optimization Status', 'suple-speed'); ?></h3>

            <ul class="suple-list">
                <li>
                    <strong><?php _e('Assets Optimization', 'suple-speed'); ?>:</strong>
                    <span class="suple-badge <?php echo !empty($current_settings['assets_enabled']) ? 'success' : 'error'; ?>">
                        <?php echo !empty($current_settings['assets_enabled']) ? esc_html__('Enabled', 'suple-speed') : esc_html__('Disabled', 'suple-speed'); ?>
                    </span>
                </li>
                <li>
                    <strong><?php _e('Merge CSS', 'suple-speed'); ?>:</strong>
                    <?php echo !empty($current_settings['merge_css']) ? esc_html__('Yes', 'suple-speed') : esc_html__('No', 'suple-speed'); ?>
                </li>
                <li>
                    <strong><?php _e('Merge JS', 'suple-speed'); ?>:</strong>
                    <?php echo !empty($current_settings['merge_js']) ? esc_html__('Yes', 'suple-speed') : esc_html__('No', 'suple-speed'); ?>
                </li>
                <li>
                    <strong><?php _e('Test Mode', 'suple-speed'); ?>:</strong>
                    <?php echo !empty($current_settings['assets_test_mode']) ? esc_html__('Active', 'suple-speed') : esc_html__('Inactive', 'suple-speed'); ?>
                </li>
            </ul>

            <a class="suple-button" href="<?php echo esc_url(admin_url('admin.php?page=suple-speed-settings#tab-assets')); ?>">
                <?php _e('Open Assets Settings', 'suple-speed'); ?>
            </a>
        </div>

        <div class="suple-card">
            <h3><?php _e('Excluded Handles', 'suple-speed'); ?></h3>

            <p><?php _e('Handles skipped from optimization for compatibility reasons.', 'suple-speed'); ?></p>

            <h4><?php _e('Manual Exclusions', 'suple-speed'); ?></h4>
            <?php if (!empty($manual_exclusions)): ?>
                <ul class="suple-list">
                    <?php foreach ($manual_exclusions as $handle): ?>
                        <li><?php echo esc_html($handle); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p><?php _e('No manual exclusions configured.', 'suple-speed'); ?></p>
            <?php endif; ?>

            <h4><?php _e('Compatibility Exclusions', 'suple-speed'); ?></h4>
            <?php if (!empty($excluded_by_compat)): ?>
                <ul class="suple-list">
                    <?php foreach ($excluded_by_compat as $handle): ?>
                        <li><?php echo esc_html($handle); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p><?php _e('No automatic exclusions detected.', 'suple-speed'); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="suple-card suple-mt-2">
        <h3><?php _e('Manual Scan', 'suple-speed'); ?></h3>
        <p><?php _e('Trigger a scan of the current page to detect enqueued handles and adjust optimization rules.', 'suple-speed'); ?></p>
        <button type="button" class="suple-button suple-scan-assets">
            <span class="dashicons dashicons-search"></span>
            <?php _e('Scan Handles', 'suple-speed'); ?>
        </button>

        <div id="handles-detected" class="suple-mt-2">
            <p class="suple-muted">
                <?php _e('Run a scan to populate the detected handles list and review their current classification.', 'suple-speed'); ?>
            </p>
        </div>

        <form id="suple-assets-groups-form" class="suple-form suple-mt-2">
            <p class="suple-muted">
                <?php _e('Override the automatic classification by assigning a fixed group to any handle.', 'suple-speed'); ?>
            </p>

            <div id="handles-results" class="suple-table-responsive">
                <?php if (!empty($manual_groups)): ?>
                    <table class="suple-table">
                        <thead>
                            <tr>
                                <th><?php _e('Handle', 'suple-speed'); ?></th>
                                <th><?php _e('Manual Group', 'suple-speed'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($manual_groups as $handle => $group): ?>
                                <tr>
                                    <td><code><?php echo esc_html($handle); ?></code></td>
                                    <td>
                                        <span class="suple-badge">
                                            <?php echo esc_html(sprintf(__('Group %1$s · %2$s', 'suple-speed'), strtoupper($group), $asset_group_labels[$group] ?? $group)); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p><?php _e('No manual overrides configured yet.', 'suple-speed'); ?></p>
                <?php endif; ?>
            </div>

            <div class="suple-flex suple-gap-1 suple-mt-2 suple-flex-wrap">
                <button type="button" class="suple-button success suple-save-manual-groups">
                    <span class="dashicons dashicons-yes"></span>
                    <?php _e('Save manual groups', 'suple-speed'); ?>
                </button>
                <button type="button" class="suple-button secondary suple-regenerate-bundles" disabled>
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Regenerate bundles', 'suple-speed'); ?>
                </button>
            </div>
        </form>
    </div>

    <div class="suple-card suple-mt-2">
        <h3><?php _e('Asset bundles', 'suple-speed'); ?></h3>
        <p><?php _e('Overview of the current merged bundles generated by Suple Speed.', 'suple-speed'); ?></p>

        <div id="bundle-status">
            <?php
            $has_bundles = false;
            foreach ($bundle_status as $entries) {
                if (!empty($entries)) {
                    $has_bundles = true;
                    break;
                }
            }
            ?>

            <?php if ($has_bundles): ?>
                <table class="suple-table">
                    <thead>
                        <tr>
                            <th><?php _e('Type', 'suple-speed'); ?></th>
                            <th><?php _e('Group', 'suple-speed'); ?></th>
                            <th><?php _e('Identifier', 'suple-speed'); ?></th>
                            <th><?php _e('Version', 'suple-speed'); ?></th>
                            <th><?php _e('Generated', 'suple-speed'); ?></th>
                            <th><?php _e('Handles', 'suple-speed'); ?></th>
                            <th><?php _e('Size', 'suple-speed'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bundle_status as $type => $groups): ?>
                            <?php foreach ($groups as $group_key => $bundles): ?>
                                <?php foreach ($bundles as $bundle): ?>
                                    <tr>
                                        <td><?php echo esc_html(strtoupper($type)); ?></td>
                                        <td>
                                            <span class="suple-badge">
                                                <?php echo esc_html(sprintf(__('Group %1$s · %2$s', 'suple-speed'), $bundle['group'], $asset_group_labels[$bundle['group']] ?? $bundle['group'])); ?>
                                            </span>
                                        </td>
                                        <td><code><?php echo esc_html($bundle['identifier']); ?></code></td>
                                        <td><?php echo esc_html($bundle['version']); ?></td>
                                        <td>
                                            <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $bundle['created'])); ?>
                                        </td>
                                        <td>
                                            <?php echo esc_html(count($bundle['handles'] ?? [])); ?>
                                            <?php if (!empty($bundle['handles'])): ?>
                                                <br><small><?php echo esc_html(implode(', ', $bundle['handles'])); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html(size_format($bundle['size'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e('No bundles have been generated yet. They will appear here after the next optimization run.', 'suple-speed'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

