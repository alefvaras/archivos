<?php
/**
 * Plugin Name: Simple DTE - Integración Simple API
 * Plugin URI: https://tu-sitio.cl
 * Description: Plugin de integración con Simple API para emisión de Boletas Electrónicas, consultas y RCV (Ambiente de Pruebas)
 * Version: 1.0.0
 * Author: Tu Nombre
 * Author URI: https://tu-sitio.cl
 * License: GPL v2 or later
 * Text Domain: simple-dte
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes
define('SIMPLE_DTE_VERSION', '1.0.0');
define('SIMPLE_DTE_PATH', plugin_dir_path(__FILE__));
define('SIMPLE_DTE_URL', plugin_dir_url(__FILE__));
define('SIMPLE_DTE_BASENAME', plugin_basename(__FILE__));

// URLs de Simple API
define('SIMPLE_DTE_API_URL_CERT', 'https://api.simpleapi.cl'); // Certificación
define('SIMPLE_DTE_API_URL_PROD', 'https://api.simpleapi.cl'); // Producción

/**
 * Clase principal del plugin
 */
class Simple_DTE_Plugin {

    private static $instance = null;

    /**
     * Singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Verificar dependencias
        add_action('plugins_loaded', array($this, 'check_dependencies'), 10);

        // Cargar archivos
        add_action('plugins_loaded', array($this, 'load_files'), 20);

        // Inicializar plugin
        add_action('plugins_loaded', array($this, 'init'), 30);

        // Activación
        register_activation_hook(__FILE__, array($this, 'activate'));

        // Desactivación
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    /**
     * Verificar dependencias
     */
    public function check_dependencies() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return false;
        }
        return true;
    }

    /**
     * Cargar archivos del plugin
     */
    public function load_files() {
        if (!$this->check_dependencies()) {
            return;
        }

        // Helpers y utilidades
        require_once SIMPLE_DTE_PATH . 'includes/class-simple-dte-logger.php';
        require_once SIMPLE_DTE_PATH . 'includes/class-simple-dte-helpers.php';

        // API Client
        require_once SIMPLE_DTE_PATH . 'includes/class-simple-dte-api-client.php';

        // Generadores
        require_once SIMPLE_DTE_PATH . 'includes/class-simple-dte-boleta-generator.php';
        require_once SIMPLE_DTE_PATH . 'includes/class-simple-dte-sobre-generator.php';

        // Email
        require_once SIMPLE_DTE_PATH . 'includes/class-simple-dte-email.php';

        // Consultas
        require_once SIMPLE_DTE_PATH . 'includes/class-simple-dte-consultas.php';

        // RCV
        require_once SIMPLE_DTE_PATH . 'includes/class-simple-dte-rcv.php';

        // RVD (Registro Ventas Diarias)
        require_once SIMPLE_DTE_PATH . 'includes/class-simple-dte-rvd.php';

        // Admin
        require_once SIMPLE_DTE_PATH . 'includes/admin/class-simple-dte-admin.php';
        require_once SIMPLE_DTE_PATH . 'includes/admin/class-simple-dte-settings.php';
        require_once SIMPLE_DTE_PATH . 'includes/admin/class-simple-dte-metabox.php';
    }

    /**
     * Inicializar componentes
     */
    public function init() {
        if (!$this->check_dependencies()) {
            return;
        }

        // Cargar traducciones
        load_plugin_textdomain('simple-dte', false, dirname(SIMPLE_DTE_BASENAME) . '/languages');

        // Inicializar logger
        Simple_DTE_Logger::init();

        // Inicializar admin
        Simple_DTE_Admin::init();
        Simple_DTE_Settings::init();
        Simple_DTE_Metabox::init();

        // Inicializar consultas
        Simple_DTE_Consultas::init();

        // Inicializar RCV
        Simple_DTE_RCV::init();

        // Inicializar RVD
        Simple_DTE_RVD::init();

        // Configurar SMTP si está habilitado
        Simple_DTE_Email::configure_smtp();

        // Log de inicialización
        Simple_DTE_Logger::info('Simple DTE Plugin inicializado correctamente');
    }

    /**
     * Activación del plugin
     */
    public function activate() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Verificar si es actualización
        $installed_version = get_option('simple_dte_version', '0.0.0');
        if (version_compare($installed_version, SIMPLE_DTE_VERSION, '<')) {
            $this->upgrade_database($installed_version);
        }

        // Crear tabla de logs
        $table_logs = $wpdb->prefix . 'simple_dte_logs';
        $sql_logs = "CREATE TABLE IF NOT EXISTS $table_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            nivel varchar(20) NOT NULL,
            mensaje text NOT NULL,
            contexto longtext,
            order_id bigint(20),
            rut varchar(20),
            folio varchar(20),
            tipo_dte varchar(10),
            operacion varchar(50),
            usuario_id bigint(20),
            fecha_creacion datetime NOT NULL,
            PRIMARY KEY (id),
            KEY nivel (nivel),
            KEY order_id (order_id),
            KEY rut (rut),
            KEY folio (folio),
            KEY fecha_creacion (fecha_creacion)
        ) $charset_collate;";

        // Crear tabla de folios
        $table_folios = $wpdb->prefix . 'simple_dte_folios';
        $sql_folios = "CREATE TABLE IF NOT EXISTS $table_folios (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            tipo_dte int(11) NOT NULL,
            folio_desde int(11) NOT NULL,
            folio_hasta int(11) NOT NULL,
            folio_actual int(11) NOT NULL,
            xml_path text NOT NULL,
            estado varchar(20) DEFAULT 'activo',
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY tipo_dte (tipo_dte),
            KEY estado (estado),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Crear tabla de cola de reintentos
        $table_queue = $wpdb->prefix . 'simple_dte_queue';
        $sql_queue = "CREATE TABLE IF NOT EXISTS $table_queue (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            dte_tipo varchar(10) NOT NULL,
            dte_data longtext NOT NULL,
            error_message text,
            retry_count int(11) DEFAULT 0,
            max_retries int(11) DEFAULT 5,
            status varchar(20) DEFAULT 'pending',
            created_at datetime NOT NULL,
            next_retry_at datetime,
            updated_at datetime,
            completed_at datetime,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY status (status),
            KEY next_retry_at (next_retry_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_logs);
        dbDelta($sql_folios);
        dbDelta($sql_queue);

        // Crear directorio de uploads
        $upload_dir = wp_upload_dir();
        $simple_dte_dir = $upload_dir['basedir'] . '/simple-dte/';

        if (!file_exists($simple_dte_dir)) {
            wp_mkdir_p($simple_dte_dir);

            // Crear subdirectorios
            wp_mkdir_p($simple_dte_dir . 'certs/');
            wp_mkdir_p($simple_dte_dir . 'caf/');
            wp_mkdir_p($simple_dte_dir . 'pdf/');
            wp_mkdir_p($simple_dte_dir . 'xml/');
            wp_mkdir_p($simple_dte_dir . 'rcv/');

            // Proteger con .htaccess
            $htaccess_content = "Order deny,allow\nDeny from all";
            file_put_contents($simple_dte_dir . '.htaccess', $htaccess_content);
            file_put_contents($simple_dte_dir . 'index.php', '<?php // Silence is golden');
        }

        // Opciones por defecto - Configuración general
        add_option('simple_dte_ambiente', 'certificacion');
        add_option('simple_dte_api_key', '');
        add_option('simple_dte_debug', true);
        add_option('simple_dte_rvd_auto', false);

        // Opciones por defecto - Datos del emisor
        add_option('simple_dte_rut_emisor', '');
        add_option('simple_dte_razon_social', '');
        add_option('simple_dte_giro', '');
        add_option('simple_dte_direccion', '');
        add_option('simple_dte_comuna', '');
        add_option('simple_dte_logo_url', '');

        // Opciones por defecto - Certificado
        add_option('simple_dte_cert_rut', '');
        add_option('simple_dte_cert_password', '');
        add_option('simple_dte_cert_path', '');

        // Opciones por defecto - Boletas de Ajuste
        add_option('simple_dte_auto_ajuste_enabled', false);

        // Registrar versión instalada
        update_option('simple_dte_version', SIMPLE_DTE_VERSION);

        // Log de activación (se guardará cuando Logger esté disponible)
        error_log('Simple DTE: Plugin activado - versión ' . SIMPLE_DTE_VERSION);
    }

    /**
     * Actualizar base de datos
     *
     * @param string $from_version Versión desde la que se actualiza
     */
    private function upgrade_database($from_version) {
        global $wpdb;

        error_log("Simple DTE: Actualizando base de datos desde versión {$from_version} a " . SIMPLE_DTE_VERSION);

        // Aquí se pueden agregar migraciones específicas por versión
        // Por ejemplo:
        // if (version_compare($from_version, '1.1.0', '<')) {
        //     // Migración para versión 1.1.0
        // }

        // Por ahora, simplemente recrear las tablas con dbDelta
        // que automáticamente agrega columnas faltantes sin eliminar datos
        $charset_collate = $wpdb->get_charset_collate();

        // Actualizar tabla de logs
        $table_logs = $wpdb->prefix . 'simple_dte_logs';
        $sql_logs = "CREATE TABLE $table_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            nivel varchar(20) NOT NULL,
            mensaje text NOT NULL,
            contexto longtext,
            order_id bigint(20),
            rut varchar(20),
            folio varchar(20),
            tipo_dte varchar(10),
            operacion varchar(50),
            usuario_id bigint(20),
            fecha_creacion datetime NOT NULL,
            PRIMARY KEY (id),
            KEY nivel (nivel),
            KEY order_id (order_id),
            KEY rut (rut),
            KEY folio (folio),
            KEY fecha_creacion (fecha_creacion)
        ) $charset_collate;";

        // Actualizar tabla de folios
        $table_folios = $wpdb->prefix . 'simple_dte_folios';
        $sql_folios = "CREATE TABLE $table_folios (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            tipo_dte int(11) NOT NULL,
            folio_desde int(11) NOT NULL,
            folio_hasta int(11) NOT NULL,
            folio_actual int(11) NOT NULL,
            xml_path text NOT NULL,
            estado varchar(20) DEFAULT 'activo',
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY tipo_dte (tipo_dte),
            KEY estado (estado),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Actualizar tabla de cola
        $table_queue = $wpdb->prefix . 'simple_dte_queue';
        $sql_queue = "CREATE TABLE $table_queue (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            dte_tipo varchar(10) NOT NULL,
            dte_data longtext NOT NULL,
            error_message text,
            retry_count int(11) DEFAULT 0,
            max_retries int(11) DEFAULT 5,
            status varchar(20) DEFAULT 'pending',
            created_at datetime NOT NULL,
            next_retry_at datetime,
            updated_at datetime,
            completed_at datetime,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY status (status),
            KEY next_retry_at (next_retry_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_logs);
        dbDelta($sql_folios);
        dbDelta($sql_queue);

        error_log("Simple DTE: Base de datos actualizada correctamente");
    }

    /**
     * Desactivación del plugin
     *
     * IMPORTANTE: Al desactivar NO se eliminan datos
     * Los datos solo se eliminan al DESINSTALAR el plugin
     */
    public function deactivate() {
        // Limpiar cron jobs programados
        $crons = array(
            'simple_dte_process_queue',
            'simple_dte_envio_resumen_diario',
            'simple_dte_envio_rvd',
        );

        foreach ($crons as $cron) {
            $timestamp = wp_next_scheduled($cron);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $cron);
            }
            wp_clear_scheduled_hook($cron);
        }

        error_log('Simple DTE: Plugin desactivado - Los datos se conservan. Para eliminar completamente, desinstale el plugin.');
    }

    /**
     * Aviso de WooCommerce faltante
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php esc_html_e('Simple DTE:', 'simple-dte'); ?></strong>
                <?php esc_html_e('Este plugin requiere WooCommerce activo para funcionar.', 'simple-dte'); ?>
            </p>
        </div>
        <?php
    }
}

// Inicializar el plugin
function simple_dte_init() {
    return Simple_DTE_Plugin::get_instance();
}

// Ejecutar
add_action('plugins_loaded', 'simple_dte_init', 5);
