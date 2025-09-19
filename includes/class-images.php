<?php

namespace SupleSpeed;

/**
 * Optimización de imágenes (lazy loading, WebP, LQIP)
 */
class Images {
    
    /**
     * Configuración
     */
    private $settings;
    private $logger;
    private $critical_images_cache = null;
    private $critical_image_index = null;
    
    /**
     * Soporte de formatos
     */
    private $webp_support = false;
    private $avif_support = false;
    
    public function __construct() {
        $this->settings = get_option('suple_speed_settings', []);
        
        if (function_exists('suple_speed')) {
            $this->logger = suple_speed()->logger;
        }
        
        $this->detect_format_support();
        $this->init_hooks();
    }
    
    /**
     * Detectar soporte de formatos
     */
    private function detect_format_support() {
        // Detectar soporte WebP
        $this->webp_support = $this->check_webp_support();
        
        // Detectar soporte AVIF
        $this->avif_support = $this->check_avif_support();
    }
    
    /**
     * Verificar soporte WebP
     */
    private function check_webp_support() {
        // Verificar por integración con plugins
        if (function_exists('suple_speed') && suple_speed()->compat) {
            $webp_enabled = apply_filters('suple_speed_webp_enabled', false);
            if ($webp_enabled) {
                return true;
            }
        }
        
        // Verificar soporte del servidor
        if (function_exists('imagewebp')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Verificar soporte AVIF
     */
    private function check_avif_support() {
        // AVIF requiere PHP 8.1+ y extensión GD con soporte
        if (version_compare(PHP_VERSION, '8.1.0', '>=') && function_exists('imageavif')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        if (!is_admin() && $this->settings['images_lazy']) {
            // Lazy loading (respetando el nativo de WordPress)
            add_filter('wp_get_attachment_image_attributes', [$this, 'add_lazy_loading'], 10, 3);
            add_filter('the_content', [$this, 'add_lazy_loading_to_content']);
            add_filter('post_thumbnail_html', [$this, 'add_lazy_loading_to_thumbnails']);
            
            // Procesar HTML completo para lazy loading
            add_filter('suple_speed_process_html', [$this, 'process_images_in_html']);
        }
        
        // WebP/AVIF rewriting si está habilitado
        if ($this->settings['images_webp_rewrite']) {
            add_filter('suple_speed_process_html', [$this, 'rewrite_images_to_webp']);
        }
        
        // LQIP (Low Quality Image Placeholders)
        if ($this->settings['images_lqip']) {
            add_filter('suple_speed_process_html', [$this, 'add_lqip_placeholders']);
        }
        
        // Preload de imágenes críticas
        add_action('wp_head', [$this, 'preload_critical_images'], 2);
    }
    
    /**
     * Añadir lazy loading a atributos de imagen
     */
    public function add_lazy_loading($attr, $attachment, $size) {
        $priority = $this->get_priority_for_attachment($attachment, $attr);

        if ($priority) {
            // Asegurar que no se aplique lazy loading y añadir fetchpriority
            $attr['loading'] = 'eager';

            if ($priority === 'high') {
                $attr['fetchpriority'] = 'high';
            }

            return $attr;
        }

        // Solo añadir si no está ya presente
        if (!isset($attr['loading'])) {
            $attr['loading'] = 'lazy';
        }

        return $attr;
    }
    
    /**
     * Añadir lazy loading al contenido
     */
    public function add_lazy_loading_to_content($content) {
        if (empty($content)) {
            return $content;
        }

        return preg_replace_callback(
            '/<img([^>]+)>/i',
            [$this, 'add_lazy_loading_to_img_tag'],
            $content
        );
    }
    
    /**
     * Añadir lazy loading a thumbnails
     */
    public function add_lazy_loading_to_thumbnails($html) {
        if (empty($html)) {
            return $html;
        }

        return preg_replace_callback(
            '/<img([^>]+)>/i',
            [$this, 'add_lazy_loading_to_img_tag'],
            $html
        );
    }
    
    /**
     * Añadir lazy loading a tag img específico
     */
    private function add_lazy_loading_to_img_tag($matches) {
        $img_tag = $matches[0];
        $attributes = $matches[1];
        $entry = $this->get_priority_for_attribute_string($attributes);

        $is_self_closing = substr(trim($img_tag), -2) === '/>';

        if ($is_self_closing) {
            $attributes = rtrim($attributes);
            if (substr($attributes, -1) === '/') {
                $attributes = rtrim(substr($attributes, 0, -1));
            }
        }

        $closing = $is_self_closing ? ' />' : '>';

        if ($entry) {
            $attributes = $this->remove_attribute_from_string($attributes, 'loading');
            $attributes = $this->set_attribute_in_string($attributes, 'loading', 'eager');

            if (($entry['priority'] ?? '') === 'high') {
                $attributes = $this->set_attribute_in_string($attributes, 'fetchpriority', 'high');
            }

            return '<img' . rtrim($attributes) . $closing;
        }

        if (stripos($attributes, 'loading=') !== false) {
            return $img_tag;
        }

        $attributes .= ' loading="lazy"';

        return '<img' . $attributes . $closing;
    }
    
    /**
     * Verificar si es imagen crítica
     */
    private function is_critical_image($attachment, $attributes) {
        return (bool) $this->get_priority_for_attachment($attachment, $attributes);
    }

    /**
     * Verificar si es imagen crítica por atributos
     */
    private function is_critical_image_by_attributes($attributes) {
        return (bool) $this->get_priority_for_attribute_string($attributes);
    }

    private function get_priority_for_attachment($attachment, $attributes) {
        $entry = $this->locate_critical_image_entry(
            $this->resolve_attachment_id($attachment),
            $this->extract_sources_from_array($attributes)
        );

        return $entry['priority'] ?? null;
    }

    private function get_priority_for_attribute_string($attributes) {
        $attribute_array = $this->parse_attribute_string($attributes);
        $entry = $this->locate_critical_image_entry(
            $this->extract_attachment_id_from_attributes($attribute_array),
            $this->extract_sources_from_array($attribute_array)
        );

        return $entry;
    }

    private function locate_critical_image_entry($attachment_id, array $sources = []) {
        $this->ensure_critical_images_index();

        if ($attachment_id && isset($this->critical_image_index['ids'][$attachment_id])) {
            return $this->critical_image_index['ids'][$attachment_id];
        }

        foreach ($sources as $source) {
            $normalized = $this->normalize_image_url($source);
            if ($normalized && isset($this->critical_image_index['urls'][$normalized])) {
                return $this->critical_image_index['urls'][$normalized];
            }
        }

        return null;
    }

    private function resolve_attachment_id($attachment) {
        if (is_numeric($attachment)) {
            return (int) $attachment;
        }

        if (is_object($attachment) && isset($attachment->ID)) {
            return (int) $attachment->ID;
        }

        return 0;
    }

    private function extract_sources_from_array($attributes) {
        if (!is_array($attributes)) {
            return [];
        }

        $sources = [];

        foreach (['src', 'data-src', 'data-lazy-src', 'data-original', 'data-srcset', 'data-image', 'data-large_image', 'data-large-image'] as $key) {
            if (!empty($attributes[$key])) {
                $sources[] = $attributes[$key];
            }
        }

        if (!empty($attributes['srcset'])) {
            foreach (explode(',', $attributes['srcset']) as $srcset_item) {
                $parts = preg_split('/\s+/', trim($srcset_item));
                if (!empty($parts[0])) {
                    $sources[] = $parts[0];
                }
            }
        }

        return $sources;
    }

    private function parse_attribute_string($attributes) {
        $parsed = [];

        if (preg_match_all("/([a-zA-Z0-9_:\\-]+)\s*=\s*(\"|')(.*?)\\2/", $attributes, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $parsed[strtolower($match[1])] = html_entity_decode($match[3], ENT_QUOTES);
            }
        }

        return $parsed;
    }

    private function extract_attachment_id_from_attributes(array $attributes) {
        $candidates = ['data-id', 'data-image-id', 'data-attachment-id', 'data-elementor-id'];

        foreach ($candidates as $candidate) {
            if (!empty($attributes[$candidate]) && is_numeric($attributes[$candidate])) {
                return (int) $attributes[$candidate];
            }
        }

        if (!empty($attributes['class']) && preg_match('/wp-image-(\d+)/', $attributes['class'], $match)) {
            return (int) $match[1];
        }

        if (!empty($attributes['id']) && preg_match('/(\d+)/', $attributes['id'], $match)) {
            return (int) $match[1];
        }

        return 0;
    }

    private function remove_attribute_from_string($attributes, $attribute) {
        return preg_replace("/\s+" . preg_quote($attribute, "/") . "\s*=\s*(\"|')[^\"']*\\1/i", '', $attributes);
    }

    private function set_attribute_in_string($attributes, $attribute, $value) {
        $pattern = "/(" . preg_quote($attribute, "/") . "\s*=\s*)(\"|')[^\"']*\\2/i";
        $replacement = '$1$2' . esc_attr($value) . '$2';

        if (preg_match($pattern, $attributes)) {
            return preg_replace($pattern, $replacement, $attributes, 1);
        }

        return rtrim($attributes) . ' ' . $attribute . '="' . esc_attr($value) . '"';
    }

    private function normalize_image_url($url) {
        if (empty($url) || !is_string($url)) {
            return '';
        }

        $url = trim($url);
        $parsed = wp_parse_url($url);

        if (!$parsed) {
            return $url;
        }

        $normalized = ($parsed['host'] ?? '') . ($parsed['path'] ?? '');

        if (!empty($parsed['query'])) {
            $normalized .= '?' . $parsed['query'];
        }

        return strtolower($normalized);
    }

    private function ensure_critical_images_index() {
        if ($this->critical_image_index === null) {
            $this->get_critical_images();
        }
    }

    private function build_critical_image_index(array $images) {
        $index = [
            'ids' => [],
            'urls' => []
        ];

        foreach ($images as $image) {
            if (!empty($image['id'])) {
                $index['ids'][(int) $image['id']] = $image;
            }

            if (!empty($image['url'])) {
                $normalized = $this->normalize_image_url($image['url']);
                if ($normalized) {
                    $index['urls'][$normalized] = $image;
                }
            }
        }

        return $index;
    }

    private function get_manual_critical_images() {
        $entries = [];
        $manual = $this->settings['images_critical_manual'] ?? '';

        if (empty($manual)) {
            return $entries;
        }

        if (is_array($manual)) {
            $manual = implode("\n", $manual);
        }

        $lines = preg_split('/[\r\n]+/', $manual);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (preg_match('/wp-image-(\d+)/', $line, $match)) {
                $line = $match[1];
            }

            if (is_numeric($line)) {
                $id = (int) $line;
                $image = wp_get_attachment_image_src($id, 'full');
                if ($image) {
                    $entries[] = [
                        'id' => $id,
                        'url' => $image[0],
                        'width' => $image[1] ?? null,
                        'height' => $image[2] ?? null,
                        'priority' => 'high',
                        'manual' => true
                    ];
                }

                continue;
            }

            if (filter_var($line, FILTER_VALIDATE_URL)) {
                $entries[] = [
                    'url' => $line,
                    'priority' => 'high',
                    'manual' => true
                ];

                continue;
            }

            $uploads = wp_get_upload_dir();
            if (!empty($uploads['baseurl'])) {
                $possible_url = trailingslashit($uploads['baseurl']) . ltrim($line, '/');

                $entries[] = [
                    'url' => $possible_url,
                    'priority' => 'high',
                    'manual' => true
                ];
            }
        }

        return $entries;
    }

    private function detect_elementor_primary_image($post_id) {
        $data = get_post_meta($post_id, '_elementor_data', true);

        if (empty($data)) {
            return null;
        }

        $decoded = is_string($data) ? json_decode($data, true) : $data;

        if (!is_array($decoded)) {
            return null;
        }

        $node = $this->find_first_elementor_image($decoded);

        if (!$node) {
            return null;
        }

        $image_settings = $node['settings']['image'] ?? [];
        $id = isset($image_settings['id']) ? (int) $image_settings['id'] : 0;
        $url = $image_settings['url'] ?? '';

        if ($id && empty($url)) {
            $image = wp_get_attachment_image_src($id, 'full');
            if ($image) {
                $url = $image[0];
            }
        }

        if (empty($url) && isset($node['settings']['image_url'])) {
            $url = $node['settings']['image_url'];
        }

        if (empty($url)) {
            return null;
        }

        $entry = [
            'url' => $url,
            'priority' => 'high',
            'context' => 'elementor'
        ];

        if ($id) {
            $entry['id'] = $id;
        }

        return $entry;
    }

    private function find_first_elementor_image(array $nodes) {
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }

            if (($node['elType'] ?? '') === 'widget' && ($node['widgetType'] ?? '') === 'image') {
                if (!empty($node['settings']['image']['url']) || !empty($node['settings']['image']['id'])) {
                    return $node;
                }
            }

            if (!empty($node['elements']) && is_array($node['elements'])) {
                $found = $this->find_first_elementor_image($node['elements']);
                if ($found) {
                    return $found;
                }
            }
        }

        return null;
    }

