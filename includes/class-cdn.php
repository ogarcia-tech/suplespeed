<?php

namespace SupleSpeed;

/**
 * Gestión de integraciones CDN
 */
class CDN {

    /**
     * Logger del sistema
     */
    private $logger;

    public function __construct() {
        if (function_exists('suple_speed')) {
            $this->logger = suple_speed()->logger;
        }
    }

    /**
     * Purga el CDN configurado
     *
     * @param string $type Tipo de purga: all|urls
     * @param array $urls  Lista de URLs a purgar en el CDN
     *
     * @return array
     */
    public function purge($type = 'all', $urls = []) {
        $results = [];
        $providers = $this->get_enabled_providers();

        if (empty($providers)) {
            return $results;
        }

        foreach ($providers as $provider => $config) {
            switch ($provider) {
                case 'cloudflare':
                    $results[$provider] = $this->purge_cloudflare($type, $urls, $config);
                    break;

                case 'bunnycdn':
                    $results[$provider] = $this->purge_bunnycdn($type, $urls, $config);
                    break;
            }
        }

        return $results;
    }

    /**
     * Obtiene los proveedores con credenciales válidas
     */
    private function get_enabled_providers() {
        $settings = get_option('suple_speed_settings', []);
        $providers = $settings['cdn_integrations'] ?? [];

        if (!is_array($providers)) {
            return [];
        }

        $enabled = [];

        foreach ($providers as $provider => $config) {
            if (!is_array($config) || empty($config['enabled'])) {
                continue;
            }

            $enabled[$provider] = $config;
        }

        return $enabled;
    }

    /**
     * Purga Cloudflare
     */
    private function purge_cloudflare($type, $urls, $config) {
        $label = 'Cloudflare';

        $api_token = trim($config['api_token'] ?? '');
        $zone_id = trim($config['zone_id'] ?? '');

        if ($api_token === '' || $zone_id === '') {
            $message = sprintf(__('Missing Cloudflare credentials. Please review your %s configuration.', 'suple-speed'), $label);
            $this->log_warning($label, $message, ['zone_id' => $zone_id]);

            return [
                'provider' => 'cloudflare',
                'label' => $label,
                'success' => false,
                'message' => $message,
            ];
        }

        $endpoint = sprintf('https://api.cloudflare.com/client/v4/zones/%s/purge_cache', rawurlencode($zone_id));
        $payload = $this->build_cloudflare_payload($type, $urls);

        if (empty($payload)) {
            return [
                'provider' => 'cloudflare',
                'label' => $label,
                'success' => true,
                'message' => __('No Cloudflare purge required for the requested action.', 'suple-speed'),
            ];
        }

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_token,
            ],
            'timeout' => 20,
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->log_error($label, 'Cloudflare purge request failed', [
                'error' => $error_message,
                'type' => $type,
            ]);

            return [
                'provider' => 'cloudflare',
                'label' => $label,
                'success' => false,
                'message' => sprintf(__('Cloudflare purge failed: %s', 'suple-speed'), $error_message),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $success = ($status_code >= 200 && $status_code < 300) && ($body['success'] ?? false);

        if ($success) {
            $this->log_info($label, 'Cloudflare purge requested successfully', [
                'type' => $type,
                'urls' => $payload['files'] ?? [],
            ]);

            return [
                'provider' => 'cloudflare',
                'label' => $label,
                'success' => true,
                'message' => __('Cloudflare purge requested successfully.', 'suple-speed'),
            ];
        }

        $error_message = $this->extract_cloudflare_error($body);

        $this->log_error($label, 'Cloudflare purge returned an error', [
            'type' => $type,
            'response_code' => $status_code,
            'response_body' => $body,
        ]);

        return [
            'provider' => 'cloudflare',
            'label' => $label,
            'success' => false,
            'message' => sprintf(__('Cloudflare purge failed: %s', 'suple-speed'), $error_message),
        ];
    }

    /**
     * Construye el payload para Cloudflare
     */
    private function build_cloudflare_payload($type, $urls) {
        if ($type === 'all') {
            return ['purge_everything' => true];
        }

        $prepared_urls = $this->prepare_urls($urls);

        if (empty($prepared_urls)) {
            return [];
        }

        return ['files' => $prepared_urls];
    }

    /**
     * Extrae mensaje de error desde Cloudflare
     */
    private function extract_cloudflare_error($body) {
        if (empty($body)) {
            return __('Unknown error', 'suple-speed');
        }

        if (!empty($body['errors']) && is_array($body['errors'])) {
            $error = $body['errors'][0];

            if (is_array($error)) {
                $message = $error['message'] ?? '';
                $code = $error['code'] ?? '';

                if ($code !== '') {
                    return trim(sprintf('%s (%s)', $message, $code));
                }

                if ($message !== '') {
                    return $message;
                }
            } elseif (is_string($error)) {
                return $error;
            }
        }

        if (!empty($body['messages'])) {
            return implode(', ', array_filter(array_map('strval', (array) $body['messages'])));
        }

        return __('Unknown error', 'suple-speed');
    }

