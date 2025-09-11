<?php

namespace SupleSpeed;

/**
 * Clase de compatibilidad con otros plugins y temas
 */
class Compat {
    
    /**
     * Plugins detectados
     */
    private $detected_plugins = [];
    
    /**
     * Configuraciones de compatibilidad
     */
    private $compat_configs = [];
    
    public function __construct() {
        $this->detect_plugins();
        $this->init_compatibility_rules();
        $this->apply_compatibility_fixes();
    }
    
    /**
     * Detectar plugins activos relevantes
     */
    private function detect_plugins() {
        // Elementor
        if (defined('ELEMENTOR_VERSION')) {
            $this->detected_plugins['elementor'] = [
                'name' => 'Elementor',
                'version' => ELEMENTOR_VERSION,
                'active' => true
            ];
        }
        
        // Elementor Pro
        if (defined('ELEMENTOR_PRO_VERSION')) {
            $this->detected_plugins['elementor_pro'] = [
                'name' => 'Elementor Pro',
                'version' => ELEMENTOR_PRO_VERSION,
                'active' => true
            ];
        }
        
        // WooCommerce
        if (class_exists('WooCommerce')) {
            $this->detected_plugins['woocommerce'] = [
                'name' => 'WooCommerce',
                'version' => WC()->version ?? 'unknown',
                'active' => true
            ];
        }
        
        // WPML
        if (defined('WPML_VERSION')) {
            $this->detected_plugins['wpml'] = [
                'name' => 'WPML',
                'version' => WPML_VERSION,
                'active' => true
            ];
        }
        
        // Polylang
        if (function_exists('pll_current_language')) {
            $this->detected_plugins['polylang'] = [
                'name' => 'Polylang',
                'version' => POLYLANG_VERSION ?? 'unknown',
                'active' => true
            ];
        }
        
        // Yoast SEO
        if (defined('WPSEO_VERSION')) {
            $this->detected_plugins['yoast'] = [
                'name' => 'Yoast SEO',
                'version' => WPSEO_VERSION,
                'active' => true
            ];
        }
        
        // RankMath
        if (defined('RANK_MATH_VERSION')) {
            $this->detected_plugins['rankmath'] = [
                'name' => 'RankMath',
                'version' => RANK_MATH_VERSION,
                'active' => true
            ];
        }
        
        // Contact Form 7
        if (defined('WPCF7_VERSION')) {
            $this->detected_plugins['cf7'] = [
                'name' => 'Contact Form 7',
                'version' => WPCF7_VERSION,
                'active' => true
            ];
        }
        
        // EWWW Image Optimizer
        if (class_exists('EWWW_Image_Optimizer')) {
            $this->detected_plugins['ewww'] = [
                'name' => 'EWWW Image Optimizer',
                'version' => EWWW_IMAGE_OPTIMIZER_VERSION ?? 'unknown',
                'active' => true
            ];
        }
        
        // WebP Express
        if (class_exists('WebPExpress\\WebPExpress')) {
            $this->detected_plugins['webp_express'] = [
                'name' => 'WebP Express',
                'version' => 'unknown',
                'active' => true
            ];
        }
        
        // WP Rocket
        if (function_exists('get_rocket_option')) {
            $this->detected_plugins['wp_rocket'] = [
                'name' => 'WP Rocket',
                'version' => WP_ROCKET_VERSION ?? 'unknown',
                'active' => true
            ];
        }
        
        // W3 Total Cache
        if (defined('W3TC_VERSION')) {
            $this->detected_plugins['w3tc'] = [
                'name' => 'W3 Total Cache',
                'version' => W3TC_VERSION,
                'active' => true
            ];
        }
        
        // WP Super Cache
        if (function_exists('wp_super_cache_text_domain')) {
            $this->detected_plugins['wp_super_cache'] = [
                'name' => 'WP Super Cache',
                'version' => 'unknown',
                'active' => true
            ];
        }
        
        // LiteSpeed Cache
        if (class_exists('LiteSpeed\\Core')) {
            $this->detected_plugins['litespeed'] = [
                'name' => 'LiteSpeed Cache',
                'version' => 'unknown',
                'active' => true
            ];
        }
        
        // Cloudflare
        if (defined('CLOUDFLARE_VERSION')) {
            $this->detected_plugins['cloudflare'] = [
                'name' => 'Cloudflare',
                'version' => CLOUDFLARE_VERSION,
                'active' => true
            ];
        }
    }
    
