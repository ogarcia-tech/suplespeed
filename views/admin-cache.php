<?php
/**
 * Página de control de caché
 */

if (!defined('ABSPATH')) {
    exit;
}

$cache_module = function_exists('suple_speed') ? suple_speed()->cache : null;
$cache_stats = [
    'total_files' => 0,
    'total_size' => 0,
    'total_size_formatted' => size_format(0),
    'oldest_file' => null,
    'newest_file' => null,
];

if ($cache_module && method_exists($cache_module, 'get_cache_stats')) {
    $cache_stats = $cache_module->get_cache_stats();
    if (empty($cache_stats['total_size_formatted'])) {
        $cache_stats['total_size_formatted'] = size_format((int) ($cache_stats['total_size'] ?? 0));
    }
}

$current_settings = $this->get_current_settings();
?>

<div class="suple-speed-admin">

    <div class="suple-speed-header">
        <h1><?php _e('Cache', 'suple-speed'); ?></h1>
        <p><?php _e('Inspect cache usage and purge content when required.', 'suple-speed'); ?></p>
    </div>

    <?php include SUPLE_SPEED_PLUGIN_DIR . 'views/partials/admin-nav.php'; ?>

    <div class="suple-grid suple-grid-2 suple-mt-2">
        <div class="suple-card">
            <h3><?php _e('Cache Status', 'suple-speed'); ?></h3>

            <ul class="suple-list">
                <li>
                    <strong><?php _e('Status', 'suple-speed'); ?>:</strong>
                    <span class="suple-badge <?php echo !empty($current_settings['cache_enabled']) ? 'success' : 'error'; ?>">
                        <?php echo !empty($current_settings['cache_enabled']) ? esc_html__('Enabled', 'suple-speed') : esc_html__('Disabled', 'suple-speed'); ?>
                    </span>
                </li>
                <li>
                    <strong><?php _e('Cache Size', 'suple-speed'); ?>:</strong>
                    <?php echo esc_html($cache_stats['total_size_formatted']); ?>
                </li>
                <li>
                    <strong><?php _e('Cached Files', 'suple-speed'); ?>:</strong>
                    <?php echo esc_html($cache_stats['total_files']); ?>
                </li>
                <li>
                    <strong><?php _e('Newest File', 'suple-speed'); ?>:</strong>
                    <?php echo !empty($cache_stats['newest_file']) ? esc_html($cache_stats['newest_file']) : esc_html__('N/A', 'suple-speed'); ?>
                </li>
                <li>
                    <strong><?php _e('Oldest File', 'suple-speed'); ?>:</strong>
                    <?php echo !empty($cache_stats['oldest_file']) ? esc_html($cache_stats['oldest_file']) : esc_html__('N/A', 'suple-speed'); ?>
                </li>
            </ul>

            <div class="suple-mt-2">
                <button type="button" class="suple-button suple-purge-cache" data-purge-action="all">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Purge Entire Cache', 'suple-speed'); ?>
                </button>
                <button type="button" class="suple-button secondary suple-purge-cache" data-purge-action="expired">
                    <?php _e('Clean Expired Files', 'suple-speed'); ?>
                </button>
            </div>
        </div>

        <div class="suple-card">
            <h3><?php _e('Quick Settings', 'suple-speed'); ?></h3>

            <p><?php _e('These are the most relevant cache settings currently applied.', 'suple-speed'); ?></p>

            <ul class="suple-list">
                <li>
                    <strong><?php _e('Cache Lifetime', 'suple-speed'); ?>:</strong>
                    <?php
                    $ttl_hours = !empty($current_settings['cache_ttl']) ? floor($current_settings['cache_ttl'] / HOUR_IN_SECONDS) : 0;
                    echo $ttl_hours ? esc_html(sprintf(_n('%d hour', '%d hours', $ttl_hours, 'suple-speed'), $ttl_hours)) : esc_html__('Default', 'suple-speed');
                    ?>
                </li>
                <li>
                    <strong><?php _e('Compression', 'suple-speed'); ?>:</strong>
                    <?php echo !empty($current_settings['compression_enabled']) ? esc_html__('Enabled', 'suple-speed') : esc_html__('Disabled', 'suple-speed'); ?>
                </li>
                <li>
                    <strong><?php _e('Logged-in Cache', 'suple-speed'); ?>:</strong>
                    <?php echo !empty($current_settings['cache_logged_users']) ? esc_html__('Enabled', 'suple-speed') : esc_html__('Disabled', 'suple-speed'); ?>
                </li>
            </ul>

            <a class="suple-button" href="<?php echo esc_url(admin_url('admin.php?page=suple-speed-settings#tab-cache')); ?>">
                <?php _e('Adjust Cache Settings', 'suple-speed'); ?>
            </a>
        </div>
    </div>
</div>
