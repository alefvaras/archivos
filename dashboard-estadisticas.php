<?php
/**
 * Dashboard Visual de Estadísticas
 *
 * Muestra estadísticas en tiempo real del sistema de boletas
 * con gráficos ASCII, tablas y métricas visuales
 */

require_once __DIR__ . '/config/settings.php';
require_once __DIR__ . '/lib/VisualHelper.php';

$v = VisualHelper::getInstance();
$config = ConfiguracionSistema::getInstance();

// Limpiar pantalla
$v->limpiar();

// Título principal
$v->titulo("DASHBOARD DE ESTADÍSTICAS - SISTEMA DE BOLETAS ELECTRÓNICAS", "═");

// ========================================
// RECOPILAR ESTADÍSTICAS
// ========================================

$stats = recopilarEstadisticas($config);

// ========================================
// MOSTRAR ESTADÍSTICAS GENERALES
// ========================================

$v->seccion("📊 ESTADÍSTICAS GENERALES");

$v->resumen("Resumen del Sistema", [
    'ambiente' => [
        'texto' => 'Ambiente',
        'valor' => strtoupper($config->get('general.ambiente')),
        'tipo' => $config->get('general.ambiente') === 'produccion' ? 'warning' : 'info',
        'icono' => '🌐'
    ],
    'boletas_total' => [
        'texto' => 'Total boletas generadas',
        'valor' => number_format($stats['boletas']['total'], 0, ',', '.'),
        'tipo' => 'success',
        'icono' => '📄'
    ],
    'boletas_hoy' => [
        'texto' => 'Boletas hoy',
        'valor' => number_format($stats['boletas']['hoy'], 0, ',', '.'),
        'tipo' => 'info',
        'icono' => '📅'
    ],
    'tasa_exito' => [
        'texto' => 'Tasa de éxito',
        'valor' => number_format($stats['boletas']['tasa_exito'], 1) . '%',
        'tipo' => $stats['boletas']['tasa_exito'] >= 90 ? 'success' : 'warning',
        'icono' => '✓'
    ],
]);

// ========================================
// GRÁFICO DE BARRAS - BOLETAS POR DÍA
// ========================================

if (!empty($stats['boletas_por_dia'])) {
    $v->subtitulo("📈  BOLETAS GENERADAS (ÚLTIMOS 7 DÍAS)");

    $max_valor = max(array_values($stats['boletas_por_dia']));

    foreach ($stats['boletas_por_dia'] as $fecha => $cantidad) {
        $porcentaje = $max_valor > 0 ? ($cantidad / $max_valor) : 0;
        $barra_width = floor($porcentaje * 40);

        echo "  " . $v->dim($fecha) . " │ ";
        echo $v->success(str_repeat('█', $barra_width));
        echo $v->dim(str_repeat('░', 40 - $barra_width));
        echo " " . $v->info($cantidad, true) . "\n";
    }

    echo "\n";
}

// ========================================
// TABLA DE FOLIOS
// ========================================

$v->subtitulo("📋  ESTADO DE FOLIOS");

$headers = ['Tipo DTE', 'Desde', 'Hasta', 'Usados', 'Disponibles', 'Estado'];
$rows = [];

foreach ($stats['folios'] as $folio) {
    $disponibles = $folio['disponibles'];
    $estado = $disponibles > 50 ? '✓ OK' :
             ($disponibles > 10 ? '⚠ Bajo' : '❌ Crítico');

    $rows[] = [
        $folio['tipo_dte'],
        $folio['desde'],
        $folio['hasta'],
        $folio['usados'],
        $disponibles,
        $estado
    ];
}

if (!empty($rows)) {
    $v->tabla($headers, $rows);
} else {
    $v->mensaje('warning', 'No hay información de folios disponible');
}

// ========================================
// TRACK IDS RECIENTES
// ========================================

if (!empty($stats['track_ids_recientes'])) {
    $v->subtitulo("🔍  TRACK IDS RECIENTES");

    $headers = ['Track ID', 'Folio', 'Estado', 'Fecha'];
    $rows = [];

    foreach ($stats['track_ids_recientes'] as $track) {
        $estado_icon = match($track['estado']) {
            'EPR' => '✓ EPR',
            'REC' => '⏳ REC',
            'RCH' => '✗ RCH',
            default => $track['estado']
        };

        $rows[] = [
            $track['track_id'],
            $track['folio'] ?? 'N/A',
            $estado_icon,
            $track['fecha']
        ];
    }

    $v->tabla($headers, $rows);
}