    private function detect_content_primary_image($post_id) {
        $post = get_post($post_id);

        if (!$post) {
            return null;
        }

        $content = $post->post_content;

        if (empty($content)) {
            return null;
        }

        if (!preg_match_all('/<img[^>]+>/i', $content, $matches)) {
            return null;
        }

        foreach ($matches[0] as $img_html) {
            $attributes = $this->parse_attribute_string($img_html);
            $width = isset($attributes['width']) ? (int) $attributes['width'] : 0;
            $classes = $attributes['class'] ?? '';

            $is_large = $width >= 640 || strpos($classes, 'size-full') !== false || strpos($classes, 'wp-image') !== false;

            if (!$is_large && !empty($attributes['srcset'])) {
                $is_large = true;
            }

            if (!$is_large) {
                continue;
            }

            $id = $this->extract_attachment_id_from_attributes($attributes);
            $sources = $this->extract_sources_from_array($attributes);
            $url = reset($sources);

            if ($id) {
                $full = wp_get_attachment_image_src($id, 'full');
                if ($full) {
                    $url = $full[0];
                }
            }

            if (!$url) {
                continue;
            }

            $entry = [
                'url' => $url,
                'priority' => 'high',
                'context' => 'content-image'
            ];

            if ($id) {
                $entry['id'] = $id;
            }

            return $entry;
        }

        return null;
    }
    
