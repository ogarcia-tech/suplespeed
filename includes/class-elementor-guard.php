<?php

namespace SupleSpeed;

/**
 * Clase para proteger la compatibilidad con Elementor
 */
class ElementorGuard {
    
    /**
     * Estados de Elementor detectados
     */
    private $is_editor_mode = null;
    private $is_preview_mode = null;
    private $is_library_mode = null;
    private $elementor_data_cache = [];
    
    public function __construct() {
        $this->init_elementor_hooks();
    }
    
    /**
     * Inicializar hooks específicos de Elementor
     */
    private function init_elementor_hooks() {
        // Solo si Elementor está activo
        if (!$this->is_elementor_active()) {
            return;
        }
        
        // Detectar modos de Elementor temprano
        add_action('init', [$this, 'detect_elementor_modes'], 1);
        
        // Hooks para proteger el editor
        add_action('elementor/editor/before_enqueue_scripts', [$this, 'disable_optimizations_in_editor']);
        add_action('elementor/preview/enqueue_styles', [$this, 'disable_optimizations_in_preview']);
        
        // Proteger CSS/JS específicos de Elementor
        add_filter('suple_speed_can_merge_handle', [$this, 'protect_elementor_handles'], 10, 2);
        add_filter('suple_speed_can_defer_script', [$this, 'protect_elementor_scripts'], 10, 2);
        add_filter('suple_speed_can_minify_css', [$this, 'protect_elementor_css'], 10, 2);
        
        // Detectar y proteger motion effects
        add_filter('suple_speed_critical_css_exclude', [$this, 'exclude_motion_effects_css']);
        
        // Proteger inline styles de Elementor
        add_filter('suple_speed_process_html', [$this, 'protect_elementor_inline_styles']);
        
        // Purgar caché cuando se guarda contenido de Elementor
        add_action('elementor/core/files/clear_cache', [$this, 'purge_cache_on_elementor_update']);
    }
    
    /**
     * Verificar si Elementor está activo
     */
    public function is_elementor_active() {
        return defined('ELEMENTOR_VERSION') || class_exists('\Elementor\Plugin');
    }
    
    /**
     * Detectar modos de Elementor
     */
    public function detect_elementor_modes() {
        if (!$this->is_elementor_active()) {
            return;
        }
        
        // Modo editor
        $this->is_editor_mode = $this->detect_editor_mode();
        
        // Modo preview
        $this->is_preview_mode = $this->detect_preview_mode();
        
        // Modo library
        $this->is_library_mode = $this->detect_library_mode();
    }
    
    /**
     * Detectar modo editor de Elementor
     */
    private function detect_editor_mode() {
        // Múltiples formas de detectar el editor
        $checks = [
            // URL parameters
            isset($_GET['elementor-preview']),
            isset($_GET['elementor_library']),
            isset($_POST['action']) && $_POST['action'] === 'elementor_ajax',
            
            // Elementor functions
            function_exists('\Elementor\Plugin::instance') && 
                method_exists(\Elementor\Plugin::instance(), 'editor') &&
                \Elementor\Plugin::instance()->editor->is_edit_mode(),
            
            // Legacy check
            function_exists('elementor_is_preview_mode') && elementor_is_preview_mode(),
            
            // Admin area específico de Elementor
            is_admin() && isset($_GET['action']) && $_GET['action'] === 'elementor'
        ];
        
        return in_array(true, $checks, true);
    }
    
    /**
     * Detectar modo preview de Elementor
     */
    private function detect_preview_mode() {
        $checks = [
            isset($_GET['elementor-preview']),
            isset($_GET['preview']) && isset($_GET['preview_id']),
            function_exists('\Elementor\Plugin::instance') && 
                method_exists(\Elementor\Plugin::instance(), 'preview') &&
                \Elementor\Plugin::instance()->preview->is_preview_mode()
        ];
        
        return in_array(true, $checks, true);
    }
    
