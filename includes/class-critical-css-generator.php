<?php

namespace SupleSpeed;

use WP_Error;

/**
 * Gestor de generación de Critical CSS mediante API externa.
 */
class Critical_CSS_Generator {
    private const STATUS_OPTION = 'suple_speed_critical_css_status';
    private const JOB_OPTION = 'suple_speed_critical_css_job';
    private const CRON_HOOK = 'suple_speed_process_critical_css_job';

    /**
     * Logger del plugin.
     */
    private $logger;

    public function __construct() {
        if (function_exists('suple_speed')) {
            $this->logger = suple_speed()->logger;
        }

        $this->init_hooks();
    }

    /**
     * Registrar hooks necesarios.
     */
    private function init_hooks() {
        add_action(self::CRON_HOOK, [$this, 'process_job'], 10, 1);
    }

    /**
     * Encolar una nueva generación de Critical CSS.
     *
     * @param string $url URL a analizar.
     * @return array|WP_Error
     */
    public function queue_generation($url) {
        $url = esc_url_raw($url);

        if (empty($url)) {
            return new WP_Error('invalid_url', __('You must provide a valid URL to generate Critical CSS.', 'suple-speed'));
        }

        $status = $this->get_status();
        if (in_array($status['status'], ['pending', 'processing'], true)) {
            return new WP_Error('job_in_progress', __('A Critical CSS job is already running. Please wait for it to finish.', 'suple-speed'));
        }

        $job_id = uniqid('critical_', true);
        $job = [
            'id'         => $job_id,
            'url'        => $url,
            'created_at' => time(),
        ];

        update_option(self::JOB_OPTION, $job, false);

        $queued_status = [
            'id'         => $job_id,
            'url'        => $url,
            'status'     => 'pending',
            'created_at' => current_time('timestamp'),
            'message'    => __('Queued for processing', 'suple-speed'),
            'started_at' => 0,
            'completed_at' => 0,
            'duration'   => 0,
            'css_length' => 0,
            'error_code' => '',
        ];

        $this->update_status($queued_status);

        if (!wp_next_scheduled(self::CRON_HOOK, [$job_id])) {
            wp_schedule_single_event(time() + 5, self::CRON_HOOK, [$job_id]);
        }

        return $queued_status;
    }

    /**
     * Procesar un trabajo encolado mediante WP Cron.
     *
     * @param string $job_id Identificador del trabajo.
     * @return void
     */
    public function process_job($job_id) {
        $job = get_option(self::JOB_OPTION);

        if (empty($job) || !is_array($job) || ($job['id'] ?? '') !== $job_id) {
            return;
        }

        $status = $this->get_status();
        $status['status'] = 'processing';
        $status['started_at'] = current_time('timestamp');
        $status['message'] = __('Generating Critical CSS…', 'suple-speed');
        $status['error_code'] = '';
        $this->update_status($status);

        $result = $this->generate_critical_css($job['url']);

        if (is_wp_error($result)) {
            $status['status'] = 'error';
            $status['completed_at'] = current_time('timestamp');
            $status['duration'] = $this->calculate_duration($status['started_at'], $status['completed_at']);
            $status['message'] = $result->get_error_message();
            $status['error_code'] = $result->get_error_code();
            $this->update_status($status);

            if ($this->logger) {
                $this->logger->error('Critical CSS generation failed', [
                    'url'   => $job['url'],
                    'error' => $result->get_error_message(),
                ], 'critical_css');
            }

            delete_option(self::JOB_OPTION);
            return;
        }

        $status['status'] = 'success';
        $status['completed_at'] = current_time('timestamp');
        $status['duration'] = $this->calculate_duration($status['started_at'], $status['completed_at']);
        $status['message'] = __('Critical CSS generated successfully.', 'suple-speed');
        $status['css_length'] = strlen($result);
        $this->update_status($status);

        $this->store_critical_css($result);

        if ($this->logger) {
            $this->logger->info('Critical CSS generated', [
                'url'    => $job['url'],
                'length' => strlen($result),
            ], 'critical_css');
        }

        delete_option(self::JOB_OPTION);
    }

