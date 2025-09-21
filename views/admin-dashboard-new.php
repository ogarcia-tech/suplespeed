<?php
/**
 * Nuevo Dashboard de Suple Speed - Moderno y Minimalista.
 */

if (!defined('ABSPATH')) {
    exit;
}

$dashboard_data = $this->get_dashboard_data();
$psi_stats = $dashboard_data['psi_stats'] ?? [];
$cache_stats = $dashboard_data['cache_stats'] ?? [];
$settings_url = admin_url('admin.php?page=suple-speed-settings');

// Lógica para determinar el estado general
$main_optimizations_on = ($this->get_current_settings()['cache_enabled'] ?? false) && ($this->get_current_settings()['assets_enabled'] ?? false);
$status_class = $main_optimizations_on ? 'suple-status-ok' : 'suple-status-warn';
$status_text = $main_optimizations_on ? __('Optimizations Active', 'suple-speed') : __('Needs Attention', 'suple-speed');

?>
<div class="suple-speed-admin suple-new-dashboard">

    <header class="suple-header">
        <div class="suple-header-main">
            <h1>Suple Speed <span class="suple-version-tag">v<?php echo esc_html(SUPLE_SPEED_VERSION); ?></span></h1>
            <div class="suple-header-status <?php echo esc_attr($status_class); ?>">
                <?php echo esc_html($status_text); ?>
            </div>
        </div>
        <div class="suple-header-actions">
            <button type="button" class="suple-button suple-purge-cache" data-purge-action="all">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Purge All Cache', 'suple-speed'); ?>
            </button>
            <a href="<?php echo esc_url($settings_url); ?>" class="suple-button secondary">
                <span class="dashicons dashicons-admin-settings"></span>
                <?php _e('Settings', 'suple-speed'); ?>
            </a>
        </div>
    </header>

    <section class="suple-section">
        <h2><?php _e('At a Glance', 'suple-speed'); ?></h2>
        <div class="suple-grid-4">
            <div class="suple-card suple-card-highlight">
                <div class="suple-card-header">
                    <span class="dashicons dashicons-performance"></span>
                    <h3><?php _e('Mobile Score', 'suple-speed'); ?></h3>
                </div>
                <div class="suple-score-meter">
                    <div class="suple-score-value"><?php echo esc_html($psi_stats['avg_performance_mobile'] ?? 'N/A'); ?></div>
                </div>
                <div class="suple-card-footer">
                    <a href="<?php echo esc_url(add_query_arg('tab', 'psi', $settings_url)); ?>"><?php _e('View Details', 'suple-speed'); ?></a>
                </div>
            </div>
            <div class="suple-card">
                 <div class="suple-card-header">
                    <span class="dashicons dashicons-database"></span>
                    <h3><?php _e('Page Cache', 'suple-speed'); ?></h3>
                </div>
                <div class="suple-card-body">
                    <div class="suple-metric-main"><?php echo esc_html($cache_stats['total_files'] ?? 0); ?></div>
                    <div class="suple-metric-label"><?php _e('Cached Pages', 'suple-speed'); ?></div>
                    <div class="suple-metric-sub"><?php echo esc_html($cache_stats['total_size_formatted'] ?? '0 B'); ?> <?php _e('in size', 'suple-speed'); ?></div>
                </div>
            </div>
             <div class="suple-card">
                 <div class="suple-card-header">
                    <span class="dashicons dashicons-media-code"></span>
                    <h3><?php _e('Asset Optimization', 'suple-speed'); ?></h3>
                </div>
                 <div class="suple-card-body">
                    <div class="suple-metric-main"><?php echo ($this->get_current_settings()['assets_enabled'] ?? false) ? __('Active', 'suple-speed') : __('Disabled', 'suple-speed'); ?></div>
                    <div class="suple-metric-label"><?php _e('CSS & JS', 'suple-speed'); ?></div>
                </div>
            </div>
            <div class="suple-card">
                <div class="suple-card-header">
                    <span class="dashicons dashicons-heart"></span>
                    <h3><?php _e('Core Web Vitals', 'suple-speed'); ?></h3>
                </div>
                </div>
        </div>
    </section>
    
    <section class="suple-section">
        <h2><?php _e('Next Steps & Recommendations', 'suple-speed'); ?></h2>
        <div class="suple-recommendations">
            <div class="suple-rec-item suple-rec-warn">
                <span class="dashicons dashicons-warning"></span>
                <div class="suple-rec-text">
                    <strong><?php _e('Action Required:', 'suple-speed'); ?></strong> <?php _e('Your PageSpeed Insights API Key is not configured.', 'suple-speed'); ?>
                </div>
                <a href="<?php echo esc_url(add_query_arg('tab', 'psi', $settings_url)); ?>" class="suple-button"><?php _e('Configure Now', 'suple-speed'); ?></a>
            </div>
            <div class="suple-rec-item suple-rec-info">
                <span class="dashicons dashicons-info"></span>
                <div class="suple-rec-text">
                    <strong><?php _e('Opportunity:', 'suple-speed'); ?></strong> <?php _e('Scan your site to discover and localize Google Fonts for a speed boost.', 'suple-speed'); ?>
                </div>
                <a href="<?php echo esc_url(add_query_arg('tab', 'fonts', $settings_url)); ?>" class="suple-button secondary"><?php _e('Optimize Fonts', 'suple-speed'); ?></a>
            </div>
        </div>
    </section>

</div>
