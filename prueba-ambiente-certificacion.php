<?php
/**
 * PRUEBA DE VERDAD CON DATOS REALES - AMBIENTE DE CERTIFICACIÓN
 *
 * Este script ejecuta pruebas completas con datos reales en el ambiente
 * de certificación del SII (Servicio de Impuestos Internos).
 *
 * IMPORTANTE: Solo ejecutar en ambiente de CERTIFICACIÓN
 *
 * Uso:
 *   php prueba-ambiente-certificacion.php
 *   php prueba-ambiente-certificacion.php --verbose
 *   php prueba-ambiente-certificacion.php --skip-envio  (genera pero no envía)
 *
 * @package SimpleDTE
 * @version 1.0.0
 */

// Cargar WordPress si existe, sino cargar standalone
if (file_exists(__DIR__ . '/../../../wp-load.php')) {
    require_once __DIR__ . '/../../../wp-load.php';
} else {
    require_once __DIR__ . '/config/settings.php';
    require_once __DIR__ . '/includes/class-simple-dte-helpers.php';
    require_once __DIR__ . '/includes/class-simple-dte-api-client.php';
    require_once __DIR__ . '/includes/class-simple-dte-health-check.php';
}

class PruebaAmbienteCertificacion {

    private $verbose = false;
    private $skip_envio = false;
    private $resultados = [];
    private $config;
    private $api_client;
    private $health_check;

    public function __construct($verbose = false, $skip_envio = false) {
        $this->verbose = $verbose;
        $this->skip_envio = $skip_envio;
        $this->config = ConfiguracionSistema::getInstance();
        $this->api_client = new Simple_DTE_API_Client();
        $this->health_check = new Simple_DTE_Health_Check();
    }

    /**
     * Ejecutar todas las pruebas
     */
    public function ejecutar() {
        $this->mostrarEncabezado();

        // Paso 1: Verificar que estamos en certificación
        if (!$this->verificarAmbienteCertificacion()) {
            $this->error("ABORTADO: El sistema NO está en ambiente de certificación.");
            $this->error("Por seguridad, solo se pueden ejecutar pruebas reales en certificación.");
            return false;
        }

        // Paso 2: Health Check completo
        if (!$this->ejecutarHealthCheck()) {
            $this->error("ABORTADO: El health check falló. Revisar configuración.");
            return false;
        }

        // Paso 3: Verificar credenciales y certificados
        if (!$this->verificarCredenciales()) {
            $this->error("ABORTADO: Credenciales o certificados no válidos.");
            return false;
        }

        // Paso 4: Verificar folios disponibles
        if (!$this->verificarFolios()) {
            $this->warning("ADVERTENCIA: Problemas con folios. Continuando...");
        }

        // Paso 5: Generar Boleta Electrónica con datos reales
        $this->info("\n=== PRUEBA 1: BOLETA ELECTRÓNICA (Tipo 39) ===");
        $boleta_result = $this->generarBoletaElectronica();
        $this->resultados['boleta'] = $boleta_result;

        // Paso 6: Generar Factura Electrónica con datos reales
        $this->info("\n=== PRUEBA 2: FACTURA ELECTRÓNICA (Tipo 33) ===");
        $factura_result = $this->generarFacturaElectronica();
        $this->resultados['factura'] = $factura_result;

        // Paso 7: Generar Boleta Exenta con datos reales
        $this->info("\n=== PRUEBA 3: BOLETA EXENTA (Tipo 41) ===");
        $boleta_exenta_result = $this->generarBoletaExenta();
        $this->resultados['boleta_exenta'] = $boleta_exenta_result;

        // Paso 8: Consultar estados (si se enviaron)
        if (!$this->skip_envio) {
            $this->info("\n=== CONSULTANDO ESTADOS EN EL SII ===");
            sleep(3); // Esperar a que el SII procese
            $this->consultarEstados();
        }

        // Paso 9: Generar reporte final
        $this->generarReporteFinal();

        return true;
    }

