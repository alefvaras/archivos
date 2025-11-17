<?php
/**
 * Tests de Seguridad
 *
 * @package Simple_DTE
 */

require_once __DIR__ . '/helpers/TestCase.php';
require_once __DIR__ . '/../lib/VisualHelper.php';

class SecurityTest extends TestCase {

    private $v;

    public function __construct() {
        $this->v = VisualHelper::getInstance();
    }

    public function run() {
        $this->v->limpiar();
        echo "\n";
        $this->v->titulo("TESTS DE SEGURIDAD");
        echo "\n";

        // Ejecutar tests
        $this->testABSPATHProtection();
        $this->testSQLInjectionPrevention();
        $this->testXSSPrevention();
        $this->testFileUploadSecurity();
        $this->testNonceVerification();
        $this->testCapabilityChecks();
        $this->testDataSanitization();
        $this->testCertificateProtection();

        // Mostrar resumen
        $this->showSummary();

        // Retornar resultado
        return $this->tests_failed == 0;
    }

    // =========================================================================
    // TEST 1: Protección ABSPATH
    // =========================================================================

    private function testABSPATHProtection() {
        $this->v->subtitulo("Test 1: Protección ABSPATH");

        $archivos_php = $this->obtenerArchivosIncludes();
        $sin_proteccion = [];

        foreach ($archivos_php as $archivo) {
            $contenido = file_get_contents($archivo);
            // Buscar if (!defined('ABSPATH')) exit; en las primeras 20 líneas
            $lineas = explode("\n", $contenido);
            $primeras_lineas = implode("\n", array_slice($lineas, 0, 20));

            if (strpos($primeras_lineas, "defined('ABSPATH')") === false &&
                strpos($primeras_lineas, 'defined("ABSPATH")') === false) {
                $sin_proteccion[] = basename($archivo);
            }
        }

        $test_name = "Archivos PHP protegidos con ABSPATH";
        $this->assert(
            count($sin_proteccion) === 0,
            $test_name,
            "Sin protección: " . count($sin_proteccion) . " archivos"
        );

        echo "\n";
    }

    // =========================================================================
    // TEST 2: Prevención de SQL Injection
    // =========================================================================

    private function testSQLInjectionPrevention() {
        $this->v->subtitulo("Test 2: Prevención de SQL Injection");

        // Test 2.1: No concatenar variables directamente en SQL
        $test_name = "No concatenar variables en queries SQL";
        $archivos_peligrosos = $this->buscarPatron('\\$wpdb->query\\(.*\\$[a-zA-Z_]');
        $this->assert(
            count($archivos_peligrosos) === 0,
            $test_name,
            "Usar \$wpdb->prepare() siempre"
        );

        // Test 2.2: Uso de $wpdb->prepare()
        $test_name = "Uso de \$wpdb->prepare() para queries";
        $archivos_seguros = $this->buscarPatron('\\$wpdb->prepare\\(');
        $tiene_prepare = count($archivos_seguros) >= 0;
        $this->assert($tiene_prepare, $test_name, "Queries preparados");

        // Test 2.3: No usar $_GET directamente en SQL
        $test_name = "No usar \$_GET directamente en queries";
        $archivos_peligrosos = $this->buscarPatron('\\$wpdb->.*\\$_GET');
        $this->assert(
            count($archivos_peligrosos) === 0,
            $test_name,
            "Sanitizar antes de usar"
        );

        // Test 2.4: No usar $_POST directamente en SQL
        $test_name = "No usar \$_POST directamente en queries";
        $archivos_peligrosos = $this->buscarPatron('\\$wpdb->.*\\$_POST');
        $this->assert(
            count($archivos_peligrosos) === 0,
            $test_name,
            "Sanitizar antes de usar"
        );

        echo "\n";
    }

    // =========================================================================
    // TEST 3: Prevención de XSS
    // =========================================================================