    /**
     * Procesar imágenes en HTML completo
     */
    public function process_images_in_html($html) {
        // Procesar todas las imágenes
        $html = preg_replace_callback(
            '/<img([^>]+)>/i',
            [$this, 'process_single_image'],
            $html
        );
        
        return $html;
    }
    
    /**
     * Procesar imagen individual
     */
    private function process_single_image($matches) {
        $img_tag = $matches[0];
        $attributes = $matches[1];
        
        // Parsear atributos
        $parsed_attrs = $this->parse_img_attributes($attributes);
        
        // Aplicar lazy loading si no está presente
        if (!isset($parsed_attrs['loading']) && 
            !$this->is_critical_image_by_attributes($attributes)) {
            $parsed_attrs['loading'] = 'lazy';
        }
        
        // Añadir dimensiones si faltan y son detectables
        if (!isset($parsed_attrs['width']) || !isset($parsed_attrs['height'])) {
            $dimensions = $this->detect_image_dimensions($parsed_attrs['src'] ?? '');
            if ($dimensions) {
                $parsed_attrs['width'] = $parsed_attrs['width'] ?? $dimensions['width'];
                $parsed_attrs['height'] = $parsed_attrs['height'] ?? $dimensions['height'];
            }
        }
        
        // Reconstruir tag
        return $this->build_img_tag($parsed_attrs);
    }
    
