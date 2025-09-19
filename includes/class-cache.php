<?php

namespace SupleSpeed;

/**
 * Sistema de caché de página a disco
 */
class Cache {
    
    /**
     * Configuración de caché
     */
    private $settings;
    private $cache_dir;
    private $logger;
    private $cdn;
    private $last_cdn_results = [];
    private $static_resource_prefixes = [
        '/wp-content/uploads/',
        '/wp-content/cache/',
        '/wp-content/themes/',
        '/wp-content/plugins/',
        '/wp-includes/',
    ];
    
    /**
     * Variaciones de caché
     */
    private $cache_variations = [
        'device' => ['mobile', 'desktop'],
        'language' => null, // Se detecta automáticamente
    ];
    
    public function __construct() {
        $this->settings = get_option('suple_speed_settings', []);
        $this->cache_dir = SUPLE_SPEED_CACHE_DIR . 'html/';
        
        if (function_exists('suple_speed')) {
            $this->logger = suple_speed()->logger;
            $this->cdn = suple_speed()->cdn ?? null;
        }
        
        $this->init_hooks();
        $this->detect_cache_variations();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // Hooks de purga automática
        add_action('save_post', [$this, 'purge_post_cache']);
        add_action('deleted_post', [$this, 'purge_post_cache']);
        add_action('wp_trash_post', [$this, 'purge_post_cache']);
        add_action('untrash_post', [$this, 'purge_post_cache']);
        
        // Purga en cambios de tema/customizer
        add_action('switch_theme', [$this, 'purge_all']);
        add_action('customize_save_after', [$this, 'purge_all']);
        
        // Purga en cambios de opciones importantes
        add_action('update_option', [$this, 'maybe_purge_on_option_update'], 10, 2);

        // Purga programada
        add_action('suple_speed_cleanup_cache', [$this, 'cleanup_expired_cache']);

        // Headers de caché
        add_action('wp_head', [$this, 'add_cache_headers'], 1);
        add_filter('wp_headers', [$this, 'maybe_apply_static_resource_headers']);
    }
    
    /**
     * Detectar variaciones de caché disponibles
     */
    private function detect_cache_variations() {
        // Detectar idiomas disponibles
        if (function_exists('icl_get_languages')) {
            // WPML
            $languages = icl_get_languages('skip_missing=0');
            $this->cache_variations['language'] = array_keys($languages);
        } elseif (function_exists('pll_languages_list')) {
            // Polylang
            $this->cache_variations['language'] = pll_languages_list();
        }
    }
    
    /**
     * Verificar si se debe cachear la página actual
     */
    public function should_cache_page() {
        // Verificar configuración global
        if (!$this->settings['cache_enabled']) {
            return false;
        }
        
        // No cachear en admin
        if (is_admin()) {
            return false;
        }
        
        // No cachear para usuarios logueados (configurable)
        if (is_user_logged_in() && !$this->allow_cache_for_logged_users()) {
            return false;
        }
        
        // No cachear en modo preview/editor de Elementor
        if (function_exists('suple_speed') && 
            suple_speed()->elementor_guard && 
            suple_speed()->elementor_guard->should_disable_optimizations()) {
            return false;
        }
        
        // No cachear páginas con formularios críticos
        if ($this->is_critical_page()) {
            return false;
        }
        
        // No cachear si hay errores PHP
        if ($this->has_php_errors()) {
            return false;
        }
        
        // Verificar exclusiones de query params
        if ($this->has_excluded_query_params()) {
            return false;
        }
        
        // Aplicar filtros de reglas
        return apply_filters('suple_speed_should_cache_page', true, [
            'url' => $this->get_current_url(),
            'post_id' => get_the_ID()
        ]);
    }
    
    /**
     * Verificar si permitir caché para usuarios logueados
     */
    private function allow_cache_for_logged_users() {
        return $this->settings['cache_logged_users'] ?? false;
    }
    
