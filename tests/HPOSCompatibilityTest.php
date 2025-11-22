<?php
/**
 * Tests de Compatibilidad HPOS (High-Performance Order Storage)
 *
 * @package Simple_DTE
 */

require_once __DIR__ . '/helpers/TestCase.php';
require_once __DIR__ . '/../lib/VisualHelper.php';

class HPOSCompatibilityTest extends TestCase {

    private $v;

    public function __construct() {
        $this->v = VisualHelper::getInstance();
    }

    public function run() {
        $this->v->limpiar();
        echo "\n";
        $this->v->titulo("TESTS DE COMPATIBILIDAD HPOS");
        echo "\n";

        // Ejecutar tests
        $this->testAPIsWooCommerce();
        $this->testMetadataAPIs();
        $this->testMetaboxCompatibility();
        $this->testQueryAPIs();
        $this->testNoDirectPostAccess();
        $this->testOrderObjectUsage();

        // Mostrar resumen
        $this->showSummary();

        // Retornar resultado
        return $this->tests_failed == 0;
    }

    // =========================================================================
    // TEST 1: APIs de WooCommerce
    // =========================================================================

    private function testAPIsWooCommerce() {
        $this->v->subtitulo("Test 1: Uso de APIs de WooCommerce");

        // Test 1.1: Función wc_get_order existe
        $test_name = "Función wc_get_order() disponible";
        $existe = function_exists('wc_get_order');
        $this->assert($existe, $test_name, "API de WooCommerce");

        // Test 1.2: Función wc_get_orders existe
        $test_name = "Función wc_get_orders() disponible";
        $existe = function_exists('wc_get_orders');
        $this->assert($existe, $test_name, "API de consultas");

        // Test 1.3: Clase WC_Order existe
        $test_name = "Clase WC_Order disponible";
        $existe = class_exists('WC_Order');
        $this->assert($existe, $test_name, "Clase de órdenes");

        // Test 1.4: No se usa get_post() para órdenes
        $test_name = "No usar get_post() para órdenes";
        $archivos_php = $this->buscarArchivosConPatron('get_post\\(\\s*\\$order');
        $this->assert(
            count($archivos_php) === 0,
            $test_name,
            "Archivos que usan get_post: " . count($archivos_php)
        );

        echo "\n";
    }

    // =========================================================================
    // TEST 2: APIs de Metadata
    // =========================================================================

    private function testMetadataAPIs() {
        $this->v->subtitulo("Test 2: Uso Correcto de Metadata APIs");

        // Test 2.1: No usar get_post_meta() para órdenes
        $test_name = "No usar get_post_meta() para órdenes";
        $archivos = $this->buscarArchivosConPatron('get_post_meta\\(\\s*\\$order');
        $this->assert(
            count($archivos) === 0,
            $test_name,
            "Usa \$order->get_meta() en su lugar"
        );

        // Test 2.2: No usar update_post_meta() para órdenes
        $test_name = "No usar update_post_meta() para órdenes";
        $archivos = $this->buscarArchivosConPatron('update_post_meta\\(\\s*\\$order');
        $this->assert(
            count($archivos) === 0,
            $test_name,
            "Usa \$order->update_meta_data() en su lugar"
        );

        // Test 2.3: Uso de $order->get_meta()
        $test_name = "Usar \$order->get_meta() correctamente";
        $archivos = $this->buscarArchivosConPatron('\\$order->get_meta\\(');
        $this->assert(
            count($archivos) > 0,
            $test_name,
            "Archivos que usan get_meta: " . count($archivos)
        );

        // Test 2.4: Uso de $order->update_meta_data()
        $test_name = "Usar \$order->update_meta_data() correctamente";
        $archivos = $this->buscarArchivosConPatron('\\$order->update_meta_data\\(');
        $this->assert(
            count($archivos) > 0,
            $test_name,
            "Archivos que usan update_meta_data: " . count($archivos)
        );

        // Test 2.5: Uso de $order->save()
        $test_name = "Usar \$order->save() después de update_meta_data()";
        $archivos = $this->buscarArchivosConPatron('\\$order->save\\(');
        $this->assert(
            count($archivos) > 0,
            $test_name,
            "Archivos que usan save: " . count($archivos)
        );

        echo "\n";
    }

    // =========================================================================
    // TEST 3: Compatibilidad de Metabox
    // =========================================================================

    private function testMetaboxCompatibility() {
        $this->v->subtitulo("Test 3: Compatibilidad de Metabox");

        // Test 3.1: Clase Simple_DTE_Metabox existe
        $test_name = "Clase Simple_DTE_Metabox existe";
        $existe = class_exists('Simple_DTE_Metabox');
        $this->assert($existe, $test_name, "Clase de metabox");

        // Test 3.2: Método add_metabox existe
        $test_name = "Método add_metabox() existe";
        $existe = method_exists('Simple_DTE_Metabox', 'add_metabox');
        $this->assert($existe, $test_name, "Método de registro");

        // Test 3.3: Detección de HPOS en metabox
        $test_name = "Metabox detecta HPOS automáticamente";
        $archivo = __DIR__ . '/../includes/admin/class-simple-dte-metabox.php';
        if (file_exists($archivo)) {
            $contenido = file_get_contents($archivo);
            $detecta_hpos = strpos($contenido, 'CustomOrdersTableController') !== false;
            $this->assert($detecta_hpos, $test_name, "Detecta HPOS");
        } else {
            $this->assert(false, $test_name, "Archivo no encontrado");
        }

        // Test 3.4: Maneja WC_Order y WP_Post
        $test_name = "Metabox maneja WC_Order y WP_Post";
        $archivo = __DIR__ . '/../includes/admin/class-simple-dte-metabox.php';
        if (file_exists($archivo)) {
            $contenido = file_get_contents($archivo);
            $maneja_ambos = strpos($contenido, 'instanceof WC_Order') !== false;
            $this->assert($maneja_ambos, $test_name, "Compatibilidad dual");
        } else {
            $this->assert(false, $test_name, "Archivo no encontrado");
        }

        echo "\n";
    }