    /**
     * Parsear atributos de imagen
     */
    private function parse_img_attributes($attributes_string) {
        $attributes = [];
        
        if (preg_match_all('/(\w+)=(["\'])([^"\']*)\2/i', $attributes_string, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attributes[$match[1]] = $match[3];
            }
        }
        
        return $attributes;
    }
    
    /**
     * Construir tag img desde atributos
     */
    private function build_img_tag($attributes) {
        $attr_strings = [];
        
        foreach ($attributes as $name => $value) {
            $attr_strings[] = $name . '="' . esc_attr($value) . '"';
        }
        
        return '<img ' . implode(' ', $attr_strings) . '>';
    }
    
    /**
     * Detectar dimensiones de imagen
     */
    private function detect_image_dimensions($src) {
        if (empty($src)) {
            return false;
        }
        
        // Intentar obtener desde WordPress si es attachment
        $attachment_id = attachment_url_to_postid($src);
        if ($attachment_id) {
            $metadata = wp_get_attachment_metadata($attachment_id);
            if (isset($metadata['width']) && isset($metadata['height'])) {
                return [
                    'width' => $metadata['width'],
                    'height' => $metadata['height']
                ];
            }
        }
        
        // Intentar getimagesize para archivos locales
        $parsed_url = parse_url($src);
        $site_url = parse_url(home_url());
        
        if (isset($parsed_url['host']) && $parsed_url['host'] === $site_url['host']) {
            $file_path = ABSPATH . ltrim($parsed_url['path'], '/');
            
            if (file_exists($file_path)) {
                $size = getimagesize($file_path);
                if ($size !== false) {
                    return [
                        'width' => $size[0],
                        'height' => $size[1]
                    ];
                }
            }
        }
        
        return false;
    }
    