    /**
     * Detectar modo library de Elementor
     */
    private function detect_library_mode() {
        return isset($_GET['elementor_library']) || 
               (isset($_GET['post_type']) && $_GET['post_type'] === 'elementor_library');
    }
    
    /**
     * Verificar si estamos en modo editor
     */
    public function is_editor_mode() {
        if ($this->is_editor_mode === null) {
            $this->detect_elementor_modes();
        }
        
        return $this->is_editor_mode;
    }
    
    /**
     * Verificar si estamos en modo preview
     */
    public function is_preview_mode() {
        if ($this->is_preview_mode === null) {
            $this->detect_elementor_modes();
        }
        
        return $this->is_preview_mode;
    }
    
    /**
     * Verificar si estamos en modo library
     */
    public function is_library_mode() {
        if ($this->is_library_mode === null) {
            $this->detect_elementor_modes();
        }
        
        return $this->is_library_mode;
    }
    
    /**
     * Verificar si debemos deshabilitar optimizaciones
     */
    public function should_disable_optimizations() {
        return $this->is_editor_mode() || $this->is_preview_mode() || $this->is_library_mode();
    }
    
    /**
     * Deshabilitar optimizaciones en el editor
     */
    public function disable_optimizations_in_editor() {
        // Añadir flag global para que otros componentes sepan
        define('SUPLE_SPEED_ELEMENTOR_EDITOR_ACTIVE', true);
        
        // Remover hooks de optimización
        $this->remove_optimization_hooks();
    }
    
    /**
     * Deshabilitar optimizaciones en el preview
     */
    public function disable_optimizations_in_preview() {
        define('SUPLE_SPEED_ELEMENTOR_PREVIEW_ACTIVE', true);
        $this->remove_optimization_hooks();
    }
    
    /**
     * Remover hooks de optimización
     */
    private function remove_optimization_hooks() {
        if (function_exists('suple_speed')) {
            $instance = suple_speed();
            
            // Remover optimización de assets
            remove_action('wp_enqueue_scripts', [$instance->assets, 'optimize_scripts_styles'], 999);
            
            // Remover inyección de Critical CSS
            remove_action('wp_head', [$instance->assets, 'inject_critical_css'], 1);
            
            // Remover preloads
            remove_action('wp_head', [$instance->assets, 'inject_preloads'], 2);
        }
    }
    
    /**
     * Proteger handles específicos de Elementor del merge
     */
    public function protect_elementor_handles($can_merge, $handle) {
        $protected_handles = [
            'elementor-frontend',
            'elementor-frontend-modules', 
            'elementor-waypoints',
            'elementor-share-buttons',
            'elementor-dialog',
            'elementor-common',
            'elementor-app-loader',
            'elementor-editor',
            'elementor-editor-modules',
            'elementor-editor-document',
            'swiper',
            'e-animations',
            'e-sticky'
        ];
        
        // Proteger handles que contienen 'elementor'
        if (strpos($handle, 'elementor') !== false) {
            return false;
        }
        
        // Proteger handles específicos
        if (in_array($handle, $protected_handles)) {
            return false;
        }
        
        return $can_merge;
    }
    
    /**
     * Proteger scripts de Elementor del defer
     */
    public function protect_elementor_scripts($can_defer, $handle) {
        $no_defer_handles = [
            'elementor-frontend',
            'elementor-frontend-modules',
            'elementor-waypoints',
            'swiper',
            'e-animations'
        ];
        
        // No defer scripts que contienen 'elementor'
        if (strpos($handle, 'elementor') !== false) {
            return false;
        }
        
        if (in_array($handle, $no_defer_handles)) {
            return false;
        }
        
        return $can_defer;
    }
    
    /**
     * Proteger CSS de Elementor de la minificación agresiva
     */
    public function protect_elementor_css($can_minify, $handle) {
        // Permitir minificación suave, pero proteger de cambios estructurales
        if (strpos($handle, 'elementor') !== false) {
            // Aplicar minificación suave pero segura
            return 'safe';
        }
        
        return $can_minify;
    }
    
