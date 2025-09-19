<?php

namespace SupleSpeed;

/**
 * Database maintenance utilities for Suple Speed
 */
class Database {

    /**
     * WordPress database instance
     *
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Logger module
     *
     * @var Logger|null
     */
    private $logger;

    public function __construct() {
        global $wpdb;

        $this->wpdb = $wpdb;

        if (function_exists('suple_speed')) {
            $plugin = suple_speed();
            if (isset($plugin->logger)) {
                $this->logger = $plugin->logger;
            }
        }
    }

    /**
     * Get database statistics for dashboard summaries.
     */
    public function get_stats() {
        $now = current_time('timestamp');

        $tables = $this->get_tables_status();

        $total_size     = 0;
        $total_overhead = 0;
        $tables_needing_optimization = 0;

        foreach ($tables as $table) {
            $total_size     += $table['size'];
            $total_overhead += $table['overhead'];

            if ($table['overhead'] > 0) {
                $tables_needing_optimization++;
            }
        }

        $stats = [
            'total_revisions'            => $this->count_revisions(),
            'expired_transients'         => $this->count_expired_transients(),
            'total_transients'           => $this->count_total_transients(),
            'database_size'              => $total_size,
            'database_size_formatted'    => size_format($total_size, 2),
            'overhead'                   => $total_overhead,
            'overhead_formatted'         => size_format($total_overhead, 2),
            'total_tables'               => count($tables),
            'tables_needing_optimization'=> $tables_needing_optimization,
            'tables'                     => array_slice($tables, 0, 10),
            'last_revision_cleanup'      => (int) get_option('suple_speed_last_revision_cleanup', 0),
            'last_transients_cleanup'    => (int) get_option('suple_speed_last_transients_cleanup', 0),
            'last_optimization'          => (int) get_option('suple_speed_last_db_optimization', 0),
        ];

        $stats['last_revision_cleanup_human'] = $stats['last_revision_cleanup']
            ? human_time_diff($stats['last_revision_cleanup'], $now)
            : null;

        $stats['last_transients_cleanup_human'] = $stats['last_transients_cleanup']
            ? human_time_diff($stats['last_transients_cleanup'], $now)
            : null;

        $stats['last_optimization_human'] = $stats['last_optimization']
            ? human_time_diff($stats['last_optimization'], $now)
            : null;

        return $stats;
    }

    /**
     * Delete post revisions in batches.
     */
    public function cleanup_revisions($limit = 0) {
        $limit = absint($limit);

        $sql = "SELECT ID FROM {$this->wpdb->posts} WHERE post_type = 'revision'";

        if ($limit > 0) {
            $sql .= $this->wpdb->prepare(' ORDER BY post_modified_gmt ASC LIMIT %d', $limit);
        }

        $revision_ids = $this->wpdb->get_col($sql);
        $deleted      = 0;

        foreach ($revision_ids as $revision_id) {
            $deleted_revision = wp_delete_post_revision((int) $revision_id);

            if ($deleted_revision) {
                $deleted++;
            }
        }

        if ($deleted > 0) {
            update_option('suple_speed_last_revision_cleanup', current_time('timestamp'));
            $this->log('Removed post revisions', [
                'deleted' => $deleted,
                'remaining' => $this->count_revisions(),
            ]);
        }

        return [
            'deleted'   => $deleted,
            'remaining' => $this->count_revisions(),
        ];
    }

    /**
     * Remove expired transients.
     */
    public function cleanup_expired_transients($limit = 0) {
        $limit = absint($limit);
        $now   = current_time('timestamp');

        $expired = $this->get_expired_transient_keys($limit, $now);
        $deleted = 0;

        foreach ($expired as $transient) {
            $type = $transient['type'];
            $key  = $transient['key'];

            if ($type === 'site') {
                if (delete_site_transient($key)) {
                    $deleted++;
                }
            } else {
                if (delete_transient($key)) {
                    $deleted++;
                }
            }
        }

        if ($deleted > 0) {
            update_option('suple_speed_last_transients_cleanup', current_time('timestamp'));
            $this->log('Deleted expired transients', [
                'deleted'   => $deleted,
                'remaining' => $this->count_expired_transients(),
            ]);
        }

        return [
            'deleted'   => $deleted,
            'remaining' => $this->count_expired_transients(),
        ];
    }

