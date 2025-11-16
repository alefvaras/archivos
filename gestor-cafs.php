#!/usr/bin/env php
<?php
/**
 * Gestor de Archivos CAF
 * Permite cambiar automáticamente entre múltiples archivos CAF
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== GESTOR DE ARCHIVOS CAF ===\n\n";

// Directorio de trabajo
$directorio = __DIR__;

// Buscar todos los archivos CAF
$caf_files = glob($directorio . '/FoliosSII*.xml');

if (empty($caf_files)) {
    die("❌ No se encontraron archivos CAF en el directorio\n");
}

echo "📋 Archivos CAF encontrados:\n\n";

$cafs_info = [];
foreach ($caf_files as $idx => $file) {
    $contenido = file_get_contents($file);
    $xml = simplexml_load_string($contenido);

    if (!$xml) {
        echo "  ⚠️  Error al parsear: " . basename($file) . "\n";
        continue;
    }

    $da = $xml->CAF->DA;
    $folio_desde = (int) ((string) $da->RNG->D);
    $folio_hasta = (int) ((string) $da->RNG->H);
    $tipo_dte = (int) ((string) $da->TD);
    $fecha = (string) $da->FA;

    $cafs_info[] = [
        'file' => $file,
        'nombre' => basename($file),
        'tipo' => $tipo_dte,
        'desde' => $folio_desde,
        'hasta' => $folio_hasta,
        'total' => $folio_hasta - $folio_desde + 1,
        'fecha' => $fecha
    ];

    echo "  " . ($idx + 1) . ". " . basename($file) . "\n";
    echo "     Tipo DTE: {$tipo_dte}\n";
    echo "     Folios: {$folio_desde} - {$folio_hasta} ({$cafs_info[$idx]['total']} folios)\n";
    echo "     Fecha: {$fecha}\n\n";
}

// Leer folios usados
$control_file = $directorio . '/folios_usados.txt';
$ultimo_folio = file_exists($control_file) ? (int) file_get_contents($control_file) : 0;

echo "📊 Estado actual:\n";
echo "   Último folio usado: {$ultimo_folio}\n\n";

// Determinar qué CAF usar
$caf_recomendado = null;
foreach ($cafs_info as $idx => $caf) {
    if ($ultimo_folio >= $caf['desde'] && $ultimo_folio <= $caf['hasta']) {
        $folios_restantes = $caf['hasta'] - $ultimo_folio;
        echo "✅ CAF ACTUAL: {$caf['nombre']}\n";
        echo "   Folios restantes: {$folios_restantes}\n\n";

        if ($folios_restantes < 10) {
            echo "⚠️  ADVERTENCIA: Quedan menos de 10 folios en el CAF actual\n\n";
        }
    }

    if ($ultimo_folio < $caf['desde']) {
        if ($caf_recomendado === null) {
            $caf_recomendado = $caf;
        }
    }
}

// Sugerir siguiente CAF si existe
if ($caf_recomendado) {
    echo "💡 Siguiente CAF disponible: {$caf_recomendado['nombre']}\n";
    echo "   Folios: {$caf_recomendado['desde']} - {$caf_recomendado['hasta']}\n";
    echo "   Total: {$caf_recomendado['total']} folios\n\n";

    echo "Para cambiar al siguiente CAF automáticamente, actualice:\n";
    echo "   define('CAF_PATH', __DIR__ . '/{$caf_recomendado['nombre']}');\n\n";
}

// Opciones de gestión
echo "=== OPCIONES DE GESTIÓN ===\n\n";
echo "1. Mostrar información detallada de un CAF\n";
echo "2. Cambiar CAF actual\n";
echo "3. Resetear contador de folios\n";
echo "4. Listar CAFs por fecha\n";
echo "0. Salir\n\n";

if (php_sapi_name() === 'cli') {
    echo "Opción: ";
    $opcion = trim(fgets(STDIN));

    switch ($opcion) {
        case '1':
            echo "\nIngrese el número del CAF (1-" . count($cafs_info) . "): ";
            $num = (int) trim(fgets(STDIN));

            if ($num > 0 && $num <= count($cafs_info)) {
                $caf = $cafs_info[$num - 1];
                echo "\n=== INFORMACIÓN DETALLADA ===\n";
                echo "Archivo: {$caf['nombre']}\n";
                echo "Tipo DTE: {$caf['tipo']}\n";
                echo "Rango: {$caf['desde']} - {$caf['hasta']}\n";
                echo "Total folios: {$caf['total']}\n";
                echo "Fecha autorización: {$caf['fecha']}\n";
                echo "Ruta completa: {$caf['file']}\n";
            } else {
                echo "❌ Número inválido\n";
            }
            break;

        case '2':
            echo "\nIngrese el número del CAF a usar (1-" . count($cafs_info) . "): ";
            $num = (int) trim(fgets(STDIN));

            if ($num > 0 && $num <= count($cafs_info)) {
                $caf = $cafs_info[$num - 1];

                echo "\n¿Cambiar al CAF '{$caf['nombre']}'? (s/n): ";
                $confirma = trim(fgets(STDIN));

                if (strtolower($confirma) === 's') {
                    // Actualizar archivo de configuración
                    echo "\nPara cambiar el CAF, actualice en generar-boleta.php:\n";
                    echo "   define('CAF_PATH', __DIR__ . '/{$caf['nombre']}');\n\n";

                    // Preguntar si resetear contador
                    echo "¿Resetear el contador al inicio de este CAF? (s/n): ";
                    $reset = trim(fgets(STDIN));

                    if (strtolower($reset) === 's') {
                        $nuevo_folio = $caf['desde'] - 1;
                        file_put_contents($control_file, $nuevo_folio);
                        echo "✅ Contador reseteado a {$nuevo_folio}\n";
                        echo "   Próximo folio: {$caf['desde']}\n";
                    }
                }
            } else {
                echo "❌ Número inválido\n";
            }
            break;

        case '3':
            echo "\n⚠️  ADVERTENCIA: Esto reseteará el contador de folios\n";
            echo "Ingrese el nuevo valor de folio (o Enter para cancelar): ";
            $nuevo = trim(fgets(STDIN));

            if (!empty($nuevo) && is_numeric($nuevo)) {
                file_put_contents($control_file, (int) $nuevo);
                echo "✅ Contador actualizado a: {$nuevo}\n";
            } else {
                echo "Cancelado\n";
            }
            break;

        case '4':
            echo "\n=== CAFs ORDENADOS POR FECHA ===\n\n";
            usort($cafs_info, function($a, $b) {
                return strcmp($a['fecha'], $b['fecha']);
            });

            foreach ($cafs_info as $idx => $caf) {
                echo ($idx + 1) . ". {$caf['nombre']}\n";
                echo "   Fecha: {$caf['fecha']}\n";
                echo "   Folios: {$caf['desde']}-{$caf['hasta']} ({$caf['total']} folios)\n\n";
            }
            break;

        case '0':
            echo "Saliendo...\n";
            exit(0);

        default:
            echo "Opción inválida\n";
    }
}
