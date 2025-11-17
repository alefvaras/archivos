<?php
/**
 * Administración general del plugin
 *
 * @package Simple_DTE
 */

if (!defined('ABSPATH')) {
    exit;
}

class Simple_DTE_Admin {

    /**
     * Inicializar
     */
    public static function init() {
        // Agregar menú
        add_action('admin_menu', array(__CLASS__, 'add_menu'));

        // Enqueue scripts y styles
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));

        // Agregar columna en lista de órdenes
        add_filter('manage_edit-shop_order_columns', array(__CLASS__, 'add_order_column'));
        add_action('manage_shop_order_posts_custom_column', array(__CLASS__, 'display_order_column'), 10, 2);

        // AJAX handlers
        add_action('wp_ajax_simple_dte_generar_boleta', array(__CLASS__, 'ajax_generar_boleta'));
        add_action('wp_ajax_simple_dte_upload_caf', array(__CLASS__, 'ajax_upload_caf'));
        add_action('wp_ajax_simple_dte_solicitar_folios', array(__CLASS__, 'ajax_solicitar_folios'));
    }

    /**
     * Agregar menú de administración
     */
    public static function add_menu() {
        add_submenu_page(
            'woocommerce',
            __('Simple DTE', 'simple-dte'),
            __('Simple DTE', 'simple-dte'),
            'manage_woocommerce',
            'simple-dte',
            array(__CLASS__, 'render_main_page')
        );

        add_submenu_page(
            'woocommerce',
            __('Consultas DTE', 'simple-dte'),
            __('Consultas DTE', 'simple-dte'),
            'manage_woocommerce',
            'simple-dte-consultas',
            array(__CLASS__, 'render_consultas_page')
        );

        add_submenu_page(
            'woocommerce',
            __('RCV', 'simple-dte'),
            __('RCV', 'simple-dte'),
            'manage_woocommerce',
            'simple-dte-rcv',
            array(__CLASS__, 'render_rcv_page')
        );

        // RVD solo en certificación
        if (Simple_DTE_Helpers::is_certificacion()) {
            add_submenu_page(
                'woocommerce',
                __('RVD - Ventas Diarias', 'simple-dte'),
                __('RVD Diario', 'simple-dte'),
                'manage_woocommerce',
                'simple-dte-rvd',
                array(__CLASS__, 'render_rvd_page')
            );
        }
    }

    /**
     * Enqueue assets
     */
    public static function enqueue_assets($hook) {
        if (strpos($hook, 'simple-dte') === false && strpos($hook, 'post.php') === false) {
            return;
        }

        wp_enqueue_style(
            'simple-dte-admin',
            SIMPLE_DTE_URL . 'assets/css/admin.css',
            array(),
            SIMPLE_DTE_VERSION
        );

        wp_enqueue_script(
            'simple-dte-admin',
            SIMPLE_DTE_URL . 'assets/js/admin.js',
            array('jquery'),
            SIMPLE_DTE_VERSION,
            true
        );

        wp_localize_script('simple-dte-admin', 'simpleDTE', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('simple_dte_nonce'),
            'strings' => array(
                'generando' => __('Generando...', 'simple-dte'),
                'error' => __('Error', 'simple-dte'),
                'exito' => __('Éxito', 'simple-dte')
            )
        ));
    }

    /**
     * Renderizar página principal
     */
    public static function render_main_page() {
        $folios = Simple_DTE_Consultas::consultar_folios();
        $api_key = get_option('simple_dte_api_key', '');
        $ambiente = Simple_DTE_Helpers::get_ambiente();

        include SIMPLE_DTE_PATH . 'templates/admin-main.php';
    }

    /**
     * Renderizar página de consultas
     */
    public static function render_consultas_page() {
        include SIMPLE_DTE_PATH . 'templates/admin-consultas.php';
    }

    /**
     * Renderizar página de RCV
     */
    public static function render_rcv_page() {
        include SIMPLE_DTE_PATH . 'templates/admin-rcv.php';
    }

    /**
     * Renderizar página de RVD
     */
    public static function render_rvd_page() {
        $historial = Simple_DTE_RVD::get_historial_envios();
        include SIMPLE_DTE_PATH . 'templates/admin-rvd.php';
    }

    /**
     * Agregar columna en lista de órdenes
     */
    public static function add_order_column($columns) {
        $new_columns = array();

        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;

            if ($key === 'order_status') {
                $new_columns['simple_dte'] = __('DTE', 'simple-dte');
            }
        }

        return $new_columns;
    }

    /**
     * Mostrar contenido de columna DTE
     */
    public static function display_order_column($column, $post_id) {
        if ($column === 'simple_dte') {
            $order = wc_get_order($post_id);

            $tiene_dte = $order->get_meta('_simple_dte_generada');
            $folio = $order->get_meta('_simple_dte_folio');
            $tipo = $order->get_meta('_simple_dte_tipo');

            if ($tiene_dte === 'yes' && $folio) {
                echo '<span style="color: green;">✓ ' . esc_html($folio) . '</span>';

                if ($tipo == 39) {
                    echo '<br><small>Boleta</small>';
                } elseif ($tipo == 41) {
                    echo '<br><small>Boleta Exenta</small>';
                }
            } else {
                echo '<span style="color: gray;">—</span>';
            }
        }
    }

    /**
     * AJAX: Generar boleta
     */
    public static function ajax_generar_boleta() {
        check_ajax_referer('simple_dte_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes', 'simple-dte')));
        }

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $caso_prueba = isset($_POST['caso_prueba']) ? sanitize_text_field($_POST['caso_prueba']) : '';

        if (!$order_id) {
            wp_send_json_error(array('message' => __('ID de orden requerido', 'simple-dte')));
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(array('message' => __('Orden no encontrada', 'simple-dte')));
        }

        $opciones = array();
        if ($caso_prueba) {
            $opciones['caso_prueba'] = $caso_prueba;
        }

        $resultado = Simple_DTE_Boleta_Generator::generar_desde_orden($order, $opciones);

        if (is_wp_error($resultado)) {
            wp_send_json_error(array('message' => $resultado->get_error_message()));
        }

        wp_send_json_success($resultado);
    }

    /**
     * AJAX: Upload CAF
     */
    public static function ajax_upload_caf() {
        check_ajax_referer('simple_dte_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes', 'simple-dte')));
        }

        if (empty($_FILES['caf_file'])) {
            wp_send_json_error(array('message' => __('No se recibió ningún archivo', 'simple-dte')));
        }

        $file = $_FILES['caf_file'];
        $tipo_dte = isset($_POST['tipo_dte']) ? intval($_POST['tipo_dte']) : 0;

        // Validar archivo
        $validation = Simple_DTE_Helpers::validate_upload($file, array('xml'));

        if (!$validation['success']) {
            wp_send_json_error(array('message' => $validation['message']));
        }

        // Procesar CAF
        $resultado = self::procesar_caf_file($file, $tipo_dte);

        if (is_wp_error($resultado)) {
            wp_send_json_error(array('message' => $resultado->get_error_message()));
        }

        wp_send_json_success($resultado);
    }

    /**
     * Procesar archivo CAF
     */
    private static function procesar_caf_file($file, $tipo_dte) {
        // Crear directorio seguro
        $upload_dir = Simple_DTE_Helpers::create_secure_upload_dir();

        $filename = 'caf-' . $tipo_dte . '-' . time() . '.xml';
        $filepath = $upload_dir . $filename;

        // Mover archivo
        if (!@move_uploaded_file($file['tmp_name'], $filepath)) {
            return new WP_Error('upload_failed', __('No se pudo guardar el archivo CAF', 'simple-dte'));
        }

        @chmod($filepath, 0600);

        // Parsear XML
        $xml_content = file_get_contents($filepath);
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_content);

        if ($xml === false) {
            @unlink($filepath);
            return new WP_Error('invalid_xml', __('El archivo CAF no es un XML válido', 'simple-dte'));
        }

        // Extraer datos del CAF
        $da = $xml->CAF->DA;

        if (!$da) {
            @unlink($filepath);
            return new WP_Error('invalid_caf', __('Estructura de CAF inválida', 'simple-dte'));
        }

        $tipo = (int) ((string) $da->TD);
        $folio_desde = (int) ((string) $da->RNG->D);
        $folio_hasta = (int) ((string) $da->RNG->H);

        // Guardar en base de datos
        global $wpdb;
        $table = $wpdb->prefix . 'simple_dte_folios';

        $wpdb->insert($table, array(
            'tipo_dte' => $tipo,
            'folio_desde' => $folio_desde,
            'folio_hasta' => $folio_hasta,
            'folio_actual' => $folio_desde - 1,
            'fecha_carga' => current_time('mysql'),
            'archivo_caf' => $filepath,
            'estado' => 'activo'
        ), array('%d', '%d', '%d', '%d', '%s', '%s', '%s'));

        Simple_DTE_Logger::info('CAF cargado', array(
            'tipo' => $tipo,
            'desde' => $folio_desde,
            'hasta' => $folio_hasta
        ));

        return array(
            'success' => true,
            'tipo_dte' => $tipo,
            'folio_desde' => $folio_desde,
            'folio_hasta' => $folio_hasta,
            'mensaje' => sprintf(__('CAF cargado: Folios %d a %d', 'simple-dte'), $folio_desde, $folio_hasta)
        );
    }

    /**
     * AJAX: Solicitar folios a SimpleAPI
     */
    public static function ajax_solicitar_folios() {
        check_ajax_referer('simple_dte_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes', 'simple-dte')));
        }

        $tipo_dte = isset($_POST['tipo_dte']) ? intval($_POST['tipo_dte']) : 0;
        $cantidad = isset($_POST['cantidad']) ? intval($_POST['cantidad']) : 0;

        if (!$tipo_dte) {
            wp_send_json_error(array('message' => __('Tipo de DTE requerido', 'simple-dte')));
        }

        if (!$cantidad || $cantidad < 1 || $cantidad > 1000) {
            wp_send_json_error(array('message' => __('Cantidad inválida (debe estar entre 1 y 1000)', 'simple-dte')));
        }

        // Obtener ruta del certificado
        $cert_path = get_option('simple_dte_cert_path', '');

        if (empty($cert_path) || !file_exists($cert_path)) {
            wp_send_json_error(array('message' => __('Certificado digital no configurado o no encontrado', 'simple-dte')));
        }

        // Solicitar folios a la API
        $resultado = Simple_DTE_API_Client::solicitar_folios($tipo_dte, $cantidad, $cert_path);

        if (is_wp_error($resultado)) {
            wp_send_json_error(array('message' => $resultado->get_error_message()));
        }

        wp_send_json_success($resultado);
    }
}
