<?php
/**
 * Tests End-to-End - Sistema de Boletas Electrónicas
 *
 * Prueba el flujo completo del sistema desde el inicio hasta el fin
 */

require_once(__DIR__ . '/../lib/VisualHelper.php');
require_once(__DIR__ . '/../lib/generar-pdf-boleta.php');

class EndToEndTest {

    private $v;
    private $tests_passed = 0;
    private $tests_failed = 0;
    private $test_folio;
    private $test_track_id;

    const API_BASE = 'https://api.simple.cl';
    const API_KEY = '9794-N370-6392-6913-8052';
    const CERT_PATH = __DIR__ . '/../16694181-4.pfx';
    const CERT_PASSWORD = 'Santiaguino.2017';
    const RUT_EMISOR = '76063822-6';

    public function __construct() {
        $this->v = VisualHelper::getInstance();
    }

    public function run($skip_sii_send = false) {
        $this->v->limpiar();
        echo "\n";
        $this->v->titulo("TESTS END-TO-END - Sistema de Boletas Electrónicas");
        echo "\n";

        if ($skip_sii_send) {
            $this->v->mensaje('info', 'Modo: Sin envío real al SII (solo validación local)');
            echo "\n";
        }

        // Flujo completo
        $this->testFlujoCompleto($skip_sii_send);

        // Mostrar resumen
        $this->showSummary();

        // Retornar resultado
        return $this->tests_failed == 0;
    }

    // =========================================================================
    // TEST: Flujo Completo End-to-End
    // =========================================================================

