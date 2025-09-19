<?php
/**
 * Dashboard principal de Suple Speed con secciones tabuladas
 */

if (!defined('ABSPATH')) {
    exit;
}

$dashboard_link = static function ($section = null) {
    $args = ['page' => 'suple-speed'];

    if ($section) {
        $args['section'] = $section;
    }

    return add_query_arg($args, admin_url('admin.php'));
};

$dashboard_data   = $this->get_dashboard_data();
$current_settings = $this->get_current_settings();
$server_caps      = $this->get_server_capabilities();

$active_tab = isset($active_tab) ? sanitize_key($active_tab) : '';

if (empty($active_tab) && isset($_GET['section'])) {
    $active_tab = sanitize_key(wp_unslash($_GET['section']));
}

if (empty($active_tab) && isset($_GET['tab'])) {
    $active_tab = sanitize_key(wp_unslash($_GET['tab']));
}

$tabs = [
    'overview' => [
        'label' => __('Overview', 'suple-speed'),
        'icon'  => 'dashicons-chart-area',
    ],
    'performance' => [
        'label' => __('Performance', 'suple-speed'),
        'icon'  => 'dashicons-performance',
    ],
    'cache' => [
        'label' => __('Cache', 'suple-speed'),
        'icon'  => 'dashicons-update',
    ],
    'assets' => [
        'label' => __('Assets', 'suple-speed'),
        'icon'  => 'dashicons-media-code',
    ],
    'critical' => [
        'label' => __('Critical & Preloads', 'suple-speed'),
        'icon'  => 'dashicons-star-filled',
    ],
    'fonts' => [
        'label' => __('Fonts', 'suple-speed'),
        'icon'  => 'dashicons-editor-textcolor',
    ],
    'images' => [
        'label' => __('Images', 'suple-speed'),
        'icon'  => 'dashicons-format-image',
    ],
    'rules' => [
        'label' => __('Rules', 'suple-speed'),
        'icon'  => 'dashicons-admin-settings',
    ],
    'compatibility' => [
        'label' => __('Compatibility', 'suple-speed'),
        'icon'  => 'dashicons-yes-alt',
    ],
    'tools' => [
        'label' => __('Tools', 'suple-speed'),
        'icon'  => 'dashicons-admin-tools',
    ],
    'logs' => [
        'label' => __('Logs', 'suple-speed'),
        'icon'  => 'dashicons-list-view',
    ],
];

if (empty($active_tab) || !isset($tabs[$active_tab])) {
    $active_tab = 'overview';
}

$psi_defaults = [
    'total_tests'              => 0,
    'avg_performance_mobile'   => 0,
    'avg_performance_desktop'  => 0,
    'latest_test'              => null,
    'improvement_trend'        => null,
];
$psi_stats = wp_parse_args($dashboard_data['psi_stats'] ?? [], $psi_defaults);

$psi_history = get_option('suple_speed_psi_history', []);
$recent_tests = [];
if (is_array($psi_history) && !empty($psi_history)) {
    $recent_tests = array_slice(array_reverse($psi_history), 0, 5);
}

$cache_defaults = [
    'total_files'         => 0,
    'total_size'          => 0,
    'total_size_formatted'=> size_format(0),
    'oldest_file'         => null,
    'newest_file'         => null,
];
$cache_stats = wp_parse_args($dashboard_data['cache_stats'] ?? [], $cache_defaults);
if (empty($cache_stats['total_size_formatted'])) {
    $cache_stats['total_size_formatted'] = size_format((int) ($cache_stats['total_size'] ?? 0));
}

$fonts_module = function_exists('suple_speed') ? suple_speed()->fonts : null;
$fonts_defaults = [
    'total_localized'      => 0,
    'total_size'           => 0,
    'total_size_formatted' => size_format(0),
    'families'             => [],
    'files'                => [],
];
$fonts_stats = wp_parse_args($dashboard_data['fonts_stats'] ?? [], $fonts_defaults);
if (empty($fonts_stats['total_size_formatted'])) {
    $fonts_stats['total_size_formatted'] = size_format((int) ($fonts_stats['total_size'] ?? 0));
}
$font_preloads = [];
if ($fonts_module && method_exists($fonts_module, 'get_font_preloads')) {
    $font_preloads = $fonts_module->get_font_preloads();
}

$asset_preloads = $current_settings['preload_assets'] ?? [];
$critical_css   = $current_settings['critical_css'] ?? '';

$compat_module = function_exists('suple_speed') ? suple_speed()->compat : null;
$compat_report_defaults = [
    'detected_plugins'    => [],
    'potential_conflicts' => [],
    'recommendations'     => [],
    'safe_mode_required'  => false,
];
$compat_report = wp_parse_args($dashboard_data['compat_report'] ?? [], $compat_report_defaults);

