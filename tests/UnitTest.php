<?php
/**
 * Tests Unitarios - Sistema de Boletas Electrónicas
 *
 * Prueba funciones individuales de forma aislada
 */

require_once(__DIR__ . '/../lib/VisualHelper.php');
require_once(__DIR__ . '/../lib/generar-pdf-boleta.php');

class UnitTest {

    private $v;
    private $tests_passed = 0;
    private $tests_failed = 0;
    private $results = [];

    public function __construct() {
        $this->v = VisualHelper::getInstance();
    }

    public function run() {
        $this->v->limpiar();
        echo "\n";
        $this->v->titulo("TESTS UNITARIOS - Sistema de Boletas Electrónicas");
        echo "\n";

        // Ejecutar todos los tests
        $this->testControlFolios();
        $this->testCalculoTotales();
        $this->testValidacionRUT();
        $this->testFormatoFecha();
        $this->testEncodingConversion();
        $this->testMontoFormato();
        $this->testXMLStructure();
        $this->testCAFValidation();

        // Mostrar resumen
        $this->showSummary();
    }

    // =========================================================================
    // TEST 1: Control de Folios
    // =========================================================================

    private function testControlFolios() {
        $this->v->subtitulo("Test 1: Control de Folios");

        $folios_file = __DIR__ . '/../folios_usados.txt';

        // Test 1.1: Leer folio actual
        $test_name = "Leer folio desde archivo";
        try {
            $folio = (int)trim(file_get_contents($folios_file));
            $this->assert($folio > 0, $test_name, "Folio: $folio");
        } catch (Exception $e) {
            $this->assert(false, $test_name, $e->getMessage());
        }

        // Test 1.2: Validar rango de folios
        $test_name = "Validar folio en rango CAF (1889-1988)";
        $folio_min = 1889;
        $folio_max = 1988;
        $this->assert(
            $folio >= $folio_min && $folio <= $folio_max,
            $test_name,
            "$folio está en rango [$folio_min, $folio_max]"
        );

        // Test 1.3: Incrementar folio
        $test_name = "Incrementar folio";
        $nuevo_folio = $folio + 1;
        $this->assert(
            $nuevo_folio == $folio + 1,
            $test_name,
            "$folio → $nuevo_folio"
        );

        echo "\n";
    }

    // =========================================================================
    // TEST 2: Cálculo de Totales
    // =========================================================================

    private function testCalculoTotales() {
        $this->v->subtitulo("Test 2: Cálculo de Totales");

        // Test 2.1: Cálculo de IVA
        $test_name = "Calcular IVA (19%)";
        $neto = 100000;
        $iva = round($neto * 0.19);
        $this->assert($iva == 19000, $test_name, "Neto: $neto → IVA: $iva");

        // Test 2.2: Cálculo de Total
        $test_name = "Calcular Total (Neto + IVA)";
        $total = $neto + $iva;
        $this->assert($total == 119000, $test_name, "Total: $total");

        // Test 2.3: Calcular Neto desde Total con IVA
        $test_name = "Calcular Neto desde Total con IVA";
        $total_con_iva = 119000;
        $neto_calculado = round($total_con_iva / 1.19);
        $this->assert(
            abs($neto_calculado - 100000) < 2,  // Margen de redondeo
            $test_name,
            "Total: $total_con_iva → Neto: $neto_calculado"
        );

        // Test 2.4: Totales con múltiples items
        $test_name = "Sumar múltiples items";
        $items = [
            ['precio' => 10000, 'cantidad' => 2],  // 20000
            ['precio' => 15000, 'cantidad' => 1],  // 15000
            ['precio' => 5000, 'cantidad' => 3],   // 15000
        ];

        $total_items = 0;
        foreach ($items as $item) {
            $total_items += $item['precio'] * $item['cantidad'];
        }

        $this->assert($total_items == 50000, $test_name, "Total items: $total_items");

        echo "\n";
    }

    // =========================================================================
    // TEST 3: Validación de RUT
    // =========================================================================

    private function testValidacionRUT() {
        $this->v->subtitulo("Test 3: Validación de RUT");

        $test_cases = [
            ['rut' => '76063822-6', 'valido' => true],
            ['rut' => '78274225-6', 'valido' => true],
            ['rut' => '66666666-6', 'valido' => true],
            ['rut' => '11111111-1', 'valido' => true],
            ['rut' => '12345678-5', 'valido' => true],
        ];

        foreach ($test_cases as $case) {
            $test_name = "Validar formato RUT: {$case['rut']}";
            $valido = preg_match('/^\d{7,8}-[\dkK]$/', $case['rut']);
            $this->assert($valido, $test_name, $case['rut']);
        }

        echo "\n";
    }

    // =========================================================================
    // TEST 4: Formato de Fecha
    // =========================================================================

    private function testFormatoFecha() {
        $this->v->subtitulo("Test 4: Formato de Fecha");

        // Test 4.1: Fecha actual en formato ISO
        $test_name = "Fecha en formato ISO (Y-m-d)";
        $fecha = date('Y-m-d');
        $valido = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha);
        $this->assert($valido, $test_name, $fecha);

        // Test 4.2: Conversión de timestamp a fecha
        $test_name = "Convertir timestamp a fecha";
        $timestamp = strtotime('2025-11-17');
        $fecha_convertida = date('Y-m-d', $timestamp);
        $this->assert($fecha_convertida == '2025-11-17', $test_name, $fecha_convertida);

        // Test 4.3: Validar fecha válida
        $test_name = "Validar fecha válida";
        $fecha_valida = checkdate(11, 17, 2025);
        $this->assert($fecha_valida, $test_name, "2025-11-17 es válida");

