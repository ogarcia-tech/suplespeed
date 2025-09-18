<?php
/**
 * Página de compatibilidad
 */

if (!defined('ABSPATH')) {
    exit;
}

$compat_module = function_exists('suple_speed') ? suple_speed()->compat : null;
$compat_report = [
    'detected_plugins' => [],
    'potential_conflicts' => [],
    'recommendations' => [],
    'safe_mode_required' => false,
];

if ($compat_module && method_exists($compat_module, 'get_compatibility_report')) {
    $compat_report = $compat_module->get_compatibility_report();
}
?>

<div class="suple-speed-admin">

    <div class="suple-speed-header">
        <h1><?php _e('Compatibility', 'suple-speed'); ?></h1>
        <p><?php _e('Review detected plugins and potential conflicts that may affect optimisation.', 'suple-speed'); ?></p>
    </div>

    <?php include SUPLE_SPEED_PLUGIN_DIR . 'views/partials/admin-nav.php'; ?>

    <div class="suple-card suple-mt-2">
        <h3><?php _e('Safe Mode', 'suple-speed'); ?></h3>
        <?php if (!empty($compat_report['safe_mode_required'])): ?>
            <p class="notice notice-warning">
                <?php _e('Safe Mode is recommended due to detected compatibility issues.', 'suple-speed'); ?>
            </p>
        <?php else: ?>
            <p><?php _e('No critical conflicts detected. Safe Mode is optional.', 'suple-speed'); ?></p>
        <?php endif; ?>
    </div>

    <div class="suple-card suple-mt-2">
        <h3><?php _e('Detected Plugins', 'suple-speed'); ?></h3>
        <?php if (!empty($compat_report['detected_plugins'])): ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Plugin', 'suple-speed'); ?></th>
                        <th><?php _e('Status', 'suple-speed'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($compat_report['detected_plugins'] as $plugin_key => $plugin_info): ?>
                        <tr>
                            <td><?php echo esc_html($plugin_info['name'] ?? $plugin_key); ?></td>
                            <td><?php echo !empty($plugin_info['active']) ? esc_html__('Active', 'suple-speed') : esc_html__('Inactive', 'suple-speed'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><?php _e('No known integrations detected yet.', 'suple-speed'); ?></p>
        <?php endif; ?>
    </div>

    <div class="suple-card suple-mt-2">
        <h3><?php _e('Potential Conflicts', 'suple-speed'); ?></h3>
        <?php if (!empty($compat_report['potential_conflicts'])): ?>
            <ul class="suple-list">
                <?php foreach ($compat_report['potential_conflicts'] as $conflict): ?>
                    <li>
                        <strong><?php echo esc_html($conflict['plugin'] ?? ''); ?></strong> –
                        <?php echo esc_html($conflict['message'] ?? ''); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p><?php _e('No conflicts detected.', 'suple-speed'); ?></p>
        <?php endif; ?>
    </div>

    <div class="suple-card suple-mt-2">
        <h3><?php _e('Recommendations', 'suple-speed'); ?></h3>
        <?php if (!empty($compat_report['recommendations'])): ?>
            <ul class="suple-list">
                <?php foreach ($compat_report['recommendations'] as $recommendation): ?>
                    <li><?php echo esc_html($recommendation); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p><?php _e('Everything looks good! No extra actions required.', 'suple-speed'); ?></p>
        <?php endif; ?>
    </div>
</div>
