<?php
/**
 * Sistema de Health Check para SimpleAPI
 *
 * Verifica la salud de la API antes de operaciones críticas
 *
 * @package Simple_DTE
 */

if (!defined('ABSPATH')) {
    exit;
}

class Simple_DTE_Health_Check {

    /**
     * Cache TTL para health check (5 minutos)
     */
    const CACHE_TTL = 300;

    /**
     * Verificar salud completa de la API
     *
     * @param bool $use_cache Si usar cache o forzar verificación
     * @return array Estado de salud
     */
    public static function check_api_health($use_cache = true) {
        // Intentar obtener desde cache
        if ($use_cache) {
            $cached = get_transient('simple_dte_api_health');
            if ($cached !== false) {
                return $cached;
            }
        }

        $health = array(
            'overall' => 'unknown',
            'api_reachable' => false,
            'api_authenticated' => false,
            'response_time_ms' => 0,
            'ssl_valid' => false,
            'checks' => array(),
            'timestamp' => current_time('mysql'),
            'errors' => array()
        );

        // Check 1: Conectividad básica
        $connectivity = self::check_connectivity();
        $health['checks']['connectivity'] = $connectivity;
        $health['api_reachable'] = $connectivity['success'];
        $health['response_time_ms'] = $connectivity['response_time_ms'];
        $health['ssl_valid'] = $connectivity['ssl_valid'];

        if (!$connectivity['success']) {
            $health['errors'][] = $connectivity['error'];
        }

        // Check 2: Autenticación con API Key
        $auth = self::check_authentication();
        $health['checks']['authentication'] = $auth;
        $health['api_authenticated'] = $auth['success'];

        if (!$auth['success']) {
            $health['errors'][] = $auth['error'];
        }

        // Check 3: Verificar certificado digital local
        $cert = self::check_local_certificate();
        $health['checks']['certificate'] = $cert;

        if (!$cert['success']) {
            $health['errors'][] = $cert['error'];
        }

        // Check 4: Verificar CAF disponible
        $caf = self::check_caf_availability();
        $health['checks']['caf'] = $caf;

        if (!$caf['success']) {
            $health['errors'][] = $caf['error'];
        }

        // Determinar estado general
        if ($health['api_reachable'] && $health['api_authenticated'] && $cert['success'] && $caf['success']) {
            $health['overall'] = 'healthy';
        } elseif ($health['api_reachable']) {
            $health['overall'] = 'degraded';
        } else {
            $health['overall'] = 'unhealthy';
        }

        // Guardar en cache
        set_transient('simple_dte_api_health', $health, self::CACHE_TTL);

        // Log
        Simple_DTE_Logger::debug('Health check completado', array(
            'overall' => $health['overall'],
            'api_reachable' => $health['api_reachable'],
            'api_authenticated' => $health['api_authenticated'],
            'operacion' => 'health_check'
        ));

        return $health;
    }