    /**
     * Verificar que estamos en ambiente de certificación
     */
    private function verificarAmbienteCertificacion() {
        $this->info("Verificando ambiente...");

        $ambiente = $this->config->get('general.ambiente');

        if ($ambiente !== 'certificacion') {
            $this->error("Ambiente actual: $ambiente");
            $this->error("Se requiere ambiente: certificacion");
            return false;
        }

        $this->success("✓ Ambiente: CERTIFICACIÓN (seguro para pruebas reales)");
        return true;
    }

    /**
     * Ejecutar health check completo
     */
    private function ejecutarHealthCheck() {
        $this->info("Ejecutando health check del sistema...");

        $health = $this->health_check->check_all();

        if ($this->verbose) {
            $this->debug("Health Check completo:");
            $this->debug(print_r($health, true));
        }

        if ($health['status'] === 'unhealthy') {
            $this->error("✗ Sistema NO saludable");
            foreach ($health['checks'] as $check => $result) {
                if (!$result['status']) {
                    $this->error("  - $check: " . $result['message']);
                }
            }
            return false;
        }

        $this->success("✓ Health check: OK");

        // Mostrar detalles si es verbose
        if ($this->verbose) {
            foreach ($health['checks'] as $check => $result) {
                $status = $result['status'] ? '✓' : '✗';
                $this->info("  $status $check");
            }
        }

        return true;
    }

    /**
     * Verificar credenciales
     */
    private function verificarCredenciales() {
        $this->info("Verificando credenciales...");

        // Verificar API Key
        $api_key = $this->config->get('api.api_key');
        if (empty($api_key)) {
            $this->error("✗ API Key no configurado");
            return false;
        }
        $this->success("✓ API Key: Configurado");

        // Verificar certificado digital
        $cert_path = $this->config->get('certificado.path');
        if (!file_exists($cert_path)) {
            $this->error("✗ Certificado no encontrado: $cert_path");
            return false;
        }

        $cert_password = $this->config->get('certificado.password');
        if (!openssl_pkcs12_read(file_get_contents($cert_path), $certs, $cert_password)) {
            $this->error("✗ Certificado no se puede leer (contraseña incorrecta?)");
            return false;
        }

        $this->success("✓ Certificado: Válido y legible");

        // Verificar fecha de expiración
        $cert_data = openssl_x509_parse($certs['cert']);
        $expiration = $cert_data['validTo_time_t'];
        $dias_restantes = floor(($expiration - time()) / 86400);

        if ($dias_restantes < 0) {
            $this->error("✗ Certificado EXPIRADO");
            return false;
        } elseif ($dias_restantes < 30) {
            $this->warning("⚠ Certificado expira en $dias_restantes días");
        } else {
            $this->success("✓ Certificado válido por $dias_restantes días");
        }

        return true;
    }

    /**
     * Verificar folios disponibles
     */
    private function verificarFolios() {
        $this->info("Verificando folios/CAF...");

        $caf_path = $this->config->get('caf.path');

        if (!file_exists($caf_path)) {
            $this->error("✗ Archivo CAF no encontrado: $caf_path");
            return false;
        }

        // Cargar CAF
        $caf_xml = simplexml_load_file($caf_path);
        if (!$caf_xml) {
            $this->error("✗ CAF no es un XML válido");
            return false;
        }

        // Extraer información del CAF
        $tipo_dte = (string) $caf_xml->CAF->DA->TD;
        $desde = (int) $caf_xml->CAF->DA->RNG->D;
        $hasta = (int) $caf_xml->CAF->DA->RNG->H;
        $total = $hasta - $desde + 1;

        $this->success("✓ CAF Tipo $tipo_dte: Folios $desde a $hasta ($total folios)");

        if ($total < 10) {
            $this->warning("⚠ Quedan pocos folios ($total). Considere solicitar más.");
        }

        return true;
    }

