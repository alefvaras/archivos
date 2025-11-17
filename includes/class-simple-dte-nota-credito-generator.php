<?php
/**
 * Generador de Notas de Crédito Electrónicas
 *
 * @package Simple_DTE
 */

if (!defined('ABSPATH')) {
    exit;
}

class Simple_DTE_Nota_Credito_Generator {

    /**
     * Inicializar hooks
     */
    public static function init() {
        // Hook para generación automática de NC cuando se crea un refund
        add_action('woocommerce_order_refunded', array(__CLASS__, 'auto_generar_nc_on_refund'), 10, 2);
    }

    /**
     * Generar NC automáticamente cuando se crea un refund
     *
     * @param int $order_id ID de la orden
     * @param int $refund_id ID del refund
     */
    public static function auto_generar_nc_on_refund($order_id, $refund_id) {
        // Verificar si la generación automática está habilitada
        if (!get_option('simple_dte_auto_nc_enabled')) {
            Simple_DTE_Logger::info('NC automática deshabilitada en configuración', array(
                'order_id' => $order_id,
                'refund_id' => $refund_id
            ));
            return;
        }

        $order = wc_get_order($order_id);
        $refund = wc_get_order($refund_id);

        if (!$order || !$refund) {
            Simple_DTE_Logger::error('Orden o refund no encontrado para NC automática', array(
                'order_id' => $order_id,
                'refund_id' => $refund_id
            ));
            return;
        }

        // Verificar que la orden tenga DTE generado
        if ($order->get_meta('_simple_dte_generada') !== 'yes') {
            Simple_DTE_Logger::info('Orden sin DTE, no se puede generar NC automática', array(
                'order_id' => $order_id
            ));
            return;
        }

        // Verificar si ya tiene NC generada
        if ($order->get_meta('_simple_dte_nc_generada') === 'yes') {
            Simple_DTE_Logger::info('Orden ya tiene NC generada', array(
                'order_id' => $order_id
            ));
            return;
        }

        // Validar monto si está configurado
        $validar_monto = get_option('simple_dte_auto_nc_validar_monto');
        if ($validar_monto) {
            $monto_refund = abs((float) $refund->get_total());
            $monto_orden = (float) $order->get_total();

            if ($monto_refund != $monto_orden) {
                Simple_DTE_Logger::info('Refund parcial, se requiere generación manual de NC', array(
                    'order_id' => $order_id,
                    'monto_refund' => $monto_refund,
                    'monto_orden' => $monto_orden
                ));

                // Agregar nota en la orden
                $order->add_order_note(
                    __('Reembolso parcial creado. Genere la Nota de Crédito manualmente desde el metabox.', 'simple-dte')
                );

                return;
            }
        }

        // Obtener tipo de NC configurado
        $codigo_ref = get_option('simple_dte_auto_nc_tipo', 1);

        // Generar NC
        Simple_DTE_Logger::info('Generando NC automática', array(
            'order_id' => $order_id,
            'refund_id' => $refund_id,
            'codigo_ref' => $codigo_ref
        ));

        $resultado = self::generar_desde_orden(
            $order,
            $refund,
            array('codigo_ref' => (int) $codigo_ref)
        );

        if (is_wp_error($resultado)) {
            Simple_DTE_Logger::error('Error al generar NC automática', array(
                'error' => $resultado->get_error_message(),
                'order_id' => $order_id
            ), $order_id);

            // Agregar nota de error en la orden
            $order->add_order_note(
                sprintf(
                    __('Error al generar NC automática: %s. Genere la NC manualmente.', 'simple-dte'),
                    $resultado->get_error_message()
                )
            );
        } else {
            Simple_DTE_Logger::info('NC automática generada exitosamente', array(
                'order_id' => $order_id,
                'folio' => $resultado['folio']
            ), $order_id);

            // Agregar nota de éxito
            $order->add_order_note(
                sprintf(
                    __('✓ Nota de Crédito N° %d generada automáticamente', 'simple-dte'),
                    $resultado['folio']
                )
            );
        }
    }

