<?php

namespace SupleSpeed;

/**
 * Integración con PageSpeed Insights API
 */
class PSI {
    
    /**
     * Configuración
     */
    private $settings;
    private $logger;
    private $api_key;
    private $api_url = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
    
    public function __construct() {
        $this->settings = get_option('suple_speed_settings', []);
        $this->api_key = $this->settings['psi_api_key'] ?? '';
        
        if (function_exists('suple_speed')) {
            $this->logger = suple_speed()->logger;
        }
        
        $this->init_hooks();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // AJAX para ejecutar tests
        add_action('wp_ajax_suple_speed_run_psi', [$this, 'ajax_run_test']);
        add_action('wp_ajax_suple_speed_get_psi_data', [$this, 'ajax_get_psi_data']);
        add_action('wp_ajax_suple_speed_apply_psi_suggestions', [$this, 'ajax_apply_suggestions']);
        
        // Scheduled tests
        add_action('suple_speed_scheduled_psi_test', [$this, 'run_scheduled_test']);
        
        // Auto-test después de cambios importantes
        if ($this->settings['psi_auto_test']) {
            add_action('suple_speed_cache_purged', [$this, 'maybe_run_auto_test']);
        }
    }
    
    /**
     * Verificar si API key está configurada
     */
    public function is_configured() {
        return !empty($this->api_key);
    }
    
    /**
     * Ejecutar test de PageSpeed Insights
     */
    public function run_test($url, $strategy = 'mobile', $categories = null) {
        if (!$this->is_configured()) {
            return new \WP_Error('no_api_key', 'PageSpeed Insights API key not configured');
        }
        
        $url = $this->normalize_url($url);
        
        // Verificar caché reciente
        $cache_key = $this->get_cache_key($url, $strategy);
        $cached_result = get_transient('suple_speed_psi_' . $cache_key);
        
        if ($cached_result !== false && 
            (time() - $cached_result['timestamp']) < HOUR_IN_SECONDS) {
            return $cached_result;
        }
        
        // Parámetros de la API
        $params = [
            'url' => $url,
            'key' => $this->api_key,
            'strategy' => $strategy
        ];
        
        // Categorías específicas
        if ($categories) {
            $params['category'] = is_array($categories) ? $categories : [$categories];
        }
        
        // Ejecutar request
        $api_url = $this->api_url . '?' . http_build_query($params);
        
        $response = wp_remote_get($api_url, [
            'timeout' => 120,
            'headers' => [
                'User-Agent' => 'Suple Speed WordPress Plugin'
            ]
        ]);
        
        if (is_wp_error($response)) {
            $this->log_error('PSI API request failed', [
                'url' => $url,
                'strategy' => $strategy,
                'error' => $response->get_error_message()
            ]);
            
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $error_message = 'PSI API returned error: ' . $response_code;
            
            if ($response_body) {
                $error_data = json_decode($response_body, true);
                if (isset($error_data['error']['message'])) {
                    $error_message = $error_data['error']['message'];
                }
            }
            
            $this->log_error($error_message, [
                'url' => $url,
                'strategy' => $strategy,
                'response_code' => $response_code
            ]);
            
            return new \WP_Error('psi_api_error', $error_message);
        }
        
        $data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error('psi_json_error', 'Invalid JSON response from PSI API');
        }
        
        // Procesar y guardar resultado
        $processed_result = $this->process_psi_result($data, $url, $strategy);
        
        // Cachear resultado por 1 hora
        set_transient('suple_speed_psi_' . $cache_key, $processed_result, HOUR_IN_SECONDS);
        
        // Guardar en historial
        $this->save_to_history($processed_result);
        
        // Log del test exitoso
        if ($this->logger) {
            $this->logger->info('PSI test completed successfully', [
                'url' => $url,
                'strategy' => $strategy,
                'score' => $processed_result['scores']['performance'] ?? 'N/A'
            ], 'psi');
        }
        
