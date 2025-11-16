<?php
/**
 * Consultas a Simple API y SII
 *
 * @package Simple_DTE
 */

if (!defined('ABSPATH')) {
    exit;
}

class Simple_DTE_Consultas {

    /**
     * Inicializar
     */
    public static function init() {
        // AJAX handlers
        add_action('wp_ajax_simple_dte_consultar_estado', array(__CLASS__, 'ajax_consultar_estado'));
        add_action('wp_ajax_simple_dte_consultar_dte', array(__CLASS__, 'ajax_consultar_dte'));
    }

    /**
     * Consultar estado de un envío
     *
     * @param string $track_id ID de seguimiento
     * @return array|WP_Error Resultado de la consulta
     */
    public static function consultar_estado_envio($track_id) {
        Simple_DTE_Logger::info('Consultando estado de envío', array('track_id' => $track_id));

        $rut_emisor = get_option('simple_dte_rut_emisor', '');

        $resultado = Simple_DTE_API_Client::consultar_estado_envio($track_id, $rut_emisor);

        if (is_wp_error($resultado)) {
            Simple_DTE_Logger::error('Error al consultar estado', array(
                'error' => $resultado->get_error_message()
            ));
            return $resultado;
        }

        return array(
            'success' => true,
            'estado' => isset($resultado['estado']) ? $resultado['estado'] : 'DESCONOCIDO',
            'glosa' => isset($resultado['glosa']) ? $resultado['glosa'] : '',
            'data' => $resultado
        );
    }

    /**
     * Consultar un DTE específico
     *
     * @param int $tipo_dte Tipo de DTE
     * @param int $folio Folio del documento
     * @return array|WP_Error Resultado de la consulta
     */
    public static function consultar_dte($tipo_dte, $folio) {
        Simple_DTE_Logger::info('Consultando DTE', array(
            'tipo' => $tipo_dte,
            'folio' => $folio
        ));

        $rut_emisor = get_option('simple_dte_rut_emisor', '');

        $resultado = Simple_DTE_API_Client::consultar_dte($tipo_dte, $folio, $rut_emisor);

        if (is_wp_error($resultado)) {
            Simple_DTE_Logger::error('Error al consultar DTE', array(
                'error' => $resultado->get_error_message()
            ));
            return $resultado;
        }

        return array(
            'success' => true,
            'existe' => isset($resultado['existe']) ? $resultado['existe'] : false,
            'data' => $resultado
        );
    }

    /**
     * Consultar folios disponibles
     *
     * @param int $tipo_dte Tipo de DTE (39, 33, 61, etc.)
     * @return array Información de folios
     */
    public static function consultar_folios($tipo_dte = null) {
        global $wpdb;

        $table = $wpdb->prefix . 'simple_dte_folios';

        if ($tipo_dte) {
            $folios = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE tipo_dte = %d ORDER BY id DESC",
                $tipo_dte
            ), ARRAY_A);
        } else {
            $folios = $wpdb->get_results(
                "SELECT * FROM $table ORDER BY tipo_dte, id DESC",
                ARRAY_A
            );
        }

        $resultado = array();

        foreach ($folios as $folio) {
            $disponibles = ($folio['folio_hasta'] - $folio['folio_actual']);
            $usados = ($folio['folio_actual'] - $folio['folio_desde'] + 1);
            $total = ($folio['folio_hasta'] - $folio['folio_desde'] + 1);
            $porcentaje_usado = $total > 0 ? round(($usados / $total) * 100, 2) : 0;

            $resultado[] = array(
                'tipo_dte' => $folio['tipo_dte'],
                'tipo_nombre' => self::get_nombre_tipo_dte($folio['tipo_dte']),
                'desde' => $folio['folio_desde'],
                'hasta' => $folio['folio_hasta'],
                'actual' => $folio['folio_actual'],
                'disponibles' => $disponibles,
                'usados' => $usados,
                'total' => $total,
                'porcentaje_usado' => $porcentaje_usado,
                'estado' => $folio['estado'],
                'fecha_carga' => $folio['fecha_carga']
            );
        }

        return $resultado;
    }

    /**
     * AJAX: Consultar estado
     */
    public static function ajax_consultar_estado() {
        check_ajax_referer('simple_dte_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes', 'simple-dte')));
        }

        $track_id = isset($_POST['track_id']) ? sanitize_text_field($_POST['track_id']) : '';

        if (empty($track_id)) {
            wp_send_json_error(array('message' => __('Track ID requerido', 'simple-dte')));
        }

        $resultado = self::consultar_estado_envio($track_id);

        if (is_wp_error($resultado)) {
            wp_send_json_error(array('message' => $resultado->get_error_message()));
        }

        wp_send_json_success($resultado);
    }

    /**
     * AJAX: Consultar DTE
     */
    public static function ajax_consultar_dte() {
        check_ajax_referer('simple_dte_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes', 'simple-dte')));
        }

        $tipo_dte = isset($_POST['tipo_dte']) ? intval($_POST['tipo_dte']) : 0;
        $folio = isset($_POST['folio']) ? intval($_POST['folio']) : 0;

        if (!$tipo_dte || !$folio) {
            wp_send_json_error(array('message' => __('Tipo DTE y Folio requeridos', 'simple-dte')));
        }

        $resultado = self::consultar_dte($tipo_dte, $folio);

        if (is_wp_error($resultado)) {
            wp_send_json_error(array('message' => $resultado->get_error_message()));
        }

        wp_send_json_success($resultado);
    }

    /**
     * Obtener nombre del tipo de DTE
     */
    private static function get_nombre_tipo_dte($tipo) {
        $tipos = Simple_DTE_Helpers::get_tipos_dte();
        return isset($tipos[$tipo]) ? $tipos[$tipo] : 'Tipo ' . $tipo;
    }
}
