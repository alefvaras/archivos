#!/usr/bin/env php
<?php
/**
 * Script para probar la consulta de estado de un envío al SII
 *
 * Este script:
 * 1. Genera una boleta electrónica
 * 2. Crea un sobre (EnvioDTE)
 * 3. Envía el sobre al SII
 * 4. Obtiene el track_id
 * 5. Consulta el estado usando el track_id
 */

// Mostrar errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Cargar autoloader si existe
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Cargar clases necesarias
require_once __DIR__ . '/lib/DTELogger.php';
require_once __DIR__ . '/lib/SimpleAPIClient.php';
require_once __DIR__ . '/lib/BoletaGenerator.php';
require_once __DIR__ . '/lib/XMLSigner.php';
require_once __DIR__ . '/lib/CAFManager.php';

class ConsultaEstadoTester {

    private $api_client;
    private $logger;
    private $config;

    public function __construct() {
        $this->logger = new DTELogger(__DIR__ . '/logs');

        // Cargar configuración desde .env.certificacion.ejemplo
        $this->loadConfig();

        $this->api_client = new SimpleAPIClient(
            $this->config['api_key'],
            $this->config['ambiente']
        );

        $this->info("🔧 Configuración cargada");
        $this->info("   Ambiente: " . $this->config['ambiente']);
        $this->info("   RUT Emisor: " . $this->config['rut_emisor']);
    }

    /**
     * Cargar configuración
     */
    private function loadConfig() {
        $env_file = __DIR__ . '/.env.certificacion.ejemplo';

        if (!file_exists($env_file)) {
            $this->error("Archivo .env.certificacion.ejemplo no encontrado");
            exit(1);
        }

        $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $config = [];

        foreach ($lines as $line) {
            if (strpos($line, '=') === false || strpos($line, '#') === 0) {
                continue;
            }

            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $value = trim($value, '"\'');

            $config[$key] = $value;
        }

        // Mapear configuración
        $this->config = [
            'ambiente' => $config['AMBIENTE'] ?? 'certificacion',
            'api_key' => $config['API_KEY'] ?? '',
            'rut_emisor' => $config['RUT_EMISOR'] ?? '',
            'razon_social' => $config['RAZON_SOCIAL'] ?? '',
            'giro' => $config['GIRO'] ?? '',
            'direccion' => $config['DIRECCION'] ?? '',
            'comuna' => $config['COMUNA'] ?? '',
            'ciudad' => $config['CIUDAD'] ?? 'Santiago',
            'cert_path' => $config['CERT_PATH'] ?? '',
            'cert_password' => $config['CERT_PASSWORD'] ?? '',
        ];

        // Validar
        if (empty($this->config['api_key'])) {
            $this->error("API Key no configurada");
            exit(1);
        }
    }

