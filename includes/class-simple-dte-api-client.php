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
     * Solicitar folios/CAF a SimpleAPI
     *
     * @param int $tipo_dte Tipo de DTE (39, 33, 61, etc.)
     * @param int $cantidad Cantidad de folios a solicitar
     * @param string $cert_path Ruta al certificado PFX
     * @return array|WP_Error Respuesta de la API con el CAF
     */
    public static function solicitar_folios($tipo_dte, $cantidad, $cert_path) {
        Simple_DTE_Logger::info('API: Solicitando folios a SimpleAPI', array(
            'tipo_dte' => $tipo_dte,
            'cantidad' => $cantidad
        ));

        // Validar certificado
        if (!file_exists($cert_path)) {
            return new WP_Error('cert_not_found', __('Certificado digital no encontrado', 'simple-dte'));
        }

        // Validar cantidad
        if ($cantidad < 1 || $cantidad > 1000) {
            return new WP_Error('invalid_quantity', __('La cantidad debe estar entre 1 y 1000 folios', 'simple-dte'));
        }

        // Construir URL - endpoint de SimpleAPI Folios
        $url = Simple_DTE_Helpers::get_api_base_url() . '/api/v1/folios/solicitar';

        // Crear boundary para multipart
        $boundary = wp_generate_password(24, false, false);

        // Datos de la solicitud
        $solicitud_data = array(
            'TipoDTE' => $tipo_dte,
            'Cantidad' => $cantidad
        );

        // Construir payload multipart
        $payload = self::build_multipart_payload(array(
            array(
                'name' => 'input',
                'content' => wp_json_encode($solicitud_data, JSON_UNESCAPED_UNICODE),
                'type' => 'application/json'
            ),
            array(
                'name' => 'certificado',
                'filename' => basename($cert_path),
                'filepath' => $cert_path,
                'type' => 'application/x-pkcs12'
            )
        ), $boundary);

        $request_options = array(
            'headers' => array_merge(
                self::get_auth_headers(),
                array('Content-Type' => 'multipart/form-data; boundary=' . $boundary)
            ),
            'body' => $payload,
            'timeout' => 120 // Solicitar folios puede tomar más tiempo
        );

        // Ejecutar con reintentos automáticos
        $response = self::execute_with_retries('POST', $url, $request_options, array(
            'operation' => 'solicitar_folios',
            'tipo_dte' => $tipo_dte,
            'cantidad' => $cantidad
        ));

        if (is_wp_error($response)) {
            Simple_DTE_Logger::error('Error al solicitar folios', array(
                'error' => $response->get_error_message()
            ));
            return $response;
        }

        // Si la respuesta contiene XML del CAF, procesarlo y guardarlo
        if (isset($response['caf_xml']) || isset($response['xml'])) {
            $caf_xml = isset($response['caf_xml']) ? $response['caf_xml'] : $response['xml'];

            // Guardar el CAF
            $save_result = self::save_caf_from_api($caf_xml, $tipo_dte);

            if (is_wp_error($save_result)) {
                Simple_DTE_Logger::error('Error al guardar CAF recibido', array(
                    'error' => $save_result->get_error_message()
                ));
                return $save_result;
            }

            Simple_DTE_Logger::info('Folios solicitados y guardados exitosamente', array(
                'tipo_dte' => $tipo_dte,
                'cantidad' => $cantidad,
                'folio_desde' => $save_result['folio_desde'],
                'folio_hasta' => $save_result['folio_hasta']
            ));

            return array(
                'success' => true,
                'message' => sprintf(
                    __('Se solicitaron %d folios exitosamente (Rango: %d - %d)', 'simple-dte'),
                    $cantidad,
                    $save_result['folio_desde'],
                    $save_result['folio_hasta']
                ),
                'caf_id' => $save_result['caf_id'],
                'folio_desde' => $save_result['folio_desde'],
                'folio_hasta' => $save_result['folio_hasta'],
                'cantidad' => $cantidad
            );
        }

        // Si no hay XML en la respuesta, retornar error
        return new WP_Error(
            'invalid_response',
            __('La respuesta de la API no contiene un CAF válido', 'simple-dte'),
            $response
        );
    }

    /**
     * Guardar CAF recibido desde la API
     *
     * @param string $caf_xml XML del CAF
     * @param int $tipo_dte Tipo de DTE
     * @return array|WP_Error Información del CAF guardado
     */
    private static function save_caf_from_api($caf_xml, $tipo_dte) {
        // Crear directorio seguro
        $upload_dir = Simple_DTE_Helpers::create_secure_upload_dir();

        $filename = 'caf-' . $tipo_dte . '-' . time() . '.xml';
        $filepath = $upload_dir . $filename;

        // Guardar archivo XML
        if (file_put_contents($filepath, $caf_xml) === false) {
            return new WP_Error('save_failed', __('No se pudo guardar el archivo CAF', 'simple-dte'));
        }

        @chmod($filepath, 0600);

        // Parsear XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($caf_xml);

        if ($xml === false) {
            @unlink($filepath);
            $errors = libxml_get_errors();
            libxml_clear_errors();
            return new WP_Error('invalid_xml', __('El CAF recibido no es un XML válido', 'simple-dte'));
        }

        // Extraer datos del CAF
        $da = $xml->CAF->DA;

        if (!$da) {
            @unlink($filepath);
            return new WP_Error('invalid_caf', __('Estructura de CAF inválida', 'simple-dte'));
        }

        $tipo = (int) ((string) $da->TD);
        $folio_desde = (int) ((string) $da->RNG->D);
        $folio_hasta = (int) ((string) $da->RNG->H);

        // Validar que el tipo coincida
        if ($tipo !== $tipo_dte) {
            @unlink($filepath);
            return new WP_Error(
                'tipo_mismatch',
                sprintf(
                    __('El CAF es de tipo %d pero se esperaba tipo %d', 'simple-dte'),
                    $tipo,
                    $tipo_dte
                )
            );
        }

        // Guardar en base de datos
        global $wpdb;
        $table = $wpdb->prefix . 'simple_dte_folios';

        $wpdb->insert($table, array(
            'tipo_dte' => $tipo,
            'folio_desde' => $folio_desde,
            'folio_hasta' => $folio_hasta,
            'folio_actual' => $folio_desde,
            'xml_path' => $filepath,
            'estado' => 'activo',
            'created_at' => current_time('mysql')
        ));

        $caf_id = $wpdb->insert_id;

        if (!$caf_id) {
            @unlink($filepath);
            return new WP_Error('db_error', __('Error al guardar el CAF en la base de datos', 'simple-dte'));
        }

        return array(
            'caf_id' => $caf_id,
            'folio_desde' => $folio_desde,
            'folio_hasta' => $folio_hasta,
            'filepath' => $filepath
        );
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
