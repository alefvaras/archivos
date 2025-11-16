<?php
/**
 * Generador de Boletas Electrónicas
 *
 * @package Simple_DTE
 */

if (!defined('ABSPATH')) {
    exit;
}

class Simple_DTE_Boleta_Generator {

    /**
     * Generar boleta desde una orden de WooCommerce
     *
     * @param WC_Order $order Orden de WooCommerce
     * @param array $opciones Opciones adicionales (caso_prueba, etc.)
     * @return array|WP_Error Resultado de la generación
     */
    public static function generar_desde_orden($order, $opciones = array()) {
        Simple_DTE_Logger::info('Generando boleta para orden', array('order_id' => $order->get_id()));

        // Validar configuración
        $validacion = self::validar_configuracion();
        if (is_wp_error($validacion)) {
            return $validacion;
        }

        // Obtener folio
        $folio = self::obtener_siguiente_folio();
        if (is_wp_error($folio)) {
            return $folio;
        }

        // Construir datos del documento
        $documento_data = array(
            'Documento' => array(
                'Encabezado' => self::build_encabezado($order, $folio, $opciones),
                'Detalles' => self::build_detalles($order, $opciones),
            ),
            'Certificado' => self::get_certificado_data()
        );

        // Agregar referencias si es caso de prueba
        if (!empty($opciones['caso_prueba'])) {
            $documento_data['Documento']['Referencias'] = self::build_referencias_prueba($opciones['caso_prueba']);
        }

        // Obtener rutas de archivos
        $cert_path = get_option('simple_dte_cert_path', '');
        $caf_path = self::get_caf_path(39); // 39 = Boleta Electrónica

        if (!file_exists($cert_path)) {
            return new WP_Error('cert_not_found', __('Certificado digital no encontrado. Configure en ajustes.', 'simple-dte'));
        }

        if (!file_exists($caf_path)) {
            return new WP_Error('caf_not_found', __('Archivo CAF no encontrado. Cargue un CAF para boletas.', 'simple-dte'));
        }

        // Llamar a la API
        $resultado = Simple_DTE_API_Client::generar_dte($documento_data, $cert_path, $caf_path);

        if (is_wp_error($resultado)) {
            Simple_DTE_Logger::error('Error al generar boleta', array(
                'error' => $resultado->get_error_message()
            ), $order->get_id());
            return $resultado;
        }

        // Guardar metadatos en la orden
        self::guardar_metadatos_orden($order, $folio, $resultado);

        // Actualizar contador de folios
        self::actualizar_folio_usado($folio);

        Simple_DTE_Logger::info('Boleta generada exitosamente', array(
            'folio' => $folio,
            'order_id' => $order->get_id()
        ), $order->get_id());

        return array(
            'success' => true,
            'folio' => $folio,
            'xml' => isset($resultado['xml']) ? $resultado['xml'] : null,
            'mensaje' => sprintf(__('Boleta N° %d generada correctamente', 'simple-dte'), $folio)
        );
    }

    /**
     * Construir encabezado del documento
     */
    private static function build_encabezado($order, $folio, $opciones) {
        $rut_emisor = get_option('simple_dte_rut_emisor', '');
        $razon_social = get_option('simple_dte_razon_social', '');
        $giro = get_option('simple_dte_giro', '');
        $direccion = get_option('simple_dte_direccion', '');
        $comuna = get_option('simple_dte_comuna', '');

        // Calcular totales
        $total = (float) $order->get_total();
        $neto = Simple_DTE_Helpers::calcular_neto($total);
        $iva = $total - $neto;

        $encabezado = array(
            'IdentificacionDTE' => array(
                'TipoDTE' => 39, // Boleta Electrónica
                'Folio' => (int) $folio,
                'FechaEmision' => Simple_DTE_Helpers::get_fecha_actual(),
                'IndicadorServicio' => 3 // Ventas y servicios
            ),
            'Emisor' => array(
                'Rut' => $rut_emisor,
                'RazonSocialBoleta' => $razon_social,
                'GiroBoleta' => $giro,
                'DireccionOrigen' => $direccion,
                'ComunaOrigen' => $comuna
            ),
            'Receptor' => self::build_receptor($order),
            'Totales' => array(
                'MontoNeto' => (int) round($neto),
                'IVA' => (int) round($iva),
                'MontoTotal' => (int) round($total),
                'MontoExento' => 0
            )
        );

        return $encabezado;
    }

