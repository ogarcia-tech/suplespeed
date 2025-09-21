<?php

namespace SupleSpeed;

/**
 * Optimización de assets (CSS/JS)
 */
class Assets {
    
    /**
     * Configuración
     */
    private $settings;
    private $logger;
    private $assets_dir;
    private $assets_url;
    
    /**
     * Grupos de assets
     */
    private $asset_groups = [
        'A' => 'core_theme',    // Core WordPress y tema
        'B' => 'plugins',       // Plugins comunes
        'C' => 'elementor',     // Elementor específico
        'D' => 'third_party'    // Terceros y miscelánea
    ];
    
    /**
     * Handles procesados
     */
    private $processed_handles = [];
    private $excluded_handles = [];
    private $dependency_map = [];
    private $async_css_groups = [];
    private $style_filter_registered = false;
    private $manual_groups = [];
    private $manual_groups_raw = [];
    private $preload_recommendations = [];
    private $preload_rejections = [];

    public function __construct() {
        $this->settings = get_option('suple_speed_settings', []);
        $this->assets_dir = SUPLE_SPEED_CACHE_DIR . 'assets/';
        $this->assets_url = str_replace(ABSPATH, home_url('/'), $this->assets_dir);
        
        if (function_exists('suple_speed')) {
            $this->logger = suple_speed()->logger;
        }

        $this->async_css_groups = array_map('strtoupper', $this->settings['assets_async_css_groups'] ?? []);

        $this->load_manual_groups();
        $this->load_preload_recommendations();
        $this->load_preload_rejections();

        $this->init_hooks();
        $this->load_excluded_handles();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // Solo en frontend y fuera del editor de Elementor
        if (!is_admin() && !$this->is_editor_mode()) {
            add_action('wp_enqueue_scripts', [$this, 'optimize_scripts_styles'], 999);
            add_action('wp_head', [$this, 'inject_critical_css'], 0);
            add_action('wp_head', [$this, 'inject_preloads'], 2);

            // Filtros para modificar output
            add_filter('style_loader_src', [$this, 'modify_css_src'], 10, 2);
            add_filter('script_loader_src', [$this, 'modify_js_src'], 10, 2);
        }

        add_action('init', [$this, 'maybe_schedule_preload_collector']);
        add_action('suple_speed_collect_preload_recommendations', [$this, 'generate_preload_recommendations']);

        // AJAX para escanear handles
        add_action('wp_ajax_suple_speed_scan_handles', [$this, 'ajax_scan_handles']);
        add_action('wp_ajax_suple_speed_get_preload_recommendations', [$this, 'ajax_get_preload_recommendations']);
        add_action('wp_ajax_suple_speed_generate_preload_recommendations', [$this, 'ajax_generate_preload_recommendations']);
        add_action('wp_ajax_suple_speed_accept_preload_recommendation', [$this, 'ajax_accept_preload_recommendation']);
        add_action('wp_ajax_suple_speed_reject_preload_recommendation', [$this, 'ajax_reject_preload_recommendation']);
        add_action('template_redirect', [$this, 'maybe_output_handles_capture'], 0);
    }
    
    /**
     * Verificar si estamos en modo editor
     */
    private function is_editor_mode() {
        if (function_exists('suple_speed') && 
            suple_speed()->elementor_guard && 
            suple_speed()->elementor_guard->should_disable_optimizations()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Cargar handles excluidos
     */
    private function load_excluded_handles() {
        $this->excluded_handles = array_merge(
            $this->settings['assets_exclude_handles'] ?? [],
            $this->get_compatibility_excluded_handles()
        );
    }

    /**
     * Cargar overrides manuales de grupos
     */
    private function load_manual_groups() {
        $stored = get_option('suple_speed_assets_manual_groups', []);

        if (!is_array($stored)) {
            $stored = [];
        }

        $this->set_manual_groups($stored);
    }

    /**
     * Cargar recomendaciones de precarga detectadas
     */
    private function load_preload_recommendations() {
        $stored = get_option('suple_speed_preload_recommendations', []);

        if (!is_array($stored)) {
            $stored = [];
        }

        if (!empty($stored) && array_values($stored) === $stored) {
            $normalized = [];

            foreach ($stored as $entry) {
                if (is_array($entry) && !empty($entry['id'])) {
                    $normalized[$entry['id']] = $entry;
                }
            }

            $stored = $normalized;
        }

        $this->preload_recommendations = $stored;
    }

    /**
     * Cargar rechazos de precarga previos
     */
    private function load_preload_rejections() {
        $stored = get_option('suple_speed_preload_rejections', []);

        if (!is_array($stored)) {
            $stored = [];
        }

        $this->preload_rejections = $stored;
    }
    
    /**
     * Obtener handles excluidos por compatibilidad
     */
    private function get_compatibility_excluded_handles() {
        $excluded = [];

        if (function_exists('suple_speed') && suple_speed()->compat) {
            $excluded = suple_speed()->compat->get_excluded_handles();
        }

        return $excluded;
    }

    /**
     * Actualizar caché interna de configuración
     */
    private function refresh_settings() {
        $this->settings = get_option('suple_speed_settings', []);

        return $this->settings;
    }

    /**
     * Guardar recomendaciones de precarga
     */
    private function save_preload_recommendations() {
        update_option('suple_speed_preload_recommendations', $this->preload_recommendations, false);
    }

    /**
     * Guardar rechazos de precarga
     */
    private function save_preload_rejections() {
        update_option('suple_speed_preload_rejections', $this->preload_rejections, false);
    }

    /**
     * Obtener recomendaciones actuales
     */
    public function get_preload_recommendations($assoc = false) {
        $recommendations = $this->preload_recommendations;

        if (!$assoc) {
            return $this->prepare_preload_recommendations_for_output($recommendations);
        }

        return $recommendations;
    }

    /**
     * Programar el recolector de precargas
     */
    public function maybe_schedule_preload_collector() {
        if (!apply_filters('suple_speed_enable_preload_collector', true)) {
            return;
        }

        if (!wp_next_scheduled('suple_speed_collect_preload_recommendations')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'suple_speed_collect_preload_recommendations');
        }
    }

    /**
     * Generar recomendaciones de precarga analizando URLs candidatas
     */
    public function generate_preload_recommendations($force = false) {
        $this->refresh_settings();

        $urls = $this->get_candidate_urls_for_preloads();

        if (empty($urls)) {
            return $this->prepare_preload_recommendations_for_output($this->preload_recommendations);
        }

        $recommendations = $this->preload_recommendations;
        $seen_ids = [];

        foreach ($urls as $url) {
            $assets = $this->analyze_url_for_preloads($url);

            foreach ($assets as $asset) {
                $id = $asset['id'];

                if (isset($this->preload_rejections[$id])) {
                    continue;
                }

                if ($this->is_asset_already_preloaded($asset['url'], $asset['as'])) {
                    continue;
                }

                if (isset($recommendations[$id])) {
                    $existing = $recommendations[$id];
                    $existing_pages = $existing['pages'] ?? [];
                    $new_pages = $asset['pages'] ?? [];
                    $combined_pages = array_values(array_unique(array_merge($existing_pages, $new_pages)));
                    $asset['pages'] = $combined_pages;

                    if (isset($existing['size']) && !isset($asset['size'])) {
                        $asset['size'] = $existing['size'];
                    }

                    if (isset($existing['position']) && isset($asset['position'])) {
                        $asset['position'] = min($existing['position'], $asset['position']);
                    }

                    if (!isset($asset['collected_at']) && isset($existing['collected_at'])) {
                        $asset['collected_at'] = $existing['collected_at'];
                    }
                }

                $asset['last_seen'] = time();
                $recommendations[$id] = $asset;
                $seen_ids[$id] = true;
            }
        }

        foreach ($recommendations as $id => $entry) {
            if (isset($this->preload_rejections[$id])) {
                unset($recommendations[$id]);
                continue;
            }

            if ($this->is_asset_already_preloaded($entry['url'] ?? '', $entry['as'] ?? '')) {
                unset($recommendations[$id]);
                continue;
            }

            if (!isset($seen_ids[$id])) {
                $last_seen = $entry['last_seen'] ?? $entry['collected_at'] ?? 0;

                if ($last_seen && (time() - $last_seen) > WEEK_IN_SECONDS) {
                    unset($recommendations[$id]);
                }
            }
        }

        if (!empty($recommendations)) {
            uasort($recommendations, function ($a, $b) {
                $posA = $a['position'] ?? PHP_INT_MAX;
                $posB = $b['position'] ?? PHP_INT_MAX;

                if ($posA === $posB) {
                    $sizeA = isset($a['size']) ? (int) $a['size'] : PHP_INT_MAX;
                    $sizeB = isset($b['size']) ? (int) $b['size'] : PHP_INT_MAX;

                    return $sizeA <=> $sizeB;
                }

                return $posA <=> $posB;
            });

            if (count($recommendations) > 20) {
                $recommendations = array_slice($recommendations, 0, 20, true);
            }
        }

        $this->preload_recommendations = $recommendations;
        $this->save_preload_recommendations();

        if ($this->logger) {
            $this->logger->info('Preload recommendations updated', [
                'total' => count($recommendations),
                'urls' => $urls,
            ], 'assets');
        }

        return $this->prepare_preload_recommendations_for_output($recommendations);
    }

    /**
     * Obtener URLs candidatas para analizar
     */
    private function get_candidate_urls_for_preloads() {
        $urls = [home_url('/')];

        $recent_posts = wp_get_recent_posts([
            'numberposts' => 8,
            'post_status' => 'publish',
            'post_type' => apply_filters('suple_speed_preload_collector_post_types', ['page', 'post'])
        ]);

        foreach ($recent_posts as $post) {
            if (empty($post['ID'])) {
                continue;
            }

            $permalink = get_permalink((int) $post['ID']);

            if ($permalink) {
                $urls[] = $permalink;
            }
        }

        $analytics = get_option('suple_speed_analytics_top_pages', []);

        if (is_array($analytics)) {
            foreach ($analytics as $entry) {
                if (is_string($entry)) {
                    $urls[] = $entry;
                } elseif (is_array($entry) && !empty($entry['url'])) {
                    $urls[] = $entry['url'];
                }
            }
        }

        $urls = array_values(array_unique(array_filter(array_map('esc_url_raw', $urls))));

        return array_slice($urls, 0, 12);
    }

    /**
     * Analizar una URL en busca de assets críticos
     */
    private function analyze_url_for_preloads($url) {
        $url = esc_url_raw($url);

        if (empty($url)) {
            return [];
        }

        $request_args = apply_filters('suple_speed_preload_collector_request_args', [
            'timeout' => 15,
            'redirection' => 3,
            'user-agent' => 'SupleSpeed/PreloadCollector',
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml'
            ]
        ], $url);

        $response = wp_remote_get($url, $request_args);

        if (is_wp_error($response)) {
            if ($this->logger) {
                $this->logger->warning('Preload collector could not fetch URL', [
                    'url' => $url,
                    'error' => $response->get_error_message(),
                ], 'assets');
            }

            return [];
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code < 200 || $code >= 400) {
            if ($this->logger) {
                $this->logger->notice('Preload collector skipped URL due to HTTP status', [
                    'url' => $url,
                    'status' => $code,
                ], 'assets');
            }

            return [];
        }

        $html = wp_remote_retrieve_body($response);

        if (empty($html)) {
            return [];
        }

        return $this->extract_assets_from_html($html, $url);
    }

