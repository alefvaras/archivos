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

        // Enviar por email si está habilitado
        $auto_email = get_option('simple_dte_auto_email_enabled', false);
        if ($auto_email) {
            // Obtener ruta del PDF si existe
            $pdf_path = $order->get_meta('_simple_dte_pdf_path');
            if (!empty($pdf_path) && file_exists($pdf_path)) {
                $envio_email = Simple_DTE_Email::enviar_boleta_cliente($order, $pdf_path);
                if (is_wp_error($envio_email)) {
                    Simple_DTE_Logger::warning('No se pudo enviar boleta por email', array(
                        'order_id' => $order->get_id(),
                        'folio' => $folio,
                        'error' => $envio_email->get_error_message()
                    ), $order->get_id());
                }
            }
        }

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
     *
     * Funcionalidades:
     * - Cambio automático de CAF cuando se agota
     * - Alerta cuando quedan menos del 10% de folios
     * - Marcado de CAF como "usado" al agotarse
     */
    private static function obtener_siguiente_folio() {
        global $wpdb;

        $table = $wpdb->prefix . 'simple_dte_folios';

        // 1. Obtener CAF activo para boletas (tipo 39)
        $caf = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE tipo_dte = %d AND estado = 'activo' ORDER BY id DESC LIMIT 1",
            39
        ));

        if (!$caf) {
            return new WP_Error('no_caf', __('No hay CAF activo para boletas electrónicas. Por favor sube un archivo CAF.', 'simple-dte'));
        }

        $siguiente_folio = (int) $caf->folio_actual + 1;

        // 2. Verificar si se agotó el CAF actual
        if ($siguiente_folio > $caf->folio_hasta) {

            Simple_DTE_Logger::info('CAF agotado, buscando siguiente CAF disponible', array(
                'caf_id' => $caf->id,
                'folio_ultimo' => $caf->folio_hasta
            ));

            // 2.1 Marcar CAF actual como usado
            $wpdb->update(
                $table,
                array('estado' => 'usado'),
                array('id' => $caf->id),
                array('%s'),
                array('%d')
            );

            // 2.2 Buscar siguiente CAF disponible (puede estar como 'pendiente' o 'activo')
            $siguiente_caf = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table
                 WHERE tipo_dte = %d
                 AND estado IN ('pendiente', 'activo')
                 AND id != %d
                 ORDER BY folio_desde ASC
                 LIMIT 1",
                39,
                $caf->id
            ));

            if ($siguiente_caf) {
                // 2.3 Activar siguiente CAF
                $wpdb->update(
                    $table,
                    array('estado' => 'activo'),
                    array('id' => $siguiente_caf->id),
                    array('%s'),
                    array('%d')
                );

                Simple_DTE_Logger::info('✅ CAF automáticamente activado', array(
                    'caf_id' => $siguiente_caf->id,
                    'folio_desde' => $siguiente_caf->folio_desde,
                    'folio_hasta' => $siguiente_caf->folio_hasta,
                    'total_folios' => ($siguiente_caf->folio_hasta - $siguiente_caf->folio_desde + 1)
                ));

                // Retornar primer folio del nuevo CAF
                return (int) $siguiente_caf->folio_desde;
            }

            // 2.4 No hay más CAFs disponibles
            Simple_DTE_Logger::error('❌ No hay más CAFs disponibles');

            return new WP_Error(
                'folios_agotados',
                __('Se agotaron todos los folios disponibles. Por favor sube un nuevo archivo CAF del SII.', 'simple-dte')
            );
        }

        // 3. Verificar folios bajos (menos del 10%)
        $total_folios = $caf->folio_hasta - $caf->folio_desde + 1;
        $folios_restantes = $caf->folio_hasta - ($siguiente_folio - 1);
        $porcentaje = ($folios_restantes / $total_folios) * 100;

        if ($porcentaje < 10 && $porcentaje > 0) {
            self::alertar_folios_bajos($folios_restantes, $caf);
        }

        return $siguiente_folio;
    }

    /**
     * Alertar cuando quedan pocos folios (menos del 10%)
     *
     * @param int $folios_restantes Cantidad de folios restantes
     * @param object $caf Objeto del CAF activo
     */
    private static function alertar_folios_bajos($folios_restantes, $caf) {
        // Solo alertar una vez por CAF para no spam
        $transient_key = 'simple_dte_alerta_folios_' . $caf->id;
        $alerta_enviada = get_transient($transient_key);

        if (!$alerta_enviada) {
            // Registrar en logs
            Simple_DTE_Logger::warning('⚠️ Folios bajos - Quedan menos del 10%', array(
                'caf_id' => $caf->id,
                'folios_restantes' => $folios_restantes,
                'folio_actual' => $caf->folio_actual,
                'folio_hasta' => $caf->folio_hasta,
                'porcentaje' => round(($folios_restantes / ($caf->folio_hasta - $caf->folio_desde + 1)) * 100, 2) . '%'
            ));

            // Enviar email al administrador
            $admin_email = get_option('admin_email');
            $razon_social = get_option('simple_dte_razon_social', get_bloginfo('name'));

            $subject = '⚠️ Alerta: Quedan Pocos Folios - Simple DTE';
            $message = "Hola,\n\n";
            $message .= "El sistema de boletas electrónicas de {$razon_social} está quedándose sin folios.\n\n";
            $message .= "Detalles:\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "• Folios restantes: {$folios_restantes}\n";
            $message .= "• CAF actual: {$caf->folio_desde} a {$caf->folio_hasta}\n";
            $message .= "• Folio en uso: {$caf->folio_actual}\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
            $message .= "ACCIÓN REQUERIDA:\n";
            $message .= "Por favor descarga un nuevo archivo CAF desde el sitio del SII y súbelo en:\n";
            $message .= "WordPress → WooCommerce → Simple DTE → Folios\n\n";
            $message .= "Esto evitará interrupciones en la generación de boletas.\n\n";
            $message .= "Saludos,\n";
            $message .= "Sistema Simple DTE";

            wp_mail($admin_email, $subject, $message);

            // Marcar alerta como enviada (válido por 7 días para este CAF específico)
            set_transient($transient_key, true, 7 * DAY_IN_SECONDS);
        }
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
