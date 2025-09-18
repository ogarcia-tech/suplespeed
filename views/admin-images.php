<?php
/**
 * Página de optimización de imágenes
 */

if (!defined('ABSPATH')) {
    exit;
}

$images_module = function_exists('suple_speed') ? suple_speed()->images : null;
$image_stats = [
    'total_images_processed' => 0,
    'lazy_loading_applied' => 0,
    'lqip_generated' => 0,
    'webp_conversions' => 0,
    'total_size_saved' => 0,
];

if ($images_module && method_exists($images_module, 'get_optimization_stats')) {
    $image_stats = $images_module->get_optimization_stats();
}

$current_settings = $this->get_current_settings();
?>

<div class="suple-speed-admin">

    <div class="suple-speed-header">
        <h1><?php _e('Images', 'suple-speed'); ?></h1>
        <p><?php _e('Control lazy loading, modern formats and LQIP generation.', 'suple-speed'); ?></p>
    </div>

    <?php include SUPLE_SPEED_PLUGIN_DIR . 'views/partials/admin-nav.php'; ?>

    <div class="suple-grid suple-grid-2 suple-mt-2">
        <div class="suple-card">
            <h3><?php _e('Optimization Status', 'suple-speed'); ?></h3>
            <ul class="suple-list">
                <li>
                    <strong><?php _e('Lazy Loading', 'suple-speed'); ?>:</strong>
                    <span class="suple-badge <?php echo !empty($current_settings['images_lazy']) ? 'success' : 'error'; ?>">
                        <?php echo !empty($current_settings['images_lazy']) ? esc_html__('Enabled', 'suple-speed') : esc_html__('Disabled', 'suple-speed'); ?>
                    </span>
                </li>
                <li>
                    <strong><?php _e('LQIP Placeholders', 'suple-speed'); ?>:</strong>
                    <?php echo !empty($current_settings['images_lqip']) ? esc_html__('Enabled', 'suple-speed') : esc_html__('Disabled', 'suple-speed'); ?>
                </li>
                <li>
                    <strong><?php _e('Modern Formats', 'suple-speed'); ?>:</strong>
                    <?php echo !empty($current_settings['images_modern']) ? esc_html__('Enabled', 'suple-speed') : esc_html__('Disabled', 'suple-speed'); ?>
                </li>
            </ul>

            <a class="suple-button" href="<?php echo esc_url(admin_url('admin.php?page=suple-speed-settings#tab-images')); ?>">
                <?php _e('Adjust Image Settings', 'suple-speed'); ?>
            </a>
        </div>

        <div class="suple-card">
            <h3><?php _e('Recent Activity', 'suple-speed'); ?></h3>
            <ul class="suple-stats">
                <li class="suple-stat-card">
                    <span class="suple-stat-value"><?php echo esc_html($image_stats['lqip_generated']); ?></span>
                    <span class="suple-stat-label"><?php _e('LQIPs Generated', 'suple-speed'); ?></span>
                </li>
                <li class="suple-stat-card">
                    <span class="suple-stat-value"><?php echo esc_html($image_stats['webp_conversions']); ?></span>
                    <span class="suple-stat-label"><?php _e('WebP Conversions', 'suple-speed'); ?></span>
                </li>
            </ul>
        </div>
    </div>

    <div class="suple-card suple-mt-2">
        <h3><?php _e('Maintenance', 'suple-speed'); ?></h3>
        <p><?php _e('Regenerate placeholders or clear cached data when necessary.', 'suple-speed'); ?></p>
        <button type="button" class="suple-button suple-images-clear-lqip">
            <span class="dashicons dashicons-image-rotate"></span>
            <?php _e('Clear LQIP Cache', 'suple-speed'); ?>
        </button>
    </div>
</div>
