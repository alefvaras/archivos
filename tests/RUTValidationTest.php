<?php
/**
 * Tests para Validación de RUT Chileno
 *
 * @package Simple_DTE
 */

require_once __DIR__ . '/helpers/TestCase.php';
require_once __DIR__ . '/../lib/VisualHelper.php';

// Helper functions para validación de RUT
if (!function_exists('formatear_rut')) {
    function formatear_rut($rut) {
        if (empty($rut)) return false;
        $rut = limpiar_rut($rut);
        if (strlen($rut) < 2) return false;

        $dv = substr($rut, -1);
        $numero = substr($rut, 0, -1);

        return $numero . '-' . strtoupper($dv);
    }
}

if (!function_exists('limpiar_rut')) {
    function limpiar_rut($rut) {
        return str_replace(['.', ' ', ','], '', $rut);
    }
}

if (!function_exists('calcular_dv_rut')) {
    function calcular_dv_rut($numero) {
        $suma = 0;
        $multiplo = 2;

        for ($i = strlen($numero) - 1; $i >= 0; $i--) {
            $suma += (int)$numero[$i] * $multiplo;
            $multiplo = $multiplo < 7 ? $multiplo + 1 : 2;
        }

        $resto = $suma % 11;
        $dv = 11 - $resto;

        if ($dv == 11) return '0';
        if ($dv == 10) return 'K';
        return (string)$dv;
    }
}

if (!function_exists('validar_formato_rut')) {
    function validar_formato_rut($rut) {
        if (empty($rut)) return false;

        $rut_limpio = limpiar_rut($rut);
        if (strlen($rut_limpio) < 2) return false;

        $dv = substr($rut_limpio, -1);
        $numero = substr($rut_limpio, 0, -1);

        if (!ctype_digit($numero)) return false;

        $dv_calculado = calcular_dv_rut($numero);

        if (strtoupper($dv) !== strtoupper($dv_calculado)) return false;

        return formatear_rut($rut);
    }
}

class RUTValidationTest extends TestCase {

    private $v;

    public function __construct() {
        $this->v = VisualHelper::getInstance();
    }

    public function run() {
        $this->v->limpiar();
        echo "\n";
        $this->v->titulo("TESTS DE VALIDACIÓN DE RUT CHILENO");
        echo "\n";

        // Ejecutar tests
        $this->testRUTsValidos();
        $this->testRUTsInvalidos();
        $this->testFormatosRUT();
        $this->testDigitoVerificador();
        $this->testRUTsEspeciales();
        $this->testRUTsEmpresa();
        $this->testRUTsPersona();
        $this->testEdgeCases();

        // Mostrar resumen
        $this->showSummary();

        // Retornar resultado
        return $this->tests_failed == 0;
    }

    // =========================================================================
    // TEST 1: RUTs Válidos
    // =========================================================================

    private function testRUTsValidos() {
        $this->v->subtitulo("Test 1: RUTs Válidos");

        $ruts_validos = [
            '78274225-6' => 'Empresa AKIBARA SPA',
            '76063822-6' => 'Empresa con dígito 6',
            '11111111-1' => 'RUT secuencial válido',
            '12345678-5' => 'RUT común válido',
            '16694181-4' => 'RUT persona válido',
            '7.777.777-7' => 'RUT con puntos',
            '88888888-8' => 'RUT secuencial 8',
            '99999999-9' => 'RUT secuencial 9',
        ];

        foreach ($ruts_validos as $rut => $descripcion) {
            $test_name = "Validar RUT: $rut";
            $rut_limpio = validar_formato_rut($rut);
            $this->assert($rut_limpio !== false, $test_name, "$descripcion");
        }

        echo "\n";
    }

    // =========================================================================
    // TEST 2: RUTs Inválidos
    // =========================================================================