    /**
     * Purga BunnyCDN
     */
    private function purge_bunnycdn($type, $urls, $config) {
        $label = 'BunnyCDN';

        $api_key = trim($config['api_key'] ?? '');
        $zone_id = trim($config['zone_id'] ?? '');

        if ($api_key === '' || $zone_id === '') {
            $message = sprintf(__('Missing BunnyCDN credentials. Please review your %s configuration.', 'suple-speed'), $label);
            $this->log_warning($label, $message, ['zone_id' => $zone_id]);

            return [
                'provider' => 'bunnycdn',
                'label' => $label,
                'success' => false,
                'message' => $message,
            ];
        }

        if ($type === 'all') {
            return $this->purge_bunnycdn_zone($zone_id, $api_key, $label);
        }

        $prepared_urls = $this->prepare_urls($urls);

        if (empty($prepared_urls)) {
            return [
                'provider' => 'bunnycdn',
                'label' => $label,
                'success' => true,
                'message' => __('No BunnyCDN purge required for the requested action.', 'suple-speed'),
            ];
        }

        $errors = [];

        foreach ($prepared_urls as $url) {
            $response = wp_remote_post('https://api.bunny.net/purge', [
                'headers' => [
                    'AccessKey' => $api_key,
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 20,
                'body' => wp_json_encode(['url' => $url]),
            ]);

            if (is_wp_error($response)) {
                $errors[] = $response->get_error_message();
                continue;
            }

            $status_code = wp_remote_retrieve_response_code($response);

            if ($status_code < 200 || $status_code >= 300) {
                $errors[] = sprintf('%s (%d)', $url, $status_code);
            }
        }

        if (empty($errors)) {
            $this->log_info($label, 'BunnyCDN URLs purged successfully', [
                'urls' => $prepared_urls,
            ]);

            return [
                'provider' => 'bunnycdn',
                'label' => $label,
                'success' => true,
                'message' => __('BunnyCDN URLs purged successfully.', 'suple-speed'),
            ];
        }

        $this->log_error($label, 'BunnyCDN URL purge completed with errors', [
            'errors' => $errors,
            'urls' => $prepared_urls,
        ]);

        return [
            'provider' => 'bunnycdn',
            'label' => $label,
            'success' => false,
            'message' => sprintf(__('BunnyCDN purge failed for some URLs: %s', 'suple-speed'), implode(', ', $errors)),
        ];
    }

    /**
     * Purga completa de zona en BunnyCDN
     */
    private function purge_bunnycdn_zone($zone_id, $api_key, $label) {
        $endpoint = sprintf('https://api.bunny.net/pullzone/%s/purgeCache', rawurlencode($zone_id));

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'AccessKey' => $api_key,
            ],
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();

            $this->log_error($label, 'BunnyCDN full purge request failed', [
                'error' => $error_message,
            ]);

            return [
                'provider' => 'bunnycdn',
                'label' => $label,
                'success' => false,
                'message' => sprintf(__('BunnyCDN purge failed: %s', 'suple-speed'), $error_message),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code >= 200 && $status_code < 300) {
            $this->log_info($label, 'BunnyCDN zone purge requested successfully', [
                'zone_id' => $zone_id,
            ]);

            return [
                'provider' => 'bunnycdn',
                'label' => $label,
                'success' => true,
                'message' => __('BunnyCDN purge requested successfully.', 'suple-speed'),
            ];
        }

        $body = wp_remote_retrieve_body($response);

        $this->log_error($label, 'BunnyCDN returned a non-success status', [
            'status_code' => $status_code,
            'response_body' => $body,
        ]);

        return [
            'provider' => 'bunnycdn',
            'label' => $label,
            'success' => false,
            'message' => sprintf(__('BunnyCDN purge failed with status code %d', 'suple-speed'), $status_code),
        ];
    }

    /**
     * Normaliza URLs para purga
     */
    private function prepare_urls($urls) {
        if (!is_array($urls)) {
            $urls = [$urls];
        }

        $prepared = [];

        foreach ($urls as $url) {
            $url = trim((string) $url);

            if ($url === '') {
                continue;
            }

            if (!preg_match('#^https?://#i', $url)) {
                $url = home_url($url);
            }

            $prepared[] = esc_url_raw($url);
        }

        return array_values(array_unique(array_filter($prepared)));
    }

    /**
     * Helper para log info
     */
    private function log_info($provider, $message, $context = []) {
        if ($this->logger) {
            $this->logger->info($message, array_merge($context, [
                'provider' => $provider,
            ]), 'cdn');
        }
    }

    /**
     * Helper para log warning
     */
    private function log_warning($provider, $message, $context = []) {
        if ($this->logger) {
            $this->logger->warning($message, array_merge($context, [
                'provider' => $provider,
            ]), 'cdn');
        }
    }

    /**
     * Helper para log error
     */
    private function log_error($provider, $message, $context = []) {
        if ($this->logger) {
            $this->logger->error($message, array_merge($context, [
                'provider' => $provider,
            ]), 'cdn');
        }
    }
}
