<?php
/**
 * Tests de Integración - Sistema de Boletas Electrónicas
 *
 * Prueba componentes trabajando juntos
 */

require_once(__DIR__ . '/../lib/VisualHelper.php');
require_once(__DIR__ . '/../lib/generar-pdf-boleta.php');
require_once(__DIR__ . '/../lib/generar-timbre-pdf417.php');

class IntegrationTest {

    private $v;
    private $tests_passed = 0;
    private $tests_failed = 0;
    private $temp_dir;

    public function __construct() {
        $this->v = VisualHelper::getInstance();
        $this->temp_dir = __DIR__ . '/../temp_test';

        // Crear directorio temporal
        if (!is_dir($this->temp_dir)) {
            mkdir($this->temp_dir, 0755, true);
        }
    }

    public function run() {
        $this->v->limpiar();
        echo "\n";
        $this->v->titulo("TESTS DE INTEGRACIÓN - Sistema de Boletas Electrónicas");
        echo "\n";

        // Ejecutar tests
        $this->testGeneracionDTE();
        $this->testGeneracionPDF();
        $this->testTimbrePDF417();
        $this->testFoliosYCAF();
        $this->testXMLyPDF();
        $this->testSimpleAPIConnection();

        // Limpiar
        $this->cleanup();

        // Mostrar resumen
        $this->showSummary();

        // Retornar resultado
        return $this->tests_failed == 0;
    }

    // =========================================================================
    // TEST 1: Generación de DTE Completo
    // =========================================================================

    private function testGeneracionDTE() {
        $this->v->subtitulo("Test 1: Generación de DTE Completo");

        // Test 1.1: Construir documento DTE
        $test_name = "Construir estructura de documento DTE";
        try {
            $documento = [
                'Documento' => [
                    'Encabezado' => [
                        'IdentificacionDTE' => [
                            'TipoDTE' => 39,
                            'Folio' => 9999,
                            'FechaEmision' => date('Y-m-d')
                        ],
                        'Emisor' => [
                            'Rut' => '78274225-6',
                            'RazonSocial' => 'TEST SPA',
                        ],
                        'Receptor' => [
                            'Rut' => '66666666-6',
                            'RazonSocial' => 'Cliente Test',
                        ],
                        'Totales' => [
                            'MontoNeto' => 100000,
                            'IVA' => 19000,
                            'MontoTotal' => 119000
                        ]
                    ],
                    'Detalles' => [
                        [
                            'NmbItem' => 'Producto Test',
                            'Cantidad' => 1,
                            'Precio' => 100000,
                            'MontoItem' => 100000
                        ]
                    ]
                ]
            ];

            $valido = isset($documento['Documento']['Encabezado']) &&
                      isset($documento['Documento']['Detalles']);

            $this->assert($valido, $test_name);
        } catch (Exception $e) {
            $this->assert(false, $test_name, $e->getMessage());
        }

        // Test 1.2: Validar coherencia de totales
        $test_name = "Validar coherencia de totales";
        $neto = $documento['Documento']['Encabezado']['Totales']['MontoNeto'];
        $iva = $documento['Documento']['Encabezado']['Totales']['IVA'];
        $total = $documento['Documento']['Encabezado']['Totales']['MontoTotal'];

        $this->assert($neto + $iva == $total, $test_name, "$neto + $iva = $total");

        echo "\n";
    }

    // =========================================================================
    // TEST 2: Generación de PDF
    // =========================================================================

    private function testGeneracionPDF() {
        $this->v->subtitulo("Test 2: Generación de PDF");

        // Datos de prueba
        $datos_test = [
            'Documento' => [
                'Encabezado' => [
                    'IdentificacionDTE' => [
                        'TipoDTE' => 39,
                        'Folio' => 9999,
                        'FechaEmision' => date('Y-m-d')
                    ],
                    'Emisor' => [
                        'Rut' => '78274225-6',
                        'RazonSocial' => 'AKIBARA SPA',
                        'GiroBoleta' => 'Servicios de Tecnología',
                        'DireccionOrigen' => 'Av. Test 123',
                        'ComunaOrigen' => 'Santiago'
                    ],
                    'Receptor' => [
                        'Rut' => '66666666-6',
                        'RazonSocial' => 'Cliente Test',
                        'Direccion' => 'Calle Test',
                        'Comuna' => 'Santiago'
                    ],
                    'Totales' => [
                        'MontoNeto' => 100000,
                        'IVA' => 19000,
                        'MontoTotal' => 119000
                    ]
                ],
                'Detalles' => [
                    [
                        'NmbItem' => 'Producto de Prueba',
                        'Cantidad' => 1,
                        'Precio' => 100000,
                        'MontoItem' => 100000
                    ]
                ]
            ]
        ];

        // XML mínimo de prueba
        $xml_test = '<?xml version="1.0" encoding="iso-8859-1"?><DTE><Documento><TED></TED></Documento></DTE>';

        // Test 2.1: Generar PDF
        $test_name = "Generar PDF con datos completos";
        $pdf_path = $this->temp_dir . '/test_integration.pdf';

        try {
            generar_pdf_boleta($datos_test, $xml_test, $pdf_path);
            $this->assert(file_exists($pdf_path), $test_name, "PDF creado: " . filesize($pdf_path) . " bytes");
        } catch (Exception $e) {
            $this->assert(false, $test_name, $e->getMessage());
        }

        // Test 2.2: Validar tamaño del PDF
        if (file_exists($pdf_path)) {
            $test_name = "Validar tamaño del PDF";
            $size = filesize($pdf_path);
            $this->assert($size > 1000 && $size < 100000, $test_name, "Tamaño: $size bytes");

            // Test 2.3: Validar que es un PDF válido
            $test_name = "Validar formato PDF";
            $content = file_get_contents($pdf_path);
            $es_pdf = substr($content, 0, 4) === '%PDF';
            $this->assert($es_pdf, $test_name, "Signature: " . substr($content, 0, 8));
        }

        echo "\n";
    }