    /**
     * Obtener el estado actual del generador.
     *
     * @return array
     */
    public function get_status() {
        $defaults = [
            'id'           => '',
            'url'          => '',
            'status'       => 'idle',
            'message'      => '',
            'created_at'   => 0,
            'started_at'   => 0,
            'completed_at' => 0,
            'duration'     => 0,
            'css_length'   => 0,
            'error_code'   => '',
        ];

        $status = get_option(self::STATUS_OPTION, []);

        if (!is_array($status)) {
            $status = [];
        }

        return wp_parse_args($status, $defaults);
    }

    /**
     * Actualizar el estado persistido del generador.
     *
     * @param array $status
     * @return void
     */
    private function update_status(array $status) {
        update_option(self::STATUS_OPTION, $status, false);
    }

    /**
     * Guardar el CSS generado en los ajustes del plugin.
     *
     * @param string $css
     * @return void
     */
    private function store_critical_css($css) {
        $settings = get_option('suple_speed_settings', []);
        $settings['critical_css_general'] = $css;
        update_option('suple_speed_settings', $settings);
    }

    /**
     * Generar el Critical CSS utilizando la API seleccionada.
     *
     * @param string $url URL de la que obtener el CSS crítico.
     * @return string|WP_Error
     */
    private function generate_critical_css($url) {
        /**
         * Permite proporcionar un CSS crítico personalizado antes de llamar a la API externa.
         *
         * @param string|null $css  CSS crítico ya generado. Devuelve null para continuar con la petición.
         * @param string      $url  URL solicitada.
         */
        $precomputed = apply_filters('suple_speed_generate_critical_css', null, $url);
        if (is_string($precomputed) && $precomputed !== '') {
            return $precomputed;
        }

        $api_key = $this->get_api_key();
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', __('No Critical CSS API key has been configured.', 'suple-speed'));
        }

        $endpoint = apply_filters('suple_speed_critical_css_endpoint', 'https://criticalcss.com/api/v1/generate');
        $body = apply_filters('suple_speed_critical_css_payload', [
            'url'      => $url,
            'token'    => $api_key,
            'strategy' => 'mobile',
        ], $url, $api_key);

        $response = wp_remote_post($endpoint, [
            'timeout' => 120,
            'body'    => $body,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('request_failed', sprintf(__('The Critical CSS service request failed: %s', 'suple-speed'), $response->get_error_message()));
        }

        $code = wp_remote_retrieve_response_code($response);
        $payload = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200 || !is_array($payload)) {
            return new WP_Error('invalid_response', __('The Critical CSS service returned an invalid response.', 'suple-speed'));
        }

        $critical_css = $payload['criticalcss'] ?? ($payload['critical_css'] ?? '');

        if (empty($critical_css)) {
            return new WP_Error('empty_payload', __('The Critical CSS service did not return any CSS.', 'suple-speed'));
        }

        /**
         * Permite filtrar el CSS crítico antes de almacenarlo.
         *
         * @param string $critical_css CSS crítico generado.
         * @param string $url          URL solicitada.
         */
        return apply_filters('suple_speed_critical_css_result', $critical_css, $url);
    }

    /**
     * Obtener la API key configurada.
     *
     * @return string
     */
    private function get_api_key() {
        $api_key = '';

        $settings = get_option('suple_speed_settings', []);

        if (!empty($settings['critical_css_api_key'])) {
            $api_key = $settings['critical_css_api_key'];
        }

        if (defined('SUPLE_SPEED_CRITICAL_CSS_API_KEY')) {
            $api_key = SUPLE_SPEED_CRITICAL_CSS_API_KEY;
        }

        /**
         * Permite personalizar la API key utilizada para la generación de Critical CSS.
         *
         * @param string $api_key API key configurada.
         */
        $api_key = apply_filters('suple_speed_critical_css_api_key', $api_key);

        return trim((string) $api_key);
    }

    /**
     * Calcular duración entre dos marcas de tiempo.
     *
     * @param int $start
     * @param int $end
     * @return int
     */
    private function calculate_duration($start, $end) {
        if (empty($start) || empty($end) || $end < $start) {
            return 0;
        }

        return (int) ($end - $start);
    }
}
