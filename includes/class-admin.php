<?php

namespace SupleSpeed;

/**
 * Interfaz de administración
 */
class Admin {
    
    /**
     * Configuración
     */
    private $settings;
    private $logger;
    
    /**
     * Páginas de admin
     */
    private $admin_pages = [];
    
    public function __construct() {
        $this->settings = get_option('suple_speed_settings', []);
        
        if (function_exists('suple_speed')) {
            $this->logger = suple_speed()->logger;
        }
        
        $this->init_hooks();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('admin_notices', [$this, 'show_admin_notices']);
        
        // AJAX handlers
        add_action('wp_ajax_suple_speed_save_settings', [$this, 'ajax_save_settings']);
        add_action('wp_ajax_suple_speed_reset_settings', [$this, 'ajax_reset_settings']);
        add_action('wp_ajax_suple_speed_export_settings', [$this, 'ajax_export_settings']);
        add_action('wp_ajax_suple_speed_import_settings', [$this, 'ajax_import_settings']);
        
        // Plugin action links
        add_filter('plugin_action_links_' . SUPLE_SPEED_PLUGIN_BASENAME, [$this, 'add_action_links']);
        
        // Admin bar
        add_action('admin_bar_menu', [$this, 'add_admin_bar_menu'], 100);
    }
    
    /**
     * Añadir menú de administración
     */
    public function add_admin_menu() {
        // Página principal
        $this->admin_pages['main'] = add_menu_page(
            'Suple Speed',
            'Suple Speed',
            'manage_options',
            'suple-speed',
            [$this, 'render_dashboard'],
            $this->get_menu_icon(),
            80
        );
        
        // Dashboard
        add_submenu_page(
            'suple-speed',
            __('Dashboard', 'suple-speed'),
            __('Dashboard', 'suple-speed'),
            'manage_options',
            'suple-speed',
            [$this, 'render_dashboard']
        );
        
        // Rendimiento (PageSpeed Insights)
        $this->admin_pages['performance'] = add_submenu_page(
            'suple-speed',
            __('Performance', 'suple-speed'),
            __('Performance', 'suple-speed'),
            'manage_options',
            'suple-speed-performance',
            [$this, 'render_performance']
        );
        
        // Caché
        $this->admin_pages['cache'] = add_submenu_page(
            'suple-speed',
            __('Cache', 'suple-speed'),
            __('Cache', 'suple-speed'),
            'manage_options',
            'suple-speed-cache',
            [$this, 'render_cache']
        );
        
        // Assets (CSS/JS)
        $this->admin_pages['assets'] = add_submenu_page(
            'suple-speed',
            __('Assets', 'suple-speed'),
            __('Assets', 'suple-speed'),
            'manage_options',
            'suple-speed-assets',
            [$this, 'render_assets']
        );
        
        // Critical CSS & Preloads
        $this->admin_pages['critical'] = add_submenu_page(
            'suple-speed',
            __('Critical & Preloads', 'suple-speed'),
            __('Critical & Preloads', 'suple-speed'),
            'manage_options',
            'suple-speed-critical',
            [$this, 'render_critical']
        );
        
        // Fuentes
        $this->admin_pages['fonts'] = add_submenu_page(
            'suple-speed',
            __('Fonts', 'suple-speed'),
            __('Fonts', 'suple-speed'),
            'manage_options',
            'suple-speed-fonts',
            [$this, 'render_fonts']
        );
        
        // Imágenes
        $this->admin_pages['images'] = add_submenu_page(
            'suple-speed',
            __('Images', 'suple-speed'),
            __('Images', 'suple-speed'),
            'manage_options',
            'suple-speed-images',
            [$this, 'render_images']
        );
        
        // Reglas
        $this->admin_pages['rules'] = add_submenu_page(
            'suple-speed',
            __('Rules', 'suple-speed'),
            __('Rules', 'suple-speed'),
            'manage_options',
            'suple-speed-rules',
            [$this, 'render_rules']
        );
        
        // Compatibilidad
        $this->admin_pages['compatibility'] = add_submenu_page(
            'suple-speed',
            __('Compatibility', 'suple-speed'),
            __('Compatibility', 'suple-speed'),
            'manage_options',
            'suple-speed-compatibility',
            [$this, 'render_compatibility']
        );
        
        // Herramientas
        $this->admin_pages['tools'] = add_submenu_page(
            'suple-speed',
            __('Tools', 'suple-speed'),
            __('Tools', 'suple-speed'),
            'manage_options',
            'suple-speed-tools',
            [$this, 'render_tools']
        );
        
        // Logs
        $this->admin_pages['logs'] = add_submenu_page(
            'suple-speed',
            __('Logs', 'suple-speed'),
            __('Logs', 'suple-speed'),
            'manage_options',
            'suple-speed-logs',
            [$this, 'render_logs']
        );
        
        // Configuración
        $this->admin_pages['settings'] = add_submenu_page(
            'suple-speed',
            __('Settings', 'suple-speed'),
            __('Settings', 'suple-speed'),
            'manage_options',
            'suple-speed-settings',
            [$this, 'render_settings']
        );
    }
    