    /**
     * Inicializar reglas de compatibilidad
     */
    private function init_compatibility_rules() {
        
        // Elementor
        $this->compat_configs['elementor'] = [
            'exclude_handles' => [
                'elementor-frontend',
                'elementor-frontend-modules',
                'elementor-waypoints',
                'elementor-share-buttons',
                'elementor-dialog',
                'swiper',
                'elementor-common'
            ],
            'no_defer_handles' => [
                'elementor-frontend',
                'elementor-frontend-modules',
                'elementor-waypoints'
            ],
            'critical_css_selectors' => [
                '.elementor-element',
                '.elementor-section',
                '.elementor-container',
                '.elementor-column',
                '.elementor-widget'
            ],
            'safe_mode_required' => false
        ];
        
        // WooCommerce
        $this->compat_configs['woocommerce'] = [
            'exclude_handles' => [
                'wc-checkout',
                'wc-cart',
                'wc-cart-fragments',
                'woocommerce'
            ],
            'no_defer_handles' => [
                'wc-checkout',
                'wc-cart-fragments',
                'wc-add-to-cart',
                'wc-single-product'
            ],
            'exclude_pages' => [
                'shop',
                'cart',
                'checkout',
                'my-account'
            ],
            'safe_mode_required' => true
        ];
        
        // WPML/Polylang
        $this->compat_configs['multilang'] = [
            'cache_variations' => [
                'language' => true
            ],
            'exclude_query_params' => [
                'lang',
                'language'
            ]
        ];
        
        // Cache plugins (conflicto potencial)
        $this->compat_configs['cache_plugins'] = [
            'wp_rocket' => [
                'warning' => true,
                'message' => 'WP Rocket detected. Consider disabling page caching in one plugin to avoid conflicts.'
            ],
            'w3tc' => [
                'warning' => true,
                'message' => 'W3 Total Cache detected. Consider disabling page caching in one plugin.'
            ],
            'litespeed' => [
                'warning' => true,
                'message' => 'LiteSpeed Cache detected. Server-level cache may override plugin cache.'
            ]
        ];
    }
    
    /**
     * Aplicar fixes de compatibilidad
     */
    private function apply_compatibility_fixes() {
        
        // Elementor compatibility
        if ($this->is_plugin_active('elementor')) {
            add_action('elementor/frontend/before_enqueue_scripts', [$this, 'elementor_before_scripts'], 1);
            add_action('elementor/frontend/after_enqueue_scripts', [$this, 'elementor_after_scripts'], 999);
            add_filter('suple_speed_should_optimize_page', [$this, 'elementor_should_optimize'], 10, 2);
            add_filter('suple_speed_exclude_handles', [$this, 'elementor_exclude_handles']);
            add_filter('suple_speed_no_defer_handles', [$this, 'elementor_no_defer_handles']);
        }
        
        // WooCommerce compatibility
        if ($this->is_plugin_active('woocommerce')) {
            add_filter('suple_speed_should_cache_page', [$this, 'woocommerce_should_cache'], 10, 2);
            add_filter('suple_speed_exclude_handles', [$this, 'woocommerce_exclude_handles']);
            add_filter('suple_speed_no_defer_handles', [$this, 'woocommerce_no_defer_handles']);
            add_action('woocommerce_cart_updated', [$this, 'woocommerce_purge_cart_cache']);
        }
        
        // Multilingual compatibility
        if ($this->is_plugin_active('wpml') || $this->is_plugin_active('polylang')) {
            add_filter('suple_speed_cache_key_factors', [$this, 'multilang_cache_factors']);
            add_filter('suple_speed_cache_exclude_params', [$this, 'multilang_exclude_params']);
        }
        
        // Image optimization integration
        if ($this->is_plugin_active('ewww')) {
            add_filter('suple_speed_webp_enabled', [$this, 'ewww_webp_integration']);
        }
        
        if ($this->is_plugin_active('webp_express')) {
            add_filter('suple_speed_webp_enabled', [$this, 'webp_express_integration']);
        }
        
        // Cache plugin warnings
        $this->check_cache_plugin_conflicts();
    }
    
