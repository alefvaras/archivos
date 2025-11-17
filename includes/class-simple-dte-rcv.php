<?php
/**
 * Gestión de RCV (Registro de Compras y Ventas)
 *
 * @package Simple_DTE
 */

if (!defined('ABSPATH')) {
    exit;
}

class Simple_DTE_RCV {

    /**
     * Inicializar
     */
    public static function init() {
        // AJAX handlers
        add_action('wp_ajax_simple_dte_generar_rcv', array(__CLASS__, 'ajax_generar_rcv'));
        add_action('wp_ajax_simple_dte_enviar_rcv', array(__CLASS__, 'ajax_enviar_rcv'));
    }

    /**
     * Generar RCV de ventas
     *
     * @param string $fecha_desde Fecha desde (AAAA-MM-DD)
     * @param string $fecha_hasta Fecha hasta (AAAA-MM-DD)
     * @return array|WP_Error Resultado de la generación
     */
    public static function generar_rcv_ventas($fecha_desde, $fecha_hasta) {
        Simple_DTE_Logger::info('Generando RCV de ventas', array(
            'desde' => $fecha_desde,
            'hasta' => $fecha_hasta
        ));

        // Obtener órdenes con DTE en el rango de fechas
        $orders = wc_get_orders(array(
            'limit' => -1,
            'date_created' => $fecha_desde . '...' . $fecha_hasta,
            'meta_query' => array(
                array(
                    'key' => '_simple_dte_generada',
                    'value' => 'yes'
                )
            )
        ));

        if (empty($orders)) {
            return new WP_Error('no_documentos', __('No hay documentos en el rango de fechas seleccionado', 'simple-dte'));
        }

        // Construir XML del RCV
        $rcv_xml = self::build_rcv_ventas_xml($orders, $fecha_desde, $fecha_hasta);

        if (is_wp_error($rcv_xml)) {
            return $rcv_xml;
        }

        Simple_DTE_Logger::info('RCV de ventas generado', array(
            'cantidad_documentos' => count($orders)
        ));

        return array(
            'success' => true,
            'xml' => $rcv_xml,
            'cantidad_documentos' => count($orders),
            'mensaje' => sprintf(__('RCV generado con %d documentos', 'simple-dte'), count($orders))
        );
    }