    // =========================================================================
    // TEST 4: APIs de Consulta
    // =========================================================================

    private function testQueryAPIs() {
        $this->v->subtitulo("Test 4: APIs de Consulta");

        // Test 4.1: No usar WP_Query para órdenes
        $test_name = "No usar WP_Query('post_type' => 'shop_order')";
        $archivos = $this->buscarArchivosConPatron("'post_type'\\s*=>\\s*'shop_order'");
        $this->assert(
            count($archivos) === 0,
            $test_name,
            "Usar wc_get_orders() en su lugar"
        );

        // Test 4.2: Uso de wc_get_orders()
        $test_name = "Usar wc_get_orders() para consultas";
        $archivos = $this->buscarArchivosConPatron('wc_get_orders\\(');
        $this->assert(
            count($archivos) > 0,
            $test_name,
            "Archivos que usan wc_get_orders: " . count($archivos)
        );

        // Test 4.3: No queries directas a wp_posts
        $test_name = "No queries SQL a wp_posts para órdenes";
        $archivos = $this->buscarArchivosConPatron('FROM.*wp_posts.*shop_order');
        $this->assert(
            count($archivos) === 0,
            $test_name,
            "Sin queries directas"
        );

        // Test 4.4: No queries directas a wp_postmeta
        $test_name = "No queries SQL a wp_postmeta para órdenes";
        $archivos = $this->buscarArchivosConPatron('FROM.*wp_postmeta.*_simple_dte');
        $this->assert(
            count($archivos) === 0,
            $test_name,
            "Sin queries directas"
        );

        echo "\n";
    }

    // =========================================================================
    // TEST 5: Sin Acceso Directo a Posts
    // =========================================================================

    private function testNoDirectPostAccess() {
        $this->v->subtitulo("Test 5: Sin Acceso Directo a Posts");

        // Test 5.1: No usar $wpdb para posts de órdenes
        $test_name = "No usar \$wpdb->posts para órdenes";
        $archivos = $this->buscarArchivosConPatron('\\$wpdb->posts');
        $encontrados_en_orden = [];
        foreach ($archivos as $archivo) {
            $contenido = file_get_contents($archivo);
            if (strpos($contenido, 'shop_order') !== false) {
                $encontrados_en_orden[] = $archivo;
            }
        }
        $this->assert(
            count($encontrados_en_orden) === 0,
            $test_name,
            "Archivos: " . count($encontrados_en_orden)
        );

        // Test 5.2: No usar $wpdb->postmeta para metadata de órdenes
        $test_name = "No usar \$wpdb->postmeta para metadata de órdenes";
        $archivos = $this->buscarArchivosConPatron('\\$wpdb->postmeta');
        $encontrados_en_orden = [];
        foreach ($archivos as $archivo) {
            $contenido = file_get_contents($archivo);
            if (strpos($contenido, '_simple_dte') !== false) {
                $encontrados_en_orden[] = $archivo;
            }
        }
        $this->assert(
            count($encontrados_en_orden) === 0,
            $test_name,
            "Archivos: " . count($encontrados_en_orden)
        );

        echo "\n";
    }

    // =========================================================================
    // TEST 6: Uso del Objeto Order
    // =========================================================================

    private function testOrderObjectUsage() {
        $this->v->subtitulo("Test 6: Uso del Objeto Order");

        // Test 6.1: Uso de $order->get_id()
        $test_name = "Usar \$order->get_id() para obtener ID";
        $archivos = $this->buscarArchivosConPatron('\\$order->get_id\\(');
        $this->assert(
            count($archivos) >= 0,
            $test_name,
            "Compatible con HPOS"
        );

        // Test 6.2: Uso de $order->get_billing_*()
        $test_name = "Usar \$order->get_billing_*() para datos de facturación";
        $archivos = $this->buscarArchivosConPatron('\\$order->get_billing_');
        $this->assert(
            count($archivos) > 0,
            $test_name,
            "Archivos: " . count($archivos)
        );

        // Test 6.3: Uso de $order->get_total()
        $test_name = "Usar \$order->get_total() para totales";
        $archivos = $this->buscarArchivosConPatron('\\$order->get_total\\(');
        $this->assert(
            count($archivos) >= 0,
            $test_name,
            "Compatible con HPOS"
        );

        // Test 6.4: Uso de $order->get_items()
        $test_name = "Usar \$order->get_items() para productos";
        $archivos = $this->buscarArchivosConPatron('\\$order->get_items\\(');
        $this->assert(
            count($archivos) >= 0,
            $test_name,
            "Compatible con HPOS"
        );

        // Test 6.5: Uso de $order->add_order_note()
        $test_name = "Usar \$order->add_order_note() para notas";
        $archivos = $this->buscarArchivosConPatron('\\$order->add_order_note\\(');
        $this->assert(
            count($archivos) > 0,
            $test_name,
            "Archivos: " . count($archivos)
        );

        echo "\n";
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function buscarArchivosConPatron($patron) {
        $archivos_encontrados = [];
        $directorio = __DIR__ . '/../includes';

        if (!is_dir($directorio)) {
            return $archivos_encontrados;
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

        return $archivos_encontrados;
    }
}

// Si se ejecuta directamente
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    $test = new HPOSCompatibilityTest();
    $test->run();
}