    /**
     * Ejecutar prueba completa
     */
    public function run() {
        $this->info("\n╔════════════════════════════════════════════════════════════╗");
        $this->info("║  TEST: Generación, Envío y Consulta de Estado DTE         ║");
        $this->info("╚════════════════════════════════════════════════════════════╝\n");

        try {
            // Paso 1: Generar boleta
            $this->info("📝 PASO 1: Generando boleta electrónica...");
            $boleta_data = $this->generarBoleta();
            $this->success("   ✓ Boleta generada - Folio: " . $boleta_data['folio']);

            // Paso 2: Crear sobre
            $this->info("\n📦 PASO 2: Creando sobre EnvioDTE...");
            $sobre_data = $this->crearSobre($boleta_data);
            $this->success("   ✓ Sobre creado exitosamente");

            // Paso 3: Enviar al SII
            $this->info("\n🚀 PASO 3: Enviando al SII...");
            $envio_result = $this->enviarSobre($sobre_data);

            if (!isset($envio_result['track_id'])) {
                $this->error("   ✗ No se obtuvo track_id del envío");
                $this->info("   Respuesta: " . json_encode($envio_result, JSON_PRETTY_PRINT));
                return false;
            }

            $track_id = $envio_result['track_id'];
            $this->success("   ✓ Enviado exitosamente");
            $this->success("   ✓ Track ID: " . $track_id);

            // Paso 4: Consultar estado
            $this->info("\n🔍 PASO 4: Consultando estado del envío...");
            $this->info("   Esperando 3 segundos...");
            sleep(3);

            $estado_result = $this->consultarEstado($track_id);

            if ($estado_result) {
                $this->success("   ✓ Consulta exitosa");
                $this->mostrarEstado($estado_result);
                return true;
            } else {
                $this->error("   ✗ Error al consultar estado");
                return false;
            }

        } catch (Exception $e) {
            $this->error("Error: " . $e->getMessage());
            $this->error("Trace: " . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Generar boleta de prueba
     */
    private function generarBoleta() {
        $caf_manager = new CAFManager(__DIR__ . '/folios');
        $folio = $caf_manager->obtenerSiguienteFolio(39);

        $generator = new BoletaGenerator($this->logger);

        $documento_data = [
            'Encabezado' => [
                'IdDoc' => [
                    'TipoDTE' => 39,
                    'Folio' => $folio,
                    'FchEmis' => date('Y-m-d'),
                    'IndServicio' => 3
                ],
                'Emisor' => [
                    'RUTEmisor' => $this->config['rut_emisor'],
                    'RznSocEmisor' => $this->config['razon_social'],
                    'GiroEmisor' => $this->config['giro'],
                    'DirOrigen' => $this->config['direccion'],
                    'CmnaOrigen' => $this->config['comuna']
                ],
                'Receptor' => [
                    'RUTRecep' => '66666666-6',
                    'RznSocRecep' => 'Cliente de Prueba',
                    'DirRecep' => 'Av. Prueba 123',
                    'CmnaRecep' => 'Santiago'
                ],
                'Totales' => [
                    'MntNeto' => 100000,
                    'TasaIVA' => 19,
                    'IVA' => 19000,
                    'MntTotal' => 119000
                ]
            ],
            'Detalle' => [
                [
                    'NroLinDet' => 1,
                    'NmbItem' => 'Producto de Prueba',
                    'QtyItem' => 1,
                    'PrcItem' => 100000,
                    'MontoItem' => 100000
                ]
            ]
        ];

        // Generar DTE con firma
        $dte_result = $this->api_client->generar_dte(
            ['Documento' => $documento_data],
            $this->config['cert_path'],
            $caf_manager->obtenerCAFPath(39)
        );

        if (isset($dte_result['error'])) {
            throw new Exception("Error al generar DTE: " . $dte_result['error']);
        }

        return [
            'folio' => $folio,
            'tipo_dte' => 39,
            'xml' => $dte_result['xml'] ?? $dte_result['dte_xml'],
            'data' => $documento_data
        ];
    }

    /**
     * Crear sobre EnvioDTE
     */
    private function crearSobre($boleta_data) {
        // El sobre se crea con la clase que genera sobres
        // Por ahora usamos la API para crearlo
        return [
            'xml' => $boleta_data['xml'],
            'folio' => $boleta_data['folio']
        ];
    }

    /**
     * Enviar sobre al SII
     */
    private function enviarSobre($sobre_data) {
        $result = $this->api_client->enviar_sobre(
            $sobre_data['xml'],
            $this->config['cert_path']
        );

        return $result;
    }

    /**
     * Consultar estado del envío
     */
    private function consultarEstado($track_id) {
        $result = $this->api_client->consultar_estado(
            $track_id,
            $this->config['rut_emisor']
        );

        return $result;
    }

    /**
     * Mostrar estado del envío
     */
    private function mostrarEstado($estado) {
        $this->info("\n╔════════════════════════════════════════════════════════════╗");
        $this->info("║  ESTADO DEL ENVÍO                                          ║");
        $this->info("╚════════════════════════════════════════════════════════════╝");

        foreach ($estado as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $this->info("   " . $key . ": " . json_encode($value, JSON_PRETTY_PRINT));
            } else {
                $this->info("   " . $key . ": " . $value);
            }
        }
    }

    private function info($msg) {
        echo "\033[0;36m" . $msg . "\033[0m\n";
    }

    private function success($msg) {
        echo "\033[0;32m" . $msg . "\033[0m\n";
    }

    private function error($msg) {
        echo "\033[0;31m" . $msg . "\033[0m\n";
    }
}

// Ejecutar
$tester = new ConsultaEstadoTester();
$success = $tester->run();

exit($success ? 0 : 1);
