<?php

namespace SupleSpeed;

/**
 * Motor de reglas globales y por página
 */
class Rules {
    
    /**
     * Reglas cargadas
     */
    private $rules = [];
    private $global_rules = [];
    private $url_rules = [];
    private $processed_rules_cache = [];
    
    public function __construct() {
        $this->load_rules();
        $this->init_hooks();
    }
    
    /**
     * Cargar reglas desde la base de datos
     */
    private function load_rules() {
        $this->rules = get_option('suple_speed_rules', []);
        $this->organize_rules();
    }
    
    /**
     * Organizar reglas por tipo
     */
    private function organize_rules() {
        $this->global_rules = [];
        $this->url_rules = [];
        
        foreach ($this->rules as $rule_id => $rule) {
            if (!isset($rule['enabled']) || !$rule['enabled']) {
                continue;
            }
            
            if ($rule['scope'] === 'global') {
                $this->global_rules[$rule_id] = $rule;
            } else {
                $this->url_rules[$rule_id] = $rule;
            }
        }
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        add_filter('suple_speed_should_cache_page', [$this, 'apply_cache_rules'], 10, 2);
        add_filter('suple_speed_cache_ttl', [$this, 'apply_cache_ttl_rules'], 10, 2);
        add_filter('suple_speed_should_merge_assets', [$this, 'apply_merge_rules'], 10, 2);
        add_filter('suple_speed_exclude_handles', [$this, 'apply_exclude_handles_rules'], 10, 2);
        add_filter('suple_speed_critical_css_content', [$this, 'apply_critical_css_rules'], 10, 2);
        add_filter('suple_speed_preload_assets', [$this, 'apply_preload_rules'], 10, 2);
    }
    
    /**
     * Obtener reglas aplicables para la URL actual
     */
    public function get_applicable_rules($url = null) {
        if ($url === null) {
            $url = $this->get_current_url();
        }
        
        $cache_key = md5($url);
        if (isset($this->processed_rules_cache[$cache_key])) {
            return $this->processed_rules_cache[$cache_key];
        }
        
        $applicable_rules = [];
        
        // Siempre incluir reglas globales
        $applicable_rules = array_merge($applicable_rules, $this->global_rules);
        
        // Buscar reglas específicas para esta URL
        foreach ($this->url_rules as $rule_id => $rule) {
            if ($this->url_matches_rule($url, $rule)) {
                $applicable_rules[$rule_id] = $rule;
            }
        }
        
        // Ordenar por prioridad
        uasort($applicable_rules, function($a, $b) {
            return ($a['priority'] ?? 50) - ($b['priority'] ?? 50);
        });
        
        $this->processed_rules_cache[$cache_key] = $applicable_rules;
        return $applicable_rules;
    }
    
    /**
     * Verificar si una URL coincide con una regla
     */
    private function url_matches_rule($url, $rule) {
        $match_type = $rule['match_type'] ?? 'exact';
        $match_value = $rule['match_value'] ?? '';
        
        switch ($match_type) {
            case 'exact':
                return $url === $match_value;
                
            case 'contains':
                return strpos($url, $match_value) !== false;
                
            case 'starts_with':
                return strpos($url, $match_value) === 0;
                
            case 'ends_with':
                return substr($url, -strlen($match_value)) === $match_value;
                
            case 'regex':
                return preg_match($match_value, $url);
                
            case 'post_type':
                return $this->is_post_type($match_value);
                
            case 'post_id':
                return get_the_ID() == $match_value;
                
            case 'page_template':
                return is_page_template($match_value);
                
            case 'category':
                return is_category($match_value);
                
            case 'tag':
                return is_tag($match_value);
                
            case 'archive':
                return is_archive() && $match_value === 'any';
                
            case 'front_page':
                return is_front_page();
                
            case 'home':
                return is_home();
                
            case 'search':
                return is_search();
                
            case '404':
                return is_404();
                
            default:
                return false;
        }
    }
    