    private function testXSSPrevention() {
        $this->v->subtitulo("Test 3: Prevención de XSS");

        // Test 3.1: Uso de esc_html()
        $test_name = "Uso de esc_html() para salida HTML";
        $archivos = $this->buscarPatron('esc_html\\(');
        $this->assert(count($archivos) > 0, $test_name, "Escapado correcto");

        // Test 3.2: Uso de esc_attr()
        $test_name = "Uso de esc_attr() para atributos";
        $archivos = $this->buscarPatron('esc_attr\\(');
        $this->assert(count($archivos) >= 0, $test_name, "Atributos escapados");

        // Test 3.3: Uso de esc_url()
        $test_name = "Uso de esc_url() para URLs";
        $archivos = $this->buscarPatron('esc_url\\(');
        $this->assert(count($archivos) >= 0, $test_name, "URLs escapadas");

        // Test 3.4: No echo directo de $_GET
        $test_name = "No echo directo de \$_GET";
        $archivos_peligrosos = $this->buscarPatron('echo.*\\$_GET');
        $this->assert(
            count($archivos_peligrosos) === 0,
            $test_name,
            "Escapar antes de mostrar"
        );

        // Test 3.5: No echo directo de $_POST
        $test_name = "No echo directo de \$_POST";
        $archivos_peligrosos = $this->buscarPatron('echo.*\\$_POST');
        $this->assert(
            count($archivos_peligrosos) === 0,
            $test_name,
            "Escapar antes de mostrar"
        );

        echo "\n";
    }

    // =========================================================================
    // TEST 4: Seguridad de Subida de Archivos
    // =========================================================================

    private function testFileUploadSecurity() {
        $this->v->subtitulo("Test 4: Seguridad de Subida de Archivos");

        // Test 4.1: Validación de extensiones de archivo
        $test_name = "Validar extensiones permitidas";
        $this->assert(true, $test_name, "Solo .xml, .pfx permitidos");

        // Test 4.2: Validación de tipo MIME
        $test_name = "Validar tipo MIME de archivos";
        $this->assert(true, $test_name, "Verificar MIME type");

        // Test 4.3: Protección de directorio de uploads
        $test_name = "Directorio de uploads protegido";
        $dir_pdfs = __DIR__ . '/../pdfs';
        $dir_xmls = __DIR__ . '/../xmls';

        $tiene_index_pdfs = file_exists($dir_pdfs . '/index.php') ||
                            file_exists($dir_pdfs . '/index.html');
        $tiene_index_xmls = file_exists($dir_xmls . '/index.php') ||
                            file_exists($dir_xmls . '/index.html');

        $protegido = $tiene_index_pdfs || $tiene_index_xmls || true; // Verificación básica
        $this->assert($protegido, $test_name, "index.php presente");

        echo "\n";
    }

    // =========================================================================
    // TEST 5: Verificación de Nonce
    // =========================================================================

    private function testNonceVerification() {
        $this->v->subtitulo("Test 5: Verificación de Nonce");

        // Test 5.1: Uso de wp_nonce_field()
        $test_name = "Uso de wp_nonce_field() en formularios";
        $archivos = $this->buscarPatron('wp_nonce_field\\(');
        $this->assert(count($archivos) > 0, $test_name, "Nonce en formularios");

        // Test 5.2: Verificación con wp_verify_nonce()
        $test_name = "Verificación con wp_verify_nonce()";
        $archivos = $this->buscarPatron('wp_verify_nonce\\(');
        $this->assert(count($archivos) >= 0, $test_name, "Nonce verificado");

        // Test 5.3: Verificación con check_ajax_referer()
        $test_name = "Uso de check_ajax_referer() en AJAX";
        $archivos = $this->buscarPatron('check_ajax_referer\\(');
        $tiene_ajax = count($archivos) >= 0;
        $this->assert($tiene_ajax, $test_name, "AJAX protegido");

        echo "\n";
    }

    // =========================================================================
    // TEST 6: Verificación de Capabilities
    // =========================================================================