    /**
     * Verificar si es página crítica que no se debe cachear
     */
    private function is_critical_page() {
        // WooCommerce páginas dinámicas
        if (function_exists('is_cart') && is_cart()) {
            return true;
        }
        
        if (function_exists('is_checkout') && is_checkout()) {
            return true;
        }
        
        if (function_exists('is_account_page') && is_account_page()) {
            return true;
        }
        
        // Páginas de formularios
        if (is_page(['contact', 'contacto', 'login'])) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Verificar si hay errores PHP activos
     */
    private function has_php_errors() {
        $error = error_get_last();
        
        if ($error && 
            in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR]) &&
            (time() - filemtime($error['file'])) < 60) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Verificar query params excluidos
     */
    private function has_excluded_query_params() {
        $excluded_params = $this->get_excluded_query_params();
        
        foreach ($excluded_params as $param) {
            if (isset($_GET[$param])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Obtener parámetros de query excluidos
     */
    private function get_excluded_query_params() {
        $default_excluded = [
            'preview',
            'preview_id', 
            'preview_nonce',
            'elementor-preview',
            'elementor_library',
            'ver',
            'nocache',
            'ac-action', // Admin Columns
            'fb_action_ids', // Facebook
            'fb_action_types', // Facebook
            'fb_source', // Facebook
            'fbclid', // Facebook Click ID
            'gclid', // Google Click ID
            'utm_source', // UTM parameters
            'utm_medium',
            'utm_campaign',
            'utm_term',
            'utm_content'
        ];
        
        $custom_excluded = $this->settings['cache_exclude_params'] ?? [];
        
        return apply_filters('suple_speed_cache_exclude_params', 
            array_merge($default_excluded, $custom_excluded)
        );
    }
    
    /**
     * Generar clave de caché
     */
    public function generate_cache_key($url = null) {
        if ($url === null) {
            $url = $this->get_current_url();
        }
        
        $factors = [];
        
        // URL base
        $factors['url'] = $this->normalize_url($url);
        
        // Variación por dispositivo
        if ($this->settings['cache_vary_device'] ?? true) {
            $factors['device'] = wp_is_mobile() ? 'mobile' : 'desktop';
        }
        
        // Variación por idioma
        if ($this->cache_variations['language']) {
            if (function_exists('icl_get_current_language')) {
                $factors['language'] = icl_get_current_language();
            } elseif (function_exists('pll_current_language')) {
                $factors['language'] = pll_current_language();
            }
        }
        
        // Cookies específicas que afectan el contenido
        $cache_cookies = $this->settings['cache_vary_cookies'] ?? [];
        foreach ($cache_cookies as $cookie_name) {
            if (isset($_COOKIE[$cookie_name])) {
                $factors['cookie_' . $cookie_name] = $_COOKIE[$cookie_name];
            }
        }
        
        // Aplicar filtros para factores adicionales
        $factors = apply_filters('suple_speed_cache_key_factors', $factors, $url);
        
        // Generar hash
        return md5(serialize($factors));
    }
    
    /**
     * Normalizar URL para caché
     */
    private function normalize_url($url) {
        $parsed = parse_url($url);
        
        // Remover query params excluidos
        $query_params = [];
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $query_params);
            
            $excluded_params = $this->get_excluded_query_params();
            foreach ($excluded_params as $param) {
                unset($query_params[$param]);
            }
        }
        
        // Reconstruir URL
        $normalized = $parsed['scheme'] . '://' . $parsed['host'];
        if (isset($parsed['port'])) {
            $normalized .= ':' . $parsed['port'];
        }
        $normalized .= $parsed['path'] ?? '/';
        
        if (!empty($query_params)) {
            ksort($query_params); // Ordenar para consistencia
            $normalized .= '?' . http_build_query($query_params);
        }
        
        if (isset($parsed['fragment'])) {
            $normalized .= '#' . $parsed['fragment'];
        }
        
        return $normalized;
    }
    
    /**
     * Obtener URL actual
     */
    private function get_current_url() {
        if (isset($_SERVER['REQUEST_URI'])) {
            return home_url($_SERVER['REQUEST_URI']);
        }
        
        return get_permalink();
    }
    
    /**
     * Obtener archivo de caché
     */
    public function get_cache_file($cache_key = null) {
        if ($cache_key === null) {
            $cache_key = $this->generate_cache_key();
        }
        
        return $this->cache_dir . $cache_key . '.html';
    }
    
    /**
     * Verificar si existe caché válida
     */
    public function has_valid_cache($cache_key = null) {
        $cache_file = $this->get_cache_file($cache_key);
        
        if (!file_exists($cache_file)) {
            return false;
        }
        
        // Verificar TTL
        $ttl = apply_filters('suple_speed_cache_ttl', 
            $this->settings['cache_ttl'] ?? 24 * HOUR_IN_SECONDS, 
            ['url' => $this->get_current_url()]
        );
        
        $cache_time = filemtime($cache_file);
        $expiry_time = $cache_time + $ttl;
        
        return time() < $expiry_time;
    }
    
    /**
     * Obtener contenido de caché
     */
    public function get_cache_content($cache_key = null) {
        if (!$this->has_valid_cache($cache_key)) {
            return false;
        }
        
        $cache_file = $this->get_cache_file($cache_key);
        $content = file_get_contents($cache_file);
        
        if ($content === false) {
            return false;
        }
        
        // Añadir comentario de caché
        $cache_time = filemtime($cache_file);
        $cache_comment = "\n<!-- Suple Speed Cache: " . date('Y-m-d H:i:s', $cache_time) . " -->\n";
        
        return $content . $cache_comment;
    }
    
    /**
     * Servir caché si está disponible
     */
    public function serve_cache() {
        if (!$this->should_cache_page()) {
            return;
        }

        $cache_content = $this->get_cache_content();

        if ($cache_content === false) {
            return;
        }

        // Headers de caché
        $this->send_cache_headers();

        // Enviar contenido
        echo $cache_content;

        // Log del hit de caché
        if ($this->logger) {
            $this->logger->debug('Cache hit served', [
                'url' => $this->get_current_url(),
                'cache_key' => $this->generate_cache_key()
            ], 'cache');
        }

        exit;
    }
    
    /**
     * Procesar salida de página para caché
     */
    public function process_page_output($buffer) {
        // Verificar si se debe cachear
        if (!$this->should_cache_page()) {
            return $buffer;
        }
        
        // Verificar que el buffer no esté vacío o corrupto
        if (empty($buffer) || strlen($buffer) < 100) {
            return $buffer;
        }
        
        // Verificar que sea HTML válido
        if (!$this->is_valid_html($buffer)) {
            return $buffer;
        }
        
        // Procesar HTML para optimizaciones
        $processed_buffer = apply_filters('suple_speed_process_html', $buffer);
        
        // Guardar en caché
        $this->store_cache($processed_buffer);
        
        return $processed_buffer;
    }
    
    /**
     * Verificar si es HTML válido
     */
    private function is_valid_html($buffer) {
        // Verificaciones básicas
        if (strpos($buffer, '<html') === false && strpos($buffer, '<HTML') === false) {
            return false;
        }
        
        if (strpos($buffer, 'wp-json') !== false && strpos($buffer, 'application/json') !== false) {
            return false; // Es una respuesta JSON
        }
        
        return true;
    }
    
    /**
     * Almacenar contenido en caché
     */
    public function store_cache($content, $cache_key = null) {
        if ($cache_key === null) {
            $cache_key = $this->generate_cache_key();
        }
        
        $cache_file = $this->get_cache_file($cache_key);
        
        // Crear directorio si no existe
        $cache_dir = dirname($cache_file);
        if (!file_exists($cache_dir)) {
            wp_mkdir_p($cache_dir);
        }
        
        // Escribir archivo
        $bytes_written = file_put_contents($cache_file, $content);
        
        if ($bytes_written !== false) {
            // Crear archivo de metadatos
            $this->store_cache_metadata($cache_key, [
                'url' => $this->get_current_url(),
                'size' => $bytes_written,
                'created' => time(),
                'post_id' => get_the_ID()
            ]);
            
            // Log del almacenamiento
            if ($this->logger) {
                $this->logger->info('Page cached successfully', [
                    'url' => $this->get_current_url(),
                    'cache_key' => $cache_key,
                    'size' => $bytes_written
                ], 'cache');
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Almacenar metadatos de caché
     */
    private function store_cache_metadata($cache_key, $metadata) {
        $metadata_file = $this->cache_dir . $cache_key . '.meta';
        file_put_contents($metadata_file, json_encode($metadata));
    }
    
    /**
     * Obtener metadatos de caché
     */
    public function get_cache_metadata($cache_key) {
        $metadata_file = $this->cache_dir . $cache_key . '.meta';
        
        if (file_exists($metadata_file)) {
            return json_decode(file_get_contents($metadata_file), true);
        }
        
        return null;
    }
    
    /**
     * Enviar headers de caché
     */
    private function send_cache_headers() {
        $ttl = apply_filters('suple_speed_cache_ttl', 
            $this->settings['cache_ttl'] ?? 24 * HOUR_IN_SECONDS,
            ['url' => $this->get_current_url()]
        );
        
        // Headers básicos de caché
        header('Cache-Control: public, max-age=' . $ttl);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $ttl) . ' GMT');
        
        // ETag basado en el archivo de caché
        $cache_file = $this->get_cache_file();
        if (file_exists($cache_file)) {
            $etag = md5_file($cache_file);
            header('ETag: "' . $etag . '"');
            
            // Verificar If-None-Match
            if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && 
                trim($_SERVER['HTTP_IF_NONE_MATCH'], '"') === $etag) {
                http_response_code(304);
                exit;
            }
        }
        
        // Last-Modified
        if (file_exists($cache_file)) {
            $last_modified = filemtime($cache_file);
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $last_modified) . ' GMT');
            
            // Verificar If-Modified-Since
            if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
                $if_modified_since = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
                if ($last_modified <= $if_modified_since) {
                    http_response_code(304);
                    exit;
                }
            }
        }
    }
    
    /**
     * Añadir headers de caché en wp_head
     */
    public function add_cache_headers() {
        if ($this->should_cache_page()) {
            // Meta tag para identificar páginas cacheables
            echo '<meta name="suple-speed-cacheable" content="true">' . "\n";

            // Información de caché para debug
            if (defined('WP_DEBUG') && WP_DEBUG) {
                echo '<meta name="suple-speed-cache-key" content="' . esc_attr($this->generate_cache_key()) . '">' . "\n";
            }
        }
    }

    /**
     * Añade headers de caché para recursos estáticos servidos por PHP.
     */
    public function maybe_apply_static_resource_headers($headers) {
        $resource = $this->get_static_resource_request();

        if (!$resource) {
            return $headers;
        }

        $ttl = (int) ($this->settings['cache_ttl'] ?? 24 * HOUR_IN_SECONDS);

        if ($ttl <= 0) {
            return $headers;
        }

        $existing_headers = array_change_key_case($headers, CASE_LOWER);
        $sent_headers = $this->get_sent_headers();

        if (!isset($existing_headers['cache-control']) && !isset($sent_headers['cache-control'])) {
            $headers['Cache-Control'] = sprintf('public, max-age=%d', $ttl);
        }

        if (!isset($existing_headers['expires']) && !isset($sent_headers['expires'])) {
            $headers['Expires'] = gmdate('D, d M Y H:i:s', time() + $ttl) . ' GMT';
        }

        if ($resource['file'] && !isset($existing_headers['etag']) && !isset($sent_headers['etag'])) {
            $headers['ETag'] = $this->generate_file_etag($resource['file']);
        }

        return $headers;
    }

    /**
     * Detecta si la petición actual es para un recurso estático local.
     */
    private function get_static_resource_request() {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if (!in_array($method, ['GET', 'HEAD'], true)) {
            return null;
        }

        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $path = $request_uri ? wp_parse_url($request_uri, PHP_URL_PATH) : '';

        if (!$path) {
            return null;
        }

        $normalized_path = '/' . ltrim($path, '/');

        foreach ($this->static_resource_prefixes as $prefix) {
            if (strpos($normalized_path, $prefix) === 0) {
                $file_path = ABSPATH . ltrim($normalized_path, '/');
                $real_root = realpath(ABSPATH);
                $real_path = realpath($file_path);

                if ($real_path && $real_root && strpos($real_path, $real_root) === 0 && is_file($real_path)) {
                    return [
                        'path' => $normalized_path,
                        'file' => $real_path,
                    ];
                }

                return null;
            }
        }

        return null;
    }

    /**
     * Obtiene el listado de headers ya registrados.
     */
    private function get_sent_headers() {
        $sent_headers = [];

        if (function_exists('headers_list')) {
            foreach (headers_list() as $header_line) {
                $parts = explode(':', $header_line, 2);
                if (count($parts) === 2) {
                    $sent_headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
            }
        }

        return $sent_headers;
    }

    /**
     * Genera un ETag basado en el archivo físico.
     */
    private function generate_file_etag($file_path) {
        $stat = @stat($file_path);

        if (!$stat) {
            return '"' . md5($file_path) . '"';
        }

        return '"' . md5($file_path . '|' . $stat['mtime'] . '|' . $stat['size']) . '"';
    }

    // === PURGA DE CACHÉ ===
    
    /**
     * Purgar toda la caché
     */
    public function purge_all() {
        $files_deleted = 0;

        if (is_dir($this->cache_dir)) {
            $files = glob($this->cache_dir . '*');
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                    $files_deleted++;
                }
            }
        }

        // Limpiar transients relacionados
        $this->clean_cache_transients();

        // Purga remota
        $this->purge_cdn('all');

        // Log de purga
        if ($this->logger) {
            $this->logger->info('All cache purged', [
                'files_deleted' => $files_deleted
            ], 'cache');
        }
        
        return $files_deleted;
    }
    
    /**
     * Purgar caché de un post específico
     */
    public function purge_post_cache($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }
        
        $urls_to_purge = [];
        
        // URL del post
        $post_url = get_permalink($post_id);
        if ($post_url) {
            $urls_to_purge[] = $post_url;
        }
        
        // Página de inicio si es sticky post
        if (is_sticky($post_id)) {
            $urls_to_purge[] = home_url('/');
        }
        
        // Archivos de categoría/etiquetas
        if ($post->post_type === 'post') {
            // Categorías
            $categories = get_the_category($post_id);
            foreach ($categories as $category) {
                $urls_to_purge[] = get_category_link($category->term_id);
            }
            
            // Etiquetas
            $tags = get_the_tags($post_id);
            if ($tags) {
                foreach ($tags as $tag) {
                    $urls_to_purge[] = get_tag_link($tag->term_id);
                }
            }
            
            // Archivo de autor
            $urls_to_purge[] = get_author_posts_url($post->post_author);
            
            // Archivos de fecha
            $urls_to_purge[] = get_year_link(get_the_date('Y', $post_id));
            $urls_to_purge[] = get_month_link(get_the_date('Y', $post_id), get_the_date('m', $post_id));
        }
        
        // Purgar URLs
        $purged_count = $this->purge_urls($urls_to_purge);
        
        // Log de purga
        if ($this->logger) {
            $this->logger->info('Post cache purged', [
                'post_id' => $post_id,
                'post_title' => $post->post_title,
                'urls_purged' => count($urls_to_purge),
                'files_purged' => $purged_count
            ], 'cache');
        }
        
        return $purged_count;
    }
    
    /**
     * Purgar caché por URLs específicas
     */
    public function purge_urls($urls) {
        $purged_count = 0;
        $valid_urls = [];

        foreach ($urls as $url) {
            if (empty($url)) {
                continue;
            }

            $purged_count += $this->purge_url($url, false);
            $valid_urls[] = $url;
        }

        if (!empty($valid_urls)) {
            $valid_urls = array_values(array_unique($valid_urls));
            $this->purge_cdn('urls', $valid_urls);
        } else {
            $this->last_cdn_results = [];
        }

        return $purged_count;
    }

    /**
     * Purgar caché de una URL específica
     */
    public function purge_url($url, $trigger_cdn = true) {
        $purged_count = 0;

        // Generar todas las posibles variaciones de caché para esta URL
        $cache_keys = $this->generate_cache_variations($url);
        
        foreach ($cache_keys as $cache_key) {
            $cache_file = $this->get_cache_file($cache_key);
            $meta_file = $this->cache_dir . $cache_key . '.meta';
            
            if (file_exists($cache_file)) {
                unlink($cache_file);
                $purged_count++;
            }
            
            if (file_exists($meta_file)) {
                unlink($meta_file);
            }
        }

        if ($trigger_cdn && !empty($url)) {
            $this->purge_cdn('urls', [$url]);
        } elseif ($trigger_cdn) {
            $this->last_cdn_results = [];
        }

        return $purged_count;
    }

    /**
     * Obtener últimos resultados de purga CDN
     */
    public function get_last_cdn_results() {
        return array_values($this->last_cdn_results);
    }

    /**
     * Ejecutar purga remota en CDN
     */
    private function purge_cdn($type, $urls = []) {
        $this->last_cdn_results = [];

        if (!$this->cdn) {
            return;
        }

        if ($type !== 'all') {
            $urls = array_filter((array) $urls);

            if (empty($urls)) {
                return;
            }
        }

        $results = $this->cdn->purge($type, $urls);

        if (is_array($results)) {
            $this->last_cdn_results = $results;
        }
    }
    
    /**
     * Generar variaciones de caché para una URL
     */
    private function generate_cache_variations($url) {
        $cache_keys = [];
        
        // Factores base
        $base_factors = ['url' => $this->normalize_url($url)];
        
        // Variaciones de dispositivo
        $devices = ['mobile', 'desktop'];
        
        // Variaciones de idioma
        $languages = $this->cache_variations['language'] ?? [null];
        if ($languages === [null]) {
            $languages = [null];
        }
        
        foreach ($devices as $device) {
            foreach ($languages as $language) {
                $factors = $base_factors;
                
                if ($this->settings['cache_vary_device'] ?? true) {
                    $factors['device'] = $device;
                }
                
                if ($language) {
                    $factors['language'] = $language;
                }
                
                $cache_keys[] = md5(serialize($factors));
            }
        }
        
        return $cache_keys;
    }
    
    /**
     * Limpiar transients relacionados con caché
     */
    private function clean_cache_transients() {
        global $wpdb;
        
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_suple_speed_cache_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_suple_speed_cache_%'");
    }
    
    /**
     * Limpiar caché expirada
     */
    public function cleanup_expired_cache() {
        $cleaned_files = 0;
        $total_size_freed = 0;
        
        if (!is_dir($this->cache_dir)) {
            return 0;
        }
        
        $files = glob($this->cache_dir . '*.html');
        $ttl = $this->settings['cache_ttl'] ?? 24 * HOUR_IN_SECONDS;
        
        foreach ($files as $file) {
            $file_time = filemtime($file);
            
            if ((time() - $file_time) > $ttl) {
                $size = filesize($file);
                unlink($file);
                
                // Eliminar archivo de metadatos asociado
                $meta_file = str_replace('.html', '.meta', $file);
                if (file_exists($meta_file)) {
                    unlink($meta_file);
                }
                
                $cleaned_files++;
                $total_size_freed += $size;
            }
        }
        
        // Log de limpieza
        if ($this->logger && $cleaned_files > 0) {
            $this->logger->info('Expired cache cleaned', [
                'files_cleaned' => $cleaned_files,
                'size_freed' => $this->format_bytes($total_size_freed)
            ], 'cache');
        }
        
        return $cleaned_files;
    }
    
    /**
     * Obtener estadísticas de caché
     */
    public function get_cache_stats() {
        if (!is_dir($this->cache_dir)) {
            return [
                'total_files' => 0,
                'total_size' => 0,
                'oldest_file' => null,
                'newest_file' => null
            ];
        }
        
        $files = glob($this->cache_dir . '*.html');
        $total_size = 0;
        $oldest_time = null;
        $newest_time = null;
        
        foreach ($files as $file) {
            $total_size += filesize($file);
            $file_time = filemtime($file);
            
            if ($oldest_time === null || $file_time < $oldest_time) {
                $oldest_time = $file_time;
            }
            
            if ($newest_time === null || $file_time > $newest_time) {
                $newest_time = $file_time;
            }
        }
        
        return [
            'total_files' => count($files),
            'total_size' => $total_size,
            'total_size_formatted' => $this->format_bytes($total_size),
            'oldest_file' => $oldest_time ? date('Y-m-d H:i:s', $oldest_time) : null,
            'newest_file' => $newest_time ? date('Y-m-d H:i:s', $newest_time) : null
        ];
    }
    
    /**
     * Formatear bytes
     */
    private function format_bytes($size) {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $size >= 1024 && $i < 3; $i++) {
            $size /= 1024;
        }
        
        return round($size, 2) . ' ' . $units[$i];
    }
    
    // === AJAX Y REST API ===
    
    /**
     * AJAX: Purgar caché
     */
    public function ajax_purge_cache() {
        check_ajax_referer('suple_speed_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $action = sanitize_text_field($_POST['purge_action'] ?? 'all');
        $purged_count = 0;
        
        switch ($action) {
            case 'all':
                $purged_count = $this->purge_all();
                break;
                
            case 'url':
                $url = sanitize_url($_POST['url'] ?? '');
                if ($url) {
                    $purged_count = $this->purge_url($url);
                }
                break;
                
            case 'post':
                $post_id = intval($_POST['post_id'] ?? 0);
                if ($post_id) {
                    $purged_count = $this->purge_post_cache($post_id);
                }
                break;
        }
        
        wp_send_json_success([
            'message' => sprintf('Cache purged successfully. %d files removed.', $purged_count),
            'purged_count' => $purged_count,
            'cdn_results' => $this->get_last_cdn_results(),
        ]);
    }
    
    /**
     * REST API: Purgar caché
     */
    public function rest_purge_cache($request) {
        $params = $request->get_params();
        $action = $params['action'] ?? 'all';
        
        switch ($action) {
            case 'all':
                $result = $this->purge_all();
                break;
                
            case 'url':
                $url = $params['url'] ?? '';
                $result = $this->purge_url($url);
                break;
                
            default:
                return new \WP_Error('invalid_action', 'Invalid purge action');
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'purged_count' => $result,
            'cdn_results' => $this->get_last_cdn_results(),
        ], 200);
    }
    
    /**
     * Purgar caché en actualización de opciones importantes
     */
    public function maybe_purge_on_option_update($option_name, $new_value) {
        $cache_affecting_options = [
            'stylesheet', // Tema
            'suple_speed_settings',
            'blogname',
            'blogdescription',
            'start_of_week',
            'date_format',
            'time_format'
        ];
        
        if (in_array($option_name, $cache_affecting_options)) {
            $this->purge_all();
        }
    }
}