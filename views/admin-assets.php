<?php
/**
 * Página de optimización de assets
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_settings = $this->get_current_settings();
$compat_module = function_exists('suple_speed') ? suple_speed()->compat : null;
$excluded_by_compat = [];

if ($compat_module && method_exists($compat_module, 'get_excluded_handles')) {
    $excluded_by_compat = $compat_module->get_excluded_handles();
}

$manual_exclusions = $current_settings['assets_exclude_handles'] ?? [];
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
    </div>
</div>