    /**
     * Construir XML del RCV de ventas
     */
    private static function build_rcv_ventas_xml($orders, $fecha_desde, $fecha_hasta) {
        $rut_emisor = get_option('simple_dte_rut_emisor', '');
        $razon_social = get_option('simple_dte_razon_social', '');

        // Calcular totales por tipo de documento
        $resumen = array();
        $detalles = array();

        foreach ($orders as $order) {
            $tipo_dte = (int) $order->get_meta('_simple_dte_tipo');
            $folio = (int) $order->get_meta('_simple_dte_folio');
            $total = (float) $order->get_total();
            $neto = Simple_DTE_Helpers::calcular_neto($total);
            $iva = $total - $neto;

            // Agregar al resumen
            if (!isset($resumen[$tipo_dte])) {
                $resumen[$tipo_dte] = array(
                    'cantidad' => 0,
                    'neto' => 0,
                    'iva' => 0,
                    'total' => 0
                );
            }

            $resumen[$tipo_dte]['cantidad']++;
            $resumen[$tipo_dte]['neto'] += $neto;
            $resumen[$tipo_dte]['iva'] += $iva;
            $resumen[$tipo_dte]['total'] += $total;

            // Agregar detalle
            $rut_receptor = $order->get_meta('_billing_rut');
            if (empty($rut_receptor)) {
                $rut_receptor = '66666666-6';
            }

            $detalles[] = array(
                'tipo_dte' => $tipo_dte,
                'folio' => $folio,
                'fecha' => $order->get_date_created()->format('Y-m-d'),
                'rut_receptor' => $rut_receptor,
                'razon_social' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'neto' => round($neto),
                'iva' => round($iva),
                'total' => round($total)
            );
        }

        // Construir XML
        $xml = '<?xml version="1.0" encoding="ISO-8859-1"?>' . "\n";
        $xml .= '<LibroCompraVenta xmlns="http://www.sii.cl/SiiDte" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sii.cl/SiiDte LibroCV_v10.xsd" version="1.0">' . "\n";

        // EnvioLibro
        $xml .= '<EnvioLibro ID="Envio">' . "\n";

        // Carátula
        $xml .= '<Caratula>' . "\n";
        $xml .= '<RutEmisorLibro>' . esc_html($rut_emisor) . '</RutEmisorLibro>' . "\n";
        $xml .= '<RutEnvia>' . esc_html($rut_emisor) . '</RutEnvia>' . "\n";
        $xml .= '<PeriodoTributario>' . date('Y-m', strtotime($fecha_desde)) . '</PeriodoTributario>' . "\n";
        $xml .= '<FchResol>2025-11-16</FchResol>' . "\n";
        $xml .= '<NroResol>0</NroResol>' . "\n";
        $xml .= '<TipoOperacion>VENTA</TipoOperacion>' . "\n";
        $xml .= '<TipoLibro>ESPECIAL</TipoLibro>' . "\n";
        $xml .= '<TipoEnvio>TOTAL</TipoEnvio>' . "\n";
        $xml .= '<FolioNotificacion>1</FolioNotificacion>' . "\n";
        $xml .= '</Caratula>' . "\n";

        // Resumen por tipo de documento
        foreach ($resumen as $tipo => $datos) {
            $xml .= '<ResumenPeriodo>' . "\n";
            $xml .= '<TpoDoc>' . $tipo . '</TpoDoc>' . "\n";
            $xml .= '<TotDoc>' . $datos['cantidad'] . '</TotDoc>' . "\n";
            $xml .= '<TotMntNeto>' . round($datos['neto']) . '</TotMntNeto>' . "\n";
            $xml .= '<TotMntIVA>' . round($datos['iva']) . '</TotMntIVA>' . "\n";
            $xml .= '<TotMntTotal>' . round($datos['total']) . '</TotMntTotal>' . "\n";
            $xml .= '</ResumenPeriodo>' . "\n";
        }

        // Detalle
        foreach ($detalles as $detalle) {
            $xml .= '<Detalle>' . "\n";
            $xml .= '<TpoDoc>' . $detalle['tipo_dte'] . '</TpoDoc>' . "\n";
            $xml .= '<Folio>' . $detalle['folio'] . '</Folio>' . "\n";
            $xml .= '<FchDoc>' . $detalle['fecha'] . '</FchDoc>' . "\n";
            $xml .= '<RUTDoc>' . esc_html($detalle['rut_receptor']) . '</RUTDoc>' . "\n";
            $xml .= '<RznSoc>' . esc_html($detalle['razon_social']) . '</RznSoc>' . "\n";
            $xml .= '<MntNeto>' . $detalle['neto'] . '</MntNeto>' . "\n";
            $xml .= '<TasaIVA>19</TasaIVA>' . "\n";
            $xml .= '<IVA>' . $detalle['iva'] . '</IVA>' . "\n";
            $xml .= '<MntTotal>' . $detalle['total'] . '</MntTotal>' . "\n";
            $xml .= '</Detalle>' . "\n";
        }

        $xml .= '</EnvioLibro>' . "\n";
        $xml .= '</LibroCompraVenta>';

        return $xml;
    }

