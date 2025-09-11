<?php

namespace SupleSpeed;

/**
 * Clase Logger para sistema de logs y métricas
 */
class Logger {
    
    /**
     * Niveles de log
     */
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';
    
    /**
     * Configuración
     */
    private $log_level;
    private $max_log_entries = 1000;
    private $log_retention_days = 30;
    
    public function __construct() {
        $settings = get_option('suple_speed_settings', []);
        $this->log_level = $settings['log_level'] ?? self::LEVEL_INFO;
        
        add_action('suple_speed_cleanup_logs', [$this, 'cleanup_old_logs']);
    }
    
    /**
     * Escribir log
     */
    public function log($level, $message, $context = [], $module = 'general') {
        if (!$this->should_log($level)) {
            return;
        }
        
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'suple_speed_logs';
        
        $wpdb->insert(
            $table_name,
            [
                'level' => $level,
                'module' => $module,
                'message' => $message,
                'context' => json_encode($context),
                'url' => $this->get_current_url(),
                'user_id' => get_current_user_id(),
                'timestamp' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s', '%d', '%s']
        );
        
        // También log al error_log de PHP para casos críticos
        if (in_array($level, [self::LEVEL_ERROR, self::LEVEL_CRITICAL])) {
            error_log("Suple Speed [{$level}] [{$module}]: {$message}");
        }
        
        // Limpiar logs antiguos periódicamente
        $this->maybe_cleanup_logs();
    }
    
    /**
     * Log de debug
     */
    public function debug($message, $context = [], $module = 'general') {
        $this->log(self::LEVEL_DEBUG, $message, $context, $module);
    }
    
    /**
     * Log de info
     */
    public function info($message, $context = [], $module = 'general') {
        $this->log(self::LEVEL_INFO, $message, $context, $module);
    }
    
    /**
     * Log de warning
     */
    public function warning($message, $context = [], $module = 'general') {
        $this->log(self::LEVEL_WARNING, $message, $context, $module);
    }
    
    /**
     * Log de error
     */
    public function error($message, $context = [], $module = 'general') {
        $this->log(self::LEVEL_ERROR, $message, $context, $module);
    }
    
    /**
     * Log crítico
     */
    public function critical($message, $context = [], $module = 'general') {
        $this->log(self::LEVEL_CRITICAL, $message, $context, $module);
    }
    
    /**
     * Verificar si debe hacer log según el nivel
     */
    private function should_log($level) {
        $levels = [
            self::LEVEL_DEBUG => 0,
            self::LEVEL_INFO => 1,
            self::LEVEL_WARNING => 2,
            self::LEVEL_ERROR => 3,
            self::LEVEL_CRITICAL => 4
        ];
        
        $current_level = $levels[$this->log_level] ?? 1;
        $message_level = $levels[$level] ?? 1;
        
        return $message_level >= $current_level;
    }
    
    /**
     * Obtener URL actual
     */
    private function get_current_url() {
        if (isset($_SERVER['REQUEST_URI'])) {
            return home_url($_SERVER['REQUEST_URI']);
        }
        
        return wp_get_referer() ?: home_url();
    }
    
    /**
     * Limpiar logs periódicamente
     */
    private function maybe_cleanup_logs() {
        // Solo hacer limpieza ocasionalmente para no sobrecargar
        if (rand(1, 100) === 1) {
            $this->cleanup_old_logs();
        }
    }
    
    /**
     * Limpiar logs antiguos
     */
    public function cleanup_old_logs() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'suple_speed_logs';
        
        // Eliminar logs más antiguos que el período de retención
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$this->log_retention_days} days"));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} WHERE timestamp < %s",
            $cutoff_date
        ));
        
        // Mantener solo los últimos N logs si hay demasiados
        $total_logs = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        
        if ($total_logs > $this->max_log_entries) {
            $excess = $total_logs - $this->max_log_entries;
            
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$table_name} ORDER BY timestamp ASC LIMIT %d",
                $excess
            ));
        }
        
        $this->info('Log cleanup completed', [
            'logs_before' => $total_logs,
            'retention_days' => $this->log_retention_days
        ], 'logger');
    }
    
    /**
     * Obtener logs para administración
     */
    public function get_logs($limit = 100, $offset = 0, $level = null, $module = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'suple_speed_logs';
        
        $where_conditions = ['1=1'];
        $where_values = [];
        
        if ($level) {
            $where_conditions[] = 'level = %s';
            $where_values[] = $level;
        }
        
        if ($module) {
            $where_conditions[] = 'module = %s';
            $where_values[] = $module;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY timestamp DESC LIMIT %d OFFSET %d";
        $where_values[] = $limit;
        $where_values[] = $offset;
        
        return $wpdb->get_results($wpdb->prepare($query, $where_values));
    }
    
    /**
     * Obtener estadísticas de logs
     */
    public function get_log_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'suple_speed_logs';
        
        $stats = [];
        
        // Total logs
        $stats['total'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        
        // Logs por nivel en las últimas 24 horas
        $stats['by_level'] = $wpdb->get_results("
            SELECT level, COUNT(*) as count 
            FROM {$table_name} 
            WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY level
        ");
        
        // Logs por módulo en las últimas 24 horas
        $stats['by_module'] = $wpdb->get_results("
            SELECT module, COUNT(*) as count 
            FROM {$table_name} 
            WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY module 
            ORDER BY count DESC
            LIMIT 10
        ");
        
        return $stats;
    }
    
    /**
     * AJAX: Obtener logs
     */
    public function ajax_get_logs() {
        check_ajax_referer('suple_speed_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $page = intval($_POST['page'] ?? 1);
        $per_page = intval($_POST['per_page'] ?? 50);
        $level = sanitize_text_field($_POST['level'] ?? '');
        $module = sanitize_text_field($_POST['module'] ?? '');
        
        $offset = ($page - 1) * $per_page;
        
        $logs = $this->get_logs($per_page, $offset, $level ?: null, $module ?: null);
        $stats = $this->get_log_stats();
        
        wp_send_json_success([
            'logs' => $logs,
            'stats' => $stats
        ]);
    }
    
    /**
     * Registrar métricas de Web Vitals
     */
    public function log_web_vitals($request) {
        $params = $request->get_params();
        
        $vitals_data = [
            'url' => sanitize_url($params['url']),
            'lcp' => floatval($params['lcp'] ?? 0),
            'inp' => floatval($params['inp'] ?? 0), 
            'cls' => floatval($params['cls'] ?? 0),
            'fcp' => floatval($params['fcp'] ?? 0),
            'ttfb' => floatval($params['ttfb'] ?? 0),
            'timestamp' => time(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
        
        // Guardar métricas en transients organizados por URL
        $url_hash = md5($vitals_data['url']);
        $existing_data = get_transient("suple_speed_vitals_{$url_hash}") ?: [];
        
        // Mantener solo las últimas 10 mediciones por URL
        $existing_data[] = $vitals_data;
        $existing_data = array_slice($existing_data, -10);
        
        set_transient("suple_speed_vitals_{$url_hash}", $existing_data, DAY_IN_SECONDS * 7);
        
        $this->info('Web Vitals logged', [
            'url' => $vitals_data['url'],
            'lcp' => $vitals_data['lcp'],
            'inp' => $vitals_data['inp'],
            'cls' => $vitals_data['cls']
        ], 'vitals');
        
        return new \WP_REST_Response(['success' => true], 200);
    }
    
    /**
     * Obtener métricas de Web Vitals por URL
     */
    public function get_web_vitals($url) {
        $url_hash = md5($url);
        return get_transient("suple_speed_vitals_{$url_hash}") ?: [];
    }
    
    /**
     * Obtener resumen de Web Vitals del sitio
     */
    public function get_vitals_summary() {
        global $wpdb;
        
        // Obtener todas las métricas de los últimos 7 días
        $transients = $wpdb->get_results(
            "SELECT option_name, option_value 
             FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_suple_speed_vitals_%'"
        );
        
        $summary = [
            'total_measurements' => 0,
            'unique_urls' => 0,
            'avg_lcp' => 0,
            'avg_inp' => 0,
            'avg_cls' => 0,
            'avg_fcp' => 0,
            'avg_ttfb' => 0
        ];
        
        $all_measurements = [];
        
        foreach ($transients as $transient) {
            $data = maybe_unserialize($transient->option_value);
            if (is_array($data)) {
                $all_measurements = array_merge($all_measurements, $data);
                $summary['unique_urls']++;
            }
        }
        
        $summary['total_measurements'] = count($all_measurements);
        
        if ($summary['total_measurements'] > 0) {
            $totals = array_reduce($all_measurements, function($carry, $item) {
                $carry['lcp'] += $item['lcp'];
                $carry['inp'] += $item['inp'];
                $carry['cls'] += $item['cls'];
                $carry['fcp'] += $item['fcp'];
                $carry['ttfb'] += $item['ttfb'];
                return $carry;
            }, ['lcp' => 0, 'inp' => 0, 'cls' => 0, 'fcp' => 0, 'ttfb' => 0]);
            
            $count = $summary['total_measurements'];
            $summary['avg_lcp'] = round($totals['lcp'] / $count, 2);
            $summary['avg_inp'] = round($totals['inp'] / $count, 2);
            $summary['avg_cls'] = round($totals['cls'] / $count, 3);
            $summary['avg_fcp'] = round($totals['fcp'] / $count, 2);
            $summary['avg_ttfb'] = round($totals['ttfb'] / $count, 2);
        }
        
        return $summary;
    }
    
    /**
     * Limpiar logs específicos
     */
    public function clear_logs($level = null, $module = null, $days = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'suple_speed_logs';
        
        $where_conditions = ['1=1'];
        $where_values = [];
        
        if ($level) {
            $where_conditions[] = 'level = %s';
            $where_values[] = $level;
        }
        
        if ($module) {
            $where_conditions[] = 'module = %s';
            $where_values[] = $module;
        }
        
        if ($days) {
            $where_conditions[] = 'timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)';
            $where_values[] = $days;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        if (!empty($where_values)) {
            $query = "DELETE FROM {$table_name} WHERE {$where_clause}";
            return $wpdb->query($wpdb->prepare($query, $where_values));
        } else {
            // Si no hay condiciones, limpiar todo
            return $wpdb->query("TRUNCATE TABLE {$table_name}");
        }
    }
    
    /**
     * Exportar logs
     */
    public function export_logs($format = 'json', $level = null, $module = null, $days = 7) {
        $logs = $this->get_logs(10000, 0, $level, $module);
        
        // Filtrar por días si se especifica
        if ($days) {
            $cutoff = time() - ($days * DAY_IN_SECONDS);
            $logs = array_filter($logs, function($log) use ($cutoff) {
                return strtotime($log->timestamp) >= $cutoff;
            });
        }
        
        switch ($format) {
            case 'csv':
                return $this->logs_to_csv($logs);
            case 'txt':
                return $this->logs_to_txt($logs);
            default:
                return json_encode($logs, JSON_PRETTY_PRINT);
        }
    }
    
    /**
     * Convertir logs a CSV
     */
    private function logs_to_csv($logs) {
        if (empty($logs)) {
            return '';
        }
        
        $csv = "Timestamp,Level,Module,Message,URL,User ID\n";
        
        foreach ($logs as $log) {
            $csv .= sprintf(
                '"%s","%s","%s","%s","%s","%s"' . "\n",
                $log->timestamp,
                $log->level,
                $log->module,
                str_replace('"', '""', $log->message),
                $log->url,
                $log->user_id
            );
        }
        
        return $csv;
    }
    
    /**
     * Convertir logs a texto plano
     */
    private function logs_to_txt($logs) {
        if (empty($logs)) {
            return '';
        }
        
        $txt = "Suple Speed - Log Export\n";
        $txt .= "Generated: " . date('Y-m-d H:i:s') . "\n";
        $txt .= str_repeat('=', 50) . "\n\n";
        
        foreach ($logs as $log) {
            $txt .= "[{$log->timestamp}] [{$log->level}] [{$log->module}] {$log->message}\n";
            if ($log->url) {
                $txt .= "  URL: {$log->url}\n";
            }
            if ($log->context && $log->context !== '[]') {
                $txt .= "  Context: {$log->context}\n";
            }
            $txt .= "\n";
        }
        
        return $txt;
    }
}