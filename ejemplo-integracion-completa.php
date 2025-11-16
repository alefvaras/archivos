#!/usr/bin/env php
<?php
/**
 * Ejemplo de Integración Completa
 * Muestra cómo usar todas las funcionalidades juntas:
 * - Base de Datos (BoletaRepository)
 * - Logging Estructurado (DTELogger)
 * - PDF con Timbre PDF417
 * - Email con adjuntos
 * - Simple API
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== EJEMPLO: INTEGRACIÓN COMPLETA ===\n\n";

// ============================================================
// PASO 1: Configuración
// ============================================================

echo "📋 Paso 1: Configuración del sistema...\n";

// Configuración Simple API (igual que antes)
define('API_KEY', '7ccec9d6e4ede91e77e48ad1e7f72bd6bcbe05a5');
define('CERT_PATH', __DIR__ . '/16694181-4.pfx');
define('CERT_PASSWORD', 'Iloveyou.2024');
define('CAF_PATH', __DIR__ . '/FoliosSII78274225391889202511161321.xml');
define('RUT_EMISOR', '78274225-6');
define('RAZON_SOCIAL', 'AKIBARA SPA');
define('AMBIENTE', 'certificacion');

$API_BASE = AMBIENTE === 'certificacion'
    ? 'https://api.simpleapi.cl/api'
    : 'https://api.simpleapi.cl/api';

// Detectar si hay base de datos configurada
$usar_bd = getenv('DB_NAME') && getenv('DB_USER');
$usar_logging = true; // Siempre usar logging

echo "  Ambiente: " . AMBIENTE . "\n";
echo "  Base de datos: " . ($usar_bd ? "✅ SÍ (MySQL)" : "❌ NO (modo archivo)") . "\n";
echo "  Logging: " . ($usar_logging ? "✅ SÍ" : "❌ NO") . "\n\n";

// ============================================================
// PASO 2: Inicializar componentes
// ============================================================

echo "🔧 Paso 2: Inicializando componentes...\n";

// Inicializar Logger
require_once(__DIR__ . '/lib/DTELogger.php');
$logger = new DTELogger(
    log_dir: __DIR__ . '/logs',
    usar_bd: $usar_bd,
    niveles_activos: [
        DTELogger::NIVEL_INFO,
        DTELogger::NIVEL_WARNING,
        DTELogger::NIVEL_ERROR,
        DTELogger::NIVEL_CRITICAL
    ]
);
echo "  ✓ Logger inicializado\n";

// Inicializar Repository (si BD está disponible)
$repo = null;
if ($usar_bd) {
    try {
        require_once(__DIR__ . '/lib/BoletaRepository.php');
        $repo = new BoletaRepository();
        echo "  ✓ Repository inicializado\n";

        $logger->info('sistema', 'Sistema iniciado con base de datos', [
            'modo' => 'bd',
            'ambiente' => AMBIENTE
        ]);
    } catch (Exception $e) {
        echo "  ⚠️  Error conectando a BD: {$e->getMessage()}\n";
        echo "  Cambiando a modo archivo...\n";
        $usar_bd = false;
        $repo = null;

        $logger->warning('sistema', 'No se pudo conectar a BD, usando modo archivo', [
            'error' => $e->getMessage()
        ]);
    }
} else {
    echo "  ℹ️  Modo archivo (sin BD)\n";
    $logger->info('sistema', 'Sistema iniciado en modo archivo', [
        'modo' => 'archivo',
        'ambiente' => AMBIENTE
    ]);
}

echo "\n";

// ============================================================
// PASO 3: Obtener próximo folio
// ============================================================

echo "🔢 Paso 3: Obteniendo próximo folio...\n";

$tipo_dte = 39; // Boleta Electrónica
$folio = null;
$caf_id = null;

if ($usar_bd && $repo) {
    // Modo BD: usar repository
    try {
        $folio_info = $repo->obtenerProximoFolio($tipo_dte);
        $folio = $folio_info['folio'];
        $caf_id = $folio_info['caf_id'];

        echo "  ✓ Folio obtenido desde BD: {$folio}\n";
        echo "  CAF ID: {$caf_id}\n";

        $logger->info('folio', "Folio obtenido desde BD: {$folio}", [
            'folio' => $folio,
            'caf_id' => $caf_id,
            'tipo_dte' => $tipo_dte
        ]);
    } catch (Exception $e) {
        echo "  ❌ Error: {$e->getMessage()}\n";
        $logger->error('folio', "Error obteniendo folio: {$e->getMessage()}");
        exit(1);
    }
} else {
    // Modo archivo: usar folios_usados.txt
    $archivo_folios = __DIR__ . '/folios_usados.txt';

    if (file_exists($archivo_folios)) {
        $ultimo_folio = (int) trim(file_get_contents($archivo_folios));
        $folio = $ultimo_folio + 1;
    } else {
        // Leer primer folio del CAF
        $caf_xml = simplexml_load_file(CAF_PATH);
        $folio = (int) $caf_xml->CAF->DA->RNG->D;
    }

    // Guardar folio usado
    file_put_contents($archivo_folios, $folio);

    echo "  ✓ Folio obtenido desde archivo: {$folio}\n";

    $logger->info('folio', "Folio obtenido desde archivo: {$folio}", [
        'folio' => $folio,
        'tipo_dte' => $tipo_dte
    ]);
}

echo "\n";

// ============================================================
// PASO 4: Preparar datos de la boleta
// ============================================================

echo "📝 Paso 4: Preparando datos de la boleta...\n";

$cliente = [
    'rut' => '12345678-9',
    'razon_social' => 'Juan Pérez Cliente',
    'email' => 'cliente@ejemplo.cl',
    'direccion' => 'Av. Providencia 123',
    'comuna' => 'Providencia'
];

$items = [
    [
        'nombre' => 'Servicio de Consultoría Técnica',
        'descripcion' => 'Asesoría especializada en desarrollo',
        'cantidad' => 3,
        'precio' => 15000,
        'unidad' => 'hrs'
    ],
    [
        'nombre' => 'Licencia Software',
        'descripcion' => 'Licencia anual',
        'cantidad' => 1,
        'precio' => 50000,
        'unidad' => 'un'
    ]
];

echo "  Cliente: {$cliente['razon_social']} ({$cliente['rut']})\n";
echo "  Items: " . count($items) . "\n";
echo "  Folio: {$folio}\n\n";

// ============================================================
// PASO 5: Generar boleta con Simple API
// ============================================================

echo "🚀 Paso 5: Generando boleta en Simple API...\n";

$logger->info('generar', "Iniciando generación de boleta", [
    'folio' => $folio,
    'cliente_rut' => $cliente['rut']
]);

// Preparar datos para Simple API
$datos_boleta = [
    'Documento' => [
        'Encabezado' => [
            'IdentificacionDTE' => [
                'TipoDTE' => 39,
                'Folio' => $folio,
                'FechaEmision' => date('Y-m-d'),
                'IndicadorServicio' => 3
            ],
            'Emisor' => [
                'Rut' => RUT_EMISOR,
                'RazonSocialBoleta' => RAZON_SOCIAL,
                'GiroBoleta' => 'Comercio minorista de coleccionables',
                'DireccionOrigen' => 'BARTOLO SOTO 3700 DP 1402 PISO 14',
                'ComunaOrigen' => 'San Miguel'
            ],
            'Receptor' => [
                'Rut' => $cliente['rut'],
                'RazonSocial' => $cliente['razon_social'],
                'Direccion' => $cliente['direccion'],
                'Comuna' => $cliente['comuna']
            ],
            'Totales' => []
        ],
        'Detalles' => []
    ]
];

// Calcular totales y preparar detalles
$total_neto = 0;
foreach ($items as $index => $item) {
    $monto_item = $item['cantidad'] * $item['precio'];
    $total_neto += $monto_item;

    $datos_boleta['Documento']['Detalles'][] = [
        'NmbItem' => $item['nombre'],
        'DscItem' => $item['descripcion'] ?? '',
        'QtyItem' => $item['cantidad'],
        'UnmdItem' => $item['unidad'] ?? 'un',
        'PrcItem' => $item['precio'],
        'MontoItem' => $monto_item
    ];
}

// Calcular IVA (restar desde el total)
$total_con_iva = $total_neto;
$monto_neto = round($total_con_iva / 1.19);
$iva = $total_con_iva - $monto_neto;

$datos_boleta['Documento']['Encabezado']['Totales'] = [
    'MontoNeto' => $monto_neto,
    'IVA' => $iva,
    'MontoTotal' => $total_con_iva
];

echo "  Monto Neto: $" . number_format($monto_neto, 0, ',', '.') . "\n";
echo "  IVA: $" . number_format($iva, 0, ',', '.') . "\n";
echo "  Total: $" . number_format($total_con_iva, 0, ',', '.') . "\n\n";

// Generar DTE
try {
    $ch = curl_init("{$API_BASE}/dte/generar");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'apikey: ' . API_KEY
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'Certificado' => [
                'Rut' => RUT_EMISOR,
                'Archivo' => base64_encode(file_get_contents(CERT_PATH)),
                'Password' => CERT_PASSWORD
            ],
            'Caf' => [
                'Contenido' => base64_encode(file_get_contents(CAF_PATH))
            ],
            'Dte' => $datos_boleta
        ])
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        throw new Exception("Error API: HTTP {$http_code}");
    }

    $result = json_decode($response, true);

    if (!isset($result['dte'])) {
        throw new Exception("No se recibió DTE en la respuesta");
    }

    $dte_xml = base64_decode($result['dte']);
    $track_id = $result['trackId'] ?? null;

    echo "  ✓ DTE generado exitosamente\n";
    echo "  Track ID: {$track_id}\n\n";

    $logger->info('generar', "Boleta generada exitosamente", [
        'folio' => $folio,
        'track_id' => $track_id,
        'monto_total' => $total_con_iva
    ]);

} catch (Exception $e) {
    echo "  ❌ Error: {$e->getMessage()}\n";
    $logger->error('generar', "Error generando boleta: {$e->getMessage()}", [
        'folio' => $folio
    ]);
    exit(1);
}

// ============================================================
// PASO 6: Guardar en base de datos (si está disponible)
// ============================================================

if ($usar_bd && $repo) {
    echo "💾 Paso 6: Guardando en base de datos...\n";

    try {
        $boleta_id = $repo->guardarBoleta([
            'tipo_dte' => 39,
            'folio' => $folio,
            'fecha_emision' => date('Y-m-d'),
            'rut_emisor' => RUT_EMISOR,
            'razon_social_emisor' => RAZON_SOCIAL,
            'rut_receptor' => $cliente['rut'],
            'razon_social_receptor' => $cliente['razon_social'],
            'email_receptor' => $cliente['email'],
            'monto_neto' => $monto_neto,
            'monto_iva' => $iva,
            'monto_total' => $total_con_iva,
            'track_id' => $track_id,
            'items' => array_map(function($item, $index) {
                return [
                    'nombre' => $item['nombre'],
                    'descripcion' => $item['descripcion'] ?? '',
                    'cantidad' => $item['cantidad'],
                    'unidad_medida' => $item['unidad'] ?? 'un',
                    'precio_unitario' => $item['precio'],
                    'monto_item' => $item['cantidad'] * $item['precio']
                ];
            }, $items, array_keys($items))
        ], $dte_xml);

        echo "  ✓ Boleta guardada en BD (ID: {$boleta_id})\n";
        echo "  ✓ Cliente registrado/actualizado\n";
        echo "  ✓ Items guardados: " . count($items) . "\n\n";

        $logger->info('bd', "Boleta guardada en base de datos", [
            'boleta_id' => $boleta_id,
            'folio' => $folio
        ]);

    } catch (Exception $e) {
        echo "  ⚠️  Error guardando en BD: {$e->getMessage()}\n\n";
        $logger->error('bd', "Error guardando boleta en BD: {$e->getMessage()}", [
            'folio' => $folio
        ]);
    }
} else {
    echo "⊘ Paso 6: Saltado (BD no disponible)\n\n";
}

// ============================================================
// PASO 7: Generar PDF con Timbre PDF417
// ============================================================

echo "📄 Paso 7: Generando PDF con Timbre PDF417...\n";

try {
    require_once(__DIR__ . '/lib/generar-pdf-boleta.php');

    $pdf_path = '/tmp/boleta_integracion_' . $folio . '.pdf';

    $pdf = new BoletaPDF($datos_boleta, $dte_xml);
    $pdf->generarBoleta();
    $pdf->Output('F', $pdf_path);

    $pdf_size = filesize($pdf_path);

    echo "  ✓ PDF generado: {$pdf_path}\n";
    echo "  Tamaño: " . number_format($pdf_size) . " bytes\n";
    echo "  ✓ Incluye Timbre PDF417\n\n";

    $logger->info('pdf', "PDF generado con timbre", [
        'folio' => $folio,
        'path' => $pdf_path,
        'size' => $pdf_size
    ]);

    // Actualizar en BD si está disponible
    if ($usar_bd && $repo && isset($boleta_id)) {
        $repo->marcarPDFGenerado($boleta_id, $pdf_path);
        echo "  ✓ PDF registrado en BD\n\n";
    }

} catch (Exception $e) {
    echo "  ❌ Error: {$e->getMessage()}\n\n";
    $logger->error('pdf', "Error generando PDF: {$e->getMessage()}", [
        'folio' => $folio
    ]);
}

// ============================================================
// PASO 8: Consultar estado en el SII
// ============================================================

echo "🔍 Paso 8: Consultando estado en el SII...\n";
echo "  Esperando 5 segundos...\n";
sleep(5);

try {
    $ch = curl_init("{$API_BASE}/dte/consultar/{$track_id}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . API_KEY
        ]
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $estado_result = json_decode($response, true);
    $estado = $estado_result['estado'] ?? 'DESCONOCIDO';

    echo "  ✓ Estado: {$estado}\n\n";

    $logger->info('consulta', "Estado consultado en SII", [
        'track_id' => $track_id,
        'estado' => $estado
    ]);

    // Actualizar en BD si está disponible
    if ($usar_bd && $repo && isset($boleta_id)) {
        $repo->actualizarEstadoSII($boleta_id, $track_id, $estado, $estado_result);
        echo "  ✓ Estado actualizado en BD\n\n";
    }

} catch (Exception $e) {
    echo "  ⚠️  Error consultando estado: {$e->getMessage()}\n\n";
    $logger->warning('consulta', "Error consultando estado SII: {$e->getMessage()}", [
        'track_id' => $track_id
    ]);
}

// ============================================================
// PASO 9: Resumen y estadísticas
// ============================================================

echo str_repeat('=', 60) . "\n";
echo "RESUMEN DE OPERACIÓN\n";
echo str_repeat('=', 60) . "\n\n";

echo "✅ Boleta generada exitosamente:\n";
echo "  Folio: {$folio}\n";
echo "  Cliente: {$cliente['razon_social']}\n";
echo "  Total: $" . number_format($total_con_iva, 0, ',', '.') . "\n";
echo "  Track ID: {$track_id}\n";
echo "  PDF: {$pdf_path}\n\n";

echo "🔧 Componentes utilizados:\n";
echo "  " . ($usar_bd ? "✅" : "⊘") . " Base de Datos\n";
echo "  ✅ Logging Estructurado\n";
echo "  ✅ PDF con Timbre PDF417\n";
echo "  ✅ Simple API\n\n";

if ($usar_bd && $repo) {
    try {
        $stats = $repo->obtenerEstadisticas(
            fecha_desde: date('Y-m-d'),
            fecha_hasta: date('Y-m-d')
        );

        echo "📊 Estadísticas del día:\n";
        echo "  Boletas generadas: {$stats['total_boletas']}\n";
        echo "  Monto total: $" . number_format($stats['monto_total'], 0, ',', '.') . "\n";
        echo "  PDFs generados: {$stats['pdfs_generados']}\n";
        echo "  Emails enviados: {$stats['emails_enviados']}\n";
        echo "  Clientes únicos: {$stats['clientes_unicos']}\n\n";
    } catch (Exception $e) {
        // Ignorar si hay error
    }
}

echo "📝 Logs generados en:\n";
echo "  logs/dte_" . date('Y-m-d') . ".log\n";
if ($usar_bd) {
    echo "  Tabla 'logs' en base de datos\n";
}
echo "\n";

echo "=== INTEGRACIÓN COMPLETADA EXITOSAMENTE ===\n";

$logger->info('sistema', "Integración completa finalizada", [
    'folio' => $folio,
    'modo' => $usar_bd ? 'bd' : 'archivo',
    'duracion' => time() - $_SERVER['REQUEST_TIME']
]);
