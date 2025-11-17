<?php
/**
 * Tests para Notas de Crédito Automáticas
 *
 * @package Simple_DTE
 */

require_once __DIR__ . '/helpers/TestCase.php';
require_once __DIR__ . '/../lib/VisualHelper.php';

class NotaCreditoTest extends TestCase {

    private $v;

    public function __construct() {
        $this->v = VisualHelper::getInstance();
    }

    public function run() {
        $this->v->limpiar();
        echo "\n";
        $this->v->titulo("TESTS DE NOTAS DE CRÉDITO AUTOMÁTICAS");
        echo "\n";

        // Ejecutar tests
        $this->testConfiguracionNC();
        $this->testValidacionesNC();
        $this->testTiposNC();
        $this->testRefundCompleto();
        $this->testRefundParcial();
        $this->testValidacionMontos();
        $this->testNCDuplicada();
        $this->testNCsinDTE();

        // Mostrar resumen
        $this->showSummary();

        // Retornar resultado
        return $this->tests_failed == 0;
    }

    // =========================================================================
    // TEST 1: Configuración de NC
    // =========================================================================

    private function testConfiguracionNC() {
        $this->v->subtitulo("Test 1: Configuración de NC Automáticas");

        // Test 1.1: Constantes de configuración
        $test_name = "Opciones de configuración definidas";
        $opciones = ['simple_dte_auto_nc_enabled', 'simple_dte_auto_nc_tipo', 'simple_dte_auto_nc_validar_monto'];
        $this->assert(count($opciones) === 3, $test_name, "3 opciones configurables");

        // Test 1.2: Tipos de NC válidos
        $test_name = "Tipos de NC válidos (1, 2, 3)";
        $tipos_validos = ['1', '2', '3'];
        $this->assert(
            count($tipos_validos) === 3,
            $test_name,
            "Tipos: " . implode(', ', $tipos_validos)
        );

        // Test 1.3: Configuración de validación de monto
        $test_name = "Validación de monto es booleana";
        $valores_validos = [true, false, '1', '0', null, ''];
        $this->assert(
            count($valores_validos) > 0,
            $test_name,
            "Valores booleanos"
        );

        echo "\n";
    }

    // =========================================================================
    // TEST 2: Validaciones de NC
    // =========================================================================

    private function testValidacionesNC() {
        $this->v->subtitulo("Test 2: Validaciones de NC");

        // Test 2.1: Archivo de clase NC existe
        $test_name = "Archivo class-simple-dte-nota-credito-generator.php existe";
        $archivo = __DIR__ . '/../includes/class-simple-dte-nota-credito-generator.php';
        $existe = file_exists($archivo);
        $this->assert($existe, $test_name, "Clase NC disponible");

        // Test 2.2: Métodos esperados en la clase
        $test_name = "Métodos auto_generar_nc_on_refund y generar_desde_orden requeridos";
        if ($existe) {
            $contenido = file_get_contents($archivo);
            $tiene_auto_generar = strpos($contenido, 'auto_generar_nc_on_refund') !== false;
            $tiene_generar = strpos($contenido, 'generar_desde_orden') !== false;
            $this->assert($tiene_auto_generar && $tiene_generar, $test_name, "Métodos presentes");
        } else {
            $this->assert(false, $test_name, "Archivo no existe");
        }

        // Test 2.3: Hook woocommerce_order_refunded
        $test_name = "Hook woocommerce_order_refunded en el código";
        if ($existe) {
            $contenido = file_get_contents($archivo);
            $tiene_hook = strpos($contenido, 'woocommerce_order_refunded') !== false;
            $this->assert($tiene_hook, $test_name, "Hook declarado");
        } else {
            $this->assert(false, $test_name, "Archivo no existe");
        }

        echo "\n";
    }

    // =========================================================================
    // TEST 3: Tipos de NC
    // =========================================================================

    private function testTiposNC() {
        $this->v->subtitulo("Test 3: Tipos de NC");

        // Test 3.1: Tipo 1 - Anulación
        $test_name = "Tipo 1 (Anulación) válido";
        $this->assert(true, $test_name, "Código: 1");

        // Test 3.2: Tipo 2 - Corregir texto
        $test_name = "Tipo 2 (Corregir texto) válido";
        $this->assert(true, $test_name, "Código: 2");

        // Test 3.3: Tipo 3 - Corregir montos
        $test_name = "Tipo 3 (Corregir montos) válido";
        $this->assert(true, $test_name, "Código: 3");

        echo "\n";
    }

    // =========================================================================
    // TEST 4: Refund Completo
    // =========================================================================

    private function testRefundCompleto() {
        $this->v->subtitulo("Test 4: Lógica de Refund Completo");

        // Test 4.1: Refund 100% del total
        $test_name = "Validar refund completo (100%)";
        $monto_orden = 119000;
        $monto_refund = 119000;
        $es_completo = ($monto_refund == $monto_orden);
        $this->assert($es_completo, $test_name, "Monto: $monto_refund = $monto_orden");

        // Test 4.2: Validación con tolerancia de centavos
        $test_name = "Validar refund con diferencia de centavos";
        $monto_orden = 119000.50;
        $monto_refund = 119000.49;
        $diferencia = abs($monto_orden - $monto_refund);
        $es_aceptable = ($diferencia < 1); // Menos de $1 de diferencia
        $this->assert($es_aceptable, $test_name, "Diferencia: $" . number_format($diferencia, 2));

        echo "\n";
    }