    /**
     * Generar Boleta Electrónica con datos reales
     */
    private function generarBoletaElectronica() {
        $emisor = $this->config->get('emisor');

        $datos_dte = [
            'Encabezado' => [
                'IdDoc' => [
                    'TipoDTE' => 39, // Boleta Electrónica
                    'Folio' => 0, // Se asigna automáticamente
                    'FchEmis' => date('Y-m-d'),
                ],
                'Emisor' => [
                    'RUTEmisor' => $emisor['rut'],
                    'RznSoc' => $emisor['razon_social'],
                    'GiroEmis' => $emisor['giro'],
                    'Acteco' => '620200', // Servicios de TI
                    'DirOrigen' => $emisor['direccion'],
                    'CmnaOrigen' => $emisor['comuna'],
                ],
                'Receptor' => [
                    'RUTRecep' => '66666666-6', // Cliente genérico para boletas
                    'RznSocRecep' => 'CLIENTE DE PRUEBA',
                    'DirRecep' => 'Calle Falsa 123',
                    'CmnaRecep' => 'Santiago',
                ],
                'Totales' => [
                    'MntNeto' => 10000,
                    'TasaIVA' => 19,
                    'IVA' => 1900,
                    'MntTotal' => 11900,
                ],
            ],
            'Detalle' => [
                [
                    'NroLinDet' => 1,
                    'NmbItem' => 'Servicio de Consultoría TI - Prueba Certificación',
                    'QtyItem' => 1,
                    'PrcItem' => 10000,
                    'MontoItem' => 10000,
                ],
            ],
        ];

        return $this->procesarDTE('Boleta Electrónica', $datos_dte);
    }

    /**
     * Generar Factura Electrónica con datos reales
     */
    private function generarFacturaElectronica() {
        $emisor = $this->config->get('emisor');

        $datos_dte = [
            'Encabezado' => [
                'IdDoc' => [
                    'TipoDTE' => 33, // Factura Electrónica
                    'Folio' => 0,
                    'FchEmis' => date('Y-m-d'),
                    'FmaPago' => 2, // Crédito
                    'FchVenc' => date('Y-m-d', strtotime('+30 days')),
                ],
                'Emisor' => [
                    'RUTEmisor' => $emisor['rut'],
                    'RznSoc' => $emisor['razon_social'],
                    'GiroEmis' => $emisor['giro'],
                    'Acteco' => '620200',
                    'DirOrigen' => $emisor['direccion'],
                    'CmnaOrigen' => $emisor['comuna'],
                ],
                'Receptor' => [
                    'RUTRecep' => '77777777-7',
                    'RznSocRecep' => 'EMPRESA CLIENTE PRUEBA SPA',
                    'GiroRecep' => 'Comercio al por menor',
                    'DirRecep' => 'Av. Apoquindo 5678',
                    'CmnaRecep' => 'Las Condes',
                ],
                'Totales' => [
                    'MntNeto' => 50000,
                    'TasaIVA' => 19,
                    'IVA' => 9500,
                    'MntTotal' => 59500,
                ],
            ],
            'Detalle' => [
                [
                    'NroLinDet' => 1,
                    'NmbItem' => 'Desarrollo de Software Personalizado - Prueba',
                    'DscItem' => 'Módulo de facturación electrónica',
                    'QtyItem' => 10,
                    'UnmdItem' => 'Hora',
                    'PrcItem' => 5000,
                    'MontoItem' => 50000,
                ],
            ],
        ];

        return $this->procesarDTE('Factura Electrónica', $datos_dte);
    }

    /**
     * Generar Boleta Exenta con datos reales
     */
    private function generarBoletaExenta() {
        $emisor = $this->config->get('emisor');

        $datos_dte = [
            'Encabezado' => [
                'IdDoc' => [
                    'TipoDTE' => 41, // Boleta Exenta
                    'Folio' => 0,
                    'FchEmis' => date('Y-m-d'),
                    'IndServicio' => 3, // Servicios
                ],
                'Emisor' => [
                    'RUTEmisor' => $emisor['rut'],
                    'RznSoc' => $emisor['razon_social'],
                    'GiroEmis' => $emisor['giro'],
                    'Acteco' => '620200',
                    'DirOrigen' => $emisor['direccion'],
                    'CmnaOrigen' => $emisor['comuna'],
                ],
                'Receptor' => [
                    'RUTRecep' => '66666666-6',
                    'RznSocRecep' => 'CLIENTE DE PRUEBA EXENTO',
                    'DirRecep' => 'Calle Exenta 456',
                    'CmnaRecep' => 'Ñuñoa',
                ],
                'Totales' => [
                    'MntExe' => 5000,
                    'MntTotal' => 5000,
                ],
            ],
            'Detalle' => [
                [
                    'NroLinDet' => 1,
                    'NmbItem' => 'Servicio Educacional Exento - Prueba',
                    'QtyItem' => 1,
                    'PrcItem' => 5000,
                    'MontoItem' => 5000,
                ],
            ],
        ];

        return $this->procesarDTE('Boleta Exenta', $datos_dte);
    }