    /**
     * Reescribir imágenes a WebP/AVIF
     */
    public function rewrite_images_to_webp($html) {
        if (!$this->webp_support && !$this->avif_support) {
            return $html;
        }
        
        return preg_replace_callback(
            '/<img([^>]+)>/i',
            [$this, 'convert_image_to_modern_format'],
            $html
        );
    }
    
    /**
     * Convertir imagen a formato moderno
     */
    private function convert_image_to_modern_format($matches) {
        $img_tag = $matches[0];
        $attributes = $this->parse_img_attributes($matches[1]);
        
        if (!isset($attributes['src'])) {
            return $img_tag;
        }
        
        $src = $attributes['src'];
        
        // Verificar si es imagen local
        if (!$this->is_local_image($src)) {
            return $img_tag;
        }
        
        // Obtener versiones en formatos modernos
        $modern_formats = $this->get_modern_format_urls($src);
        
        if (empty($modern_formats)) {
            return $img_tag;
        }
        
        // Crear picture element con fallback
        return $this->create_picture_element($modern_formats, $attributes);
    }
    
    /**
     * Verificar si es imagen local
     */
    private function is_local_image($src) {
        $parsed_url = parse_url($src);
        $site_url = parse_url(home_url());
        
        return isset($parsed_url['host']) && $parsed_url['host'] === $site_url['host'];
    }
    
    /**
     * Obtener URLs en formatos modernos
     */
    private function get_modern_format_urls($original_src) {
        $formats = [];
        
        // Verificar si existen versiones WebP/AVIF
        $file_info = pathinfo($original_src);
        $base_url = $file_info['dirname'] . '/' . $file_info['filename'];
        
        if ($this->avif_support) {
            $avif_url = $base_url . '.avif';
            if ($this->url_exists($avif_url)) {
                $formats['avif'] = $avif_url;
            }
        }
        
        if ($this->webp_support) {
            $webp_url = $base_url . '.webp';
            if ($this->url_exists($webp_url)) {
                $formats['webp'] = $webp_url;
            }
        }
        
        $formats['original'] = $original_src;
        
        return $formats;
    }
    
