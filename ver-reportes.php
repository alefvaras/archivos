<?php
/**
 * VISUALIZADOR DE REPORTES DE PRUEBAS
 *
 * Script para listar y visualizar los reportes de pruebas generados
 * en el ambiente de certificación.
 *
 * Uso:
 *   php ver-reportes.php                 # Listar todos los reportes
 *   php ver-reportes.php --ultimo        # Ver el reporte más reciente
 *   php ver-reportes.php reporte.json    # Ver reporte específico
 *
 * @package SimpleDTE
 * @version 1.0.0
 */

class VisualizadorReportes {

    private $directorio_reportes;

    public function __construct() {
        $this->directorio_reportes = __DIR__ . '/reportes';
    }

    /**
     * Ejecutar el visualizador
     */
    public function ejecutar($args) {
        if (!is_dir($this->directorio_reportes)) {
            $this->error("Directorio de reportes no existe: {$this->directorio_reportes}");
            $this->info("Ejecute primero: php prueba-ambiente-certificacion.php");
            return false;
        }

        // Sin argumentos: listar reportes
        if (count($args) === 1) {
            $this->listarReportes();
            return true;
        }

        // --ultimo: mostrar el más reciente
        if (in_array('--ultimo', $args) || in_array('-l', $args)) {
            $this->mostrarUltimoReporte();
            return true;
        }

        // Archivo específico
        $archivo = $args[1];
        if (!file_exists($archivo)) {
            // Intentar en directorio de reportes
            $archivo_completo = $this->directorio_reportes . '/' . $archivo;
            if (file_exists($archivo_completo)) {
                $archivo = $archivo_completo;
            } else {
                $this->error("Archivo no encontrado: $archivo");
                return false;
            }
        }

        $this->mostrarReporte($archivo);
        return true;
    }

