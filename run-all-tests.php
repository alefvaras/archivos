<?php
/**
 * Test Runner - Ejecuta todos los tests del sistema
 *
 * Ejecuta tests unitarios, de integración y end-to-end
 */

require_once(__DIR__ . '/lib/VisualHelper.php');
require_once(__DIR__ . '/tests/UnitTest.php');
require_once(__DIR__ . '/tests/IntegrationTest.php');
require_once(__DIR__ . '/tests/EndToEndTest.php');
require_once(__DIR__ . '/tests/RUTValidationTest.php');
require_once(__DIR__ . '/tests/HPOSCompatibilityTest.php');
require_once(__DIR__ . '/tests/SecurityTest.php');

$v = VisualHelper::getInstance();
$v->limpiar();

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                   TEST SUITE COMPLETA                          ║\n";
echo "║          Sistema de Boletas Electrónicas - SII                ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

$start_time = microtime(true);

// Configuración
$skip_e2e_real_send = !isset($argv[1]) || $argv[1] !== '--real';

if ($skip_e2e_real_send) {
    $v->mensaje('info', 'Tests E2E sin envío real al SII (modo seguro)');
    echo "  Para incluir envío real: php run-all-tests.php --real\n";
} else {
    $v->mensaje('warning', 'Tests E2E CON envío real al SII');
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "\n";

// Resultados
$results = [];

// ============================================================================
// 1. TESTS UNITARIOS
// ============================================================================

echo "\n";
echo "┌─────────────────────────────────────────────────────────────┐\n";
echo "│ 1️⃣  TESTS UNITARIOS                                         │\n";
echo "└─────────────────────────────────────────────────────────────┘\n";
echo "\n";

$unit_test = new UnitTest();
ob_start();
$unit_success = $unit_test->run();
$unit_output = ob_get_clean();
echo $unit_output;

$results['unit'] = [
    'success' => $unit_success,
    'output' => $unit_output
];

echo "═══════════════════════════════════════════════════════════════\n";

// ============================================================================
// 2. TESTS DE INTEGRACIÓN
// ============================================================================

echo "\n";
echo "┌─────────────────────────────────────────────────────────────┐\n";
echo "│ 2️⃣  TESTS DE INTEGRACIÓN                                    │\n";
echo "└─────────────────────────────────────────────────────────────┘\n";
echo "\n";

$integration_test = new IntegrationTest();
ob_start();
$integration_success = $integration_test->run();
$integration_output = ob_get_clean();
echo $integration_output;

$results['integration'] = [
    'success' => $integration_success,
    'output' => $integration_output
];

echo "═══════════════════════════════════════════════════════════════\n";

// ============================================================================
// 3. TESTS END-TO-END
// ============================================================================

echo "\n";
echo "┌─────────────────────────────────────────────────────────────┐\n";
echo "│ 3️⃣  TESTS END-TO-END                                        │\n";
echo "└─────────────────────────────────────────────────────────────┘\n";
echo "\n";

$e2e_test = new EndToEndTest();
ob_start();
$e2e_success = $e2e_test->run($skip_e2e_real_send);
$e2e_output = ob_get_clean();
echo $e2e_output;

$results['e2e'] = [
    'success' => $e2e_success,
    'output' => $e2e_output
];

echo "═══════════════════════════════════════════════════════════════\n";

// ============================================================================
// 4. TESTS DE VALIDACIÓN DE RUT
// ============================================================================

echo "\n";
echo "┌─────────────────────────────────────────────────────────────┐\n";
echo "│ 4️⃣  TESTS DE VALIDACIÓN DE RUT CHILENO                     │\n";
echo "└─────────────────────────────────────────────────────────────┘\n";
echo "\n";

$rut_test = new RUTValidationTest();
ob_start();
$rut_success = $rut_test->run();
$rut_output = ob_get_clean();
echo $rut_output;

$results['rut'] = [
    'success' => $rut_success,
    'output' => $rut_output
];

echo "═══════════════════════════════════════════════════════════════\n";

// ============================================================================
// 5. TESTS DE COMPATIBILIDAD HPOS
// ============================================================================

echo "\n";
echo "┌─────────────────────────────────────────────────────────────┐\n";
echo "│ 5️⃣  TESTS DE COMPATIBILIDAD HPOS                           │\n";
echo "└─────────────────────────────────────────────────────────────┘\n";
echo "\n";

$hpos_test = new HPOSCompatibilityTest();
ob_start();
$hpos_success = $hpos_test->run();
$hpos_output = ob_get_clean();
echo $hpos_output;

$results['hpos'] = [
    'success' => $hpos_success,
    'output' => $hpos_output
];

echo "═══════════════════════════════════════════════════════════════\n";

// ============================================================================
// 6. TESTS DE SEGURIDAD
// ============================================================================

echo "\n";
echo "┌─────────────────────────────────────────────────────────────┐\n";
echo "│ 6️⃣  TESTS DE SEGURIDAD                                      │\n";
echo "└─────────────────────────────────────────────────────────────┘\n";
echo "\n";

$security_test = new SecurityTest();
ob_start();
$security_success = $security_test->run();
$security_output = ob_get_clean();
echo $security_output;

$results['security'] = [
    'success' => $security_success,
    'output' => $security_output
];

echo "═══════════════════════════════════════════════════════════════\n";

// ============================================================================
// RESUMEN FINAL
// ============================================================================

$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 2);

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                    RESUMEN FINAL                               ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