    /**
     * Construir datos del receptor
     */
    private static function build_receptor($order) {
        $rut_receptor = $order->get_meta('_billing_rut');

        // Si no hay RUT específico, usar genérico
        if (empty($rut_receptor)) {
            $rut_receptor = '66666666-6'; // Consumidor final
        }

        $receptor = array(
            'Rut' => $rut_receptor,
            'RazonSocial' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'Direccion' => $order->get_billing_address_1(),
            'Comuna' => Simple_DTE_Helpers::normalizar_comuna($order->get_billing_city()),
            'Ciudad' => $order->get_billing_state(),
            'Contacto' => $order->get_billing_phone()
        );

        return $receptor;
    }

    /**
     * Construir detalles (items) del documento
     */
    private static function build_detalles($order, $opciones) {
        $detalles = array();

        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $cantidad = (float) $item->get_quantity();
            $precio_unitario = (float) ($item->get_total() / $cantidad);

            $detalle = array(
                'IndicadorExento' => 0, // No exento
                'Nombre' => $item->get_name(),
                'Descripcion' => '',
                'Cantidad' => $cantidad,
                'UnidadMedida' => 'un',
                'Precio' => round($precio_unitario),
                'Descuento' => 0,
                'Recargo' => 0,
                'MontoItem' => (int) round($item->get_total())
            );

            $detalles[] = $detalle;
        }

        // Agregar envío si existe
        if ($order->get_shipping_total() > 0) {
            $detalles[] = array(
                'IndicadorExento' => 0,
                'Nombre' => 'Envío',
                'Descripcion' => $order->get_shipping_method(),
                'Cantidad' => 1,
                'UnidadMedida' => 'un',
                'Precio' => (int) round($order->get_shipping_total()),
                'Descuento' => 0,
                'Recargo' => 0,
                'MontoItem' => (int) round($order->get_shipping_total())
            );
        }

        return $detalles;
    }

    /**
     * Construir referencias para set de pruebas
     */
    private static function build_referencias_prueba($caso_prueba) {
        return array(
            array(
                'NroLinRef' => 1,
                'TpoDocRef' => 'SET',
                'FolioRef' => 0,
                'FchRef' => Simple_DTE_Helpers::get_fecha_actual(),
                'CodRef' => 'SET',
                'RazonRef' => $caso_prueba
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
     * Validar configuración necesaria
     */
    private static function validar_configuracion() {
        $campos_requeridos = array(
            'simple_dte_rut_emisor' => 'RUT del emisor',
            'simple_dte_razon_social' => 'Razón social',
            'simple_dte_giro' => 'Giro',
            'simple_dte_direccion' => 'Dirección',
            'simple_dte_comuna' => 'Comuna',
            'simple_dte_cert_rut' => 'RUT del certificado',
            'simple_dte_cert_password' => 'Contraseña del certificado',
            'simple_dte_api_key' => 'API Key'
        );

        foreach ($campos_requeridos as $opcion => $nombre) {
            if (empty(get_option($opcion))) {
                return new WP_Error(
                    'config_incompleta',
                    sprintf(__('Configuración incompleta: falta %s', 'simple-dte'), $nombre)
                );
            }
        }

        return true;
    }

    /**
     * Obtener siguiente folio disponible
     */
    private static function obtener_siguiente_folio() {
        global $wpdb;

        $table = $wpdb->prefix . 'simple_dte_folios';

        // Obtener CAF activo para boletas (tipo 39)
        $caf = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE tipo_dte = %d AND estado = 'activo' ORDER BY id DESC LIMIT 1",
            39
        ));

        if (!$caf) {
            return new WP_Error('no_caf', __('No hay CAF activo para boletas electrónicas', 'simple-dte'));
        }

        $siguiente_folio = (int) $caf->folio_actual + 1;

        if ($siguiente_folio > $caf->folio_hasta) {
            return new WP_Error('folios_agotados', __('Se agotaron los folios del CAF actual', 'simple-dte'));
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
            array('tipo_dte' => 39, 'estado' => 'activo'),
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
        $order->update_meta_data('_simple_dte_generada', 'yes');
        $order->update_meta_data('_simple_dte_folio', $folio);
        $order->update_meta_data('_simple_dte_tipo', 39);
        $order->update_meta_data('_simple_dte_fecha_generacion', current_time('mysql'));

        if (isset($resultado['xml'])) {
            $order->update_meta_data('_simple_dte_xml', $resultado['xml']);
        }

        $order->add_order_note(sprintf(__('Boleta electrónica N° %d generada correctamente', 'simple-dte'), $folio));

        $order->save();
    }
}