    /**
     * Verificar si URL existe
     */
    private function url_exists($url) {
        // Para imágenes locales, convertir a path y verificar
        $parsed_url = parse_url($url);
        $site_url = parse_url(home_url());
        
        if (isset($parsed_url['host']) && $parsed_url['host'] === $site_url['host']) {
            $file_path = ABSPATH . ltrim($parsed_url['path'], '/');
            return file_exists($file_path);
        }
        
        return false;
    }
    
    /**
     * Crear elemento picture con fallback
     */
    private function create_picture_element($formats, $img_attributes) {
        $picture_html = '<picture>';
        
        // Añadir source para AVIF
        if (isset($formats['avif'])) {
            $picture_html .= '<source srcset="' . esc_attr($formats['avif']) . '" type="image/avif">';
        }
        
        // Añadir source para WebP
        if (isset($formats['webp'])) {
            $picture_html .= '<source srcset="' . esc_attr($formats['webp']) . '" type="image/webp">';
        }
        
        // Imagen original como fallback
        $img_attributes['src'] = $formats['original'];
        $picture_html .= $this->build_img_tag($img_attributes);
        
        $picture_html .= '</picture>';
        
        return $picture_html;
    }
    
    /**
     * Añadir placeholders LQIP
     */
    public function add_lqip_placeholders($html) {
        if (!$this->settings['images_lqip']) {
            return $html;
        }
        
        return preg_replace_callback(
            '/<img([^>]+)>/i',
            [$this, 'add_lqip_to_image'],
            $html
        );
    }
    
    /**
     * Añadir LQIP a imagen específica
     */
    private function add_lqip_to_image($matches) {
        $img_tag = $matches[0];
        $attributes = $this->parse_img_attributes($matches[1]);
        
        if (!isset($attributes['src'])) {
            return $img_tag;
        }
        
        // Solo para imágenes con lazy loading
        if (!isset($attributes['loading']) || $attributes['loading'] !== 'lazy') {
            return $img_tag;
        }
        
        // Generar LQIP
        $lqip = $this->generate_lqip($attributes['src']);
        
        if ($lqip) {
            // Mover src original a data-src y usar LQIP como src
            $attributes['data-src'] = $attributes['src'];
            $attributes['src'] = $lqip;
            $attributes['class'] = ($attributes['class'] ?? '') . ' suple-speed-lqip';
        }
        
        return $this->build_img_tag($attributes);
    }
    
    /**
     * Generar LQIP (Low Quality Image Placeholder)
     */
    private function generate_lqip($src) {
        // Cache key para LQIP
        $cache_key = 'lqip_' . md5($src);
        $cached_lqip = get_transient('suple_speed_' . $cache_key);
        
        if ($cached_lqip !== false) {
            return $cached_lqip;
        }
        
        $lqip = false;
        
        // Verificar si es imagen local
        if ($this->is_local_image($src)) {
            $file_path = $this->url_to_path($src);
            
            if ($file_path && file_exists($file_path)) {
                $lqip = $this->create_lqip_data_url($file_path);
            }
        }
        
        // Cachear resultado por 24 horas
        if ($lqip) {
            set_transient('suple_speed_' . $cache_key, $lqip, DAY_IN_SECONDS);
        }
        
        return $lqip;
    }
    
    /**
     * Convertir URL a path del sistema
     */
    private function url_to_path($url) {
        $parsed_url = parse_url($url);
        
        if (isset($parsed_url['path'])) {
            return ABSPATH . ltrim($parsed_url['path'], '/');
        }
        
        return false;
    }
    