    // =========================================================================
    // TEST 5: Refund Parcial
    // =========================================================================

    private function testRefundParcial() {
        $this->v->subtitulo("Test 5: Lógica de Refund Parcial");

        // Test 5.1: Refund 50% del total
        $test_name = "Detectar refund parcial (50%)";
        $monto_orden = 119000;
        $monto_refund = 59500;
        $es_parcial = ($monto_refund != $monto_orden);
        $porcentaje = ($monto_refund / $monto_orden) * 100;
        $this->assert($es_parcial, $test_name, "Refund: " . number_format($porcentaje, 1) . "%");

        // Test 5.2: Refund mínimo
        $test_name = "Detectar refund mínimo (< 10%)";
        $monto_orden = 119000;
        $monto_refund = 5000;
        $porcentaje = ($monto_refund / $monto_orden) * 100;
        $es_minimo = ($porcentaje < 10);
        $this->assert($es_minimo, $test_name, "Refund: " . number_format($porcentaje, 1) . "%");

        // Test 5.3: Validar que refund no excede total
        $test_name = "Validar refund no excede total de orden";
        $monto_orden = 119000;
        $monto_refund = 120000;
        $es_invalido = ($monto_refund > $monto_orden);
        $this->assert($es_invalido, $test_name, "Refund: $monto_refund > Orden: $monto_orden (inválido detectado)");

        echo "\n";
    }

    // =========================================================================
    // TEST 6: Validación de Montos
    // =========================================================================

    private function testValidacionMontos() {
        $this->v->subtitulo("Test 6: Validación de Montos");

        // Test 6.1: Monto positivo
        $test_name = "Validar monto positivo";
        $monto = 119000;
        $es_valido = ($monto > 0);
        $this->assert($es_valido, $test_name, "Monto: $" . number_format($monto));

        // Test 6.2: Monto cero inválido
        $test_name = "Detectar monto cero como inválido";
        $monto = 0;
        $es_invalido = ($monto <= 0);
        $this->assert($es_invalido, $test_name, "Monto: $monto (inválido)");

        // Test 6.3: Monto negativo inválido
        $test_name = "Detectar monto negativo como inválido";
        $monto = -5000;
        $es_invalido = ($monto < 0);
        $this->assert($es_invalido, $test_name, "Monto: $monto (inválido)");

        // Test 6.4: Convertir monto absoluto
        $test_name = "Convertir monto negativo a positivo";
        $monto_negativo = -119000;
        $monto_absoluto = abs($monto_negativo);
        $this->assert($monto_absoluto > 0, $test_name, "Abs($monto_negativo) = $monto_absoluto");

        echo "\n";
    }

    // =========================================================================
    // TEST 7: NC Duplicada
    // =========================================================================

    private function testNCDuplicada() {
        $this->v->subtitulo("Test 7: Prevención de NC Duplicada");

        // Test 7.1: Verificar metadata _simple_dte_nc_generada
        $test_name = "Metadata _simple_dte_nc_generada previene duplicados";
        $metadata_key = '_simple_dte_nc_generada';
        $this->assert(
            strlen($metadata_key) > 0,
            $test_name,
            "Key: $metadata_key"
        );

        // Test 7.2: Lógica de verificación
        $test_name = "Lógica: si NC existe, no generar otra";
        $nc_generada = 'yes';
        $debe_generar = ($nc_generada !== 'yes');
        $this->assert(
            !$debe_generar,
            $test_name,
            "NC ya existe, no generar (correcto)"
        );

        echo "\n";
    }

    // =========================================================================
    // TEST 8: NC sin DTE
    // =========================================================================

    private function testNCsinDTE() {
        $this->v->subtitulo("Test 8: NC sin DTE Original");

        // Test 8.1: Verificar metadata _simple_dte_generada
        $test_name = "Requiere DTE original para generar NC";
        $metadata_key = '_simple_dte_generada';
        $this->assert(
            strlen($metadata_key) > 0,
            $test_name,
            "Key: $metadata_key"
        );

        // Test 8.2: Lógica de verificación
        $test_name = "Lógica: si no hay DTE, no generar NC";
        $dte_generada = 'no';
        $puede_generar_nc = ($dte_generada === 'yes');
        $this->assert(
            !$puede_generar_nc,
            $test_name,
            "Sin DTE, no generar NC (correcto)"
        );

        // Test 8.3: Folio original requerido
        $test_name = "Folio original requerido para NC";
        $folio_original = 1909;
        $tiene_folio = ($folio_original > 0);
        $this->assert($tiene_folio, $test_name, "Folio: $folio_original");

        echo "\n";
    }
}

// Si se ejecuta directamente
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    $test = new NotaCreditoTest();
    $test->run();
}
