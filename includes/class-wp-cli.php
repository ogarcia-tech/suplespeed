<?php

namespace SupleSpeed;

/**
 * Comandos WP-CLI para Suple Speed
 */
class WP_CLI {
    foreach ($urls as $url) {
        wp_remote_get($url, [
            'timeout' => 30,
            'blocking' => false, // No esperar la respuesta para agilizar el proceso
            'headers' => [
                'User-Agent' => 'Suple Speed Cache Warmer'
            ]
        ]);
        $progress->tick();
        if ($delay > 0) {
            sleep($delay);
        }
    }
    public function __construct() {
        if (defined('WP_CLI') && WP_CLI) {
            add_action('init', [$this, 'register_commands']);
        }
    }
    
    /**
     * Registrar comandos WP-CLI
     */
    public function register_commands() {
        \WP_CLI::add_command('suple-speed cache', [$this, 'cache_commands']);
        \WP_CLI::add_command('suple-speed psi', [$this, 'psi_commands']);
        \WP_CLI::add_command('suple-speed fonts', [$this, 'fonts_commands']);
        \WP_CLI::add_command('suple-speed warm', [$this, 'warm_commands']);
        \WP_CLI::add_command('suple-speed status', [$this, 'status_command']);
        \WP_CLI::add_command('suple-speed settings', [$this, 'settings_commands']);
    }
    
    /**
     * Comandos de caché
     * 
     * ## OPTIONS
     * 
     * [--url=<url>]
     * : URL específica para purgar
     * 
     * [--post-id=<id>]
     * : ID del post para purgar
     * 
     * [--all]
     * : Purgar toda la caché
     * 
     * ## EXAMPLES
     * 
     *     wp suple-speed cache purge --all
     *     wp suple-speed cache purge --url=https://example.com/page/
     *     wp suple-speed cache purge --post-id=123
     *     wp suple-speed cache stats
     * 
     * @subcommand purge
     */
    public function cache_commands($args, $assoc_args) {
        if (!function_exists('suple_speed')) {
            \WP_CLI::error('Suple Speed plugin not loaded');
        }
        
        $subcommand = $args[0] ?? 'purge';
        
        switch ($subcommand) {
            case 'purge':
                $this->cache_purge($assoc_args);
                break;
                
            case 'stats':
                $this->cache_stats();
                break;
                
            case 'cleanup':
                $this->cache_cleanup();
                break;
                
            default:
                \WP_CLI::error("Unknown cache subcommand: {$subcommand}");
        }
    }
    
    /**
     * Purgar caché
     */
    private function cache_purge($assoc_args) {
        $cache = suple_speed()->cache;
        
        if (isset($assoc_args['all'])) {
            \WP_CLI::line('Purging all cache...');
            $purged = $cache->purge_all();
            \WP_CLI::success("Purged {$purged} cached files");
            
        } elseif (isset($assoc_args['url'])) {
            $url = $assoc_args['url'];
            \WP_CLI::line("Purging cache for URL: {$url}");
            $purged = $cache->purge_url($url);
            \WP_CLI::success("Purged {$purged} cached files for URL");
            
        } elseif (isset($assoc_args['post-id'])) {
            $post_id = intval($assoc_args['post-id']);
            \WP_CLI::line("Purging cache for post ID: {$post_id}");
            $purged = $cache->purge_post_cache($post_id);
            \WP_CLI::success("Purged {$purged} cached files for post");
            
        } else {
            \WP_CLI::error('Please specify --all, --url=<url>, or --post-id=<id>');
        }
    }
    
    /**
     * Mostrar estadísticas de caché
     */
    private function cache_stats() {
        $cache = suple_speed()->cache;
        $stats = $cache->get_cache_stats();
        
        $table_data = [
            ['Metric', 'Value'],
            ['Total Files', $stats['total_files']],
            ['Total Size', $stats['total_size_formatted']],
            ['Oldest File', $stats['oldest_file'] ?: 'N/A'],
            ['Newest File', $stats['newest_file'] ?: 'N/A']
        ];
        
        \WP_CLI\Utils\format_items('table', $table_data, ['Metric', 'Value']);
    }
    
    /**
     * Limpiar caché expirada
     */
    private function cache_cleanup() {
        $cache = suple_speed()->cache;
        \WP_CLI::line('Cleaning up expired cache...');
        $cleaned = $cache->cleanup_expired_cache();
        \WP_CLI::success("Cleaned {$cleaned} expired cache files");
    }
    
