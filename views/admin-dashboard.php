<?php
/**
 * Dashboard principal de Suple Speed simplificado sin pestañas.
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
];


$psi_defaults = [
    'total_tests'              => 0,
    'avg_performance_mobile'   => 0,
    'avg_performance_desktop'  => 0,
    'latest_test'              => null,
    'improvement_trend'        => null,
];
$psi_stats = wp_parse_args($dashboard_data['psi_stats'] ?? [], $psi_defaults);

$cache_defaults = [
    'total_files'          => 0,
    'total_size'           => 0,
    'total_size_formatted' => size_format(0),
    'oldest_file'          => null,
    'newest_file'          => null,
];
$cache_stats = wp_parse_args($dashboard_data['cache_stats'] ?? [], $cache_defaults);
if (empty($cache_stats['total_size_formatted'])) {
    $cache_stats['total_size_formatted'] = size_format((int) ($cache_stats['total_size'] ?? 0));
}

$images_defaults = [
    'total_images_processed' => 0,
    'lazy_loading_applied'   => 0,
    'lqip_generated'         => 0,
    'webp_conversions'       => 0,
    'total_size_saved'       => 0,
];
$images_stats = wp_parse_args($dashboard_data['images_stats'] ?? [], $images_defaults);
$images_stats['total_images_processed'] = number_format_i18n((int) $images_stats['total_images_processed']);
$images_stats['lazy_loading_applied']   = number_format_i18n((int) $images_stats['lazy_loading_applied']);
$images_stats['lqip_generated']         = number_format_i18n((int) $images_stats['lqip_generated']);
$images_stats['webp_conversions']       = number_format_i18n((int) $images_stats['webp_conversions']);
$images_stats['total_size_saved']       = size_format((int) $images_stats['total_size_saved']);

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

$database_defaults = [
    'database_size'           => 0,
    'database_size_formatted' => size_format(0),
];
$database_stats = wp_parse_args($dashboard_data['database_stats'] ?? [], $database_defaults);


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

$onboarding_dismissed = !empty($onboarding_state['dismissed']);
$onboarding_body_id   = 'suple-onboarding-body-' . uniqid();

$onboarding_total     = count($onboarding_steps);
$onboarding_completed = 0;

foreach ($onboarding_steps as $step_key => $step) {
    if (!empty($onboarding_state[$step_key])) {
        $onboarding_completed++;
    }
}

$onboarding_progress = $onboarding_total > 0 ? round(($onboarding_completed / $onboarding_total) * 100) : 0;

$onboarding_critical_remaining = array_filter(
    $onboarding_steps,
    function ($step, $key) use ($onboarding_state) {
        return !empty($step['critical']) && empty($onboarding_state[$key]);
    },
    ARRAY_FILTER_USE_BOTH
);

$onboarding_critical_labels = array_map(
    function ($step) {
        return $step['title'];
    },
    $onboarding_critical_remaining
);
?>

<div class="suple-speed-admin">

    <div class="suple-speed-header">
        <h1>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M13 10h5l-6 6-6-6h5V3h2v7zm-9 9h16v-7h2v8a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-8h2v7z" />
            </svg>
            <?php _e('Suple Speed', 'suple-speed'); ?>
            <span class="version">v<?php echo esc_html(SUPLE_SPEED_VERSION); ?></span>
        </h1>
        <p><?php _e('Intelligent optimization for WordPress & Elementor', 'suple-speed'); ?></p>
    </div>

    <?php if ($onboarding_total > 0) : ?>
        <section class="suple-dashboard-section">
            <div class="suple-card suple-onboarding <?php echo $onboarding_dismissed ? 'is-dismissed' : ''; ?>"
                data-total="<?php echo esc_attr($onboarding_total); ?>"
                data-completed="<?php echo esc_attr($onboarding_completed); ?>"
                data-dismissed="<?php echo $onboarding_dismissed ? '1' : '0'; ?>">
                <div class="suple-onboarding-head">
                    <div class="suple-onboarding-title">
                        <h3><?php _e('Guía rápida', 'suple-speed'); ?></h3>
                        <span class="suple-onboarding-progress-count"><?php echo esc_html(sprintf('%d/%d', $onboarding_completed, $onboarding_total)); ?></span>
                    </div>
                    <div class="suple-onboarding-actions">
                        <button type="button"
                                class="suple-onboarding-toggle suple-onboarding-dismiss"
                                aria-controls="<?php echo esc_attr($onboarding_body_id); ?>"
                                aria-expanded="<?php echo $onboarding_dismissed ? 'false' : 'true'; ?>">
                            <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                            <span><?php _e('Ocultar', 'suple-speed'); ?></span>
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
    
                    <div class="suple-stat-card">
                        <span class="suple-stat-value database-size-value"><?php echo esc_html($database_stats['database_size_formatted']); ?></span>
                        <span class="suple-stat-label"><?php _e('DB Size', 'suple-speed'); ?></span>
                    </div>
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
                                <a href="<?php echo esc_url(admin_url('admin.php?page=suple-speed-settings#tab-logs')); ?>" class="suple-button secondary">
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
    
                        <div class="suple-card suple-database-summary">
                            <h3><?php _e('Database Health', 'suple-speed'); ?></h3>
                            <div class="suple-stats">
                                <div class="suple-stat-card">
                                    <span class="suple-stat-value database-total-revisions"><?php echo esc_html(number_format_i18n((int) $database_stats['total_revisions'])); ?></span>
                                    <span class="suple-stat-label"><?php _e('Post Revisions', 'suple-speed'); ?></span>
                                </div>
                                <div class="suple-stat-card">
                                    <span class="suple-stat-value database-expired-transients"><?php echo esc_html(number_format_i18n((int) $database_stats['expired_transients'])); ?></span>
                                    <span class="suple-stat-label"><?php _e('Expired Transients', 'suple-speed'); ?></span>
                                </div>
                                <div class="suple-stat-card">
                                    <span class="suple-stat-value database-tables-needing-optimization"><?php echo esc_html(number_format_i18n((int) $database_stats['tables_needing_optimization'])); ?></span>
                                    <span class="suple-stat-label"><?php _e('Tables w/ Overhead', 'suple-speed'); ?></span>
                                </div>
                            </div>
                            <p class="suple-text-muted database-optimization-status">
                                <?php
                                echo wp_kses_post(
                                    sprintf(
                                        __('Tables needing optimization: %1$s of %2$s', 'suple-speed'),
                                        '<strong class="database-tables-needing-optimization">' . esc_html(number_format_i18n((int) $database_stats['tables_needing_optimization'])) . '</strong>',
                                        '<span class="database-total-tables">' . esc_html(number_format_i18n((int) $database_stats['total_tables'])) . '</span>'
                                    )
                                );
                                ?>
                            </p>
                            <ul class="suple-database-meta">
                                <li>
                                    <strong><?php _e('Last revision cleanup', 'suple-speed'); ?>:</strong>
                                    <span class="database-last-revision">
                                        <?php
                                        if (!empty($database_stats['last_revision_cleanup_human'])) {
                                            printf(
                                                esc_html__('%s ago', 'suple-speed'),
                                                esc_html($database_stats['last_revision_cleanup_human'])
                                            );
                                        } else {
                                            esc_html_e('Never', 'suple-speed');
                                        }
                                        ?>
                                    </span>
                                </li>
                                <li>
                                    <strong><?php _e('Last transient cleanup', 'suple-speed'); ?>:</strong>
                                    <span class="database-last-transients">
                                        <?php
                                        if (!empty($database_stats['last_transients_cleanup_human'])) {
                                            printf(
                                                esc_html__('%s ago', 'suple-speed'),
                                                esc_html($database_stats['last_transients_cleanup_human'])
                                            );
                                        } else {
                                            esc_html_e('Never', 'suple-speed');
                                        }
                                        ?>
                                    </span>
                                </li>
                                <li>
                                    <strong><?php _e('Last optimization', 'suple-speed'); ?>:</strong>
                                    <span class="database-last-optimization">
                                        <?php
                                        if (!empty($database_stats['last_optimization_human'])) {
                                            printf(
                                                esc_html__('%s ago', 'suple-speed'),
                                                esc_html($database_stats['last_optimization_human'])
                                            );
                                        } else {
                                            esc_html_e('Never', 'suple-speed');
                                        }
                                        ?>
                                    </span>
                                </li>
                            </ul>
                            <div class="suple-button-group suple-mt-1">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=suple-speed-settings#tab-database')); ?>" class="suple-button secondary">
                                    <?php _e('Open Database Tools', 'suple-speed'); ?>
                                </a>
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
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=suple-speed-settings#tab-compatibility')); ?>" class="suple-button secondary">
                                        <?php _e('View Compatibility Report', 'suple-speed'); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
    
                <div id="scan-results" style="display:none;"></div>
            </div>
        </section>
    <?php endif; ?>

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
                    </div>
                </div>

                <p class="suple-onboarding-collapsed-message" role="status">
                    <?php _e('Has ocultado la guía rápida. Puedes reabrirla cuando quieras.', 'suple-speed'); ?>
                </p>

                <div class="suple-onboarding-body" id="<?php echo esc_attr($onboarding_body_id); ?>" aria-hidden="<?php echo $onboarding_dismissed ? 'true' : 'false'; ?>">
                    <div class="suple-onboarding-progress">
                        <div class="suple-onboarding-progress-bar">
                            <span class="suple-onboarding-progress-bar-fill" style="width: <?php echo esc_attr($onboarding_progress); ?>%;"></span>
                        </div>
                        <span class="suple-onboarding-progress-label"><?php echo esc_html($onboarding_progress); ?>%</span>
                    </div>

                    <p class="suple-onboarding-status <?php echo empty($onboarding_critical_remaining) ? 'success' : 'warning'; ?>"
                        data-warning-template="<?php echo esc_attr__('Quedan %1$s pasos críticos por completar: %2$s', 'suple-speed'); ?>"
                        data-success-text="<?php echo esc_attr__('¡Listo! Todas las optimizaciones críticas están activas.', 'suple-speed'); ?>">
                        <?php if (empty($onboarding_critical_remaining)) : ?>
                            <?php _e('¡Listo! Todas las optimizaciones críticas están activas.', 'suple-speed'); ?>
                        <?php else : ?>
                            <?php
                            printf(
                                esc_html__('Quedan %1$s pasos críticos por completar: %2$s', 'suple-speed'),
                                count($onboarding_critical_remaining),
                                esc_html(implode(', ', $onboarding_critical_labels))
                            );
                            ?>
                        <?php endif; ?>
                        <?php if (!empty($critical_css_started_at)): ?>
                            <li>
                                <strong><?php _e('Started at', 'suple-speed'); ?>:</strong>
                                <?php echo esc_html($critical_css_started_at); ?>
                            </li>
                        <?php endif; ?>
                        <?php if (!empty($critical_css_completed_at)): ?>
                            <li>
                                <strong><?php _e('Completed at', 'suple-speed'); ?>:</strong>
                                <?php echo esc_html($critical_css_completed_at); ?>
                            </li>
                        <?php endif; ?>
                        <?php if (!empty($critical_css_duration)): ?>
                            <li>
                                <strong><?php _e('Generation time', 'suple-speed'); ?>:</strong>
                                <?php echo esc_html($critical_css_duration); ?>
                            </li>
                        <?php endif; ?>
                        <?php if (!empty($critical_css_status['css_length'])): ?>
                            <li>
                                <strong><?php _e('CSS size', 'suple-speed'); ?>:</strong>
                                <?php echo esc_html(sprintf(__('%s characters', 'suple-speed'), number_format_i18n((int) $critical_css_status['css_length']))); ?>
                            </li>
                        <?php endif; ?>
                        <?php if (!empty($critical_css_status['error_code']) && $critical_css_status['status'] === 'error'): ?>
                            <li>
                                <strong><?php _e('Error code', 'suple-speed'); ?>:</strong>
                                <code><?php echo esc_html($critical_css_status['error_code']); ?></code>
                            </li>
                        <?php endif; ?>
                    </ul>
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
                            <?php echo !empty($current_settings['images_webp_rewrite']) ? esc_html__('Enabled', 'suple-speed') : esc_html__('Disabled', 'suple-speed'); ?>
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
    </div>

</div>
