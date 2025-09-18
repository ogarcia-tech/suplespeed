<?php
/**
 * Página de herramientas
 */

if (!defined('ABSPATH')) {
    exit;
}

$cache_module = function_exists('suple_speed') ? suple_speed()->cache : null;
$logger_module = function_exists('suple_speed') ? suple_speed()->logger : null;

$cache_stats = [
    'total_files' => 0,
    'total_size_formatted' => size_format(0),
];

if ($cache_module && method_exists($cache_module, 'get_cache_stats')) {
    $cache_stats = $cache_module->get_cache_stats();
    if (empty($cache_stats['total_size_formatted'])) {
        $cache_stats['total_size_formatted'] = size_format((int) ($cache_stats['total_size'] ?? 0));
    }
}

$log_stats = [
    'total' => 0,
    'by_level' => [],
];

if ($logger_module && method_exists($logger_module, 'get_log_stats')) {
    $log_stats = $logger_module->get_log_stats();
}
?>

<div class="suple-speed-admin">

    <div class="suple-speed-header">
        <h1><?php _e('Tools', 'suple-speed'); ?></h1>
        <p><?php _e('Utility actions to debug and maintain Suple Speed.', 'suple-speed'); ?></p>
    </div>

    <?php include SUPLE_SPEED_PLUGIN_DIR . 'views/partials/admin-nav.php'; ?>

    <div class="suple-grid suple-grid-2 suple-mt-2">
        <div class="suple-card">
            <h3><?php _e('Cache Maintenance', 'suple-speed'); ?></h3>
            <p><?php _e('Review current cache footprint and purge it on demand.', 'suple-speed'); ?></p>
            <ul class="suple-list">
                <li>
                    <strong><?php _e('Cached Files', 'suple-speed'); ?>:</strong> <?php echo esc_html($cache_stats['total_files'] ?? 0); ?>
                </li>
                <li>
                    <strong><?php _e('Disk Usage', 'suple-speed'); ?>:</strong> <?php echo esc_html($cache_stats['total_size_formatted'] ?? size_format(0)); ?>
                </li>
            </ul>
            <div class="suple-button-group">
                <button type="button" class="suple-button suple-purge-cache" data-purge-action="all">
                    <?php _e('Purge Cache', 'suple-speed'); ?>
                </button>
                <button type="button" class="suple-button secondary suple-purge-cache" data-purge-action="expired">
                    <?php _e('Clean Expired', 'suple-speed'); ?>
                </button>
            </div>
        </div>

        <div class="suple-card">
            <h3><?php _e('Logs Overview', 'suple-speed'); ?></h3>
            <p><?php _e('Inspect recent log volume before exporting or clearing data.', 'suple-speed'); ?></p>
            <p><strong><?php _e('Entries Stored', 'suple-speed'); ?>:</strong> <?php echo esc_html($log_stats['total'] ?? 0); ?></p>
            <?php if (!empty($log_stats['by_level'])): ?>
                <ul class="suple-list">
                    <?php foreach ($log_stats['by_level'] as $row): ?>
                        <li><?php echo esc_html(sprintf('%s: %d', ucfirst($row->level), $row->count)); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <div class="suple-button-group">
                <button type="button" class="suple-button secondary suple-export-logs">
                    <?php _e('Export Logs', 'suple-speed'); ?>
                </button>
                <button type="button" class="suple-button suple-clear-logs">
                    <?php _e('Clear Logs', 'suple-speed'); ?>
                </button>
            </div>
        </div>
    </div>

    <div class="suple-card suple-mt-2">
        <h3><?php _e('Diagnostics', 'suple-speed'); ?></h3>
        <p><?php _e('Use these helpers to verify that core services are working as expected.', 'suple-speed'); ?></p>
        <div class="suple-button-group">
            <button type="button" class="suple-button suple-run-psi" data-default-strategy="mobile">
                <?php _e('Run PSI Test', 'suple-speed'); ?>
            </button>
            <button type="button" class="suple-button secondary suple-test-cache">
                <?php _e('Test Cache Warmup', 'suple-speed'); ?>
            </button>
        </div>
    </div>
</div>
