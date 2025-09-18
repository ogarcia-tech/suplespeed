<?php
/**
 * Página de registros
 */

if (!defined('ABSPATH')) {
    exit;
}

$logger_module = function_exists('suple_speed') ? suple_speed()->logger : null;
$log_stats = [
    'total' => 0,
    'by_level' => [],
    'by_module' => [],
];
$recent_logs = [];

if ($logger_module) {
    if (method_exists($logger_module, 'get_log_stats')) {
        $log_stats = $logger_module->get_log_stats();
    }
    if (method_exists($logger_module, 'get_logs')) {
        $recent_logs = $logger_module->get_logs(20);
    }
}
?>

<div class="suple-speed-admin">

    <div class="suple-speed-header">
        <h1><?php _e('Logs', 'suple-speed'); ?></h1>
        <p><?php _e('Review recent events recorded by Suple Speed.', 'suple-speed'); ?></p>
    </div>

    <?php include SUPLE_SPEED_PLUGIN_DIR . 'views/partials/admin-nav.php'; ?>

    <div class="suple-grid suple-grid-3 suple-mt-2">
        <div class="suple-stat-card">
            <span class="suple-stat-value"><?php echo esc_html($log_stats['total'] ?? 0); ?></span>
            <span class="suple-stat-label"><?php _e('Total Entries', 'suple-speed'); ?></span>
        </div>
        <?php if (!empty($log_stats['by_level'])): ?>
            <?php foreach ($log_stats['by_level'] as $row): ?>
                <div class="suple-stat-card">
                    <span class="suple-stat-value"><?php echo esc_html($row->count); ?></span>
                    <span class="suple-stat-label"><?php echo esc_html(ucfirst($row->level)); ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="suple-card suple-mt-2">
        <h3><?php _e('Recent Entries', 'suple-speed'); ?></h3>
        <?php if (!empty($recent_logs)): ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Date', 'suple-speed'); ?></th>
                        <th><?php _e('Level', 'suple-speed'); ?></th>
                        <th><?php _e('Module', 'suple-speed'); ?></th>
                        <th><?php _e('Message', 'suple-speed'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_logs as $log): ?>
                        <tr>
                            <td><?php echo !empty($log->timestamp) ? esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $log->timestamp)) : ''; ?></td>
                            <td><?php echo esc_html(ucfirst($log->level ?? '')); ?></td>
                            <td><?php echo esc_html($log->module ?? ''); ?></td>
                            <td><?php echo esc_html($log->message ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><?php _e('No log entries available yet.', 'suple-speed'); ?></p>
        <?php endif; ?>
    </div>

    <div class="suple-card suple-mt-2">
        <h3><?php _e('Actions', 'suple-speed'); ?></h3>
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
