<?php
/**
 * Registro de Ventas Diarias (RVD) - Ex RCOF
 * Solo para ambiente de certificación
 *
 * @package Simple_DTE
 */

if (!defined('ABSPATH')) {
    exit;
}

class Simple_DTE_RVD {

    /**
     * Inicializar
     */
    public static function init() {
        // AJAX handlers
        add_action('wp_ajax_simple_dte_generar_rvd', array(__CLASS__, 'ajax_generar_rvd'));
        add_action('wp_ajax_simple_dte_enviar_rvd', array(__CLASS__, 'ajax_enviar_rvd'));

        // Cron para envío automático diario (solo certificación)
        add_action('simple_dte_envio_rvd_diario', array(__CLASS__, 'enviar_rvd_automatico'));

        // Activar cron si está en certificación
        if (Simple_DTE_Helpers::is_certificacion()) {
            self::schedule_daily_rvd();
        }
    }

    /**
     * Programar envío diario automático
     */
    private static function schedule_daily_rvd() {
        if (!wp_next_scheduled('simple_dte_envio_rvd_diario')) {
            // Programar para las 23:00 todos los días
            wp_schedule_event(strtotime('23:00:00'), 'daily', 'simple_dte_envio_rvd_diario');
        }
    }

    /**
     * Generar RVD para una fecha específica
     *
     * @param string $fecha Fecha en formato AAAA-MM-DD
     * @return array|WP_Error Resultado de la generación
     */
    public static function generar_rvd($fecha) {
        // Validar que estamos en certificación
        if (!Simple_DTE_Helpers::is_certificacion()) {
            return new WP_Error(
                'ambiente_invalido',
                __('El RVD solo está disponible en ambiente de certificación', 'simple-dte')
            );
        }

        Simple_DTE_Logger::info('Generando RVD', array('fecha' => $fecha));

        // Validar fecha
        if (!Simple_DTE_Helpers::validar_fecha($fecha)) {
            return new WP_Error('fecha_invalida', __('Formato de fecha inválido', 'simple-dte'));
        }

        // Obtener boletas del día
        $orders = self::get_boletas_del_dia($fecha);

        if (empty($orders)) {
            return new WP_Error('no_documentos', __('No hay boletas para la fecha seleccionada', 'simple-dte'));
        }

        // Construir XML del RVD
        $rvd_xml = self::build_rvd_xml($orders, $fecha);

        if (is_wp_error($rvd_xml)) {
            return $rvd_xml;
        }

        Simple_DTE_Logger::info('RVD generado', array(
            'fecha' => $fecha,
            'cantidad_boletas' => count($orders)
        ));

        return array(
            'success' => true,
            'xml' => $rvd_xml,
            'cantidad_boletas' => count($orders),
            'fecha' => $fecha,
            'mensaje' => sprintf(
                __('RVD generado: %d boletas para %s', 'simple-dte'),
                count($orders),
                date_i18n('d/m/Y', strtotime($fecha))
            )
        );
    }

    /**
     * Enviar RVD al SII
     *
     * @param string $rvd_xml XML del RVD
     * @param string $fecha Fecha del RVD
     * @return array|WP_Error Resultado del envío
     */
    public static function enviar_rvd($rvd_xml, $fecha) {
        Simple_DTE_Logger::info('Enviando RVD al SII', array('fecha' => $fecha));

        // Obtener certificado
        $cert_path = get_option('simple_dte_cert_path', '');

        if (!file_exists($cert_path)) {
            return new WP_Error('cert_not_found', __('Certificado digital no encontrado', 'simple-dte'));
        }

        // Crear archivo temporal del RVD
        $temp_rvd = tempnam(sys_get_temp_dir(), 'rvd_');
        $rvd_path = $temp_rvd . '.xml';
        @rename($temp_rvd, $rvd_path);
        file_put_contents($rvd_path, $rvd_xml);

        // Enviar usando el mismo endpoint de sobres
        $resultado = Simple_DTE_API_Client::enviar_sobre($rvd_xml, $cert_path);

        // Limpiar archivo temporal
        @unlink($rvd_path);

        if (is_wp_error($resultado)) {
            Simple_DTE_Logger::error('Error al enviar RVD', array(
                'error' => $resultado->get_error_message(),
                'fecha' => $fecha
            ));
            return $resultado;
        }

        // Guardar registro del envío
        self::guardar_registro_envio($fecha, $resultado);

        Simple_DTE_Logger::info('RVD enviado exitosamente', array(
            'fecha' => $fecha,
            'track_id' => isset($resultado['track_id']) ? $resultado['track_id'] : 'N/A'
        ));

        return array(
            'success' => true,
            'track_id' => isset($resultado['track_id']) ? $resultado['track_id'] : null,
            'mensaje' => sprintf(__('RVD enviado correctamente para %s', 'simple-dte'), date_i18n('d/m/Y', strtotime($fecha)))
        );
    }