    /**
     * Verificar si un plugin está activo
     */
    public function is_plugin_active($plugin_key) {
        return isset($this->detected_plugins[$plugin_key]) && $this->detected_plugins[$plugin_key]['active'];
    }
    
    /**
     * Obtener información de plugin detectado
     */
    public function get_plugin_info($plugin_key) {
        return $this->detected_plugins[$plugin_key] ?? null;
    }
    
    /**
     * Obtener todos los plugins detectados
     */
    public function get_detected_plugins() {
        return $this->detected_plugins;
    }
    
    // === ELEMENTOR COMPATIBILITY ===
    
    /**
     * Elementor: Antes de cargar scripts
     */
    public function elementor_before_scripts() {
        // Marcar que estamos en contexto Elementor
        define('SUPLE_SPEED_ELEMENTOR_CONTEXT', true);
    }
    
    /**
     * Elementor: Después de cargar scripts
     */
    public function elementor_after_scripts() {
        // Cualquier limpieza necesaria
    }
    
    /**
     * Elementor: Determinar si optimizar página
     */
    public function elementor_should_optimize($should_optimize, $context) {
        // No optimizar en modo preview/editor
        if (isset($_GET['elementor-preview'])) {
            return false;
        }
        
        if (function_exists('elementor_is_preview_mode') && elementor_is_preview_mode()) {
            return false;
        }
        
        if (isset($_GET['elementor_library'])) {
            return false;
        }
        
        return $should_optimize;
    }
    
    /**
     * Elementor: Handles a excluir
     */
    public function elementor_exclude_handles($handles) {
        return array_merge($handles, $this->compat_configs['elementor']['exclude_handles']);
    }
    
    /**
     * Elementor: Handles sin defer
     */
    public function elementor_no_defer_handles($handles) {
        return array_merge($handles, $this->compat_configs['elementor']['no_defer_handles']);
    }
    
    // === WOOCOMMERCE COMPATIBILITY ===
    
    /**
     * WooCommerce: Determinar si cachear página
     */
    public function woocommerce_should_cache($should_cache, $context) {
        // No cachear páginas dinámicas de WooCommerce
        if (is_cart() || is_checkout() || is_account_page()) {
            return false;
        }
        
        // No cachear si hay productos en el carrito (para mostrar contador)
        if (WC()->cart && !WC()->cart->is_empty()) {
            return false;
        }
        
        return $should_cache;
    }
    
    /**
     * WooCommerce: Handles a excluir
     */
    public function woocommerce_exclude_handles($handles) {
        return array_merge($handles, $this->compat_configs['woocommerce']['exclude_handles']);
    }
    
    /**
     * WooCommerce: Handles sin defer
     */
    public function woocommerce_no_defer_handles($handles) {
        return array_merge($handles, $this->compat_configs['woocommerce']['no_defer_handles']);
    }
    
    /**
     * WooCommerce: Purgar caché cuando cambia carrito
     */
    public function woocommerce_purge_cart_cache() {
        // Purgar páginas relacionadas con carrito
        if (function_exists('suple_speed')) {
            suple_speed()->cache->purge_urls([
                wc_get_cart_url(),
                wc_get_checkout_url()
            ]);
        }
    }
    
    // === MULTILINGUAL COMPATIBILITY ===
    
    /**
     * Multilang: Factores para clave de caché
     */
    public function multilang_cache_factors($factors) {
        // WPML
        if (function_exists('icl_get_current_language')) {
            $factors['language'] = icl_get_current_language();
        }
        
        // Polylang
        if (function_exists('pll_current_language')) {
            $factors['language'] = pll_current_language();
        }
        
        return $factors;
    }
    
    /**
     * Multilang: Parámetros a excluir de caché
     */
    public function multilang_exclude_params($params) {
        return array_merge($params, ['lang', 'language']);
    }
    
    // === IMAGE OPTIMIZATION INTEGRATION ===
    