    /**
     * Optimize database tables.
     */
    public function optimize_tables(array $tables = [], $only_overhead = true) {
        $status = $this->get_tables_status();

        $allowed_tables = wp_list_pluck($status, 'name');

        if (!empty($tables)) {
            $requested = array_map(function($table) {
                $table = trim($table);
                $table = str_replace(['`', ';'], '', $table);
                return $table;
            }, $tables);

            $tables_to_optimize = array_values(array_intersect($allowed_tables, $requested));
        } else {
            $tables_to_optimize = $allowed_tables;
        }

        if ($only_overhead) {
            $tables_to_optimize = array_values(array_filter($tables_to_optimize, function($table_name) use ($status) {
                foreach ($status as $table) {
                    if ($table['name'] === $table_name && $table['overhead'] > 0) {
                        return true;
                    }
                }

                return false;
            }));
        }

        if (empty($tables_to_optimize)) {
            return [
                'optimized' => 0,
                'tables'    => [],
            ];
        }

        $optimized = 0;

        foreach ($tables_to_optimize as $table_name) {
            $result = $this->wpdb->query("OPTIMIZE TABLE `{$table_name}`");

            if ($result !== false) {
                $optimized++;
            }
        }

        if ($optimized > 0) {
            update_option('suple_speed_last_db_optimization', current_time('timestamp'));
            $this->log('Optimized database tables', [
                'optimized' => $optimized,
                'tables'    => $tables_to_optimize,
            ]);
        }

        return [
            'optimized' => $optimized,
            'tables'    => $tables_to_optimize,
        ];
    }

    /**
     * Count total revisions.
     */
    private function count_revisions() {
        return (int) $this->wpdb->get_var("SELECT COUNT(ID) FROM {$this->wpdb->posts} WHERE post_type = 'revision'");
    }

    /**
     * Count expired transients.
     */
    private function count_expired_transients() {
        $now = current_time('timestamp');

        $option_count = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
            '_transient_timeout_%',
            $now
        ));

        if (!is_multisite()) {
            return $option_count;
        }

        $site_count = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->wpdb->sitemeta} WHERE meta_key LIKE %s AND meta_value < %d",
            '_site_transient_timeout_%',
            $now
        ));

        return $option_count + $site_count;
    }

    /**
     * Count total transients (including network transients).
     */
    private function count_total_transients() {
        $option_count = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->wpdb->options} WHERE option_name LIKE '_transient_%'"
        );

        if (!is_multisite()) {
            return $option_count;
        }

        $site_count = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_%'"
        );

        return $option_count + $site_count;
    }

    /**
     * Retrieve detailed status of WordPress tables.
     */
    private function get_tables_status() {
        $like = $this->wpdb->esc_like($this->wpdb->prefix) . '%';

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare('SHOW TABLE STATUS LIKE %s', $like)
        );

        if (empty($results)) {
            return [];
        }

        $tables = [];

        foreach ($results as $row) {
            $data_length  = isset($row->Data_length) ? (float) $row->Data_length : 0;
            $index_length = isset($row->Index_length) ? (float) $row->Index_length : 0;
            $data_free    = isset($row->Data_free) ? (float) $row->Data_free : 0;

            $size = $data_length + $index_length;

            $tables[] = [
                'name'                => $row->Name,
                'engine'              => $row->Engine,
                'rows'                => isset($row->Rows) ? (int) $row->Rows : 0,
                'size'                => $size,
                'size_formatted'      => size_format($size, 2),
                'overhead'            => $data_free,
                'overhead_formatted'  => size_format($data_free, 2),
                'needs_optimization'  => $data_free > 0,
            ];
        }

        usort($tables, function($a, $b) {
            if ($a['size'] === $b['size']) {
                return 0;
            }

            return ($a['size'] > $b['size']) ? -1 : 1;
        });

        return $tables;
    }

    /**
     * Get expired transient keys (option and site option).
     */
    private function get_expired_transient_keys($limit, $now) {
        $keys = [];

        if ($limit > 0) {
            $sql = $this->wpdb->prepare(
                "SELECT option_name FROM {$this->wpdb->options} WHERE option_name LIKE %s AND option_value < %d LIMIT %d",
                '_transient_timeout_%',
                $now,
                $limit
            );
        } else {
            $sql = $this->wpdb->prepare(
                "SELECT option_name FROM {$this->wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
                '_transient_timeout_%',
                $now
            );
        }

        $option_keys = $this->wpdb->get_col($sql);

        foreach ($option_keys as $option_name) {
            $keys[] = [
                'type' => 'single',
                'key'  => substr($option_name, strlen('_transient_timeout_')),
            ];
        }

        if (is_multisite()) {
            if ($limit > 0) {
                $site_sql = $this->wpdb->prepare(
                    "SELECT meta_key FROM {$this->wpdb->sitemeta} WHERE meta_key LIKE %s AND meta_value < %d LIMIT %d",
                    '_site_transient_timeout_%',
                    $now,
                    max(0, $limit - count($keys))
                );
            } else {
                $site_sql = $this->wpdb->prepare(
                    "SELECT meta_key FROM {$this->wpdb->sitemeta} WHERE meta_key LIKE %s AND meta_value < %d",
                    '_site_transient_timeout_%',
                    $now
                );
            }

            $site_keys = $this->wpdb->get_col($site_sql);

            foreach ($site_keys as $meta_key) {
                $keys[] = [
                    'type' => 'site',
                    'key'  => substr($meta_key, strlen('_site_transient_timeout_')),
                ];
            }
        }

        return $keys;
    }

    /**
     * Helper to log actions.
     */
    private function log($message, array $context = []) {
        if ($this->logger && method_exists($this->logger, 'info')) {
            $this->logger->info($message, $context, 'database');
        }
    }
}