    /**
     * Comandos de PageSpeed Insights
     * 
     * ## OPTIONS
     * 
     * --url=<url>
     * : URL para testear
     * 
     * [--strategy=<strategy>]
     * : Estrategia (mobile/desktop)
     * ---
     * default: mobile
     * options:
     *   - mobile
     *   - desktop
     * ---
     * 
     * [--format=<format>]
     * : Formato de salida
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - yaml
     * ---
     * 
     * ## EXAMPLES
     * 
     *     wp suple-speed psi --url=https://example.com
     *     wp suple-speed psi --url=https://example.com --strategy=desktop
     *     wp suple-speed psi --url=https://example.com --format=json
     * 
     * @subcommand run
     */
    public function psi_commands($args, $assoc_args) {
        if (!function_exists('suple_speed')) {
            \WP_CLI::error('Suple Speed plugin not loaded');
        }
        
        if (!isset($assoc_args['url'])) {
            \WP_CLI::error('Please specify --url=<url>');
        }
        
        $url = $assoc_args['url'];
        $strategy = $assoc_args['strategy'] ?? 'mobile';
        $format = $assoc_args['format'] ?? 'table';
        
        $psi = suple_speed()->psi;
        
        if (!$psi->is_configured()) {
            \WP_CLI::error('PageSpeed Insights API key not configured');
        }
        
        \WP_CLI::line("Running PageSpeed test for: {$url} ({$strategy})");
        
        $result = $psi->run_test($url, $strategy);
        
        if (is_wp_error($result)) {
            \WP_CLI::error($result->get_error_message());
        }
        
        switch ($format) {
            case 'json':
                \WP_CLI::line(json_encode($result, JSON_PRETTY_PRINT));
                break;
                
            case 'yaml':
                \WP_CLI::line(\Symfony\Component\Yaml\Yaml::dump($result, 4, 2));
                break;
                
            default:
                $this->display_psi_table($result);
        }
    }
    
    /**
     * Mostrar resultados PSI en formato tabla
     */
    private function display_psi_table($result) {
        // Scores
        if (!empty($result['scores'])) {
            \WP_CLI::line("\nScores:");
            $score_data = [['Category', 'Score']];
            
            foreach ($result['scores'] as $category => $score_info) {
                $score_data[] = [
                    ucfirst($category),
                    round($score_info['score']) . '/100'
                ];
            }
            
            \WP_CLI\Utils\format_items('table', $score_data, ['Category', 'Score']);
        }
        
        // Core Web Vitals
        if (!empty($result['metrics'])) {
            \WP_CLI::line("\nCore Web Vitals:");
            $metrics_data = [['Metric', 'Value', 'Score']];
            
            $cwv_metrics = ['lcp', 'inp', 'cls', 'fcp', 'ttfb'];
            
            foreach ($cwv_metrics as $metric_key) {
                if (isset($result['metrics'][$metric_key])) {
                    $metric = $result['metrics'][$metric_key];
                    $metrics_data[] = [
                        strtoupper($metric_key),
                        $metric['displayValue'] ?? $metric['value'],
                        isset($metric['score']) ? round($metric['score'] * 100) . '/100' : 'N/A'
                    ];
                }
            }
            
            \WP_CLI\Utils\format_items('table', $metrics_data, ['Metric', 'Value', 'Score']);
        }
        
        // Opportunities
        if (!empty($result['opportunities'])) {
            \WP_CLI::line("\nOptimization Opportunities:");
            $opportunities_data = [['Opportunity', 'Potential Savings']];
            
            foreach (array_slice($result['opportunities'], 0, 5) as $opportunity) {
                $opportunities_data[] = [
                    $opportunity['title'],
                    round($opportunity['savings_ms'] / 1000, 1) . 's'
                ];
            }
            
            \WP_CLI\Utils\format_items('table', $opportunities_data, ['Opportunity', 'Potential Savings']);
        }
    }
    
