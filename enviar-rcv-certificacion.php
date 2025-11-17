<?php
/**
 * Enviar RCV (Registro de Compras y Ventas) al SII
 * SOLO para ambiente de CERTIFICACIÓN
 *
 * Nota: En PRODUCCIÓN, el RCV de boletas NO es obligatorio desde 2024
 *       En CERTIFICACIÓN, SÍ es requerido para pasar las pruebas del SII
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once(__DIR__ . '/lib/VisualHelper.php');

$v = VisualHelper::getInstance();
$v->limpiar();

echo "\n";
$v->titulo("Envío de RCV al SII - Sistema Configurable");
echo "\n";

// ============================================================================
// CARGAR CONFIGURACIÓN
// ============================================================================

$config_file = __DIR__ . '/config-rcv.php';
if (!file_exists($config_file)) {
    $v->mensaje('error', 'Archivo de configuración no encontrado: config-rcv.php');
    exit(1);
}

$config = require $config_file;

// ============================================================================
// CONFIGURACIÓN DE API Y CERTIFICADO
// ============================================================================

define('API_BASE', 'https://api.simple.cl');
define('API_KEY', '9794-N370-6392-6913-8052');
define('CERT_PATH', __DIR__ . '/16694181-4.pfx');
define('CERT_PASSWORD', 'Santiaguino.2017');
define('RUT_EMISOR', '76063822-6');
define('AMBIENTE', 0); // 0 = Certificación, 1 = Producción

// ============================================================================
// VALIDAR CONFIGURACIÓN
// ============================================================================

$ambiente_nombre = AMBIENTE === 0 ? 'certificacion' : 'produccion';

// Verificar si el envío está habilitado globalmente
if (!$config['envio_habilitado']) {
    $v->mensaje('warning', '⚠️  Envío de RCV DESHABILITADO en configuración');
    echo "\n";
    echo "  El envío de RCV está deshabilitado en config-rcv.php\n";
    echo "  Solo se generará el XML para respaldo.\n";
    echo "\n";
    $envio_permitido = false;
} else {
    // Verificar si el ambiente actual está permitido
    if (!in_array($ambiente_nombre, $config['ambientes_permitidos'])) {
        $v->mensaje('warning', '⚠️  Envío NO permitido en ambiente: ' . strtoupper($ambiente_nombre));
        echo "\n";
        echo "  El ambiente '$ambiente_nombre' no está en la lista de ambientes permitidos.\n";
        echo "  Ambientes permitidos: " . implode(', ', $config['ambientes_permitidos']) . "\n";
        echo "  Solo se generará el XML para respaldo.\n";
        echo "\n";
        $envio_permitido = false;
    } else {
        $envio_permitido = true;

        // Mostrar advertencia si estamos en producción
        if (AMBIENTE === 1 && $config['alertas']['advertir_produccion']) {
            $v->mensaje('warning', '⚠️  ADVERTENCIA: Estás en PRODUCCIÓN');
            echo "\n";
            echo "  Desde 2024, el RCV de boletas NO es obligatorio en PRODUCCIÓN.\n";
            echo "  El SII obtiene la información directamente de cada boleta enviada.\n";
            echo "\n";
            echo "  ¿Estás seguro de que quieres continuar?\n";
            echo "  Presiona Ctrl+C para cancelar o Enter para continuar...\n";
            fgets(STDIN);
        }
    }
}

$v->lista([
    ['texto' => 'Ambiente', 'valor' => strtoupper($ambiente_nombre)],
    ['texto' => 'Envío habilitado', 'valor' => $config['envio_habilitado'] ? 'SÍ' : 'NO'],
    ['texto' => 'Envío permitido', 'valor' => $envio_permitido ? 'SÍ' : 'NO'],
]);
echo "\n";

// ============================================================================
// PARÁMETROS DE ENTRADA
// ============================================================================

// Período del RCV (ejemplo: Noviembre 2024)
$periodo_desde = isset($argv[1]) ? $argv[1] : date('Y-m-01', strtotime('-1 month'));
$periodo_hasta = isset($argv[2]) ? $argv[2] : date('Y-m-t', strtotime('-1 month'));

$v->subtitulo("Parámetros del RCV");
$v->lista([
    ['texto' => 'Período desde', 'valor' => $periodo_desde],
    ['texto' => 'Período hasta', 'valor' => $periodo_hasta],
    ['texto' => 'Ambiente', 'valor' => 'CERTIFICACIÓN'],
]);

echo "\n";

// ============================================================================
// GENERAR XML DEL RCV
// ============================================================================

$v->subtitulo("Generando XML del RCV");

// Buscar todas las boletas del período
$xmls_dir = __DIR__ . '/xmls';
$pdfs_dir = __DIR__ . '/pdfs';

if (!is_dir($xmls_dir)) {
    $v->mensaje('error', "Directorio de XMLs no existe: $xmls_dir");
    exit(1);
}

// Buscar XMLs de boletas en el rango de fechas
$xml_files = glob($xmls_dir . '/boleta_*.xml');

$documentos = [];
$totales_por_tipo = [];

foreach ($xml_files as $xml_file) {
    $xml_content = file_get_contents($xml_file);
    $xml = simplexml_load_string($xml_content);

    if (!$xml) {
        continue;
    }

    // Extraer datos del documento
    $fecha = (string) $xml->Documento->Encabezado->IdDoc->FchEmis;

    // Verificar si está en el rango
    if ($fecha < $periodo_desde || $fecha > $periodo_hasta) {
        continue;
    }

    $tipo_dte = (int) $xml->Documento->Encabezado->IdDoc->TipoDTE;
    $folio = (int) $xml->Documento->Encabezado->IdDoc->Folio;
    $monto_total = (int) $xml->Documento->Encabezado->Totales->MntTotal;

    // Calcular neto e IVA
    $monto_neto = round($monto_total / 1.19);
    $monto_iva = $monto_total - $monto_neto;

    // RUT receptor
    $rut_receptor = (string) $xml->Documento->Encabezado->Receptor->RUTRecep;
    if (empty($rut_receptor)) {
        $rut_receptor = '66666666-6'; // Consumidor final
    }

    $razon_social = (string) $xml->Documento->Encabezado->Receptor->RznSocRecep;
    if (empty($razon_social)) {
        $razon_social = 'Consumidor Final';
    }

    $documentos[] = [
        'tipo' => $tipo_dte,
        'folio' => $folio,
        'fecha' => $fecha,
        'rut_receptor' => $rut_receptor,
        'razon_social' => $razon_social,
        'monto_neto' => $monto_neto,
        'monto_iva' => $monto_iva,
        'monto_total' => $monto_total
    ];

    // Acumular en totales por tipo
    if (!isset($totales_por_tipo[$tipo_dte])) {
        $totales_por_tipo[$tipo_dte] = [
            'cantidad' => 0,
            'neto' => 0,
            'iva' => 0,
            'total' => 0
        ];
    }

    $totales_por_tipo[$tipo_dte]['cantidad']++;
    $totales_por_tipo[$tipo_dte]['neto'] += $monto_neto;
    $totales_por_tipo[$tipo_dte]['iva'] += $monto_iva;
    $totales_por_tipo[$tipo_dte]['total'] += $monto_total;
}

if (empty($documentos)) {
    $v->mensaje('error', 'No se encontraron documentos en el rango de fechas especificado');
    exit(1);
}

$v->mensaje('success', 'Documentos encontrados: ' . count($documentos));
echo "\n";

foreach ($totales_por_tipo as $tipo => $totales) {
    $nombre_tipo = $tipo == 39 ? 'Boletas' : ($tipo == 41 ? 'Boletas Exentas' : "Tipo $tipo");
    echo "  • $nombre_tipo: {$totales['cantidad']} docs, Total: $" . number_format($totales['total'], 0, ',', '.') . "\n";
}

echo "\n";

// ============================================================================
// CONSTRUIR XML DEL LIBRO
// ============================================================================

$v->subtitulo("Construyendo XML del Libro");

$periodo_tributario = date('Y-m', strtotime($periodo_desde));

$xml_rcv = '<?xml version="1.0" encoding="ISO-8859-1"?>' . "\n";
$xml_rcv .= '<LibroCompraVenta xmlns="http://www.sii.cl/SiiDte" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sii.cl/SiiDte LibroCV_v10.xsd" version="1.0">' . "\n";
$xml_rcv .= '<EnvioLibro ID="Envio">' . "\n";

// Carátula
$xml_rcv .= '<Caratula>' . "\n";
$xml_rcv .= '<RutEmisorLibro>' . RUT_EMISOR . '</RutEmisorLibro>' . "\n";
$xml_rcv .= '<RutEnvia>' . RUT_EMISOR . '</RutEnvia>' . "\n";
$xml_rcv .= '<PeriodoTributario>' . $periodo_tributario . '</PeriodoTributario>' . "\n";
$xml_rcv .= '<FchResol>2025-11-16</FchResol>' . "\n";
$xml_rcv .= '<NroResol>0</NroResol>' . "\n";
$xml_rcv .= '<TipoOperacion>VENTA</TipoOperacion>' . "\n";
$xml_rcv .= '<TipoLibro>ESPECIAL</TipoLibro>' . "\n";
$xml_rcv .= '<TipoEnvio>TOTAL</TipoEnvio>' . "\n";
$xml_rcv .= '<FolioNotificacion>1</FolioNotificacion>' . "\n";
$xml_rcv .= '</Caratula>' . "\n";

// Resumen por tipo
foreach ($totales_por_tipo as $tipo => $totales) {
    $xml_rcv .= '<ResumenPeriodo>' . "\n";
    $xml_rcv .= '<TpoDoc>' . $tipo . '</TpoDoc>' . "\n";
    $xml_rcv .= '<TotDoc>' . $totales['cantidad'] . '</TotDoc>' . "\n";
    $xml_rcv .= '<TotMntNeto>' . round($totales['neto']) . '</TotMntNeto>' . "\n";
    $xml_rcv .= '<TotMntIVA>' . round($totales['iva']) . '</TotMntIVA>' . "\n";
    $xml_rcv .= '<TotMntTotal>' . round($totales['total']) . '</TotMntTotal>' . "\n";
    $xml_rcv .= '</ResumenPeriodo>' . "\n";
}

// Detalle de cada documento
foreach ($documentos as $doc) {
    $xml_rcv .= '<Detalle>' . "\n";
    $xml_rcv .= '<TpoDoc>' . $doc['tipo'] . '</TpoDoc>' . "\n";
    $xml_rcv .= '<Folio>' . $doc['folio'] . '</Folio>' . "\n";
    $xml_rcv .= '<FchDoc>' . $doc['fecha'] . '</FchDoc>' . "\n";
    $xml_rcv .= '<RUTDoc>' . htmlspecialchars($doc['rut_receptor']) . '</RUTDoc>' . "\n";
    $xml_rcv .= '<RznSoc>' . htmlspecialchars($doc['razon_social']) . '</RznSoc>' . "\n";
    $xml_rcv .= '<MntNeto>' . $doc['monto_neto'] . '</MntNeto>' . "\n";
    $xml_rcv .= '<TasaIVA>19</TasaIVA>' . "\n";
    $xml_rcv .= '<IVA>' . $doc['monto_iva'] . '</IVA>' . "\n";
    $xml_rcv .= '<MntTotal>' . $doc['monto_total'] . '</MntTotal>' . "\n";
    $xml_rcv .= '</Detalle>' . "\n";
}

$xml_rcv .= '</EnvioLibro>' . "\n";
$xml_rcv .= '</LibroCompraVenta>';

// Guardar XML generado
$rcv_dir = __DIR__ . '/rcv';
if (!is_dir($rcv_dir)) {
    mkdir($rcv_dir, 0755, true);
}

$rcv_filename = "rcv_ventas_{$periodo_tributario}.xml";
$rcv_path = $rcv_dir . '/' . $rcv_filename;
file_put_contents($rcv_path, $xml_rcv);

$v->mensaje('success', "XML del libro generado");
$v->lista([
    ['texto' => 'Archivo', 'valor' => $rcv_filename],
    ['texto' => 'Tamaño', 'valor' => number_format(strlen($xml_rcv)) . ' bytes'],
]);

echo "\n";

// ============================================================================
// ENVIAR RCV AL SII VÍA SIMPLE API (SI ESTÁ PERMITIDO)
// ============================================================================

if (!$envio_permitido) {
    $v->mensaje('info', '✓ XML generado pero NO se enviará al SII (según configuración)');
    echo "\n";
    echo "  Archivo: $rcv_filename\n";
    echo "  Para enviar al SII, edita config-rcv.php\n";
    echo "\n";

    // Guardar log si está habilitado
    if ($config['log']['habilitar_log']) {
        $log_entry = date('Y-m-d H:i:s') . " - RCV generado pero NO enviado - Período: $periodo_tributario - Docs: " . count($documentos) . " - Archivo: $rcv_filename\n";
        file_put_contents($config['log']['archivo_log'], $log_entry, FILE_APPEND);
    }

    echo "Proceso completado (solo generación de XML) - " . date('Y-m-d H:i:s') . "\n";
    echo "\n";
    exit(0);
}

$v->subtitulo("Enviando RCV al SII");

$eol = "\r\n";
$boundary = '----WebKitFormBoundary' . md5(time());
$body = '';

// Configuración
$config = [
    'Certificado' => [
        'Rut' => '16694181-4',
        'Password' => CERT_PASSWORD
    ],
    'Ambiente' => AMBIENTE
];

$body .= '--' . $boundary . $eol;
$body .= 'Content-Disposition: form-data; name="input"' . $eol . $eol;
$body .= json_encode($config) . $eol;

// Certificado
$body .= '--' . $boundary . $eol;
$body .= 'Content-Disposition: form-data; name="files"; filename="certificado.pfx"' . $eol;
$body .= 'Content-Type: application/x-pkcs12' . $eol . $eol;
$body .= file_get_contents(CERT_PATH) . $eol;

// XML del libro
$body .= '--' . $boundary . $eol;
$body .= 'Content-Disposition: form-data; name="files"; filename="libro.xml"' . $eol;
$body .= 'Content-Type: text/xml' . $eol . $eol;
$body .= $xml_rcv . $eol;

$body .= '--' . $boundary . '--' . $eol;

$ch = curl_init(API_BASE . '/api/v1/rcv/enviar');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_HTTPHEADER => [
        'Authorization: ' . API_KEY,
        'Content-Type: multipart/form-data; boundary=' . $boundary,
        'Content-Length: ' . strlen($body)
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_TIMEOUT => 90,
]);

$v->cargando("Enviando al SII (Certificación)", 3);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "\n";

if ($http_code != 200) {
    $v->mensaje('error', "Error HTTP $http_code al enviar RCV");
    echo "\nRespuesta del servidor:\n$response\n";
    exit(1);
}

// Parsear respuesta
$result = json_decode($response, true);

if (!$result) {
    // Intentar como XML
    $xml_response = simplexml_load_string($response);
    if ($xml_response) {
        $track_id = (string) $xml_response->trackId;
    }
}

if (isset($result['trackId'])) {
    $track_id = $result['trackId'];
} elseif (isset($result['track_id'])) {
    $track_id = $result['track_id'];
}

if (empty($track_id)) {
    $v->mensaje('warning', 'Respuesta del SII sin Track ID');
    echo "\nRespuesta:\n$response\n";
} else {
    $v->mensaje('success', "RCV enviado exitosamente al SII");
    $v->lista([
        ['texto' => 'Track ID', 'valor' => $track_id],
        ['texto' => 'Período', 'valor' => $periodo_tributario],
        ['texto' => 'Documentos', 'valor' => count($documentos)],
    ]);

    // Guardar Track ID
    $track_file = $rcv_dir . "/track_rcv_{$periodo_tributario}.txt";
    file_put_contents($track_file, $track_id);
}

echo "\n";
$v->mensaje('info', 'El RCV fue enviado en ambiente de CERTIFICACIÓN como es requerido.');
echo "\n";

echo "═══════════════════════════════════════════════════════════════════════\n";
echo "\n";

// ============================================================================
// CONSULTAR ESTADO (OPCIONAL)
// ============================================================================

if (!empty($track_id)) {
    echo "\n";
    $v->subtitulo("Consultando Estado del RCV");
    $v->mensaje('info', "Esperando 10 segundos antes de consultar...");
    sleep(10);

    $ch2 = curl_init(API_BASE . "/api/v1/rcv/$track_id/estado");
    curl_setopt_array($ch2, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . API_KEY,
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30,
    ]);

    $v->cargando("Consultando estado Track ID $track_id", 1);

    $response_estado = curl_exec($ch2);
    $http_code_estado = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);

    echo "\n";

    if ($http_code_estado == 200) {
        $estado = json_decode($response_estado, true);

        if ($estado && isset($estado['estado'])) {
            $v->mensaje('success', "Estado: " . $estado['estado']);

            if (isset($estado['detalle'])) {
                echo "\nDetalle: {$estado['detalle']}\n";
            }
        } else {
            echo "Respuesta:\n$response_estado\n";
        }
    } else {
        $v->mensaje('warning', 'No se pudo consultar el estado (intenta más tarde)');
    }
}

echo "\n";
echo "Proceso completado - " . date('Y-m-d H:i:s') . "\n";
echo "\n";
