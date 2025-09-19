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

    private $database;


    /**
     * Pasos de onboarding
     */
    private $onboarding_steps;

    /**
     * Páginas de admin
     */
    private $admin_pages = [];
    
    public function __construct() {
        $this->settings = get_option('suple_speed_settings', []);
        
        if (function_exists('suple_speed')) {
            $this->logger = suple_speed()->logger;

            if (isset(suple_speed()->database)) {
                $this->database = suple_speed()->database;
            }

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
        add_action('admin_post_suple_speed_generate_critical_css', [$this, 'handle_generate_critical_css']);

        // AJAX handlers
        add_action('wp_ajax_suple_speed_save_settings', [$this, 'ajax_save_settings']);
        add_action('wp_ajax_suple_speed_save_cdn_settings', [$this, 'ajax_save_cdn_settings']);
        add_action('wp_ajax_suple_speed_reset_settings', [$this, 'ajax_reset_settings']);
        add_action('wp_ajax_suple_speed_export_settings', [$this, 'ajax_export_settings']);
        add_action('wp_ajax_suple_speed_import_settings', [$this, 'ajax_import_settings']);
        add_action('wp_ajax_suple_speed_import_rules', [$this, 'ajax_import_rules']);
        add_action('wp_ajax_suple_speed_clear_lqip_cache', [$this, 'ajax_clear_lqip_cache']);
        add_action('wp_ajax_suple_speed_export_logs', [$this, 'ajax_export_logs']);
        add_action('wp_ajax_suple_speed_clear_logs', [$this, 'ajax_clear_logs']);
        add_action('wp_ajax_suple_speed_test_cache_warmup', [$this, 'ajax_test_cache_warmup']);

        add_action('wp_ajax_suple_speed_update_onboarding', [$this, 'ajax_update_onboarding']);
        add_action('wp_ajax_suple_speed_database_cleanup_revisions', [$this, 'ajax_database_cleanup_revisions']);
        add_action('wp_ajax_suple_speed_database_cleanup_transients', [$this, 'ajax_database_cleanup_transients']);
        add_action('wp_ajax_suple_speed_database_optimize_tables', [$this, 'ajax_database_optimize_tables']);

        
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
     * Obtener pasos de onboarding del dashboard
     */
    public function get_onboarding_steps() {
        if (is_array($this->onboarding_steps)) {
            return $this->onboarding_steps;
        }

        $steps = [
            'enable_cache' => [
                'title' => __('Activa la caché inteligente', 'suple-speed'),
                'description' => __('Habilita la caché a disco con purga automática e inteligente y un TTL de 24 horas como punto de partida recomendado.', 'suple-speed'),
                'links' => [
                    [
                        'label' => __('Ir a Ajustes > Caché', 'suple-speed'),
                        'url' => admin_url('admin.php?page=suple-speed-settings#tab-cache'),
                    ],
                    [
                        'label' => __('Abrir herramientas de caché', 'suple-speed'),
                        'url' => admin_url('admin.php?page=suple-speed-cache'),
                        'secondary' => true,
                    ],
                ],
                'badge' => __('Paso crítico', 'suple-speed'),
                'badge_class' => 'warning',
                'critical' => true,
            ],
            'configure_assets' => [
                'title' => __('Optimiza y agrupa tus assets', 'suple-speed'),
                'description' => __('Activa la fusión inteligente de CSS y JS respetando dependencias y comienza con los grupos A (Core/Theme) y B (Plugins), evitando el grupo C de Elementor hasta verificar compatibilidad.', 'suple-speed'),
                'links' => [
                    [
                        'label' => __('Ir a Ajustes > Assets', 'suple-speed'),
                        'url' => admin_url('admin.php?page=suple-speed-settings#tab-assets'),
                    ],
                    [
                        'label' => __('Gestionar assets', 'suple-speed'),
                        'url' => admin_url('admin.php?page=suple-speed-assets'),
                        'secondary' => true,
                    ],
                ],
                'badge' => __('Paso crítico', 'suple-speed'),
                'badge_class' => 'warning',
                'critical' => true,
            ],
            'psi_tests' => [
                'title' => __('Configura PageSpeed Insights', 'suple-speed'),
                'description' => __('Añade tu API key de PageSpeed Insights, ejecuta un test móvil y otro desktop y deja que Suple Speed genere sugerencias automáticas.', 'suple-speed'),
                'links' => [
                    [
                        'label' => __('Ajustes > PageSpeed Insights', 'suple-speed'),
                        'url' => admin_url('admin.php?page=suple-speed-settings#tab-psi'),
                    ],
                    [
                        'label' => __('Lanzar primer test', 'suple-speed'),
                        'url' => admin_url('admin.php?page=suple-speed-performance'),
                        'secondary' => true,
                    ],
                ],
                'badge' => __('Paso crítico', 'suple-speed'),
                'badge_class' => 'warning',
                'critical' => true,
            ],
            'local_fonts' => [
                'title' => __('Localiza fuentes y precargas críticas', 'suple-speed'),
                'description' => __('Descarga Google Fonts de forma local, aplica font-display: swap y configura los preloads recomendados para mejorar la primera pintura.', 'suple-speed'),
                'links' => [
                    [
                        'label' => __('Ajustes > Fuentes', 'suple-speed'),
                        'url' => admin_url('admin.php?page=suple-speed-settings#tab-fonts'),
                    ],
                    [
                        'label' => __('Escanear y localizar fuentes', 'suple-speed'),
                        'url' => admin_url('admin.php?page=suple-speed-fonts'),
                        'secondary' => true,
                    ],
                ],
                'badge' => __('Recomendado', 'suple-speed'),
                'badge_class' => 'info',
                'critical' => false,
            ],
        ];

        if (class_exists('WooCommerce')) {
            $steps['woocommerce_rules'] = [
                'title' => __('Afina reglas para WooCommerce', 'suple-speed'),
                'description' => __('Excluye checkout y carrito de la caché, mantén el modo test activo mientras ajustas optimizaciones y aplica reglas dedicadas para tu tienda.', 'suple-speed'),
                'links' => [
                    [
                        'label' => __('Configurar reglas', 'suple-speed'),
                        'url' => admin_url('admin.php?page=suple-speed-rules'),
                    ],
                    [
                        'label' => __('Ver compatibilidad', 'suple-speed'),
                        'url' => admin_url('admin.php?page=suple-speed-compatibility'),
                        'secondary' => true,
                    ],
                ],
                'badge' => __('Recomendado', 'suple-speed'),
                'badge_class' => 'info',
                'critical' => false,
            ];
        }

        $this->onboarding_steps = $steps;

        return $this->onboarding_steps;
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
        $current_settings = get_option('suple_speed_settings', []);

        // Configuraciones booleanas
        $boolean_settings = [
            'cache_enabled', 'compression_enabled', 'assets_enabled',
            'merge_css', 'merge_js', 'minify_css', 'minify_js', 'defer_js',
            'critical_css_enabled', 'fonts_local', 'fonts_display_swap', 'images_lazy', 'images_lqip',
            'images_webp_rewrite', 'elementor_compat', 'safe_mode',
            'multisite_network', 'assets_test_mode', 'psi_auto_test'
        ];
        
        foreach ($boolean_settings as $setting) {
            $sanitized[$setting] = array_key_exists($setting, $input)
                ? filter_var($input[$setting], FILTER_VALIDATE_BOOLEAN)
                : false;
        }
        
        // Configuraciones numéricas
        $numeric_settings = [
            'cache_ttl' => HOUR_IN_SECONDS,
            'assets_version_hashing' => 0,
            'images_lqip_quality' => 20
        ];
        
        foreach ($numeric_settings as $setting => $default) {
            if ($setting === 'cache_ttl') {
                $sanitized[$setting] = isset($input[$setting])
                    ? intval($input[$setting]) * HOUR_IN_SECONDS
                    : $default;
                continue;
            }

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
            'assets_async_css_groups',
            'assets_merge_js_groups', 'cache_vary_cookies',
            'assets_test_roles', 'assets_test_ips', 'preload_assets',
            'fonts_preload', 'images_preload'
        ];
        
        foreach ($array_settings as $setting) {
            if ($setting === 'preload_assets') {
                $existing = isset($current_settings[$setting]) && is_array($current_settings[$setting])
                    ? $current_settings[$setting]
                    : [];

                $sanitized[$setting] = $this->sanitize_preload_assets($input[$setting] ?? null, $existing);
                continue;
            }

            $sanitized[$setting] = isset($input[$setting])
                ? $this->normalize_list_setting($input[$setting])
                : [];

            if ($setting === 'assets_async_css_groups') {
                $sanitized[$setting] = array_values(array_unique(array_intersect(
                    ['A', 'B', 'C', 'D'],
                    array_map('strtoupper', $sanitized[$setting])
                )));
            }
        }

        
        // Campos de textarea
        $sanitized['images_critical_manual'] = isset($input['images_critical_manual']) ?
            sanitize_textarea_field($input['images_critical_manual']) :
            '';

        if (isset($input['cdn_integrations']) && is_array($input['cdn_integrations'])) {
            $sanitized['cdn_integrations'] = $this->sanitize_cdn_settings($input['cdn_integrations']);
        } else {
            $sanitized['cdn_integrations'] = $current_settings['cdn_integrations'] ?? [];
        }

        return $sanitized;
    }

    /**
     * Sanitizar configuraciones de CDN
     */
    private function sanitize_cdn_settings($input) {
        $defaults = [
            'cloudflare' => [
                'enabled' => false,
                'api_token' => '',
                'zone_id' => '',
            ],
            'bunnycdn' => [
                'enabled' => false,
                'api_key' => '',
                'zone_id' => '',
            ],
        ];

        $sanitized = [];

        foreach ($defaults as $provider => $provider_defaults) {
            $provider_input = $input[$provider] ?? [];

            if (!is_array($provider_input)) {
                $provider_input = [];
            }

            $provider_settings = [
                'enabled' => filter_var($provider_input['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];

            foreach ($provider_defaults as $field => $default_value) {
                if ($field === 'enabled') {
                    continue;
                }

                $value = $provider_input[$field] ?? '';
                $provider_settings[$field] = $value === ''
                    ? ''
                    : sanitize_text_field($value);
            }

            $sanitized[$provider] = wp_parse_args($provider_settings, $provider_defaults);
        }

        return $sanitized;
    }

    /**
     * Sanitizar configuración de precarga de assets
     */
    private function sanitize_preload_assets($value, $existing = []) {
        if ($value === null) {
            return is_array($existing) ? $existing : [];
        }

        if (!is_array($value)) {
            return is_array($existing) ? $existing : [];
        }

        $sanitized = [];

        foreach ($value as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $url = esc_url_raw($entry['url'] ?? '');

            if (empty($url)) {
                continue;
            }

            $item = ['url' => $url];

            if (!empty($entry['as'])) {
                $item['as'] = sanitize_text_field($entry['as']);
            }

            if (!empty($entry['type'])) {
                $item['type'] = sanitize_text_field($entry['type']);
            }

            if (!empty($entry['crossorigin'])) {
                $item['crossorigin'] = sanitize_text_field($entry['crossorigin']);
            }

            if (!empty($entry['media'])) {
                $item['media'] = sanitize_text_field($entry['media']);
            }

            $sanitized[] = $item;
        }

        return $sanitized;
    }

    /**
     * Normalizar valores de listas provenientes de textareas o arrays
     */
    private function normalize_list_setting($value) {
        if (is_string($value)) {
            $value = preg_split('/[\r\n,]+/', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $item) {
            if (is_array($item) || is_object($item)) {
                continue;
            }

            $item = is_string($item) ? trim($item) : trim((string) $item);

            if ($item === '') {
                continue;
            }

            $normalized[] = sanitize_text_field($item);
        }

        return $normalized;
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

        $asset_groups = [];
        $manual_overrides = [];
        $bundle_status = ['css' => [], 'js' => []];
        $preload_recommendations = [];

        if (function_exists('suple_speed') && isset(suple_speed()->assets)) {
            $assets_module = suple_speed()->assets;

            if (method_exists($assets_module, 'get_asset_group_labels')) {
                $asset_groups = $assets_module->get_asset_group_labels();
            }

            if (method_exists($assets_module, 'get_manual_groups')) {
                $manual_overrides = $assets_module->get_manual_groups();
            }

            if (method_exists($assets_module, 'get_bundle_status')) {
                $bundle_status = $assets_module->get_bundle_status();
            }

            if (method_exists($assets_module, 'get_preload_recommendations')) {
                $preload_recommendations = $assets_module->get_preload_recommendations();
            }
        }

        // Datos para JavaScript
        wp_localize_script('suple-speed-admin', 'supleSpeedAdmin', [
            'nonce' => wp_create_nonce('suple_speed_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'homeUrl' => home_url('/'),
            'assetGroups' => $asset_groups,
            'manualAssetGroups' => $manual_overrides,
            'bundleStatus' => $bundle_status,
            'preloadRecommendations' => $preload_recommendations,
            'labels' => [
                'handle' => __('Handle', 'suple-speed'),
                'type' => __('Type', 'suple-speed'),
                'detectedGroup' => __('Detected group', 'suple-speed'),
                'manualGroup' => __('Manual group', 'suple-speed'),
                'source' => __('Source', 'suple-speed'),
                'canMerge' => __('Can merge', 'suple-speed'),
                'canDefer' => __('Can defer', 'suple-speed'),
                'css' => __('CSS', 'suple-speed'),
                'js' => __('JS', 'suple-speed'),
                'manual' => __('Manual', 'suple-speed'),
                'auto' => __('Automatic', 'suple-speed'),
                'noHandles' => __('No handles available yet.', 'suple-speed'),
                'noHandlesDetected' => __('No handles detected yet.', 'suple-speed'),
                'noBundles' => __('No bundles have been generated yet. They will appear here after the next optimization run.', 'suple-speed'),
                'bundlesType' => __('Type', 'suple-speed'),
                'bundlesGroup' => __('Group', 'suple-speed'),
                'bundlesIdentifier' => __('Identifier', 'suple-speed'),
                'bundlesVersion' => __('Version', 'suple-speed'),
                'bundlesGenerated' => __('Generated', 'suple-speed'),
                'bundlesHandles' => __('Handles', 'suple-speed'),
                'bundlesSize' => __('Size', 'suple-speed'),
                'groupPrefix' => __('Group', 'suple-speed'),
                'scanPlaceholder' => __('Run a scan to populate the detected handles list and review their current classification.', 'suple-speed'),
                'resource' => __('Resource', 'suple-speed'),
                'size' => __('Size', 'suple-speed'),
                'seenOn' => __('Seen on', 'suple-speed'),
                'position' => __('Position', 'suple-speed'),
                'actions' => __('Actions', 'suple-speed')
            ],
            'strings' => [
                'confirmReset' => __('Are you sure you want to reset all settings?', 'suple-speed'),
                'confirmPurge' => __('Are you sure you want to purge all cache?', 'suple-speed'),
                'confirmCleanRevisions' => __('This will permanently delete all post revisions. Continue?', 'suple-speed'),
                'confirmCleanTransients' => __('Remove all expired transients? Cached data may need to be regenerated.', 'suple-speed'),
                'confirmOptimizeTables' => __('Run OPTIMIZE TABLE on database tables? This may lock tables temporarily.', 'suple-speed'),
                'processing' => __('Processing...', 'suple-speed'),
                'scanningHandles' => __('Scanning handles…', 'suple-speed'),
                'scanHandlesError' => __('We could not retrieve the handles for this page.', 'suple-speed'),
                'confirmClearLogs' => __('Are you sure you want to clear the stored logs?', 'suple-speed'),
                'success' => __('Operation completed successfully', 'suple-speed'),
                'error' => __('An error occurred', 'suple-speed'),

                'databaseNoTables' => __('No table information available.', 'suple-speed'),
                'tablesNeedingOptimization' => __('Tables needing optimization: %1$s of %2$s', 'suple-speed'),
                'tableNeedsAttention' => __('Needs attention', 'suple-speed'),
                'agoFormat' => __('%s ago', 'suple-speed'),
                'never' => __('Never', 'suple-speed')

            ]
        ]);
    }
    
    /**
     * Mostrar avisos de administración
     */
    public function show_admin_notices() {
        if (isset($_GET['page']) && strpos(sanitize_text_field(wp_unslash($_GET['page'])), 'suple-speed') === 0) {
            $feedback = get_transient('suple_speed_critical_css_feedback');
            if ($feedback && is_array($feedback)) {
                $type = $feedback['type'] ?? 'info';
                $message = $feedback['message'] ?? '';

                if (!empty($message)) {
                    $class = 'notice';
                    switch ($type) {
                        case 'success':
                            $class .= ' notice-success';
                            break;
                        case 'error':
                            $class .= ' notice-error';
                            break;
                        default:
                            $class .= ' notice-info';
                            break;
                    }

                    printf('<div class="%1$s is-dismissible"><p>%2$s</p></div>', esc_attr($class), wp_kses_post($message));
                }

                delete_transient('suple_speed_critical_css_feedback');
            }
        }

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
    public function render_dashboard($active_tab = null) {
        if ($active_tab === null) {
            $requested = isset($_GET['section']) ? sanitize_key(wp_unslash($_GET['section'])) : '';
            if (empty($requested) && isset($_GET['tab'])) {
                $requested = sanitize_key(wp_unslash($_GET['tab']));
            }
            $active_tab = $requested ?: 'overview';
        }

        if (empty($active_tab)) {
            $active_tab = 'overview';
        }

        include SUPLE_SPEED_PLUGIN_DIR . 'views/admin-dashboard.php';
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
        $this->settings = get_option('suple_speed_settings', []);

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
            suple_speed()->reset_default_options();
        }

        $this->settings = get_option('suple_speed_settings', []);

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
        $this->settings = get_option('suple_speed_settings', []);
        
        // Importar reglas si existen
        if (isset($import_data['rules']) && is_array($import_data['rules'])) {
            update_option('suple_speed_rules', $import_data['rules']);
        }
        
        wp_send_json_success([
            'message' => __('Settings imported successfully', 'suple-speed'),
            'imported_from_version' => $import_data['version'] ?? 'unknown'
        ]);
    }

    /**

     * AJAX: Guardar credenciales CDN
     */
    public function ajax_save_cdn_settings() {
        check_ajax_referer('suple_speed_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $cdn_settings = isset($_POST['cdn']) ? wp_unslash($_POST['cdn']) : [];
        if (!is_array($cdn_settings)) {
            $cdn_settings = [];
        }

        $sanitized_cdn = $this->sanitize_cdn_settings($cdn_settings);
        $current_settings = get_option('suple_speed_settings', []);
        $current_settings['cdn_integrations'] = $sanitized_cdn;

        update_option('suple_speed_settings', $current_settings);
        $this->settings = $current_settings;

        if ($this->logger) {
            $enabled_providers = array_keys(array_filter($sanitized_cdn, function($config) {
                return !empty($config['enabled']);
            }));

            $this->logger->info('CDN settings updated', [
                'enabled_providers' => $enabled_providers,
            ], 'cdn');
        }

        wp_send_json_success([
            'message' => __('CDN credentials saved successfully', 'suple-speed'),

        ]);
    }

    /**

     * AJAX: Guardar progreso del onboarding
     */
    public function ajax_update_onboarding() {
        check_ajax_referer('suple_speed_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'suple-speed'));
        }

        $step_key = isset($_POST['step']) ? sanitize_key($_POST['step']) : '';
        $completed = isset($_POST['completed'])
            ? filter_var($_POST['completed'], FILTER_VALIDATE_BOOLEAN)
            : false;
        $dismissed = array_key_exists('dismissed', $_POST)
            ? filter_var($_POST['dismissed'], FILTER_VALIDATE_BOOLEAN)
            : null;

        $steps = $this->get_onboarding_steps();
        if (!is_array($steps)) {
            $steps = [];
        }

        $state = get_option('suple_speed_onboarding', []);
        if (!is_array($state)) {
            $state = [];
        }

        $state_changed = false;

        if ($step_key !== '') {
            if (!isset($steps[$step_key])) {
                wp_send_json_error(__('Invalid onboarding step', 'suple-speed'));
            }

            if ($completed) {
                $state[$step_key] = true;
            } else {
                unset($state[$step_key]);
            }

            $state_changed = true;
        }

        if ($dismissed !== null) {
            if ($dismissed) {
                $state['dismissed'] = true;
            } else {
                unset($state['dismissed']);
            }

            $state_changed = true;
        }

        if (!$state_changed) {
            wp_send_json_error(__('Invalid onboarding request', 'suple-speed'));
        }

        update_option('suple_speed_onboarding', $state);

        $total_steps = count($steps);
        $completed_steps = 0;

        foreach ($steps as $key => $step) {
            if (!empty($state[$key])) {
                $completed_steps++;
            }
        }

        $progress = $total_steps > 0 ? round(($completed_steps / $total_steps) * 100) : 0;

        $remaining_critical = array_filter($steps, function($step, $key) use ($state) {
            return !empty($step['critical']) && empty($state[$key]);
        }, ARRAY_FILTER_USE_BOTH);

        $remaining_labels = array_map(function($step) {
            return wp_strip_all_tags($step['title']);
        }, $remaining_critical);

        wp_send_json_success([
            'completed' => $completed_steps,
            'total' => $total_steps,
            'progress' => $progress,
            'remaining_critical' => array_keys($remaining_critical),
            'remaining_labels' => array_values($remaining_labels),
            'dismissed' => !empty($state['dismissed']),

        ]);
    }

    /**
     * AJAX: Cleanup post revisions
     */
    public function ajax_database_cleanup_revisions() {
        check_ajax_referer('suple_speed_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'suple-speed'));
        }

        $database = $this->get_database_module();

        if (!$database) {
            wp_send_json_error(__('Database tools are not available.', 'suple-speed'));
        }

        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 0;
        $result = $database->cleanup_revisions($limit);

        $message = $result['deleted'] > 0
            ? sprintf(__('Deleted %d post revisions.', 'suple-speed'), $result['deleted'])
            : __('No post revisions were deleted.', 'suple-speed');

        wp_send_json_success([
            'message' => $message,
            'result'  => $result,
            'stats'   => $database->get_stats(),
        ]);
    }

    /**
     * AJAX: Cleanup expired transients
     */
    public function ajax_database_cleanup_transients() {
        check_ajax_referer('suple_speed_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'suple-speed'));
        }

        $database = $this->get_database_module();

        if (!$database) {
            wp_send_json_error(__('Database tools are not available.', 'suple-speed'));
        }

        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 0;
        $result = $database->cleanup_expired_transients($limit);

        $message = $result['deleted'] > 0
            ? sprintf(__('Deleted %d expired transients.', 'suple-speed'), $result['deleted'])
            : __('No expired transients found.', 'suple-speed');

        wp_send_json_success([
            'message' => $message,
            'result'  => $result,
            'stats'   => $database->get_stats(),
        ]);
    }

    /**
     * AJAX: Optimize database tables
     */
    public function ajax_database_optimize_tables() {
        check_ajax_referer('suple_speed_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized', 'suple-speed'));
        }

        $database = $this->get_database_module();

        if (!$database) {
            wp_send_json_error(__('Database tools are not available.', 'suple-speed'));
        }

        $tables = [];
        if (!empty($_POST['tables'])) {
            $tables = array_map(function($table) {
                return sanitize_text_field(wp_unslash($table));
            }, (array) $_POST['tables']);
        }

        $scope = isset($_POST['scope']) ? sanitize_text_field(wp_unslash($_POST['scope'])) : 'overhead';
        $only_overhead = $scope !== 'all';

        $result = $database->optimize_tables($tables, $only_overhead);

        $message = $result['optimized'] > 0
            ? sprintf(__('Optimized %d database tables.', 'suple-speed'), $result['optimized'])
            : __('No tables required optimization.', 'suple-speed');

        wp_send_json_success([
            'message' => $message,
            'result'  => $result,
            'stats'   => $database->get_stats(),
        ]);
    }

    // === UTILIDADES ===

    /**
     * Retrieve database module instance
     */
    private function get_database_module() {
        if ($this->database && method_exists($this->database, 'get_stats')) {
            return $this->database;
        }

        if (function_exists('suple_speed') && isset(suple_speed()->database)) {
            return suple_speed()->database;
        }

        return null;
    }

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

            if ($this->database && method_exists($this->database, 'get_stats')) {
                $data['database_stats'] = $this->database->get_stats();
            }

        }

        return $data;
    }

    /**
     * Gestionar la solicitud de generación de Critical CSS.
     */
    public function handle_generate_critical_css() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'suple-speed'));
        }

        check_admin_referer('suple_speed_generate_critical_css');

        $url = isset($_POST['critical_css_url']) ? esc_url_raw(trim(wp_unslash($_POST['critical_css_url']))) : '';

        if (empty($url) && $this->critical_css_generator) {
            $status = $this->critical_css_generator->get_status();
            if (!empty($status['url'])) {
                $url = $status['url'];
            }
        }

        if (!$this->critical_css_generator) {
            set_transient('suple_speed_critical_css_feedback', [
                'type'    => 'error',
                'message' => __('The Critical CSS generator service is unavailable.', 'suple-speed'),
            ], MINUTE_IN_SECONDS);

            wp_safe_redirect(add_query_arg([
                'page'    => 'suple-speed',
                'section' => 'critical',
            ], admin_url('admin.php')));
            exit;
        }

        if (empty($url)) {
            set_transient('suple_speed_critical_css_feedback', [
                'type'    => 'error',
                'message' => __('Please provide a valid URL to generate Critical CSS.', 'suple-speed'),
            ], MINUTE_IN_SECONDS);
        } else {
            $result = $this->critical_css_generator->queue_generation($url);

            if (is_wp_error($result)) {
                set_transient('suple_speed_critical_css_feedback', [
                    'type'    => 'error',
                    'message' => $result->get_error_message(),
                ], MINUTE_IN_SECONDS);
            } else {
                set_transient('suple_speed_critical_css_feedback', [
                    'type'    => 'success',
                    'message' => __('Critical CSS generation has been queued. Refresh this page in a moment to review the result.', 'suple-speed'),
                ], MINUTE_IN_SECONDS);
            }
        }

        wp_safe_redirect(add_query_arg([
            'page'    => 'suple-speed',
            'section' => 'critical',
        ], admin_url('admin.php')));
        exit;
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