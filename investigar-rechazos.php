<?php
/**
 * Script para Investigar Rechazos del SII
 *
 * Consulta detalles completos de Track IDs rechazados
 * para identificar exactamente por qué fueron rechazados
 */

require_once __DIR__ . '/generar-boleta.php';

echo "=== INVESTIGACIÓN DE DOCUMENTOS RECHAZADOS ===\n\n";

// Track IDs rechazados que queremos investigar
$track_ids_rechazados = [
    25790877 => 'Primer rechazo',
    25791022 => 'Segundo rechazo (Folio 1890)'
];

echo "🔍 Analizando " . count($track_ids_rechazados) . " documentos rechazados...\n\n";

foreach ($track_ids_rechazados as $track_id => $descripcion) {
    echo str_repeat("=", 70) . "\n";
    echo "Track ID: {$track_id}\n";
    echo "Descripción: {$descripcion}\n";
    echo str_repeat("-", 70) . "\n\n";

    try {
        $estado = consultar_estado($track_id, $API_BASE);

        if ($estado) {
            echo "📊 INFORMACIÓN GENERAL:\n";
            echo "  Estado: " . ($estado['estado'] ?? 'N/A') . "\n";
            echo "  Glosa: " . ($estado['glosa'] ?? 'Sin glosa') . "\n\n";

            // Estadísticas
            if (isset($estado['estadistica']) && is_array($estado['estadistica'])) {
                echo "📈 ESTADÍSTICAS:\n";
                foreach ($estado['estadistica'] as $stat) {
                    echo "  Tipo DTE: " . ($stat['tipo'] ?? 'N/A') . "\n";
                    echo "  Aceptados: " . ($stat['aceptados'] ?? 0) . "\n";
                    echo "  Rechazados: " . ($stat['rechazados'] ?? 0) . "\n";
                    echo "  Reparos: " . ($stat['reparos'] ?? 0) . "\n";
                    echo "  Total: " . ($stat['total'] ?? 0) . "\n";
                }
                echo "\n";
            }

            // DETALLES DE DOCUMENTOS - AQUÍ ESTÁ LA INFO CLAVE
            if (isset($estado['detalle']) && is_array($estado['detalle'])) {
                echo "📄 DETALLE DE DOCUMENTOS (RAZONES DE RECHAZO):\n";
                foreach ($estado['detalle'] as $i => $doc) {
                    echo "\n  Documento #" . ($i + 1) . ":\n";
                    echo "    Folio: " . ($doc['folio'] ?? 'N/A') . "\n";
                    echo "    Tipo: " . ($doc['tipo'] ?? 'N/A') . "\n";
                    echo "    Estado: " . ($doc['estado'] ?? 'N/A') . "\n";
                    echo "    Glosa: " . ($doc['glosa'] ?? 'Sin glosa específica') . "\n";

                    if (isset($doc['error'])) {
                        echo "    ⚠️  ERROR: " . $doc['error'] . "\n";
                    }

                    if (isset($doc['errores']) && is_array($doc['errores'])) {
                        echo "    ⚠️  ERRORES:\n";
                        foreach ($doc['errores'] as $error) {
                            echo "      - " . $error . "\n";
                        }
                    }
                }
            } else {
                echo "⚠️  No hay detalles específicos disponibles\n";
            }

            // Errores generales del envío
            if (isset($estado['errores']) && !empty($estado['errores'])) {
                echo "\n❌ ERRORES DEL ENVÍO:\n";
                if (is_array($estado['errores'])) {
                    foreach ($estado['errores'] as $error) {
                        echo "  - " . $error . "\n";
                    }
                } else {
                    echo "  - " . $estado['errores'] . "\n";
                }
            }

            // Mostrar respuesta completa para debugging
            echo "\n🔧 RESPUESTA COMPLETA (DEBUG):\n";
            echo json_encode($estado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

        } else {
            echo "❌ No se pudo obtener información del Track ID\n";
        }

    } catch (Exception $e) {
        echo "❌ Error consultando: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

echo str_repeat("=", 70) . "\n\n";

// Comparar con uno aceptado para ver diferencias
echo "🔍 COMPARACIÓN CON DOCUMENTO ACEPTADO:\n\n";

$track_id_aceptado = 25791013;
echo "Track ID Aceptado: {$track_id_aceptado} (Folio 1891)\n";
echo str_repeat("-", 70) . "\n\n";

try {
    $estado_ok = consultar_estado($track_id_aceptado, $API_BASE);

    if ($estado_ok) {
        echo "Estado: " . ($estado_ok['estado'] ?? 'N/A') . "\n";

        if (isset($estado_ok['estadistica']) && is_array($estado_ok['estadistica'])) {
            foreach ($estado_ok['estadistica'] as $stat) {
                echo "Aceptados: " . ($stat['aceptados'] ?? 0) . "\n";
                echo "Rechazados: " . ($stat['rechazados'] ?? 0) . "\n";
            }
        }

        if (isset($estado_ok['detalle']) && is_array($estado_ok['detalle'])) {
            echo "\nDetalle:\n";
            foreach ($estado_ok['detalle'] as $doc) {
                echo "  Folio: " . ($doc['folio'] ?? 'N/A') . "\n";
                echo "  Estado: " . ($doc['estado'] ?? 'N/A') . "\n";
                echo "  Glosa: " . ($doc['glosa'] ?? 'Sin glosa') . "\n";
            }
        }
    }
} catch (Exception $e) {
    echo "Error consultando documento aceptado: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 70) . "\n\n";

// Recomendaciones basadas en rechazos comunes
echo "💡 CAUSAS COMUNES DE RECHAZO Y SOLUCIONES:\n\n";

$causas_comunes = [
    "RUT receptor inválido" => "Usar RUTs reales o RUT genérico 66666666-6 correctamente formateado",
    "Monto fuera de rango" => "Montos deben estar en rangos válidos (no muy altos en certificación)",
    "Fecha inválida" => "Fecha debe ser actual, no futuras ni muy antiguas",
    "Datos faltantes" => "Todos los campos obligatorios deben estar presentes",
    "Formato incorrecto" => "Validar formato de campos según schema XSD del SII",
    "Timbre incorrecto" => "Verificar que TED esté correctamente generado y firmado",
    "CAF inválido" => "Verificar que CAF sea oficial del SII",
    "Folio duplicado" => "No reutilizar folios ya enviados",
    "RUT emisor no autorizado" => "Verificar que RUT emisor esté habilitado en SII",
    "Ambiente incorrecto" => "Usar ambiente certificacion para pruebas"
];

foreach ($causas_comunes as $causa => $solucion) {
    echo "  • {$causa}:\n";
    echo "    → {$solucion}\n\n";
}

echo str_repeat("=", 70) . "\n\n";

echo "📝 PRÓXIMOS PASOS:\n\n";
echo "1. Revisar los errores específicos reportados arriba\n";
echo "2. Comparar XMLs de documentos rechazados vs aceptados\n";
echo "3. Ajustar datos de prueba según las causas identificadas\n";
echo "4. Generar nuevas boletas con datos mejorados\n";
echo "5. Verificar 100% de aceptación\n\n";

echo "✅ Investigación completa\n\n";