    /**
     * Comandos de fuentes
     * 
     * ## SUBCOMMANDS
     * 
     * scan          Escanear fuentes de Google en el sitio
     * localize      Localizar fuentes específicas
     * stats         Mostrar estadísticas de fuentes
     * cleanup       Limpiar fuentes no utilizadas
     * 
     * ## OPTIONS
     * 
     * [--urls=<urls>]
     * : URLs de fuentes separadas por comas (para localize)
     * 
     * ## EXAMPLES
     * 
     *     wp suple-speed fonts scan
     *     wp suple-speed fonts localize --urls="https://fonts.googleapis.com/css?family=Open+Sans"
     *     wp suple-speed fonts stats
     *     wp suple-speed fonts cleanup
     * 
     * @subcommand scan
     */
    public function fonts_commands($args, $assoc_args) {
        if (!function_exists('suple_speed')) {
            \WP_CLI::error('Suple Speed plugin not loaded');
        }
        
        $subcommand = $args[0] ?? 'scan';
        $fonts = suple_speed()->fonts;
        
        switch ($subcommand) {
            case 'scan':
                $this->fonts_scan($fonts);
                break;
                
            case 'localize':
                $this->fonts_localize($fonts, $assoc_args);
                break;
                
            case 'stats':
                $this->fonts_stats($fonts);
                break;
                
            case 'cleanup':
                $this->fonts_cleanup($fonts);
                break;
                
            default:
                \WP_CLI::error("Unknown fonts subcommand: {$subcommand}");
        }
    }
    
    /**
     * Escanear fuentes de Google
     */
    private function fonts_scan($fonts) {
        \WP_CLI::line('Scanning for Google Fonts...');
        $found_fonts = $fonts->scan_google_fonts();
        
        if (empty($found_fonts)) {
            \WP_CLI::success('No Google Fonts found');
            return;
        }
        
        \WP_CLI::success(sprintf('Found %d Google Fonts:', count($found_fonts)));
        
        $table_data = [['Source', 'Location', 'URL']];
        foreach ($found_fonts as $font) {
            $table_data[] = [
                $font['source'],
                $font['location'],
                substr($font['url'], 0, 60) . '...'
            ];
        }
        
        \WP_CLI\Utils\format_items('table', $table_data, ['Source', 'Location', 'URL']);
    }
    
    /**
     * Localizar fuentes específicas
     */
    private function fonts_localize($fonts, $assoc_args) {
        if (!isset($assoc_args['urls'])) {
            \WP_CLI::error('Please specify --urls="url1,url2,..."');
        }
        
        $urls = array_map('trim', explode(',', $assoc_args['urls']));
        
        \WP_CLI::line('Localizing fonts...');
        
        foreach ($urls as $url) {
            \WP_CLI::line("Processing: {$url}");
            
            $result = $fonts->localize_google_font($url);
            
            if ($result) {
                \WP_CLI::success("✓ Localized successfully");
            } else {
                \WP_CLI::warning("✗ Failed to localize");
            }
        }
    }
    
    /**
     * Estadísticas de fuentes
     */
    private function fonts_stats($fonts) {
        $stats = $fonts->get_fonts_stats();
        
        $table_data = [
            ['Metric', 'Value'],
            ['Total Localized Fonts', $stats['total_localized']],
            ['Total Size', $stats['total_size_formatted']],
            ['Font Families', count($stats['families'])],
            ['Font Files', count($stats['files'])]
        ];
        
        \WP_CLI\Utils\format_items('table', $table_data, ['Metric', 'Value']);
        
        if (!empty($stats['families'])) {
            \WP_CLI::line("\nFont Families:");
            foreach ($stats['families'] as $family) {
                \WP_CLI::line("  - {$family}");
            }
        }
    }
    
    /**
     * Limpiar fuentes no utilizadas
     */
    private function fonts_cleanup($fonts) {
        \WP_CLI::line('Cleaning up unused fonts...');
        $cleaned = $fonts->cleanup_unused_fonts();
        \WP_CLI::success("Cleaned {$cleaned} unused font files");
    }
    