    /**
     * Crear data URL para LQIP
     */
    private function create_lqip_data_url($file_path) {
        // Crear versión muy pequeña de la imagen
        $image_info = getimagesize($file_path);
        
        if ($image_info === false) {
            return false;
        }
        
        $mime_type = $image_info['mime'];
        
        // Crear imagen pequeña (10px de ancho máximo)
        $small_image = $this->create_small_image($file_path, 10);
        
        if ($small_image === false) {
            return false;
        }
        
        // Convertir a data URL
        ob_start();
        switch ($mime_type) {
            case 'image/jpeg':
                imagejpeg($small_image, null, 20); // Baja calidad
                break;
            case 'image/png':
                imagepng($small_image, null, 9); // Máxima compresión
                break;
            case 'image/gif':
                imagegif($small_image);
                break;
            default:
                imagejpeg($small_image, null, 20);
        }
        
        $image_data = ob_get_contents();
        ob_end_clean();
        
        imagedestroy($small_image);
        
        if ($image_data) {
            return 'data:' . $mime_type . ';base64,' . base64_encode($image_data);
        }
        
        return false;
    }
    
    /**
     * Crear imagen pequeña
     */
    private function create_small_image($file_path, $max_width) {
        $image_info = getimagesize($file_path);
        
        if ($image_info === false) {
            return false;
        }
        
        list($width, $height, $type) = $image_info;
        
        // Calcular nuevas dimensiones manteniendo proporción
        if ($width > $max_width) {
            $new_width = $max_width;
            $new_height = intval(($height * $max_width) / $width);
        } else {
            $new_width = $width;
            $new_height = $height;
        }
        
        // Crear imagen fuente
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($file_path);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($file_path);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($file_path);
                break;
            default:
                return false;
        }
        
        if ($source === false) {
            return false;
        }
        
        // Crear imagen de destino
        $destination = imagecreatetruecolor($new_width, $new_height);
        