    /**
     * Procesar un DTE (generar y opcionalmente enviar)
     */
    private function procesarDTE($nombre, $datos) {
        $resultado = [
            'nombre' => $nombre,
            'generado' => false,
            'enviado' => false,
            'folio' => null,
            'track_id' => null,
            'xml_path' => null,
            'pdf_path' => null,
            'errores' => [],
        ];

        try {
            // Generar DTE
            $this->info("Generando $nombre...");

            $response = $this->api_client->generar_dte($datos);

            if (!$response || isset($response['error'])) {
                $error = $response['error'] ?? 'Error desconocido al generar';
                $this->error("✗ Error al generar: $error");
                $resultado['errores'][] = $error;
                return $resultado;
            }

            $resultado['generado'] = true;
            $resultado['folio'] = $response['folio'] ?? null;
            $resultado['xml_path'] = $response['xml_path'] ?? null;
            $resultado['pdf_path'] = $response['pdf_path'] ?? null;

            $this->success("✓ DTE generado - Folio: " . $resultado['folio']);

            if ($this->verbose) {
                $this->debug("  XML: " . $resultado['xml_path']);
                $this->debug("  PDF: " . $resultado['pdf_path']);
            }

            // Enviar al SII (si no se salta el envío)
            if (!$this->skip_envio) {
                $this->info("Enviando al SII (ambiente certificación)...");

                $envio_response = $this->api_client->enviar_dte(
                    $resultado['xml_path'],
                    $datos['Encabezado']['IdDoc']['TipoDTE'],
                    $resultado['folio']
                );

                if (!$envio_response || isset($envio_response['error'])) {
                    $error = $envio_response['error'] ?? 'Error desconocido al enviar';
                    $this->error("✗ Error al enviar: $error");
                    $resultado['errores'][] = $error;
                } else {
                    $resultado['enviado'] = true;
                    $resultado['track_id'] = $envio_response['track_id'] ?? null;
                    $this->success("✓ Enviado al SII - Track ID: " . $resultado['track_id']);
                }
            } else {
                $this->info("(Envío omitido por --skip-envio)");
            }

        } catch (Exception $e) {
            $this->error("✗ Excepción: " . $e->getMessage());
            $resultado['errores'][] = $e->getMessage();
        }

        return $resultado;
    }

    /**
     * Consultar estados de los DTEs enviados
     */
    private function consultarEstados() {
        foreach ($this->resultados as $tipo => $resultado) {
            if ($resultado['enviado'] && $resultado['track_id']) {
                $this->info("Consultando estado de {$resultado['nombre']}...");

                $estado = $this->api_client->consultar_estado($resultado['track_id']);

                if ($estado && !isset($estado['error'])) {
                    $estado_sii = $estado['estado'] ?? 'Desconocido';
                    $glosa = $estado['glosa'] ?? '';

                    $this->success("  Estado SII: $estado_sii");
                    if ($glosa) {
                        $this->info("  Glosa: $glosa");
                    }

                    $this->resultados[$tipo]['estado_sii'] = $estado_sii;
                    $this->resultados[$tipo]['glosa_sii'] = $glosa;
                } else {
                    $this->warning("  No se pudo consultar estado");
                }
            }
        }
    }