        return $processed_result;
    }
    
    /**
     * Procesar resultado de PSI
     */
    private function process_psi_result($data, $url, $strategy) {
        $result = [
            'url' => $url,
            'strategy' => $strategy,
            'timestamp' => time(),
            'scores' => [],
            'metrics' => [],
            'audits' => [],
            'opportunities' => [],
            'diagnostics' => [],
            'raw_data' => $data
        ];
        
        // Extraer scores de categorías
        if (isset($data['lighthouseResult']['categories'])) {
            foreach ($data['lighthouseResult']['categories'] as $category_id => $category) {
                $result['scores'][$category_id] = [
                    'score' => $category['score'] * 100,
                    'title' => $category['title'] ?? $category_id
                ];
            }
        }
        
        // Extraer métricas Core Web Vitals
        if (isset($data['lighthouseResult']['audits'])) {
            $audits = $data['lighthouseResult']['audits'];
            
            $metrics_map = [
                'largest-contentful-paint' => 'lcp',
                'interaction-to-next-paint' => 'inp',
                'cumulative-layout-shift' => 'cls',
                'first-contentful-paint' => 'fcp',
                'total-blocking-time' => 'tbt',
                'time-to-interactive' => 'tti',
                'speed-index' => 'si',
                'server-response-time' => 'ttfb'
            ];
            
            foreach ($metrics_map as $audit_id => $metric_key) {
                if (isset($audits[$audit_id])) {
                    $audit = $audits[$audit_id];
                    $result['metrics'][$metric_key] = [
                        'value' => $audit['numericValue'] ?? null,
                        'displayValue' => $audit['displayValue'] ?? null,
                        'score' => $audit['score'] ?? null,
                        'title' => $audit['title'] ?? $metric_key
                    ];
                }
            }
            
            // Extraer oportunidades y diagnósticos
            foreach ($audits as $audit_id => $audit) {
                if (isset($audit['details']['type']) && 
                    $audit['details']['type'] === 'opportunity' &&
                    isset($audit['details']['overallSavingsMs']) &&
                    $audit['details']['overallSavingsMs'] > 0) {
                    
                    $result['opportunities'][] = [
                        'id' => $audit_id,
                        'title' => $audit['title'] ?? $audit_id,
                        'description' => $audit['description'] ?? '',
                        'savings_ms' => $audit['details']['overallSavingsMs'],
                        'savings_bytes' => $audit['details']['overallSavingsBytes'] ?? 0,
                        'score' => $audit['score'] ?? null
                    ];
                }
                
                // Diagnósticos (audits con score < 1)
                if (isset($audit['score']) && 
                    $audit['score'] < 1 && 
                    !isset($audit['details']['overallSavingsMs'])) {
                    
                    $result['diagnostics'][] = [
                        'id' => $audit_id,
                        'title' => $audit['title'] ?? $audit_id,
                        'description' => $audit['description'] ?? '',
                        'score' => $audit['score']
                    ];
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Normalizar URL
     */
    private function normalize_url($url) {
        // Asegurar protocolo
        if (strpos($url, 'http') !== 0) {
            $url = home_url($url);
        }
        
        // Remover fragment
        $url = strtok($url, '#');
        
        return $url;
    }
    
    /**
     * Generar clave de caché
     */
    private function get_cache_key($url, $strategy) {
        return md5($url . $strategy);
    }
    
    /**
     * Guardar en historial
     */
    private function save_to_history($result) {
        $history = get_option('suple_speed_psi_history', []);
        
        // Generar ID único para este test
        $test_id = uniqid('psi_');
        
        $history_entry = [
            'id' => $test_id,
            'url' => $result['url'],
            'strategy' => $result['strategy'],
            'timestamp' => $result['timestamp'],
            'scores' => $result['scores'],
            'metrics' => $result['metrics']
        ];
        
        $history[] = $history_entry;
        
        // Mantener solo los últimos 100 tests
        $history = array_slice($history, -100);
        
        update_option('suple_speed_psi_history', $history);
        
        return $test_id;
    }
    
    /**
     * Obtener historial de tests
     */
    public function get_history($url = null, $limit = 20) {
        $history = get_option('suple_speed_psi_history', []);
        
        // Filtrar por URL si se especifica
        if ($url) {
            $normalized_url = $this->normalize_url($url);
            $history = array_filter($history, function($entry) use ($normalized_url) {
                return $entry['url'] === $normalized_url;
            });
        }
        
        // Ordenar por timestamp descendente
        usort($history, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
        
        // Limitar resultados
        return array_slice($history, 0, $limit);
    }
    
    /**
     * Comparar dos tests
     */
    public function compare_tests($test_id_1, $test_id_2) {
        $history = get_option('suple_speed_psi_history', []);
        
        $test_1 = null;
        $test_2 = null;
        
        foreach ($history as $entry) {
            if ($entry['id'] === $test_id_1) {
                $test_1 = $entry;
            }
            if ($entry['id'] === $test_id_2) {
                $test_2 = $entry;
            }
        }
        
        if (!$test_1 || !$test_2) {
            return false;
        }
        
        $comparison = [
            'test_1' => $test_1,
            'test_2' => $test_2,
            'differences' => []
        ];
        
        // Comparar scores
        foreach ($test_1['scores'] as $category => $score_1) {
            if (isset($test_2['scores'][$category])) {
                $score_2 = $test_2['scores'][$category];
                $difference = $score_2['score'] - $score_1['score'];
                
                $comparison['differences']['scores'][$category] = [
                    'before' => $score_1['score'],
                    'after' => $score_2['score'],
                    'difference' => $difference,
                    'improved' => $difference > 0
                ];
            }
        }
        
        // Comparar métricas
        foreach ($test_1['metrics'] as $metric => $data_1) {
            if (isset($test_2['metrics'][$metric]) && 
                isset($data_1['value']) && 
                isset($test_2['metrics'][$metric]['value'])) {
                
                $value_1 = $data_1['value'];
                $value_2 = $test_2['metrics'][$metric]['value'];
                $difference = $value_2 - $value_1;
                
                // Para métricas de tiempo, menor es mejor
                $improved = in_array($metric, ['lcp', 'inp', 'fcp', 'tbt', 'tti', 'si', 'ttfb']) ? 
                           $difference < 0 : 
                           $difference > 0;
                
                $comparison['differences']['metrics'][$metric] = [
                    'before' => $value_1,
                    'after' => $value_2,
                    'difference' => $difference,
                    'improved' => $improved
                ];
            }
        }
        
        return $comparison;
    }
    
    /**
     * Generar sugerencias automáticas
     */
    public function generate_suggestions($psi_result) {
        $suggestions = [];
        
        if (!isset($psi_result['opportunities'])) {
            return $suggestions;
        }
        
        foreach ($psi_result['opportunities'] as $opportunity) {
            $suggestion = $this->map_opportunity_to_suggestion($opportunity);
            if ($suggestion) {
                $suggestions[] = $suggestion;
            }
        }
        
        // Ordenar por impacto (savings_ms)
        usort($suggestions, function($a, $b) {
            return ($b['impact'] ?? 0) - ($a['impact'] ?? 0);
        });
        
        return $suggestions;
    }
    
    /**
     * Mapear oportunidad a sugerencia
     */
    private function map_opportunity_to_suggestion($opportunity) {
        $suggestion_map = [
            'unused-css-rules' => [
                'title' => 'Remove unused CSS',
                'action' => 'enable_css_optimization',
                'settings' => ['merge_css' => true, 'minify_css' => true]
            ],
            'unused-javascript' => [
                'title' => 'Remove unused JavaScript',
                'action' => 'enable_js_optimization',
                'settings' => ['merge_js' => true, 'minify_js' => true]
            ],
            'render-blocking-resources' => [
                'title' => 'Eliminate render-blocking resources',
                'action' => 'enable_critical_css',
                'settings' => ['critical_css_enabled' => true, 'defer_js' => true]
            ],
            'unoptimized-images' => [
                'title' => 'Serve images in next-gen formats',
                'action' => 'enable_webp',
                'settings' => ['images_webp_rewrite' => true]
            ],
            'offscreen-images' => [
                'title' => 'Defer offscreen images',
                'action' => 'enable_lazy_loading',
                'settings' => ['images_lazy' => true]
            ],
            'uses-text-compression' => [
                'title' => 'Enable text compression',
                'action' => 'enable_compression',
                'settings' => ['compression_enabled' => true]
            ],
            'uses-rel-preconnect' => [
                'title' => 'Preconnect to required origins',
                'action' => 'add_preconnects',
                'settings' => [] // Se configurará dinámicamente
            ],
            'font-display' => [
                'title' => 'Ensure text remains visible during webfont load',
                'action' => 'optimize_fonts',
                'settings' => ['fonts_local' => true]
            ]
        ];
        
        if (!isset($suggestion_map[$opportunity['id']])) {
            return null;
        }
        
        $suggestion = $suggestion_map[$opportunity['id']];
        $suggestion['id'] = $opportunity['id'];
        $suggestion['description'] = $opportunity['description'];
        $suggestion['impact'] = $opportunity['savings_ms'];
        $suggestion['impact_formatted'] = $this->format_savings($opportunity['savings_ms']);
        
        return $suggestion;
    }
    
    /**
     * Formatear ahorros
     */
    private function format_savings($ms) {
        if ($ms >= 1000) {
            return round($ms / 1000, 1) . 's';
        }
        
        return $ms . 'ms';
    }
    
    /**
     * Aplicar sugerencia automáticamente
     */
    public function apply_suggestion($suggestion_id, $psi_result) {
        $suggestions = $this->generate_suggestions($psi_result);
        
        $suggestion = null;
        foreach ($suggestions as $s) {
            if ($s['id'] === $suggestion_id) {
                $suggestion = $s;
                break;
            }
        }
        
        if (!$suggestion) {
            return false;
        }
        
        $current_settings = get_option('suple_speed_settings', []);
        $updated_settings = array_merge($current_settings, $suggestion['settings']);
        
        update_option('suple_speed_settings', $updated_settings);
        
        // Log de aplicación
        if ($this->logger) {
            $this->logger->info('PSI suggestion applied automatically', [
                'suggestion_id' => $suggestion_id,
                'title' => $suggestion['title'],
                'settings_changed' => $suggestion['settings']
            ], 'psi');
        }
        
        return true;
    }
    
    /**
     * Ejecutar test programado
     */
    public function run_scheduled_test() {
        $scheduled_urls = $this->settings['psi_scheduled_urls'] ?? [home_url('/')];
        
        foreach ($scheduled_urls as $url) {
            // Test móvil y desktop
            $this->run_test($url, 'mobile');
            $this->run_test($url, 'desktop');
            
            // Esperar entre tests para no sobrecargar la API
            sleep(2);
        }
    }
    
    /**
     * Maybe ejecutar test automático
     */
    public function maybe_run_auto_test() {
        $last_auto_test = get_transient('suple_speed_last_auto_psi_test');
        
        // No ejecutar más de una vez cada 2 horas
        if ($last_auto_test !== false) {
            return;
        }
        
        // Ejecutar en background
        wp_schedule_single_event(time() + 300, 'suple_speed_scheduled_psi_test'); // 5 minutos después
        
        // Marcar que ya se ejecutó
        set_transient('suple_speed_last_auto_psi_test', time(), 2 * HOUR_IN_SECONDS);
    }
    
    /**
     * Log de error
     */
    private function log_error($message, $context = []) {
        if ($this->logger) {
            $this->logger->error($message, $context, 'psi');
        }
    }
    
    // === AJAX HANDLERS ===
    
    /**
     * AJAX: Ejecutar test PSI
     */
    public function ajax_run_test() {
        check_ajax_referer('suple_speed_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $url = sanitize_url($_POST['url'] ?? home_url('/'));
        $strategy = sanitize_text_field($_POST['strategy'] ?? 'mobile');
        
        $result = $this->run_test($url, $strategy);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success([
            'result' => $result,
            'suggestions' => $this->generate_suggestions($result)
        ]);
    }
    
    /**
     * AJAX: Obtener datos PSI
     */
    public function ajax_get_psi_data() {
        check_ajax_referer('suple_speed_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $url = sanitize_url($_POST['url'] ?? '');
        $limit = intval($_POST['limit'] ?? 10);
        
        $history = $this->get_history($url, $limit);
        
        wp_send_json_success([
            'history' => $history,
            'configured' => $this->is_configured()
        ]);
    }
    
    /**
     * AJAX: Aplicar sugerencias
     */
    public function ajax_apply_suggestions() {
        check_ajax_referer('suple_speed_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $test_id = sanitize_text_field($_POST['test_id'] ?? '');
        $suggestion_ids = $_POST['suggestion_ids'] ?? [];
        
        if (!is_array($suggestion_ids)) {
            wp_send_json_error('Invalid suggestion IDs');
        }
        
        // Obtener resultado del test
        $history = get_option('suple_speed_psi_history', []);
        $psi_result = null;
        
        foreach ($history as $entry) {
            if ($entry['id'] === $test_id) {
                // Necesitamos el resultado completo, no solo el historial
                $cache_key = $this->get_cache_key($entry['url'], $entry['strategy']);
                $psi_result = get_transient('suple_speed_psi_' . $cache_key);
                break;
            }
        }
        
        if (!$psi_result) {
            wp_send_json_error('Test result not found');
        }
        
        $applied_suggestions = [];
        
        foreach ($suggestion_ids as $suggestion_id) {
            $suggestion_id = sanitize_text_field($suggestion_id);
            
            if ($this->apply_suggestion($suggestion_id, $psi_result)) {
                $applied_suggestions[] = $suggestion_id;
            }
        }
        
        wp_send_json_success([
            'applied_suggestions' => $applied_suggestions,
            'message' => sprintf('Applied %d suggestions successfully', count($applied_suggestions))
        ]);
    }
    
    /**
     * Obtener estadísticas de PSI
     */
    public function get_psi_stats() {
        $history = get_option('suple_speed_psi_history', []);
        
        $stats = [
            'total_tests' => count($history),
            'avg_performance_mobile' => 0,
            'avg_performance_desktop' => 0,
            'latest_test' => null,
            'improvement_trend' => null
        ];
        
        if (empty($history)) {
            return $stats;
        }
        
        // Últimos 10 tests móviles y desktop
        $mobile_scores = [];
        $desktop_scores = [];
        
        foreach (array_reverse($history) as $entry) {
            if (isset($entry['scores']['performance'])) {
                $score = $entry['scores']['performance']['score'];
                
                if ($entry['strategy'] === 'mobile' && count($mobile_scores) < 10) {
                    $mobile_scores[] = $score;
                } elseif ($entry['strategy'] === 'desktop' && count($desktop_scores) < 10) {
                    $desktop_scores[] = $score;
                }
            }
        }
        
        // Promedios
        if (!empty($mobile_scores)) {
            $stats['avg_performance_mobile'] = round(array_sum($mobile_scores) / count($mobile_scores), 1);
        }
        
        if (!empty($desktop_scores)) {
            $stats['avg_performance_desktop'] = round(array_sum($desktop_scores) / count($desktop_scores), 1);
        }
        
        // Último test
        $stats['latest_test'] = end($history);
        
        // Tendencia de mejora (comparar primeros 5 vs últimos 5)
        if (count($mobile_scores) >= 10) {
            $first_half = array_slice($mobile_scores, 0, 5);
            $second_half = array_slice($mobile_scores, -5);
            
            $avg_first = array_sum($first_half) / count($first_half);
            $avg_second = array_sum($second_half) / count($second_half);
            
            $stats['improvement_trend'] = $avg_second - $avg_first;
        }
        
        return $stats;
    }
}