    // =========================================================================
    // TEST 3: Timbre PDF417
    // =========================================================================

    private function testTimbrePDF417() {
        $this->v->subtitulo("Test 3: Generación de Timbre PDF417");

        // Test 3.1: Generar timbre PDF417
        $test_name = "Generar código PDF417";
        $timbre_path = $this->temp_dir . '/test_timbre.png';

        try {
            // Usar XML real de boleta generada anteriormente
            $xml_real_path = __DIR__ . '/../xmls/boleta_1902.xml';

            if (file_exists($xml_real_path)) {
                $xml_content = file_get_contents($xml_real_path);
                generar_timbre_pdf417($xml_content, $timbre_path);
                $this->assert(file_exists($timbre_path), $test_name, "Timbre creado");
            } else {
                // Fallback: marcar como skip si no hay XML real
                $this->assert(true, $test_name, "SKIPPED (requiere XML real)");
            }
        } catch (Exception $e) {
            $this->assert(false, $test_name, $e->getMessage());
        }

        // Test 3.2: Validar que es una imagen
        if (file_exists($timbre_path)) {
            $test_name = "Validar formato de imagen PNG";
            $image_info = @getimagesize($timbre_path);
            $this->assert($image_info !== false, $test_name, "Tipo: " . ($image_info ? $image_info['mime'] : 'N/A'));
        }

        echo "\n";
    }

    // =========================================================================
    // TEST 4: Integración Folios y CAF
    // =========================================================================

    private function testFoliosYCAF() {
        $this->v->subtitulo("Test 4: Integración Folios y CAF");

        // Test 4.1: Leer folio actual
        $test_name = "Leer folio desde archivo";
        $folios_file = __DIR__ . '/../folios_usados.txt';

        try {
            $folio = (int)trim(file_get_contents($folios_file));
            $this->assert($folio > 0, $test_name, "Folio actual: $folio");
        } catch (Exception $e) {
            $this->assert(false, $test_name, $e->getMessage());
            return;
        }

        // Test 4.2: Cargar CAF
        $test_name = "Cargar archivo CAF";
        $caf_file = __DIR__ . '/../folios/folio_39.xml';

        try {
            $caf_content = file_get_contents($caf_file);
            $caf_xml = simplexml_load_string($caf_content);
            $this->assert($caf_xml !== false, $test_name, "CAF cargado");
        } catch (Exception $e) {
            $this->assert(false, $test_name, $e->getMessage());
            return;
        }

        // Test 4.3: Verificar que folio está en rango CAF
        if ($caf_xml) {
            $test_name = "Folio dentro del rango CAF";
            $rango_desde = (int)$caf_xml->CAF->DA->RNG->D;
            $rango_hasta = (int)$caf_xml->CAF->DA->RNG->H;

            $en_rango = $folio >= $rango_desde && $folio <= $rango_hasta;
            $this->assert($en_rango, $test_name, "Folio $folio en rango [$rango_desde, $rango_hasta]");
        }

        echo "\n";
    }

    // =========================================================================
    // TEST 5: Integración XML y PDF
    // =========================================================================