    /**
     * Enviar RCV al SII
     *
     * @param string $rcv_xml XML del RCV
     * @param string $fecha_desde Fecha desde
     * @param string $fecha_hasta Fecha hasta
     * @return array|WP_Error Resultado del envío
     */
    public static function enviar_rcv($rcv_xml, $fecha_desde, $fecha_hasta) {
        Simple_DTE_Logger::info('Enviando RCV al SII', array(
            'desde' => $fecha_desde,
            'hasta' => $fecha_hasta
        ));

        // Obtener certificado
        $cert_path = get_option('simple_dte_cert_path', '');

        if (!file_exists($cert_path)) {
            return new WP_Error('cert_not_found', __('Certificado digital no encontrado', 'simple-dte'));
        }

        // Enviar usando el mismo endpoint de sobres
        $resultado = Simple_DTE_API_Client::enviar_sobre($rcv_xml, $cert_path);

        if (is_wp_error($resultado)) {
            Simple_DTE_Logger::error('Error al enviar RCV', array(
                'error' => $resultado->get_error_message()
            ));
            return $resultado;
        }

        // Guardar registro del envío
        self::guardar_registro_envio($fecha_desde, $fecha_hasta, $resultado);

        Simple_DTE_Logger::info('RCV enviado exitosamente', array(
            'track_id' => isset($resultado['track_id']) ? $resultado['track_id'] : 'N/A'
        ));

        return array(
            'success' => true,
            'track_id' => isset($resultado['track_id']) ? $resultado['track_id'] : null,
            'mensaje' => __('RCV enviado correctamente al SII', 'simple-dte')
        );
    }

    /**
     * Guardar registro del envío de RCV
     */
    private static function guardar_registro_envio($fecha_desde, $fecha_hasta, $resultado) {
        $registros = get_option('simple_dte_rcv_enviados', array());

        $registros[] = array(
            'fecha_desde' => $fecha_desde,
            'fecha_hasta' => $fecha_hasta,
            'fecha_envio' => current_time('mysql'),
            'track_id' => isset($resultado['track_id']) ? $resultado['track_id'] : null,
            'estado' => 'enviado'
        );

        // Mantener solo los últimos 90 días
        $registros = array_slice($registros, -90);

        update_option('simple_dte_rcv_enviados', $registros);
    }

    /**
     * Obtener historial de RCVs enviados
     */
    public static function get_historial_envios() {
        $registros = get_option('simple_dte_rcv_enviados', array());
        return array_reverse($registros); // Más recientes primero
    }

    /**
     * AJAX: Generar RCV
     */
    public static function ajax_generar_rcv() {
        check_ajax_referer('simple_dte_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes', 'simple-dte')));
        }

        $fecha_desde = isset($_POST['fecha_desde']) ? sanitize_text_field($_POST['fecha_desde']) : '';
        $fecha_hasta = isset($_POST['fecha_hasta']) ? sanitize_text_field($_POST['fecha_hasta']) : '';

        if (empty($fecha_desde) || empty($fecha_hasta)) {
            wp_send_json_error(array('message' => __('Fechas requeridas', 'simple-dte')));
        }

        $resultado = self::generar_rcv_ventas($fecha_desde, $fecha_hasta);

        if (is_wp_error($resultado)) {
            wp_send_json_error(array('message' => $resultado->get_error_message()));
        }

        wp_send_json_success($resultado);
    }

    /**
     * AJAX: Enviar RCV
     */
    public static function ajax_enviar_rcv() {
        check_ajax_referer('simple_dte_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes', 'simple-dte')));
        }

        $fecha_desde = isset($_POST['fecha_desde']) ? sanitize_text_field($_POST['fecha_desde']) : '';
        $fecha_hasta = isset($_POST['fecha_hasta']) ? sanitize_text_field($_POST['fecha_hasta']) : '';
        $xml = isset($_POST['xml']) ? $_POST['xml'] : '';

        if (empty($fecha_desde) || empty($fecha_hasta) || empty($xml)) {
            wp_send_json_error(array('message' => __('Faltan datos requeridos', 'simple-dte')));
        }

        $resultado = self::enviar_rcv($xml, $fecha_desde, $fecha_hasta);

        if (is_wp_error($resultado)) {
            wp_send_json_error(array('message' => $resultado->get_error_message()));
        }

        wp_send_json_success($resultado);
    }
}
