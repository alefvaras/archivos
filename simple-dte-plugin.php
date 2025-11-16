<?php
/**
 * Plugin Name: Simple DTE - Integración Simple API
 * Plugin URI: https://tu-sitio.cl
 * Description: Plugin de integración con Simple API para emisión de Boletas, Notas de Crédito, consultas y RCV (Ambiente de Pruebas)
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
        require_once SIMPLE_DTE_PATH . 'includes/class-simple-dte-nota-credito-generator.php';
        require_once SIMPLE_DTE_PATH . 'includes/class-simple-dte-sobre-generator.php';

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

        // Log de inicialización
        Simple_DTE_Logger::info('Simple DTE Plugin inicializado correctamente');
    }

    /**
     * Activación del plugin
     */
    public function activate() {
        global $wpdb;

        // Crear tabla de logs
        $table_logs = $wpdb->prefix . 'simple_dte_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql_logs = "CREATE TABLE IF NOT EXISTS $table_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            fecha_hora datetime NOT NULL,
            nivel varchar(20) NOT NULL,
            mensaje text NOT NULL,
            contexto longtext,
            order_id bigint(20),
            PRIMARY KEY (id),
            KEY fecha_hora (fecha_hora),
            KEY nivel (nivel),
            KEY order_id (order_id)
        ) $charset_collate;";

        // Crear tabla de folios
        $table_folios = $wpdb->prefix . 'simple_dte_folios';

        $sql_folios = "CREATE TABLE IF NOT EXISTS $table_folios (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            tipo_dte int(11) NOT NULL,
            folio_desde int(11) NOT NULL,
            folio_hasta int(11) NOT NULL,
            folio_actual int(11) NOT NULL,
            fecha_carga datetime NOT NULL,
            archivo_caf text NOT NULL,
            estado varchar(20) DEFAULT 'activo',
            PRIMARY KEY (id),
            KEY tipo_dte (tipo_dte),
            KEY estado (estado)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_logs);
        dbDelta($sql_folios);

        // Opciones por defecto
        add_option('simple_dte_ambiente', 'certificacion');
        add_option('simple_dte_api_key', '');
        add_option('simple_dte_debug', true);
        add_option('simple_dte_rvd_auto', false); // RVD automático desactivado por defecto

        // Datos del emisor
        add_option('simple_dte_rut_emisor', '');
        add_option('simple_dte_razon_social', '');
        add_option('simple_dte_giro', '');
        add_option('simple_dte_direccion', '');
        add_option('simple_dte_comuna', '');

        // Certificado
        add_option('simple_dte_cert_rut', '');
        add_option('simple_dte_cert_password', '');
        add_option('simple_dte_cert_path', '');

        Simple_DTE_Logger::info('Plugin Simple DTE activado');
    }

    /**
     * Desactivación del plugin
     */
    public function deactivate() {
        Simple_DTE_Logger::info('Plugin Simple DTE desactivado');
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
