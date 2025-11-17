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
        add_action('wp_ajax_simple_dte_generar_resumen_diario', array(__CLASS__, 'ajax_generar_resumen_diario'));
        add_action('wp_ajax_simple_dte_enviar_rcv', array(__CLASS__, 'ajax_enviar_rcv'));

        // Hook para Boletas de Ajuste (anulación automática en refunds)
        add_action('woocommerce_order_refunded', array(__CLASS__, 'handle_boleta_ajuste'), 10, 2);

        // Cron para envío automático del resumen diario
        add_action('simple_dte_envio_resumen_diario', array(__CLASS__, 'cron_enviar_resumen_diario'));

        if (!wp_next_scheduled('simple_dte_envio_resumen_diario')) {
            wp_schedule_event(strtotime('today 23:00'), 'daily', 'simple_dte_envio_resumen_diario');
        }
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
     * Generar Resumen Diario de Boletas (RCOF)
     *
     * @param string $fecha Fecha del resumen (AAAA-MM-DD)
     * @return array|WP_Error Resultado de la generación
     */
    public static function generar_resumen_diario($fecha = null) {
        if (empty($fecha)) {
            $fecha = date('Y-m-d', strtotime('yesterday'));
        }

        Simple_DTE_Logger::info('Generando resumen diario de boletas', array('fecha' => $fecha));

        // Obtener boletas del día (tipo 39 y 41)
        $orders = wc_get_orders(array(
            'limit' => -1,
            'date_created' => $fecha . '...' . $fecha . ' 23:59:59',
            'meta_query' => array(
                array(
                    'key' => '_simple_dte_generada',
                    'value' => 'yes'
                ),
                array(
                    'key' => '_simple_dte_tipo',
                    'value' => array('39', '41'),
                    'compare' => 'IN'
                )
            )
        ));

        if (empty($orders)) {
            return new WP_Error('no_boletas', __('No hay boletas para el día seleccionado', 'simple-dte'));
        }

        // Construir XML del resumen diario
        $xml = self::build_resumen_diario_xml($orders, $fecha);

        if (is_wp_error($xml)) {
            return $xml;
        }

        // Guardar XML
        $filename = 'resumen_diario_' . str_replace('-', '', $fecha) . '.xml';
        $filepath = self::save_rcv_xml($xml, $filename);

        Simple_DTE_Logger::info('Resumen diario generado', array(
            'cantidad_boletas' => count($orders),
            'archivo' => $filename
        ));

        return array(
            'success' => true,
            'xml' => $xml,
            'filepath' => $filepath,
            'cantidad_documentos' => count($orders),
            'mensaje' => sprintf(__('Resumen diario generado con %d boletas', 'simple-dte'), count($orders))
        );
    }

    /**
     * Construir XML del resumen diario de boletas
     */
    private static function build_resumen_diario_xml($orders, $fecha) {
        $rut_emisor = get_option('simple_dte_rut_emisor', '');
        $razon_social = get_option('simple_dte_razon_social', '');

        // Consolidar por rangos de folios
        $boletas_39 = array();
        $boletas_41 = array();

        foreach ($orders as $order) {
            $tipo_dte = (int) $order->get_meta('_simple_dte_tipo');
            $folio = (int) $order->get_meta('_simple_dte_folio');
            $total = (float) $order->get_total();
            $neto = Simple_DTE_Helpers::calcular_neto($total);
            $iva = $total - $neto;
            $exento = 0;

            if ($tipo_dte == 39) {
                $boletas_39[] = array('folio' => $folio, 'neto' => $neto, 'iva' => $iva, 'exento' => $exento, 'total' => $total);
            } elseif ($tipo_dte == 41) {
                $boletas_41[] = array('folio' => $folio, 'neto' => $neto, 'iva' => $iva, 'exento' => $exento, 'total' => $total);
            }
        }

        // Ordenar por folio
        usort($boletas_39, function($a, $b) { return $a['folio'] - $b['folio']; });
        usort($boletas_41, function($a, $b) { return $a['folio'] - $b['folio']; });

        // Construir XML
        $xml = '<?xml version="1.0" encoding="ISO-8859-1"?>' . "\n";
        $xml .= '<ConsumoFolios xmlns="http://www.sii.cl/SiiDte" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sii.cl/SiiDte ConsumoFolio_v10.xsd" version="1.0">' . "\n";

        $xml .= '<DocumentoConsumoFolios ID="Consumo">' . "\n";

        // Carátula
        $xml .= '<Caratula>' . "\n";
        $xml .= '<RutEmisor>' . esc_html($rut_emisor) . '</RutEmisor>' . "\n";
        $xml .= '<RutEnvia>' . esc_html($rut_emisor) . '</RutEnvia>' . "\n";
        $xml .= '<FchResol>2025-11-16</FchResol>' . "\n";
        $xml .= '<NroResol>0</NroResol>' . "\n";
        $xml .= '<FchInicio>' . $fecha . '</FchInicio>' . "\n";
        $xml .= '<FchFinal>' . $fecha . '</FchFinal>' . "\n";
        $xml .= '<SecEnvio>1</SecEnvio>' . "\n";
        $xml .= '<TmstFirmaEnv>' . date('Y-m-d\TH:i:s') . '</TmstFirmaEnv>' . "\n";
        $xml .= '</Caratula>' . "\n";

        // Resumen por tipo de documento
        $tipos = array();
        if (!empty($boletas_39)) $tipos[] = array('tipo' => 39, 'boletas' => $boletas_39);
        if (!empty($boletas_41)) $tipos[] = array('tipo' => 41, 'boletas' => $boletas_41);

        foreach ($tipos as $tipo_data) {
            $tipo = $tipo_data['tipo'];
            $boletas = $tipo_data['boletas'];

            if (empty($boletas)) continue;

            $folio_inicial = $boletas[0]['folio'];
            $folio_final = $boletas[count($boletas) - 1]['folio'];

            $total_neto = array_sum(array_column($boletas, 'neto'));
            $total_iva = array_sum(array_column($boletas, 'iva'));
            $total_exento = array_sum(array_column($boletas, 'exento'));
            $total_monto = array_sum(array_column($boletas, 'total'));

            $xml .= '<Resumen>' . "\n";
            $xml .= '<TipoDocumento>' . $tipo . '</TipoDocumento>' . "\n";
            $xml .= '<MntNeto>' . round($total_neto) . '</MntNeto>' . "\n";
            $xml .= '<MntIva>' . round($total_iva) . '</MntIva>' . "\n";
            $xml .= '<TasaIVA>19</TasaIVA>' . "\n";
            $xml .= '<MntExento>' . round($total_exento) . '</MntExento>' . "\n";
            $xml .= '<MntTotal>' . round($total_monto) . '</MntTotal>' . "\n";
            $xml .= '<FoliosEmitidos>' . count($boletas) . '</FoliosEmitidos>' . "\n";
            $xml .= '<FoliosAnulados>0</FoliosAnulados>' . "\n";
            $xml .= '<FoliosUtilizados>' . count($boletas) . '</FoliosUtilizados>' . "\n";
            $xml .= '<RangoUtilizados>' . "\n";
            $xml .= '<Inicial>' . $folio_inicial . '</Inicial>' . "\n";
            $xml .= '<Final>' . $folio_final . '</Final>' . "\n";
            $xml .= '</RangoUtilizados>' . "\n";
            $xml .= '</Resumen>' . "\n";
        }

        $xml .= '</DocumentoConsumoFolios>' . "\n";
        $xml .= '</ConsumoFolios>';

        return $xml;
    }

    /**
     * Guardar XML de RCV
     */
    private static function save_rcv_xml($xml, $filename) {
        $upload_dir = wp_upload_dir();
        $rcv_dir = $upload_dir['basedir'] . '/simple-dte/rcv/';

        if (!file_exists($rcv_dir)) {
            wp_mkdir_p($rcv_dir);
        }

        $filepath = $rcv_dir . $filename;
        file_put_contents($filepath, $xml);

        return $filepath;
    }

    /**
     * Enviar RCV/Resumen al SII
     */
    public static function enviar_rcv_sii($xml, $tipo = 'rcv') {
        Simple_DTE_Logger::info('Enviando ' . $tipo . ' al SII');

        $api_client = new Simple_DTE_API_Client();

        // El endpoint depende del tipo
        $endpoint = ($tipo === 'resumen_diario') ? 'boletas/consumo_folios' : 'rcv';

        $response = $api_client->request('POST', $endpoint, array(
            'xml' => $xml
        ));

        if (is_wp_error($response)) {
            Simple_DTE_Logger::error('Error enviando ' . $tipo . ' al SII', array(
                'error' => $response->get_error_message()
            ));
            return $response;
        }

        Simple_DTE_Logger::info($tipo . ' enviado al SII exitosamente', array(
            'track_id' => $response['track_id'] ?? null
        ));

        return array(
            'success' => true,
            'track_id' => $response['track_id'] ?? null,
            'mensaje' => __('Enviado al SII exitosamente', 'simple-dte')
        );
    }

    /**
     * Cron: Enviar resumen diario automáticamente
     */
    public static function cron_enviar_resumen_diario() {
        Simple_DTE_Logger::info('Ejecutando cron de envío de resumen diario');

        // Generar resumen del día anterior
        $fecha_ayer = date('Y-m-d', strtotime('yesterday'));
        $resultado = self::generar_resumen_diario($fecha_ayer);

        if (is_wp_error($resultado)) {
            Simple_DTE_Logger::warning('No se pudo generar resumen diario', array(
                'fecha' => $fecha_ayer,
                'error' => $resultado->get_error_message()
            ));
            return;
        }

        // Enviar al SII
        $envio = self::enviar_rcv_sii($resultado['xml'], 'resumen_diario');

        if (is_wp_error($envio)) {
            Simple_DTE_Logger::error('Error enviando resumen diario al SII', array(
                'fecha' => $fecha_ayer,
                'error' => $envio->get_error_message()
            ));
            return;
        }

        Simple_DTE_Logger::info('Resumen diario enviado exitosamente', array(
            'fecha' => $fecha_ayer,
            'track_id' => $envio['track_id'] ?? null
        ));
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

        // Guardar XML
        $filename = 'rcv_' . str_replace('-', '', $fecha_desde) . '_' . str_replace('-', '', $fecha_hasta) . '.xml';
        $filepath = self::save_rcv_xml($resultado['xml'], $filename);
        $resultado['filepath'] = $filepath;

        wp_send_json_success($resultado);
    }

    /**
     * AJAX: Generar resumen diario
     */
    public static function ajax_generar_resumen_diario() {
        check_ajax_referer('simple_dte_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes', 'simple-dte')));
        }

        $fecha = isset($_POST['fecha']) ? sanitize_text_field($_POST['fecha']) : date('Y-m-d', strtotime('yesterday'));

        $resultado = self::generar_resumen_diario($fecha);

        if (is_wp_error($resultado)) {
            wp_send_json_error(array('message' => $resultado->get_error_message()));
        }

        wp_send_json_success($resultado);
    }

    /**
     * AJAX: Enviar RCV/Resumen al SII
     */
    public static function ajax_enviar_rcv() {
        check_ajax_referer('simple_dte_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes', 'simple-dte')));
        }

        $xml = isset($_POST['xml']) ? wp_unslash($_POST['xml']) : '';
        $tipo = isset($_POST['tipo']) ? sanitize_text_field($_POST['tipo']) : 'rcv';

        if (empty($xml)) {
            wp_send_json_error(array('message' => __('XML requerido', 'simple-dte')));
        }

        $resultado = self::enviar_rcv_sii($xml, $tipo);

        if (is_wp_error($resultado)) {
            wp_send_json_error(array('message' => $resultado->get_error_message()));
        }

        wp_send_json_success($resultado);
    }

    /**
     * Manejar Boleta de Ajuste (anulación) cuando se crea un refund
     *
     * @param int $order_id ID de la orden
     * @param int $refund_id ID del refund
     */
    public static function handle_boleta_ajuste($order_id, $refund_id) {
        // Verificar si la funcionalidad está habilitada
        if (!get_option('simple_dte_auto_ajuste_enabled')) {
            return;
        }

        $order = wc_get_order($order_id);
        $refund = wc_get_order($refund_id);

        if (!$order || !$refund) {
            Simple_DTE_Logger::error('Orden o refund no encontrado para Boleta de Ajuste', array(
                'order_id' => $order_id,
                'refund_id' => $refund_id
            ));
            return;
        }

        // Verificar que la orden tenga boleta generada
        if ($order->get_meta('_simple_dte_generada') !== 'yes') {
            return;
        }

        // Verificar que sea un refund total
        $monto_refund = abs((float) $refund->get_total());
        $monto_orden = (float) $order->get_total();

        if ($monto_refund != $monto_orden) {
            Simple_DTE_Logger::info('Refund parcial, no se marca boleta como anulada', array(
                'order_id' => $order_id,
                'monto_refund' => $monto_refund,
                'monto_orden' => $monto_orden
            ));

            $order->add_order_note(
                __('Reembolso parcial creado. Las boletas solo se anulan en reembolsos totales.', 'simple-dte')
            );

            return;
        }

        // Verificar que no esté ya anulada
        if ($order->get_meta('_simple_dte_anulada') === 'yes') {
            return;
        }

        // Marcar boleta como anulada
        $order->update_meta_data('_simple_dte_anulada', 'yes');
        $order->update_meta_data('_simple_dte_fecha_anulacion', current_time('mysql'));
        $order->save();

        Simple_DTE_Logger::info('Boleta marcada como anulada (Boleta de Ajuste)', array(
            'order_id' => $order_id,
            'folio' => $order->get_meta('_simple_dte_folio')
        ), $order_id);

        $order->add_order_note(
            sprintf(
                __('✓ Boleta N° %d marcada como anulada. Se reportará en el Resumen Diario como folio anulado.', 'simple-dte'),
                $order->get_meta('_simple_dte_folio')
            )
        );
    }

}
