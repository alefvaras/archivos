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
     * Máximo de reintentos para operaciones críticas
     */
    const MAX_RETRIES = 3;

    /**
     * Tiempo de espera inicial para backoff (segundos)
     */
    const BACKOFF_INITIAL = 2;

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
            'Authorization' => self::get_api_key(),
            'User-Agent' => 'Simple-DTE-WordPress/' . (defined('SIMPLE_DTE_VERSION') ? SIMPLE_DTE_VERSION : '1.0')
        );
    }

    /**
     * Generar DTE (Boleta, Factura, Nota, etc.) con reintentos automáticos
     *
     * @param array $documento_data Datos del documento
     * @param string $cert_path Ruta al certificado PFX
     * @param string $caf_path Ruta al archivo CAF
     * @param bool $with_health_check Si verificar salud antes de operar
     * @return array|WP_Error Respuesta de la API
     */
    public static function generar_dte($documento_data, $cert_path, $caf_path, $with_health_check = true) {
        Simple_DTE_Logger::info('API: Iniciando generación de DTE', array(
            'tipo' => $documento_data['Documento']['Encabezado']['IdentificacionDTE']['TipoDTE'],
            'folio' => $documento_data['Documento']['Encabezado']['IdentificacionDTE']['Folio']
        ));

        // Health check previo (solo si está disponible la clase)
        if ($with_health_check && class_exists('Simple_DTE_Health_Check')) {
            $health_check = Simple_DTE_Health_Check::verify_before_operation(false);
            if (is_wp_error($health_check)) {
                Simple_DTE_Logger::warning('Health check falló, continuando de todos modos', array(
                    'error' => $health_check->get_error_message()
                ));
                // No bloqueamos la operación, solo advertimos
            }
        }

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

        $request_options = array(
            'headers' => array_merge(
                self::get_auth_headers(),
                array('Content-Type' => 'multipart/form-data; boundary=' . $boundary)
            ),
            'body' => $payload,
            'timeout' => 60
        );

        // Ejecutar con reintentos automáticos
        return self::execute_with_retries('POST', $url, $request_options, array(
            'operation' => 'generar_dte',
            'tipo_dte' => $documento_data['Documento']['Encabezado']['IdentificacionDTE']['TipoDTE'],
            'folio' => $documento_data['Documento']['Encabezado']['IdentificacionDTE']['Folio']
        ));
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

    /**
     * Ejecutar petición HTTP con reintentos automáticos y exponential backoff
     *
     * @param string $method Método HTTP (GET, POST, etc.)
     * @param string $url URL del endpoint
     * @param array $options Opciones de wp_remote_*
     * @param array $context Contexto para logs
     * @return array|WP_Error Respuesta procesada
     */
    private static function execute_with_retries($method, $url, $options, $context = array()) {
        $attempt = 0;
        $last_error = null;

        while ($attempt < self::MAX_RETRIES) {
            $attempt++;

            $start_time = microtime(true);

            // Ejecutar petición según el método
            if ($method === 'POST') {
                $response = wp_remote_post($url, $options);
            } elseif ($method === 'GET') {
                $response = wp_remote_get($url, $options);
            } else {
                $response = wp_remote_request($url, array_merge($options, array('method' => $method)));
            }

            $end_time = microtime(true);
            $duration_ms = round(($end_time - $start_time) * 1000);

            $log_context = array_merge($context, array(
                'attempt' => $attempt,
                'max_retries' => self::MAX_RETRIES,
                'duration_ms' => $duration_ms
            ));

            // Si es un error de WordPress (no pudo conectar)
            if (is_wp_error($response)) {
                $last_error = $response;

                Simple_DTE_Logger::warning('Intento de API falló', array_merge($log_context, array(
                    'error' => $response->get_error_message(),
                    'error_code' => $response->get_error_code()
                )));

                // Si no es el último intento, esperar antes de reintentar
                if ($attempt < self::MAX_RETRIES) {
                    $backoff_time = self::BACKOFF_INITIAL * pow(2, $attempt - 1);
                    Simple_DTE_Logger::debug('Esperando antes de reintentar', array(
                        'backoff_seconds' => $backoff_time,
                        'next_attempt' => $attempt + 1
                    ));
                    sleep($backoff_time);
                    continue;
                }

                // Último intento falló
                Simple_DTE_Logger::error('Todos los intentos de API fallaron', array_merge($log_context, array(
                    'final_error' => $response->get_error_message()
                )));

                return $response;
            }

            // Obtener código de estado HTTP
            $status_code = wp_remote_retrieve_response_code($response);
            $log_context['http_code'] = $status_code;

            // Si es un error HTTP recuperable (500, 502, 503, 504), reintentar
            if (in_array($status_code, array(500, 502, 503, 504, 408, 429))) {
                $body = wp_remote_retrieve_body($response);
                $last_error = new WP_Error(
                    'api_http_error',
                    sprintf('Error HTTP %d: %s', $status_code, substr($body, 0, 200))
                );

                Simple_DTE_Logger::warning('Error HTTP recuperable', array_merge($log_context, array(
                    'body_preview' => substr($body, 0, 100)
                )));

                // Si no es el último intento, esperar antes de reintentar
                if ($attempt < self::MAX_RETRIES) {
                    $backoff_time = self::BACKOFF_INITIAL * pow(2, $attempt - 1);

                    // Para 429 (rate limit), respetar header Retry-After si existe
                    if ($status_code === 429) {
                        $retry_after = wp_remote_retrieve_header($response, 'retry-after');
                        if ($retry_after && is_numeric($retry_after)) {
                            $backoff_time = max($backoff_time, (int) $retry_after);
                        }
                    }

                    Simple_DTE_Logger::debug('Esperando antes de reintentar', array(
                        'backoff_seconds' => $backoff_time,
                        'next_attempt' => $attempt + 1,
                        'reason' => 'http_' . $status_code
                    ));

                    sleep($backoff_time);
                    continue;
                }

                // Último intento falló
                Simple_DTE_Logger::error('Todos los intentos HTTP fallaron', $log_context);
                return $last_error;
            }

            // Si llegamos aquí, la petición fue exitosa (o es un error no recuperable)
            Simple_DTE_Logger::info('Petición API exitosa', $log_context);

            return self::process_response($response);
        }

        // No debería llegar aquí, pero por si acaso
        return $last_error ?? new WP_Error('api_unknown_error', 'Error desconocido en reintentos');
    }

    /**
     * Obtener estadísticas de la API
     *
     * @return array Estadísticas de uso
     */
    public static function get_api_stats() {
        global $wpdb;

        $stats = array(
            'total_requests' => 0,
            'successful_requests' => 0,
            'failed_requests' => 0,
            'avg_response_time_ms' => 0,
            'last_request' => null
        );

        // Si existe tabla de logs, obtener estadísticas
        $table = $wpdb->prefix . 'simple_dte_logs';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;

        if ($table_exists) {
            // Total de requests en las últimas 24 horas
            $stats['total_requests'] = $wpdb->get_var(
                "SELECT COUNT(*) FROM $table
                 WHERE mensaje LIKE '%API:%'
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            );

            // Requests exitosos
            $stats['successful_requests'] = $wpdb->get_var(
                "SELECT COUNT(*) FROM $table
                 WHERE mensaje LIKE '%API exitosa%'
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            );

            $stats['failed_requests'] = $stats['total_requests'] - $stats['successful_requests'];

            // Última petición
            $stats['last_request'] = $wpdb->get_var(
                "SELECT created_at FROM $table
                 WHERE mensaje LIKE '%API:%'
                 ORDER BY created_at DESC
                 LIMIT 1"
            );
        }

        return $stats;
    }
}
