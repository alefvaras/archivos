<?php
/**
 * Script para Consultar Estados de Track IDs del SII
 *
 * Consulta el estado de uno o múltiples Track IDs del SII
 * y muestra información detallada de cada envío.
 *
 * Uso:
 *   php consultar-track-ids.php [track_id1] [track_id2] ...
 *   php consultar-track-ids.php 25791022
 *   php consultar-track-ids.php 25791022 25791013 25790877
 */

require_once __DIR__ . '/generar-boleta.php';

echo "=== CONSULTA DE TRACK IDs SII ===\n\n";

// Obtener track IDs de argumentos o del último generado
$track_ids = [];

if ($argc > 1) {
    // Track IDs desde argumentos
    for ($i = 1; $i < $argc; $i++) {
        $track_ids[] = intval($argv[$i]);
    }
} else {
    // Leer último track ID desde archivo
    $track_file = '/tmp/track_id.txt';
    if (file_exists($track_file)) {
        $last_track_id = trim(file_get_contents($track_file));
        if ($last_track_id) {
            $track_ids[] = intval($last_track_id);
            echo "ℹ️  Consultando último Track ID generado: $last_track_id\n\n";
        }
    }
}

if (empty($track_ids)) {
    echo "❌ No se especificaron Track IDs\n\n";
    echo "Uso:\n";
    echo "  php consultar-track-ids.php [track_id1] [track_id2] ...\n\n";
    echo "Ejemplos:\n";
    echo "  php consultar-track-ids.php 25791022\n";
    echo "  php consultar-track-ids.php 25791022 25791013 25790877\n\n";
    exit(1);
}

echo "📋 Track IDs a consultar: " . count($track_ids) . "\n";
echo "   " . implode(", ", $track_ids) . "\n\n";
echo str_repeat("=", 70) . "\n\n";

$resultados = [];

foreach ($track_ids as $index => $track_id) {
    $num = $index + 1;
    echo "🔍 Consulta #{$num}: Track ID {$track_id}\n";
    echo str_repeat("-", 70) . "\n";

    try {
        $estado = consultar_estado($track_id, $API_BASE);

        if ($estado && isset($estado['estado'])) {
            $resultados[$track_id] = [
                'exito' => true,
                'estado' => $estado
            ];

            echo "✓ Estado: {$estado['estado']}\n";

            // Mapeo de estados
            $estados_descripcion = [
                'REC' => 'Recibido - En proceso de validación',
                'EPR' => 'Envío Procesado - Aceptado por SII',
                'RCH' => 'Rechazado por SII',
                'RPR' => 'Reprocesar - Aceptado con Reparos',
                'RCT' => 'Rechazado Total',
                'SOK' => 'Envío OK - Documentos con problemas'
            ];

            $descripcion = $estados_descripcion[$estado['estado']] ?? 'Estado desconocido';
            echo "   $descripcion\n";

            // Estadísticas
            if (isset($estado['estadistica']) && is_array($estado['estadistica'])) {
                echo "\n📊 Estadísticas del envío:\n";
                foreach ($estado['estadistica'] as $stat) {
                    if (isset($stat['tipo'])) {
                        $tipo = $stat['tipo'] == 39 ? 'Boleta Electrónica' :
                               ($stat['tipo'] == 33 ? 'Factura Afecta' :
                               ($stat['tipo'] == 34 ? 'Factura Exenta' :
                               ($stat['tipo'] == 61 ? 'Nota de Crédito' :
                               ($stat['tipo'] == 56 ? 'Nota de Débito' : "Tipo {$stat['tipo']}"))));

                        echo "   Tipo DTE: {$tipo}\n";
                    }

                    echo "   ✓ Aceptados: " . ($stat['aceptados'] ?? 0) . "\n";
                    echo "   ✓ Aceptados con reparos: " . ($stat['reparos'] ?? 0) . "\n";
                    echo "   ❌ Rechazados: " . ($stat['rechazados'] ?? 0) . "\n";

                    if (isset($stat['total'])) {
                        echo "   📋 Total documentos: {$stat['total']}\n";
                    }
                }
            }

            // Detalles de documentos
            if (isset($estado['detalle']) && is_array($estado['detalle'])) {
                echo "\n📄 Detalle de documentos:\n";
                foreach ($estado['detalle'] as $doc) {
                    $folio = $doc['folio'] ?? 'N/A';
                    $estado_doc = $doc['estado'] ?? 'N/A';
                    $glosa = $doc['glosa'] ?? '';

                    echo "   Folio {$folio}: {$estado_doc}";
                    if ($glosa) {
                        echo " - {$glosa}";
                    }
                    echo "\n";
                }
            }

            // Errores o glosas
            if (isset($estado['glosa']) && !empty($estado['glosa'])) {
                echo "\n📝 Glosa: {$estado['glosa']}\n";
            }

            if (isset($estado['errores']) && !empty($estado['errores'])) {
                echo "\n⚠️  Errores:\n";
                if (is_array($estado['errores'])) {
                    foreach ($estado['errores'] as $error) {
                        echo "   - $error\n";
                    }
                } else {
                    echo "   - {$estado['errores']}\n";
                }
            }

        } else {
            $resultados[$track_id] = [
                'exito' => false,
                'mensaje' => 'Estado aún no disponible o Track ID inválido'
            ];
            echo "⚠️  Estado aún no disponible\n";
            echo "   El SII puede tardar unos minutos en procesar el envío.\n";
            echo "   Intenta consultar nuevamente en 5-10 minutos.\n";
        }

    } catch (Exception $e) {
        $resultados[$track_id] = [
            'exito' => false,
            'error' => $e->getMessage()
        ];
        echo "❌ Error al consultar: {$e->getMessage()}\n";
    }

    echo "\n" . str_repeat("=", 70) . "\n\n";

    // Esperar un poco entre consultas para no saturar
    if ($num < count($track_ids)) {
        sleep(1);
    }
}

