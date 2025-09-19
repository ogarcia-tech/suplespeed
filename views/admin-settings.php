<?php
/**
 * Página de configuración principal
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_settings = $current_settings ?? $this->get_current_settings();
$dashboard_data  = $dashboard_data ?? [];
$server_caps     = $server_caps ?? $this->get_server_capabilities();

$cache_stats     = $cache_stats ?? ($dashboard_data['cache_stats'] ?? []);
$database_stats  = $database_stats ?? ($dashboard_data['database_stats'] ?? []);
$compat_report   = $compat_report ?? ($dashboard_data['compat_report'] ?? []);
$rules           = $rules ?? [];
$rules_stats     = $rules_stats ?? [];
$log_stats       = $log_stats ?? [];
$recent_logs     = $recent_logs ?? [];

$cdn_defaults = [
    'cloudflare' => [
        'enabled'   => false,
        'api_token' => '',
        'zone_id'   => '',
    ],
    'bunnycdn' => [
        'enabled' => false,
        'api_key' => '',
        'zone_id' => '',
    ],
];
$cloudflare_cdn = isset($cloudflare_cdn) ? wp_parse_args($cloudflare_cdn, $cdn_defaults['cloudflare']) : $cdn_defaults['cloudflare'];
$cloudflare_cdn['enabled'] = !empty($cloudflare_cdn['enabled']);
$bunnycdn_cdn = isset($bunnycdn_cdn) ? wp_parse_args($bunnycdn_cdn, $cdn_defaults['bunnycdn']) : $cdn_defaults['bunnycdn'];
$bunnycdn_cdn['enabled'] = !empty($bunnycdn_cdn['enabled']);

$assets_module = function_exists('suple_speed') ? suple_speed()->assets : null;
$preload_recommendations = [];

if ($assets_module && method_exists($assets_module, 'get_preload_recommendations')) {
    $preload_recommendations = $assets_module->get_preload_recommendations();
}
?>

<div class="suple-speed-admin">
    
    <!-- Header -->
    <div class="suple-speed-header">
        <h1><?php _e('Settings', 'suple-speed'); ?></h1>
        <p><?php _e('Configure Suple Speed optimization settings', 'suple-speed'); ?></p>
    </div>

    <!-- Notices Container -->
    <div class="suple-notices"></div>

    <!-- Settings Form -->
    <form id="suple-settings-form" class="suple-auto-save">
        
        <!-- Tabs Navigation -->
        <div class="suple-tabs">
            <div class="suple-tab-nav" role="tablist" aria-label="<?php esc_attr_e('Settings sections', 'suple-speed'); ?>">
                <ul>
                    <li role="presentation">
                        <a href="#tab-general" class="active" role="tab" aria-controls="tab-general" aria-selected="true"><?php _e('General', 'suple-speed'); ?></a>
                    </li>
                    <li role="presentation">
                        <a href="#tab-cache" role="tab" aria-controls="tab-cache" aria-selected="false"><?php _e('Cache', 'suple-speed'); ?></a>
                    </li>
                    <li role="presentation">
                        <a href="#tab-assets" role="tab" aria-controls="tab-assets" aria-selected="false"><?php _e('Asset Optimization', 'suple-speed'); ?></a>
                    </li>
                    <li role="presentation">
                        <a href="#tab-critical" role="tab" aria-controls="tab-critical" aria-selected="false"><?php _e('Critical & Preloads', 'suple-speed'); ?></a>
                    </li>
                    <li role="presentation">
                        <a href="#tab-fonts" role="tab" aria-controls="tab-fonts" aria-selected="false"><?php _e('Fonts', 'suple-speed'); ?></a>
                    </li>
                    <li role="presentation">
                        <a href="#tab-images" role="tab" aria-controls="tab-images" aria-selected="false"><?php _e('Images', 'suple-speed'); ?></a>
                    </li>
                    <li role="presentation">
                        <a href="#tab-psi" role="tab" aria-controls="tab-psi" aria-selected="false"><?php _e('PageSpeed Insights', 'suple-speed'); ?></a>
                    </li>
                    <li role="presentation">
                        <a href="#tab-advanced" role="tab" aria-controls="tab-advanced" aria-selected="false"><?php _e('Advanced', 'suple-speed'); ?></a>
                    </li>
                    <li role="presentation">
                        <a href="#tab-rules" role="tab" aria-controls="tab-rules" aria-selected="false"><?php _e('Rules', 'suple-speed'); ?></a>
                    </li>
                    <li role="presentation">
                        <a href="#tab-compatibility" role="tab" aria-controls="tab-compatibility" aria-selected="false"><?php _e('Compatibility', 'suple-speed'); ?></a>
                    </li>
                    <li role="presentation">
                        <a href="#tab-database" role="tab" aria-controls="tab-database" aria-selected="false"><?php _e('Database', 'suple-speed'); ?></a>
                    </li>
                    <li role="presentation">
                        <a href="#tab-tools" role="tab" aria-controls="tab-tools" aria-selected="false"><?php _e('Tools & Diagnostics', 'suple-speed'); ?></a>
                    </li>
                    <li role="presentation">
                        <a href="#tab-logs" role="tab" aria-controls="tab-logs" aria-selected="false"><?php _e('Logs', 'suple-speed'); ?></a>
                    </li>
                </ul>
            </div>

            <!-- General Tab -->
            <div id="tab-general" class="suple-tab-content active">
                <div class="suple-card">
                    <h3><?php _e('General Settings', 'suple-speed'); ?></h3>
                    
                    <!-- Safe Mode -->
                    <div class="suple-form-row">
                        <div class="suple-form-toggle">
                            <div class="suple-toggle">
                                <label for="safe_mode">
                                    <input type="checkbox" id="safe_mode" name="safe_mode" <?php checked($current_settings['safe_mode'] ?? false); ?>>
                                    <span class="suple-toggle-slider"></span>
                                </label>
                            </div>
                            <label for="safe_mode" class="suple-form-label"><?php _e('Safe Mode', 'suple-speed'); ?></label>
                        </div>
                        <div class="suple-form-help">
                            <?php _e('Disable aggressive optimizations for maximum compatibility. Enable if you experience issues.', 'suple-speed'); ?>
                        </div>
                    </div>

                    <!-- Elementor Compatibility -->
                    <div class="suple-form-row">
                        <div class="suple-form-toggle">
                            <div class="suple-toggle">
                                <label for="elementor_compat">
                                    <input type="checkbox" id="elementor_compat" name="elementor_compat" <?php checked($current_settings['elementor_compat'] ?? true); ?>>
                                    <span class="suple-toggle-slider"></span>
                                </label>
                            </div>
                            <label for="elementor_compat" class="suple-form-label"><?php _e('Elementor Compatibility Mode', 'suple-speed'); ?></label>
                        </div>
                        <div class="suple-form-help">
                            <?php _e('Enhanced compatibility with Elementor page builder. Recommended if using Elementor.', 'suple-speed'); ?>
                        </div>
                    </div>

                    <!-- Log Level -->
                    <div class="suple-form-row">
                        <label for="log_level" class="suple-form-label"><?php _e('Log Level', 'suple-speed'); ?></label>
                        <select id="log_level" name="log_level" class="suple-form-input">
                            <option value="debug" <?php selected($current_settings['log_level'] ?? 'info', 'debug'); ?>><?php _e('Debug', 'suple-speed'); ?></option>
                            <option value="info" <?php selected($current_settings['log_level'] ?? 'info', 'info'); ?>><?php _e('Info', 'suple-speed'); ?></option>
                            <option value="notice" <?php selected($current_settings['log_level'] ?? 'info', 'notice'); ?>><?php _e('Notice', 'suple-speed'); ?></option>
                            <option value="warning" <?php selected($current_settings['log_level'] ?? 'info', 'warning'); ?>><?php _e('Warning', 'suple-speed'); ?></option>
                            <option value="error" <?php selected($current_settings['log_level'] ?? 'info', 'error'); ?>><?php _e('Error', 'suple-speed'); ?></option>
                        </select>
                        <div class="suple-form-help">
                            <?php _e('Choose which level of events to log. Debug provides most detail.', 'suple-speed'); ?>
                        </div>
                    </div>

                    <?php if (is_multisite()): ?>
                    <!-- Multisite Network Mode -->
                    <div class="suple-form-row">
                        <div class="suple-form-toggle">
                            <div class="suple-toggle">
                                <label for="multisite_network">
                                    <input type="checkbox" id="multisite_network" name="multisite_network" <?php checked($current_settings['multisite_network'] ?? false); ?>>
                                    <span class="suple-toggle-slider"></span>
                                </label>
                            </div>
                            <label for="multisite_network" class="suple-form-label"><?php _e('Network Wide Settings', 'suple-speed'); ?></label>
                        </div>
                        <div class="suple-form-help">
                            <?php _e('Apply settings across the entire multisite network.', 'suple-speed'); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

            <!-- Cache Tab -->
            <div id="tab-cache" class="suple-tab-content">
                <div class="suple-card">
                    <h3><?php _e('Page Cache Settings', 'suple-speed'); ?></h3>
                    
                    <!-- Enable Cache -->
                    <div class="suple-form-row">
                        <div class="suple-form-toggle">
                            <div class="suple-toggle">
                                <label for="cache_enabled">
                                    <input type="checkbox" id="cache_enabled" name="cache_enabled" <?php checked($current_settings['cache_enabled'] ?? true); ?>>
                                    <span class="suple-toggle-slider"></span>
                                </label>
                            </div>
                            <label for="cache_enabled" class="suple-form-label"><?php _e('Enable Page Cache', 'suple-speed'); ?></label>
                        </div>
                        <div class="suple-form-help">
                            <?php _e('Cache complete HTML pages to disk for fastest delivery.', 'suple-speed'); ?>
                        </div>
                    </div>

                    <!-- Cache TTL -->
                    <div class="suple-form-row">
                        <label for="cache_ttl" class="suple-form-label"><?php _e('Cache Lifetime (hours)', 'suple-speed'); ?></label>
                        <input type="number" id="cache_ttl" name="cache_ttl" 
                               value="<?php echo esc_attr(($current_settings['cache_ttl'] ?? 24 * HOUR_IN_SECONDS) / HOUR_IN_SECONDS); ?>" 
                               min="1" max="168" class="suple-form-input">
                        <div class="suple-form-help">
                            <?php _e('How long to keep cached pages before regenerating them.', 'suple-speed'); ?>
                            <br>
                            <?php _e('When server rules cannot be written, Suple Speed will automatically apply Cache-Control, Expires and ETag headers to local static files using this lifetime as fallback.', 'suple-speed'); ?>
                        </div>
                    </div>

                    <!-- Compression -->
                    <div class="suple-form-row">
                        <div class="suple-form-toggle">
                            <div class="suple-toggle">
                                <label for="compression_enabled">
                                    <input type="checkbox" id="compression_enabled" name="compression_enabled" <?php checked($current_settings['compression_enabled'] ?? true); ?>>
                                    <span class="suple-toggle-slider"></span>
                                </label>
                            </div>
                            <label for="compression_enabled" class="suple-form-label"><?php _e('Enable Compression', 'suple-speed'); ?></label>
                        </div>
                        <div class="suple-form-help">
                            <?php _e('Enable Gzip and Brotli compression for faster downloads.', 'suple-speed'); ?>
                        </div>
                    </div>

                    <!-- Cache Exclude Parameters -->
                    <div class="suple-form-row">
                        <label for="cache_exclude_params" class="suple-form-label"><?php _e('Exclude Query Parameters', 'suple-speed'); ?></label>
                        <textarea id="cache_exclude_params" name="cache_exclude_params" class="suple-form-input suple-form-textarea" 
                                  placeholder="<?php _e('utm_source, utm_medium, fbclid, gclid', 'suple-speed'); ?>"><?php 
                            echo esc_textarea(implode(', ', $current_settings['cache_exclude_params'] ?? [])); 
                        ?></textarea>
                        <div class="suple-form-help">
                            <?php _e('Comma-separated list of query parameters to ignore when caching pages.', 'suple-speed'); ?>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Assets Tab -->
            <div id="tab-assets" class="suple-tab-content">
                <div class="suple-card">
                    <h3><?php _e('CSS & JavaScript Optimization', 'suple-speed'); ?></h3>
                    
                    <!-- Enable Assets Optimization -->
                    <div class="suple-form-row">
                        <div class="suple-form-toggle">
                            <div class="suple-toggle">
                                <label for="assets_enabled">
                                    <input type="checkbox" id="assets_enabled" name="assets_enabled" <?php checked($current_settings['assets_enabled'] ?? true); ?>>
                                    <span class="suple-toggle-slider"></span>
                                </label>
                            </div>
                            <label for="assets_enabled" class="suple-form-label"><?php _e('Enable Assets Optimization', 'suple-speed'); ?></label>
                        </div>
                        <div class="suple-form-help">
                            <?php _e('Optimize CSS and JavaScript files for better performance.', 'suple-speed'); ?>
                        </div>
                    </div>

                    <!-- CSS Options -->
                    <div class="suple-form-row">
                        <h4><?php _e('CSS Optimization', 'suple-speed'); ?></h4>
                        
                        <div class="suple-form-toggle suple-mb-1">
                            <div class="suple-toggle">
                                <label for="merge_css">
                                    <input type="checkbox" id="merge_css" name="merge_css" <?php checked($current_settings['merge_css'] ?? true); ?>>
                                    <span class="suple-toggle-slider"></span>
                                </label>
                            </div>
                            <label for="merge_css" class="suple-form-label"><?php _e('Merge CSS Files', 'suple-speed'); ?></label>
                        </div>

                        <div class="suple-form-toggle">
                            <div class="suple-toggle">
                                <label for="minify_css">
                                    <input type="checkbox" id="minify_css" name="minify_css" <?php checked($current_settings['minify_css'] ?? true); ?>>
                                    <span class="suple-toggle-slider"></span>
                                </label>
                            </div>
                            <label for="minify_css" class="suple-form-label"><?php _e('Minify CSS', 'suple-speed'); ?></label>
                        </div>
                    </div>

                    <!-- JavaScript Options -->
                    <div class="suple-form-row">
                        <h4><?php _e('JavaScript Optimization', 'suple-speed'); ?></h4>
                        
                        <div class="suple-form-toggle suple-mb-1">
                            <div class="suple-toggle">
                                <label for="merge_js">
                                    <input type="checkbox" id="merge_js" name="merge_js" <?php checked($current_settings['merge_js'] ?? true); ?>>
                                    <span class="suple-toggle-slider"></span>
                                </label>
                            </div>
                            <label for="merge_js" class="suple-form-label"><?php _e('Merge JavaScript Files', 'suple-speed'); ?></label>
                        </div>

                        <div class="suple-form-toggle suple-mb-1">
                            <div class="suple-toggle">
                                <label for="minify_js">
                                    <input type="checkbox" id="minify_js" name="minify_js" <?php checked($current_settings['minify_js'] ?? true); ?>>
                                    <span class="suple-toggle-slider"></span>
                                </label>
                            </div>
                            <label for="minify_js" class="suple-form-label"><?php _e('Minify JavaScript', 'suple-speed'); ?></label>
                        </div>

                        <div class="suple-form-toggle">
                            <div class="suple-toggle">
                                <label for="defer_js">
                                    <input type="checkbox" id="defer_js" name="defer_js" <?php checked($current_settings['defer_js'] ?? true); ?>>
                                    <span class="suple-toggle-slider"></span>
                                </label>
                            </div>
                            <label for="defer_js" class="suple-form-label"><?php _e('Defer JavaScript Loading', 'suple-speed'); ?></label>
                        </div>
                    </div>

                    <!-- Test Mode -->
                    <div class="suple-form-row">
                        <div class="suple-form-toggle">
                            <div class="suple-toggle">
                                <label for="assets_test_mode">
                                    <input type="checkbox" id="assets_test_mode" name="assets_test_mode" <?php checked($current_settings['assets_test_mode'] ?? false); ?>>
                                    <span class="suple-toggle-slider"></span>
                                </label>
                            </div>
                            <label for="assets_test_mode" class="suple-form-label"><?php _e('Test Mode', 'suple-speed'); ?></label>
                        </div>
                        <div class="suple-form-help">
                            <?php _e('Only apply optimizations for administrators to test before going live.', 'suple-speed'); ?>
                        </div>
                    </div>

                </div>
            </div>

            <div id="tab-critical" class="suple-tab-content">
                <div class="suple-card">
                    <h3><?php _e('Preload Suggestions', 'suple-speed'); ?></h3>
                    <p><?php _e('Review the assets detected on your most visited pages and decide which ones should be preloaded.', 'suple-speed'); ?></p>

                    <div class="suple-flex suple-gap-1 suple-flex-wrap">
                        <button type="button" class="suple-button suple-run-preload-collector">
                            <span class="dashicons dashicons-update"></span>
                            <?php _e('Analyze popular pages', 'suple-speed'); ?>
                        </button>
                        <button type="button" class="suple-button secondary suple-refresh-preload-recommendations">
                            <span class="dashicons dashicons-update-alt"></span>
                            <?php _e('Refresh suggestions', 'suple-speed'); ?>
                        </button>
                    </div>

                    <div id="suple-preload-recommendations" class="suple-mt-2" data-empty-message="<?php esc_attr_e('No preload suggestions available yet. Trigger a scan to populate this list.', 'suple-speed'); ?>">
                        <?php if (!empty($preload_recommendations)): ?>
                            <table class="suple-table">
                                <thead>
                                    <tr>
                                        <th><?php _e('Type', 'suple-speed'); ?></th>
                                        <th><?php _e('Resource', 'suple-speed'); ?></th>
                                        <th><?php _e('Size', 'suple-speed'); ?></th>
                                        <th><?php _e('Seen on', 'suple-speed'); ?></th>
                                        <th><?php _e('Position', 'suple-speed'); ?></th>
                                        <th><?php _e('Actions', 'suple-speed'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($preload_recommendations as $recommendation): ?>
                                        <?php
                                        $type_label = $recommendation['type'] ?? ($recommendation['as'] ?? '');
                                        $type_label = $type_label ? ucfirst($type_label) : __('Unknown', 'suple-speed');
                                        $url = esc_url($recommendation['url'] ?? '');
                                        $size_display = isset($recommendation['size']) ? size_format((int) $recommendation['size']) : '—';
                                        $pages = $recommendation['pages'] ?? [];
                                        $position = isset($recommendation['position']) ? (int) $recommendation['position'] : null;
                                        ?>
                                        <tr data-id="<?php echo esc_attr($recommendation['id'] ?? ''); ?>">
                                            <td>
                                                <span class="suple-badge"><?php echo esc_html($type_label); ?></span>
                                                <?php if (!empty($recommendation['crossorigin'])): ?>
                                                    <br><small><?php _e('Crossorigin', 'suple-speed'); ?>: <?php echo esc_html($recommendation['crossorigin']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($url): ?>
                                                    <code><?php echo esc_html($url); ?></code>
                                                <?php else: ?>
                                                    &mdash;
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $size_display === '—' ? '&mdash;' : esc_html($size_display); ?></td>
                                            <td>
                                                <?php if (!empty($pages)): ?>
                                                    <?php foreach ($pages as $page_url): ?>
                                                        <div><a href="<?php echo esc_url($page_url); ?>" target="_blank" rel="noopener"><?php echo esc_html($page_url); ?></a></div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    &mdash;
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $position ? '#' . esc_html($position) : '&mdash;'; ?></td>
                                            <td>
                                                <div class="suple-flex suple-gap-0-5 suple-flex-wrap">
                                                    <button type="button" class="suple-button success suple-accept-preload" data-id="<?php echo esc_attr($recommendation['id'] ?? ''); ?>">
                                                        <span class="dashicons dashicons-upload"></span>
                                                        <?php _e('Add preload', 'suple-speed'); ?>
                                                    </button>
                                                    <button type="button" class="suple-button secondary suple-reject-preload" data-id="<?php echo esc_attr($recommendation['id'] ?? ''); ?>">
                                                        <span class="dashicons dashicons-no-alt"></span>
                                                        <?php _e('Dismiss', 'suple-speed'); ?>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="suple-muted"><?php _e('No preload suggestions available yet. Trigger a scan to populate this list.', 'suple-speed'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Critical CSS -->
                <div class="suple-card">
                    <h3><?php _e('Critical CSS', 'suple-speed'); ?></h3>

                    <!-- Enable Critical CSS -->
                    <div class="suple-form-row">
                        <div class="suple-form-toggle">
                            <div class="suple-toggle">
                                <label for="critical_css_enabled">
                                    <input type="checkbox" id="critical_css_enabled" name="critical_css_enabled" <?php checked($current_settings['critical_css_enabled'] ?? false); ?>>
                                    <span class="suple-toggle-slider"></span>
                                </label>
                            </div>
                            <label for="critical_css_enabled" class="suple-form-label"><?php _e('Enable Critical CSS', 'suple-speed'); ?></label>
                        </div>
                        <div class="suple-form-help">
                            <?php _e('Inline critical CSS for above-the-fold content and defer non-critical CSS.', 'suple-speed'); ?>
                        </div>
                    </div>

                    <!-- General Critical CSS -->
                    <div class="suple-form-row">
                        <label for="critical_css_general" class="suple-form-label"><?php _e('General Critical CSS', 'suple-speed'); ?></label>
                        <textarea id="critical_css_general" name="critical_css_general" class="suple-form-input suple-form-textarea"
                                  rows="8" placeholder="<?php _e('Paste your critical CSS here...', 'suple-speed'); ?>"><?php
                            echo esc_textarea($current_settings['critical_css_general'] ?? '');
                        ?></textarea>
                        <div class="suple-form-help">
                            <?php _e('Critical CSS to be used on all pages. You can override this per page using rules.', 'suple-speed'); ?>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Fonts Tab -->
            <div id="tab-fonts" class="suple-tab-content">
                <div class="suple-card">
                    <h3><?php _e('Font Optimization', 'suple-speed'); ?></h3>
                    
                    <!-- Enable Font Localization -->
                    <div class="suple-form-row">
                        <div class="suple-form-toggle">
                            <div class="suple-toggle">
                                <label for="fonts_local">
                                    <input type="checkbox" id="fonts_local" name="fonts_local" <?php checked($current_settings['fonts_local'] ?? true); ?>>
                                    <span class="suple-toggle-slider"></span>
                                </label>
                            </div>
                            <label for="fonts_local" class="suple-form-label"><?php _e('Localize Google Fonts', 'suple-speed'); ?></label>
                        </div>
                        <div class="suple-form-help">
                            <?php _e('Download and host Google Fonts locally for better performance and privacy.', 'suple-speed'); ?>
                        </div>
                    </div>

                    <!-- Font Display Swap -->
                    <div class="suple-form-row">
                        <div class="suple-form-toggle">
                            <div class="suple-toggle">
                                <label for="fonts_display_swap">
                                    <input type="checkbox" id="fonts_display_swap" name="fonts_display_swap" <?php checked($current_settings['fonts_display_swap'] ?? true); ?>>
                                    <span class="suple-toggle-slider"></span>
                                </label>
                            </div>
                            <label for="fonts_display_swap" class="suple-form-label"><?php _e('Add font-display: swap to local fonts', 'suple-speed'); ?></label>
                        </div>
                        <div class="suple-form-help">
                            <?php _e('Automatically append font-display: swap to @font-face rules that serve fonts from your domain. Disable if this conflicts with custom styling.', 'suple-speed'); ?>
                        </div>
                    </div>

                    <!-- Font Display -->
                    <div class="suple-form-row">
                        <div class="suple-notice info">
                            <p>
                                <strong><?php _e('Font Display Optimization', 'suple-speed'); ?></strong><br>
                                <?php _e('Ensures text stays visible while local webfonts load by leveraging the font-display property.', 'suple-speed'); ?>
                            </p>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Images Tab -->
            <div id="tab-images" class="suple-tab-content">
                <div class="suple-card">
                    <h3><?php _e('Image Optimization', 'suple-speed'); ?></h3>
                    
                    <!-- Enable Lazy Loading -->
                    <div class="suple-form-row">
                        <div class="suple-form-toggle">
                            <div class="suple-toggle">
                                <label for="images_lazy">
                                    <input type="checkbox" id="images_lazy" name="images_lazy" <?php checked($current_settings['images_lazy'] ?? true); ?>>
                                    <span class="suple-toggle-slider"></span>
                                </label>
                            </div>
                            <label for="images_lazy" class="suple-form-label"><?php _e('Enable Lazy Loading', 'suple-speed'); ?></label>
                        </div>
                        <div class="suple-form-help">
                            <?php _e('Load images only when they come into view. Respects native WordPress lazy loading.', 'suple-speed'); ?>
                        </div>
                    </div>

                    <!-- LQIP -->
                    <div class="suple-form-row">
                        <div class="suple-form-toggle">
                            <div class="suple-toggle">
                                <label for="images_lqip">
                                    <input type="checkbox" id="images_lqip" name="images_lqip" <?php checked($current_settings['images_lqip'] ?? false); ?>>
                                    <span class="suple-toggle-slider"></span>
                                </label>
                            </div>
                            <label for="images_lqip" class="suple-form-label"><?php _e('Low Quality Image Placeholders (LQIP)', 'suple-speed'); ?></label>
                        </div>
                        <div class="suple-form-help">
                            <?php _e('Show blurred placeholders while images load to improve perceived performance.', 'suple-speed'); ?>
                        </div>
                    </div>

                    <!-- WebP Rewriting -->
                    <div class="suple-form-row">
                        <div class="suple-form-toggle">
                            <div class="suple-toggle">
                                <label for="images_webp_rewrite">
                                    <input type="checkbox" id="images_webp_rewrite" name="images_webp_rewrite" <?php checked($current_settings['images_webp_rewrite'] ?? false); ?>>
                                    <span class="suple-toggle-slider"></span>
                                </label>
                            </div>
                            <label for="images_webp_rewrite" class="suple-form-label"><?php _e('WebP/AVIF Conversion', 'suple-speed'); ?></label>
                        </div>
                        <div class="suple-form-help">
                            <?php _e('Automatically serve WebP or AVIF images when supported by the browser. Requires existing WebP files or image optimization plugin.', 'suple-speed'); ?>
                        </div>
                    </div>

                    <div class="suple-form-row">
                        <label class="suple-form-label"><?php _e('Critical image heuristic', 'suple-speed'); ?></label>
                        <div class="suple-form-help">
                            <p><?php _e('We automatically preload the site logo, the featured image of the current entry and the first large image in the content (including Elementor hero blocks).', 'suple-speed'); ?></p>
                            <p><?php _e('These images get high fetch priority and skip lazy loading to improve Largest Contentful Paint.', 'suple-speed'); ?></p>
                        </div>
                    </div>

                    <div class="suple-form-row">
                        <label for="images_critical_manual" class="suple-form-label"><?php _e('Manual critical images', 'suple-speed'); ?></label>
                        <textarea id="images_critical_manual" name="images_critical_manual" class="suple-form-textarea" rows="4"><?php echo esc_textarea($current_settings['images_critical_manual'] ?? ''); ?></textarea>
                        <div class="suple-form-help">
                            <?php _e('One value per line. You can use attachment IDs, URLs or paths relative to the uploads directory to force additional images to be treated as critical (useful for false positives or custom layouts).', 'suple-speed'); ?>
                        </div>
                    </div>

                </div>
            </div>

            <!-- PageSpeed Insights Tab -->
            <div id="tab-psi" class="suple-tab-content">
                <div class="suple-card">
                    <h3><?php _e('PageSpeed Insights Integration', 'suple-speed'); ?></h3>
                    
                    <!-- API Key -->
                    <div class="suple-form-row">
                        <label for="psi_api_key" class="suple-form-label"><?php _e('Google PageSpeed Insights API Key', 'suple-speed'); ?></label>
                        <input type="text" id="psi_api_key" name="psi_api_key" 
                               value="<?php echo esc_attr($current_settings['psi_api_key'] ?? ''); ?>" 
                               class="suple-form-input" 
                               placeholder="<?php _e('Enter your API key...', 'suple-speed'); ?>">
                        <div class="suple-form-help">
                            <?php printf(
                                __('Get your free API key from %s', 'suple-speed'),
                                '<a href="https://developers.google.com/speed/docs/insights/v5/get-started" target="_blank">Google Cloud Console</a>'
                            ); ?>
                        </div>
                    </div>

                    <!-- Auto Test -->
                    <div class="suple-form-row">
                        <div class="suple-form-toggle">
                            <div class="suple-toggle">
                                <label for="psi_auto_test">
                                    <input type="checkbox" id="psi_auto_test" name="psi_auto_test" <?php checked($current_settings['psi_auto_test'] ?? false); ?>>
                                    <span class="suple-toggle-slider"></span>
                                </label>
                            </div>
                            <label for="psi_auto_test" class="suple-form-label"><?php _e('Auto-test After Changes', 'suple-speed'); ?></label>
                        </div>
                        <div class="suple-form-help">
                            <?php _e('Automatically run PageSpeed tests after making optimization changes.', 'suple-speed'); ?>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Advanced Tab -->
            <div id="tab-advanced" class="suple-tab-content">
                <div class="suple-card">
                    <h3><?php _e('Advanced Settings', 'suple-speed'); ?></h3>
                    
                    <div class="suple-notice warning">
                        <p>
                            <strong><?php _e('Warning', 'suple-speed'); ?></strong><br>
                            <?php _e('Advanced settings should only be modified by experienced users. Incorrect settings may break your website.', 'suple-speed'); ?>
                        </p>
                    </div>

                    <!-- Asset Groups to Merge -->
                    <div class="suple-form-row">
                        <label class="suple-form-label"><?php _e('CSS Groups to Merge', 'suple-speed'); ?></label>
                        <div class="suple-checkbox-group">
                            <?php
                            $css_groups = ['A' => 'Core & Theme', 'B' => 'Plugins', 'C' => 'Elementor', 'D' => 'Third Party'];
                            $selected_css_groups = $current_settings['assets_merge_css_groups'] ?? ['A', 'B'];

                            foreach ($css_groups as $group => $label):
                            ?>
                            <label class="suple-form-toggle">
                                <input type="checkbox" name="assets_merge_css_groups[]" value="<?php echo esc_attr($group); ?>"
                                       <?php checked(in_array($group, $selected_css_groups)); ?>>
                                <span><?php printf(__('Group %s: %s', 'suple-speed'), $group, $label); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="suple-form-help">
                            <?php _e('Select which asset groups to merge together. Group C (Elementor) should be handled carefully.', 'suple-speed'); ?>
                        </div>
                    </div>

                    <!-- Asynchronous CSS Groups -->
                    <div class="suple-form-row">
                        <label class="suple-form-label"><?php _e('Async CSS Groups', 'suple-speed'); ?></label>
                        <div class="suple-checkbox-group">
                            <?php
                            $async_css_groups = $current_settings['assets_async_css_groups'] ?? [];

                            foreach ($css_groups as $group => $label):
                            ?>
                            <label class="suple-form-toggle">
                                <input type="checkbox" name="assets_async_css_groups[]" value="<?php echo esc_attr($group); ?>"
                                       <?php checked(in_array($group, $async_css_groups)); ?>>
                                <span><?php printf(__('Load Group %s (%s) asynchronously', 'suple-speed'), $group, $label); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="suple-form-help">
                            <?php _e('Selected groups will use <link rel="preload"> + onload to avoid render blocking. Leave critical groups unchecked.', 'suple-speed'); ?>
                        </div>
                    </div>

                    <!-- JavaScript Groups to Merge -->
                    <div class="suple-form-row">
                        <label class="suple-form-label"><?php _e('JavaScript Groups to Merge', 'suple-speed'); ?></label>
                        <div class="suple-checkbox-group">
                            <?php
                            $js_groups = ['A' => 'Core & Theme', 'B' => 'Plugins', 'C' => 'Elementor', 'D' => 'Third Party'];
                            $selected_js_groups = $current_settings['assets_merge_js_groups'] ?? ['A', 'B'];
                            
                            foreach ($js_groups as $group => $label):
                            ?>
                            <label class="suple-form-toggle">
                                <input type="checkbox" name="assets_merge_js_groups[]" value="<?php echo esc_attr($group); ?>" 
                                       <?php checked(in_array($group, $selected_js_groups)); ?>>
                                <span><?php printf(__('Group %s: %s', 'suple-speed'), $group, $label); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="suple-form-help">
                            <?php _e('Select which JavaScript groups to merge together. Be cautious with dependencies.', 'suple-speed'); ?>
                        </div>
                    </div>

                </div>

                <!-- Import/Export -->
                <div class="suple-card">
                    <h3><?php _e('Import/Export Settings', 'suple-speed'); ?></h3>
                    
                    <div class="suple-button-group">
                        <button type="button" class="suple-button secondary suple-export-settings">
                            <span class="dashicons dashicons-download"></span>
                            <?php _e('Export Settings', 'suple-speed'); ?>
                        </button>
                        
                        <label class="suple-button secondary" for="suple-import-file">
                            <span class="dashicons dashicons-upload"></span>
                            <?php _e('Import Settings', 'suple-speed'); ?>
                        </label>
                        <input type="file" id="suple-import-file" accept=".json" style="display: none;">
                    </div>
                    
                    <div class="suple-form-help suple-mt-1">
                        <?php _e('Export your current settings or import settings from another installation.', 'suple-speed'); ?>
                    </div>
                </div>

            </div>

            <div id="tab-rules" class="suple-tab-content">
                <div class="suple-grid suple-grid-3 suple-mt-2">
                    <div class="suple-stat-card">
                        <span class="suple-stat-value"><?php echo esc_html($rules_stats['total_rules'] ?? 0); ?></span>
                        <span class="suple-stat-label"><?php _e('Total Rules', 'suple-speed'); ?></span>
                    </div>
                    <div class="suple-stat-card">
                        <span class="suple-stat-value"><?php echo esc_html($rules_stats['enabled_rules'] ?? 0); ?></span>
                        <span class="suple-stat-label"><?php _e('Enabled', 'suple-speed'); ?></span>
                    </div>
                    <div class="suple-stat-card">
                        <span class="suple-stat-value"><?php echo esc_html($rules_stats['global_rules'] ?? 0); ?></span>
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

            <div id="tab-compatibility" class="suple-tab-content">
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

            <div id="tab-database" class="suple-tab-content">
                <div class="suple-grid suple-grid-2 suple-mt-2">
                    <div class="suple-card">
                        <h3><?php _e('Database Summary', 'suple-speed'); ?></h3>
                        <div class="suple-stats suple-database-stats">
                            <div class="suple-stat-card">
                                <span class="suple-stat-value database-total-revisions"><?php echo esc_html(number_format_i18n((int) ($database_stats['total_revisions'] ?? 0))); ?></span>
                                <span class="suple-stat-label"><?php _e('Post Revisions', 'suple-speed'); ?></span>
                            </div>
                            <div class="suple-stat-card">
                                <span class="suple-stat-value database-expired-transients"><?php echo esc_html(number_format_i18n((int) ($database_stats['expired_transients'] ?? 0))); ?></span>
                                <span class="suple-stat-label"><?php _e('Expired Transients', 'suple-speed'); ?></span>
                            </div>
                            <div class="suple-stat-card">
                                <span class="suple-stat-value database-size-value"><?php echo esc_html($database_stats['database_size_formatted'] ?? size_format(0)); ?></span>
                                <span class="suple-stat-label"><?php _e('Database Size', 'suple-speed'); ?></span>
                            </div>
                            <div class="suple-stat-card">
                                <span class="suple-stat-value database-overhead"><?php echo esc_html($database_stats['overhead_formatted'] ?? size_format(0)); ?></span>
                                <span class="suple-stat-label"><?php _e('Overhead', 'suple-speed'); ?></span>
                            </div>
                        </div>
                        <p class="suple-text-muted database-optimization-status">
                            <?php
                            echo wp_kses_post(
                                sprintf(
                                    __('Tables needing optimization: %1$s of %2$s', 'suple-speed'),
                                    '<strong class="database-tables-needing-optimization">' . esc_html(number_format_i18n((int) ($database_stats['tables_needing_optimization'] ?? 0))) . '</strong>',
                                    '<span class="database-total-tables">' . esc_html(number_format_i18n((int) ($database_stats['total_tables'] ?? 0))) . '</span>'
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
                                        printf(esc_html__('%s ago', 'suple-speed'), esc_html($database_stats['last_revision_cleanup_human']));
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
                                        printf(esc_html__('%s ago', 'suple-speed'), esc_html($database_stats['last_transients_cleanup_human']));
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
                                        printf(esc_html__('%s ago', 'suple-speed'), esc_html($database_stats['last_optimization_human']));
                                    } else {
                                        esc_html_e('Never', 'suple-speed');
                                    }
                                    ?>
                                </span>
                            </li>
                        </ul>
                    </div>
                    <div class="suple-card">
                        <h3><?php _e('Maintenance Actions', 'suple-speed'); ?></h3>
                        <div class="suple-maintenance-warning">
                            <strong><?php _e('Important', 'suple-speed'); ?></strong>
                            <?php _e('Always back up your database before running cleanup operations.', 'suple-speed'); ?>
                        </div>
                        <div class="suple-maintenance-actions">
                            <div class="suple-maintenance-action">
                                <div>
                                    <h4><?php _e('Delete Post Revisions', 'suple-speed'); ?></h4>
                                    <p class="suple-text-muted"><?php _e('Remove stored revisions to reduce database size and keep content history lean.', 'suple-speed'); ?></p>
                                </div>
                                <button type="button" class="suple-button secondary suple-clean-revisions">
                                    <span class="dashicons dashicons-editor-paste-text"></span>
                                    <?php _e('Clean Revisions', 'suple-speed'); ?>
                                </button>
                            </div>
                            <div class="suple-maintenance-action">
                                <div>
                                    <h4><?php _e('Clear Expired Transients', 'suple-speed'); ?></h4>
                                    <p class="suple-text-muted"><?php _e('Delete cached entries that have expired to free up options table space.', 'suple-speed'); ?></p>
                                </div>
                                <button type="button" class="suple-button secondary suple-clean-transients">
                                    <span class="dashicons dashicons-clock"></span>
                                    <?php _e('Clean Transients', 'suple-speed'); ?>
                                </button>
                            </div>
                            <div class="suple-maintenance-action">
                                <div>
                                    <h4><?php _e('Optimize Tables', 'suple-speed'); ?></h4>
                                    <p class="suple-text-muted"><?php _e('Run OPTIMIZE TABLE on WordPress tables that report overhead.', 'suple-speed'); ?></p>
                                </div>
                                <button type="button" class="suple-button suple-optimize-tables" data-scope="overhead">
                                    <span class="dashicons dashicons-database"></span>
                                    <?php _e('Optimize Tables', 'suple-speed'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="suple-card suple-mt-2">
                    <h3><?php _e('Top WordPress Tables', 'suple-speed'); ?></h3>
                    <table class="suple-table suple-database-table">
                        <thead>
                            <tr>
                                <th><?php _e('Table', 'suple-speed'); ?></th>
                                <th><?php _e('Rows', 'suple-speed'); ?></th>
                                <th><?php _e('Size', 'suple-speed'); ?></th>
                                <th><?php _e('Overhead', 'suple-speed'); ?></th>
                                <th><?php _e('Engine', 'suple-speed'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="suple-database-table-body">
                            <?php if (!empty($database_stats['tables'])): ?>
                                <?php foreach ($database_stats['tables'] as $table): ?>
                                    <tr>
                                        <td>
                                            <?php echo esc_html($table['name']); ?>
                                            <?php if (!empty($table['needs_optimization'])): ?>
                                                <span class="suple-badge warning"><?php _e('Needs attention', 'suple-speed'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html(number_format_i18n((int) ($table['rows'] ?? 0))); ?></td>
                                        <td><?php echo esc_html($table['size_formatted'] ?? ''); ?></td>
                                        <td><?php echo !empty($table['overhead']) ? esc_html($table['overhead_formatted']) : '&mdash;'; ?></td>
                                        <td><?php echo esc_html($table['engine'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="suple-text-muted database-empty-message"><?php _e('No table information available.', 'suple-speed'); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="tab-tools" class="suple-tab-content">
                <div class="suple-grid suple-grid-2 suple-mt-2">
                    <div class="suple-card">
                        <h3><?php _e('Cache Maintenance', 'suple-speed'); ?></h3>
                        <p><?php _e('Review current cache footprint and purge it on demand.', 'suple-speed'); ?></p>
                        <div class="cache-stats">
                            <ul class="suple-list">
                                <li>
                                    <strong><?php _e('Cached Files', 'suple-speed'); ?>:</strong>
                                    <span class="total-files"><?php echo esc_html($cache_stats['total_files'] ?? 0); ?></span>
                                </li>
                                <li>
                                    <strong><?php _e('Disk Usage', 'suple-speed'); ?>:</strong>
                                    <span class="total-size"><?php echo esc_html($cache_stats['total_size_formatted'] ?? size_format(0)); ?></span>
                                </li>
                            </ul>
                        </div>
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
                                    <li><?php echo esc_html(sprintf('%s: %d', ucfirst($row->level ?? ''), (int) ($row->count ?? 0))); ?></li>
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
                    <h3><?php _e('CDN Integrations', 'suple-speed'); ?></h3>
                    <p><?php _e('Store API credentials so Suple Speed can purge your CDN after clearing the local cache.', 'suple-speed'); ?></p>

                    <form id="suple-cdn-settings-form" class="suple-form suple-mt-1" autocomplete="off">
                        <div class="suple-cdn-provider">
                            <h4><?php _e('Cloudflare', 'suple-speed'); ?></h4>

                            <div class="suple-form-row">
                                <div class="suple-form-toggle">
                                    <div class="suple-toggle">
                                        <label for="cdn-cloudflare-enabled">
                                            <input type="checkbox" id="cdn-cloudflare-enabled" name="cdn_cloudflare_enabled" <?php checked(!empty($cloudflare_cdn['enabled'])); ?>>
                                            <span class="suple-toggle-slider"></span>
                                        </label>
                                    </div>
                                    <label for="cdn-cloudflare-enabled" class="suple-form-label"><?php _e('Enable Cloudflare purging', 'suple-speed'); ?></label>
                                </div>
                                <div class="suple-form-help"><?php _e('Trigger a Cloudflare cache purge whenever the local cache is cleared.', 'suple-speed'); ?></div>
                            </div>

                            <div class="suple-form-row">
                                <label for="cdn-cloudflare-zone" class="suple-form-label"><?php _e('Zone ID', 'suple-speed'); ?></label>
                                <input type="text" id="cdn-cloudflare-zone" name="cdn_cloudflare_zone_id" class="suple-form-input" value="<?php echo esc_attr($cloudflare_cdn['zone_id']); ?>" autocomplete="off">
                                <div class="suple-form-help"><?php _e('Copy the Zone ID from your Cloudflare dashboard.', 'suple-speed'); ?></div>
                            </div>

                            <div class="suple-form-row">
                                <label for="cdn-cloudflare-token" class="suple-form-label"><?php _e('API Token', 'suple-speed'); ?></label>
                                <input type="password" id="cdn-cloudflare-token" name="cdn_cloudflare_api_token" class="suple-form-input" value="<?php echo esc_attr($cloudflare_cdn['api_token']); ?>" autocomplete="new-password">
                                <div class="suple-form-help"><?php _e('Use a token with “Cache Purge” permissions.', 'suple-speed'); ?></div>
                            </div>
                        </div>

                        <div class="suple-cdn-provider suple-mt-2">
                            <h4><?php _e('BunnyCDN', 'suple-speed'); ?></h4>

                            <div class="suple-form-row">
                                <div class="suple-form-toggle">
                                    <div class="suple-toggle">
                                        <label for="cdn-bunny-enabled">
                                            <input type="checkbox" id="cdn-bunny-enabled" name="cdn_bunnycdn_enabled" <?php checked(!empty($bunnycdn_cdn['enabled'])); ?>>
                                            <span class="suple-toggle-slider"></span>
                                        </label>
                                    </div>
                                    <label for="cdn-bunny-enabled" class="suple-form-label"><?php _e('Enable BunnyCDN purging', 'suple-speed'); ?></label>
                                </div>
                                <div class="suple-form-help"><?php _e('Send purge requests to your BunnyCDN pull zone after local cache clears.', 'suple-speed'); ?></div>
                            </div>

                            <div class="suple-form-row">
                                <label for="cdn-bunny-zone" class="suple-form-label"><?php _e('Pull Zone ID', 'suple-speed'); ?></label>
                                <input type="text" id="cdn-bunny-zone" name="cdn_bunnycdn_zone_id" class="suple-form-input" value="<?php echo esc_attr($bunnycdn_cdn['zone_id']); ?>" autocomplete="off">
                                <div class="suple-form-help"><?php _e('Enter the numeric ID or name of the pull zone to purge.', 'suple-speed'); ?></div>
                            </div>

                            <div class="suple-form-row">
                                <label for="cdn-bunny-key" class="suple-form-label"><?php _e('API Key', 'suple-speed'); ?></label>
                                <input type="password" id="cdn-bunny-key" name="cdn_bunnycdn_api_key" class="suple-form-input" value="<?php echo esc_attr($bunnycdn_cdn['api_key']); ?>" autocomplete="new-password">
                                <div class="suple-form-help"><?php _e('Use the API Access Key from the BunnyCDN dashboard.', 'suple-speed'); ?></div>
                            </div>
                        </div>

                        <div class="suple-button-group suple-mt-2">
                            <button type="button" class="suple-button suple-save-cdn-settings">
                                <?php _e('Save CDN Credentials', 'suple-speed'); ?>
                            </button>
                        </div>
                    </form>
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

            <div id="tab-logs" class="suple-tab-content">
                <div class="suple-grid suple-grid-3 suple-mt-2">
                    <div class="suple-stat-card">
                        <span class="suple-stat-value"><?php echo esc_html($log_stats['total'] ?? 0); ?></span>
                        <span class="suple-stat-label"><?php _e('Total Entries', 'suple-speed'); ?></span>
                    </div>
                    <?php if (!empty($log_stats['by_level'])): ?>
                        <?php foreach ($log_stats['by_level'] as $row): ?>
                            <div class="suple-stat-card">
                                <span class="suple-stat-value"><?php echo esc_html((int) ($row->count ?? 0)); ?></span>
                                <span class="suple-stat-label"><?php echo esc_html(ucfirst($row->level ?? '')); ?></span>
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

        <!-- Action Buttons -->
        <div class="suple-card">
            <div class="suple-button-group">
                <button type="button" class="suple-button suple-save-settings">
                    <?php _e('Save Settings', 'suple-speed'); ?>
                </button>
                
                <button type="button" class="suple-button secondary suple-reset-settings">
                    <?php _e('Reset to Defaults', 'suple-speed'); ?>
                </button>
            </div>
        </div>

    </form>

</div>

<style>
.suple-checkbox-group {
    display: grid;
    gap: 8px;
}

.suple-checkbox-group .suple-form-toggle {
    margin: 0;
}

.suple-auto-save-indicator {
    position: fixed;
    top: 32px;
    right: 20px;
    background: var(--suple-primary);
    color: white;
    padding: 8px 12px;
    border-radius: var(--suple-radius);
    font-size: 12px;
    z-index: 9999;
    opacity: 0.9;
}

.suple-auto-save-indicator.success {
    background: var(--suple-success);
}

.suple-auto-save-indicator.error {
    background: var(--suple-error);
}
</style>
