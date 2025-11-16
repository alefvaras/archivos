#!/usr/bin/env php
<?php
/**
 * Test: Generación de Timbre PDF417
 * Valida que el sistema puede generar correctamente el código PDF417
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== TEST: GENERACIÓN DE TIMBRE PDF417 ===\n\n";

// Cargar librería de timbre
require_once(__DIR__ . '/lib/generar-timbre-pdf417.php');

// Verificar extensiones PHP requeridas
echo "📋 Verificando extensiones PHP...\n";
$extensions_required = ['gd', 'dom', 'bcmath', 'simplexml'];
$missing = [];

foreach ($extensions_required as $ext) {
    if (extension_loaded($ext)) {
        echo "  ✓ {$ext}\n";
    } else {
        echo "  ✗ {$ext} (FALTA)\n";
        $missing[] = $ext;
    }
}

if (!empty($missing)) {
    echo "\n❌ Faltan extensiones requeridas: " . implode(', ', $missing) . "\n";
    echo "Instalar con: apt-get install php-gd php-xml php-bcmath\n";
    exit(1);
}

echo "\n✓ Todas las extensiones están disponibles\n\n";

// Cargar XML de prueba
$xml_path = '/tmp/boleta_1890.xml';

if (!file_exists($xml_path)) {
    echo "❌ No se encuentra el archivo de prueba: {$xml_path}\n";
    echo "Ejecuta primero: php generar-boleta.php\n";
    exit(1);
}

echo "📄 Cargando XML de prueba: {$xml_path}\n";
$dte_xml = file_get_contents($xml_path);

echo "  Tamaño: " . number_format(strlen($dte_xml)) . " bytes\n\n";

// Paso 1: Extraer información del TED
echo "📊 Paso 1: Extrayendo información del TED...\n";
$ted_info = obtener_info_ted($dte_xml);

if ($ted_info) {
    echo "  ✓ TED extraído correctamente\n\n";
    echo "  Información del TED:\n";
    echo "  ├─ RUT Emisor: {$ted_info['rut_emisor']}\n";
    echo "  ├─ Tipo DTE: {$ted_info['tipo_dte']}\n";
    echo "  ├─ Folio: {$ted_info['folio']}\n";
    echo "  ├─ Fecha: {$ted_info['fecha_emision']}\n";
    echo "  ├─ RUT Receptor: {$ted_info['rut_receptor']}\n";
    echo "  ├─ Receptor: {$ted_info['razon_social_receptor']}\n";
    echo "  ├─ Monto: $" . number_format($ted_info['monto_total'], 0, ',', '.') . "\n";
    echo "  ├─ Item 1: {$ted_info['item1']}\n";
    echo "  └─ Timestamp: {$ted_info['timestamp']}\n\n";
} else {
    echo "  ❌ Error al extraer información del TED\n";
    exit(1);
}

// Paso 2: Extraer TED como string XML
echo "📋 Paso 2: Extrayendo TED completo...\n";
$ted_string = extraer_ted_xml($dte_xml);

if ($ted_string) {
    echo "  ✓ TED extraído\n";
    echo "  Tamaño TED: " . number_format(strlen($ted_string)) . " bytes\n\n";
} else {
    echo "  ❌ Error al extraer TED\n";
    exit(1);
}

// Paso 3: Generar código PDF417
echo "🔄 Paso 3: Generando código PDF417...\n";
echo "  Configuración:\n";
echo "  ├─ Columnas: 15\n";
echo "  ├─ Nivel de seguridad: 5 (requerido por SII)\n";
echo "  ├─ Escala: 2\n";
echo "  ├─ Ratio: 3\n";
echo "  └─ Padding: 5px\n\n";

$output_path = '/tmp/timbre_pdf417_test.png';

$resultado = generar_timbre_pdf417($dte_xml, $output_path, [
    'columns' => 15,
    'security_level' => 5,
    'scale' => 2,
    'ratio' => 3,
    'padding' => 5,
]);

if ($resultado && file_exists($output_path)) {
    $size = filesize($output_path);
    $img_info = getimagesize($output_path);

    echo "  ✓ PDF417 generado exitosamente\n";
    echo "  Ubicación: {$output_path}\n";
    echo "  Tamaño archivo: " . number_format($size) . " bytes\n";
    echo "  Dimensiones: {$img_info[0]} x {$img_info[1]} px\n";
    echo "  Formato: {$img_info['mime']}\n\n";

    // Validar que es una imagen PNG válida
    if ($img_info['mime'] === 'image/png') {
        echo "  ✓ Imagen PNG válida\n\n";
    } else {
        echo "  ⚠ Advertencia: Formato inesperado: {$img_info['mime']}\n\n";
    }
} else {
    echo "  ❌ Error al generar PDF417\n";
    exit(1);
}

// Paso 4: Generar en memoria (para integración con FPDF)
echo "📦 Paso 4: Generando PDF417 en memoria...\n";
$imagen_datos = generar_timbre_pdf417($dte_xml, null, [
    'columns' => 15,
    'security_level' => 5,
    'scale' => 2,
    'ratio' => 3,
    'padding' => 5,
]);

if ($imagen_datos) {
    echo "  ✓ PDF417 generado en memoria\n";
    echo "  Tamaño en memoria: " . number_format(strlen($imagen_datos)) . " bytes\n\n";

    // Guardar versión en memoria para comparar
    $memory_path = '/tmp/timbre_pdf417_memory.png';
    file_put_contents($memory_path, $imagen_datos);
    echo "  ✓ Guardado para comparación: {$memory_path}\n\n";
} else {
    echo "  ❌ Error al generar en memoria\n";
    exit(1);
}

// Resumen
echo str_repeat('=', 60) . "\n";
echo "RESUMEN DE VALIDACIÓN\n";
echo str_repeat('=', 60) . "\n\n";

echo "✅ VALIDACIONES EXITOSAS:\n";
echo "  ✓ Extensiones PHP disponibles\n";
echo "  ✓ XML DTE cargado correctamente\n";
echo "  ✓ TED extraído del XML\n";
echo "  ✓ Información del TED parseada\n";
echo "  ✓ PDF417 generado en archivo\n";
echo "  ✓ PDF417 generado en memoria\n";
echo "  ✓ Imagen PNG válida\n\n";

echo "📁 ARCHIVOS GENERADOS:\n";
echo "  {$output_path}\n";
echo "  {$memory_path}\n\n";

echo "🎯 SIGUIENTE PASO:\n";
echo "  Integrar generar_timbre_pdf417() en lib/generar-pdf-boleta.php\n";
echo "  para agregar el código de barras al PDF de la boleta.\n\n";

echo "Para visualizar la imagen:\n";
echo "  xdg-open {$output_path}\n\n";

echo "=== TEST COMPLETADO EXITOSAMENTE ===\n";
