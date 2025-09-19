<?php
/**
 * Dashboard principal de Suple Speed
 */

if (!defined('ABSPATH')) {
    exit;
}

// Obtener datos del dashboard
$dashboard_data = $this->get_dashboard_data();
$current_settings = $this->get_current_settings();
$server_caps = $this->get_server_capabilities();

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

    <!-- Navegación -->
    <?php include SUPLE_SPEED_PLUGIN_DIR . 'views/partials/admin-nav.php'; ?>

    <!-- Welcome Section -->
    <div class="suple-dashboard-welcome">
        <h2><?php _e('Welcome to Suple Speed!', 'suple-speed'); ?></h2>
        <p><?php _e('Boost your PageSpeed Insights scores with intelligent caching, asset optimization, and Elementor-aware performance enhancements.', 'suple-speed'); ?></p>
        
        <div class="suple-quick-actions">
            <a href="<?php echo admin_url('admin.php?page=suple-speed-performance'); ?>" class="suple-button">
                <span class="dashicons dashicons-performance"></span>
                <?php _e('Run Performance Test', 'suple-speed'); ?>
            </a>
            
            <button type="button" class="suple-button suple-purge-cache" data-purge-action="all">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Purge All Cache', 'suple-speed'); ?>
            </button>
            
            <a href="<?php echo admin_url('admin.php?page=suple-speed-settings'); ?>" class="suple-button">
                <span class="dashicons dashicons-admin-settings"></span>
                <?php _e('Configure Settings', 'suple-speed'); ?>
            </a>
        </div>
    </div>

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

    <!-- Main Content Grid -->
    <div class="suple-grid suple-grid-2">
        
        <!-- Left Column -->
        <div>
            
            <!-- Current Status -->
            <div class="suple-card">
                <h3><?php _e('Current Status', 'suple-speed'); ?></h3>
                
                <div class="suple-status-grid">
                    
                    <!-- Cache Status -->
                    <div class="suple-status-item">
                        <strong><?php _e('Page Cache', 'suple-speed'); ?></strong>
                        <span class="suple-badge <?php echo $current_settings['cache_enabled'] ? 'success' : 'error'; ?>">
                            <?php echo $current_settings['cache_enabled'] ? __('Enabled', 'suple-speed') : __('Disabled', 'suple-speed'); ?>
                        </span>
                    </div>
                    
                    <!-- Assets Optimization -->
                    <div class="suple-status-item">
                        <strong><?php _e('Assets Optimization', 'suple-speed'); ?></strong>
                        <span class="suple-badge <?php echo $current_settings['assets_enabled'] ? 'success' : 'error'; ?>">
                            <?php echo $current_settings['assets_enabled'] ? __('Enabled', 'suple-speed') : __('Disabled', 'suple-speed'); ?>
                        </span>
                    </div>
                    
                    <!-- Compression -->
                    <div class="suple-status-item">
                        <strong><?php _e('Compression', 'suple-speed'); ?></strong>
                        <span class="suple-badge <?php echo $current_settings['compression_enabled'] ? 'success' : 'error'; ?>">
                            <?php echo $current_settings['compression_enabled'] ? __('Enabled', 'suple-speed') : __('Disabled', 'suple-speed'); ?>
                        </span>
                    </div>
                    
                    <!-- Font Localization -->
                    <div class="suple-status-item">
                        <strong><?php _e('Font Localization', 'suple-speed'); ?></strong>
                        <span class="suple-badge <?php echo $current_settings['fonts_local'] ? 'success' : 'error'; ?>">
                            <?php echo $current_settings['fonts_local'] ? __('Enabled', 'suple-speed') : __('Disabled', 'suple-speed'); ?>
                        </span>
                    </div>
                    
                    <!-- Image Optimization -->
                    <div class="suple-status-item">
                        <strong><?php _e('Image Lazy Loading', 'suple-speed'); ?></strong>
                        <span class="suple-badge <?php echo $current_settings['images_lazy'] ? 'success' : 'error'; ?>">
                            <?php echo $current_settings['images_lazy'] ? __('Enabled', 'suple-speed') : __('Disabled', 'suple-speed'); ?>
                        </span>
                    </div>
                    
                    <!-- Safe Mode -->
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

            <!-- Performance Score History -->
            <?php if (!empty($dashboard_data['psi_stats']['latest_test'])): ?>
            <div class="suple-card">
                <h3><?php _e('Latest Performance Test', 'suple-speed'); ?></h3>
                
                <?php 
                $latest_test = $dashboard_data['psi_stats']['latest_test'];
                $performance_score = $latest_test['scores']['performance']['score'] ?? 0;
                $score_class = $performance_score >= 90 ? 'success' : ($performance_score >= 50 ? 'warning' : 'error');
                ?>
                
                <div class="suple-performance-score">
                    <div class="suple-score-circle <?php echo $score_class; ?>">
                        <span class="suple-score-value"><?php echo esc_html(round($performance_score)); ?></span>
                    </div>
                    
                    <p class="suple-text-center suple-text-muted">
                        <?php echo esc_html($latest_test['strategy']); ?> • 
                        <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $latest_test['timestamp'])); ?>
                    </p>
                    
                    <div class="suple-text-center">
                        <a href="<?php echo admin_url('admin.php?page=suple-speed-performance'); ?>" class="suple-button secondary">
                            <?php _e('View Details', 'suple-speed'); ?>
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Activity -->
            <div class="suple-card">
                <h3><?php _e('Recent Activity', 'suple-speed'); ?></h3>
                
                <div id="recent-activity">
                    <p class="suple-text-muted"><?php _e('Loading recent activity...', 'suple-speed'); ?></p>
                </div>
                
                <div class="suple-text-center suple-mt-1">
                    <a href="<?php echo admin_url('admin.php?page=suple-speed-logs'); ?>" class="suple-button secondary">
                        <?php _e('View All Logs', 'suple-speed'); ?>
                    </a>
                </div>
            </div>

        </div>

        <!-- Right Column -->
        <div>
            
            <!-- Quick Tools -->
            <div class="suple-card">
                <h3><?php _e('Quick Tools', 'suple-speed'); ?></h3>
                
                <div class="suple-quick-tools">
                    
                    <!-- Purge Cache Options -->
                    <div class="suple-tool-group">
                        <h4><?php _e('Cache Management', 'suple-speed'); ?></h4>
                        <div class="suple-button-group">
                            <button type="button" class="suple-button suple-purge-cache" data-purge-action="all">
                                <?php _e('Purge All', 'suple-speed'); ?>
                            </button>
                            <button type="button" class="suple-button secondary suple-purge-cache" data-purge-action="url" data-url="<?php echo home_url('/'); ?>">
                                <?php _e('Purge Homepage', 'suple-speed'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Font Tools -->
                    <div class="suple-tool-group">
                        <h4><?php _e('Font Optimization', 'suple-speed'); ?></h4>
                        <div class="suple-button-group">
                            <a href="<?php echo admin_url('admin.php?page=suple-speed-fonts'); ?>" class="suple-button">
                                <?php _e('Manage Fonts', 'suple-speed'); ?>
                            </a>
                            <button type="button" class="suple-button secondary suple-scan-fonts">
                                <?php _e('Scan for Fonts', 'suple-speed'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Asset Tools -->
                    <div class="suple-tool-group">
                        <h4><?php _e('Asset Optimization', 'suple-speed'); ?></h4>
                        <div class="suple-button-group">
                            <a href="<?php echo admin_url('admin.php?page=suple-speed-assets'); ?>" class="suple-button">
                                <?php _e('Manage Assets', 'suple-speed'); ?>
                            </a>
                            <button type="button" class="suple-button secondary suple-scan-handles">
                                <?php _e('Scan Handles', 'suple-speed'); ?>
                            </button>
                        </div>
                    </div>
                    
                </div>
            </div>

            <!-- System Information -->
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
                                <span class="suple-badge <?php echo $server_caps['webp_support'] ? 'success' : 'error'; ?>">
                                    <?php echo $server_caps['webp_support'] ? __('Yes', 'suple-speed') : __('No', 'suple-speed'); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('AVIF Support', 'suple-speed'); ?></strong></td>
                            <td>
                                <span class="suple-badge <?php echo $server_caps['avif_support'] ? 'success' : 'error'; ?>">
                                    <?php echo $server_caps['avif_support'] ? __('Yes', 'suple-speed') : __('No', 'suple-speed'); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('OPCache', 'suple-speed'); ?></strong></td>
                            <td>
                                <span class="suple-badge <?php echo $server_caps['opcache_enabled'] ? 'success' : 'warning'; ?>">
                                    <?php echo $server_caps['opcache_enabled'] ? __('Enabled', 'suple-speed') : __('Disabled', 'suple-speed'); ?>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Compatibility Status -->
            <?php if (!empty($dashboard_data['compat_report']['detected_plugins'])): ?>
            <div class="suple-card">
                <h3><?php _e('Detected Plugins', 'suple-speed'); ?></h3>
                
                <div class="suple-compat-plugins">
                    <?php foreach ($dashboard_data['compat_report']['detected_plugins'] as $plugin_key => $plugin_info): ?>
                    <div class="suple-compat-plugin">
                        <strong><?php echo esc_html($plugin_info['name']); ?></strong>
                        <span class="suple-badge success"><?php _e('Compatible', 'suple-speed'); ?></span>
                        <br>
                        <small class="suple-text-muted">v<?php echo esc_html($plugin_info['version']); ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="suple-text-center suple-mt-1">
                    <a href="<?php echo admin_url('admin.php?page=suple-speed-compatibility'); ?>" class="suple-button secondary">
                        <?php _e('View Compatibility Report', 'suple-speed'); ?>
                    </a>
                </div>
            </div>
            <?php endif; ?>

        </div>

    </div>

    <!-- Hidden containers for dynamic content -->
    <div id="scan-results" style="display: none;"></div>

</div>

<style>
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
    // Cargar actividad reciente
    loadRecentActivity();
    
    function loadRecentActivity() {
        SupleSpeedAdmin.ajaxRequest('get_logs', {
            per_page: 5
        }, function(data) {
            let html = '';
            
            if (data.logs && data.logs.length > 0) {
                data.logs.forEach(function(log) {
                    const levelClass = log.level === 'error' ? 'error' : 
                                      log.level === 'warning' ? 'warning' : 
                                      'info';
                    
                    html += '<div class="suple-activity-item">';
                    html += '<span class="suple-badge ' + levelClass + '">' + log.level + '</span>';
                    html += ' <strong>' + log.module + '</strong>: ' + log.message;
                    html += '<br><small class="suple-text-muted">' + log.timestamp + '</small>';
                    html += '</div>';
                });
            } else {
                html = '<p class="suple-text-muted"><?php _e("No recent activity", "suple-speed"); ?></p>';
            }
            
            $('#recent-activity').html(html);
        });
    }
});
</script>