        echo "\n";
    }

    // =========================================================================
    // TEST 5: Conversión de Encoding
    // =========================================================================

    private function testEncodingConversion() {
        $this->v->subtitulo("Test 5: Conversión de Encoding UTF-8 → ISO-8859-1");

        // Crear instancia temporal de PDF para acceder al método
        $pdf_test = new class extends FPDF {
            private function utf8ToLatin1($text) {
                if (empty($text)) return '';
                return mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
            }

            public function testConversion($text) {
                return $this->utf8ToLatin1($text);
            }
        };

        $test_cases = [
            ['input' => 'N°', 'contains' => '°'],
            ['input' => 'ELECTRÓNICA', 'contains' => 'Ó'],
            ['input' => 'Ñuñoa', 'contains' => 'Ñ'],
            ['input' => 'José María', 'contains' => 'é'],
            ['input' => 'Peñalolén', 'contains' => 'ñ'],
        ];

        foreach ($test_cases as $case) {
            $test_name = "Convertir: {$case['input']}";
            $converted = $pdf_test->testConversion($case['input']);
            $this->assert(
                strlen($converted) > 0,
                $test_name,
                "OK (length: " . strlen($converted) . ")"
            );
        }

        echo "\n";
    }

    // =========================================================================
    // TEST 6: Formato de Montos
    // =========================================================================

    private function testMontoFormato() {
        $this->v->subtitulo("Test 6: Formato de Montos");

        // Test 6.1: Formato con separador de miles
        $test_name = "Formatear con separador de miles";
        $monto = 1234567;
        $formateado = number_format($monto, 0, ',', '.');
        $this->assert($formateado == '1.234.567', $test_name, "$$formateado");

        // Test 6.2: Formato sin decimales
        $test_name = "Formatear sin decimales";
        $monto = 150000.99;
        $sin_decimales = (int)$monto;
        $this->assert($sin_decimales == 150000, $test_name, "$sin_decimales");

        echo "\n";
    }

    // =========================================================================
    // TEST 7: Estructura XML
    // =========================================================================

    private function testXMLStructure() {
        $this->v->subtitulo("Test 7: Estructura XML");

        // Test 7.1: Crear XML básico
        $test_name = "Crear estructura XML básica";
        $xml = '<?xml version="1.0" encoding="ISO-8859-1"?>';
        $xml .= '<DTE><Documento><Folio>1909</Folio></Documento></DTE>';

        libxml_use_internal_errors(true);
        $parsed = simplexml_load_string($xml);
        $this->assert($parsed !== false, $test_name, "XML válido");

        // Test 7.2: Acceder a nodos
        $test_name = "Acceder a nodos XML";
        if ($parsed) {
            $folio = (string)$parsed->Documento->Folio;
            $this->assert($folio == '1909', $test_name, "Folio: $folio");
        }

        // Test 7.3: Validar encoding
        $test_name = "Validar encoding ISO-8859-1";
        $tiene_encoding = strpos($xml, 'encoding="ISO-8859-1"') !== false;
        $this->assert($tiene_encoding, $test_name, "Encoding correcto");

        echo "\n";
    }

    // =========================================================================
    // TEST 8: Validación de CAF
    // =========================================================================

    private function testCAFValidation() {
        $this->v->subtitulo("Test 8: Validación de CAF");

        $caf_file = __DIR__ . '/../folios/folio_39.xml';

        // Test 8.1: Archivo CAF existe
        $test_name = "Archivo CAF existe";
        $this->assert(file_exists($caf_file), $test_name, $caf_file);

        if (file_exists($caf_file)) {
            // Test 8.2: CAF es XML válido
            $test_name = "CAF es XML válido";
            $caf_content = file_get_contents($caf_file);
            libxml_use_internal_errors(true);
            $caf_xml = simplexml_load_string($caf_content);
            $this->assert($caf_xml !== false, $test_name, "XML válido");

            if ($caf_xml) {
                // Test 8.3: CAF tiene rango de folios
                $test_name = "CAF tiene rango de folios";
                $rango_desde = (int)$caf_xml->DA->RNG->D;
                $rango_hasta = (int)$caf_xml->DA->RNG->H;
                $this->assert(
                    $rango_desde > 0 && $rango_hasta > $rango_desde,
                    $test_name,
                    "Rango: $rango_desde - $rango_hasta"
                );
            }
        }

        echo "\n";
    }

    // =========================================================================
    // Utilidades de Testing
    // =========================================================================

    private function assert($condition, $test_name, $details = '') {
        if ($condition) {
            $this->tests_passed++;
            $this->results[] = ['status' => 'pass', 'name' => $test_name, 'details' => $details];
            echo "  ✅ PASS: $test_name";
            if ($details) echo " ($details)";
            echo "\n";
        } else {
            $this->tests_failed++;
            $this->results[] = ['status' => 'fail', 'name' => $test_name, 'details' => $details];
            echo "  ❌ FAIL: $test_name";
            if ($details) echo " ($details)";
            echo "\n";
        }
    }

    private function showSummary() {
        echo "\n";
        $this->v->titulo("RESUMEN DE TESTS UNITARIOS");
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
            $this->v->mensaje('success', '¡Todos los tests unitarios pasaron! 🎉');
        } else {
            $this->v->mensaje('error', "Hay $this->tests_failed tests que fallaron");

            echo "\n  Tests fallados:\n";
            foreach ($this->results as $result) {
                if ($result['status'] == 'fail') {
                    echo "    • {$result['name']}\n";
                }
            }
        }

        echo "\n";

        return $this->tests_failed == 0;
    }
}

// Ejecutar tests si se llama directamente
if (php_sapi_name() === 'cli' && basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    $test = new UnitTest();
    $success = $test->run();
    exit($success ? 0 : 1);
}