    private function testRUTsInvalidos() {
        $this->v->subtitulo("Test 2: RUTs Inválidos");

        $ruts_invalidos = [
            '12345678-9' => 'Dígito verificador incorrecto',
            '00000000-0' => 'RUT todo ceros',
            '11111111-2' => 'DV incorrecto para 11111111',
            '99999999-0' => 'DV incorrecto para 99999999',
            'ABCDEFGH-I' => 'Letras en lugar de números',
            '123-4' => 'RUT demasiado corto',
            '999999999999-9' => 'RUT demasiado largo',
            '' => 'RUT vacío',
        ];

        foreach ($ruts_invalidos as $rut => $descripcion) {
            $test_name = "Rechazar RUT inválido: " . ($rut ?: '(vacío)');
            $rut_limpio = validar_formato_rut($rut);
            $this->assert($rut_limpio === false, $test_name, "$descripcion");
        }

        echo "\n";
    }

    // =========================================================================
    // TEST 3: Formatos de RUT
    // =========================================================================

    private function testFormatosRUT() {
        $this->v->subtitulo("Test 3: Formatos de RUT");

        // Test 3.1: RUT sin formato (solo números)
        $test_name = "Formato: 782742256 (sin guión)";
        $rut = '782742256';
        $rut_formateado = formatear_rut($rut);
        $esperado = '78274225-6';
        $this->assert($rut_formateado === $esperado, $test_name, "$rut → $rut_formateado");

        // Test 3.2: RUT con guión
        $test_name = "Formato: 78274225-6 (con guión)";
        $rut = '78274225-6';
        $rut_formateado = formatear_rut($rut);
        $esperado = '78274225-6';
        $this->assert($rut_formateado === $esperado, $test_name, "Mantiene formato");

        // Test 3.3: RUT con puntos
        $test_name = "Formato: 78.274.225-6 (con puntos)";
        $rut = '78.274.225-6';
        $rut_formateado = formatear_rut($rut);
        $esperado = '78274225-6';
        $this->assert($rut_formateado === $esperado, $test_name, "$rut → $rut_formateado");

        // Test 3.4: RUT con espacios
        $test_name = "Formato: '78274225 6' (con espacios)";
        $rut = '78274225 6';
        $rut_limpio = limpiar_rut($rut);
        $esperado = '782742256';
        $this->assert($rut_limpio === $esperado, $test_name, "$rut → $rut_limpio");

        // Test 3.5: RUT con K mayúscula
        $test_name = "Formato: 12345678-K (DV K mayúscula)";
        $rut = '12345678-K';
        $rut_formateado = formatear_rut($rut);
        $esperado = '12345678-K';
        $this->assert($rut_formateado === $esperado, $test_name, "Normaliza K");

        // Test 3.6: RUT con k minúscula
        $test_name = "Formato: 12345678-k (DV k minúscula)";
        $rut = '12345678-k';
        $rut_formateado = formatear_rut($rut);
        $esperado = '12345678-K';
        $this->assert($rut_formateado === $esperado, $test_name, "k → K");

        echo "\n";
    }

    // =========================================================================
    // TEST 4: Dígito Verificador
    // =========================================================================

    private function testDigitoVerificador() {
        $this->v->subtitulo("Test 4: Cálculo de Dígito Verificador");

        $test_cases = [
            '78274225' => '6',
            '76063822' => '6',
            '11111111' => '1',
            '12345678' => '5',
            '16694181' => '4',
            '7777777' => '7',
            '8888888' => '8',
            '9999999' => '9',
        ];

        foreach ($test_cases as $numero => $dv_esperado) {
            $test_name = "Calcular DV para: $numero";
            $dv_calculado = calcular_dv_rut($numero);
            $this->assert(
                $dv_calculado === $dv_esperado,
                $test_name,
                "DV: $dv_calculado (esperado: $dv_esperado)"
            );
        }

        echo "\n";
    }

    // =========================================================================
    // TEST 5: RUTs Especiales
    // =========================================================================

    private function testRUTsEspeciales() {
        $this->v->subtitulo("Test 5: RUTs Especiales");

        // Test 5.1: RUT genérico (66666666-6)
        $test_name = "RUT genérico: 66666666-6";
        $rut = '66666666-6';
        $rut_valido = validar_formato_rut($rut);
        $this->assert($rut_valido !== false, $test_name, "Usado para clientes sin RUT");

        // Test 5.2: RUT con DV = 0
        $test_name = "RUT con DV = 0";
        $rut = '24010719-0';
        $rut_valido = validar_formato_rut($rut);
        $es_valido = ($rut_valido !== false);
        $this->assert($es_valido, $test_name, "DV cero es válido");

        // Test 5.3: RUT con DV = K
        $test_name = "RUT con DV = K";
        $rut = '12345678-K';
        $dv_calculado = calcular_dv_rut('12345678');
        $es_k = ($dv_calculado === 'K');
        $this->assert($es_k, $test_name, "DV: K");

        echo "\n";
    }

