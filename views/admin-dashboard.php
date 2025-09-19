<?php
/**
 * Dashboard principal de Suple Speed simplificado sin pestañas.
 */

if (!defined('ABSPATH')) {
    exit;
}

$dashboard_data = $this->get_dashboard_data();

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
                        <button type="button"
                                class="suple-onboarding-toggle suple-onboarding-reopen"
                                aria-controls="<?php echo esc_attr($onboarding_body_id); ?>"
                                aria-expanded="<?php echo $onboarding_dismissed ? 'false' : 'true'; ?>">
                            <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                            <span><?php _e('Mostrar', 'suple-speed'); ?></span>
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
                    </p>

                    <div class="suple-onboarding-steps">
                        <?php foreach ($onboarding_steps as $step_key => $step) :
                            $completed   = !empty($onboarding_state[$step_key]);
                            $links       = $step['links'] ?? [];
                            $badge_class = $step['badge_class'] ?? 'info';
                            $aria_label  = sprintf(__('Marcar "%s" como completado', 'suple-speed'), $step['title']);
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

                                    <?php if (!empty($links)) : ?>
                                        <div class="suple-onboarding-links">
                                            <?php foreach ($links as $link) :
                                                $link_classes = !empty($link['secondary']) ? ' class="secondary"' : '';
                                                ?>
                                                <a href="<?php echo esc_url($link['url']); ?>"<?php echo $link_classes; ?>>
                                                    <?php echo esc_html($link['label']); ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($step['badge'])) : ?>
                                        <span class="suple-badge <?php echo esc_attr($badge_class); ?>">
                                            <?php echo esc_html($step['badge']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="suple-dashboard-section">
        <div class="suple-card">
            <h3><?php _e('Métricas clave', 'suple-speed'); ?></h3>
            <div class="suple-stats">
                <div class="suple-stat-card">
                    <span class="suple-stat-value"><?php echo esc_html(number_format_i18n((int) $psi_stats['total_tests'])); ?></span>
                    <span class="suple-stat-label"><?php _e('PSI Tests Run', 'suple-speed'); ?></span>
                </div>

                <div class="suple-stat-card">
                    <span class="suple-stat-value"><?php echo esc_html(number_format_i18n((int) $cache_stats['total_files'])); ?></span>
                    <span class="suple-stat-label"><?php _e('Cached Files', 'suple-speed'); ?></span>
                </div>

                <div class="suple-stat-card">
                    <span class="suple-stat-value"><?php echo esc_html($cache_stats['total_size_formatted']); ?></span>
                    <span class="suple-stat-label"><?php _e('Cache Size', 'suple-speed'); ?></span>
                </div>

                <div class="suple-stat-card">
                    <span class="suple-stat-value"><?php echo esc_html(number_format_i18n((int) $fonts_stats['total_localized'])); ?></span>
                    <span class="suple-stat-label"><?php _e('Local Fonts', 'suple-speed'); ?></span>
                </div>

                <?php if (!empty($psi_stats['avg_performance_mobile'])) : ?>
                    <div class="suple-stat-card">
                        <span class="suple-stat-value"><?php echo esc_html(number_format_i18n((float) $psi_stats['avg_performance_mobile'])); ?></span>
                        <span class="suple-stat-label"><?php _e('Avg Score (Mobile)', 'suple-speed'); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($psi_stats['avg_performance_desktop'])) : ?>
                    <div class="suple-stat-card">
                        <span class="suple-stat-value"><?php echo esc_html(number_format_i18n((float) $psi_stats['avg_performance_desktop'])); ?></span>
                        <span class="suple-stat-label"><?php _e('Avg Score (Desktop)', 'suple-speed'); ?></span>
                    </div>
                <?php endif; ?>

                <div class="suple-stat-card">
                    <span class="suple-stat-value database-size-value"><?php echo esc_html($database_stats['database_size_formatted']); ?></span>
                    <span class="suple-stat-label"><?php _e('DB Size', 'suple-speed'); ?></span>
                </div>
            </div>
        </div>
    </section>
</div>
