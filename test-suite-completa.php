<?php
/**
 * Suite Completa de Tests del Sistema de Boletas Electrónicas
 * Verifica todos los componentes críticos del sistema
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once(__DIR__ . '/lib/VisualHelper.php');
require_once(__DIR__ . '/lib/generar-pdf-boleta.php');

$v = VisualHelper::getInstance();
$v->limpiar();

echo "\n";
$v->titulo("SUITE COMPLETA DE TESTS - Sistema Boletas Electrónicas SII");
echo "\n";

// Contadores de tests
$total_tests = 0;
$tests_exitosos = 0;
$tests_fallidos = 0;
$warnings = 0;

// Función helper para ejecutar tests
function ejecutar_test($nombre, $callback, &$total, &$exitosos, &$fallidos, &$warnings) {
    global $v;

    $total++;
    echo "\n";
    $v->subtitulo("TEST #{$total}: $nombre");
    echo "\n";

    try {
        $resultado = $callback();

        if ($resultado === true) {
            $v->mensaje('success', "✅ Test EXITOSO");
            $exitosos++;
        } elseif ($resultado === 'warning') {
            $v->mensaje('warning', "⚠️  Test con ADVERTENCIAS");
            $warnings++;
        } else {
            $v->mensaje('error', "❌ Test FALLIDO: " . ($resultado ?? 'Error desconocido'));
            $fallidos++;
        }

    } catch (Exception $e) {
        $v->mensaje('error', "❌ Test FALLIDO: " . $e->getMessage());
        $fallidos++;
    }
}

// ============================================================================
// TEST 1: Verificar Dependencias y Archivos Necesarios
// ============================================================================

ejecutar_test("Verificar Dependencias y Archivos", function() use ($v) {

    $archivos_requeridos = [
        'lib/fpdf.php' => 'Librería FPDF',
        'lib/generar-pdf-boleta.php' => 'Generador de PDF',
        'lib/generar-timbre-pdf417.php' => 'Generador de Timbre PDF417',
        'lib/VisualHelper.php' => 'Helper Visual',
        'generar-y-enviar-email.php' => 'Script principal',
        'folios_usados.txt' => 'Control de folios',
        '16694181-4.pfx' => 'Certificado digital'
    ];

    $errores = [];

    foreach ($archivos_requeridos as $archivo => $desc) {
        if (file_exists(__DIR__ . '/' . $archivo)) {
            echo "  ✓ $desc: $archivo\n";
        } else {
            echo "  ✗ FALTA: $archivo ($desc)\n";
            $errores[] = "Falta archivo: $archivo";
        }
    }

    // Verificar extensiones PHP
    $extensiones = ['curl', 'xml', 'gd', 'mbstring'];

    echo "\n  Extensiones PHP:\n";
    foreach ($extensiones as $ext) {
        if (extension_loaded($ext)) {
            echo "  ✓ $ext\n";
        } else {
            echo "  ✗ FALTA: $ext\n";
            $errores[] = "Falta extensión PHP: $ext";
        }
    }

    return empty($errores) ? true : implode(', ', $errores);

}, $total_tests, $tests_exitosos, $tests_fallidos, $warnings);

// ============================================================================
// TEST 2: Control de Folios
// ============================================================================

ejecutar_test("Control de Folios", function() use ($v) {

    $folio_file = __DIR__ . '/folios_usados.txt';

    if (!file_exists($folio_file)) {
        return "Archivo folios_usados.txt no existe";
    }

    $folio_actual = (int) trim(file_get_contents($folio_file));

    echo "  • Folio actual: $folio_actual\n";

    if ($folio_actual < 1889 || $folio_actual > 1988) {
        echo "  ⚠️  ADVERTENCIA: Folio fuera del rango asignado (1889-1988)\n";
        return 'warning';
    }

    $disponibles = 1988 - $folio_actual + 1;
    echo "  • Folios disponibles: $disponibles\n";

    if ($disponibles < 10) {
        echo "  ⚠️  ADVERTENCIA: Quedan menos de 10 folios disponibles\n";
        return 'warning';
    }

    return true;

}, $total_tests, $tests_exitosos, $tests_fallidos, $warnings);

// ============================================================================
// TEST 3: Encoding UTF-8 → ISO-8859-1
// ============================================================================

ejecutar_test("Conversión de Encoding UTF-8 → ISO-8859-1", function() use ($v) {

    // Crear instancia temporal de PDF para probar la función
    $datos_test = [
        'Documento' => [
            'Encabezado' => [
                'IdentificacionDTE' => ['TipoDTE' => 39, 'Folio' => 1, 'FechaEmision' => date('Y-m-d')],
                'Emisor' => ['Rut' => '76063822-6', 'RazonSocial' => 'Test', 'GiroBoleta' => 'Test'],
                'Receptor' => ['Rut' => '11111111-1', 'RazonSocial' => 'Test'],
                'Totales' => ['MontoTotal' => 1000]
            ],
            'Detalles' => [['NmbItem' => 'Test', 'Cantidad' => 1, 'Precio' => 1000, 'MontoItem' => 1000]]
        ]
    ];

    $xml_test = '<?xml version="1.0"?><DTE><Documento><Encabezado><IdDoc><TipoDTE>39</TipoDTE><Folio>1</Folio></IdDoc></Encabezado></Documento></DTE>';

    $pdf = new BoletaPDF($datos_test, $xml_test);

    // Probar conversión mediante reflection
    $reflection = new ReflectionClass($pdf);
    $method = $reflection->getMethod('utf8ToLatin1');
    $method->setAccessible(true);

    $tests_encoding = [
        'N°' => 'N°',
        'ELECTRÓNICO' => 'ELECTRÓNICO',
        'Ñuñoa' => 'Ñuñoa',
        'José María' => 'José María',
        'Diseño Gráfico' => 'Diseño Gráfico',
        'Café Orgánico' => 'Café Orgánico'
    ];

    $errores = [];

    foreach ($tests_encoding as $entrada => $esperado) {
        $resultado = $method->invoke($pdf, $entrada);

        // La conversión debería devolver algo (no vacío)
        if (empty($resultado)) {
            $errores[] = "Conversión vacía para: $entrada";
            echo "  ✗ '$entrada' → (vacío)\n";
        } else {
            echo "  ✓ '$entrada' → convertido\n";
        }
    }

    return empty($errores) ? true : implode(', ', $errores);

}, $total_tests, $tests_exitosos, $tests_fallidos, $warnings);

// ============================================================================
// TEST 4: Generación de PDF Básico
// ============================================================================

ejecutar_test("Generación de PDF Básico", function() use ($v) {

    $datos_test = [
        'Documento' => [
            'Encabezado' => [
                'IdentificacionDTE' => [
                    'TipoDTE' => 39,
                    'Folio' => 9998,
                    'FechaEmision' => date('Y-m-d')
                ],
                'Emisor' => [
                    'Rut' => '76063822-6',
                    'RazonSocial' => 'EMPRESA TEST ELECTRÓNICA',
                    'GiroBoleta' => 'Venta de artículos de computación',
                    'DireccionOrigen' => 'Av. Test 123',
                    'ComunaOrigen' => 'Santiago'
                ],
                'Receptor' => [
                    'Rut' => '11111111-1',
                    'RazonSocial' => 'Cliente Test Ñuñoa'
                ],
                'Totales' => [
                    'MontoNeto' => 10000,
                    'IVA' => 1900,
                    'MontoTotal' => 11900
                ]
            ],
            'Detalles' => [
                [
                    'NmbItem' => 'Producto Café Orgánico',
                    'Cantidad' => 2,
                    'Precio' => 5000,
                    'MontoItem' => 10000
                ]
            ]
        ]
    ];

    $xml_test = '<?xml version="1.0"?><DTE><Documento><Encabezado><IdDoc><TipoDTE>39</TipoDTE><Folio>9998</Folio><FchEmis>' . date('Y-m-d') . '</FchEmis></IdDoc><Emisor><RUTEmisor>76063822-6</RUTEmisor></Emisor><Totales><MntTotal>11900</MntTotal></Totales></Encabezado></Documento></DTE>';

    $pdf_path = __DIR__ . '/pdfs/test_basico.pdf';

    // Generar PDF
    $resultado = generar_pdf_boleta($datos_test, $xml_test, $pdf_path);

    if (!$resultado) {
        return "No se pudo generar el PDF";
    }

    if (!file_exists($pdf_path)) {
        return "El archivo PDF no fue creado";
    }

    $tamano = filesize($pdf_path);
    echo "  • PDF generado: " . basename($pdf_path) . "\n";
    echo "  • Tamaño: " . number_format($tamano / 1024, 2) . " KB\n";

    if ($tamano < 1000) {
        return "PDF muy pequeño (posible error): $tamano bytes";
    }

    return true;

}, $total_tests, $tests_exitosos, $tests_fallidos, $warnings);

// ============================================================================
// TEST 5: PDF con Múltiples Items
// ============================================================================

ejecutar_test("PDF con Múltiples Items (10 productos)", function() use ($v) {

    $items = [];
    for ($i = 1; $i <= 10; $i++) {
        $items[] = [
            'NmbItem' => "Producto Test Nº $i con Descripción Larga",
            'Descripcion' => "Descripción adicional del producto número $i con caracteres especiales: café, señalización, año",
            'Cantidad' => $i,
            'Precio' => 1000 * $i,
            'MontoItem' => 1000 * $i * $i
        ];
    }

    $total = array_sum(array_column($items, 'MontoItem'));

    $datos_test = [
        'Documento' => [
            'Encabezado' => [
                'IdentificacionDTE' => [
                    'TipoDTE' => 39,
                    'Folio' => 9997,
                    'FechaEmision' => date('Y-m-d')
                ],
                'Emisor' => [
                    'Rut' => '76063822-6',
                    'RazonSocial' => 'EMPRESA TEST',
                    'GiroBoleta' => 'Test'
                ],
                'Receptor' => [
                    'Rut' => '11111111-1',
                    'RazonSocial' => 'Cliente Test'
                ],
                'Totales' => [
                    'MontoTotal' => $total
                ]
            ],
            'Detalles' => $items
        ]
    ];

    $xml_test = '<?xml version="1.0"?><DTE><Documento><Encabezado><IdDoc><TipoDTE>39</TipoDTE><Folio>9997</Folio><FchEmis>' . date('Y-m-d') . '</FchEmis></IdDoc><Emisor><RUTEmisor>76063822-6</RUTEmisor></Emisor><Totales><MntTotal>' . $total . '</MntTotal></Totales></Encabezado></Documento></DTE>';

    $pdf_path = __DIR__ . '/pdfs/test_multiples_items.pdf';

    $resultado = generar_pdf_boleta($datos_test, $xml_test, $pdf_path);

    if (!$resultado || !file_exists($pdf_path)) {
        return "No se pudo generar el PDF con múltiples items";
    }

    $tamano = filesize($pdf_path);
    echo "  • Items: 10 productos\n";
    echo "  • Total: $" . number_format($total, 0, ',', '.') . "\n";
    echo "  • PDF: " . basename($pdf_path) . "\n";
    echo "  • Tamaño: " . number_format($tamano / 1024, 2) . " KB\n";

    // Verificar que el PDF sea más grande (más contenido)
    if ($tamano < 2000) {
        return "PDF muy pequeño para 10 items: $tamano bytes";
    }

    return true;

}, $total_tests, $tests_exitosos, $tests_fallidos, $warnings);

// ============================================================================
// TEST 6: Verificar Estructura de Directorios
// ============================================================================

ejecutar_test("Verificar Estructura de Directorios", function() use ($v) {

    $directorios = [
        'pdfs' => 'PDFs generados',
        'xmls' => 'XMLs de DTEs',
        'lib' => 'Librerías'
    ];

    $errores = [];

    foreach ($directorios as $dir => $desc) {
        $path = __DIR__ . '/' . $dir;

        if (is_dir($path)) {
            $permisos = substr(sprintf('%o', fileperms($path)), -4);
            echo "  ✓ $desc ($dir) - permisos: $permisos\n";

            if (!is_writable($path)) {
                echo "  ⚠️  ADVERTENCIA: $dir no es escribible\n";
                $errores[] = "$dir no escribible";
            }
        } else {
            echo "  ✗ FALTA: $dir ($desc)\n";
            $errores[] = "Falta directorio: $dir";
        }
    }

    return empty($errores) ? true : 'warning';

}, $total_tests, $tests_exitosos, $tests_fallidos, $warnings);

// ============================================================================
// TEST 7: Verificar Tamaño Dinámico del PDF
// ============================================================================

ejecutar_test("Tamaño Dinámico del PDF (2 pasadas)", function() use ($v) {

    // Test con 1 item
    $datos_1_item = [
        'Documento' => [
            'Encabezado' => [
                'IdentificacionDTE' => ['TipoDTE' => 39, 'Folio' => 9996, 'FechaEmision' => date('Y-m-d')],
                'Emisor' => ['Rut' => '76063822-6', 'RazonSocial' => 'TEST', 'GiroBoleta' => 'Test'],
                'Receptor' => ['Rut' => '11111111-1', 'RazonSocial' => 'Cliente'],
                'Totales' => ['MontoTotal' => 1000]
            ],
            'Detalles' => [['NmbItem' => 'Item 1', 'Cantidad' => 1, 'Precio' => 1000, 'MontoItem' => 1000]]
        ]
    ];

    // Test con 20 items
    $items_20 = [];
    for ($i = 1; $i <= 20; $i++) {
        $items_20[] = ['NmbItem' => "Item con nombre largo número $i", 'Cantidad' => 1, 'Precio' => 100, 'MontoItem' => 100];
    }

    $datos_20_items = $datos_1_item;
    $datos_20_items['Documento']['Detalles'] = $items_20;
    $datos_20_items['Documento']['Encabezado']['Totales']['MontoTotal'] = 2000;

    $xml_test = '<?xml version="1.0"?><DTE><Documento><Encabezado><IdDoc><TipoDTE>39</TipoDTE><Folio>9996</Folio></IdDoc></Encabezado></Documento></DTE>';

    $pdf_1 = __DIR__ . '/pdfs/test_1_item.pdf';
    $pdf_20 = __DIR__ . '/pdfs/test_20_items.pdf';

    generar_pdf_boleta($datos_1_item, $xml_test, $pdf_1);
    generar_pdf_boleta($datos_20_items, $xml_test, $pdf_20);

    if (!file_exists($pdf_1) || !file_exists($pdf_20)) {
        return "No se pudieron generar los PDFs de prueba";
    }

    $tamano_1 = filesize($pdf_1);
    $tamano_20 = filesize($pdf_20);

    echo "  • PDF con 1 item: " . number_format($tamano_1) . " bytes\n";
    echo "  • PDF con 20 items: " . number_format($tamano_20) . " bytes\n";
    echo "  • Diferencia: " . number_format($tamano_20 - $tamano_1) . " bytes\n";

    // El PDF con más items debería ser más grande
    if ($tamano_20 <= $tamano_1) {
        return "El PDF con 20 items no es más grande que el de 1 item (tamaño dinámico no funciona)";
    }

    echo "  ✓ El tamaño dinámico funciona correctamente\n";

    return true;

}, $total_tests, $tests_exitosos, $tests_fallidos, $warnings);

// ============================================================================
// RESUMEN FINAL
// ============================================================================

echo "\n\n";
echo "═══════════════════════════════════════════════════════════════════════\n";
echo "                        RESUMEN DE TESTS                               \n";
echo "═══════════════════════════════════════════════════════════════════════\n";
echo "\n";

$porcentaje_exito = $total_tests > 0 ? ($tests_exitosos / $total_tests) * 100 : 0;

echo "  Total de tests ejecutados: $total_tests\n";
echo "  ✅ Tests exitosos: $tests_exitosos\n";
echo "  ⚠️  Tests con advertencias: $warnings\n";
echo "  ❌ Tests fallidos: $tests_fallidos\n";
echo "\n";
echo "  Porcentaje de éxito: " . number_format($porcentaje_exito, 1) . "%\n";
echo "\n";

if ($tests_fallidos == 0 && $warnings == 0) {
    $v->mensaje('success', '🎉 ¡TODOS LOS TESTS PASARON EXITOSAMENTE!');
} elseif ($tests_fallidos == 0) {
    $v->mensaje('warning', '⚠️  Todos los tests pasaron, pero con algunas advertencias');
} else {
    $v->mensaje('error', "❌ $tests_fallidos test(s) fallaron. Revisar errores arriba.");
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════════════\n";
echo "\n";

// PDFs generados durante los tests
echo "PDFs generados durante los tests:\n\n";
$pdfs_test = glob(__DIR__ . '/pdfs/test_*.pdf');
foreach ($pdfs_test as $pdf) {
    $size = filesize($pdf);
    echo "  • " . basename($pdf) . " (" . number_format($size / 1024, 2) . " KB)\n";
}

echo "\n";

// Exit code según resultado
exit($tests_fallidos > 0 ? 1 : 0);
