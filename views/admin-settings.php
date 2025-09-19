<?php
/**
 * Página de configuración principal
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_settings = $this->get_current_settings();
?>

<div class="suple-speed-admin">
    
    <!-- Header -->
    <div class="suple-speed-header">
        <h1><?php _e('Settings', 'suple-speed'); ?></h1>
        <p><?php _e('Configure Suple Speed optimization settings', 'suple-speed'); ?></p>
    </div>

    <!-- Navegación -->
    <?php include SUPLE_SPEED_PLUGIN_DIR . 'views/partials/admin-nav.php'; ?>

    <!-- Notices Container -->
    <div class="suple-notices"></div>

    <!-- Settings Form -->
    <form id="suple-settings-form" class="suple-auto-save">
        
        <!-- Tabs Navigation -->
        <div class="suple-tabs">
            <div class="suple-tab-nav">
                <ul>
                    <li><a href="#tab-general" class="active"><?php _e('General', 'suple-speed'); ?></a></li>
                    <li><a href="#tab-cache"><?php _e('Cache', 'suple-speed'); ?></a></li>
                    <li><a href="#tab-assets"><?php _e('Assets', 'suple-speed'); ?></a></li>
                    <li><a href="#tab-fonts"><?php _e('Fonts', 'suple-speed'); ?></a></li>
                    <li><a href="#tab-images"><?php _e('Images', 'suple-speed'); ?></a></li>
                    <li><a href="#tab-psi"><?php _e('PageSpeed Insights', 'suple-speed'); ?></a></li>
                    <li><a href="#tab-advanced"><?php _e('Advanced', 'suple-speed'); ?></a></li>
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
                                <input type="checkbox" id="safe_mode" name="safe_mode" <?php checked($current_settings['safe_mode'] ?? false); ?>>
                                <span class="suple-toggle-slider"></span>
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
                                <input type="checkbox" id="elementor_compat" name="elementor_compat" <?php checked($current_settings['elementor_compat'] ?? true); ?>>
                                <span class="suple-toggle-slider"></span>
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
                                <input type="checkbox" id="multisite_network" name="multisite_network" <?php checked($current_settings['multisite_network'] ?? false); ?>>
                                <span class="suple-toggle-slider"></span>
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
                                <input type="checkbox" id="cache_enabled" name="cache_enabled" <?php checked($current_settings['cache_enabled'] ?? true); ?>>
                                <span class="suple-toggle-slider"></span>
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
                                <input type="checkbox" id="compression_enabled" name="compression_enabled" <?php checked($current_settings['compression_enabled'] ?? true); ?>>
                                <span class="suple-toggle-slider"></span>
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
                                <input type="checkbox" id="assets_enabled" name="assets_enabled" <?php checked($current_settings['assets_enabled'] ?? true); ?>>
                                <span class="suple-toggle-slider"></span>
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
                                <input type="checkbox" id="merge_css" name="merge_css" <?php checked($current_settings['merge_css'] ?? true); ?>>
                                <span class="suple-toggle-slider"></span>
                            </div>
                            <label for="merge_css" class="suple-form-label"><?php _e('Merge CSS Files', 'suple-speed'); ?></label>
                        </div>

                        <div class="suple-form-toggle">
                            <div class="suple-toggle">
                                <input type="checkbox" id="minify_css" name="minify_css" <?php checked($current_settings['minify_css'] ?? true); ?>>
                                <span class="suple-toggle-slider"></span>
                            </div>
                            <label for="minify_css" class="suple-form-label"><?php _e('Minify CSS', 'suple-speed'); ?></label>
                        </div>
                    </div>

                    <!-- JavaScript Options -->
                    <div class="suple-form-row">
                        <h4><?php _e('JavaScript Optimization', 'suple-speed'); ?></h4>
                        
                        <div class="suple-form-toggle suple-mb-1">
                            <div class="suple-toggle">
                                <input type="checkbox" id="merge_js" name="merge_js" <?php checked($current_settings['merge_js'] ?? true); ?>>
                                <span class="suple-toggle-slider"></span>
                            </div>
                            <label for="merge_js" class="suple-form-label"><?php _e('Merge JavaScript Files', 'suple-speed'); ?></label>
                        </div>

                        <div class="suple-form-toggle suple-mb-1">
                            <div class="suple-toggle">
                                <input type="checkbox" id="minify_js" name="minify_js" <?php checked($current_settings['minify_js'] ?? true); ?>>
                                <span class="suple-toggle-slider"></span>
                            </div>
                            <label for="minify_js" class="suple-form-label"><?php _e('Minify JavaScript', 'suple-speed'); ?></label>
                        </div>

                        <div class="suple-form-toggle">
                            <div class="suple-toggle">
                                <input type="checkbox" id="defer_js" name="defer_js" <?php checked($current_settings['defer_js'] ?? true); ?>>
                                <span class="suple-toggle-slider"></span>
                            </div>
                            <label for="defer_js" class="suple-form-label"><?php _e('Defer JavaScript Loading', 'suple-speed'); ?></label>
                        </div>
                    </div>

                    <!-- Test Mode -->
                    <div class="suple-form-row">
                        <div class="suple-form-toggle">
                            <div class="suple-toggle">
                                <input type="checkbox" id="assets_test_mode" name="assets_test_mode" <?php checked($current_settings['assets_test_mode'] ?? false); ?>>
                                <span class="suple-toggle-slider"></span>
                            </div>
                            <label for="assets_test_mode" class="suple-form-label"><?php _e('Test Mode', 'suple-speed'); ?></label>
                        </div>
                        <div class="suple-form-help">
                            <?php _e('Only apply optimizations for administrators to test before going live.', 'suple-speed'); ?>
                        </div>
                    </div>

                </div>

                <!-- Critical CSS -->
                <div class="suple-card">
                    <h3><?php _e('Critical CSS', 'suple-speed'); ?></h3>
                    
                    <!-- Enable Critical CSS -->
                    <div class="suple-form-row">
                        <div class="suple-form-toggle">
                            <div class="suple-toggle">
                                <input type="checkbox" id="critical_css_enabled" name="critical_css_enabled" <?php checked($current_settings['critical_css_enabled'] ?? false); ?>>
                                <span class="suple-toggle-slider"></span>
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
                                <input type="checkbox" id="fonts_local" name="fonts_local" <?php checked($current_settings['fonts_local'] ?? true); ?>>
                                <span class="suple-toggle-slider"></span>
                            </div>
                            <label for="fonts_local" class="suple-form-label"><?php _e('Localize Google Fonts', 'suple-speed'); ?></label>
                        </div>
                        <div class="suple-form-help">
                            <?php _e('Download and host Google Fonts locally for better performance and privacy.', 'suple-speed'); ?>
                        </div>
                    </div>

                    <!-- Font Display -->
                    <div class="suple-form-row">
                        <div class="suple-notice info">
                            <p>
                                <strong><?php _e('Font Display Optimization', 'suple-speed'); ?></strong><br>
                                <?php _e('Suple Speed automatically adds font-display: swap to all fonts for better loading performance.', 'suple-speed'); ?>
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
                                <input type="checkbox" id="images_lazy" name="images_lazy" <?php checked($current_settings['images_lazy'] ?? true); ?>>
                                <span class="suple-toggle-slider"></span>
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
                                <input type="checkbox" id="images_lqip" name="images_lqip" <?php checked($current_settings['images_lqip'] ?? false); ?>>
                                <span class="suple-toggle-slider"></span>
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
                                <input type="checkbox" id="images_webp_rewrite" name="images_webp_rewrite" <?php checked($current_settings['images_webp_rewrite'] ?? false); ?>>
                                <span class="suple-toggle-slider"></span>
                            </div>
                            <label for="images_webp_rewrite" class="suple-form-label"><?php _e('WebP/AVIF Conversion', 'suple-speed'); ?></label>
                        </div>
                        <div class="suple-form-help">
                            <?php _e('Automatically serve WebP or AVIF images when supported by the browser. Requires existing WebP files or image optimization plugin.', 'suple-speed'); ?>
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
                                <input type="checkbox" id="psi_auto_test" name="psi_auto_test" <?php checked($current_settings['psi_auto_test'] ?? false); ?>>
                                <span class="suple-toggle-slider"></span>
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