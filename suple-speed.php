<?php
/**
 * Plugin Name: Suple Speed – Optimización Inteligente
 * Plugin URI: https://suple.com/speed
 * Description: Optimiza tu WordPress (especialmente con Elementor) para subir tu PageSpeed sin dolores de cabeza. Caché a disco, compresión, fusión inteligente de JS/CSS, Critical CSS, fuentes locales y más.
 * Version: 1.0.0
 * Author: Suple
 * Author URI: https://suple.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: suple-speed
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 8.0
 * Network: true
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
define('SUPLE_SPEED_VERSION', '1.0.0');
define('SUPLE_SPEED_PLUGIN_FILE', __FILE__);
define('SUPLE_SPEED_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SUPLE_SPEED_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SUPLE_SPEED_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('SUPLE_SPEED_CACHE_DIR', WP_CONTENT_DIR . '/cache/suple-speed/');
define('SUPLE_SPEED_UPLOADS_DIR', wp_upload_dir()['basedir'] . '/suple-speed/');

/**
 * Clase principal del plugin Suple Speed
 */
class SupleSpeed {
    
    /**
     * Instancia única del plugin
     */
    private static $instance = null;
    
    /**
     * Módulos del plugin
     */
    public $admin;
    public $cache;
    public $assets;
    public $fonts;
    public $images;
    public $psi;
    public $rules;
    public $logger;
    public $elementor_guard;
    public $compat;
    public $wp_cli;
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Obtener instancia única
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        add_action('plugins_loaded', [$this, 'init']);
        add_action('init', [$this, 'load_textdomain']);
        
        // Hooks de activación y desactivación
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        register_uninstall_hook(__FILE__, [__CLASS__, 'uninstall']);
        