    /**
     * Obtener ícono del menú
     */
    private function get_menu_icon() {
        return 'data:image/svg+xml;base64,' . base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path d="M13 10h5l-6 6-6-6h5V3h2v7zm-9 9h16v-7h2v8a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-8h2v7z"/>
            </svg>'
        );
    }
    
    /**
     * Registrar configuraciones
     */
    public function register_settings() {
        register_setting('suple_speed_settings', 'suple_speed_settings', [
            'sanitize_callback' => [$this, 'sanitize_settings']
        ]);
    }
    
    /**
     * Sanitizar configuraciones
     */
    public function sanitize_settings($input) {
        $sanitized = [];
        
        // Configuraciones booleanas
        $boolean_settings = [
            'cache_enabled', 'compression_enabled', 'assets_enabled',
            'merge_css', 'merge_js', 'minify_css', 'minify_js', 'defer_js',
            'critical_css_enabled', 'fonts_local', 'fonts_display_swap', 'images_lazy', 'images_lqip',
            'images_webp_rewrite', 'elementor_compat', 'safe_mode',
            'multisite_network', 'assets_test_mode', 'psi_auto_test'
        ];
        
        foreach ($boolean_settings as $setting) {
            $sanitized[$setting] = !empty($input[$setting]);
        }
        
        // Configuraciones numéricas
        $numeric_settings = [
            'cache_ttl' => HOUR_IN_SECONDS,
            'assets_version_hashing' => 0,
            'images_lqip_quality' => 20
        ];
        
        foreach ($numeric_settings as $setting => $default) {
            $sanitized[$setting] = isset($input[$setting]) ? 
                                   intval($input[$setting]) : 
                                   $default;
        }
        
        // Configuraciones de texto
        $text_settings = [
            'psi_api_key', 'log_level', 'critical_css_general'
        ];
        
        foreach ($text_settings as $setting) {
            $sanitized[$setting] = isset($input[$setting]) ? 
                                   sanitize_text_field($input[$setting]) : 
                                   '';
        }
        
        // Arrays
        $array_settings = [
            'cache_exclude_params', 'assets_exclude_handles', 
            'assets_no_defer_handles', 'assets_merge_css_groups',
            'assets_merge_js_groups', 'cache_vary_cookies',
            'assets_test_roles', 'assets_test_ips', 'preload_assets',
            'fonts_preload', 'images_preload'
        ];
        
        foreach ($array_settings as $setting) {
            $sanitized[$setting] = isset($input[$setting]) && is_array($input[$setting]) ?
                                   array_map('sanitize_text_field', $input[$setting]) :
                                   [];
        }
        
        return $sanitized;
    }
    
    /**
     * Cargar scripts de administración
     */
    public function enqueue_admin_scripts($hook) {
        // Solo cargar en páginas de Suple Speed
        if (!in_array($hook, $this->admin_pages)) {
            return;
        }
        
        $version = SUPLE_SPEED_VERSION;
        
        // CSS
        wp_enqueue_style(
            'suple-speed-admin',
            SUPLE_SPEED_PLUGIN_URL . 'public/css/admin.css',
            [],
            $version
        );
        
        // JavaScript
        wp_enqueue_script(
            'suple-speed-admin',
            SUPLE_SPEED_PLUGIN_URL . 'public/js/admin.js',
            ['jquery', 'wp-util'],
            $version,
            true
        );
        
        // Datos para JavaScript
        wp_localize_script('suple-speed-admin', 'supleSpeedAdmin', [
            'nonce' => wp_create_nonce('suple_speed_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'strings' => [
                'confirmReset' => __('Are you sure you want to reset all settings?', 'suple-speed'),
                'confirmPurge' => __('Are you sure you want to purge all cache?', 'suple-speed'),
                'processing' => __('Processing...', 'suple-speed'),
                'success' => __('Operation completed successfully', 'suple-speed'),
                'error' => __('An error occurred', 'suple-speed')
            ]
        ]);
    }
    
    /**
     * Mostrar avisos de administración
     */
    public function show_admin_notices() {
        // Aviso si no hay API key de PSI
        if (empty($this->settings['psi_api_key']) && 
            isset($_GET['page']) && 
            strpos($_GET['page'], 'suple-speed') === 0) {
            
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p>';
            echo sprintf(
                __('Configure your PageSpeed Insights API key in %s to enable performance testing.', 'suple-speed'),
                '<a href="' . admin_url('admin.php?page=suple-speed-settings') . '">' . __('Settings', 'suple-speed') . '</a>'
            );
            echo '</p>';
            echo '</div>';
        }
        
        // Aviso de modo seguro
        if (!empty($this->settings['safe_mode'])) {
            echo '<div class="notice notice-info">';
            echo '<p>';
            echo __('Safe Mode is enabled. Some optimizations are disabled for compatibility.', 'suple-speed');
            echo '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Añadir enlaces de acción al plugin
     */
    public function add_action_links($links) {
        $action_links = [
            '<a href="' . admin_url('admin.php?page=suple-speed') . '">' . __('Dashboard', 'suple-speed') . '</a>',
            '<a href="' . admin_url('admin.php?page=suple-speed-settings') . '">' . __('Settings', 'suple-speed') . '</a>'
        ];
        
        return array_merge($action_links, $links);
    }
    
    /**
     * Añadir menú en admin bar
     */
    public function add_admin_bar_menu($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Nodo principal
        $wp_admin_bar->add_node([
            'id' => 'suple-speed',
            'title' => 'Suple Speed',
            'href' => admin_url('admin.php?page=suple-speed')
        ]);
        
        // Purgar caché
        $wp_admin_bar->add_node([
            'id' => 'suple-speed-purge-cache',
            'parent' => 'suple-speed',
            'title' => __('Purge Cache', 'suple-speed'),
            'href' => wp_nonce_url(
                admin_url('admin.php?page=suple-speed&action=purge_cache'),
                'suple_speed_purge_cache'
            )
        ]);
        
        // Test PageSpeed
        if (!empty($this->settings['psi_api_key'])) {
            $wp_admin_bar->add_node([
                'id' => 'suple-speed-test-page',
                'parent' => 'suple-speed',
                'title' => __('Test This Page', 'suple-speed'),
                'href' => admin_url('admin.php?page=suple-speed-performance&test_url=' . urlencode(get_permalink()))
            ]);
        }
        
        // Información rápida
        if (function_exists('suple_speed')) {
            $cache_stats = suple_speed()->cache->get_cache_stats();
            
            $wp_admin_bar->add_node([
                'id' => 'suple-speed-info',
                'parent' => 'suple-speed',
                'title' => sprintf(__('Cache: %d files (%s)', 'suple-speed'), 
                    $cache_stats['total_files'],
                    $cache_stats['total_size_formatted']
                ),
                'href' => admin_url('admin.php?page=suple-speed-cache')
            ]);
        }
    }
    
    // === RENDERIZADO DE PÁGINAS ===
    
    /**
     * Renderizar dashboard
     */
    public function render_dashboard() {
        include SUPLE_SPEED_PLUGIN_DIR . 'views/admin-dashboard.php';
    }
    
    /**
     * Renderizar página de rendimiento
     */
    public function render_performance() {
        include SUPLE_SPEED_PLUGIN_DIR . 'views/admin-performance.php';
    }
    
    /**
     * Renderizar página de caché
     */
    public function render_cache() {
        include SUPLE_SPEED_PLUGIN_DIR . 'views/admin-cache.php';
    }
    
    /**
     * Renderizar página de assets
     */
    public function render_assets() {
        include SUPLE_SPEED_PLUGIN_DIR . 'views/admin-assets.php';
    }
    
    /**
     * Renderizar página de critical CSS
     */
    public function render_critical() {
        include SUPLE_SPEED_PLUGIN_DIR . 'views/admin-critical.php';
    }
    
    /**
     * Renderizar página de fuentes
     */
    public function render_fonts() {
        include SUPLE_SPEED_PLUGIN_DIR . 'views/admin-fonts.php';
    }
    
    /**
     * Renderizar página de imágenes
     */
    public function render_images() {
        include SUPLE_SPEED_PLUGIN_DIR . 'views/admin-images.php';
    }
    
    /**
     * Renderizar página de reglas
     */
    public function render_rules() {
        include SUPLE_SPEED_PLUGIN_DIR . 'views/admin-rules.php';
    }
    
    /**
     * Renderizar página de compatibilidad
     */
    public function render_compatibility() {
        include SUPLE_SPEED_PLUGIN_DIR . 'views/admin-compatibility.php';
    }
    
    /**
     * Renderizar página de herramientas
     */
    public function render_tools() {
        include SUPLE_SPEED_PLUGIN_DIR . 'views/admin-tools.php';
    }
    
    /**
     * Renderizar página de logs
     */
    public function render_logs() {
        include SUPLE_SPEED_PLUGIN_DIR . 'views/admin-logs.php';
    }
    
    /**
     * Renderizar página de configuración
     */
    public function render_settings() {
        include SUPLE_SPEED_PLUGIN_DIR . 'views/admin-settings.php';
    }
    
    // === AJAX HANDLERS ===
    
    /**
     * AJAX: Guardar configuraciones
     */
    public function ajax_save_settings() {
        check_ajax_referer('suple_speed_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $settings = $_POST['settings'] ?? [];
        
        // Sanitizar y guardar
        $sanitized_settings = $this->sanitize_settings($settings);
        update_option('suple_speed_settings', $sanitized_settings);
        
        // Log del cambio
        if ($this->logger) {
            $this->logger->info('Settings updated via AJAX', [
                'changed_settings' => array_keys($settings)
            ], 'admin');
        }
        
        wp_send_json_success([
            'message' => __('Settings saved successfully', 'suple-speed')
        ]);
    }
    
    /**
     * AJAX: Resetear configuraciones
     */
    public function ajax_reset_settings() {
        check_ajax_referer('suple_speed_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Resetear a valores por defecto
        delete_option('suple_speed_settings');
        
        // Recrear configuración por defecto
        if (function_exists('suple_speed')) {
            suple_speed()->set_default_options();
        }
        
        wp_send_json_success([
            'message' => __('Settings reset to defaults', 'suple-speed')
        ]);
    }
    
    /**
     * AJAX: Exportar configuraciones
     */
    public function ajax_export_settings() {
        check_ajax_referer('suple_speed_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $export_data = [
            'version' => SUPLE_SPEED_VERSION,
            'exported_at' => time(),
            'site_url' => home_url(),
            'settings' => get_option('suple_speed_settings', []),
            'rules' => get_option('suple_speed_rules', [])
        ];
        
        $filename = 'suple-speed-settings-' . date('Y-m-d-H-i-s') . '.json';
        
        wp_send_json_success([
            'filename' => $filename,
            'data' => json_encode($export_data, JSON_PRETTY_PRINT)
        ]);
    }
    
    /**
     * AJAX: Importar configuraciones
     */
    public function ajax_import_settings() {
        check_ajax_referer('suple_speed_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        if (!isset($_FILES['import_file'])) {
            wp_send_json_error(__('No file uploaded', 'suple-speed'));
        }
        
        $file = $_FILES['import_file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(__('File upload error', 'suple-speed'));
        }
        
        $content = file_get_contents($file['tmp_name']);
        $import_data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(__('Invalid JSON file', 'suple-speed'));
        }
        
        // Validar estructura
        if (!isset($import_data['settings']) || !is_array($import_data['settings'])) {
            wp_send_json_error(__('Invalid settings file format', 'suple-speed'));
        }
        
        // Importar configuraciones
        $sanitized_settings = $this->sanitize_settings($import_data['settings']);
        update_option('suple_speed_settings', $sanitized_settings);
        
        // Importar reglas si existen
        if (isset($import_data['rules']) && is_array($import_data['rules'])) {
            update_option('suple_speed_rules', $import_data['rules']);
        }
        
        wp_send_json_success([
            'message' => __('Settings imported successfully', 'suple-speed'),
            'imported_from_version' => $import_data['version'] ?? 'unknown'
        ]);
    }
    
    // === UTILIDADES ===
    
    /**
     * Obtener datos del dashboard
     */
    public function get_dashboard_data() {
        $data = [
            'plugin_version' => SUPLE_SPEED_VERSION,
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'status' => 'active'
        ];
        
        // Estadísticas de caché
        if (function_exists('suple_speed')) {
            $data['cache_stats'] = suple_speed()->cache->get_cache_stats();
            $data['psi_stats'] = suple_speed()->psi->get_psi_stats();
            $data['fonts_stats'] = suple_speed()->fonts->get_fonts_stats();
            $data['images_stats'] = suple_speed()->images->get_optimization_stats();
            $data['compat_report'] = suple_speed()->compat->get_compatibility_report();
        }
        
        return $data;
    }
    
    /**
     * Obtener configuración actual
     */
    public function get_current_settings() {
        return $this->settings;
    }
    
    /**
     * Verificar capacidades del servidor
     */
    public function get_server_capabilities() {
        $capabilities = [
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'gd_extension' => extension_loaded('gd'),
            'imagemagick_extension' => extension_loaded('imagick'),
            'curl_extension' => extension_loaded('curl'),
            'zip_extension' => extension_loaded('zip'),
            'webp_support' => function_exists('imagewebp'),
            'avif_support' => function_exists('imageavif'),
            'opcache_enabled' => function_exists('opcache_get_status') && opcache_get_status() !== false,
            'mod_rewrite' => function_exists('apache_get_modules') ? in_array('mod_rewrite', apache_get_modules()) : null,
            'htaccess_writable' => is_writable(ABSPATH . '.htaccess') || is_writable(ABSPATH)
        ];
        
        return $capabilities;
    }
    
    /**
     * Generar reporte de estado
     */
    public function generate_status_report() {
        $report = [
            'timestamp' => time(),
            'plugin_info' => [
                'version' => SUPLE_SPEED_VERSION,
                'active_since' => get_option('suple_speed_activated_at', time())
            ],
            'wordpress_info' => [
                'version' => get_bloginfo('version'),
                'multisite' => is_multisite(),
                'language' => get_locale(),
                'theme' => get_template(),
                'active_plugins' => count(get_option('active_plugins', []))
            ],
            'server_info' => $this->get_server_capabilities(),
            'settings' => $this->settings,
            'stats' => $this->get_dashboard_data()
        ];
        
        return $report;
    }
    
    /**
     * Procesar acciones de admin bar
     */
    public function process_admin_bar_actions() {
        if (!isset($_GET['action']) || !current_user_can('manage_options')) {
            return;
        }
        
        $action = sanitize_text_field($_GET['action']);
        
        switch ($action) {
            case 'purge_cache':
                if (wp_verify_nonce($_GET['_wpnonce'] ?? '', 'suple_speed_purge_cache')) {
                    if (function_exists('suple_speed')) {
                        $purged = suple_speed()->cache->purge_all();
                        
                        add_action('admin_notices', function() use ($purged) {
                            echo '<div class="notice notice-success is-dismissible">';
                            echo '<p>' . sprintf(__('Cache purged successfully. %d files removed.', 'suple-speed'), $purged) . '</p>';
                            echo '</div>';
                        });
                    }
                }
                break;
        }
    }
}