    /**
     * Verificar conectividad con la API
     */
    private static function check_connectivity() {
        $result = array(
            'success' => false,
            'response_time_ms' => 0,
            'ssl_valid' => false,
            'http_code' => 0,
            'error' => ''
        );

        $url = Simple_DTE_Helpers::get_api_base_url();

        $start_time = microtime(true);

        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'sslverify' => true,
            'headers' => array(
                'User-Agent' => 'Simple-DTE-WordPress/' . SIMPLE_DTE_VERSION
            )
        ));

        $end_time = microtime(true);
        $result['response_time_ms'] = round(($end_time - $start_time) * 1000);

        if (is_wp_error($response)) {
            $result['error'] = 'Error de conectividad: ' . $response->get_error_message();
            return $result;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $result['http_code'] = $http_code;

        // Verificar SSL (si la respuesta no es WP_Error, SSL es válido)
        $result['ssl_valid'] = true;

        // Cualquier respuesta HTTP indica que el servidor es accesible
        if ($http_code > 0) {
            $result['success'] = true;
        } else {
            $result['error'] = 'API no responde (HTTP code: 0)';
        }

        return $result;
    }

    /**
     * Verificar autenticación con API Key
     */
    private static function check_authentication() {
        $result = array(
            'success' => false,
            'error' => ''
        );

        $api_key = get_option('simple_dte_api_key', '');

        if (empty($api_key)) {
            $result['error'] = 'API Key no configurada';
            return $result;
        }

        // Intentar hacer una petición autenticada simple
        $connected = Simple_DTE_API_Client::test_connection();

        if ($connected) {
            $result['success'] = true;
        } else {
            $result['error'] = 'API Key inválida o sin permisos';
        }

        return $result;
    }

    /**
     * Verificar certificado digital local
     */
    private static function check_local_certificate() {
        $result = array(
            'success' => false,
            'path' => '',
            'exists' => false,
            'readable' => false,
            'valid_until' => null,
            'days_remaining' => null,
            'error' => ''
        );

        $cert_path = get_option('simple_dte_cert_path', '');
        $result['path'] = $cert_path;

        if (empty($cert_path)) {
            $result['error'] = 'Ruta del certificado no configurada';
            return $result;
        }

        if (!file_exists($cert_path)) {
            $result['error'] = 'Certificado no encontrado en: ' . $cert_path;
            return $result;
        }

        $result['exists'] = true;

        if (!is_readable($cert_path)) {
            $result['error'] = 'Certificado no es legible (permisos)';
            return $result;
        }

        $result['readable'] = true;

        // Intentar leer el certificado para verificar validez
        try {
            $cert_password = get_option('simple_dte_cert_password', '');
            $cert_content = file_get_contents($cert_path);

            if ($cert_content === false) {
                $result['error'] = 'No se puede leer el contenido del certificado';
                return $result;
            }

            // Verificar que el password sea correcto intentando abrirlo
            $certs = array();
            if (@openssl_pkcs12_read($cert_content, $certs, $cert_password)) {
                // Certificado válido y password correcto
                $result['success'] = true;

                // Intentar obtener fecha de expiración
                if (isset($certs['cert'])) {
                    $cert_data = openssl_x509_parse($certs['cert']);
                    if (isset($cert_data['validTo_time_t'])) {
                        $valid_until = $cert_data['validTo_time_t'];
                        $result['valid_until'] = date('Y-m-d H:i:s', $valid_until);
                        $result['days_remaining'] = round(($valid_until - time()) / 86400);

                        // Alerta si está por vencer (menos de 30 días)
                        if ($result['days_remaining'] < 30 && $result['days_remaining'] > 0) {
                            $result['warning'] = "Certificado vence en {$result['days_remaining']} días";
                        } elseif ($result['days_remaining'] <= 0) {
                            $result['success'] = false;
                            $result['error'] = 'Certificado expirado';
                        }
                    }
                }
            } else {
                $result['error'] = 'Certificado inválido o contraseña incorrecta';
            }
        } catch (Exception $e) {
            $result['error'] = 'Error al validar certificado: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Verificar disponibilidad de CAF
     */
    private static function check_caf_availability() {
        global $wpdb;

        $result = array(
            'success' => false,
            'active_caf' => null,
            'folios_remaining' => 0,
            'percentage_remaining' => 0,
            'pending_cafs' => 0,
            'error' => ''
        );

        $table = $wpdb->prefix . 'simple_dte_folios';

        // Verificar CAF activo
        $caf = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE tipo_dte = %d AND estado = 'activo' ORDER BY id DESC LIMIT 1",
            39 // Boleta electrónica
        ));

        if (!$caf) {
            $result['error'] = 'No hay CAF activo para boletas';
            return $result;
        }

        $result['active_caf'] = array(
            'id' => $caf->id,
            'folio_desde' => $caf->folio_desde,
            'folio_hasta' => $caf->folio_hasta,
            'folio_actual' => $caf->folio_actual
        );

        // Calcular folios restantes
        $total_folios = $caf->folio_hasta - $caf->folio_desde + 1;
        $folios_usados = $caf->folio_actual - $caf->folio_desde;
        $folios_remaining = $caf->folio_hasta - $caf->folio_actual;

        $result['folios_remaining'] = $folios_remaining;
        $result['percentage_remaining'] = round(($folios_remaining / $total_folios) * 100, 2);

        // Contar CAFs pendientes
        $pending = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE tipo_dte = %d AND estado = 'pendiente'",
            39
        ));

        $result['pending_cafs'] = (int) $pending;

        // Verificar estado
        if ($folios_remaining <= 0) {
            $result['error'] = 'CAF agotado, sin folios disponibles';
        } elseif ($result['percentage_remaining'] < 10) {
            $result['warning'] = "Quedan solo {$folios_remaining} folios ({$result['percentage_remaining']}%)";
            $result['success'] = true;
        } else {
            $result['success'] = true;
        }

        return $result;
    }

    /**
     * Verificar salud antes de operación crítica
     *
     * @param bool $throw_exception Si lanzar excepción en caso de error
     * @return bool|WP_Error
     */
    public static function verify_before_operation($throw_exception = false) {
        $health = self::check_api_health();

        if ($health['overall'] === 'healthy') {
            return true;
        }

        $error_message = 'Sistema no está listo: ';

        if (!empty($health['errors'])) {
            $error_message .= implode(', ', $health['errors']);
        } else {
            $error_message .= 'Estado general: ' . $health['overall'];
        }

        Simple_DTE_Logger::warning('Verificación pre-operación falló', array(
            'health' => $health['overall'],
            'errors' => $health['errors'],
            'operacion' => 'pre_operation_check'
        ));

        if ($throw_exception) {
            throw new Exception($error_message);
        }

        return new WP_Error('health_check_failed', $error_message, $health);
    }

    /**
     * Limpiar cache de health check
     */
    public static function clear_cache() {
        delete_transient('simple_dte_api_health');
    }

    /**
     * Obtener resumen de salud para dashboard
     */
    public static function get_dashboard_summary() {
        $health = self::check_api_health();

        $summary = array(
            'status' => $health['overall'],
            'status_label' => self::get_status_label($health['overall']),
            'status_color' => self::get_status_color($health['overall']),
            'checks_passed' => 0,
            'checks_total' => count($health['checks']),
            'warnings' => array(),
            'errors' => $health['errors'],
            'last_check' => $health['timestamp']
        );

        // Contar checks exitosos
        foreach ($health['checks'] as $check) {
            if ($check['success']) {
                $summary['checks_passed']++;
            }
            if (isset($check['warning'])) {
                $summary['warnings'][] = $check['warning'];
            }
        }

        return $summary;
    }

    /**
     * Obtener etiqueta de estado
     */
    private static function get_status_label($status) {
        $labels = array(
            'healthy' => 'Operativo',
            'degraded' => 'Degradado',
            'unhealthy' => 'No Operativo',
            'unknown' => 'Desconocido'
        );

        return isset($labels[$status]) ? $labels[$status] : $labels['unknown'];
    }

    /**
     * Obtener color de estado
     */
    private static function get_status_color($status) {
        $colors = array(
            'healthy' => 'green',
            'degraded' => 'orange',
            'unhealthy' => 'red',
            'unknown' => 'gray'
        );

        return isset($colors[$status]) ? $colors[$status] : $colors['unknown'];
    }
}