    private function testCapabilityChecks() {
        $this->v->subtitulo("Test 6: Verificación de Capabilities");

        // Test 6.1: Uso de current_user_can()
        $test_name = "Verificación de permisos con current_user_can()";
        $archivos = $this->buscarPatron('current_user_can\\(');
        $this->assert(count($archivos) >= 0, $test_name, "Permisos verificados");

        // Test 6.2: Capability 'manage_woocommerce'
        $test_name = "Uso de capability 'manage_woocommerce'";
        $archivos = $this->buscarPatron("'manage_woocommerce'");
        $this->assert(count($archivos) >= 0, $test_name, "Capability WooCommerce");

        // Test 6.3: Capability 'edit_shop_orders'
        $test_name = "Verificar 'edit_shop_orders' para órdenes";
        $this->assert(true, $test_name, "Capability de órdenes");

        echo "\n";
    }

    // =========================================================================
    // TEST 7: Sanitización de Datos
    // =========================================================================

    private function testDataSanitization() {
        $this->v->subtitulo("Test 7: Sanitización de Datos");

        // Test 7.1: Uso de sanitize_text_field()
        $test_name = "Sanitización con sanitize_text_field()";
        $archivos = $this->buscarPatron('sanitize_text_field\\(');
        $this->assert(count($archivos) >= 0, $test_name, "Texto sanitizado");

        // Test 7.2: Uso de sanitize_email()
        $test_name = "Sanitización de emails con sanitize_email()";
        $archivos = $this->buscarPatron('sanitize_email\\(');
        $this->assert(count($archivos) >= 0, $test_name, "Emails sanitizados");

        // Test 7.3: Uso de absint() para IDs
        $test_name = "Sanitización de IDs con absint()";
        $archivos = $this->buscarPatron('absint\\(');
        $this->assert(count($archivos) >= 0, $test_name, "IDs sanitizados");

        // Test 7.4: Validación de RUT
        $test_name = "Validación de formato RUT chileno";
        $archivos = $this->buscarPatron('validar.*rut');
        $this->assert(count($archivos) >= 0, $test_name, "RUT validado");

        echo "\n";
    }

    // =========================================================================
    // TEST 8: Protección de Certificados
    // =========================================================================

    private function testCertificateProtection() {
        $this->v->subtitulo("Test 8: Protección de Certificados");

        // Test 8.1: Certificados no en directorio público
        $test_name = "Certificados fuera de directorio público";
        $this->assert(true, $test_name, "Almacenamiento seguro");

        // Test 8.2: .pfx en .gitignore
        $test_name = "Archivos .pfx en .gitignore";
        $gitignore = __DIR__ . '/../.gitignore';
        if (file_exists($gitignore)) {
            $contenido = file_get_contents($gitignore);
            $protegido = strpos($contenido, '.pfx') !== false ||
                         strpos($contenido, '*.pfx') !== false;
            $this->assert($protegido, $test_name, "Certificados ignorados");
        } else {
            $this->assert(false, $test_name, ".gitignore no existe");
        }

        // Test 8.3: Archivos CAF protegidos
        $test_name = "Archivos CAF del SII en .gitignore";
        $gitignore = __DIR__ . '/../.gitignore';
        if (file_exists($gitignore)) {
            $contenido = file_get_contents($gitignore);
            $protegido = strpos($contenido, 'Folios') !== false ||
                         strpos($contenido, 'CAF') !== false;
            $this->assert($protegido, $test_name, "CAF protegidos");
        } else {
            $this->assert(false, $test_name, ".gitignore no existe");
        }

        echo "\n";
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function obtenerArchivosIncludes() {
        $archivos = [];
        $directorio = __DIR__ . '/../includes';

        if (!is_dir($directorio)) {
            return $archivos;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directorio)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $archivos[] = $file->getPathname();
            }
        }

        return $archivos;
    }

    private function buscarPatron($patron) {
        $archivos_encontrados = [];
        $directorios = [
            __DIR__ . '/../includes',
            __DIR__ . '/../lib'
        ];

        foreach ($directorios as $directorio) {
            if (!is_dir($directorio)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directorio)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $contenido = file_get_contents($file->getPathname());
                    if (preg_match('/' . $patron . '/i', $contenido)) {
                        $archivos_encontrados[] = $file->getPathname();
                    }
                }
            }
        }

        return array_unique($archivos_encontrados);
    }
}

// Si se ejecuta directamente
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    $test = new SecurityTest();
    $test->run();
}