// Resumen final
echo "=== RESUMEN DE CONSULTAS ===\n\n";

$exitosos = array_filter($resultados, fn($r) => $r['exito'] ?? false);
$pendientes = array_filter($resultados, fn($r) => !($r['exito'] ?? false));

echo "✅ Consultas exitosas: " . count($exitosos) . "\n";
echo "⏳ Pendientes/Error: " . count($pendientes) . "\n\n";

if (!empty($exitosos)) {
    echo "Estados obtenidos:\n";
    foreach ($exitosos as $track_id => $resultado) {
        $estado = $resultado['estado']['estado'] ?? 'N/A';
        echo "  Track ID {$track_id}: {$estado}\n";
    }
    echo "\n";
}

if (!empty($pendientes)) {
    echo "Pendientes de procesar:\n";
    foreach ($pendientes as $track_id => $resultado) {
        $mensaje = $resultado['mensaje'] ?? $resultado['error'] ?? 'Desconocido';
        echo "  Track ID {$track_id}: {$mensaje}\n";
    }
    echo "\n";
}

// Guardar resultados en archivo JSON
$resultado_file = __DIR__ . '/logs/consulta_track_ids_' . date('Y-m-d_H-i-s') . '.json';
$log_dir = dirname($resultado_file);
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

file_put_contents($resultado_file, json_encode([
    'fecha_consulta' => date('Y-m-d H:i:s'),
    'track_ids_consultados' => $track_ids,
    'resultados' => $resultados
], JSON_PRETTY_PRINT));

echo "💾 Resultados guardados en: {$resultado_file}\n\n";

echo "ℹ️  Información de Estados SII:\n";
echo "   REC - Recibido (aún procesando)\n";
echo "   EPR - Envío Procesado (aceptado)\n";
echo "   RCH - Rechazado\n";
echo "   RPR - Reprocesar (aceptado con reparos)\n";
echo "   SOK - Envío OK con documentos problemáticos\n\n";

// Retornar código de salida según resultados
exit(count($pendientes) > 0 ? 1 : 0);
