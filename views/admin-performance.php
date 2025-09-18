<?php
/**
 * Página de rendimiento y PageSpeed Insights
 */

if (!defined('ABSPATH')) {
    exit;
}

$psi_module = function_exists('suple_speed') ? suple_speed()->psi : null;
$psi_stats = [
    'total_tests' => 0,
    'avg_performance_mobile' => 0,
    'avg_performance_desktop' => 0,
    'latest_test' => null,
    'improvement_trend' => null,
];

if ($psi_module && method_exists($psi_module, 'get_psi_stats')) {
    $psi_stats = $psi_module->get_psi_stats();
}

$psi_history = get_option('suple_speed_psi_history', []);
$recent_tests = [];

if (is_array($psi_history) && !empty($psi_history)) {
    $recent_tests = array_slice(array_reverse($psi_history), 0, 5);
}
?>

<div class="suple-speed-admin">

    <div class="suple-speed-header">
        <h1><?php _e('Performance', 'suple-speed'); ?></h1>
        <p><?php _e('Monitor and launch PageSpeed Insights tests for your site.', 'suple-speed'); ?></p>
    </div>

    <?php include SUPLE_SPEED_PLUGIN_DIR . 'views/partials/admin-nav.php'; ?>

    <div class="suple-grid suple-grid-2 suple-mt-2">
        <div class="suple-card">
            <h3><?php _e('Performance Overview', 'suple-speed'); ?></h3>

            <div class="suple-stats">
                <div class="suple-stat-card">
                    <span class="suple-stat-value"><?php echo esc_html($psi_stats['total_tests']); ?></span>
                    <span class="suple-stat-label"><?php _e('Total Tests', 'suple-speed'); ?></span>
                </div>
                <div class="suple-stat-card">
                    <span class="suple-stat-value"><?php echo esc_html($psi_stats['avg_performance_mobile']); ?></span>
                    <span class="suple-stat-label"><?php _e('Avg Mobile Score', 'suple-speed'); ?></span>
                </div>
                <div class="suple-stat-card">
                    <span class="suple-stat-value"><?php echo esc_html($psi_stats['avg_performance_desktop']); ?></span>
                    <span class="suple-stat-label"><?php _e('Avg Desktop Score', 'suple-speed'); ?></span>
                </div>
                <div class="suple-stat-card">
                    <span class="suple-stat-value"><?php echo esc_html($psi_stats['improvement_trend'] ?? 0); ?></span>
                    <span class="suple-stat-label"><?php _e('Trend (Mobile)', 'suple-speed'); ?></span>
                </div>
            </div>

            <div class="suple-mt-2">
                <button type="button" class="suple-button suple-run-psi" data-default-strategy="mobile">
                    <span class="dashicons dashicons-performance"></span>
                    <?php _e('Run PSI Test', 'suple-speed'); ?>
                </button>
                <a class="suple-button secondary" href="<?php echo esc_url(admin_url('admin.php?page=suple-speed-settings#tab-psi')); ?>">
                    <?php _e('Configure API Key', 'suple-speed'); ?>
                </a>
            </div>
        </div>

        <div class="suple-card">
            <h3><?php _e('Latest Test', 'suple-speed'); ?></h3>

            <?php if (!empty($psi_stats['latest_test'])): ?>
                <?php $latest = $psi_stats['latest_test']; ?>
                <p><strong><?php _e('URL:', 'suple-speed'); ?></strong> <?php echo esc_html($latest['url'] ?? ''); ?></p>
                <p><strong><?php _e('Strategy:', 'suple-speed'); ?></strong> <?php echo esc_html(ucfirst($latest['strategy'] ?? '')); ?></p>
                <?php if (!empty($latest['scores']['performance']['score'])): ?>
                    <p><strong><?php _e('Performance Score:', 'suple-speed'); ?></strong> <?php echo esc_html($latest['scores']['performance']['score']); ?></p>
                <?php endif; ?>
                <p><strong><?php _e('Tested At:', 'suple-speed'); ?></strong> <?php echo !empty($latest['timestamp']) ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $latest['timestamp'])) : ''; ?></p>
            <?php else: ?>
                <p><?php _e('No PageSpeed Insights tests have been run yet. Start one to populate this area.', 'suple-speed'); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="suple-card suple-mt-2">
        <h3><?php _e('Recent Tests', 'suple-speed'); ?></h3>

        <?php if (!empty($recent_tests)): ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Date', 'suple-speed'); ?></th>
                        <th><?php _e('URL', 'suple-speed'); ?></th>
                        <th><?php _e('Strategy', 'suple-speed'); ?></th>
                        <th><?php _e('Performance', 'suple-speed'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_tests as $test): ?>
                        <tr>
                            <td><?php echo !empty($test['timestamp']) ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $test['timestamp'])) : ''; ?></td>
                            <td><?php echo esc_html($test['url'] ?? ''); ?></td>
                            <td><?php echo esc_html(ucfirst($test['strategy'] ?? '')); ?></td>
                            <td><?php echo !empty($test['scores']['performance']['score']) ? esc_html($test['scores']['performance']['score']) : '&mdash;'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><?php _e('No previous tests recorded yet.', 'suple-speed'); ?></p>
        <?php endif; ?>
    </div>
</div>