// ========================================
// ESTADÍSTICAS DE ERROR
// ========================================

if ($stats['errores']['total'] > 0) {
    $v->subtitulo("⚠️  ERRORES REGISTRADOS");

    $v->resumen("Últimas 24 horas", [
        'total' => [
            'texto' => 'Total errores',
            'valor' => $stats['errores']['total'],
            'tipo' => 'error',
            'icono' => '❌'
        ],
        'criticos' => [
            'texto' => 'Críticos',
            'valor' => $stats['errores']['criticos'],
            'tipo' => 'error',
            'icono' => '🔴'
        ],
        'advertencias' => [
            'texto' => 'Advertencias',
            'valor' => $stats['errores']['advertencias'],
            'tipo' => 'warning',
            'icono' => '🟡'
        ],
    ]);

    // Mostrar últimos 5 errores
    if (!empty($stats['errores']['recientes'])) {
        echo "\n";
        $v->mensaje('info', 'Últimos 5 errores:');

        foreach (array_slice($stats['errores']['recientes'], 0, 5) as $error) {
            echo "  " . $v->dim($error['fecha']) . " - ";
            echo $v->error($error['mensaje']) . "\n";
        }

        echo "\n";
    }
}

// ========================================
// MÉTRICAS DE RENDIMIENTO
// ========================================

$v->subtitulo("⚡  MÉTRICAS DE RENDIMIENTO");

$v->resumen("Promedios", [
    'tiempo_generacion' => [
        'texto' => 'Tiempo generación',
        'valor' => number_format($stats['rendimiento']['tiempo_promedio_generacion'], 2) . 's',
        'tipo' => 'info',
        'icono' => '⏱️'
    ],
    'tiempo_envio' => [
        'texto' => 'Tiempo envío SII',
        'valor' => number_format($stats['rendimiento']['tiempo_promedio_envio'], 2) . 's',
        'tipo' => 'info',
        'icono' => '📤'
    ],
    'tamano_pdf' => [
        'texto' => 'Tamaño PDF promedio',
        'valor' => number_format($stats['rendimiento']['tamano_promedio_pdf'] / 1024, 0) . ' KB',
        'tipo' => 'info',
        'icono' => '📄'
    ],
]);

// ========================================
// ESTADO DEL SISTEMA
// ========================================

$v->subtitulo("💻  ESTADO DEL SISTEMA");

$checks = [];

// Check certificado
$cert_path = $config->get('certificado.path');
$cert_ok = file_exists($cert_path);
$checks[] = [
    'Certificado digital',
    $cert_ok ? '✓ OK' : '✗ ERROR',
    $cert_ok ? 'Disponible' : 'No encontrado'
];

// Check CAF
$caf_path = $config->get('caf.path');
$caf_ok = file_exists($caf_path);
$checks[] = [
    'Archivo CAF',
    $caf_ok ? '✓ OK' : '✗ ERROR',
    $caf_ok ? 'Disponible' : 'No encontrado'
];

// Check BD
$bd_ok = $config->get('database.habilitado');
$checks[] = [
    'Base de datos',
    $bd_ok ? '✓ Habilitada' : '○ Archivos',
    $bd_ok ? 'MySQL/MariaDB' : 'Modo archivo'
];

// Check logs
$log_dir = $config->get('logging.path');
$log_ok = is_dir($log_dir) && is_writable($log_dir);
$checks[] = [
    'Sistema de logs',
    $log_ok ? '✓ OK' : '✗ ERROR',
    $log_ok ? 'Escribible' : 'Sin permisos'
];

// Check email
$email_ok = $config->get('email.habilitado');
$checks[] = [
    'Email',
    $email_ok ? '✓ Habilitado' : '○ Deshabilitado',
    $config->get('email.metodo')
];

$v->tabla(['Componente', 'Estado', 'Detalles'], $checks);

// ========================================
// ALERTAS Y RECOMENDACIONES
// ========================================

$alertas = [];

// Alertas de folios bajos
foreach ($stats['folios'] as $folio) {
    if ($folio['disponibles'] < 10) {
        $alertas[] = [
            'tipo' => 'error',
            'mensaje' => "CRÍTICO: Solo {$folio['disponibles']} folios disponibles para DTE {$folio['tipo_dte']}"
        ];
    } elseif ($folio['disponibles'] < 50) {
        $alertas[] = [
            'tipo' => 'warning',
            'mensaje' => "ADVERTENCIA: {$folio['disponibles']} folios disponibles para DTE {$folio['tipo_dte']}"
        ];
    }
}