    /**
     * Obtener boletas del día
     *
     * @param string $fecha Fecha en formato AAAA-MM-DD
     * @return array Array de órdenes
     */
    private static function get_boletas_del_dia($fecha) {
        $args = array(
            'limit' => -1,
            'date_created' => $fecha,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_simple_dte_generada',
                    'value' => 'yes'
                ),
                array(
                    'key' => '_simple_dte_tipo',
                    'value' => array(39, 41), // Boletas afectas y exentas
                    'compare' => 'IN'
                )
            )
        );

        return wc_get_orders($args);
    }

    /**
     * Construir XML del RVD
     *
     * @param array $orders Array de órdenes
     * @param string $fecha Fecha del RVD
     * @return string|WP_Error XML del RVD
     */
    private static function build_rvd_xml($orders, $fecha) {
        $rut_emisor = get_option('simple_dte_rut_emisor', '');

        // Agrupar por tipo de documento
        $resumen = array(
            39 => array('cantidad' => 0, 'neto' => 0, 'iva' => 0, 'exento' => 0, 'total' => 0),
            41 => array('cantidad' => 0, 'neto' => 0, 'iva' => 0, 'exento' => 0, 'total' => 0)
        );

        $folios_usados = array(
            39 => array('min' => PHP_INT_MAX, 'max' => 0),
            41 => array('min' => PHP_INT_MAX, 'max' => 0)
        );

        foreach ($orders as $order) {
            $tipo = (int) $order->get_meta('_simple_dte_tipo');
            $folio = (int) $order->get_meta('_simple_dte_folio');
            $total = (float) $order->get_total();

            if (!isset($resumen[$tipo])) {
                continue;
            }

            $resumen[$tipo]['cantidad']++;

            if ($tipo == 39) { // Boleta afecta
                $neto = Simple_DTE_Helpers::calcular_neto($total);
                $iva = $total - $neto;
                $resumen[$tipo]['neto'] += $neto;
                $resumen[$tipo]['iva'] += $iva;
            } else { // Boleta exenta
                $resumen[$tipo]['exento'] += $total;
            }

            $resumen[$tipo]['total'] += $total;

            // Actualizar rango de folios
            if ($folio > 0) {
                $folios_usados[$tipo]['min'] = min($folios_usados[$tipo]['min'], $folio);
                $folios_usados[$tipo]['max'] = max($folios_usados[$tipo]['max'], $folio);
            }
        }

        // Construir XML según schema del SII
        $xml = '<?xml version="1.0" encoding="ISO-8859-1"?>' . "\n";
        $xml .= '<ConsumoFolios xmlns="http://www.sii.cl/SiiDte" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="1.0">' . "\n";
        $xml .= '<DocumentoConsumoFolios ID="RVD">' . "\n";

        // Carátula
        $xml .= '<Caratula>' . "\n";
        $xml .= '<RutEmisor>' . esc_html($rut_emisor) . '</RutEmisor>' . "\n";
        $xml .= '<RutEnvia>' . esc_html($rut_emisor) . '</RutEnvia>' . "\n";
        $xml .= '<FchResol>2014-08-22</FchResol>' . "\n";
        $xml .= '<NroResol>0</NroResol>' . "\n";
        $xml .= '<FchInicio>' . $fecha . '</FchInicio>' . "\n";
        $xml .= '<FchFinal>' . $fecha . '</FchFinal>' . "\n";
        $xml .= '<SecEnvio>1</SecEnvio>' . "\n";
        $xml .= '<TmstFirmaEnv>' . date('Y-m-d\TH:i:s') . '</TmstFirmaEnv>' . "\n";
        $xml .= '</Caratula>' . "\n";

        // Resumen por tipo de documento
        foreach ($resumen as $tipo => $datos) {
            if ($datos['cantidad'] == 0) {
                continue;
            }

            $xml .= '<Resumen>' . "\n";
            $xml .= '<TipoDocumento>' . $tipo . '</TipoDocumento>' . "\n";

            if ($tipo == 39) { // Boleta afecta
                $xml .= '<MntNeto>' . (int) round($datos['neto']) . '</MntNeto>' . "\n";
                $xml .= '<MntIva>' . (int) round($datos['iva']) . '</MntIva>' . "\n";
                $xml .= '<TasaIVA>19</TasaIVA>' . "\n";
                $xml .= '<MntExento>0</MntExento>' . "\n";
            } else { // Boleta exenta
                $xml .= '<MntNeto>0</MntNeto>' . "\n";
                $xml .= '<MntExento>' . (int) round($datos['exento']) . '</MntExento>' . "\n";
            }

            $xml .= '<MntTotal>' . (int) round($datos['total']) . '</MntTotal>' . "\n";
            $xml .= '<FoliosEmitidos>' . $datos['cantidad'] . '</FoliosEmitidos>' . "\n";
            $xml .= '<FoliosAnulados>0</FoliosAnulados>' . "\n";
            $xml .= '<FoliosUtilizados>' . $datos['cantidad'] . '</FoliosUtilizados>' . "\n";

            // Rango de folios (si hay folios válidos)
            if ($folios_usados[$tipo]['min'] != PHP_INT_MAX) {
                $xml .= '<RangoUtilizados>' . "\n";
                $xml .= '<Inicial>' . $folios_usados[$tipo]['min'] . '</Inicial>' . "\n";
                $xml .= '<Final>' . $folios_usados[$tipo]['max'] . '</Final>' . "\n";
                $xml .= '</RangoUtilizados>' . "\n";
            }

            $xml .= '</Resumen>' . "\n";
        }

        $xml .= '</DocumentoConsumoFolios>' . "\n";
        $xml .= '</ConsumoFolios>';

        return $xml;
    }

    /**
     * Guardar registro del envío
     */
    private static function guardar_registro_envio($fecha, $resultado) {
        $registros = get_option('simple_dte_rvd_enviados', array());

        $registros[] = array(
            'fecha' => $fecha,
            'fecha_envio' => current_time('mysql'),
            'track_id' => isset($resultado['track_id']) ? $resultado['track_id'] : null,
            'estado' => 'enviado'
        );

        // Mantener solo los últimos 90 días
        $registros = array_slice($registros, -90);

        update_option('simple_dte_rvd_enviados', $registros);
    }

    /**
     * Envío automático diario
     */
    public static function enviar_rvd_automatico() {
        // Solo ejecutar en certificación
        if (!Simple_DTE_Helpers::is_certificacion()) {
            return;
        }

        // Verificar si está habilitado
        if (!get_option('simple_dte_rvd_auto', false)) {
            return;
        }

        $ayer = date('Y-m-d', strtotime('-1 day'));

        Simple_DTE_Logger::info('Iniciando envío automático de RVD', array('fecha' => $ayer));

        // Generar RVD
        $resultado_gen = self::generar_rvd($ayer);

        if (is_wp_error($resultado_gen)) {
            Simple_DTE_Logger::warning('No se pudo generar RVD automático', array(
                'error' => $resultado_gen->get_error_message()
            ));
            return;
        }

        // Enviar RVD
        $resultado_envio = self::enviar_rvd($resultado_gen['xml'], $ayer);

        if (is_wp_error($resultado_envio)) {
            Simple_DTE_Logger::error('Error en envío automático de RVD', array(
                'error' => $resultado_envio->get_error_message()
            ));
            return;
        }

        Simple_DTE_Logger::info('RVD enviado automáticamente', array(
            'fecha' => $ayer,
            'track_id' => $resultado_envio['track_id']
        ));
    }

    /**
     * Obtener historial de RVDs enviados
     */
    public static function get_historial_envios() {
        $registros = get_option('simple_dte_rvd_enviados', array());
        return array_reverse($registros); // Más recientes primero
    }

    /**
     * AJAX: Generar RVD
     */
    public static function ajax_generar_rvd() {
        check_ajax_referer('simple_dte_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes', 'simple-dte')));
        }

        $fecha = isset($_POST['fecha']) ? sanitize_text_field($_POST['fecha']) : '';

        if (empty($fecha)) {
            $fecha = date('Y-m-d', strtotime('-1 day')); // Ayer por defecto
        }

        $resultado = self::generar_rvd($fecha);

        if (is_wp_error($resultado)) {
            wp_send_json_error(array('message' => $resultado->get_error_message()));
        }

        wp_send_json_success($resultado);
    }

    /**
     * AJAX: Enviar RVD
     */
    public static function ajax_enviar_rvd() {
        check_ajax_referer('simple_dte_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes', 'simple-dte')));
        }

        $fecha = isset($_POST['fecha']) ? sanitize_text_field($_POST['fecha']) : '';
        $xml = isset($_POST['xml']) ? $_POST['xml'] : '';

        if (empty($fecha) || empty($xml)) {
            wp_send_json_error(array('message' => __('Faltan datos requeridos', 'simple-dte')));
        }

        $resultado = self::enviar_rvd($xml, $fecha);

        if (is_wp_error($resultado)) {
            wp_send_json_error(array('message' => $resultado->get_error_message()));
        }

        wp_send_json_success($resultado);
    }
}