    /**
     * Generar reporte final
     */
    private function generarReporteFinal() {
        $this->info("\n" . str_repeat("=", 70));
        $this->info("REPORTE FINAL DE PRUEBAS - AMBIENTE DE CERTIFICACIÓN");
        $this->info(str_repeat("=", 70));

        $total = count($this->resultados);
        $generados = 0;
        $enviados = 0;
        $errores = 0;

        foreach ($this->resultados as $tipo => $resultado) {
            $this->info("\n[$tipo] {$resultado['nombre']}:");
            $this->info("  Generado: " . ($resultado['generado'] ? '✓ SÍ' : '✗ NO'));

            if ($resultado['generado']) {
                $generados++;
                $this->info("  Folio: " . $resultado['folio']);

                if (!$this->skip_envio) {
                    $this->info("  Enviado: " . ($resultado['enviado'] ? '✓ SÍ' : '✗ NO'));

                    if ($resultado['enviado']) {
                        $enviados++;
                        $this->info("  Track ID: " . $resultado['track_id']);

                        if (isset($resultado['estado_sii'])) {
                            $this->info("  Estado SII: " . $resultado['estado_sii']);
                        }
                    }
                }

                if ($this->verbose) {
                    $this->info("  XML: " . $resultado['xml_path']);
                    $this->info("  PDF: " . $resultado['pdf_path']);
                }
            }

            if (!empty($resultado['errores'])) {
                $errores += count($resultado['errores']);
                $this->error("  Errores:");
                foreach ($resultado['errores'] as $error) {
                    $this->error("    - $error");
                }
            }
        }

        $this->info("\n" . str_repeat("-", 70));
        $this->info("RESUMEN:");
        $this->info("  Total de pruebas: $total");
        $this->info("  DTEs generados: $generados/$total");

        if (!$this->skip_envio) {
            $this->info("  DTEs enviados al SII: $enviados/$total");
        }

        $this->info("  Errores totales: $errores");
        $this->info(str_repeat("=", 70));

        if ($errores === 0) {
            $this->success("\n¡TODAS LAS PRUEBAS COMPLETADAS EXITOSAMENTE!");
        } elseif ($generados > 0) {
            $this->warning("\nPruebas completadas con algunos errores.");
        } else {
            $this->error("\nLas pruebas fallaron. Revisar configuración.");
        }

        // Guardar reporte en archivo
        $this->guardarReporteEnArchivo();
    }

    /**
     * Guardar reporte en archivo JSON
     */
    private function guardarReporteEnArchivo() {
        $reporte = [
            'fecha' => date('Y-m-d H:i:s'),
            'ambiente' => 'certificacion',
            'skip_envio' => $this->skip_envio,
            'resultados' => $this->resultados,
        ];

        $filename = __DIR__ . '/reportes/prueba-certificacion-' . date('Y-m-d-His') . '.json';

        // Crear directorio si no existe
        $dir = dirname($filename);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($filename, json_encode($reporte, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("\nReporte guardado en: $filename");
    }

    // =================================================================
    // MÉTODOS DE PRESENTACIÓN
    // =================================================================

    private function mostrarEncabezado() {
        echo "\n";
        echo str_repeat("=", 70) . "\n";
        echo "  PRUEBA DE VERDAD - AMBIENTE DE CERTIFICACIÓN\n";
        echo "  Datos Reales | Entorno Seguro | SII Certificación\n";
        echo str_repeat("=", 70) . "\n";
        echo "Fecha: " . date('Y-m-d H:i:s') . "\n";
        echo "Verbose: " . ($this->verbose ? 'Sí' : 'No') . "\n";
        echo "Skip Envío: " . ($this->skip_envio ? 'Sí' : 'No') . "\n";
        echo str_repeat("=", 70) . "\n\n";
    }

    private function info($msg) {
        echo "[INFO] $msg\n";
    }

    private function success($msg) {
        echo "\033[32m[OK]\033[0m $msg\n"; // Verde
    }

    private function error($msg) {
        echo "\033[31m[ERROR]\033[0m $msg\n"; // Rojo
    }

    private function warning($msg) {
        echo "\033[33m[WARN]\033[0m $msg\n"; // Amarillo
    }

    private function debug($msg) {
        if ($this->verbose) {
            echo "\033[36m[DEBUG]\033[0m $msg\n"; // Cyan
        }
    }
}

// =================================================================
// EJECUCIÓN
// =================================================================

// Parsear argumentos
$verbose = in_array('--verbose', $argv) || in_array('-v', $argv);
$skip_envio = in_array('--skip-envio', $argv) || in_array('--no-envio', $argv);

// Ejecutar prueba
$prueba = new PruebaAmbienteCertificacion($verbose, $skip_envio);
$exitoso = $prueba->ejecutar();

// Código de salida
exit($exitoso ? 0 : 1);
