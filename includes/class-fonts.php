<?php

namespace SupleSpeed;

/**
 * Localización y optimización de Google Fonts
 */
class Fonts {
    
    /**
     * Configuración
     */
    private $settings;
    private $logger;
    private $fonts_dir;
    private $fonts_url;
    private $capturing_head = false;
    
    /**
     * Fuentes detectadas
     */
    private $detected_fonts = [];
    private $processed_fonts = [];
    
    public function __construct() {
        $this->settings = get_option('suple_speed_settings', []);
        $this->fonts_dir = SUPLE_SPEED_UPLOADS_DIR . 'fonts/';
        $this->fonts_url = str_replace(ABSPATH, home_url('/'), $this->fonts_dir);

        $this->settings['fonts_display_swap'] = isset($this->settings['fonts_display_swap'])
            ? (bool) $this->settings['fonts_display_swap']
            : true;

        if (function_exists('suple_speed')) {
            $this->logger = suple_speed()->logger;
        }
        
        $this->init_hooks();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        if ($this->settings['fonts_local']) {
            // Capturar y procesar fuentes en el frontend
            add_action('wp_head', [$this, 'capture_google_fonts'], 1);
            add_action('wp_head', [$this, 'inject_local_fonts'], PHP_INT_MAX);
            
            // Filtrar URLs de Google Fonts
            add_filter('style_loader_src', [$this, 'filter_google_fonts_url'], 10, 2);
            
            // Procesar CSS para encontrar @import de fuentes
            add_filter('suple_speed_process_html', [$this, 'process_google_fonts_in_html']);
        }
        
        // AJAX para localización manual
        add_action('wp_ajax_suple_speed_localize_fonts', [$this, 'ajax_localize_fonts']);
        add_action('wp_ajax_suple_speed_scan_fonts', [$this, 'ajax_scan_fonts']);
    }
    
    /**
     * Capturar Google Fonts del HTML
     */
    public function capture_google_fonts() {
        if ($this->capturing_head) {
            return;
        }

        $this->capturing_head = true;
        ob_start();
    }

    /**
     * Inyectar fuentes locales
     */
    public function inject_local_fonts() {
        if (!$this->capturing_head) {
            return;
        }

        $buffer = ob_get_clean();
        $this->capturing_head = false;

        if (!empty($buffer)) {
            $buffer = $this->process_google_fonts_in_html($buffer);
        }

        echo $buffer;
    }
    
    /**
     * Filtrar URLs de Google Fonts
     */
    public function filter_google_fonts_url($src, $handle) {
        if (strpos($src, 'fonts.googleapis.com') !== false ||
            strpos($src, 'fonts.gstatic.com') !== false) {
            
            $local_font = $this->get_local_font_url($src);
            
            if ($local_font) {
                return $local_font;
            }
        }
        
        return $src;
    }
    
    /**
     * Procesar Google Fonts en HTML
     */
    public function process_google_fonts_in_html($html) {
        // Buscar enlaces a Google Fonts
        $html = preg_replace_callback(
            '/<link[^>]*href=["\']([^"\']*fonts\.googleapis\.com[^"\']*)["\'][^>]*>/i',
            [$this, 'replace_google_font_link'],
            $html
        );

        // Buscar @import en CSS inline
        $html = preg_replace_callback(
            '/@import\s+(?:url\()?["\']?([^"\'\)]*fonts\.googleapis\.com[^"\'\)]*)["\']?\)?;?/i',
            [$this, 'replace_google_font_import'],
            $html
        );

        if ($this->is_font_display_swap_enabled()) {
            $html = preg_replace_callback(
                '/(<style\b[^>]*>)(.*?)(<\/style>)/is',
                function($matches) {
                    $result = $this->enforce_font_display_swap($matches[2], 'inline_style');

                    if (!is_array($result) || empty($result['adjusted'])) {
                        return $matches[0];
                    }

                    return $matches[1] . $result['css'] . $matches[3];
                },
                $html
            );
        }

        return $html;
    }