    /**
     * Excluir CSS de motion effects del Critical CSS
     */
    public function exclude_motion_effects_css($exclude_selectors) {
        $elementor_motion_selectors = [
            '.elementor-motion-effects-element',
            '.elementor-motion-effects-parent',
            '[data-settings*="motion_fx"]',
            '.e-transform',
            '.e-transform-flip',
            '.e-transform-zoom',
            '.e-transform-rotate'
        ];
        
        return array_merge($exclude_selectors, $elementor_motion_selectors);
    }
    
    /**
     * Proteger estilos inline de Elementor
     */
    public function protect_elementor_inline_styles($html) {
        // No procesar HTML si estamos en modo editor/preview
        if ($this->should_disable_optimizations()) {
            return $html;
        }
        
        // Proteger estilos inline específicos de Elementor
        $protected_patterns = [
            // CSS Variables de Elementor
            '/(<style[^>]*>.*?--e-[^}]*}.*?<\/style>)/s',
            
            // Estilos de motion effects
            '/(<style[^>]*>.*?motion_fx.*?<\/style>)/s',
            
            // Estilos de responsive
            '/(<style[^>]*>.*?@media.*?elementor.*?<\/style>)/s'
        ];
        
        foreach ($protected_patterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                foreach ($matches[1] as $match) {
                    // Marcar como protegido
                    $protected = '<!-- SUPLE_SPEED_PROTECTED -->' . $match . '<!-- /SUPLE_SPEED_PROTECTED -->';
                    $html = str_replace($match, $protected, $html);
                }
            }
        }
        