    /**
     * Generar nota de crédito desde una orden/refund
     *
     * @param WC_Order $order Orden original
     * @param WC_Order_Refund $refund Reembolso (opcional)
     * @param array $opciones Opciones adicionales
     * @return array|WP_Error Resultado de la generación
     */
    public static function generar_desde_orden($order, $refund = null, $opciones = array()) {
        Simple_DTE_Logger::info('Generando nota de crédito', array('order_id' => $order->get_id()));

        // Validar que la orden tenga boleta/factura
        $tiene_dte = $order->get_meta('_simple_dte_generada');
        if ($tiene_dte !== 'yes') {
            return new WP_Error('no_dte_original', __('La orden no tiene DTE generado', 'simple-dte'));
        }

        // Obtener datos del DTE original
        $folio_original = $order->get_meta('_simple_dte_folio');
        $tipo_dte_original = $order->get_meta('_simple_dte_tipo');

        if (!$folio_original || !$tipo_dte_original) {
            return new WP_Error('datos_incompletos', __('Faltan datos del DTE original', 'simple-dte'));
        }

        // Obtener folio para la nota de crédito
        $folio = self::obtener_siguiente_folio();
        if (is_wp_error($folio)) {
            return $folio;
        }

        // Determinar código de referencia
        $codigo_ref = isset($opciones['codigo_ref']) ? $opciones['codigo_ref'] : 1; // 1 = Anular, 2 = Corregir texto, 3 = Corregir montos

        // Construir datos del documento
        $documento_data = array(
            'Documento' => array(
                'Encabezado' => self::build_encabezado($order, $refund, $folio),
                'Detalles' => self::build_detalles($order, $refund),
                'Referencias' => self::build_referencias($tipo_dte_original, $folio_original, $codigo_ref, $opciones)
            ),
            'Certificado' => self::get_certificado_data()
        );

        // Obtener rutas de archivos
        $cert_path = get_option('simple_dte_cert_path', '');
        $caf_path = self::get_caf_path(61); // 61 = Nota de Crédito

        if (!file_exists($cert_path)) {
            return new WP_Error('cert_not_found', __('Certificado digital no encontrado', 'simple-dte'));
        }

        if (!file_exists($caf_path)) {
            return new WP_Error('caf_not_found', __('Archivo CAF no encontrado para notas de crédito', 'simple-dte'));
        }

        // Llamar a la API
        $resultado = Simple_DTE_API_Client::generar_dte($documento_data, $cert_path, $caf_path);

        if (is_wp_error($resultado)) {
            Simple_DTE_Logger::error('Error al generar nota de crédito', array(
                'error' => $resultado->get_error_message()
            ), $order->get_id());
            return $resultado;
        }

        // Guardar metadatos
        self::guardar_metadatos_orden($order, $folio, $resultado);

        // Actualizar contador de folios
        self::actualizar_folio_usado($folio);

        Simple_DTE_Logger::info('Nota de crédito generada exitosamente', array(
            'folio' => $folio,
            'order_id' => $order->get_id()
        ), $order->get_id());

        return array(
            'success' => true,
            'folio' => $folio,
            'xml' => isset($resultado['xml']) ? $resultado['xml'] : null,
            'mensaje' => sprintf(__('Nota de Crédito N° %d generada correctamente', 'simple-dte'), $folio)
        );
    }

    /**
     * Construir encabezado del documento
     */
    private static function build_encabezado($order, $refund, $folio) {
        $rut_emisor = get_option('simple_dte_rut_emisor', '');
        $razon_social = get_option('simple_dte_razon_social', '');
        $giro = get_option('simple_dte_giro', '');
        $direccion = get_option('simple_dte_direccion', '');
        $comuna = get_option('simple_dte_comuna', '');

        // Si hay refund, usar sus montos
        if ($refund) {
            $total = abs((float) $refund->get_total());
        } else {
            $total = (float) $order->get_total();
        }

        $neto = Simple_DTE_Helpers::calcular_neto($total);
        $iva = $total - $neto;

        // Obtener datos del receptor
        $rut_receptor = $order->get_meta('_billing_rut');
        if (empty($rut_receptor)) {
            $rut_receptor = '66666666-6';
        }

        $encabezado = array(
            'IdentificacionDTE' => array(
                'TipoDTE' => 61, // Nota de Crédito
                'Folio' => (int) $folio,
                'FechaEmision' => Simple_DTE_Helpers::get_fecha_actual(),
                'FormaPago' => 1 // Contado
            ),
            'Emisor' => array(
                'Rut' => $rut_emisor,
                'RazonSocial' => $razon_social,
                'Giro' => $giro,
                'DireccionOrigen' => $direccion,
                'ComunaOrigen' => $comuna
            ),
            'Receptor' => array(
                'Rut' => $rut_receptor,
                'RazonSocial' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'Direccion' => $order->get_billing_address_1(),
                'Comuna' => Simple_DTE_Helpers::normalizar_comuna($order->get_billing_city()),
                'Giro' => 'Particular'
            ),
            'Totales' => array(
                'MontoNeto' => (int) round($neto),
                'TasaIVA' => 19,
                'IVA' => (int) round($iva),
                'MontoTotal' => (int) round($total)
            )
        );

        return $encabezado;
    }