    /**
     * Asegurar font-display: swap en CSS dado
     */
    public function enforce_font_display_swap($css, $context = 'assets') {
        if (!$this->is_font_display_swap_enabled() || empty($css) || !is_string($css)) {
            return ['css' => $css, 'adjusted' => 0];
        }

        $site_host = parse_url(home_url(), PHP_URL_HOST);

        if (empty($site_host)) {
            return ['css' => $css, 'adjusted' => 0];
        }

        $adjusted_blocks = 0;
        $processed_css = preg_replace_callback(
            '/@font-face\s*{[^{}]*}/i',
            function($matches) use (&$adjusted_blocks, $site_host) {
                $block = $matches[0];

                if (!$this->font_face_contains_local_source($block, $site_host)) {
                    return $block;
                }

                if (preg_match('/font-display\s*:/i', $block)) {
                    return $block;
                }

                $adjusted_blocks++;

                return $this->inject_font_display_swap($block);
            },
            $css
        );

        if ($processed_css === null) {
            $processed_css = $css;
        }

        if ($adjusted_blocks > 0 && $this->logger) {
            $this->logger->info('font-display: swap enforced on local fonts', [
                'context' => $context,
                'blocks_adjusted' => $adjusted_blocks
            ], 'fonts');
        }

        return [
            'css' => $processed_css,
            'adjusted' => $adjusted_blocks
        ];
    }

    /**
     * Verificar si font-display swap está habilitado
     */
    private function is_font_display_swap_enabled() {
        return !empty($this->settings['fonts_display_swap']);
    }

