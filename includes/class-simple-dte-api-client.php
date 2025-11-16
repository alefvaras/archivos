<?php
/**
 * Cliente API para Simple API
 *
 * @package Simple_DTE
 */

if (!defined('ABSPATH')) {
    exit;
}

class Simple_DTE_API_Client {

    /**
     * Obtener API Key
     */
    private static function get_api_key() {
        return get_option('simple_dte_api_key', '');
    }

    /**
     * Obtener headers de autenticación
     */
    private static function get_auth_headers() {
        return array(
            'Authorization' => self::get_api_key()
        );
    }

    /**
     * Generar DTE (Boleta, Factura, Nota, etc.)
     *
     * @param array $documento_data Datos del documento
     * @param string $cert_path Ruta al certificado PFX
     * @param string $caf_path Ruta al archivo CAF
     * @return array|WP_Error Respuesta de la API
     */
    public static function generar_dte($documento_data, $cert_path, $caf_path) {
        Simple_DTE_Logger::debug('API: Generando DTE', array('tipo' => $documento_data['Documento']['Encabezado']['IdentificacionDTE']['TipoDTE']));

        // Validar archivos
        if (!file_exists($cert_path)) {
            return new WP_Error('cert_not_found', __('Certificado digital no encontrado', 'simple-dte'));
        }

        if (!file_exists($caf_path)) {
            return new WP_Error('caf_not_found', __('Archivo CAF no encontrado', 'simple-dte'));
        }

        // Construir URL
        $url = Simple_DTE_Helpers::get_api_base_url() . '/api/v1/dte/generar';

        // Crear boundary para multipart
        $boundary = wp_generate_password(24, false, false);

        // Construir payload multipart
        $payload = self::build_multipart_payload(array(
            array(
                'name' => 'input',
                'content' => wp_json_encode($documento_data, JSON_UNESCAPED_UNICODE),
                'type' => 'text/plain'
            ),
            array(
                'name' => 'files',
                'filename' => basename($cert_path),
                'filepath' => $cert_path,
                'type' => 'application/octet-stream'
            ),
            array(
                'name' => 'files2',
                'filename' => basename($caf_path),
                'filepath' => $caf_path,
                'type' => 'application/octet-stream'
            )
        ), $boundary);

        // Hacer petición
        $response = wp_remote_post($url, array(
            'headers' => array_merge(
                self::get_auth_headers(),
                array('Content-Type' => 'multipart/form-data; boundary=' . $boundary)
            ),
            'body' => $payload,
            'timeout' => 60
        ));

        return self::process_response($response);
    }

    /**
     * Enviar sobre al SII
     *
     * @param string $sobre_xml XML del sobre
     * @param string $cert_path Ruta al certificado PFX
     * @return array|WP_Error Respuesta de la API
     */
    public static function enviar_sobre($sobre_xml, $cert_path) {
        Simple_DTE_Logger::debug('API: Enviando sobre al SII');

        if (!file_exists($cert_path)) {
            return new WP_Error('cert_not_found', __('Certificado digital no encontrado', 'simple-dte'));
        }

        // Construir URL
        $url = Simple_DTE_Helpers::get_api_base_url() . '/api/v1/dte/enviar';

        // Crear archivo temporal para el sobre
        $temp_sobre = tempnam(sys_get_temp_dir(), 'sobre_');
        $sobre_path = $temp_sobre . '.xml';
        @rename($temp_sobre, $sobre_path);
        file_put_contents($sobre_path, $sobre_xml);

        // Crear boundary para multipart
        $boundary = wp_generate_password(24, false, false);

        // Construir payload multipart
        $payload = self::build_multipart_payload(array(
            array(
                'name' => 'files',
                'filename' => basename($cert_path),
                'filepath' => $cert_path,
                'type' => 'application/octet-stream'
            ),
            array(
                'name' => 'files2',
                'filename' => basename($sobre_path),
                'filepath' => $sobre_path,
                'type' => 'application/octet-stream'
            )
        ), $boundary);

        // Hacer petición
        $response = wp_remote_post($url, array(
            'headers' => array_merge(
                self::get_auth_headers(),
                array('Content-Type' => 'multipart/form-data; boundary=' . $boundary)
            ),
            'body' => $payload,
            'timeout' => 60
        ));

        // Limpiar archivo temporal
        @unlink($sobre_path);

        return self::process_response($response);
    }