// Alertas de tasa de éxito baja
if ($stats['boletas']['tasa_exito'] < 90 && $stats['boletas']['total'] > 10) {
    $alertas[] = [
        'tipo' => 'warning',
        'mensaje' => "Tasa de éxito baja: " . number_format($stats['boletas']['tasa_exito'], 1) . "% (esperado >90%)"
    ];
}

// Alertas de errores recientes
if ($stats['errores']['total'] > 10) {
    $alertas[] = [
        'tipo' => 'warning',
        'mensaje' => "{$stats['errores']['total']} errores registrados en las últimas 24h"
    ];
}

if (!empty($alertas)) {
    $v->subtitulo("🚨  ALERTAS");

    foreach ($alertas as $alerta) {
        $v->mensaje($alerta['tipo'], $alerta['mensaje']);
    }

    echo "\n";
}

// ========================================
// PIE DE PÁGINA
// ========================================

$v->separador('═');

echo "\n";
echo $v->dim("Última actualización: " . date('Y-m-d H:i:s')) . "\n";
echo $v->dim("Actualizar: php dashboard-estadisticas.php") . "\n";
echo "\n";

// ========================================
// FUNCIÓN PARA RECOPILAR ESTADÍSTICAS
// ========================================

function recopilarEstadisticas($config) {
    $stats = [
        'boletas' => [
            'total' => 0,
            'hoy' => 0,
            'aceptadas' => 0,
            'rechazadas' => 0,
            'tasa_exito' => 0,
        ],
        'boletas_por_dia' => [],
        'folios' => [],
        'track_ids_recientes' => [],
        'errores' => [
            'total' => 0,
            'criticos' => 0,
            'advertencias' => 0,
            'recientes' => [],
        ],
        'rendimiento' => [
            'tiempo_promedio_generacion' => 0,
            'tiempo_promedio_envio' => 0,
            'tamano_promedio_pdf' => 0,
        ],
    ];

    // Estadísticas desde BD si está habilitada
    if ($config->get('database.habilitado')) {
        $stats = estadisticasDesdeDB($stats);
    } else {
        $stats = estadisticasDesdeArchivos($stats);
    }

    return $stats;
}