    /**
     * Comandos de warm-up
     * 
     * ## OPTIONS
     * 
     * [--sitemap=<url>]
     * : URL del sitemap XML
     * 
     * [--concurrent=<num>]
     * : Número de requests concurrentes
     * ---
     * default: 3
     * ---
     * 
     * [--delay=<seconds>]
     * : Delay entre requests en segundos
     * ---
     * default: 1
     * ---
     * 
     * ## EXAMPLES
     * 
     *     wp suple-speed warm
     *     wp suple-speed warm --sitemap=https://example.com/sitemap.xml
     *     wp suple-speed warm --concurrent=5 --delay=2
     * 
     * @subcommand run
     */
    public function warm_commands($args, $assoc_args) {
        if (!function_exists('suple_speed')) {
            \WP_CLI::error('Suple Speed plugin not loaded');
        }
        
        $sitemap_url = $assoc_args['sitemap'] ?? home_url('/sitemap.xml');
        $concurrent = intval($assoc_args['concurrent'] ?? 3);
        $delay = intval($assoc_args['delay'] ?? 1);
        
        \WP_CLI::line("Starting cache warm-up...");
        \WP_CLI::line("Sitemap: {$sitemap_url}");
        \WP_CLI::line("Concurrent requests: {$concurrent}");
        \WP_CLI::line("Delay: {$delay}s");
        
        $urls = $this->get_urls_from_sitemap($sitemap_url);
        
        if (empty($urls)) {
            \WP_CLI::error('No URLs found in sitemap');
        }
        
        \WP_CLI::success(sprintf('Found %d URLs to warm up', count($urls)));
        
        $progress = \WP_CLI\Utils\make_progress_bar('Warming cache', count($urls));
        
        $chunks = array_chunk($urls, $concurrent);
        
        foreach ($chunks as $chunk) {
            $requests = [];
            
            // Crear requests concurrentes
            foreach ($chunk as $url) {
                $requests[] = [
                    'url' => $url,
                    'args' => [
                        'timeout' => 30,
                        'blocking' => false,
                        'headers' => [
                            'User-Agent' => 'Suple Speed Cache Warmer'
                        ]
                    ]
                ];
            }
            
            // Ejecutar requests
            $responses = \Requests::request_multiple($requests);
            
            // Actualizar progress bar
            foreach ($chunk as $url) {
                $progress->tick();
            }
            
            // Delay entre chunks
            if ($delay > 0) {
                sleep($delay);
            }
        }
        
        $progress->finish();
        \WP_CLI::success('Cache warm-up completed');
    }
    