    /**
     * Consultar estado de un envío
     *
     * @param string $track_id ID de seguimiento
     * @param string $rut_emisor RUT del emisor
     * @return array|WP_Error Respuesta de la API
     */
    public static function consultar_estado_envio($track_id, $rut_emisor) {
        Simple_DTE_Logger::debug('API: Consultando estado de envío', array('track_id' => $track_id));

        // Construir URL
        $url = Simple_DTE_Helpers::get_api_base_url() . '/api/v1/dte/estado/' . $track_id;

        // Hacer petición GET
        $response = wp_remote_get($url, array(
            'headers' => self::get_auth_headers(),
            'timeout' => 30
        ));

        return self::process_response($response);
    }

    /**
     * Consultar un DTE específico
     *
     * @param int $tipo_dte Tipo de DTE (39, 33, 61, etc.)
     * @param int $folio Folio del documento
     * @param string $rut_emisor RUT del emisor
     * @return array|WP_Error Respuesta de la API
     */
    public static function consultar_dte($tipo_dte, $folio, $rut_emisor) {
        Simple_DTE_Logger::debug('API: Consultando DTE', array(
            'tipo' => $tipo_dte,
            'folio' => $folio,
            'rut' => $rut_emisor
        ));

        // Construir URL
        $url = Simple_DTE_Helpers::get_api_base_url() . sprintf(
            '/api/v1/dte/consulta/%s/%d/%s',
            $tipo_dte,
            $folio,
            $rut_emisor
        );

        // Hacer petición GET
        $response = wp_remote_get($url, array(
            'headers' => self::get_auth_headers(),
            'timeout' => 30
        ));

        return self::process_response($response);
    }

    /**
     * Construir payload multipart
     *
     * @param array $parts Array de partes del multipart
     * @param string $boundary Boundary string
     * @return string Payload construido
     */
    private static function build_multipart_payload($parts, $boundary) {
        $payload = '';

        foreach ($parts as $part) {
            $payload .= "--{$boundary}\r\n";
            $payload .= 'Content-Disposition: form-data; name="' . $part['name'] . '"';

            if (isset($part['filename'])) {
                $payload .= '; filename="' . $part['filename'] . '"';
            }

            $payload .= "\r\n";

            if (isset($part['type'])) {
                $payload .= 'Content-Type: ' . $part['type'] . "\r\n";
            }

            $payload .= "\r\n";

            if (isset($part['filepath'])) {
                $payload .= file_get_contents($part['filepath']);
            } else {
                $payload .= $part['content'];
            }

            $payload .= "\r\n";
        }

        $payload .= "--{$boundary}--\r\n";

        return $payload;
    }

    /**
     * Procesar respuesta de la API
     *
     * @param array|WP_Error $response Respuesta HTTP
     * @return array|WP_Error Datos procesados o error
     */
    private static function process_response($response) {
        if (is_wp_error($response)) {
            Simple_DTE_Logger::error('Error HTTP', array('error' => $response->get_error_message()));
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        Simple_DTE_Logger::debug('Respuesta API', array(
            'status' => $status_code,
            'body_length' => strlen($body)
        ));

        // Verificar código de estado
        if ($status_code < 200 || $status_code >= 300) {
            $error_message = sprintf('Error HTTP %d: %s', $status_code, substr($body, 0, 200));
            Simple_DTE_Logger::error($error_message);
            return new WP_Error('api_error', $error_message);
        }

        // Intentar decodificar JSON
        $data = json_decode($body, true);

        // Si no es JSON, podría ser XML
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Retornar como XML crudo
            return array(
                'success' => true,
                'xml' => $body,
                'raw' => $body
            );
        }

        return $data;
    }

    /**
     * Test de conexión con la API
     *
     * @return bool True si la conexión es exitosa
     */
    public static function test_connection() {
        $url = Simple_DTE_Helpers::get_api_base_url();

        $response = wp_remote_get($url, array(
            'headers' => self::get_auth_headers(),
            'timeout' => 10
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);

        return $status_code === 200 || $status_code === 401; // 401 significa que la API responde
    }
}