        // Manejar transparencia para PNG y GIF
        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
            $transparent = imagecolorallocatealpha($destination, 0, 0, 0, 127);
            imagefill($destination, 0, 0, $transparent);
            imagesavealpha($destination, true);
        }
        
        // Redimensionar
        imagecopyresampled(
            $destination, $source,
            0, 0, 0, 0,
            $new_width, $new_height, $width, $height
        );
        
        imagedestroy($source);
        
        return $destination;
    }
    
    /**
     * Preload de imágenes críticas
     */
    public function preload_critical_images() {
        $critical_images = $this->get_critical_images();
        
        foreach ($critical_images as $image) {
            echo '<link rel="preload" as="image" href="' . esc_attr($image['url']) . '"';

            if (isset($image['type'])) {
                echo ' type="' . esc_attr($image['type']) . '"';
            }

            if (isset($image['media'])) {
                echo ' media="' . esc_attr($image['media']) . '"';
            }

            if (($image['priority'] ?? '') === 'high') {
                echo ' fetchpriority="high"';
            }

            echo '>' . "\n";
        }
    }
    
    /**
     * Obtener imágenes críticas para preload
     */
    private function get_critical_images() {
        if ($this->critical_images_cache !== null) {
            return $this->critical_images_cache;
        }

        $images = [];
        $add_image = function(array $image) use (&$images) {
            $image = array_filter($image, function($value) {
                return $value !== null && $value !== '';
            });

            if (empty($image['url']) && empty($image['id'])) {
                return;
            }

            $image['priority'] = $image['priority'] ?? 'high';

            if (!empty($image['id'])) {
                $key = 'id:' . intval($image['id']);
            } else {
                $key = 'url:' . $this->normalize_image_url($image['url']);
            }

            if (isset($images[$key])) {
                // Mantener la prioridad más alta registrada
                if (($image['priority'] ?? '') === 'high') {
                    $images[$key]['priority'] = 'high';
                }

                return;
            }

            if (!isset($image['type']) && !empty($image['id'])) {
                $mime = get_post_mime_type($image['id']);
                if ($mime) {
                    $image['type'] = $mime;
                }
            }

            $images[$key] = $image;
        };

        // Imágenes configuradas manualmente para preload
        $manual_preloads = $this->settings['images_preload'] ?? [];
        if (is_array($manual_preloads)) {
            foreach ($manual_preloads as $preload) {
                if (is_array($preload) && isset($preload['url'])) {
                    $add_image([
                        'url' => $preload['url'],
                        'type' => $preload['type'] ?? null,
                        'media' => $preload['media'] ?? null,
                        'priority' => $preload['priority'] ?? 'high',
                        'manual' => true
                    ]);
                } elseif (is_string($preload)) {
                    $add_image([
                        'url' => $preload,
                        'priority' => 'high',
                        'manual' => true
                    ]);
                }
            }
        }

        // Imágenes críticas marcadas manualmente
        foreach ($this->get_manual_critical_images() as $manual_image) {
            $add_image($manual_image);
        }

        // Logo principal del sitio
        $logo_id = get_theme_mod('custom_logo');
        if ($logo_id) {
            $logo_image = wp_get_attachment_image_src($logo_id, 'full');
            if ($logo_image) {
                $add_image([
                    'id' => $logo_id,
                    'url' => $logo_image[0],
                    'width' => $logo_image[1] ?? null,
                    'height' => $logo_image[2] ?? null,
                    'context' => 'site-logo',
                    'priority' => 'high'
                ]);
            }
        }

        // Featured image de la entrada/página actual
        if (is_singular()) {
            $post_id = get_queried_object_id();
            if ($post_id) {
                $featured_id = get_post_thumbnail_id($post_id);
                if ($featured_id) {
                    $featured_image = wp_get_attachment_image_src($featured_id, 'full');
                    if ($featured_image) {
                        $add_image([
                            'id' => $featured_id,
                            'url' => $featured_image[0],
                            'width' => $featured_image[1] ?? null,
                            'height' => $featured_image[2] ?? null,
                            'context' => 'featured-image',
                            'priority' => 'high'
                        ]);
                    }
                }

                // Imagen hero detectada en Elementor
                $elementor_image = $this->detect_elementor_primary_image($post_id);
                if ($elementor_image) {
                    $add_image($elementor_image);
                }

                // Primer imagen grande en el contenido
                $content_image = $this->detect_content_primary_image($post_id);
                if ($content_image) {
                    $add_image($content_image);
                }
            }
        }

        $this->critical_images_cache = array_values($images);
        $this->critical_image_index = $this->build_critical_image_index($this->critical_images_cache);

        return $this->critical_images_cache;
    }
    
    /**
     * Obtener estadísticas de optimización de imágenes
     */
    public function get_optimization_stats() {
        $stats = [
            'total_images_processed' => 0,
            'lazy_loading_applied' => 0,
            'lqip_generated' => 0,
            'webp_conversions' => 0,
            'total_size_saved' => 0
        ];
        
        // Obtener estadísticas desde transients
        global $wpdb;
        
        $lqip_transients = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_suple_speed_lqip_%'"
        );
        
        $stats['lqip_generated'] = $lqip_transients;
        
        return $stats;
    }
    
    /**
     * Limpiar transients de LQIP antiguos
     */
    public function cleanup_lqip_cache() {
        global $wpdb;
        
        // Limpiar transients expirados
        $expired_count = $wpdb->query("
            DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_timeout_suple_speed_lqip_%' 
            AND option_value < UNIX_TIMESTAMP()
        ");
        
        // Limpiar transients correspondientes
        $wpdb->query("
            DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_suple_speed_lqip_%' 
            AND option_name NOT IN (
                SELECT REPLACE(option_name, '_timeout', '') 
                FROM {$wpdb->options} 
                WHERE option_name LIKE '_transient_timeout_suple_speed_lqip_%'
            )
        ");
        
        return $expired_count;
    }
}