        return $html;
    }
    
    /**
     * Purgar caché cuando Elementor actualiza archivos
     */
    public function purge_cache_on_elementor_update() {
        if (function_exists('suple_speed')) {
            suple_speed()->cache->purge_all();
            
            // Log del evento
            if (suple_speed()->logger) {
                suple_speed()->logger->info(
                    'Cache purged due to Elementor files update',
                    [],
                    'elementor'
                );
            }
        }
    }
    
    /**
     * Obtener datos específicos de Elementor de una página
     */
    public function get_elementor_page_data($post_id) {
        if (isset($this->elementor_data_cache[$post_id])) {
            return $this->elementor_data_cache[$post_id];
        }
        
        $data = [
            'is_elementor_page' => false,
            'has_motion_effects' => false,
            'has_custom_css' => false,
            'widgets_used' => [],
            'css_files' => [],
            'js_files' => []
        ];
        
        if (!$this->is_elementor_active()) {
            $this->elementor_data_cache[$post_id] = $data;
            return $data;
        }
        
        // Verificar si es página de Elementor
        $elementor_data = get_post_meta($post_id, '_elementor_data', true);
        if (!empty($elementor_data)) {
            $data['is_elementor_page'] = true;
            
            // Analizar datos de Elementor
            $elementor_data = json_decode($elementor_data, true);
            if (is_array($elementor_data)) {
                $this->analyze_elementor_data($elementor_data, $data);
            }
        }
        
        // Verificar CSS personalizado de Elementor
        $custom_css = get_post_meta($post_id, '_elementor_page_settings', true);
        if (!empty($custom_css['custom_css'])) {
            $data['has_custom_css'] = true;
        }
        
        // Obtener archivos CSS/JS de Elementor para esta página
        $this->get_elementor_assets($post_id, $data);
        
        $this->elementor_data_cache[$post_id] = $data;
        return $data;
    }
    
    /**
     * Analizar datos de Elementor recursivamente
     */
    private function analyze_elementor_data($elements, &$data) {
        foreach ($elements as $element) {
            if (isset($element['elType'])) {
                // Detectar motion effects
                if (isset($element['settings']['motion_fx_motion_fx_scrolling']) ||
                    isset($element['settings']['motion_fx_motion_fx_mouse'])) {
                    $data['has_motion_effects'] = true;
                }
                
                // Recopilar widgets usados
                if ($element['elType'] === 'widget' && isset($element['widgetType'])) {
                    $data['widgets_used'][] = $element['widgetType'];
                }
                
                // Analizar elementos hijos recursivamente
                if (isset($element['elements']) && is_array($element['elements'])) {
                    $this->analyze_elementor_data($element['elements'], $data);
                }
            }
        }
        
        $data['widgets_used'] = array_unique($data['widgets_used']);
    }
    
    /**
     * Obtener assets de Elementor para una página específica
     */
    private function get_elementor_assets($post_id, &$data) {
        // CSS específico de la página
        $css_file = wp_upload_dir()['basedir'] . "/elementor/css/post-{$post_id}.css";
        if (file_exists($css_file)) {
            $data['css_files'][] = wp_upload_dir()['baseurl'] . "/elementor/css/post-{$post_id}.css";
        }
        
        // Global CSS de Elementor
        $global_css = wp_upload_dir()['basedir'] . '/elementor/css/global.css';
        if (file_exists($global_css)) {
            $data['css_files'][] = wp_upload_dir()['baseurl'] . '/elementor/css/global.css';
        }
    }
    
    /**
     * Verificar si una página requiere tratamiento especial
     */
    public function requires_special_treatment($post_id) {
        $data = $this->get_elementor_page_data($post_id);
        
        return $data['is_elementor_page'] && (
            $data['has_motion_effects'] || 
            $data['has_custom_css'] ||
            count($data['widgets_used']) > 10  // Página compleja
        );
    }
    
    /**
     * Generar Critical CSS específico para página de Elementor
     */
    public function generate_elementor_critical_css($post_id) {
        $data = $this->get_elementor_page_data($post_id);
        
        if (!$data['is_elementor_page']) {
            return '';
        }
        
        $critical_css = '';
        
        // CSS base de Elementor siempre crítico
        $base_selectors = [
            '.elementor-section',
            '.elementor-container',
            '.elementor-row',
            '.elementor-column',
            '.elementor-widget'
        ];
        
        // CSS específico según widgets usados
        $widget_critical_css = $this->get_widget_critical_css($data['widgets_used']);
        
        return $critical_css . $widget_critical_css;
    }
    
    /**
     * Obtener CSS crítico por widgets
     */
    private function get_widget_critical_css($widgets) {
        $critical_css = '';
        
        $widget_css_map = [
            'heading' => '.elementor-heading-title { font-size: inherit; line-height: inherit; }',
            'text-editor' => '.elementor-text-editor { font-size: inherit; }',
            'image' => '.elementor-image img { max-width: 100%; height: auto; }',
            'button' => '.elementor-button { display: inline-block; }',
            'spacer' => '.elementor-spacer { clear: both; }'
        ];
        
        foreach ($widgets as $widget) {
            if (isset($widget_css_map[$widget])) {
                $critical_css .= $widget_css_map[$widget];
            }
        }
        
        return $critical_css;
    }
    
    /**
     * Obtener configuración de caché específica para Elementor
     */
    public function get_cache_config($post_id) {
        $data = $this->get_elementor_page_data($post_id);
        
        $config = [
            'cache_enabled' => true,
            'cache_ttl' => 24 * HOUR_IN_SECONDS,
            'vary_by_device' => true,
            'exclude_query_params' => []
        ];
        
        // Si tiene motion effects, reducir TTL
        if ($data['has_motion_effects']) {
            $config['cache_ttl'] = 12 * HOUR_IN_SECONDS;
        }
        
        // Si es página compleja, variar por más factores
        if ($this->requires_special_treatment($post_id)) {
            $config['vary_by_device'] = true;
            $config['exclude_query_params'] = ['elementor-preview'];
        }
        
        return $config;
    }
}