    /**
     * EWWW: Verificar si WebP está habilitado
     */
    public function ewww_webp_integration($enabled) {
        if (function_exists('ewww_image_optimizer_get_option')) {
            return ewww_image_optimizer_get_option('ewww_image_optimizer_webp');
        }
        
        return $enabled;
    }
    
    /**
     * WebP Express: Verificar integración
     */
    public function webp_express_integration($enabled) {
        // WebP Express maneja la conversión automáticamente
        return true;
    }
    
    // === CACHE PLUGIN CONFLICTS ===
    
    /**
     * Verificar conflictos con otros plugins de caché
     */
    private function check_cache_plugin_conflicts() {
        $conflicts = [];
        
        foreach (['wp_rocket', 'w3tc', 'wp_super_cache', 'litespeed'] as $plugin) {
            if ($this->is_plugin_active($plugin)) {
                $config = $this->compat_configs['cache_plugins'][$plugin] ?? null;
                if ($config && $config['warning']) {
                    $conflicts[] = [
                        'plugin' => $plugin,
                        'name' => $this->detected_plugins[$plugin]['name'],
                        'message' => $config['message']
                    ];
                }
            }
        }
        
        if (!empty($conflicts)) {
            add_action('admin_notices', function() use ($conflicts) {
                foreach ($conflicts as $conflict) {
                    echo '<div class="notice notice-warning is-dismissible">';
                    echo '<p><strong>Suple Speed:</strong> ' . esc_html($conflict['message']) . '</p>';
                    echo '</div>';
                }
            });
        }
    }
    
    /**
     * Obtener configuración de compatibilidad para un plugin
     */
    public function get_plugin_config($plugin_key) {
        return $this->compat_configs[$plugin_key] ?? [];
    }
    
    /**
     * Verificar si se requiere modo seguro
     */
    public function requires_safe_mode() {
        foreach ($this->detected_plugins as $key => $plugin) {
            if ($plugin['active']) {
                $config = $this->get_plugin_config($key);
                if (isset($config['safe_mode_required']) && $config['safe_mode_required']) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Obtener handles a excluir por compatibilidad
     */
    public function get_excluded_handles() {
        $excluded = [];
        
        foreach ($this->detected_plugins as $key => $plugin) {
            if ($plugin['active']) {
                $config = $this->get_plugin_config($key);
                if (isset($config['exclude_handles'])) {
                    $excluded = array_merge($excluded, $config['exclude_handles']);
                }
            }
        }
        
        return array_unique($excluded);
    }
    
    /**
     * Obtener handles sin defer por compatibilidad
     */
    public function get_no_defer_handles() {
        $no_defer = [];
        
        foreach ($this->detected_plugins as $key => $plugin) {
            if ($plugin['active']) {
                $config = $this->get_plugin_config($key);
                if (isset($config['no_defer_handles'])) {
                    $no_defer = array_merge($no_defer, $config['no_defer_handles']);
                }
            }
        }
        
        return array_unique($no_defer);
    }
    
    /**
     * Generar reporte de compatibilidad
     */
    public function get_compatibility_report() {
        $report = [
            'detected_plugins' => $this->detected_plugins,
            'potential_conflicts' => [],
            'recommendations' => [],
            'safe_mode_required' => $this->requires_safe_mode()
        ];
        
        // Detectar conflictos potenciales
        foreach (['wp_rocket', 'w3tc', 'wp_super_cache', 'litespeed'] as $cache_plugin) {
            if ($this->is_plugin_active($cache_plugin)) {
                $report['potential_conflicts'][] = [
                    'type' => 'cache_conflict',
                    'plugin' => $cache_plugin,
                    'severity' => 'medium',
                    'message' => "Cache plugin conflict with {$this->detected_plugins[$cache_plugin]['name']}"
                ];
            }
        }
        
        // Recomendaciones específicas
        if ($this->is_plugin_active('elementor')) {
            $report['recommendations'][] = 'Enable Elementor compatibility mode for best results';
        }
        
        if ($this->is_plugin_active('woocommerce')) {
            $report['recommendations'][] = 'Configure cache exclusions for WooCommerce dynamic pages';
        }
        
        return $report;
    }
}