    /**
     * Listar todos los reportes
     */
    private function listarReportes() {
        $reportes = glob($this->directorio_reportes . '/prueba-certificacion-*.json');

        if (empty($reportes)) {
            $this->info("No hay reportes disponibles.");
            $this->info("Ejecute: php prueba-ambiente-certificacion.php");
            return;
        }

        // Ordenar por fecha (más reciente primero)
        usort($reportes, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        $this->titulo("REPORTES DE PRUEBAS DISPONIBLES");
        echo str_repeat("=", 90) . "\n";
        printf("%-35s %-20s %-15s %-15s\n", "ARCHIVO", "FECHA", "GENERADOS", "ENVIADOS");
        echo str_repeat("-", 90) . "\n";

        foreach ($reportes as $reporte) {
            $data = json_decode(file_get_contents($reporte), true);

            $nombre = basename($reporte);
            $fecha = $data['fecha'] ?? 'Desconocida';

            $total = count($data['resultados'] ?? []);
            $generados = 0;
            $enviados = 0;

            foreach ($data['resultados'] ?? [] as $resultado) {
                if ($resultado['generado'] ?? false) {
                    $generados++;
                }
                if ($resultado['enviado'] ?? false) {
                    $enviados++;
                }
            }

            $generados_str = "$generados/$total";
            $enviados_str = $data['skip_envio'] ? "N/A" : "$enviados/$total";

            printf("%-35s %-20s %-15s %-15s\n", $nombre, $fecha, $generados_str, $enviados_str);
        }

        echo str_repeat("=", 90) . "\n";
        $this->info("\nTotal de reportes: " . count($reportes));
        $this->info("\nPara ver un reporte:");
        $this->info("  php ver-reportes.php --ultimo");
        $this->info("  php ver-reportes.php <nombre-archivo>");
    }

    /**
     * Mostrar el último reporte
     */
    private function mostrarUltimoReporte() {
        $reportes = glob($this->directorio_reportes . '/prueba-certificacion-*.json');

        if (empty($reportes)) {
            $this->error("No hay reportes disponibles.");
            return;
        }

        // Obtener el más reciente
        usort($reportes, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        $ultimo = $reportes[0];
        $this->mostrarReporte($ultimo);
    }

    /**
     * Mostrar un reporte específico
     */
    private function mostrarReporte($archivo) {
        $data = json_decode(file_get_contents($archivo), true);

        if (!$data) {
            $this->error("No se pudo leer el reporte: $archivo");
            return;
        }

        // Encabezado
        echo "\n";
        echo str_repeat("=", 90) . "\n";
        echo "  REPORTE DE PRUEBA - AMBIENTE DE CERTIFICACIÓN\n";
        echo str_repeat("=", 90) . "\n";
        echo "Archivo: " . basename($archivo) . "\n";
        echo "Fecha: " . ($data['fecha'] ?? 'Desconocida') . "\n";
        echo "Ambiente: " . strtoupper($data['ambiente'] ?? 'desconocido') . "\n";
        echo "Envío al SII: " . ($data['skip_envio'] ? 'NO (solo generación)' : 'SÍ') . "\n";
        echo str_repeat("=", 90) . "\n\n";

        // Resultados por tipo
        $total = count($data['resultados'] ?? []);
        $generados = 0;
        $enviados = 0;
        $errores_totales = 0;

        foreach ($data['resultados'] ?? [] as $tipo => $resultado) {
            $this->mostrarResultadoDTE($tipo, $resultado);

            if ($resultado['generado'] ?? false) {
                $generados++;
            }
            if ($resultado['enviado'] ?? false) {
                $enviados++;
            }
            $errores_totales += count($resultado['errores'] ?? []);
        }

        // Resumen
        echo "\n" . str_repeat("-", 90) . "\n";
        echo "RESUMEN:\n";
        echo str_repeat("-", 90) . "\n";
        echo "  Total de pruebas: $total\n";
        echo "  DTEs generados: $generados/$total\n";

        if (!$data['skip_envio']) {
            echo "  DTEs enviados al SII: $enviados/$total\n";
        }

        echo "  Errores totales: $errores_totales\n";
        echo str_repeat("=", 90) . "\n";

        if ($errores_totales === 0) {
            $this->success("\n¡TODAS LAS PRUEBAS FUERON EXITOSAS!");
        } elseif ($generados > 0) {
            $this->warning("\nPruebas completadas con algunos errores.");
        } else {
            $this->error("\nLas pruebas fallaron completamente.");
        }

        echo "\n";
    }

    /**
     * Mostrar resultado de un DTE
     */
    private function mostrarResultadoDTE($tipo, $resultado) {
        echo "[$tipo] {$resultado['nombre']}:\n";
        echo str_repeat("-", 90) . "\n";

        // Generado
        if ($resultado['generado'] ?? false) {
            $this->success("  ✓ Generado correctamente");
            echo "    Folio: " . ($resultado['folio'] ?? 'N/A') . "\n";

            if (!empty($resultado['xml_path'])) {
                echo "    XML: " . $resultado['xml_path'] . "\n";
            }
            if (!empty($resultado['pdf_path'])) {
                echo "    PDF: " . $resultado['pdf_path'] . "\n";
            }
        } else {
            $this->error("  ✗ NO se pudo generar");
        }

        // Enviado
        if (isset($resultado['enviado'])) {
            if ($resultado['enviado']) {
                $this->success("  ✓ Enviado al SII");
                echo "    Track ID: " . ($resultado['track_id'] ?? 'N/A') . "\n";

                if (!empty($resultado['estado_sii'])) {
                    $estado = $resultado['estado_sii'];
                    $glosa = $resultado['glosa_sii'] ?? '';

                    if (in_array($estado, ['ACEPTADO', 'APROBADO'])) {
                        $this->success("    Estado SII: $estado");
                    } elseif (in_array($estado, ['RECHAZADO', 'REPARO'])) {
                        $this->error("    Estado SII: $estado");
                    } else {
                        $this->warning("    Estado SII: $estado");
                    }

                    if ($glosa) {
                        echo "    Glosa: $glosa\n";
                    }
                }
            } else {
                $this->error("  ✗ NO se pudo enviar al SII");
            }
        }

        // Errores
        if (!empty($resultado['errores'])) {
            $this->error("  Errores encontrados:");
            foreach ($resultado['errores'] as $error) {
                echo "    - $error\n";
            }
        }

        echo "\n";
    }

    // =================================================================
    // MÉTODOS DE PRESENTACIÓN
    // =================================================================

    private function titulo($texto) {
        echo "\n$texto\n";
    }

    private function info($msg) {
        echo "[INFO] $msg\n";
    }

    private function success($msg) {
        echo "\033[32m[OK]\033[0m $msg\n";
    }

    private function error($msg) {
        echo "\033[31m[ERROR]\033[0m $msg\n";
    }

    private function warning($msg) {
        echo "\033[33m[WARN]\033[0m $msg\n";
    }
}

// =================================================================
// EJECUCIÓN
// =================================================================

$visualizador = new VisualizadorReportes();
$exitoso = $visualizador->ejecutar($argv);

exit($exitoso ? 0 : 1);