$all_success = $results['unit']['success'] &&
               $results['integration']['success'] &&
               $results['e2e']['success'] &&
               $results['rut']['success'] &&
               $results['hpos']['success'] &&
               $results['security']['success'];

$v->lista([
    ['texto' => 'Tests Unitarios', 'valor' => $results['unit']['success'] ? '✅ PASS' : '❌ FAIL'],
    ['texto' => 'Tests Integración', 'valor' => $results['integration']['success'] ? '✅ PASS' : '❌ FAIL'],
    ['texto' => 'Tests End-to-End', 'valor' => $results['e2e']['success'] ? '✅ PASS' : '❌ FAIL'],
    ['texto' => 'Tests RUT', 'valor' => $results['rut']['success'] ? '✅ PASS' : '❌ FAIL'],
    ['texto' => 'Tests HPOS', 'valor' => $results['hpos']['success'] ? '✅ PASS' : '❌ FAIL'],
    ['texto' => 'Tests Seguridad', 'valor' => $results['security']['success'] ? '✅ PASS' : '❌ FAIL'],
]);

echo "\n";

$v->lista([
    ['texto' => 'Tiempo de ejecución', 'valor' => $execution_time . ' segundos'],
    ['texto' => 'Estado general', 'valor' => $all_success ? '✅ TODOS PASARON' : '❌ HAY FALLOS'],
]);

echo "\n";

if ($all_success) {
    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║  🎉 ¡EXCELENTE! TODOS LOS TESTS PASARON EXITOSAMENTE 🎉       ║\n";
    echo "║                                                                ║\n";
    echo "║  El sistema está completamente funcional y certificado.       ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n";
} else {
    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║  ⚠️  ATENCIÓN: HAY TESTS QUE FALLARON                          ║\n";
    echo "║                                                                ║\n";
    echo "║  Revisa los detalles arriba para identificar los problemas.   ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n";
}

echo "\n";

// Generar reporte
$report_file = __DIR__ . '/test-report-' . date('Y-m-d-His') . '.txt';
$report = "TEST SUITE REPORT - " . date('Y-m-d H:i:s') . "\n";
$report .= "=================================================\n\n";
$report .= "RESULTADOS:\n";
$report .= "- Tests Unitarios: " . ($results['unit']['success'] ? 'PASS' : 'FAIL') . "\n";
$report .= "- Tests Integración: " . ($results['integration']['success'] ? 'PASS' : 'FAIL') . "\n";
$report .= "- Tests End-to-End: " . ($results['e2e']['success'] ? 'PASS' : 'FAIL') . "\n";
$report .= "\nTiempo total: $execution_time segundos\n";
$report .= "\n=================================================\n\n";
$report .= "DETALLES:\n\n";
$report .= "UNIT TESTS:\n" . $results['unit']['output'] . "\n\n";
$report .= "INTEGRATION TESTS:\n" . $results['integration']['output'] . "\n\n";
$report .= "E2E TESTS:\n" . $results['e2e']['output'] . "\n";

file_put_contents($report_file, $report);

echo "📄 Reporte guardado en: $report_file\n";
echo "\n";

// Exit code
exit($all_success ? 0 : 1);