    /**
     * Verificar si es un post type específico
     */
    private function is_post_type($post_type) {
        if (is_singular()) {
            return get_post_type() === $post_type;
        }
        
        if (is_archive()) {
            return is_post_type_archive($post_type);
        }
        
        return false;
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
    
    // === APLICACIÓN DE REGLAS ===
    
    /**
     * Aplicar reglas de caché
     */
    public function apply_cache_rules($should_cache, $context) {
        $rules = $this->get_applicable_rules();
        
        foreach ($rules as $rule) {
            if (isset($rule['cache']['enabled'])) {
                $should_cache = $rule['cache']['enabled'];
            }
            
            // Reglas de exclusión específicas
            if (isset($rule['cache']['exclude_if'])) {
                foreach ($rule['cache']['exclude_if'] as $condition) {
                    if ($this->evaluate_condition($condition)) {
                        return false;
                    }
                }
            }
        }
        
        return $should_cache;
    }
    
    /**
     * Aplicar reglas de TTL de caché
     */
    public function apply_cache_ttl_rules($ttl, $context) {
        $rules = $this->get_applicable_rules();
        
        foreach ($rules as $rule) {
            if (isset($rule['cache']['ttl']) && $rule['cache']['ttl'] > 0) {
                $ttl = $rule['cache']['ttl'];
            }
        }
        
        return $ttl;
    }
    
    /**
     * Aplicar reglas de fusión de assets
     */
    public function apply_merge_rules($should_merge, $context) {
        $rules = $this->get_applicable_rules();
        
        foreach ($rules as $rule) {
            if (isset($rule['assets']['merge_css'])) {
                $should_merge['css'] = $rule['assets']['merge_css'];
            }
            
            if (isset($rule['assets']['merge_js'])) {
                $should_merge['js'] = $rule['assets']['merge_js'];
            }
            
            if (isset($rule['assets']['merge_groups'])) {
                $should_merge['groups'] = $rule['assets']['merge_groups'];
            }
        }
        
        return $should_merge;
    }
    
    /**
     * Aplicar reglas de exclusión de handles
     */
    public function apply_exclude_handles_rules($excluded_handles, $context) {
        $rules = $this->get_applicable_rules();
        
        foreach ($rules as $rule) {
            if (isset($rule['assets']['exclude_handles'])) {
                $excluded_handles = array_merge($excluded_handles, $rule['assets']['exclude_handles']);
            }
        }
        
        return array_unique($excluded_handles);
    }
    
    /**
     * Aplicar reglas de Critical CSS
     */
    public function apply_critical_css_rules($critical_css, $context) {
        $rules = $this->get_applicable_rules();
        
        foreach ($rules as $rule) {
            if (isset($rule['critical_css']['content']) && !empty($rule['critical_css']['content'])) {
                $critical_css = $rule['critical_css']['content'];
            }
            
            if (isset($rule['critical_css']['file']) && !empty($rule['critical_css']['file'])) {
                $file_path = SUPLE_SPEED_UPLOADS_DIR . 'critical/' . $rule['critical_css']['file'];
                if (file_exists($file_path)) {
                    $critical_css = file_get_contents($file_path);
                }
            }
        }
        
        return $critical_css;
    }
    
    /**
     * Aplicar reglas de preload
     */
    public function apply_preload_rules($preload_assets, $context) {
        $rules = $this->get_applicable_rules();
        
        foreach ($rules as $rule) {
            if (isset($rule['preload']['assets'])) {
                $preload_assets = array_merge($preload_assets, $rule['preload']['assets']);
            }
            
            if (isset($rule['preload']['fonts'])) {
                $preload_assets['fonts'] = array_merge(
                    $preload_assets['fonts'] ?? [],
                    $rule['preload']['fonts']
                );
            }
            
            if (isset($rule['preload']['images'])) {
                $preload_assets['images'] = array_merge(
                    $preload_assets['images'] ?? [],
                    $rule['preload']['images']
                );
            }
        }
        
        return $preload_assets;
    }
    
    /**
     * Evaluar condición
     */
    private function evaluate_condition($condition) {
        $type = $condition['type'] ?? '';
        $value = $condition['value'] ?? '';
        
        switch ($type) {
            case 'user_logged_in':
                return is_user_logged_in();
                
            case 'user_role':
                return current_user_can($value);
                
            case 'cookie_exists':
                return isset($_COOKIE[$value]);
                
            case 'cookie_value':
                return isset($_COOKIE[$value]) && $_COOKIE[$value] === $condition['expected'];
                
            case 'query_param':
                return isset($_GET[$value]);
                
            case 'query_param_value':
                return isset($_GET[$value]) && $_GET[$value] === $condition['expected'];
                
            case 'mobile_device':
                return wp_is_mobile();
                
            case 'time_range':
                $current_hour = (int) date('H');
                return $current_hour >= $condition['start'] && $current_hour <= $condition['end'];
                
            default:
                return false;
        }
    }
    
    // === GESTIÓN DE REGLAS ===
    
    /**
     * Crear nueva regla
     */
    public function create_rule($data) {
        $rule_id = uniqid('rule_');
        
        $rule = array_merge([
            'id' => $rule_id,
            'name' => '',
            'description' => '',
            'enabled' => true,
            'scope' => 'global', // global | url
            'priority' => 50,
            'match_type' => 'exact',
            'match_value' => '',
            'created' => time(),
            'modified' => time()
        ], $data);
        
        $this->rules[$rule_id] = $rule;
        $this->save_rules();
        $this->organize_rules();
        
        return $rule_id;
    }
    
    /**
     * Actualizar regla
     */
    public function update_rule($rule_id, $data) {
        if (!isset($this->rules[$rule_id])) {
            return false;
        }
        
        $data['modified'] = time();
        $this->rules[$rule_id] = array_merge($this->rules[$rule_id], $data);
        
        $this->save_rules();
        $this->organize_rules();
        
        return true;
    }
    
    /**
     * Eliminar regla
     */
    public function delete_rule($rule_id) {
        if (!isset($this->rules[$rule_id])) {
            return false;
        }
        
        unset($this->rules[$rule_id]);
        $this->save_rules();
        $this->organize_rules();
        
        return true;
    }
    
    /**
     * Obtener regla por ID
     */
    public function get_rule($rule_id) {
        return $this->rules[$rule_id] ?? null;
    }
    
    /**
     * Obtener todas las reglas
     */
    public function get_all_rules() {
        return $this->rules;
    }
    
    /**
     * Duplicar regla
     */
    public function duplicate_rule($rule_id) {
        if (!isset($this->rules[$rule_id])) {
            return false;
        }
        
        $original_rule = $this->rules[$rule_id];
        $new_rule = $original_rule;
        $new_rule['name'] = $original_rule['name'] . ' (Copy)';
        unset($new_rule['id']);
        
        return $this->create_rule($new_rule);
    }
    
    /**
     * Importar reglas
     */
    public function import_rules($rules_data, $replace = false) {
        if ($replace) {
            $this->rules = [];
        }
        
        $imported_count = 0;
        
        foreach ($rules_data as $rule_data) {
            if ($this->validate_rule_data($rule_data)) {
                $this->create_rule($rule_data);
                $imported_count++;
            }
        }
        
        return $imported_count;
    }
    
    /**
     * Exportar reglas
     */
    public function export_rules($rule_ids = null) {
        if ($rule_ids === null) {
            return array_values($this->rules);
        }
        
        $exported_rules = [];
        foreach ($rule_ids as $rule_id) {
            if (isset($this->rules[$rule_id])) {
                $exported_rules[] = $this->rules[$rule_id];
            }
        }
        
        return $exported_rules;
    }
    
    /**
     * Validar datos de regla
     */
    private function validate_rule_data($rule_data) {
        $required_fields = ['name', 'scope'];
        
        foreach ($required_fields as $field) {
            if (!isset($rule_data[$field]) || empty($rule_data[$field])) {
                return false;
            }
        }
        
        if (!in_array($rule_data['scope'], ['global', 'url'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Guardar reglas en la base de datos
     */
    private function save_rules() {
        update_option('suple_speed_rules', $this->rules);
        
        // Limpiar caché de reglas procesadas
        $this->processed_rules_cache = [];
    }
    
    // === REGLAS PREDEFINIDAS ===
    
    /**
     * Crear reglas predefinidas
     */
    public function create_default_rules() {
        $default_rules = $this->get_default_rules_definitions();
        
        foreach ($default_rules as $rule_data) {
            // Solo crear si no existe una regla similar
            if (!$this->rule_exists($rule_data['name'])) {
                $this->create_rule($rule_data);
            }
        }
    }
    
    /**
     * Verificar si existe una regla con el mismo nombre
     */
    private function rule_exists($name) {
        foreach ($this->rules as $rule) {
            if ($rule['name'] === $name) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Obtener definiciones de reglas por defecto
     */
    private function get_default_rules_definitions() {
        return [
            [
                'name' => 'WooCommerce - Sin caché en checkout',
                'description' => 'Deshabilitar caché en páginas de checkout y carrito',
                'scope' => 'url',
                'match_type' => 'regex',
                'match_value' => '/checkout|cart|my-account/',
                'priority' => 10,
                'cache' => [
                    'enabled' => false
                ]
            ],
            [
                'name' => 'Elementor - Proteger assets',
                'description' => 'No fusionar assets críticos de Elementor',
                'scope' => 'global',
                'priority' => 20,
                'assets' => [
                    'exclude_handles' => [
                        'elementor-frontend',
                        'elementor-frontend-modules',
                        'swiper'
                    ]
                ]
            ],
            [
                'name' => 'Usuarios logueados - Sin caché',
                'description' => 'No cachear para usuarios logueados',
                'scope' => 'global',
                'priority' => 5,
                'cache' => [
                    'exclude_if' => [
                        ['type' => 'user_logged_in']
                    ]
                ]
            ],
            [
                'name' => 'Página de inicio - Caché extendido',
                'description' => 'Caché más agresivo para la página de inicio',
                'scope' => 'url',
                'match_type' => 'front_page',
                'priority' => 30,
                'cache' => [
                    'ttl' => 48 * HOUR_IN_SECONDS
                ],
                'assets' => [
                    'merge_groups' => ['A', 'B', 'C']
                ]
            ],
            [
                'name' => 'Móviles - Critical CSS específico',
                'description' => 'Critical CSS optimizado para móviles',
                'scope' => 'global',
                'priority' => 40,
                'critical_css' => [
                    'content' => '/* Mobile-first critical CSS */'
                ],
                'cache' => [
                    'exclude_if' => [
                        ['type' => 'mobile_device']
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Obtener estadísticas de reglas
     */
    public function get_rules_stats() {
        $stats = [
            'total_rules' => count($this->rules),
            'enabled_rules' => 0,
            'global_rules' => 0,
            'url_rules' => 0,
            'rules_by_type' => []
        ];
        
        foreach ($this->rules as $rule) {
            if ($rule['enabled']) {
                $stats['enabled_rules']++;
            }
            
            if ($rule['scope'] === 'global') {
                $stats['global_rules']++;
            } else {
                $stats['url_rules']++;
            }
            
            // Contar por tipo de coincidencia
            $match_type = $rule['match_type'] ?? 'none';
            $stats['rules_by_type'][$match_type] = ($stats['rules_by_type'][$match_type] ?? 0) + 1;
        }
        
        return $stats;
    }
    
    /**
     * Probar regla contra URL específica
     */
    public function test_rule_against_url($rule_id, $test_url) {
        if (!isset($this->rules[$rule_id])) {
            return false;
        }
        
        $rule = $this->rules[$rule_id];
        
        if ($rule['scope'] === 'global') {
            return true;
        }
        
        return $this->url_matches_rule($test_url, $rule);
    }
    
    /**
     * Optimizar reglas (eliminar duplicados, consolidar, etc.)
     */
    public function optimize_rules() {
        // Eliminar reglas deshabilitadas antiguas
        $cutoff_time = time() - (30 * DAY_IN_SECONDS);
        
        foreach ($this->rules as $rule_id => $rule) {
            if (!$rule['enabled'] && 
                isset($rule['modified']) && 
                $rule['modified'] < $cutoff_time) {
                unset($this->rules[$rule_id]);
            }
        }
        
        // TODO: Implementar más optimizaciones
        // - Detectar reglas que se solapan
        // - Consolidar reglas similares
        // - Reordenar por eficiencia
        
        $this->save_rules();
        $this->organize_rules();
    }
}