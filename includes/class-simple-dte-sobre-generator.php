<?php
/**
 * Generador de Sobres de Envío al SII
 *
 * @package Simple_DTE
 */

if (!defined('ABSPATH')) {
    exit;
}

class Simple_DTE_Sobre_Generator {

    /**
     * Generar y enviar sobre con DTEs al SII
     *
     * @param array $dtes_xml Array de XMLs de DTEs a enviar
     * @param array $opciones Opciones adicionales
     * @return array|WP_Error Resultado del envío
     */
    public static function generar_y_enviar($dtes_xml, $opciones = array()) {
        Simple_DTE_Logger::info('Generando sobre de envío', array('cantidad_dtes' => count($dtes_xml)));

        if (empty($dtes_xml)) {
            return new WP_Error('no_dtes', __('No hay DTEs para enviar', 'simple-dte'));
        }

        // Construir XML del sobre
        $sobre_xml = self::build_sobre_xml($dtes_xml, $opciones);

        if (is_wp_error($sobre_xml)) {
            return $sobre_xml;
        }

        // Obtener ruta del certificado
        $cert_path = get_option('simple_dte_cert_path', '');

        if (!file_exists($cert_path)) {
            return new WP_Error('cert_not_found', __('Certificado digital no encontrado', 'simple-dte'));
        }

        // Enviar a la API
        $resultado = Simple_DTE_API_Client::enviar_sobre($sobre_xml, $cert_path);

        if (is_wp_error($resultado)) {
            Simple_DTE_Logger::error('Error al enviar sobre', array(
                'error' => $resultado->get_error_message()
            ));
            return $resultado;
        }

        Simple_DTE_Logger::info('Sobre enviado exitosamente', array(
            'cantidad_dtes' => count($dtes_xml)
        ));

        return array(
            'success' => true,
            'track_id' => isset($resultado['track_id']) ? $resultado['track_id'] : null,
            'mensaje' => sprintf(__('Sobre con %d documentos enviado correctamente', 'simple-dte'), count($dtes_xml))
        );
    }

    /**
     * Construir XML del sobre EnvioBoleta o EnvioDTE
     *
     * @param array $dtes_xml Array de XMLs de DTEs
     * @param array $opciones Opciones adicionales
     * @return string|WP_Error XML del sobre
     */
    private static function build_sobre_xml($dtes_xml, $opciones) {
        $rut_emisor = get_option('simple_dte_rut_emisor', '');
        $razon_social = get_option('simple_dte_razon_social', '');

        // Determinar si es EnvioBoleta o EnvioDTE según el primer documento
        $primer_dte = $dtes_xml[0];
        $es_boleta = self::is_boleta_xml($primer_dte);

        if ($es_boleta) {
            return self::build_envio_boleta_xml($dtes_xml, $rut_emisor, $razon_social, $opciones);
        } else {
            return self::build_envio_dte_xml($dtes_xml, $rut_emisor, $razon_social, $opciones);
        }
    }

    /**
     * Construir XML EnvioBoleta
     */
    private static function build_envio_boleta_xml($dtes_xml, $rut_emisor, $razon_social, $opciones) {
        $fecha_resolucion = isset($opciones['fecha_resolucion']) ? $opciones['fecha_resolucion'] : '2014-08-22';
        $nro_resolucion = isset($opciones['nro_resolucion']) ? $opciones['nro_resolucion'] : '0';

        $xml = '<?xml version="1.0" encoding="ISO-8859-1"?>' . "\n";
        $xml .= '<EnvioBOLETA xmlns="http://www.sii.cl/SiiDte" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sii.cl/SiiDte EnvioBOLETA_v11.xsd" version="1.0">' . "\n";

        // SetDTE
        $xml .= '<SetDTE ID="SetDoc">' . "\n";

        // Carátula
        $xml .= '<Caratula version="1.0">' . "\n";
        $xml .= '<RutEmisor>' . esc_html($rut_emisor) . '</RutEmisor>' . "\n";
        $xml .= '<RutEnvia>' . esc_html($rut_emisor) . '</RutEnvia>' . "\n";
        $xml .= '<RutReceptor>60803000-K</RutReceptor>' . "\n"; // RUT del SII
        $xml .= '<FchResol>' . $fecha_resolucion . '</FchResol>' . "\n";
        $xml .= '<NroResol>' . $nro_resolucion . '</NroResol>' . "\n";
        $xml .= '<TmstFirmaEnv>' . date('Y-m-d\TH:i:s') . '</TmstFirmaEnv>' . "\n";
        $xml .= '<SubTotDTE>' . "\n";
        $xml .= '<TpoDTE>39</TpoDTE>' . "\n";
        $xml .= '<NroDTE>' . count($dtes_xml) . '</NroDTE>' . "\n";
        $xml .= '</SubTotDTE>' . "\n";
        $xml .= '</Caratula>' . "\n";

        // Agregar DTEs
        foreach ($dtes_xml as $dte_xml) {
            $xml .= $dte_xml . "\n";
        }

        $xml .= '</SetDTE>' . "\n";
        $xml .= '</EnvioBOLETA>';

        return $xml;
    }

    /**
     * Construir XML EnvioDTE
     */
    private static function build_envio_dte_xml($dtes_xml, $rut_emisor, $razon_social, $opciones) {
        $fecha_resolucion = isset($opciones['fecha_resolucion']) ? $opciones['fecha_resolucion'] : '2014-08-22';
        $nro_resolucion = isset($opciones['nro_resolucion']) ? $opciones['nro_resolucion'] : '0';

        $xml = '<?xml version="1.0" encoding="ISO-8859-1"?>' . "\n";
        $xml .= '<EnvioDTE xmlns="http://www.sii.cl/SiiDte" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sii.cl/SiiDte EnvioDTE_v10.xsd" version="1.0">' . "\n";

        // SetDTE
        $xml .= '<SetDTE ID="SetDoc">' . "\n";

        // Carátula
        $xml .= '<Caratula version="1.0">' . "\n";
        $xml .= '<RutEmisor>' . esc_html($rut_emisor) . '</RutEmisor>' . "\n";
        $xml .= '<RutEnvia>' . esc_html($rut_emisor) . '</RutEnvia>' . "\n";
        $xml .= '<RutReceptor>60803000-K</RutReceptor>' . "\n"; // RUT del SII
        $xml .= '<FchResol>' . $fecha_resolucion . '</FchResol>' . "\n";
        $xml .= '<NroResol>' . $nro_resolucion . '</NroResol>' . "\n";
        $xml .= '<TmstFirmaEnv>' . date('Y-m-d\TH:i:s') . '</TmstFirmaEnv>' . "\n";
        $xml .= '</Caratula>' . "\n";

        // Agregar DTEs
        foreach ($dtes_xml as $dte_xml) {
            $xml .= $dte_xml . "\n";
        }

        $xml .= '</SetDTE>' . "\n";
        $xml .= '</EnvioDTE>';

        return $xml;
    }

    /**
     * Determinar si un XML es de boleta
     */
    private static function is_boleta_xml($xml) {
        // Buscar TipoDTE 39 o 41 en el XML
        return (strpos($xml, '<TipoDTE>39</TipoDTE>') !== false) ||
               (strpos($xml, '<TipoDTE>41</TipoDTE>') !== false);
    }
}