        // Hook para verificar compatibilidad
        add_action('admin_init', [$this, 'check_compatibility']);
    }
    
    /**
     * Inicializar el plugin
     */
    public function init() {
        // Verificar compatibilidad
        if (!$this->is_compatible()) {
            return;
        }
        
        // Cargar autoloader
        $this->load_autoloader();
        
        // Inicializar módulos
        $this->init_modules();
        
        // Hooks principales del plugin
        $this->init_main_hooks();
    }
    
    /**
     * Cargar autoloader
     */
    private function load_autoloader() {
        spl_autoload_register([$this, 'autoloader']);
    }
    
    /**
     * Autoloader del plugin
     */
    public function autoloader($class_name) {
        if (strpos($class_name, 'SupleSpeed\\') !== 0) {
            return;
        }
        
        $class_name = str_replace('SupleSpeed\\', '', $class_name);
        $class_file = SUPLE_SPEED_PLUGIN_DIR . 'includes/class-' . 
                      strtolower(str_replace('_', '-', $class_name)) . '.php';
        
        if (file_exists($class_file)) {
            require_once $class_file;
        }
    }
    
    /**
     * Inicializar módulos
     */
    private function init_modules() {
        // Cargar clases principales
        require_once SUPLE_SPEED_PLUGIN_DIR . 'includes/class-logger.php';
        require_once SUPLE_SPEED_PLUGIN_DIR . 'includes/class-compat.php';
        require_once SUPLE_SPEED_PLUGIN_DIR . 'includes/class-elementor-guard.php';
        require_once SUPLE_SPEED_PLUGIN_DIR . 'includes/class-rules.php';
        require_once SUPLE_SPEED_PLUGIN_DIR . 'includes/class-cache.php';
        require_once SUPLE_SPEED_PLUGIN_DIR . 'includes/class-assets.php';
        require_once SUPLE_SPEED_PLUGIN_DIR . 'includes/class-fonts.php';
        require_once SUPLE_SPEED_PLUGIN_DIR . 'includes/class-images.php';
        require_once SUPLE_SPEED_PLUGIN_DIR . 'includes/class-psi.php';
        require_once SUPLE_SPEED_PLUGIN_DIR . 'includes/class-admin.php';
        
        // Inicializar módulos
        $this->logger = new SupleSpeed\Logger();
        $this->compat = new SupleSpeed\Compat();
        $this->elementor_guard = new SupleSpeed\ElementorGuard();
        $this->rules = new SupleSpeed\Rules();
        $this->cache = new SupleSpeed\Cache();
        $this->assets = new SupleSpeed\Assets();
        $this->fonts = new SupleSpeed\Fonts();
        $this->images = new SupleSpeed\Images();
        $this->psi = new SupleSpeed\PSI();
        
        // Admin solo en el backend
        if (is_admin()) {
            $this->admin = new SupleSpeed\Admin();
        }
        
        // WP-CLI commands
        if (defined('WP_CLI') && WP_CLI) {
            require_once SUPLE_SPEED_PLUGIN_DIR . 'includes/class-wp-cli.php';
            $this->wp_cli = new SupleSpeed\WP_CLI();
        }
    }
    
    /**
     * Inicializar hooks principales
     */
    private function init_main_hooks() {
        // Solo ejecutar optimizaciones en frontend si no estamos en modo editor
        if (!is_admin() && !$this->elementor_guard->is_editor_mode()) {
            add_action('template_redirect', [$this->cache, 'serve_cache'], 0);
            add_action('template_redirect', [$this, 'start_output_buffering'], 1);
            add_action('wp_enqueue_scripts', [$this->assets, 'optimize_scripts_styles'], 999);
            add_action('wp_head', [$this->assets, 'inject_critical_css'], 1);
            add_action('wp_head', [$this->assets, 'inject_preloads'], 2);
        }
        
        // Hooks de purga de caché
        add_action('save_post', [$this->cache, 'purge_post_cache']);
        add_action('deleted_post', [$this->cache, 'purge_post_cache']);
        add_action('switch_theme', [$this->cache, 'purge_all']);
        add_action('customize_save_after', [$this->cache, 'purge_all']);
        add_action('update_option', [$this->cache, 'maybe_purge_on_option_update'], 10, 2);
        
        // AJAX hooks
        add_action('wp_ajax_suple_speed_purge_cache', [$this->cache, 'ajax_purge_cache']);
        add_action('wp_ajax_suple_speed_run_psi', [$this->psi, 'ajax_run_test']);
        add_action('wp_ajax_suple_speed_get_logs', [$this->logger, 'ajax_get_logs']);
        
        // REST API
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }
    
    /**
     * Iniciar captura de salida para caché de página
     */
    public function start_output_buffering() {
        if ($this->cache->should_cache_page()) {
            ob_start([$this->cache, 'process_page_output']);
        }
    }
    
    /**
     * Registrar rutas REST API
     */
    public function register_rest_routes() {
        // Endpoint para Web Vitals beacon
        register_rest_route('suple-speed/v1', '/vitals', [
            'methods' => 'POST',
            'callback' => [$this->logger, 'log_web_vitals'],
            'permission_callback' => '__return_true',
            'args' => [
                'url' => ['required' => true],
                'lcp' => ['required' => false],
                'inp' => ['required' => false], 
                'cls' => ['required' => false],
                'fcp' => ['required' => false],
                'ttfb' => ['required' => false]
            ]
        ]);
        
        // Endpoint para purgar caché específica
        register_rest_route('suple-speed/v1', '/cache/purge', [
            'methods' => 'POST',
            'callback' => [$this->cache, 'rest_purge_cache'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
    }
    
    /**
     * Cargar textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'suple-speed',
            false,
            dirname(SUPLE_SPEED_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Verificar compatibilidad del sistema
     */
    private function is_compatible() {
        // Verificar versión PHP
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            return false;
        }
        
        // Verificar versión WordPress
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Verificar compatibilidad y mostrar avisos
     */
    public function check_compatibility() {
        if (!$this->is_compatible()) {
            add_action('admin_notices', [$this, 'compatibility_notice']);
            
            // Desactivar plugin si no es compatible
            if (is_plugin_active(SUPLE_SPEED_PLUGIN_BASENAME)) {
                deactivate_plugins(SUPLE_SPEED_PLUGIN_BASENAME);
            }
        }
    }
    
    /**
     * Mostrar aviso de compatibilidad
     */
    public function compatibility_notice() {
        echo '<div class="notice notice-error">';
        echo '<p><strong>' . esc_html__('Suple Speed', 'suple-speed') . '</strong>: ';
        echo esc_html__('Este plugin requiere PHP 8.0+ y WordPress 5.0+. Por favor actualiza tu sistema.', 'suple-speed');
        echo '</p></div>';
    }
    
    /**
     * Activación del plugin
     */
    public function activate() {
        // Crear directorios necesarios
        $this->create_directories();
        
        // Crear tablas de base de datos si es necesario
        $this->create_database_tables();
        
        // Configuración inicial
        $this->set_default_options();
        
        // Generar reglas de servidor si es posible
        $this->generate_server_rules();
        
        // Programar eventos cron
        $this->schedule_cron_events();
        
        // Log de activación
        error_log('Suple Speed Plugin activated successfully');
    }
    
    /**
     * Desactivación del plugin
     */
    public function deactivate() {
        // Limpiar eventos cron
        $this->clear_cron_events();
        
        // Opcional: mantener caché y configuración para reactivación
        // $this->cleanup_files();
        
        error_log('Suple Speed Plugin deactivated');
    }
    
    /**
     * Desinstalación del plugin
     */
    public static function uninstall() {
        // Limpiar opciones
        delete_option('suple_speed_settings');
        global $wpdb;

        delete_option('suple_speed_rules');
        delete_option('suple_speed_psi_data');
        delete_option('suple_speed_version');
        delete_option('suple_speed_onboarding');

        // Limpiar cualquier otro estado de onboarding almacenado
        $onboarding_like = $wpdb->esc_like('suple_speed_onboarding_') . '%';
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $onboarding_like
            )
        );
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
                $onboarding_like
            )
        );

        // Limpiar transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_suple_speed_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_suple_speed_%'");
        
        // Limpiar archivos de caché
        self::cleanup_cache_files();
        
        error_log('Suple Speed Plugin uninstalled completely');
    }
    
    /**
     * Crear directorios necesarios
     */
    private function create_directories() {
        $directories = [
            SUPLE_SPEED_CACHE_DIR,
            SUPLE_SPEED_CACHE_DIR . 'html/',
            SUPLE_SPEED_CACHE_DIR . 'assets/',
            SUPLE_SPEED_UPLOADS_DIR,
            SUPLE_SPEED_UPLOADS_DIR . 'fonts/',
            SUPLE_SPEED_UPLOADS_DIR . 'critical/',
            SUPLE_SPEED_UPLOADS_DIR . 'logs/'
        ];
        
        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
                
                // Crear archivo .htaccess para proteger directorios sensibles
                if (strpos($dir, '/logs/') !== false) {
                    file_put_contents($dir . '.htaccess', "Deny from all\n");
                }
            }
        }
    }
    
    /**
     * Crear tablas de base de datos
     */
    private function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabla para logs detallados
        $table_name = $wpdb->prefix . 'suple_speed_logs';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            level varchar(20) NOT NULL,
            module varchar(50) NOT NULL,
            message text NOT NULL,
            context longtext,
            url varchar(500),
            user_id bigint(20),
            PRIMARY KEY (id),
            KEY level (level),
            KEY module (module),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
    
    /**
     * Configurar opciones por defecto
     */
    private function set_default_options() {
        $default_settings = [
            'cache_enabled' => true,
            'cache_ttl' => 24 * HOUR_IN_SECONDS,
            'compression_enabled' => true,
            'assets_enabled' => true,
            'merge_css' => true,
            'merge_js' => true,
            'minify_css' => true,
            'minify_js' => true,
            'defer_js' => true,
            'critical_css_enabled' => false,
            'fonts_local' => true,
            'images_lazy' => true,
            'psi_api_key' => '',
            'elementor_compat' => true,
            'safe_mode' => false,
            'log_level' => 'info',
            'multisite_network' => false
        ];
        
        add_option('suple_speed_settings', $default_settings);
        add_option('suple_speed_version', SUPLE_SPEED_VERSION);
        add_option('suple_speed_rules', []);
        add_option('suple_speed_onboarding', []);
    }

    /**
     * Permite recrear las opciones por defecto desde otros componentes.
     */
    public function reset_default_options() {
        $this->set_default_options();
    }
    
    /**
     * Generar reglas de servidor
     */
    private function generate_server_rules() {
        // Intentar generar .htaccess para Apache
        $this->generate_htaccess_rules();

        // Guardar reglas de Nginx para mostrar en admin
        $this->generate_nginx_rules();
    }

    /**
     * Obtener instancia del logger incluso durante la activación
     */
    private function get_logger() {
        if ($this->logger instanceof \SupleSpeed\Logger) {
            return $this->logger;
        }

        if (!class_exists('\\SupleSpeed\\Logger')) {
            $logger_file = SUPLE_SPEED_PLUGIN_DIR . 'includes/class-logger.php';

            if (file_exists($logger_file)) {
                require_once $logger_file;
            }
        }

        if (class_exists('\\SupleSpeed\\Logger')) {
            $this->logger = new \SupleSpeed\Logger();
            return $this->logger;
        }

        return null;
    }

    /**
     * Generar reglas .htaccess
     */
    private function generate_htaccess_rules() {
        $htaccess_file = ABSPATH . '.htaccess';
        $logger = $this->get_logger();
        $context = ['file' => $htaccess_file];
        $directory = dirname($htaccess_file);

        $can_write = file_exists($htaccess_file)
            ? is_writable($htaccess_file)
            : is_writable($directory);

        if (!$can_write) {
            if ($logger) {
                $logger->error('No se puede escribir en el archivo .htaccess.', $context, 'rules');
            }
            return;
        }

        $rules = $this->get_htaccess_rules();
        $htaccess_content = '';

        if (file_exists($htaccess_file)) {
            $existing_content = file_get_contents($htaccess_file);

            if ($existing_content === false) {
                if ($logger) {
                    $logger->error('No se pudo leer el archivo .htaccess existente.', $context, 'rules');
                }
                return;
            }

            $htaccess_content = $existing_content;
        }

        // Remover reglas existentes de Suple Speed
        $htaccess_content = preg_replace(
            '/# BEGIN Suple Speed.*?# END Suple Speed\s*/s',
            '',
            $htaccess_content
        );

        // Añadir nuevas reglas al inicio
        $new_content = "# BEGIN Suple Speed\n" . $rules . "\n# END Suple Speed\n\n" . $htaccess_content;

        if (file_put_contents($htaccess_file, $new_content) === false) {
            if ($logger) {
                $logger->error('No se pudo escribir en el archivo .htaccess.', $context, 'rules');
            }
        }
    }
    
    /**
     * Obtener reglas .htaccess
     */
    private function get_htaccess_rules() {
        return '# Compresión Gzip y Brotli
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE text/javascript
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
    AddOutputFilterByType DEFLATE application/json
    AddOutputFilterByType DEFLATE application/ld+json
    AddOutputFilterByType DEFLATE image/svg+xml
</IfModule>

<IfModule mod_brotli.c>
    BrotliCompressionQuality 6
    BrotliFilterByType text/plain
    BrotliFilterByType text/html
    BrotliFilterByType text/xml
    BrotliFilterByType text/css
    BrotliFilterByType text/javascript
    BrotliFilterByType application/xml
    BrotliFilterByType application/xhtml+xml
    BrotliFilterByType application/rss+xml
    BrotliFilterByType application/javascript
    BrotliFilterByType application/x-javascript
    BrotliFilterByType application/json
    BrotliFilterByType application/ld+json
    BrotliFilterByType image/svg+xml
</IfModule>

# Cache Headers
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 year"
    ExpiresByType application/javascript "access plus 1 year"
    ExpiresByType text/javascript "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
    ExpiresByType application/pdf "access plus 1 month"
    ExpiresByType image/x-icon "access plus 1 year"
</IfModule>

<IfModule mod_headers.c>
    <FilesMatch "\.(css|js|png|jpg|jpeg|gif|svg|ico|pdf|woff|woff2|ttf|eot)$">
        Header set Cache-Control "public, max-age=31536000, immutable"
    </FilesMatch>
    
    <FilesMatch "\.(html|htm)$">
        Header set Cache-Control "public, max-age=3600, must-revalidate"
    </FilesMatch>
</IfModule>';
    }
    
    /**
     * Generar reglas Nginx
     */
    private function generate_nginx_rules() {
        $nginx_rules = '# Suple Speed - Nginx Configuration
        
# Compresión Gzip
gzip on;
gzip_vary on;
gzip_min_length 1024;
gzip_comp_level 6;
gzip_types
    text/plain
    text/css
    text/xml
    text/javascript
    application/xml+rss
    application/javascript
    application/json
    application/xml
    image/svg+xml;

# Compresión Brotli (si está disponible)
brotli on;
brotli_comp_level 6;
brotli_types
    text/plain
    text/css
    text/xml
    text/javascript
    application/xml+rss
    application/javascript
    application/json
    application/xml
    image/svg+xml;

# Cache Headers
location ~* \.(css|js|png|jpg|jpeg|gif|svg|ico|pdf|woff|woff2|ttf|eot)$ {
    expires 1y;
    add_header Cache-Control "public, max-age=31536000, immutable";
}

location ~* \.(html|htm)$ {
    expires 1h;
    add_header Cache-Control "public, max-age=3600, must-revalidate";
}';
        
        update_option('suple_speed_nginx_rules', $nginx_rules);
    }
    
    /**
     * Programar eventos cron
     */
    private function schedule_cron_events() {
        // Limpieza de logs diaria
        if (!wp_next_scheduled('suple_speed_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'suple_speed_cleanup_logs');
        }
        
        // Limpieza de caché caducada cada hora
        if (!wp_next_scheduled('suple_speed_cleanup_cache')) {
            wp_schedule_event(time(), 'hourly', 'suple_speed_cleanup_cache');
        }
    }
    
    /**
     * Limpiar eventos cron
     */
    private function clear_cron_events() {
        wp_clear_scheduled_hook('suple_speed_cleanup_logs');
        wp_clear_scheduled_hook('suple_speed_cleanup_cache');
    }
    
    /**
     * Limpiar archivos de caché
     */
    private static function cleanup_cache_files() {
        $cache_dir = WP_CONTENT_DIR . '/cache/suple-speed/';
        
        if (is_dir($cache_dir)) {
            self::delete_directory($cache_dir);
        }
    }
    
    /**
     * Eliminar directorio recursivamente
     */
    private static function delete_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                self::delete_directory($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($dir);
    }
}

/**
 * Función de utilidad para obtener la instancia del plugin
 */
function suple_speed() {
    return SupleSpeed::instance();
}

// Inicializar el plugin
suple_speed();

/**
 * Hooks de cron
 */
add_action('suple_speed_cleanup_logs', function() {
    if (class_exists('SupleSpeed\\Logger')) {
        $logger = new SupleSpeed\Logger();
        $logger->cleanup_old_logs();
    }
});

add_action('suple_speed_cleanup_cache', function() {
    if (class_exists('SupleSpeed\\Cache')) {
        $cache = new SupleSpeed\Cache();
        $cache->cleanup_expired_cache();
    }
});