    /**
     * Construir detalles (items) del documento
     */
    private static function build_detalles($order, $refund) {
        $detalles = array();

        // Si hay refund, usar sus items
        if ($refund) {
            foreach ($refund->get_items() as $item_id => $item) {
                $cantidad = abs((float) $item->get_quantity());
                $precio_unitario = abs((float) ($item->get_total() / $cantidad));

                $detalle = array(
                    'IndicadorExento' => 0,
                    'Nombre' => $item->get_name(),
                    'Descripcion' => 'Devolución',
                    'Cantidad' => $cantidad,
                    'UnidadMedida' => 'un',
                    'Precio' => round($precio_unitario),
                    'Descuento' => 0,
                    'Recargo' => 0,
                    'MontoItem' => (int) round(abs($item->get_total()))
                );

                $detalles[] = $detalle;
            }
        } else {
            // Usar items de la orden original
            foreach ($order->get_items() as $item_id => $item) {
                $cantidad = (float) $item->get_quantity();
                $precio_unitario = (float) ($item->get_total() / $cantidad);

                $detalle = array(
                    'IndicadorExento' => 0,
                    'Nombre' => $item->get_name(),
                    'Descripcion' => 'Anulación',
                    'Cantidad' => $cantidad,
                    'UnidadMedida' => 'un',
                    'Precio' => round($precio_unitario),
                    'Descuento' => 0,
                    'Recargo' => 0,
                    'MontoItem' => (int) round($item->get_total())
                );

                $detalles[] = $detalle;
            }
        }

        return $detalles;
    }

    /**
     * Construir referencias al documento original
     */
    private static function build_referencias($tipo_dte_original, $folio_original, $codigo_ref, $opciones) {
        $razon_ref = '';

        switch ($codigo_ref) {
            case 1:
                $razon_ref = 'Anula documento';
                break;
            case 2:
                $razon_ref = isset($opciones['razon_ref']) ? $opciones['razon_ref'] : 'Corrige texto del documento';
                break;
            case 3:
                $razon_ref = 'Corrige montos';
                break;
            default:
                $razon_ref = isset($opciones['razon_ref']) ? $opciones['razon_ref'] : 'Nota de Crédito';
        }

        return array(
            array(
                'NroLinRef' => 1,
                'TpoDocRef' => (string) $tipo_dte_original,
                'FolioRef' => (int) $folio_original,
                'FchRef' => Simple_DTE_Helpers::get_fecha_actual(),
                'CodRef' => (int) $codigo_ref,
                'RazonRef' => $razon_ref
            )
        );
    }

    /**
     * Obtener datos del certificado
     */
    private static function get_certificado_data() {
        return array(
            'Rut' => get_option('simple_dte_cert_rut', ''),
            'Password' => get_option('simple_dte_cert_password', '')
        );
    }

    /**
     * Obtener siguiente folio disponible
     */
    private static function obtener_siguiente_folio() {
        global $wpdb;

        $table = $wpdb->prefix . 'simple_dte_folios';

        $caf = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE tipo_dte = %d AND estado = 'activo' ORDER BY id DESC LIMIT 1",
            61
        ));

        if (!$caf) {
            return new WP_Error('no_caf', __('No hay CAF activo para notas de crédito', 'simple-dte'));
        }

        $siguiente_folio = (int) $caf->folio_actual + 1;

        if ($siguiente_folio > $caf->folio_hasta) {
            return new WP_Error('folios_agotados', __('Se agotaron los folios del CAF para notas de crédito', 'simple-dte'));
        }

        return $siguiente_folio;
    }

    /**
     * Actualizar folio usado
     */
    private static function actualizar_folio_usado($folio) {
        global $wpdb;

        $table = $wpdb->prefix . 'simple_dte_folios';

        $wpdb->update(
            $table,
            array('folio_actual' => $folio),
            array('tipo_dte' => 61, 'estado' => 'activo'),
            array('%d'),
            array('%d', '%s')
        );
    }

    /**
     * Obtener ruta del CAF
     */
    private static function get_caf_path($tipo_dte) {
        global $wpdb;

        $table = $wpdb->prefix . 'simple_dte_folios';

        $caf = $wpdb->get_row($wpdb->prepare(
            "SELECT archivo_caf FROM $table WHERE tipo_dte = %d AND estado = 'activo' ORDER BY id DESC LIMIT 1",
            $tipo_dte
        ));

        return $caf ? $caf->archivo_caf : '';
    }

    /**
     * Guardar metadatos en la orden
     */
    private static function guardar_metadatos_orden($order, $folio, $resultado) {
        $order->update_meta_data('_simple_dte_nc_generada', 'yes');
        $order->update_meta_data('_simple_dte_nc_folio', $folio);
        $order->update_meta_data('_simple_dte_nc_fecha', current_time('mysql'));

        if (isset($resultado['xml'])) {
            $order->update_meta_data('_simple_dte_nc_xml', $resultado['xml']);
        }

        $order->add_order_note(sprintf(__('Nota de Crédito N° %d generada correctamente', 'simple-dte'), $folio));

        $order->save();
    }
}