    /**
     * Extraer assets críticos del HTML
     */
    private function extract_assets_from_html($html, $page_url) {
        $recommendations = [];

        if (trim($html) === '') {
            return $recommendations;
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = @$dom->loadHTML('<?xml encoding="utf-8"?>' . $html);
        libxml_clear_errors();

        if (!$loaded) {
            return $recommendations;
        }

        $heads = $dom->getElementsByTagName('head');

        if ($heads->length === 0) {
            return $recommendations;
        }

        $head = $heads->item(0);
        $position = 0;

        foreach ($head->childNodes as $node) {
            if ($node->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $position++;
            $tag = strtolower($node->nodeName);

            if ($tag === 'link') {
                $rel_attr = strtolower($node->getAttribute('rel'));
                $rel_tokens = preg_split('/\s+/', $rel_attr);
                $href = $this->ensure_absolute_url($node->getAttribute('href'), $page_url);

                if (!$href) {
                    continue;
                }

                if (in_array('preload', $rel_tokens, true) && strtolower($node->getAttribute('as')) === 'font') {
                    continue;
                }

                if (in_array('stylesheet', $rel_tokens, true)) {
                    $media = strtolower($node->getAttribute('media'));

                    if (!empty($media) && $media !== 'all') {
                        continue;
                    }

                    $size = $this->maybe_fetch_asset_size($href);

                    if ($size !== null && $size > 180000) {
                        continue;
                    }

                    $recommendation = $this->create_preload_recommendation('style', $href, $page_url, $position, [
                        'media' => $node->getAttribute('media')
                    ], $size);

                    if ($recommendation) {
                        $recommendations[$recommendation['id']] = $recommendation;
                    }

                    $font_recommendations = $this->collect_google_font_preloads($href, $page_url, $position);

                    foreach ($font_recommendations as $font_rec) {
                        $recommendations[$font_rec['id']] = $font_rec;
                    }
                }
            }

            if ($tag === 'script') {
                $src = $this->ensure_absolute_url($node->getAttribute('src'), $page_url);

                if (!$src) {
                    continue;
                }

                if ($node->hasAttribute('async') || $node->hasAttribute('defer')) {
                    continue;
                }

                $type_attr = strtolower($node->getAttribute('type'));

                if (!empty($type_attr) && !in_array($type_attr, ['text/javascript', 'application/javascript', 'module'], true)) {
                    continue;
                }

                $size = $this->maybe_fetch_asset_size($src);

                if ($size !== null && $size > 200000) {
                    continue;
                }

                $recommendation = $this->create_preload_recommendation('script', $src, $page_url, $position, [
                    'module' => ($type_attr === 'module')
                ], $size);

                if ($recommendation) {
                    $recommendations[$recommendation['id']] = $recommendation;
                }
            }
        }

        return array_values($recommendations);
    }

    /**
     * Crear estructura de recomendación
     */
    private function create_preload_recommendation($type, $asset_url, $page_url, $position, array $extra = [], $size = null) {
        $asset_url = esc_url_raw($asset_url);

        if (empty($asset_url)) {
            return null;
        }

        $id = md5(strtolower($type . '|' . $asset_url));

        $recommendation = [
            'id' => $id,
            'url' => $asset_url,
            'as' => $type === 'style' ? 'style' : ($type === 'font' ? 'font' : 'script'),
            'type' => $type,
            'pages' => [esc_url_raw($page_url)],
            'collected_at' => time(),
            'last_seen' => time(),
            'position' => $position,
        ];

        if ($size !== null) {
            $recommendation['size'] = (int) $size;
        }

        if (!empty($extra['media'])) {
            $recommendation['media'] = sanitize_text_field($extra['media']);
        }

        if (!empty($extra['crossorigin'])) {
            $recommendation['crossorigin'] = sanitize_text_field($extra['crossorigin']);
        }

        if (!empty($extra['module'])) {
            $recommendation['module'] = (bool) $extra['module'];
        }

        return $recommendation;
    }

    /**
     * Resolver URLs relativas
     */
    private function ensure_absolute_url($asset_url, $base_url) {
        $asset_url = trim((string) $asset_url);

        if ($asset_url === '') {
            return '';
        }

        if (strpos($asset_url, '//') === 0) {
            return (is_ssl() ? 'https:' : 'http:') . $asset_url;
        }

        if (preg_match('#^https?://#i', $asset_url)) {
            return $asset_url;
        }

        if ($asset_url[0] === '/') {
            return home_url($asset_url);
        }

        $parts = wp_parse_url($base_url);

        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return home_url('/' . ltrim($asset_url, '/'));
        }

        $path = isset($parts['path']) ? $parts['path'] : '/';

        if (substr($path, -1) !== '/') {
            $path = trailingslashit(dirname($path));
        }

        if ($path === './') {
            $path = '/';
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return $parts['scheme'] . '://' . $parts['host'] . $port . $path . ltrim($asset_url, '/');
    }

    /**
     * Intentar obtener el tamaño de un asset
     */
    private function maybe_fetch_asset_size($asset_url) {
        $asset_url = esc_url_raw($asset_url);

        if (empty($asset_url)) {
            return null;
        }

        $home_host = wp_parse_url(home_url(), PHP_URL_HOST);
        $asset_host = wp_parse_url($asset_url, PHP_URL_HOST);

        if ($asset_host && $home_host && strcasecmp($asset_host, $home_host) !== 0) {
            return null;
        }

        $response = wp_remote_head($asset_url, [
            'timeout' => 10,
            'redirection' => 3,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code < 200 || $code >= 400) {
            return null;
        }

        $length = wp_remote_retrieve_header($response, 'content-length');

        if ($length !== false) {
            return (int) $length;
        }

        return null;
    }

    /**
     * Detectar fuentes en hojas de Google Fonts
     */
    private function collect_google_font_preloads($stylesheet_url, $page_url, $position) {
        $host = wp_parse_url($stylesheet_url, PHP_URL_HOST);

        if (!$host || stripos($host, 'fonts.googleapis') === false) {
            return [];
        }

        $response = wp_remote_get($stylesheet_url, [
            'timeout' => 10,
            'redirection' => 3,
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code < 200 || $code >= 400) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);

        if (empty($body)) {
            return [];
        }

        $recommendations = [];

        if (preg_match_all('#url\(([^)]+\.woff2[^)]*)\)#i', $body, $matches)) {
            foreach ($matches[1] as $font_url_raw) {
                $font_url = trim($font_url_raw, "\"' ");
                $font_url = $this->ensure_absolute_url($font_url, $stylesheet_url);

                $recommendation = $this->create_preload_recommendation('font', $font_url, $page_url, $position, [
                    'crossorigin' => 'anonymous'
                ]);

                if ($recommendation) {
                    $recommendations[$recommendation['id']] = $recommendation;
                }
            }
        }

        return array_values($recommendations);
    }

    /**
     * Verificar si un asset ya está configurado para precarga
     */
    private function is_asset_already_preloaded($asset_url, $as) {
        if (empty($asset_url)) {
            return false;
        }

        $configured = $this->settings['preload_assets'] ?? [];

        if (!is_array($configured)) {
            return false;
        }

        foreach ($configured as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $entry_url = $entry['url'] ?? '';
            $entry_as = $entry['as'] ?? '';

            if (!$entry_url) {
                continue;
            }

            if (strcasecmp($entry_url, $asset_url) === 0 && (!$entry_as || $entry_as === $as)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Preparar recomendaciones para salida pública
     */
    private function prepare_preload_recommendations_for_output($recommendations) {
        if (!is_array($recommendations) || empty($recommendations)) {
            return [];
        }

        $output = [];

        foreach ($recommendations as $id => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $pages = [];

            foreach ($entry['pages'] ?? [] as $page) {
                $pages[] = esc_url_raw($page);
            }

            $output[] = [
                'id' => $entry['id'] ?? $id,
                'url' => esc_url_raw($entry['url'] ?? ''),
                'as' => $entry['as'] ?? '',
                'type' => $entry['type'] ?? '',
                'crossorigin' => $entry['crossorigin'] ?? '',
                'media' => $entry['media'] ?? '',
                'module' => !empty($entry['module']),
                'size' => isset($entry['size']) ? (int) $entry['size'] : null,
                'pages' => $pages,
                'collected_at' => isset($entry['collected_at']) ? (int) $entry['collected_at'] : null,
                'last_seen' => isset($entry['last_seen']) ? (int) $entry['last_seen'] : null,
                'position' => isset($entry['position']) ? (int) $entry['position'] : null,
            ];
        }

        return $output;
    }
    
    /**
     * Optimizar scripts y estilos
     */
    public function optimize_scripts_styles() {
        global $wp_scripts, $wp_styles;
        
        if (!$this->should_optimize()) {
            return;
        }
        
        // Construir mapas de dependencias
        $this->build_dependency_maps();
        
        // Optimizar CSS
        if ($this->settings['assets_enabled'] && $this->settings['merge_css']) {
            $this->optimize_css();
        }
        
        // Optimizar JS
        if ($this->settings['assets_enabled'] && $this->settings['merge_js']) {
            $this->optimize_js();
        }
    }
    
    /**
     * Verificar si se debe optimizar
     */
    private function should_optimize() {
        if ($this->is_scan_capture_request()) {
            return false;
        }

        // Verificar configuración
        if (!$this->settings['assets_enabled']) {
            return false;
        }
        
        // Modo seguro
        if ($this->settings['safe_mode']) {
            return false;
        }
        
        // Verificar modo test
        if ($this->is_test_mode() && !$this->is_test_user()) {
            return false;
        }
        
        // Aplicar filtros
        return apply_filters('suple_speed_should_optimize_assets', true);
    }
    
    /**
     * Verificar modo test
     */
    private function is_test_mode() {
        return $this->settings['assets_test_mode'] ?? false;
    }
    
    /**
     * Verificar si es usuario de prueba
     */
    private function is_test_user() {
        // Por rol
        $test_roles = $this->settings['assets_test_roles'] ?? ['administrator'];
        $user = wp_get_current_user();
        
        if (!empty(array_intersect($user->roles, $test_roles))) {
            return true;
        }
        
        // Por IP
        $test_ips = $this->settings['assets_test_ips'] ?? [];
        $user_ip = $this->get_user_ip();
        
        return in_array($user_ip, $test_ips);
    }
    
    /**
     * Obtener IP del usuario
     */
    private function get_user_ip() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return $ip;
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    /**
     * Construir mapas de dependencias
     */
    private function build_dependency_maps() {
        global $wp_scripts, $wp_styles;
        
        // Construir mapa de dependencias CSS
        $this->dependency_map['css'] = $this->build_dependency_tree($wp_styles);
        
        // Construir mapa de dependencias JS
        $this->dependency_map['js'] = $this->build_dependency_tree($wp_scripts);
    }
    
    /**
     * Construir árbol de dependencias
     */
    private function build_dependency_tree($wp_dependencies) {
        $tree = [];
        $processed = [];
        
        foreach ($wp_dependencies->queue as $handle) {
            $this->build_dependency_branch($handle, $wp_dependencies, $tree, $processed);
        }
        
        return $tree;
    }
    
    /**
     * Construir rama de dependencias
     */
    private function build_dependency_branch($handle, $wp_dependencies, &$tree, &$processed) {
        if (isset($processed[$handle])) {
            return;
        }
        
        $processed[$handle] = true;
        
        if (!isset($wp_dependencies->registered[$handle])) {
            return;
        }
        
        $item = $wp_dependencies->registered[$handle];
        
        // Procesar dependencias primero
        if (!empty($item->deps)) {
            foreach ($item->deps as $dep) {
                $this->build_dependency_branch($dep, $wp_dependencies, $tree, $processed);
            }
        }
        
        $tree[$handle] = [
            'src' => $item->src,
            'deps' => $item->deps ?? [],
            'ver' => $item->ver,
            'group' => $this->classify_handle($handle, $item->src),
            'can_merge' => $this->can_merge_handle($handle),
            'can_defer' => $this->can_defer_handle($handle),
            'media' => $item->args ?? 'all' // Para CSS
        ];
    }
    
    /**
     * Clasificar handle en grupo
     */
    private function classify_handle($handle, $src) {
        $manual_group = $this->get_manual_group_for_handle($handle);

        if (!empty($manual_group)) {
            return $manual_group;
        }

        // Elementor (Grupo C)
        if (strpos($handle, 'elementor') !== false ||
            strpos($src, 'elementor') !== false ||
            strpos($handle, 'swiper') !== false) {
            return 'C';
        }
        
        // Core WordPress y tema (Grupo A)
        if (strpos($src, 'wp-admin') !== false ||
            strpos($src, 'wp-includes') !== false ||
            strpos($src, '/themes/') !== false ||
            in_array($handle, ['jquery', 'jquery-core', 'jquery-migrate'])) {
            return 'A';
        }
        
        // Plugins conocidos (Grupo B)
        $plugin_indicators = [
            'woocommerce', 'contact-form-7', 'yoast', 'rankmath',
            'wpml', 'polylang', 'jetpack'
        ];
        
        foreach ($plugin_indicators as $indicator) {
            if (strpos($handle, $indicator) !== false || 
                strpos($src, $indicator) !== false) {
                return 'B';
            }
        }
        
        // Por defecto, terceros (Grupo D)
        return 'D';
    }

    /**
     * Obtener grupo manual asignado a un handle
     */
    private function get_manual_group_for_handle($handle) {
        $normalized = strtolower($handle);

        return $this->manual_groups[$normalized] ?? '';
    }
    
    /**
     * Verificar si se puede fusionar handle
     */
    private function can_merge_handle($handle) {
        // Verificar exclusiones
        if (in_array($handle, $this->excluded_handles)) {
            return false;
        }
        
        // Verificar lista negra de patrones
        $no_merge_patterns = [
            'google-recaptcha',
            'stripe',
            'paypal',
            'facebook',
            'analytics',
            'gtag',
            'customize-preview'
        ];
        
        foreach ($no_merge_patterns as $pattern) {
            if (strpos($handle, $pattern) !== false) {
                return false;
            }
        }
        
        return apply_filters('suple_speed_can_merge_handle', true, $handle);
    }
    
    /**
     * Verificar si se puede diferir handle
     */
    private function can_defer_handle($handle) {
        // No diferir si está en la lista de no-defer
        $no_defer_handles = array_merge(
            $this->settings['assets_no_defer_handles'] ?? [],
            $this->get_compatibility_no_defer_handles()
        );
        
        if (in_array($handle, $no_defer_handles)) {
            return false;
        }
        
        // No diferir jQuery y dependencias críticas
        $critical_handles = ['jquery', 'jquery-core', 'jquery-migrate'];
        if (in_array($handle, $critical_handles)) {
            return false;
        }
        
        return apply_filters('suple_speed_can_defer_script', true, $handle);
    }
    
    /**
     * Obtener handles sin defer por compatibilidad
     */
    private function get_compatibility_no_defer_handles() {
        $no_defer = [];
        
        if (function_exists('suple_speed') && suple_speed()->compat) {
            $no_defer = suple_speed()->compat->get_no_defer_handles();
        }
        
        return $no_defer;
    }
    
    // === OPTIMIZACIÓN CSS ===
    
    /**
     * Optimizar CSS
     */
    private function optimize_css() {
        global $wp_styles;
        
        if (empty($this->dependency_map['css'])) {
            return;
        }
        
        // Agrupar CSS por grupos configurados
        $enabled_groups = $this->settings['assets_merge_css_groups'] ?? ['A', 'B'];
        $grouped_css = $this->group_assets($this->dependency_map['css'], $enabled_groups);
        
        foreach ($grouped_css as $group => $handles) {
            if (empty($handles)) {
                continue;
            }
            
            $merged_file = $this->merge_css_group($group, $handles);
            
            if ($merged_file) {
                // Remover handles originales y añadir el fusionado
                foreach (array_keys($handles) as $handle) {
                    wp_dequeue_style($handle);
                    $this->processed_handles[] = $handle;
                }
                
                // Registrar archivo fusionado
                wp_enqueue_style(
                    'suple-speed-css-' . strtolower($group),
                    $merged_file['url'],
                    [],
                    $merged_file['version']
                );

                if (!empty($this->async_css_groups)) {
                    $this->ensure_async_style_filter();
                }
            }
        }

        if (!empty($this->async_css_groups)) {
            $this->log_async_manual_tests();
        }
    }
    
    /**
     * Fusionar grupo de CSS
     */
    private function merge_css_group($group, $handles) {
        $cache_key = $this->generate_css_cache_key($group, $handles);
        $merged_file = $this->assets_dir . 'css-' . $group . '-' . $cache_key . '.css';
        $merged_url = $this->assets_url . 'css-' . $group . '-' . $cache_key . '.css';
        
        // Verificar si ya existe y está actualizado
        if (file_exists($merged_file) && $this->is_cache_valid($merged_file, $handles)) {
            return [
                'file' => $merged_file,
                'url' => $merged_url,
                'version' => filemtime($merged_file)
            ];
        }
        
        // Crear directorio si no existe
        if (!file_exists($this->assets_dir)) {
            wp_mkdir_p($this->assets_dir);
        }
        
        $merged_content = '';
        $source_files = [];
        
        foreach ($handles as $handle => $data) {
            $css_content = $this->get_css_content($data['src'], $handle);
            
            if ($css_content !== false) {
                // Procesar URLs relativas
                $css_content = $this->process_css_urls($css_content, $data['src']);
                
                // Minificar si está habilitado
                if ($this->settings['minify_css']) {
                    $css_content = $this->minify_css($css_content);
                }
                
                $merged_content .= "/* Handle: {$handle} */\n" . $css_content . "\n\n";
                $source_files[] = $data['src'];
            }
        }
        
        if (!empty($merged_content)) {
            // Optimizaciones finales
            $merged_content = $this->optimize_final_css($merged_content);

            if (function_exists('suple_speed')) {
                $fonts_module = suple_speed()->fonts ?? null;

                if ($fonts_module && method_exists($fonts_module, 'enforce_font_display_swap')) {
                    $result = $fonts_module->enforce_font_display_swap(
                        $merged_content,
                        'merged_css_' . strtolower($group)
                    );

                    if (is_array($result) && isset($result['css'])) {
                        $merged_content = $result['css'];
                    }
                }
            }

            // Guardar archivo
            $bytes_written = file_put_contents($merged_file, $merged_content);

            if ($bytes_written !== false) {
                // Guardar metadatos
                $this->save_merge_metadata($merged_file, [
                    'type' => 'css',
                    'group' => $group,
                    'handles' => array_keys($handles),
                    'source_files' => $source_files,
                    'size' => $bytes_written,
                    'created' => time()
                ]);
                
                // Log
                if ($this->logger) {
                    $this->logger->info('CSS group merged successfully', [
                        'group' => $group,
                        'handles_count' => count($handles),
                        'output_size' => $bytes_written
                    ], 'assets');
                }
                
                return [
                    'file' => $merged_file,
                    'url' => $merged_url,
                    'version' => filemtime($merged_file)
                ];
            }
        }
        
        return false;
    }
    
    /**
     * Obtener contenido CSS
     */
    private function get_css_content($src, $handle) {
        // Convertir URL relativa a path absoluto
        if (strpos($src, '//') === false) {
            $src = home_url($src);
        }
        
        // Verificar si es archivo local
        $parsed_url = parse_url($src);
        $site_url = parse_url(home_url());
        
        if ($parsed_url['host'] !== $site_url['host']) {
            // Archivo externo, intentar descargar
            return $this->download_external_css($src);
        }
        
        // Archivo local
        $file_path = ABSPATH . ltrim($parsed_url['path'], '/');
        
        if (file_exists($file_path)) {
            return file_get_contents($file_path);
        }
        
        return false;
    }
    
    /**
     * Descargar CSS externo
     */
    private function download_external_css($url) {
        $transient_key = 'suple_speed_external_css_' . md5($url);
        $cached_content = get_transient($transient_key);
        
        if ($cached_content !== false) {
            return $cached_content;
        }
        
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'user-agent' => 'Suple Speed Plugin'
        ]);
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $content = wp_remote_retrieve_body($response);
            
            // Cache por 1 hora
            set_transient($transient_key, $content, HOUR_IN_SECONDS);
            
            return $content;
        }
        
        return false;
    }
    
    /**
     * Procesar URLs en CSS
     */
    private function process_css_urls($css_content, $original_src) {
        // Obtener directorio base del CSS original
        $base_url = dirname($original_src);
        
        // Reemplazar URLs relativas
        $css_content = preg_replace_callback(
            '/url\s*\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/i',
            function($matches) use ($base_url) {
                $url = trim($matches[1], '\'"');
                
                // Skip data URIs y URLs absolutas
                if (strpos($url, 'data:') === 0 || 
                    strpos($url, 'http') === 0 || 
                    strpos($url, '//') === 0) {
                    return $matches[0];
                }
                
                // Convertir URL relativa a absoluta
                if (strpos($url, '/') === 0) {
                    $absolute_url = home_url($url);
                } else {
                    $absolute_url = $base_url . '/' . $url;
                }
                
                return 'url("' . $absolute_url . '")';
            },
            $css_content
        );
        
        return $css_content;
    }
    
    /**
     * Minificar CSS
     */
    private function minify_css($css) {
        // Eliminar comentarios
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Eliminar espacios en blanco innecesarios
        $css = str_replace(["\r\n", "\r", "\n", "\t"], '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        
        // Optimizar selectores y propiedades
        $css = preg_replace('/;\s*}/', '}', $css);
        $css = preg_replace('/\s*{\s*/', '{', $css);
        $css = preg_replace('/;\s*/', ';', $css);
        $css = preg_replace('/:\s*/', ':', $css);
        
        return trim($css);
    }
    
    /**
     * Optimizaciones finales de CSS
     */
    private function optimize_final_css($css) {
        // Eliminar duplicados de reglas
        // TODO: Implementar eliminación de duplicados más sofisticada
        
        // Optimizar colores
        $css = preg_replace('/#([0-9a-f])\1([0-9a-f])\2([0-9a-f])\3/i', '#$1$2$3', $css);
        
        // Optimizar valores 0
        $css = preg_replace('/\b0+(px|em|%|in|cm|mm|pc|pt|ex)/', '0', $css);
        
        return $css;
    }
    
    // === OPTIMIZACIÓN JS ===
    
    /**
     * Optimizar JavaScript
     */
    private function optimize_js() {
        global $wp_scripts;
        
        if (empty($this->dependency_map['js'])) {
            return;
        }
        
        // Agrupar JS por grupos configurados
        $enabled_groups = $this->settings['assets_merge_js_groups'] ?? ['A', 'B'];
        $grouped_js = $this->group_assets($this->dependency_map['js'], $enabled_groups);
        
        foreach ($grouped_js as $group => $handles) {
            if (empty($handles)) {
                continue;
            }
            
            $merged_file = $this->merge_js_group($group, $handles);
            
            if ($merged_file) {
                // Remover handles originales y añadir el fusionado
                foreach (array_keys($handles) as $handle) {
                    wp_dequeue_script($handle);
                    $this->processed_handles[] = $handle;
                }
                
                // Registrar archivo fusionado
                wp_enqueue_script(
                    'suple-speed-js-' . strtolower($group),
                    $merged_file['url'],
                    [],
                    $merged_file['version'],
                    true // Cargar en footer
                );
                
                // Aplicar defer si es apropiado
                if ($this->settings['defer_js'] && $group !== 'A') {
                    add_filter('script_loader_tag', function($tag, $handle) use ($group) {
                        if ($handle === 'suple-speed-js-' . strtolower($group)) {
                            return str_replace('<script', '<script defer', $tag);
                        }
                        return $tag;
                    }, 10, 2);
                }
            }
        }
    }
    
    /**
     * Fusionar grupo de JS
     */
    private function merge_js_group($group, $handles) {
        $cache_key = $this->generate_js_cache_key($group, $handles);
        $merged_file = $this->assets_dir . 'js-' . $group . '-' . $cache_key . '.js';
        $merged_url = $this->assets_url . 'js-' . $group . '-' . $cache_key . '.js';
        
        // Verificar si ya existe y está actualizado
        if (file_exists($merged_file) && $this->is_cache_valid($merged_file, $handles)) {
            return [
                'file' => $merged_file,
                'url' => $merged_url,
                'version' => filemtime($merged_file)
            ];
        }
        
        // Crear directorio si no existe
        if (!file_exists($this->assets_dir)) {
            wp_mkdir_p($this->assets_dir);
        }
        
        $merged_content = '';
        $source_files = [];
        
        foreach ($handles as $handle => $data) {
            $js_content = $this->get_js_content($data['src'], $handle);
            
            if ($js_content !== false) {
                // Minificar si está habilitado
                if ($this->settings['minify_js']) {
                    $js_content = $this->minify_js($js_content);
                }
                
                $merged_content .= "/* Handle: {$handle} */\n" . $js_content . "\n;\n\n";
                $source_files[] = $data['src'];
            }
        }
        
        if (!empty($merged_content)) {
            // Guardar archivo
            $bytes_written = file_put_contents($merged_file, $merged_content);
            
            if ($bytes_written !== false) {
                // Guardar metadatos
                $this->save_merge_metadata($merged_file, [
                    'type' => 'js',
                    'group' => $group,
                    'handles' => array_keys($handles),
                    'source_files' => $source_files,
                    'size' => $bytes_written,
                    'created' => time()
                ]);
                
                // Log
                if ($this->logger) {
                    $this->logger->info('JS group merged successfully', [
                        'group' => $group,
                        'handles_count' => count($handles),
                        'output_size' => $bytes_written
                    ], 'assets');
                }
                
                return [
                    'file' => $merged_file,
                    'url' => $merged_url,
                    'version' => filemtime($merged_file)
                ];
            }
        }
        
        return false;
    }
    
    /**
     * Obtener contenido JS
     */
    private function get_js_content($src, $handle) {
        // Convertir URL relativa a path absoluto
        if (strpos($src, '//') === false) {
            $src = home_url($src);
        }
        
        // Verificar si es archivo local
        $parsed_url = parse_url($src);
        $site_url = parse_url(home_url());
        
        if ($parsed_url['host'] !== $site_url['host']) {
            // Archivo externo, intentar descargar
            return $this->download_external_js($src);
        }
        
        // Archivo local
        $file_path = ABSPATH . ltrim($parsed_url['path'], '/');
        
        if (file_exists($file_path)) {
            return file_get_contents($file_path);
        }
        
        return false;
    }
    
    /**
     * Descargar JS externo
     */
    private function download_external_js($url) {
        $transient_key = 'suple_speed_external_js_' . md5($url);
        $cached_content = get_transient($transient_key);
        
        if ($cached_content !== false) {
            return $cached_content;
        }
        
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'user-agent' => 'Suple Speed Plugin'
        ]);
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $content = wp_remote_retrieve_body($response);
            
            // Cache por 1 hora
            set_transient($transient_key, $content, HOUR_IN_SECONDS);
            
            return $content;
        }
        
        return false;
    }
    
    /**
     * Minificar JavaScript básico
     */
    private function minify_js($js) {
        // Eliminar comentarios de una línea
        $js = preg_replace('/\/\/.*$/m', '', $js);
        
        // Eliminar comentarios multilínea
        $js = preg_replace('/\/\*[\s\S]*?\*\//', '', $js);
        
        // Eliminar espacios en blanco innecesarios
        $js = preg_replace('/\s+/', ' ', $js);
        
        // Eliminar espacios alrededor de operadores
        $js = preg_replace('/\s*([{}();,=+\-*\/])\s*/', '$1', $js);
        
        return trim($js);
    }
    
    // === UTILIDADES ===
    
    /**
     * Agrupar assets por grupos habilitados
     */
    private function group_assets($assets, $enabled_groups) {
        $grouped = [];
        
        foreach ($enabled_groups as $group) {
            $grouped[$group] = [];
        }
        
        foreach ($assets as $handle => $data) {
            if (!$data['can_merge']) {
                continue;
            }

            $group = $data['group'];

            $manual_group = $this->get_manual_group_for_handle($handle);

            if (!empty($manual_group)) {
                $group = $manual_group;
            }

            if (in_array($group, $enabled_groups)) {
                $grouped[$group][$handle] = $data;
            }
        }

        return $grouped;
    }

    /**
     * Obtener etiquetas de los grupos disponibles
     */
    public function get_asset_group_labels() {
        $labels = [
            'A' => __('Core / Theme', 'suple-speed'),
            'B' => __('WooCommerce / Security', 'suple-speed'),
            'C' => __('Elementor', 'suple-speed'),
            'D' => __('Third-party / Misc', 'suple-speed')
        ];

        return apply_filters('suple_speed_asset_group_labels', $labels);
    }

    /**
     * Obtener overrides manuales actuales
     */
    public function get_manual_groups() {
        return $this->manual_groups_raw;
    }

    /**
     * Definir overrides manuales
     */
    public function set_manual_groups($groups) {
        if (!is_array($groups)) {
            $groups = [];
        }

        $normalized = [];
        $raw = [];

        foreach ($groups as $handle => $group) {
            $handle_key = strtolower($handle);
            $group_key = strtoupper($group);

            if (empty($handle_key) || empty($group_key)) {
                continue;
            }

            $normalized[$handle_key] = $group_key;
            $raw[$handle_key] = $group_key;
        }

        ksort($normalized);
        ksort($raw);

        $this->manual_groups = $normalized;
        $this->manual_groups_raw = $raw;
    }

    /**
     * Obtener estado de bundles generados
     */
    public function get_bundle_status() {
        $status = [
            'css' => [],
            'js' => []
        ];

        if (!file_exists($this->assets_dir)) {
            return $status;
        }

        $status['css'] = $this->collect_bundle_status('css');
        $status['js'] = $this->collect_bundle_status('js');

        return $status;
    }

    /**
     * Purga los bundles generados para forzar regeneración
     */
    public function purge_asset_cache($types = ['css', 'js']) {
        if (!file_exists($this->assets_dir)) {
            return 0;
        }

        $patterns = [];
        $deleted = 0;

        if (empty($types)) {
            $types = ['css', 'js'];
        }

        if (in_array('css', $types, true)) {
            $patterns[] = $this->assets_dir . 'css-*.css';
        }

        if (in_array('js', $types, true)) {
            $patterns[] = $this->assets_dir . 'js-*.js';
        }

        foreach ($patterns as $pattern) {
            $files = glob($pattern) ?: [];

            foreach ($files as $file) {
                if (is_file($file) && @unlink($file)) {
                    $deleted++;
                }

                $meta_file = $file . '.meta';

                if (is_file($meta_file) && @unlink($meta_file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * Recopila el estado de bundles para un tipo de asset
     */
    private function collect_bundle_status($type) {
        $bundles = [];
        $pattern = $this->assets_dir . $type . '-*.' . $type;
        $files = glob($pattern) ?: [];

        foreach ($files as $file) {
            $basename = basename($file);

            if (!preg_match('/^(css|js)-([A-Z])-([a-f0-9]+)\.' . $type . '$/i', $basename, $matches)) {
                continue;
            }

            $group = strtoupper($matches[2]);
            $identifier = $matches[3];

            $meta = $this->read_merge_metadata($file . '.meta');

            $bundles[$group][] = [
                'file' => $basename,
                'group' => $group,
                'identifier' => $identifier,
                'version' => filemtime($file),
                'created' => $meta['created'] ?? filemtime($file),
                'size' => filesize($file),
                'handles' => $meta['handles'] ?? [],
                'type' => $type
            ];
        }

        foreach ($bundles as &$entries) {
            usort($entries, function ($a, $b) {
                return $b['created'] <=> $a['created'];
            });
        }

        ksort($bundles);

        return $bundles;
    }

    /**
     * Lee metadatos asociados a un bundle
     */
    private function read_merge_metadata($meta_file) {
        if (!is_file($meta_file)) {
            return [];
        }

        $contents = file_get_contents($meta_file);

        if ($contents === false) {
            return [];
        }

        $data = json_decode($contents, true);

        return is_array($data) ? $data : [];
    }
    
    /**
     * Generar clave de caché para CSS
     */
    private function generate_css_cache_key($group, $handles) {
        $key_data = [];
        
        foreach ($handles as $handle => $data) {
            $key_data[] = $handle . '-' . ($data['ver'] ?? '1.0');
        }
        
        return substr(md5(implode('|', $key_data)), 0, 12);
    }
    
    /**
     * Generar clave de caché para JS
     */
    private function generate_js_cache_key($group, $handles) {
        return $this->generate_css_cache_key($group, $handles);
    }
    
    /**
     * Verificar si la caché es válida
     */
    private function is_cache_valid($cache_file, $handles) {
        if (!file_exists($cache_file)) {
            return false;
        }
        
        $cache_time = filemtime($cache_file);
        
        // Verificar si algún archivo fuente es más nuevo
        foreach ($handles as $handle => $data) {
            $source_file = $this->get_source_file_path($data['src']);
            
            if ($source_file && file_exists($source_file)) {
                if (filemtime($source_file) > $cache_time) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Obtener path del archivo fuente
     */
    private function get_source_file_path($src) {
        if (strpos($src, home_url()) === 0) {
            $relative_path = str_replace(home_url(), '', $src);
            return ABSPATH . ltrim($relative_path, '/');
        }
        
        return null;
    }
    
    /**
     * Guardar metadatos de merge
     */
    private function save_merge_metadata($merged_file, $metadata) {
        $metadata_file = $merged_file . '.meta';
        file_put_contents($metadata_file, json_encode($metadata, JSON_PRETTY_PRINT));
    }
    
    /**
     * Modificar src de CSS
     */
    public function modify_css_src($src, $handle) {
        // Aplicar versión hash para cache busting
        if ($this->settings['assets_version_hashing']) {
            $file_path = $this->get_source_file_path($src);
            if ($file_path && file_exists($file_path)) {
                $hash = substr(md5_file($file_path), 0, 8);
                $src = add_query_arg('v', $hash, $src);
            }
        }
        
        return $src;
    }
    
    /**
     * Modificar src de JS
     */
    public function modify_js_src($src, $handle) {
        return $this->modify_css_src($src, $handle);
    }
    
    // === CRITICAL CSS Y PRELOADS ===
    
    /**
     * Inyectar Critical CSS y cargar el resto de forma asíncrona.
     */
    public function inject_critical_css() {
        if (!($this->settings['critical_css_enabled'] ?? false)) {
            return;
        }
        
        $critical_css = $this->get_critical_css();
        
        if (!empty($critical_css)) {
            echo '<style id="suple-speed-critical-css">';
            echo $this->minify_css($critical_css); // Minificar el CSS crítico
            echo '</style>';
            
            // Ahora, instruimos para que los demás CSS se carguen sin bloquear
            add_filter('style_loader_tag', [$this, 'defer_stylesheet_load'], 999, 2);
        }
    }

    /**
     * Modifica la etiqueta <link> de los CSS para que carguen de forma asíncrona.
     */
    public function defer_stylesheet_load($tag, $handle) {
        // No diferir handles críticos o excluidos
        $critical_handles = apply_filters('suple_speed_critical_css_handles', []);
        if (in_array($handle, $this->excluded_handles) || in_array($handle, $critical_handles)) {
            return $tag;
        }

        // Convertir a carga asíncrona
        $preload_tag = str_replace(
            "rel='stylesheet'",
            "rel='preload' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"",
            $tag
        );
        
        // Añadir un fallback para navegadores que no soportan JS
        $noscript_tag = '<noscript>' . $tag . '</noscript>';

        return $preload_tag . "\n" . $noscript_tag;
    }

    
    /**
     * Obtener Critical CSS
     */
    private function get_critical_css() {
        // Aplicar filtros de reglas
        $critical_css = apply_filters('suple_speed_critical_css_content', '', [
            'url' => $this->get_current_url(),
            'post_id' => get_the_ID()
        ]);

        // Si no hay CSS específico, usar el general
        if (empty($critical_css)) {
            $critical_css = $this->settings['critical_css_general'] ?? '';
        }

        return $critical_css;
    }

    /**
     * Registrar filtro para precarga de estilos
     */
    private function ensure_async_style_filter() {
        if ($this->style_filter_registered) {
            return;
        }

        add_filter('style_loader_tag', [$this, 'filter_style_loader_tag'], 10, 4);
        $this->style_filter_registered = true;
    }

    /**
     * Convertir enlaces de estilos en precarga asincrónica
     */
    public function filter_style_loader_tag($html, $handle, $href, $media) {
        if (empty($this->async_css_groups) || empty($href)) {
            return $html;
        }

        if (in_array($handle, $this->excluded_handles, true)) {
            return $html;
        }

        $group = $this->get_group_from_handle($handle);

        if (!$group || !in_array($group, $this->async_css_groups, true)) {
            return $html;
        }

        $this->log_async_manual_tests();

        $media_attr = '';
        if (!empty($media) && $media !== 'all') {
            $media_attr = ' media="' . esc_attr($media) . '"';
        }

        $id_attr = ' id="' . esc_attr($handle) . '-css"';
        $preload_tag = '<link rel="preload" as="style" href="' . esc_url($href) . '"' . $id_attr . $media_attr . ' onload="this.rel=\'stylesheet\';this.removeAttribute(\'onload\');">';
        $noscript_tag = '<noscript><link rel="stylesheet" href="' . esc_url($href) . '"' . $id_attr . $media_attr . '></noscript>';

        return $preload_tag . "\n" . $noscript_tag;
    }

    /**
     * Obtener grupo a partir del handle fusionado
     */
    private function get_group_from_handle($handle) {
        if (strpos($handle, 'suple-speed-css-') !== 0) {
            return null;
        }

        $suffix = strtoupper(str_replace('suple-speed-css-', '', $handle));

        if (strlen($suffix) !== 1) {
            return null;
        }

        return $suffix;
    }

    /**
     * Registrar en el logger las pruebas manuales
     */
    private function log_async_manual_tests() {
        if (!$this->logger) {
            return;
        }

        static $logged = false;

        if ($logged) {
            return;
        }

        $logged = true;

        if (empty($this->async_css_groups)) {
            return;
        }

        $this->logger->notice('Manual test required: validate Elementor CSS dependencies with async loading.', [
            'async_css_groups' => $this->async_css_groups,
            'steps' => [
                '1. Clear caches and load a complex Elementor page on the frontend to ensure widgets render styled.',
                '2. Open the same page in the Elementor editor and verify that the design system and global styles load without delays.',
                '3. Inspect the Network tab to confirm suple-speed-css-* requests complete and rel="stylesheet" is applied after preload.'
            ]
        ]);
    }

    /**
     * Inyectar preloads
     */
    public function inject_preloads() {
        $preloads = $this->get_preload_assets();
        
        foreach ($preloads as $preload) {
            $attributes = [];
            
            foreach ($preload as $key => $value) {
                if ($value !== null) {
                    $attributes[] = $key . '="' . esc_attr($value) . '"';
                }
            }
            
            echo '<link ' . implode(' ', $attributes) . '>';
            echo "\n";
        }
    }
    
    /**
     * Obtener assets para preload
     */
    private function get_preload_assets() {
        $preloads = [];
        
        // Preloads configurados
        $configured_preloads = $this->settings['preload_assets'] ?? [];
        
        foreach ($configured_preloads as $asset) {
            $preloads[] = [
                'rel' => 'preload',
                'href' => $asset['url'],
                'as' => $asset['as'] ?? 'script',
                'type' => $asset['type'] ?? null,
                'crossorigin' => $asset['crossorigin'] ?? null
            ];
        }
        
        // Aplicar filtros de reglas
        $preloads = apply_filters('suple_speed_preload_assets', $preloads, [
            'url' => $this->get_current_url(),
            'post_id' => get_the_ID()
        ]);
        
        return $preloads;
    }
    
    /**
     * Obtener URL actual
     */
    private function get_current_url() {
        return home_url($_SERVER['REQUEST_URI'] ?? '');
    }
    
    // === AJAX ===

    /**
     * AJAX: Escanear handles activos
     */
    public function ajax_get_preload_recommendations() {
        check_ajax_referer('suple_speed_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        wp_send_json_success([
            'recommendations' => $this->prepare_preload_recommendations_for_output($this->preload_recommendations)
        ]);
    }

    /**
     * AJAX: Generar recomendaciones de precarga
     */
    public function ajax_generate_preload_recommendations() {
        check_ajax_referer('suple_speed_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $recommendations = $this->generate_preload_recommendations(true);

        wp_send_json_success([
            'message' => __('Popular pages analyzed. Review the preload suggestions below.', 'suple-speed'),
            'recommendations' => $recommendations
        ]);
    }

    /**
     * AJAX: Aceptar una recomendación de precarga
     */
    public function ajax_accept_preload_recommendation() {
        check_ajax_referer('suple_speed_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $id = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])) : '';

        if (!$id || !isset($this->preload_recommendations[$id])) {
            wp_send_json_error(__('The requested recommendation no longer exists.', 'suple-speed'));
        }

        $recommendation = $this->preload_recommendations[$id];

        $settings = get_option('suple_speed_settings', []);

        if (!is_array($settings)) {
            $settings = [];
        }

        $preload_assets = $settings['preload_assets'] ?? [];

        if (!is_array($preload_assets)) {
            $preload_assets = [];
        }

        $already_configured = false;

        foreach ($preload_assets as $asset) {
            if (!is_array($asset)) {
                continue;
            }

            $asset_url = $asset['url'] ?? '';
            $asset_as = $asset['as'] ?? '';

            if ($asset_url && strcasecmp($asset_url, $recommendation['url']) === 0 && (!$asset_as || $asset_as === $recommendation['as'])) {
                $already_configured = true;
                break;
            }
        }

        if (!$already_configured) {
            $new_entry = [
                'url' => $recommendation['url'],
                'as' => $recommendation['as'],
            ];

            if (!empty($recommendation['type']) && $recommendation['type'] !== $recommendation['as']) {
                $new_entry['type'] = $recommendation['type'];
            }

            if (!empty($recommendation['crossorigin'])) {
                $new_entry['crossorigin'] = $recommendation['crossorigin'];
            }

            if (!empty($recommendation['media'])) {
                $new_entry['media'] = $recommendation['media'];
            }

            $preload_assets[] = $new_entry;
            $settings['preload_assets'] = array_values($preload_assets);
            update_option('suple_speed_settings', $settings);
        }

        unset($this->preload_recommendations[$id]);
        unset($this->preload_rejections[$id]);

        $this->save_preload_recommendations();
        $this->save_preload_rejections();
        $this->refresh_settings();

        if ($this->logger) {
            $this->logger->info('Preload recommendation accepted', [
                'id' => $id,
                'url' => $recommendation['url'],
                'type' => $recommendation['as'],
            ], 'assets');
        }

        wp_send_json_success([
            'message' => __('Preload added successfully.', 'suple-speed'),
            'recommendations' => $this->prepare_preload_recommendations_for_output($this->preload_recommendations)
        ]);
    }

    /**
     * AJAX: Rechazar una recomendación de precarga
     */
    public function ajax_reject_preload_recommendation() {
        check_ajax_referer('suple_speed_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $id = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])) : '';

        if (!$id) {
            wp_send_json_error(__('Invalid recommendation identifier.', 'suple-speed'));
        }

        if (isset($this->preload_recommendations[$id])) {
            unset($this->preload_recommendations[$id]);
            $this->save_preload_recommendations();
        }

        $this->preload_rejections[$id] = time();
        $this->save_preload_rejections();

        if ($this->logger) {
            $this->logger->info('Preload recommendation rejected', [
                'id' => $id,
            ], 'assets');
        }

        wp_send_json_success([
            'message' => __('We will stop suggesting this asset.', 'suple-speed'),
            'recommendations' => $this->prepare_preload_recommendations_for_output($this->preload_recommendations)
        ]);
    }

    /**
     * AJAX: Escanear handles activos
     */
    public function ajax_scan_handles() {
        check_ajax_referer('suple_speed_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Simular frontend para obtener handles
        $url = home_url('/');
        if (isset($_POST['scan_url'])) {
            $url = sanitize_url($_POST['scan_url']);
        }
        
        $handles_data = $this->scan_handles_from_url($url);

        if (is_wp_error($handles_data)) {
            wp_send_json_error($handles_data->get_error_message());
        }

        wp_send_json_success($handles_data);
    }

    /**
     * Escanear handles desde URL específica
     */
    private function scan_handles_from_url($url) {
        $target_url = add_query_arg('suple_speed_capture_handles', '1', $url);

        $request_args = [
            'timeout' => 20,
            'redirection' => 5,
            'user-agent' => 'SupleSpeed Assets Scanner',
            'headers' => [
                'Accept' => 'application/json'
            ],
            'cookies' => $this->get_internal_request_cookies()
        ];

        $request_args = apply_filters('suple_speed_scan_handles_request_args', $request_args, $target_url, $url);

        $response = wp_remote_get($target_url, $request_args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code < 200 || $status_code >= 300) {
            return new \WP_Error(
                'suple_speed_scan_http_error',
                sprintf(
                    __('Unexpected response status: %d', 'suple-speed'),
                    $status_code
                )
            );
        }

        $body = wp_remote_retrieve_body($response);

        if (empty($body)) {
            return new \WP_Error(
                'suple_speed_scan_empty',
                __('The scan request returned an empty response.', 'suple-speed')
            );
        }

        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error(
                'suple_speed_scan_invalid_json',
                __('Unable to parse the assets scan response.', 'suple-speed')
            );
        }

        if (isset($decoded['success'])) {
            if (!$decoded['success']) {
                $message = $decoded['data'] ?? __('Unable to fetch handles from the requested URL.', 'suple-speed');

                if (is_array($message) && isset($message['message'])) {
                    $message = $message['message'];
                }

                if (!is_string($message) || $message === '') {
                    $message = __('Unable to fetch handles from the requested URL.', 'suple-speed');
                }

                return new \WP_Error('suple_speed_scan_failed', $message);
            }

            $decoded = $decoded['data'];
        }

        $css = isset($decoded['css']) && is_array($decoded['css']) ? $decoded['css'] : [];
        $js = isset($decoded['js']) && is_array($decoded['js']) ? $decoded['js'] : [];

        return [
            'css' => $css,
            'js' => $js
        ];
    }

    /**
     * Verificar si la petición actual es un escaneo de handles
     */
    private function is_scan_capture_request() {
        return !empty($_GET['suple_speed_capture_handles']);
    }

    /**
     * Capturar handles encolados cuando se solicita desde el frontend
     */
    public function maybe_output_handles_capture() {
        if (!$this->is_scan_capture_request()) {
            return;
        }

        global $wp_scripts, $wp_styles;

        $wp_styles = wp_styles();
        $wp_scripts = wp_scripts();

        $handles_data = [
            'css' => [],
            'js' => []
        ];

        if ($wp_styles instanceof \WP_Styles) {
            $css_queue = array_unique(array_merge($wp_styles->queue, $wp_styles->done));

            foreach ($css_queue as $handle) {
                if (empty($handle) || !isset($wp_styles->registered[$handle])) {
                    continue;
                }

                if (in_array($handle, $this->excluded_handles, true)) {
                    continue;
                }

                $data = $wp_styles->registered[$handle];
                $src = $this->normalize_asset_src($data->src ?? '', 'css');

                $handles_data['css'][$handle] = [
                    'handle' => $handle,
                    'src' => $src,
                    'deps' => array_values($data->deps ?? []),
                    'ver' => $data->ver,
                    'group' => $this->classify_handle($handle, $src),
                    'manual_group' => $this->get_manual_group_for_handle($handle),
                    'can_merge' => $this->can_merge_handle($handle)
                ];
            }
        }

        if ($wp_scripts instanceof \WP_Scripts) {
            $js_queue = array_unique(array_merge($wp_scripts->queue, $wp_scripts->done));

            foreach ($js_queue as $handle) {
                if (empty($handle) || !isset($wp_scripts->registered[$handle])) {
                    continue;
                }

                if (in_array($handle, $this->excluded_handles, true)) {
                    continue;
                }

                $data = $wp_scripts->registered[$handle];
                $src = $this->normalize_asset_src($data->src ?? '', 'js');

                $handles_data['js'][$handle] = [
                    'handle' => $handle,
                    'src' => $src,
                    'deps' => array_values($data->deps ?? []),
                    'ver' => $data->ver,
                    'group' => $this->classify_handle($handle, $src),
                    'manual_group' => $this->get_manual_group_for_handle($handle),
                    'can_merge' => $this->can_merge_handle($handle),
                    'can_defer' => $this->can_defer_handle($handle)
                ];
            }
        }

        $handles_data = apply_filters('suple_speed_scan_handles_data', $handles_data, $this->get_current_url());

        nocache_headers();

        wp_send_json_success($handles_data);
    }

    /**
     * Normalizar la URL de un asset
     */
    private function normalize_asset_src($src, $type) {
        if (empty($src)) {
            return '';
        }

        if (strpos($src, '//') === 0) {
            return (is_ssl() ? 'https:' : 'http:') . $src;
        }

        if (preg_match('#^https?://#i', $src)) {
            return $src;
        }

        if (strpos($src, '/') === 0) {
            return home_url($src);
        }

        global $wp_scripts, $wp_styles;

        if ($type === 'css' && $wp_styles instanceof \WP_Styles && !empty($wp_styles->base_url)) {
            return trailingslashit($wp_styles->base_url) . ltrim($src, '/');
        }

        if ($type === 'js' && $wp_scripts instanceof \WP_Scripts && !empty($wp_scripts->base_url)) {
            return trailingslashit($wp_scripts->base_url) . ltrim($src, '/');
        }

        return $src;
    }

    /**
     * Obtener cookies actuales para enviarlas en la petición interna
     */
    private function get_internal_request_cookies() {
        if (empty($_COOKIE) || !is_array($_COOKIE)) {
            return [];
        }

        $cookies = [];

        foreach ($_COOKIE as $name => $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $cookies[] = new \WP_Http_Cookie([
                'name' => $name,
                'value' => wp_unslash($value)
            ]);
        }

        return $cookies;
    }
}