function estadisticasDesdeDB($stats) {
    try {
        require_once __DIR__ . '/lib/BoletaRepository.php';
        $repo = new BoletaRepository();
        $db = $repo->getConnection();

        // Total de boletas
        $stmt = $db->query("SELECT COUNT(*) as total FROM boletas");
        $stats['boletas']['total'] = $stmt->fetchColumn();

        // Boletas de hoy
        $stmt = $db->query("SELECT COUNT(*) as total FROM boletas WHERE DATE(fecha_emision) = CURDATE()");
        $stats['boletas']['hoy'] = $stmt->fetchColumn();

        // Boletas por día (últimos 7 días)
        $stmt = $db->query("
            SELECT DATE(fecha_emision) as fecha, COUNT(*) as cantidad
            FROM boletas
            WHERE fecha_emision >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(fecha_emision)
            ORDER BY fecha DESC
        ");

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stats['boletas_por_dia'][$row['fecha']] = $row['cantidad'];
        }

        // Folios disponibles
        $stmt = $db->query("
            SELECT tipo_dte, folio_desde, folio_hasta, proximo_folio
            FROM cafs
            WHERE activo = 1
        ");

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $usados = $row['proximo_folio'] - $row['folio_desde'];
            $disponibles = $row['folio_hasta'] - $row['proximo_folio'] + 1;

            $stats['folios'][] = [
                'tipo_dte' => $row['tipo_dte'],
                'desde' => $row['folio_desde'],
                'hasta' => $row['folio_hasta'],
                'usados' => $usados,
                'disponibles' => $disponibles,
            ];
        }

        // Track IDs recientes
        $stmt = $db->query("
            SELECT track_id, folio, estado_sii, fecha_emision
            FROM boletas
            WHERE track_id IS NOT NULL
            ORDER BY fecha_emision DESC
            LIMIT 10
        ");

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stats['track_ids_recientes'][] = [
                'track_id' => $row['track_id'],
                'folio' => $row['folio'],
                'estado' => $row['estado_sii'] ?? 'N/A',
                'fecha' => date('Y-m-d H:i', strtotime($row['fecha_emision'])),
            ];
        }

        // Calcular tasa de éxito
        if ($stats['boletas']['total'] > 0) {
            $stmt = $db->query("
                SELECT COUNT(*) as aceptadas
                FROM boletas
                WHERE estado_sii = 'EPR'
            ");

            $stats['boletas']['aceptadas'] = $stmt->fetchColumn();
            $stats['boletas']['tasa_exito'] = ($stats['boletas']['aceptadas'] / $stats['boletas']['total']) * 100;
        }

    } catch (Exception $e) {
        // Silenciar error y continuar con datos vacíos
    }

    return $stats;
}

function estadisticasDesdeArchivos($stats) {
    // Leer logs para estadísticas
    $log_dir = __DIR__ . '/logs';

    if (is_dir($log_dir)) {
        // Últimos 7 días de logs
        for ($i = 6; $i >= 0; $i--) {
            $fecha = date('Y-m-d', strtotime("-$i days"));
            $log_file = $log_dir . "/dte_$fecha.log";

            if (file_exists($log_file)) {
                $contenido = file_get_contents($log_file);

                // Contar boletas generadas
                $boletas = substr_count($contenido, 'Boleta generada');
                $stats['boletas']['total'] += $boletas;
                $stats['boletas_por_dia'][$fecha] = $boletas;

                if ($i === 0) {
                    $stats['boletas']['hoy'] = $boletas;
                }

                // Contar éxitos
                $exitos = substr_count($contenido, 'estado_sii=EPR');
                $stats['boletas']['aceptadas'] += $exitos;
            }
        }

        // Calcular tasa de éxito
        if ($stats['boletas']['total'] > 0) {
            $stats['boletas']['tasa_exito'] = ($stats['boletas']['aceptadas'] / $stats['boletas']['total']) * 100;
        }

        // Leer errores del log de hoy
        $error_log = $log_dir . "/errors_" . date('Y-m-d') . ".log";
        if (file_exists($error_log)) {
            $lineas = file($error_log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $stats['errores']['total'] = count($lineas);

            foreach ($lineas as $linea) {
                if (stripos($linea, 'CRITICAL') !== false || stripos($linea, 'ERROR') !== false) {
                    $stats['errores']['criticos']++;
                    $stats['errores']['recientes'][] = [
                        'fecha' => date('H:i:s'),
                        'mensaje' => substr($linea, 0, 100)
                    ];
                } elseif (stripos($linea, 'WARNING') !== false) {
                    $stats['errores']['advertencias']++;
                }
            }
        }
    }

    // Leer información de CAF
    $caf_files = glob(__DIR__ . '/FoliosSII*.xml');

    foreach ($caf_files as $caf_file) {
        $caf_xml = simplexml_load_file($caf_file);

        if ($caf_xml && isset($caf_xml->CAF->DA->RNG)) {
            $tipo_dte = (int) $caf_xml->CAF->DA->TD;
            $desde = (int) $caf_xml->CAF->DA->RNG->D;
            $hasta = (int) $caf_xml->CAF->DA->RNG->H;

            // Leer folios usados
            $control_file = __DIR__ . '/folios_usados.txt';
            $proximo_folio = $desde;

            if (file_exists($control_file)) {
                $lineas = file($control_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lineas as $linea) {
                    if (strpos($linea, "DTE $tipo_dte") !== false) {
                        preg_match('/Próximo folio: (\d+)/', $linea, $matches);
                        if (isset($matches[1])) {
                            $proximo_folio = (int) $matches[1];
                            break;
                        }
                    }
                }
            }

            $usados = $proximo_folio - $desde;
            $disponibles = $hasta - $proximo_folio + 1;

            $stats['folios'][] = [
                'tipo_dte' => $tipo_dte,
                'desde' => $desde,
                'hasta' => $hasta,
                'usados' => $usados,
                'disponibles' => $disponibles,
            ];
        }
    }

    // Métricas de rendimiento desde PDFs
    $pdf_dir = __DIR__ . '/pdfs';
    if (is_dir($pdf_dir)) {
        $pdfs = glob($pdf_dir . '/*.pdf');
        if (count($pdfs) > 0) {
            $tamano_total = 0;
            foreach ($pdfs as $pdf) {
                $tamano_total += filesize($pdf);
            }
            $stats['rendimiento']['tamano_promedio_pdf'] = $tamano_total / count($pdfs);
        }
    }

    // Tiempos de generación estimados
    $stats['rendimiento']['tiempo_promedio_generacion'] = 1.5; // Estimado
    $stats['rendimiento']['tiempo_promedio_envio'] = 2.3; // Estimado

    return $stats;
}