$images_defaults = [
    'total_images_processed' => 0,
    'lazy_loading_applied'   => 0,
    'lqip_generated'         => 0,
    'webp_conversions'       => 0,
    'total_size_saved'       => 0,
];
$images_stats = wp_parse_args($dashboard_data['images_stats'] ?? [], $images_defaults);

$cache_module = function_exists('suple_speed') ? suple_speed()->cache : null;
$logger_module = function_exists('suple_speed') ? suple_speed()->logger : null;
$rules_module  = function_exists('suple_speed') ? suple_speed()->rules : null;

$rules = [];
$rules_stats = [
    'total_rules'   => 0,
    'enabled_rules' => 0,
    'global_rules'  => 0,
    'url_rules'     => 0,
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

$log_stats = [
    'total'    => 0,
    'by_level' => [],
    'by_module'=> [],
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

$compat_excluded = [];
if ($compat_module && method_exists($compat_module, 'get_excluded_handles')) {
    $compat_excluded = $compat_module->get_excluded_handles();
}
$manual_exclusions = $current_settings['assets_exclude_handles'] ?? [];

$onboarding_steps = method_exists($this, 'get_onboarding_steps') ? $this->get_onboarding_steps() : [];
if (!is_array($onboarding_steps)) {
    $onboarding_steps = [];
}

$onboarding_state = get_option('suple_speed_onboarding', []);
if (!is_array($onboarding_state)) {
    $onboarding_state = [];
}

$onboarding_total = count($onboarding_steps);
$onboarding_completed = 0;

foreach ($onboarding_steps as $step_key => $step) {
    if (!empty($onboarding_state[$step_key])) {
        $onboarding_completed++;
    }
}

$onboarding_progress = $onboarding_total > 0 ? round(($onboarding_completed / $onboarding_total) * 100) : 0;

$onboarding_critical_remaining = array_filter($onboarding_steps, function($step, $key) use ($onboarding_state) {
    return !empty($step['critical']) && empty($onboarding_state[$key]);
}, ARRAY_FILTER_USE_BOTH);

$onboarding_critical_labels = array_map(function($step) {
    return $step['title'];
}, $onboarding_critical_remaining);

?>

<div class="suple-speed-admin">

    <!-- Header -->
    <div class="suple-speed-header">
        <h1>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                <path d="M13 10h5l-6 6-6-6h5V3h2v7zm-9 9h16v-7h2v8a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-8h2v7z"/>
            </svg>
            <?php _e('Suple Speed', 'suple-speed'); ?>
            <span class="version">v<?php echo esc_html(SUPLE_SPEED_VERSION); ?></span>
        </h1>
        <p><?php _e('Intelligent optimization for WordPress & Elementor', 'suple-speed'); ?></p>
    </div>

    <!-- Navegación principal -->
    <?php include SUPLE_SPEED_PLUGIN_DIR . 'views/partials/admin-nav.php'; ?>

    <!-- Tabs -->
    <nav class="suple-dashboard-tabs">
        <?php foreach ($tabs as $tab_slug => $tab_info): ?>
            <a href="<?php echo esc_url($dashboard_link($tab_slug)); ?>"
               class="suple-tab-button <?php echo $active_tab === $tab_slug ? 'is-active' : ''; ?>"
               data-tab="<?php echo esc_attr($tab_slug); ?>">
                <span class="dashicons <?php echo esc_attr($tab_info['icon']); ?>"></span>
                <?php echo esc_html($tab_info['label']); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Getting Started -->
    <?php if ($onboarding_total > 0): ?>
    <div class="suple-card suple-onboarding" data-total="<?php echo esc_attr($onboarding_total); ?>" data-completed="<?php echo esc_attr($onboarding_completed); ?>">
        <div class="suple-onboarding-head">
            <h3><?php _e('Guía rápida', 'suple-speed'); ?></h3>
            <span class="suple-onboarding-progress-count"><?php echo esc_html(sprintf('%d/%d', $onboarding_completed, $onboarding_total)); ?></span>
        </div>

        <div class="suple-onboarding-progress">
            <div class="suple-onboarding-progress-bar">
                <span class="suple-onboarding-progress-bar-fill" style="width: <?php echo esc_attr($onboarding_progress); ?>%;"></span>
            </div>
            <span class="suple-onboarding-progress-label"><?php echo esc_html($onboarding_progress); ?>%</span>
        </div>

        <p class="suple-onboarding-status <?php echo empty($onboarding_critical_remaining) ? 'success' : 'warning'; ?>"
           data-warning-template="<?php echo esc_attr__('Quedan %1$s pasos críticos por completar: %2$s', 'suple-speed'); ?>"
           data-success-text="<?php echo esc_attr__('¡Listo! Todas las optimizaciones críticas están activas.', 'suple-speed'); ?>">
            <?php if (empty($onboarding_critical_remaining)): ?>
                <?php _e('¡Listo! Todas las optimizaciones críticas están activas.', 'suple-speed'); ?>
            <?php else: ?>
                <?php
                printf(
                    esc_html__('Quedan %1$s pasos críticos por completar: %2$s', 'suple-speed'),
                    count($onboarding_critical_remaining),
                    esc_html(implode(', ', $onboarding_critical_labels))
                );
                ?>
            <?php endif; ?>
        </p>

        <div class="suple-onboarding-steps">
            <?php foreach ($onboarding_steps as $step_key => $step):
                $completed = !empty($onboarding_state[$step_key]);
                $links = $step['links'] ?? [];
                $badge_class = $step['badge_class'] ?? 'info';
                $aria_label = sprintf(__('Marcar "%s" como completado', 'suple-speed'), $step['title']);
            ?>
            <label class="suple-onboarding-card <?php echo $completed ? 'completed' : ''; ?>">
                <input type="checkbox"
                       class="suple-onboarding-step"
                       data-step="<?php echo esc_attr($step_key); ?>"
                       aria-label="<?php echo esc_attr($aria_label); ?>"
                       <?php checked($completed); ?>>
                <span class="suple-onboarding-marker" aria-hidden="true">
                    <span class="dashicons dashicons-yes"></span>
                </span>
                <div class="suple-onboarding-content">
                    <h4><?php echo esc_html($step['title']); ?></h4>
                    <p><?php echo esc_html($step['description']); ?></p>

                    <?php if (!empty($links)): ?>
                    <div class="suple-onboarding-links">
                        <?php foreach ($links as $link):
                            $link_classes = !empty($link['secondary']) ? ' class="secondary"' : '';
                        ?>
                        <a href="<?php echo esc_url($link['url']); ?>"<?php echo $link_classes; ?>>
                            <?php echo esc_html($link['label']); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($step['badge'])): ?>
                    <span class="suple-badge <?php echo esc_attr($badge_class); ?>">
                        <?php echo esc_html($step['badge']); ?>
                    </span>
                    <?php endif; ?>
                </div>
            </label>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Stats Overview -->
    <div class="suple-stats">
        
        <!-- Cache Stats -->
        <div class="suple-stat-card">
            <span class="suple-stat-value"><?php echo esc_html($dashboard_data['cache_stats']['total_files']); ?></span>
            <span class="suple-stat-label"><?php _e('Cached Files', 'suple-speed'); ?></span>
        </div>
        
        <!-- Cache Size -->
        <div class="suple-stat-card">
            <span class="suple-stat-value"><?php echo esc_html($dashboard_data['cache_stats']['total_size_formatted']); ?></span>
            <span class="suple-stat-label"><?php _e('Cache Size', 'suple-speed'); ?></span>
        </div>
        
        <!-- Optimized Fonts -->
        <div class="suple-stat-card">
            <span class="suple-stat-value"><?php echo esc_html($dashboard_data['fonts_stats']['total_localized']); ?></span>
            <span class="suple-stat-label"><?php _e('Local Fonts', 'suple-speed'); ?></span>
        </div>
        
        <!-- Performance Score -->
        <?php if (!empty($dashboard_data['psi_stats']['avg_performance_mobile'])): ?>
        <div class="suple-stat-card">
            <span class="suple-stat-value"><?php echo esc_html($dashboard_data['psi_stats']['avg_performance_mobile']); ?></span>
            <span class="suple-stat-label"><?php _e('Avg Score (Mobile)', 'suple-speed'); ?></span>
        </div>
        <?php endif; ?>
        
    </div>


                <div class="suple-quick-actions">
                    <a href="<?php echo esc_url($dashboard_link('performance')); ?>" class="suple-button suple-open-tab" data-tab="performance">
                        <span class="dashicons dashicons-performance"></span>
                        <?php _e('Run Performance Test', 'suple-speed'); ?>
                    </a>

                    <button type="button" class="suple-button suple-purge-cache" data-purge-action="all">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Purge All Cache', 'suple-speed'); ?>
                    </button>

                    <a href="<?php echo esc_url(admin_url('admin.php?page=suple-speed-settings')); ?>" class="suple-button">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php _e('Configure Settings', 'suple-speed'); ?>
                    </a>
                </div>
            </div>

            <div class="suple-stats">
                <div class="suple-stat-card">
                    <span class="suple-stat-value"><?php echo esc_html($cache_stats['total_files']); ?></span>
                    <span class="suple-stat-label"><?php _e('Cached Files', 'suple-speed'); ?></span>
                </div>

                <div class="suple-stat-card">
                    <span class="suple-stat-value"><?php echo esc_html($cache_stats['total_size_formatted']); ?></span>
                    <span class="suple-stat-label"><?php _e('Cache Size', 'suple-speed'); ?></span>
                </div>

                <div class="suple-stat-card">
                    <span class="suple-stat-value"><?php echo esc_html($fonts_stats['total_localized']); ?></span>
                    <span class="suple-stat-label"><?php _e('Local Fonts', 'suple-speed'); ?></span>
                </div>

                <?php if (!empty($psi_stats['avg_performance_mobile'])): ?>
                    <div class="suple-stat-card">
                        <span class="suple-stat-value"><?php echo esc_html($psi_stats['avg_performance_mobile']); ?></span>
                        <span class="suple-stat-label"><?php _e('Avg Score (Mobile)', 'suple-speed'); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="suple-grid suple-grid-2">
                <div>
                    <div class="suple-card">
                        <h3><?php _e('Current Status', 'suple-speed'); ?></h3>
                        <div class="suple-status-grid">
                            <div class="suple-status-item">
                                <strong><?php _e('Page Cache', 'suple-speed'); ?></strong>
                                <span class="suple-badge <?php echo !empty($current_settings['cache_enabled']) ? 'success' : 'error'; ?>">
                                    <?php echo !empty($current_settings['cache_enabled']) ? esc_html__('Enabled', 'suple-speed') : esc_html__('Disabled', 'suple-speed'); ?>
                                </span>
                            </div>

                            <div class="suple-status-item">
                                <strong><?php _e('Assets Optimization', 'suple-speed'); ?></strong>
                                <span class="suple-badge <?php echo !empty($current_settings['assets_enabled']) ? 'success' : 'error'; ?>">
                                    <?php echo !empty($current_settings['assets_enabled']) ? esc_html__('Enabled', 'suple-speed') : esc_html__('Disabled', 'suple-speed'); ?>
                                </span>
                            </div>

                            <div class="suple-status-item">
                                <strong><?php _e('Compression', 'suple-speed'); ?></strong>
                                <span class="suple-badge <?php echo !empty($current_settings['compression_enabled']) ? 'success' : 'error'; ?>">
                                    <?php echo !empty($current_settings['compression_enabled']) ? esc_html__('Enabled', 'suple-speed') : esc_html__('Disabled', 'suple-speed'); ?>
                                </span>
                            </div>

                            <div class="suple-status-item">
                                <strong><?php _e('Font Localization', 'suple-speed'); ?></strong>
                                <span class="suple-badge <?php echo !empty($current_settings['fonts_local']) ? 'success' : 'error'; ?>">
                                    <?php echo !empty($current_settings['fonts_local']) ? esc_html__('Enabled', 'suple-speed') : esc_html__('Disabled', 'suple-speed'); ?>
                                </span>
                            </div>

                            <div class="suple-status-item">
                                <strong><?php _e('Image Lazy Loading', 'suple-speed'); ?></strong>
                                <span class="suple-badge <?php echo !empty($current_settings['images_lazy']) ? 'success' : 'error'; ?>">
                                    <?php echo !empty($current_settings['images_lazy']) ? esc_html__('Enabled', 'suple-speed') : esc_html__('Disabled', 'suple-speed'); ?>
                                </span>
                            </div>

                            <?php if (!empty($current_settings['safe_mode'])): ?>
                                <div class="suple-status-item">
                                    <strong><?php _e('Safe Mode', 'suple-speed'); ?></strong>
                                    <span class="suple-badge warning">
                                        <?php _e('Active', 'suple-speed'); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($psi_stats['latest_test'])): ?>
                        <div class="suple-card">
                            <h3><?php _e('Latest Performance Test', 'suple-speed'); ?></h3>
                            <?php
                            $latest_test     = $psi_stats['latest_test'];
                            $performance_raw = $latest_test['scores']['performance']['score'] ?? 0;
                            $performance_val = round((float) $performance_raw);
                            $score_class     = $performance_val >= 90 ? 'success' : ($performance_val >= 50 ? 'warning' : 'error');
                            ?>
                            <div class="suple-performance-score">
                                <div class="suple-score-circle <?php echo esc_attr($score_class); ?>">
                                    <span class="suple-score-value"><?php echo esc_html($performance_val); ?></span>
                                </div>
                                <p class="suple-text-center suple-text-muted">
                                    <?php echo esc_html($latest_test['strategy'] ?? ''); ?> •
                                    <?php echo !empty($latest_test['timestamp']) ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $latest_test['timestamp'])) : ''; ?>
                                </p>
                                <div class="suple-text-center">
                                    <a href="<?php echo esc_url($dashboard_link('performance')); ?>" class="suple-button secondary suple-open-tab" data-tab="performance">
                                        <?php _e('View Details', 'suple-speed'); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="suple-card">
                        <h3><?php _e('Recent Activity', 'suple-speed'); ?></h3>
                        <div id="recent-activity">
                            <p class="suple-text-muted"><?php _e('Loading recent activity...', 'suple-speed'); ?></p>
                        </div>
                        <div class="suple-text-center suple-mt-1">
                            <a href="<?php echo esc_url($dashboard_link('logs')); ?>" class="suple-button secondary suple-open-tab" data-tab="logs">
                                <?php _e('Open Logs', 'suple-speed'); ?>
                            </a>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="suple-card">
                        <h3><?php _e('Quick Tools', 'suple-speed'); ?></h3>
                        <div class="suple-quick-tools">
                            <div class="suple-tool-group">
                                <h4><?php _e('Cache Management', 'suple-speed'); ?></h4>
                                <div class="suple-button-group">
                                    <button type="button" class="suple-button suple-purge-cache" data-purge-action="all">
                                        <?php _e('Purge All', 'suple-speed'); ?>
                                    </button>
                                    <button type="button" class="suple-button secondary suple-purge-cache" data-purge-action="url" data-url="<?php echo esc_url(home_url('/')); ?>">
                                        <?php _e('Purge Homepage', 'suple-speed'); ?>
                                    </button>
                                </div>
                            </div>

                            <div class="suple-tool-group">
                                <h4><?php _e('Font Optimization', 'suple-speed'); ?></h4>
                                <div class="suple-button-group">
                                    <a href="<?php echo esc_url($dashboard_link('fonts')); ?>" class="suple-button suple-open-tab" data-tab="fonts">
                                        <?php _e('Manage Fonts', 'suple-speed'); ?>
                                    </a>
                                    <button type="button" class="suple-button secondary suple-scan-fonts">
                                        <?php _e('Scan for Fonts', 'suple-speed'); ?>
                                    </button>
                                </div>
                            </div>

                            <div class="suple-tool-group">
                                <h4><?php _e('Asset Optimization', 'suple-speed'); ?></h4>
                                <div class="suple-button-group">
                                    <a href="<?php echo esc_url($dashboard_link('assets')); ?>" class="suple-button suple-open-tab" data-tab="assets">
                                        <?php _e('Manage Assets', 'suple-speed'); ?>
                                    </a>
                                    <button type="button" class="suple-button secondary suple-scan-handles">
                                        <?php _e('Scan Handles', 'suple-speed'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="suple-card">
                        <h3><?php _e('System Information', 'suple-speed'); ?></h3>
                        <table class="suple-table">
                            <tbody>
                                <tr>
                                    <td><strong><?php _e('WordPress Version', 'suple-speed'); ?></strong></td>
                                    <td><?php echo esc_html($dashboard_data['wp_version']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php _e('PHP Version', 'suple-speed'); ?></strong></td>
                                    <td><?php echo esc_html($dashboard_data['php_version']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php _e('Memory Limit', 'suple-speed'); ?></strong></td>
                                    <td><?php echo esc_html($server_caps['memory_limit']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php _e('WebP Support', 'suple-speed'); ?></strong></td>
                                    <td>
                                        <span class="suple-badge <?php echo !empty($server_caps['webp_support']) ? 'success' : 'error'; ?>">
                                            <?php echo !empty($server_caps['webp_support']) ? esc_html__('Yes', 'suple-speed') : esc_html__('No', 'suple-speed'); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong><?php _e('AVIF Support', 'suple-speed'); ?></strong></td>
                                    <td>
                                        <span class="suple-badge <?php echo !empty($server_caps['avif_support']) ? 'success' : 'error'; ?>">
                                            <?php echo !empty($server_caps['avif_support']) ? esc_html__('Yes', 'suple-speed') : esc_html__('No', 'suple-speed'); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong><?php _e('OPCache', 'suple-speed'); ?></strong></td>
                                    <td>
                                        <span class="suple-badge <?php echo !empty($server_caps['opcache_enabled']) ? 'success' : 'warning'; ?>">
                                            <?php echo !empty($server_caps['opcache_enabled']) ? esc_html__('Enabled', 'suple-speed') : esc_html__('Disabled', 'suple-speed'); ?>
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <?php if (!empty($compat_report['detected_plugins'])): ?>
                        <div class="suple-card">
                            <h3><?php _e('Detected Plugins', 'suple-speed'); ?></h3>
                            <div class="suple-compat-plugins">
                                <?php foreach ($compat_report['detected_plugins'] as $plugin_key => $plugin_info): ?>
                                    <div class="suple-compat-plugin">
                                        <strong><?php echo esc_html($plugin_info['name'] ?? $plugin_key); ?></strong>
                                        <span class="suple-badge success"><?php _e('Compatible', 'suple-speed'); ?></span><br>
                                        <small class="suple-text-muted">v<?php echo esc_html($plugin_info['version'] ?? ''); ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="suple-text-center suple-mt-1">
                                <a href="<?php echo esc_url($dashboard_link('compatibility')); ?>" class="suple-button secondary suple-open-tab" data-tab="compatibility">
                                    <?php _e('View Compatibility Report', 'suple-speed'); ?>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="scan-results" style="display:none;"></div>
        </div>

        <!-- Performance Tab -->
        <div class="suple-tab-panel <?php echo $active_tab === 'performance' ? 'is-active' : ''; ?>" data-tab="performance">
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
                            <span class="suple-stat-value"><?php echo esc_html($psi_stats['avg_performance_desktop'] ?? 0); ?></span>
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

        <!-- Cache Tab -->
        <div class="suple-tab-panel <?php echo $active_tab === 'cache' ? 'is-active' : ''; ?>" data-tab="cache">
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

        <!-- Assets Tab -->
        <div class="suple-tab-panel <?php echo $active_tab === 'assets' ? 'is-active' : ''; ?>" data-tab="assets">
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
                    <?php if (!empty($compat_excluded)): ?>
                        <ul class="suple-list">
                            <?php foreach ($compat_excluded as $handle): ?>
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

        <!-- Critical Tab -->
        <div class="suple-tab-panel <?php echo $active_tab === 'critical' ? 'is-active' : ''; ?>" data-tab="critical">
            <div class="suple-grid suple-grid-2 suple-mt-2">
                <div class="suple-card">
                    <h3><?php _e('Critical CSS', 'suple-speed'); ?></h3>
                    <?php if (!empty($critical_css)): ?>
                        <p><?php _e('A custom critical CSS snippet is currently active.', 'suple-speed'); ?></p>
                        <textarea class="widefat" rows="6" readonly><?php echo esc_textarea(wp_trim_words($critical_css, 120, '…')); ?></textarea>
                    <?php else: ?>
                        <p><?php _e('No critical CSS has been configured yet. You can add one in the settings panel.', 'suple-speed'); ?></p>
                    <?php endif; ?>
                    <a class="suple-button" href="<?php echo esc_url(admin_url('admin.php?page=suple-speed-settings#tab-assets')); ?>">
                        <?php _e('Edit Critical CSS', 'suple-speed'); ?>
                    </a>
                </div>

                <div class="suple-card">
                    <h3><?php _e('Asset Preloads', 'suple-speed'); ?></h3>
                    <?php if (!empty($asset_preloads)): ?>
                        <ul class="suple-list">
                            <?php foreach ($asset_preloads as $preload): ?>
                                <li>
                                    <strong><?php echo esc_html($preload['type'] ?? ($preload['as'] ?? '')); ?></strong> –
                                    <span><?php echo esc_html($preload['url'] ?? ($preload['href'] ?? '')); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p><?php _e('No asset preloads defined. Configure CSS/JS preloads to improve paint times.', 'suple-speed'); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="suple-card suple-mt-2">
                <h3><?php _e('Font Preloads', 'suple-speed'); ?></h3>
                <?php if (!empty($font_preloads)): ?>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php _e('URL', 'suple-speed'); ?></th>
                                <th><?php _e('Type', 'suple-speed'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($font_preloads as $preload): ?>
                                <tr>
                                    <td><?php echo esc_html($preload['href'] ?? ''); ?></td>
                                    <td><?php echo esc_html($preload['type'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p><?php _e('No font preloads detected yet. Suple Speed will populate this list after scanning your pages.', 'suple-speed'); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Fonts Tab -->
        <div class="suple-tab-panel <?php echo $active_tab === 'fonts' ? 'is-active' : ''; ?>" data-tab="fonts">
            <div class="suple-grid suple-grid-2 suple-mt-2">
                <div class="suple-card">
                    <h3><?php _e('Localization Summary', 'suple-speed'); ?></h3>
                    <ul class="suple-stats">
                        <li class="suple-stat-card">
                            <span class="suple-stat-value"><?php echo esc_html($fonts_stats['total_localized']); ?></span>
                            <span class="suple-stat-label"><?php _e('Localized Fonts', 'suple-speed'); ?></span>
                        </li>
                        <li class="suple-stat-card">
                            <span class="suple-stat-value"><?php echo esc_html($fonts_stats['total_size_formatted']); ?></span>
                            <span class="suple-stat-label"><?php _e('Storage Used', 'suple-speed'); ?></span>
                        </li>
                    </ul>
                    <button type="button" class="suple-button suple-localize-fonts">
                        <span class="dashicons dashicons-admin-site-alt3"></span>
                        <?php _e('Localize Fonts Now', 'suple-speed'); ?>
                    </button>
                </div>

                <div class="suple-card">
                    <h3><?php _e('Font Families', 'suple-speed'); ?></h3>
                    <?php if (!empty($fonts_stats['families'])): ?>
                        <ul class="suple-list">
                            <?php foreach ($fonts_stats['families'] as $family): ?>
                                <li><?php echo esc_html($family); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p><?php _e('No localized font families detected yet.', 'suple-speed'); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="suple-card suple-mt-2">
                <h3><?php _e('Localized Files', 'suple-speed'); ?></h3>
                <?php if (!empty($fonts_stats['files'])): ?>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php _e('File', 'suple-speed'); ?></th>
                                <th><?php _e('Size', 'suple-speed'); ?></th>
                                <th><?php _e('Last Modified', 'suple-speed'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fonts_stats['files'] as $file): ?>
                                <tr>
                                    <td><?php echo esc_html($file['name'] ?? ''); ?></td>
                                    <td><?php echo isset($file['size']) ? esc_html(size_format((int) $file['size'])) : ''; ?></td>
                                    <td><?php echo !empty($file['modified']) ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $file['modified'])) : ''; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p><?php _e('No font files have been localized yet. Run a localization to populate this list.', 'suple-speed'); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Images Tab -->
        <div class="suple-tab-panel <?php echo $active_tab === 'images' ? 'is-active' : ''; ?>" data-tab="images">
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
                            <span class="suple-stat-value"><?php echo esc_html($images_stats['lqip_generated']); ?></span>
                            <span class="suple-stat-label"><?php _e('LQIPs Generated', 'suple-speed'); ?></span>
                        </li>
                        <li class="suple-stat-card">
                            <span class="suple-stat-value"><?php echo esc_html($images_stats['webp_conversions']); ?></span>
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

        <!-- Rules Tab -->
        <div class="suple-tab-panel <?php echo $active_tab === 'rules' ? 'is-active' : ''; ?>" data-tab="rules">
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

        <!-- Compatibility Tab -->
        <div class="suple-tab-panel <?php echo $active_tab === 'compatibility' ? 'is-active' : ''; ?>" data-tab="compatibility">
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

        <!-- Tools Tab -->
        <div class="suple-tab-panel <?php echo $active_tab === 'tools' ? 'is-active' : ''; ?>" data-tab="tools">
            <div class="suple-grid suple-grid-2 suple-mt-2">
                <div class="suple-card">
                    <h3><?php _e('Cache Maintenance', 'suple-speed'); ?></h3>
                    <p><?php _e('Review current cache footprint and purge it on demand.', 'suple-speed'); ?></p>
                    <ul class="suple-list">
                        <li>
                            <strong><?php _e('Cached Files', 'suple-speed'); ?>:</strong> <?php echo esc_html($cache_stats['total_files']); ?>
                        </li>
                        <li>
                            <strong><?php _e('Disk Usage', 'suple-speed'); ?>:</strong> <?php echo esc_html($cache_stats['total_size_formatted']); ?>
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

        <!-- Logs Tab -->
        <div class="suple-tab-panel <?php echo $active_tab === 'logs' ? 'is-active' : ''; ?>" data-tab="logs">
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
    </div>
</div>

<style>
.suple-dashboard-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 20px;
}

.suple-tab-button {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border-radius: 6px;
    background: var(--suple-surface-secondary);
    text-decoration: none;
}

.suple-tab-button.is-active {
    background: var(--suple-primary);
    color: #fff;
}

.suple-tab-panels {
    margin-top: 30px;
}

.suple-tab-panel {
    display: none;
}

.suple-tab-panel.is-active {
    display: block;
}

.suple-status-grid {
    display: grid;
    gap: 15px;
}

.suple-status-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid var(--suple-border);
}

.suple-status-item:last-child {
    border-bottom: none;
}

.suple-tool-group {
    margin-bottom: 20px;
}

.suple-tool-group:last-child {
    margin-bottom: 0;
}

.suple-tool-group h4 {
    margin: 0 0 10px 0;
    color: var(--suple-text-light);
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.suple-compat-plugin {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid var(--suple-border);
}

.suple-compat-plugin:last-child {
    border-bottom: none;
}

.suple-onboarding {
    margin: 30px 0;
}

.suple-onboarding-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.suple-onboarding-progress {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.suple-onboarding-progress-bar {
    flex: 1;
    height: 8px;
    background: var(--suple-border);
    border-radius: 999px;
    overflow: hidden;
}

.suple-onboarding-progress-bar-fill {
    display: block;
    height: 100%;
    background: var(--suple-primary);
    transition: width 0.3s ease;
}

.suple-onboarding-progress-label {
    font-weight: 600;
    color: var(--suple-text-light);
}

.suple-onboarding-status {
    font-size: 13px;
    margin-bottom: 18px;
}

.suple-onboarding-status.success {
    color: var(--suple-success);
}

.suple-onboarding-status.warning {
    color: var(--suple-warning);
}

.suple-onboarding-steps {
    display: grid;
    gap: 15px;
}

.suple-onboarding-card {
    display: flex;
    gap: 16px;
    padding: 16px;
    border: 1px solid var(--suple-border);
    border-radius: 12px;
    align-items: flex-start;
    cursor: pointer;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
    background: #fff;
    position: relative;
}

.suple-onboarding-card:hover {
    border-color: var(--suple-primary);
    box-shadow: 0 12px 25px rgba(15, 24, 44, 0.08);
}

.suple-onboarding-card.loading {
    opacity: 0.6;
    pointer-events: none;
}

.suple-onboarding-card input[type="checkbox"] {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
}

.suple-onboarding-marker {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    border: 2px solid var(--suple-border);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: 4px;
    flex-shrink: 0;
    background: #fff;
    transition: all 0.2s ease;
}

.suple-onboarding-marker .dashicons {
    display: none;
    font-size: 14px;
}

.suple-onboarding-card.completed .suple-onboarding-marker {
    background: var(--suple-success);
    border-color: var(--suple-success);
    color: #fff;
}

.suple-onboarding-card.completed .suple-onboarding-marker .dashicons {
    display: block;
}

.suple-onboarding-content h4 {
    margin: 0 0 6px 0;
    font-size: 15px;
}

.suple-onboarding-content p {
    margin: 0;
    color: var(--suple-text-light);
}

.suple-onboarding-links {
    margin-top: 12px;
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.suple-onboarding-links a {
    font-size: 12px;
    font-weight: 600;
    color: var(--suple-primary);
    text-decoration: none;
}

.suple-onboarding-links a.secondary {
    color: var(--suple-text-light);
}

.suple-onboarding-links a::after {
    content: '\2192';
    margin-left: 4px;
}

.suple-onboarding-card .suple-badge {
    margin-top: 12px;
}
</style>

<script>
jQuery(document).ready(function($) {
    function activateTab(tab) {
        var $targetButton = $('.suple-tab-button[data-tab="' + tab + '"]');
        if ($targetButton.length === 0) {
            tab = $('.suple-tab-button').first().data('tab');
            $targetButton = $('.suple-tab-button').first();
        }

        $('.suple-tab-button').removeClass('is-active');
        $targetButton.addClass('is-active');

        $('.suple-tab-panel').removeClass('is-active');
        $('.suple-tab-panel[data-tab="' + tab + '"]').addClass('is-active');

        var url = new URL(window.location.href);
        if (tab === 'overview') {
            url.searchParams.delete('section');
        } else {
            url.searchParams.set('section', tab);
        }
        window.history.replaceState(null, '', url.toString());
    }

    $('.suple-tab-button').on('click', function(event) {
        event.preventDefault();
        activateTab($(this).data('tab'));
    });

    $('.suple-open-tab').on('click', function(event) {
        event.preventDefault();
        activateTab($(this).data('tab'));
        $('html, body').animate({ scrollTop: $('.suple-dashboard-tabs').offset().top - 80 }, 200);
    });

    var initialTab = '<?php echo esc_js($active_tab); ?>';
    var hash = window.location.hash.replace('#', '');
    if (hash && $('.suple-tab-button[data-tab="' + hash + '"]').length) {
        initialTab = hash;
    }

    activateTab(initialTab);

    // Cargar actividad reciente
    function loadRecentActivity() {
        SupleSpeedAdmin.ajaxRequest('get_logs', {
            per_page: 5
        }, function(data) {
            var html = '';

            if (data.logs && data.logs.length > 0) {
                data.logs.forEach(function(log) {
                    var levelClass = log.level === 'error' ? 'error' :
                        log.level === 'warning' ? 'warning' : 'info';

                    html += '<div class="suple-activity-item">';
                    html += '<span class="suple-badge ' + levelClass + '">' + log.level + '</span>';
                    html += ' <strong>' + log.module + '</strong>: ' + log.message;
                    html += '<br><small class="suple-text-muted">' + log.timestamp + '</small>';
                    html += '</div>';
                });
            } else {
                html = '<p class="suple-text-muted"><?php echo esc_js(__('No recent activity', 'suple-speed')); ?></p>';
            }

            jQuery('#recent-activity').html(html);
        });
    }

    loadRecentActivity();
});
</script>