    /**
     * Obtener URLs desde sitemap
     */
    private function get_urls_from_sitemap($sitemap_url) {
        $response = wp_remote_get($sitemap_url, [
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            \WP_CLI::error('Failed to fetch sitemap: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $urls = [];
        
        // Parse XML sitemap
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        
        if ($xml === false) {
            \WP_CLI::error('Invalid sitemap XML');
        }
        
        // Sitemap index
        if (isset($xml->sitemap)) {
            foreach ($xml->sitemap as $sitemap) {
                if (isset($sitemap->loc)) {
                    $sub_urls = $this->get_urls_from_sitemap((string)$sitemap->loc);
                    $urls = array_merge($urls, $sub_urls);
                }
            }
        }
        
        // URL entries
        if (isset($xml->url)) {
            foreach ($xml->url as $url_entry) {
                if (isset($url_entry->loc)) {
                    $urls[] = (string)$url_entry->loc;
                }
            }
        }
        
        return array_unique($urls);
    }
    
    /**
     * Comando de estado general
     * 
     * ## EXAMPLES
     * 
     *     wp suple-speed status
     *     wp suple-speed status --format=json
     * 
     * @subcommand overview
     */
    public function status_command($args, $assoc_args) {
        if (!function_exists('suple_speed')) {
            \WP_CLI::error('Suple Speed plugin not loaded');
        }
        
        $format = $assoc_args['format'] ?? 'table';
        
        $admin = suple_speed()->admin;
        $dashboard_data = $admin->get_dashboard_data();
        $settings = $admin->get_current_settings();
        
        $status_data = [
            ['Component', 'Status', 'Details'],
            ['Plugin Version', 'Active', SUPLE_SPEED_VERSION],
            ['Page Cache', $settings['cache_enabled'] ? 'Enabled' : 'Disabled', $dashboard_data['cache_stats']['total_files'] . ' files'],
            ['Assets Optimization', $settings['assets_enabled'] ? 'Enabled' : 'Disabled', 'CSS/JS merging'],
            ['Font Localization', $settings['fonts_local'] ? 'Enabled' : 'Disabled', $dashboard_data['fonts_stats']['total_localized'] . ' fonts'],
            ['Image Optimization', $settings['images_lazy'] ? 'Enabled' : 'Disabled', 'Lazy loading'],
            ['PageSpeed Insights', !empty($settings['psi_api_key']) ? 'Configured' : 'Not configured', 'API integration'],
            ['Safe Mode', $settings['safe_mode'] ? 'Active' : 'Inactive', 'Compatibility mode']
        ];
        
        if ($format === 'json') {
            $json_data = [];
            foreach ($status_data as $i => $row) {
                if ($i === 0) continue; // Skip header
                $json_data[$row[0]] = [
                    'status' => $row[1],
                    'details' => $row[2]
                ];
            }
            \WP_CLI::line(json_encode($json_data, JSON_PRETTY_PRINT));
        } else {
            \WP_CLI\Utils\format_items('table', $status_data, ['Component', 'Status', 'Details']);
        }
    }
    
    /**
     * Comandos de configuración
     * 
     * ## SUBCOMMANDS
     * 
     * get           Obtener valor de configuración
     * set           Establecer valor de configuración
     * list          Listar todas las configuraciones
     * reset         Resetear a valores por defecto
     * 
     * ## OPTIONS
     * 
     * <key>         Clave de configuración
     * <value>       Valor de configuración
     * 
     * ## EXAMPLES
     * 
     *     wp suple-speed settings get cache_enabled
     *     wp suple-speed settings set cache_enabled true
     *     wp suple-speed settings list
     *     wp suple-speed settings reset
     * 
     * @subcommand get
     */
    public function settings_commands($args, $assoc_args) {
        if (!function_exists('suple_speed')) {
            \WP_CLI::error('Suple Speed plugin not loaded');
        }
        
        $subcommand = $args[0] ?? 'list';
        
        switch ($subcommand) {
            case 'get':
                if (!isset($args[1])) {
                    \WP_CLI::error('Please specify a setting key');
                }
                $this->settings_get($args[1]);
                break;
                
            case 'set':
                if (!isset($args[1]) || !isset($args[2])) {
                    \WP_CLI::error('Please specify key and value');
                }
                $this->settings_set($args[1], $args[2]);
                break;
                
            case 'list':
                $this->settings_list();
                break;
                
            case 'reset':
                $this->settings_reset();
                break;
                
            default:
                \WP_CLI::error("Unknown settings subcommand: {$subcommand}");
        }
    }
    
    /**
     * Obtener configuración
     */
    private function settings_get($key) {
        $settings = get_option('suple_speed_settings', []);
        
        if (!isset($settings[$key])) {
            \WP_CLI::error("Setting '{$key}' not found");
        }
        
        $value = $settings[$key];
        
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        } elseif (is_array($value)) {
            $value = json_encode($value);
        }
        
        \WP_CLI::line($value);
    }
    
    /**
     * Establecer configuración
     */
    private function settings_set($key, $value) {
        $settings = get_option('suple_speed_settings', []);
        
        // Convertir valor según tipo
        if ($value === 'true') {
            $value = true;
        } elseif ($value === 'false') {
            $value = false;
        } elseif (is_numeric($value)) {
            $value = is_float($value) ? floatval($value) : intval($value);
        } elseif (preg_match('/^\[.*\]$/', $value)) {
            $value = json_decode($value, true);
        }
        
        $settings[$key] = $value;
        
        update_option('suple_speed_settings', $settings);
        
        \WP_CLI::success("Setting '{$key}' updated");
    }
    
    /**
     * Listar configuraciones
     */
    private function settings_list() {
        $settings = get_option('suple_speed_settings', []);
        
        $table_data = [['Key', 'Value', 'Type']];
        
        foreach ($settings as $key => $value) {
            $type = gettype($value);
            
            if (is_bool($value)) {
                $display_value = $value ? 'true' : 'false';
            } elseif (is_array($value)) {
                $display_value = json_encode($value);
                $type = 'array (' . count($value) . ' items)';
            } elseif (is_string($value) && strlen($value) > 50) {
                $display_value = substr($value, 0, 47) . '...';
            } else {
                $display_value = (string)$value;
            }
            
            $table_data[] = [$key, $display_value, $type];
        }
        
        \WP_CLI\Utils\format_items('table', $table_data, ['Key', 'Value', 'Type']);
    }
    
    /**
     * Resetear configuraciones
     */
    private function settings_reset() {
        if (!\WP_CLI::confirm('This will reset all Suple Speed settings to defaults. Continue?')) {
            \WP_CLI::line('Operation cancelled');
            return;
        }
        
        delete_option('suple_speed_settings');
        
        // Recrear configuraciones por defecto
        if (function_exists('suple_speed')) {
            suple_speed()->reset_default_options();
        }
        
        \WP_CLI::success('Settings reset to defaults');
    }
}