    /**
     * Verificar si un bloque @font-face usa fuentes locales
     */
    private function font_face_contains_local_source($font_face_block, $site_host) {
        if (!preg_match('/src\s*:\s*([^;]+);?/i', $font_face_block, $src_match)) {
            return false;
        }

        $src_value = $src_match[1];

        if (!preg_match_all('/url\(([^)]+)\)/i', $src_value, $url_matches)) {
            return false;
        }

        foreach ($url_matches[1] as $url_candidate) {
            $url_candidate = trim($url_candidate, "\"' \t\n\r\0\x0B");

            if ($url_candidate === '') {
                continue;
            }

            if ($this->is_local_font_url($url_candidate, $site_host)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determinar si la URL apunta al dominio actual
     */
    private function is_local_font_url($url, $site_host) {
        if (strpos($url, 'data:') === 0) {
            return false;
        }

        if (strpos($url, '//') === 0) {
            $parsed_host = parse_url('https:' . $url, PHP_URL_HOST);
            return !empty($parsed_host) && $parsed_host === $site_host;
        }

        if (preg_match('#^[a-z][a-z0-9+\-.]*:#i', $url)) {
            if (!preg_match('#^https?://#i', $url)) {
                return false;
            }

            $parsed_host = parse_url($url, PHP_URL_HOST);
            return !empty($parsed_host) && $parsed_host === $site_host;
        }

        // Sin esquema, se asume que es una ruta local
        return true;
    }

    /**
     * Insertar font-display: swap en un bloque @font-face
     */
    private function inject_font_display_swap($font_face_block) {
        if (preg_match('/;\s*}\s*$/', $font_face_block)) {
            $replacement = preg_replace('/;\s*}\s*$/', '; font-display: swap;}', $font_face_block, 1);
        } else {
            $replacement = preg_replace('/}\s*$/', '; font-display: swap;}', $font_face_block, 1);
        }

        return $replacement !== null ? $replacement : $font_face_block;
    }
    
    /**
     * Reemplazar enlace de Google Font
     */
    private function replace_google_font_link($matches) {
        $original_url = $matches[1];
        $local_font = $this->localize_google_font($original_url);
        
        if ($local_font) {
            $link_tag = $matches[0];
            $new_link = str_replace($original_url, $local_font['url'], $link_tag);
            
            // Añadir font-display: swap si no está presente
            if (strpos($new_link, 'font-display') === false) {
                $new_link = str_replace('>', ' style="font-display: swap;">', $new_link);
            }
            
            return $new_link;
        }
        
        return $matches[0];
    }
    
    /**
     * Reemplazar @import de Google Font
     */
    private function replace_google_font_import($matches) {
        $original_url = $matches[1];
        $local_font = $this->localize_google_font($original_url);
        
        if ($local_font) {
            return '@import url("' . $local_font['url'] . '");';
        }
        
        return $matches[0];
    }
    
    /**
     * Localizar Google Font
     */
    public function localize_google_font($google_url) {
        // Parsear URL de Google Fonts
        $font_data = $this->parse_google_fonts_url($google_url);
        
        if (!$font_data) {
            return false;
        }
        
        // Verificar si ya está localizada
        $cache_key = md5($google_url);
        $existing_font = $this->get_cached_font($cache_key);
        
        if ($existing_font && file_exists($existing_font['file'])) {
            return $existing_font;
        }
        
        // Descargar y procesar fuente
        return $this->download_and_process_font($font_data, $cache_key);
    }
    
    /**
     * Parsear URL de Google Fonts
     */
    private function parse_google_fonts_url($url) {
        $parsed_url = parse_url($url);
        
        if (!isset($parsed_url['query'])) {
            return false;
        }
        
        parse_str($parsed_url['query'], $params);
        
        $font_data = [
            'families' => [],
            'display' => $params['display'] ?? 'swap',
            'subset' => $params['subset'] ?? 'latin'
        ];
        
        // Parsear familias de fuentes
        if (isset($params['family'])) {
            if (is_array($params['family'])) {
                $families = $params['family'];
            } else {
                $families = explode('|', $params['family']);
            }
            
            foreach ($families as $family) {
                $font_info = $this->parse_font_family($family);
                if ($font_info) {
                    $font_data['families'][] = $font_info;
                }
            }
        }
        
        return $font_data;
    }
    
    /**
     * Parsear familia de fuente individual
     */
    private function parse_font_family($family_string) {
        // Formato: "Font Name:weight1,weight2,weight3:style"
        $parts = explode(':', $family_string);
        
        $font_info = [
            'name' => str_replace('+', ' ', $parts[0]),
            'weights' => ['400'], // Peso por defecto
            'styles' => ['normal'] // Estilo por defecto
        ];
        
        if (isset($parts[1])) {
            $weights_styles = explode(',', $parts[1]);
            $weights = [];
            $styles = [];
            
            foreach ($weights_styles as $ws) {
                if (strpos($ws, 'italic') !== false) {
                    $styles[] = 'italic';
                    $weight = str_replace('italic', '', $ws);
                    if (!empty($weight)) {
                        $weights[] = $weight;
                    }
                } else {
                    $weights[] = $ws;
                    $styles[] = 'normal';
                }
            }
            
            if (!empty($weights)) {
                $font_info['weights'] = array_unique($weights);
            }
            
            if (!empty($styles)) {
                $font_info['styles'] = array_unique($styles);
            }
        }
        
        return $font_info;
    }
    
    /**
     * Obtener fuente cacheada
     */
    private function get_cached_font($cache_key) {
        $font_data = get_transient('suple_speed_font_' . $cache_key);
        
        if ($font_data && is_array($font_data)) {
            return $font_data;
        }
        
        return false;
    }
    
    /**
     * Descargar y procesar fuente
     */
    private function download_and_process_font($font_data, $cache_key) {
        // Crear directorio si no existe
        if (!file_exists($this->fonts_dir)) {
            wp_mkdir_p($this->fonts_dir);
        }
        
        $css_content = '';
        $downloaded_files = [];
        
        foreach ($font_data['families'] as $family) {
            $family_css = $this->download_font_family($family, $font_data);
            
            if ($family_css) {
                $css_content .= $family_css['css'] . "\n\n";
                $downloaded_files = array_merge($downloaded_files, $family_css['files']);
            }
        }
        
        if (!empty($css_content)) {
            // Guardar archivo CSS local
            $css_filename = 'google-fonts-' . $cache_key . '.css';
            $css_file = $this->fonts_dir . $css_filename;
            $css_url = $this->fonts_url . $css_filename;
            
            file_put_contents($css_file, $css_content);
            
            // Preparar datos para cache
            $local_font_data = [
                'url' => $css_url,
                'file' => $css_file,
                'files' => $downloaded_files,
                'created' => time(),
                'families' => array_column($font_data['families'], 'name')
            ];
            
            // Cachear información por 30 días
            set_transient('suple_speed_font_' . $cache_key, $local_font_data, 30 * DAY_IN_SECONDS);
            
            // Log
            if ($this->logger) {
                $this->logger->info('Google Fonts localized successfully', [
                    'families' => count($font_data['families']),
                    'files_downloaded' => count($downloaded_files),
                    'css_file' => $css_filename
                ], 'fonts');
            }
            
            return $local_font_data;
        }
        
        return false;
    }
    
    /**
     * Descargar familia de fuente específica
     */
    private function download_font_family($family, $font_data) {
        $css_parts = [];
        $downloaded_files = [];
        
        foreach ($family['weights'] as $weight) {
            foreach ($family['styles'] as $style) {
                $font_face = $this->download_font_variant(
                    $family['name'],
                    $weight,
                    $style,
                    $font_data['subset']
                );
                
                if ($font_face) {
                    $css_parts[] = $font_face['css'];
                    if (isset($font_face['file'])) {
                        $downloaded_files[] = $font_face['file'];
                    }
                }
            }
        }
        
        if (!empty($css_parts)) {
            return [
                'css' => implode("\n", $css_parts),
                'files' => $downloaded_files
            ];
        }
        
        return false;
    }
    
    /**
     * Descargar variante específica de fuente
     */
    private function download_font_variant($font_name, $weight, $style, $subset) {
        // Construir URL de Google Fonts para esta variante específica
        $google_url = $this->build_google_fonts_url($font_name, $weight, $style, $subset);
        
        // Descargar CSS de Google
        $css_content = $this->fetch_google_fonts_css($google_url);
        
        if (!$css_content) {
            return false;
        }
        
        // Extraer URLs de archivos de fuente del CSS
        $font_files = $this->extract_font_urls_from_css($css_content);
        
        // Descargar archivos de fuente
        $local_font_files = [];
        foreach ($font_files as $font_url) {
            $local_file = $this->download_font_file($font_url, $font_name, $weight, $style);
            if ($local_file) {
                $local_font_files[$font_url] = $local_file;
            }
        }
        
        // Reemplazar URLs en CSS
        $local_css = $css_content;
        foreach ($local_font_files as $original_url => $local_file) {
            $local_css = str_replace($original_url, $local_file['url'], $local_css);
        }
        
        // Añadir font-display: swap
        $local_css = preg_replace('/font-display:\s*[^;]+;?/', '', $local_css);
        $local_css = str_replace('{', "{\n  font-display: swap;", $local_css);
        
        return [
            'css' => $local_css,
            'files' => array_values($local_font_files)
        ];
    }
    
    /**
     * Construir URL de Google Fonts
     */
    private function build_google_fonts_url($font_name, $weight, $style, $subset) {
        $family = str_replace(' ', '+', $font_name);
        
        if ($weight !== '400' || $style !== 'normal') {
            $family .= ':';
            
            if ($style === 'italic' && $weight === '400') {
                $family .= 'italic';
            } elseif ($style === 'italic') {
                $family .= $weight . 'italic';
            } else {
                $family .= $weight;
            }
        }
        
        $params = [
            'family' => $family,
            'subset' => $subset,
            'display' => 'swap'
        ];
        
        return 'https://fonts.googleapis.com/css?' . http_build_query($params);
    }
    
    /**
     * Obtener CSS de Google Fonts
     */
    private function fetch_google_fonts_css($url) {
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            return wp_remote_retrieve_body($response);
        }
        
        return false;
    }
    
    /**
     * Extraer URLs de fuentes del CSS
     */
    private function extract_font_urls_from_css($css) {
        $urls = [];
        
        if (preg_match_all('/url\(([^)]+)\)/i', $css, $matches)) {
            foreach ($matches[1] as $url) {
                $url = trim($url, '\'"');
                if (strpos($url, 'fonts.gstatic.com') !== false) {
                    $urls[] = $url;
                }
            }
        }
        
        return array_unique($urls);
    }
    
    /**
     * Descargar archivo de fuente
     */
    private function download_font_file($font_url, $font_name, $weight, $style) {
        // Generar nombre de archivo local
        $extension = pathinfo(parse_url($font_url, PHP_URL_PATH), PATHINFO_EXTENSION);
        if (empty($extension)) {
            $extension = 'woff2'; // Formato por defecto
        }
        
        $filename = sanitize_file_name(
            strtolower($font_name . '-' . $weight . '-' . $style . '.' . $extension)
        );
        
        $local_file = $this->fonts_dir . $filename;
        $local_url = $this->fonts_url . $filename;
        
        // Verificar si ya existe
        if (file_exists($local_file)) {
            return [
                'file' => $local_file,
                'url' => $local_url,
                'size' => filesize($local_file)
            ];
        }
        
        // Descargar archivo
        $response = wp_remote_get($font_url, [
            'timeout' => 60,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $font_data = wp_remote_retrieve_body($response);
            $bytes_written = file_put_contents($local_file, $font_data);
            
            if ($bytes_written !== false) {
                return [
                    'file' => $local_file,
                    'url' => $local_url,
                    'size' => $bytes_written
                ];
            }
        }
        
        return false;
    }
    
    /**
     * Obtener URL de fuente local
     */
    private function get_local_font_url($google_url) {
        $cache_key = md5($google_url);
        $cached_font = $this->get_cached_font($cache_key);
        
        if ($cached_font && file_exists($cached_font['file'])) {
            return $cached_font['url'];
        }
        
        return false;
    }
    
    /**
     * Generar preloads para fuentes
     */
    public function get_font_preloads() {
        $preloads = [];
        
        // Preloads configurados manualmente
        $manual_preloads = $this->settings['fonts_preload'] ?? [];
        
        foreach ($manual_preloads as $preload) {
            $preloads[] = [
                'rel' => 'preload',
                'href' => $preload['url'],
                'as' => 'font',
                'type' => $preload['type'] ?? 'font/woff2',
                'crossorigin' => 'anonymous'
            ];
        }
        
        // Auto-detectar fuentes críticas
        $critical_fonts = $this->detect_critical_fonts();
        
        foreach ($critical_fonts as $font) {
            $preloads[] = [
                'rel' => 'preload',
                'href' => $font['url'],
                'as' => 'font',
                'type' => $font['type'],
                'crossorigin' => 'anonymous'
            ];
        }
        
        return $preloads;
    }
    
    /**
     * Detectar fuentes críticas automáticamente
     */
    private function detect_critical_fonts() {
        // Implementación básica para detectar fuentes en el LCP o en elementos hero
        // Esto es un ejemplo y debería ser expandido.
        $critical_fonts = [];
        $lcp_element_font = get_option('suple_speed_lcp_font_url'); // Asumimos que guardas la fuente del LCP

        if ($lcp_element_font) {
            $critical_fonts[] = [
                'rel' => 'preload',
                'href' => $lcp_element_font,
                'as' => 'font',
                'type' => 'font/woff2',
                'crossorigin' => 'anonymous'
            ];
        }
        
        return $critical_fonts;
    }
    
    /**
     * Escanear fuentes de Google en el sitio
     */
    public function scan_google_fonts() {
        global $wpdb;
        
        $fonts_found = [];
        
        // Buscar en opciones del tema
        $theme_mods = get_theme_mods();
        foreach ($theme_mods as $key => $value) {
            if (is_string($value) && strpos($value, 'fonts.googleapis.com') !== false) {
                $fonts_found[] = [
                    'source' => 'theme_customizer',
                    'location' => $key,
                    'url' => $value
                ];
            }
        }
        
        // Buscar en posts/páginas
        $posts_with_fonts = $wpdb->get_results("
            SELECT ID, post_title, post_content, post_excerpt 
            FROM {$wpdb->posts} 
            WHERE (post_content LIKE '%fonts.googleapis.com%' 
                   OR post_excerpt LIKE '%fonts.googleapis.com%')
            AND post_status = 'publish'
        ");
        
        foreach ($posts_with_fonts as $post) {
            $content = $post->post_content . ' ' . $post->post_excerpt;
            
            if (preg_match_all('/fonts\.googleapis\.com[^\s"\'<>]*/i', $content, $matches)) {
                foreach ($matches[0] as $font_url) {
                    $fonts_found[] = [
                        'source' => 'post_content',
                        'location' => 'Post: ' . $post->post_title . ' (ID: ' . $post->ID . ')',
                        'url' => 'https://' . $font_url
                    ];
                }
            }
        }
        
        // Buscar en widgets
        $widgets = $wpdb->get_results("SELECT * FROM {$wpdb->options} WHERE option_name LIKE 'widget_%'");
        
        foreach ($widgets as $widget) {
            $widget_data = maybe_unserialize($widget->option_value);
            
            if (is_array($widget_data)) {
                $widget_content = serialize($widget_data);
                
                if (strpos($widget_content, 'fonts.googleapis.com') !== false) {
                    if (preg_match_all('/fonts\.googleapis\.com[^\s"\'<>]*/i', $widget_content, $matches)) {
                        foreach ($matches[0] as $font_url) {
                            $fonts_found[] = [
                                'source' => 'widget',
                                'location' => $widget->option_name,
                                'url' => 'https://' . $font_url
                            ];
                        }
                    }
                }
            }
        }
        
        // Eliminar duplicados
        $unique_fonts = [];
        foreach ($fonts_found as $font) {
            $key = md5($font['url']);
            if (!isset($unique_fonts[$key])) {
                $unique_fonts[$key] = $font;
            }
        }
        
        return array_values($unique_fonts);
    }
    
    /**
     * Obtener estadísticas de fuentes
     */
    public function get_fonts_stats() {
        $stats = [
            'total_localized' => 0,
            'total_size' => 0,
            'families' => [],
            'files' => []
        ];
        
        if (!is_dir($this->fonts_dir)) {
            return $stats;
        }
        
        $files = glob($this->fonts_dir . '*');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $stats['total_size'] += filesize($file);
                $stats['files'][] = [
                    'name' => basename($file),
                    'size' => filesize($file),
                    'modified' => filemtime($file)
                ];
            }
        }
        
        // Contar fuentes localizadas desde transients
        global $wpdb;
        $font_transients = $wpdb->get_results(
            "SELECT option_name, option_value 
             FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_suple_speed_font_%'"
        );
        
        foreach ($font_transients as $transient) {
            $font_data = maybe_unserialize($transient->option_value);
            if (isset($font_data['families'])) {
                $stats['families'] = array_merge($stats['families'], $font_data['families']);
                $stats['total_localized']++;
            }
        }
        
        $stats['families'] = array_unique($stats['families']);
        $stats['total_size_formatted'] = size_format($stats['total_size']);
        
        return $stats;
    }
    
    // === AJAX ===
    
    /**
     * AJAX: Localizar fuentes manualmente
     */
    public function ajax_localize_fonts() {
        check_ajax_referer('suple_speed_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $font_urls = $_POST['font_urls'] ?? [];
        
        if (!is_array($font_urls)) {
            wp_send_json_error('Invalid font URLs provided');
        }
        
        $results = [];
        
        foreach ($font_urls as $url) {
            $url = sanitize_url($url);
            
            if (strpos($url, 'fonts.googleapis.com') !== false) {
                $local_font = $this->localize_google_font($url);
                
                $results[] = [
                    'original_url' => $url,
                    'success' => $local_font !== false,
                    'local_url' => $local_font ? $local_font['url'] : null,
                    'message' => $local_font ? 'Font localized successfully' : 'Failed to localize font'
                ];
            } else {
                $results[] = [
                    'original_url' => $url,
                    'success' => false,
                    'message' => 'Not a Google Fonts URL'
                ];
            }
        }
        
        wp_send_json_success($results);
    }
    
    /**
     * AJAX: Escanear fuentes de Google
     */
    public function ajax_scan_fonts() {
        check_ajax_referer('suple_speed_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $fonts_found = $this->scan_google_fonts();
        $fonts_stats = $this->get_fonts_stats();
        
        wp_send_json_success([
            'fonts_found' => $fonts_found,
            'stats' => $fonts_stats
        ]);
    }
    
    /**
     * Limpiar fuentes no utilizadas
     */
    public function cleanup_unused_fonts() {
        if (!is_dir($this->fonts_dir)) {
            return 0;
        }
        
        $cleaned_files = 0;
        $cutoff_time = time() - (30 * DAY_IN_SECONDS); // 30 días
        
        $files = glob($this->fonts_dir . '*');
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff_time) {
                unlink($file);
                $cleaned_files++;
            }
        }
        
        // Limpiar transients expirados
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_suple_speed_font_%' AND option_value < UNIX_TIMESTAMP()");
        
        return $cleaned_files;
    }
}
