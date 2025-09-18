<?php
/**
 * Página de reglas inteligentes
 */

if (!defined('ABSPATH')) {
    exit;
}

$rules_module = function_exists('suple_speed') ? suple_speed()->rules : null;
$rules = [];
$rules_stats = [
    'total_rules' => 0,
    'enabled_rules' => 0,
    'global_rules' => 0,
    'url_rules' => 0,
    'rules_by_type' => [],
];

if ($rules_module) {
    if (method_exists($rules_module, 'get_all_rules')) {
        $rules = $rules_module->get_all_rules();
    }
    if (method_exists($rules_module, 'get_rules_stats')) {
        $rules_stats = $rules_module->get_rules_stats();
    }
}
?>

<div class="suple-speed-admin">

    <div class="suple-speed-header">
        <h1><?php _e('Rules', 'suple-speed'); ?></h1>
        <p><?php _e('Fine tune caching and optimization behaviour based on flexible conditions.', 'suple-speed'); ?></p>
    </div>

    <?php include SUPLE_SPEED_PLUGIN_DIR . 'views/partials/admin-nav.php'; ?>

    <div class="suple-grid suple-grid-3 suple-mt-2">
        <div class="suple-stat-card">
            <span class="suple-stat-value"><?php echo esc_html($rules_stats['total_rules']); ?></span>
            <span class="suple-stat-label"><?php _e('Total Rules', 'suple-speed'); ?></span>
        </div>
        <div class="suple-stat-card">
            <span class="suple-stat-value"><?php echo esc_html($rules_stats['enabled_rules']); ?></span>
            <span class="suple-stat-label"><?php _e('Enabled', 'suple-speed'); ?></span>
        </div>
        <div class="suple-stat-card">
            <span class="suple-stat-value"><?php echo esc_html($rules_stats['global_rules']); ?></span>
            <span class="suple-stat-label"><?php _e('Global Rules', 'suple-speed'); ?></span>
        </div>
    </div>

    <div class="suple-card suple-mt-2">
        <h3><?php _e('Configured Rules', 'suple-speed'); ?></h3>

        <?php if (!empty($rules)): ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Name', 'suple-speed'); ?></th>
                        <th><?php _e('Type', 'suple-speed'); ?></th>
                        <th><?php _e('Scope', 'suple-speed'); ?></th>
                        <th><?php _e('Status', 'suple-speed'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rules as $rule_id => $rule): ?>
                        <tr>
                            <td><?php echo esc_html($rule['name'] ?? sprintf(__('Rule #%d', 'suple-speed'), $rule_id)); ?></td>
                            <td><?php echo esc_html($rule['type'] ?? ''); ?></td>
                            <td><?php echo esc_html($rule['scope'] ?? ''); ?></td>
                            <td>
                                <span class="suple-badge <?php echo !empty($rule['enabled']) ? 'success' : 'error'; ?>">
                                    <?php echo !empty($rule['enabled']) ? esc_html__('Enabled', 'suple-speed') : esc_html__('Disabled', 'suple-speed'); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><?php _e('No rules configured yet. Use rules to conditionally enable caching, merging or exclusions.', 'suple-speed'); ?></p>
        <?php endif; ?>
    </div>

    <div class="suple-card suple-mt-2">
        <h3><?php _e('Create or Import Rules', 'suple-speed'); ?></h3>
        <p><?php _e('Set up custom rules or import existing configurations to tailor the optimisation behaviour.', 'suple-speed'); ?></p>
        <div class="suple-button-group">
            <a class="suple-button" href="<?php echo esc_url(admin_url('admin.php?page=suple-speed-settings#tab-advanced')); ?>">
                <?php _e('Open Rule Builder', 'suple-speed'); ?>
            </a>
            <button type="button" class="suple-button secondary suple-import-rules">
                <?php _e('Import Rules', 'suple-speed'); ?>
            </button>
        </div>
    </div>
</div>