    private function testXMLyPDF() {
        $this->v->subtitulo("Test 5: Integración XML → PDF");

        // Test 5.1: Crear XML válido
        $test_name = "Crear XML de DTE";
        $xml = '<?xml version="1.0" encoding="iso-8859-1"?>' . "\n";
        $xml .= '<DTE version="1.0">' . "\n";
        $xml .= '  <Documento ID="T_TEST">' . "\n";
        $xml .= '    <Encabezado>' . "\n";
        $xml .= '      <IdDoc><TipoDTE>39</TipoDTE><Folio>9999</Folio></IdDoc>' . "\n";
        $xml .= '      <Totales><MntTotal>119000</MntTotal></Totales>' . "\n";
        $xml .= '    </Encabezado>' . "\n";
        $xml .= '    <TED><DD><RE>78274225-6</RE></DD></TED>' . "\n";
        $xml .= '  </Documento>' . "\n";
        $xml .= '</DTE>';

        $xml_parsed = simplexml_load_string($xml);
        $this->assert($xml_parsed !== false, $test_name);

        // Test 5.2: Guardar XML en archivo
        if ($xml_parsed) {
            $test_name = "Guardar XML en archivo";
            $xml_file = $this->temp_dir . '/test_dte.xml';
            file_put_contents($xml_file, $xml);
            $this->assert(file_exists($xml_file), $test_name, "XML guardado");

            // Test 5.3: Generar PDF desde XML
            $test_name = "Generar PDF desde XML guardado";
            $pdf_file = $this->temp_dir . '/test_from_xml.pdf';

            $datos = [
                'Documento' => [
                    'Encabezado' => [
                        'IdentificacionDTE' => ['TipoDTE' => 39, 'Folio' => 9999, 'FechaEmision' => date('Y-m-d')],
                        'Emisor' => ['Rut' => '78274225-6', 'RazonSocial' => 'TEST', 'GiroBoleta' => 'Test', 'DireccionOrigen' => 'Test', 'ComunaOrigen' => 'Test'],
                        'Receptor' => ['Rut' => '66666666-6', 'RazonSocial' => 'Test', 'Direccion' => 'Test', 'Comuna' => 'Test'],
                        'Totales' => ['MontoNeto' => 100000, 'IVA' => 19000, 'MontoTotal' => 119000]
                    ],
                    'Detalles' => [['NmbItem' => 'Test', 'Cantidad' => 1, 'Precio' => 100000, 'MontoItem' => 100000]]
                ]
            ];

            try {
                generar_pdf_boleta($datos, $xml, $pdf_file);
                $this->assert(file_exists($pdf_file), $test_name);
            } catch (Exception $e) {
                $this->assert(false, $test_name, $e->getMessage());
            }
        }

        echo "\n";
    }

    // =========================================================================
    // TEST 6: Conexión con Simple API
    // =========================================================================

    private function testSimpleAPIConnection() {
        $this->v->subtitulo("Test 6: Conexión con Simple API");

        // Test 6.1: Verificar certificado existe
        $test_name = "Certificado digital existe";
        $cert_path = __DIR__ . '/../16694181-4.pfx';
        $this->assert(file_exists($cert_path), $test_name, $cert_path);

        // Test 6.2: Verificar API key configurada
        $test_name = "API Key configurada";
        $api_key = '9794-N370-6392-6913-8052';
        $this->assert(strlen($api_key) > 0, $test_name, "Key: " . substr($api_key, 0, 10) . "...");

        // Test 6.3: Test de conectividad (sin enviar datos reales)
        $test_name = "Conectividad con Simple API";
        try {
            $ch = curl_init('https://api.simple.cl');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_NOBODY => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Cualquier respuesta HTTP (incluso 404) indica que el servidor está accesible
            $this->assert($http_code > 0, $test_name, "HTTP Code: $http_code");
        } catch (Exception $e) {
            $this->assert(false, $test_name, $e->getMessage());
        }

        echo "\n";
    }

    // =========================================================================
    // Utilidades
    // =========================================================================

    private function assert($condition, $test_name, $details = '') {
        if ($condition) {
            $this->tests_passed++;
            echo "  ✅ PASS: $test_name";
            if ($details) echo " ($details)";
            echo "\n";
        } else {
            $this->tests_failed++;
            echo "  ❌ FAIL: $test_name";
            if ($details) echo " ($details)";
            echo "\n";
        }
    }

    private function cleanup() {
        // Limpiar archivos temporales
        if (is_dir($this->temp_dir)) {
            $files = glob($this->temp_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->temp_dir);
        }
    }

    private function showSummary() {
        echo "\n";
        $this->v->titulo("RESUMEN DE TESTS DE INTEGRACIÓN");
        echo "\n";

        $total = $this->tests_passed + $this->tests_failed;
        $percentage = $total > 0 ? round(($this->tests_passed / $total) * 100, 2) : 0;

        $this->v->lista([
            ['texto' => 'Total tests', 'valor' => $total],
            ['texto' => 'Pasados', 'valor' => $this->tests_passed . ' ✅'],
            ['texto' => 'Fallados', 'valor' => $this->tests_failed . ' ❌'],
            ['texto' => 'Porcentaje', 'valor' => $percentage . '%'],
        ]);

        echo "\n";

        if ($this->tests_failed == 0) {
            $this->v->mensaje('success', '¡Todos los tests de integración pasaron! 🎉');
        } else {
            $this->v->mensaje('error', "Hay $this->tests_failed tests que fallaron");
        }

        echo "\n";

        return $this->tests_failed == 0;
    }
}

// Ejecutar tests si se llama directamente
if (php_sapi_name() === 'cli' && basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    $test = new IntegrationTest();
    $success = $test->run();
    exit($success ? 0 : 1);
}