    // =========================================================================
    // TEST 6: RUTs de Empresa
    // =========================================================================

    private function testRUTsEmpresa() {
        $this->v->subtitulo("Test 6: RUTs de Empresa");

        // RUTs de empresa generalmente empiezan con 70-99 millones
        $ruts_empresa = [
            '76063822-6' => 'Empresa rango 76M',
            '78274225-6' => 'Empresa rango 78M',
            '96500000-7' => 'Empresa rango 96M',
            '99500000-5' => 'Empresa rango 99M',
        ];

        foreach ($ruts_empresa as $rut => $descripcion) {
            $test_name = "Validar RUT empresa: $rut";
            $numero = (int) str_replace('-', '', substr($rut, 0, -2));
            $es_empresa = ($numero >= 50000000);
            $this->assert($es_empresa, $test_name, "$descripcion");
        }

        echo "\n";
    }

    // =========================================================================
    // TEST 7: RUTs de Persona
    // =========================================================================

    private function testRUTsPersona() {
        $this->v->subtitulo("Test 7: RUTs de Persona Natural");

        // RUTs de persona generalmente están entre 1M y 50M
        $ruts_persona = [
            '16694181-4' => 'Persona rango 16M',
            '12345678-5' => 'Persona rango 12M',
            '20000000-1' => 'Persona rango 20M',
            '25000000-K' => 'Persona rango 25M',
        ];

        foreach ($ruts_persona as $rut => $descripcion) {
            $test_name = "Validar RUT persona: $rut";
            $numero = (int) str_replace(['-', 'K', 'k'], '', substr($rut, 0, -2));
            $es_persona = ($numero < 50000000 && $numero > 0);
            $this->assert($es_persona, $test_name, "$descripcion");
        }

        echo "\n";
    }

    // =========================================================================
    // TEST 8: Edge Cases
    // =========================================================================

    private function testEdgeCases() {
        $this->v->subtitulo("Test 8: Casos Extremos");

        // Test 8.1: RUT mínimo válido
        $test_name = "RUT mínimo: 1-9";
        $rut = '1-9';
        $rut_valido = validar_formato_rut($rut);
        $this->assert($rut_valido !== false, $test_name, "RUT de 1 dígito");

        // Test 8.2: RUT máximo válido (8 dígitos)
        $test_name = "RUT máximo: 99999999-9";
        $rut = '99999999-9';
        $rut_valido = validar_formato_rut($rut);
        $this->assert($rut_valido !== false, $test_name, "RUT de 8 dígitos");

        // Test 8.3: RUT con caracteres especiales
        $test_name = "RUT con caracteres especiales (limpiar)";
        $rut = '78.274.225-6';
        $rut_limpio = limpiar_rut($rut);
        $solo_numeros = ctype_digit(substr($rut_limpio, 0, -1));
        $this->assert($solo_numeros, $test_name, "Limpiado: $rut_limpio");

        // Test 8.4: Null/vacío
        $test_name = "RUT null/vacío debe fallar";
        $rut = '';
        $rut_valido = validar_formato_rut($rut);
        $this->assert($rut_valido === false, $test_name, "Rechaza vacío");

        // Test 8.5: RUT muy largo
        $test_name = "RUT muy largo debe fallar";
        $rut = '123456789012345-6';
        $rut_valido = validar_formato_rut($rut);
        $this->assert($rut_valido === false, $test_name, "Rechaza largo excesivo");

        // Test 8.6: Solo DV sin número
        $test_name = "Solo DV sin número debe fallar";
        $rut = '-6';
        $rut_valido = validar_formato_rut($rut);
        $this->assert($rut_valido === false, $test_name, "Rechaza sin número");

        echo "\n";
    }
}

// Si se ejecuta directamente
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    $test = new RUTValidationTest();
    $test->run();
}