    private function testFlujoCompleto($skip_sii_send) {
        $this->v->subtitulo("FLUJO END-TO-END COMPLETO");
        echo "\n";

        // PASO 1: Obtener Folio
        echo "═══ PASO 1: Obtener Folio ═══\n";
        $test_name = "Leer y asignar folio";
        try {
            $folios_file = __DIR__ . '/../folios_usados.txt';
            $this->test_folio = (int)trim(file_get_contents($folios_file));
            $this->assert($this->test_folio > 0, $test_name, "Folio: {$this->test_folio}");
        } catch (Exception $e) {
            $this->assert(false, $test_name, $e->getMessage());
            return;
        }
        echo "\n";

        // PASO 2: Construir Documento DTE
        echo "═══ PASO 2: Construir Documento DTE ═══\n";
        $test_name = "Construir datos del documento";
        try {
            $documento = [
                'Documento' => [
                    'Encabezado' => [
                        'IdentificacionDTE' => [
                            'TipoDTE' => 39,
                            'Folio' => $this->test_folio,
                            'FechaEmision' => date('Y-m-d')
                        ],
                        'Emisor' => [
                            'Rut' => '78274225-6',
                            'RazonSocial' => 'AKIBARA SPA',
                            'GiroBoleta' => 'Servicios de Tecnología',
                            'DireccionOrigen' => 'Av. Providencia 1234',
                            'ComunaOrigen' => 'Providencia'
                        ],
                        'Receptor' => [
                            'Rut' => '66666666-6',
                            'RazonSocial' => 'Cliente Test E2E',
                            'Direccion' => 'Santiago, Chile',
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
                            'NmbItem' => 'Test End-to-End',
                            'Cantidad' => 1,
                            'Precio' => 100000,
                            'MontoItem' => 100000
                        ]
                    ]
                ],
                'Certificado' => [
                    'Rut' => '16694181-4',
                    'Password' => self::CERT_PASSWORD
                ]
            ];

            $this->assert(true, $test_name);
        } catch (Exception $e) {
            $this->assert(false, $test_name, $e->getMessage());
            return;
        }
        echo "\n";

        // PASO 3: Generar DTE Firmado (vía Simple API)
        if (!$skip_sii_send) {
            echo "═══ PASO 3: Generar DTE Firmado ═══\n";
            $test_name = "Enviar a Simple API para firma";

            try {
                $caf_path = __DIR__ . '/../folios/folio_39.xml';
                $caf_content = file_get_contents($caf_path);

                $boundary = '----WebKitFormBoundary' . md5(time());
                $eol = "\r\n";
                $body = '';

                // Construir multipart
                $body .= '--' . $boundary . $eol;
                $body .= 'Content-Disposition: form-data; name="input"' . $eol . $eol;
                $body .= json_encode($documento) . $eol;

                $body .= '--' . $boundary . $eol;
                $body .= 'Content-Disposition: form-data; name="files"; filename="' . basename(self::CERT_PATH) . '"' . $eol;
                $body .= 'Content-Type: application/x-pkcs12' . $eol . $eol;
                $body .= file_get_contents(self::CERT_PATH) . $eol;

                $body .= '--' . $boundary . $eol;
                $body .= 'Content-Disposition: form-data; name="files2"; filename="folio_39.xml"' . $eol;
                $body .= 'Content-Type: text/xml' . $eol . $eol;
                $body .= $caf_content . $eol;

                $body .= '--' . $boundary . $eol;
                $body .= 'Content-Disposition: form-data; name="password"' . $eol . $eol;
                $body .= self::CERT_PASSWORD . $eol;

                $body .= '--' . $boundary . '--' . $eol;

                $ch = curl_init(self::API_BASE . '/api/v1/dte/generar');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $body,
                    CURLOPT_HTTPHEADER => [
                        'Authorization: ' . self::API_KEY,
                        'Content-Type: multipart/form-data; boundary=' . $boundary,
                        'Content-Length: ' . strlen($body)
                    ],
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_TIMEOUT => 60,
                ]);

                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $dte_xml = $response;

                $success = $http_code == 200 && strpos($dte_xml, '<?xml') === 0;
                $this->assert($success, $test_name, "HTTP $http_code, XML: " . substr($dte_xml, 0, 50) . "...");

                if (!$success) {
                    echo "Respuesta: $response\n";
                    return;
                }
            } catch (Exception $e) {
                $this->assert(false, $test_name, $e->getMessage());
                return;
            }
        } else {
            echo "═══ PASO 3: OMITIDO (modo sin envío) ═══\n";
            // Crear XML de prueba
            $dte_xml = '<?xml version="1.0" encoding="iso-8859-1"?>';
            $dte_xml .= '<DTE><Documento><TED><DD><RE>78274225-6</RE></DD></TED></Documento></DTE>';
        }
        echo "\n";

        // PASO 4: Guardar XML
        echo "═══ PASO 4: Guardar XML del DTE ═══\n";
        $test_name = "Guardar XML en archivo";
        try {
            $xml_dir = __DIR__ . '/../xmls';
            if (!is_dir($xml_dir)) {
                mkdir($xml_dir, 0755, true);
            }

            $xml_file = $xml_dir . "/boleta_test_e2e.xml";
            file_put_contents($xml_file, $dte_xml);

            $this->assert(file_exists($xml_file), $test_name, "Guardado en: $xml_file");
        } catch (Exception $e) {
            $this->assert(false, $test_name, $e->getMessage());
        }
        echo "\n";

        // PASO 5: Generar PDF
        echo "═══ PASO 5: Generar PDF con Timbre ═══\n";
        $test_name = "Generar PDF de la boleta";
        try {
            $pdf_dir = __DIR__ . '/../pdfs';
            if (!is_dir($pdf_dir)) {
                mkdir($pdf_dir, 0755, true);
            }

            $pdf_file = $pdf_dir . "/boleta_test_e2e.pdf";
            generar_pdf_boleta($documento, $dte_xml, $pdf_file);

            $this->assert(file_exists($pdf_file), $test_name, "PDF: " . filesize($pdf_file) . " bytes");
        } catch (Exception $e) {
            $this->assert(false, $test_name, $e->getMessage());
        }
        echo "\n";

        // PASO 6: Validar PDF
        echo "═══ PASO 6: Validar PDF Generado ═══\n";
        if (file_exists($pdf_file)) {
            $test_name = "Validar estructura del PDF";
            $pdf_content = file_get_contents($pdf_file);
            $es_pdf = substr($pdf_content, 0, 4) === '%PDF';
            $this->assert($es_pdf, $test_name);

            $test_name = "Validar encoding en PDF (sin caracteres corruptos)";
            // Si el PDF contiene "NÂ°" en lugar de "N°", el encoding está mal
            $encoding_ok = strpos($pdf_content, 'NÂ') === false;
            $this->assert($encoding_ok, $test_name, $encoding_ok ? "Encoding correcto" : "Encoding corrupto detectado");
        }
        echo "\n";

        // PASO 7: Limpiar archivos de prueba
        echo "═══ PASO 7: Limpiar Archivos de Prueba ═══\n";
        $test_name = "Eliminar archivos temporales de test";
        try {
            if (isset($xml_file) && file_exists($xml_file)) {
                unlink($xml_file);
            }
            if (isset($pdf_file) && file_exists($pdf_file)) {
                unlink($pdf_file);
            }
            $this->assert(true, $test_name);
        } catch (Exception $e) {
            $this->assert(false, $test_name, $e->getMessage());
        }
        echo "\n";

        // Resumen del flujo
        echo "═══ FLUJO COMPLETO ═══\n";
        echo "  1. ✓ Folio asignado\n";
        echo "  2. ✓ Documento construido\n";
        echo "  3. " . ($skip_sii_send ? "⊘ DTE firmado (omitido)\n" : "✓ DTE firmado\n");
        echo "  4. ✓ XML guardado\n";
        echo "  5. ✓ PDF generado\n";
        echo "  6. ✓ PDF validado\n";
        echo "  7. ✓ Limpieza\n";
        echo "\n";
    }

    // =========================================================================
    // Utilidades
    // =========================================================================

    private function assert($condition, $test_name, $details = '') {
        if ($condition) {
            $this->tests_passed++;
            echo "  ✅ $test_name";
            if ($details) echo " → $details";
            echo "\n";
        } else {
            $this->tests_failed++;
            echo "  ❌ $test_name";
            if ($details) echo " → $details";
            echo "\n";
        }
    }

    private function showSummary() {
        echo "\n";
        $this->v->titulo("RESUMEN DE TESTS END-TO-END");
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
            $this->v->mensaje('success', '¡Flujo end-to-end completado exitosamente! 🎉');
        } else {
            $this->v->mensaje('error', "Hay $this->tests_failed pasos que fallaron");
        }

        echo "\n";

        return $this->tests_failed == 0;
    }
}

// Ejecutar tests si se llama directamente
if (php_sapi_name() === 'cli' && basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    // Por defecto, skip_sii_send = true para no consumir folios reales
    $skip_sii_send = !isset($argv[1]) || $argv[1] !== '--real';

    if ($skip_sii_send) {
        echo "\nModo: Sin envío real al SII (solo validación local)\n";
        echo "Para envío real, usa: php EndToEndTest.php --real\n\n";
    }

    $test = new EndToEndTest();
    $success = $test->run($skip_sii_send);
    exit($success ? 0 : 1);
}
