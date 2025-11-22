#!/usr/bin/env php
<?php
/**
 * Script simple para generar boleta, enviarla y consultar el estado
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuración
$API_KEY = '9794-N370-6392-6913-8052';
$API_BASE = 'https://api.simpleapi.cl';
$CERT_PATH = '/home/user/archivos/16694181-4.pfx';
$CAF_PATH = '/home/user/archivos/FoliosSII78274225391889202511161321.xml';
$RUT_EMISOR = '78274225-6';

// Leer folio actual
$folios_usados_file = __DIR__ . '/folios_usados.txt';
$folio_actual = file_exists($folios_usados_file) ? (int) trim(file_get_contents($folios_usados_file)) : 1889;
$folio_actual++;

echo "\n═══════════════════════════════════════════════════════════\n";
echo "  TEST: Generar, Enviar y Consultar Estado de Boleta\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// ============================================================
// PASO 1: Generar DTE
// ============================================================
echo "📝 PASO 1: Generando DTE con folio $folio_actual...\n";

$documento_data = [
    'Documento' => [
        'Encabezado' => [
            'IdentificacionDTE' => [
                'TipoDTE' => 39,
                'Folio' => $folio_actual,
                'FechaEmision' => date('Y-m-d')
            ],
            'Emisor' => [
                'Rut' => $RUT_EMISOR,
                'RazonSocial' => 'AKIBARA SPA',
                'GiroBoleta' => 'Servicios de Tecnología',
                'DireccionOrigen' => 'Av. Providencia 1234',
                'ComunaOrigen' => 'Providencia'
            ],
            'Receptor' => [
                'Rut' => '66666666-6',
                'RazonSocial' => 'Cliente de Prueba',
                'Direccion' => 'Av. Prueba 123',
                'Comuna' => 'Santiago'
            ],
            'Totales' => [
                'MontoNeto' => 100000,
                'IVA' => 19000,
                'MontoTotal' => 119000
            ]
        ],
        'Detalles' => [
            [
                'NmbItem' => 'Producto de Prueba - Consulta Estado',
                'Cantidad' => 1,
                'Precio' => 100000,
                'MontoItem' => 100000
            ]
        ]
    ],
    'Certificado' => [
        'Rut' => '16694181-4',
        'Password' => '5605'
    ]
];

// Crear multipart para generar DTE
$boundary = '----WebKitFormBoundary' . uniqid();
$eol = "\r\n";

$body = '';

// Parte 1: input (JSON)
$body .= '--' . $boundary . $eol;
$body .= 'Content-Disposition: form-data; name="input"' . $eol;
$body .= 'Content-Type: text/plain' . $eol . $eol;
$body .= json_encode($documento_data, JSON_UNESCAPED_UNICODE) . $eol;

// Parte 2: files (certificado)
$body .= '--' . $boundary . $eol;
$body .= 'Content-Disposition: form-data; name="files"; filename="' . basename($CERT_PATH) . '"' . $eol;
$body .= 'Content-Type: application/octet-stream' . $eol . $eol;
$body .= file_get_contents($CERT_PATH) . $eol;

// Parte 3: files2 (CAF)
$body .= '--' . $boundary . $eol;
$body .= 'Content-Disposition: form-data; name="files2"; filename="' . basename($CAF_PATH) . '"' . $eol;
$body .= 'Content-Type: application/octet-stream' . $eol . $eol;
$body .= file_get_contents($CAF_PATH) . $eol;

$body .= '--' . $boundary . '--' . $eol;

// Generar DTE
$ch = curl_init($API_BASE . '/api/v1/dte/generar');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_HTTPHEADER => [
        'Authorization: ' . $API_KEY,
        'Content-Type: multipart/form-data; boundary=' . $boundary,
    ],
    CURLOPT_TIMEOUT => 60
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    echo "❌ Error al generar DTE (HTTP $http_code)\n";
    echo "Respuesta: " . substr($response, 0, 500) . "\n";
    exit(1);
}

echo "✅ DTE generado exitosamente\n";

// Mostrar respuesta raw para debug
if (strlen($response) > 2000) {
    echo "📄 Respuesta (primeros 500 caracteres): " . substr($response, 0, 500) . "...\n\n";
} else {
    echo "📄 Respuesta completa:\n" . $response . "\n\n";
}

// La respuesta podría ser el XML directamente
if (strpos($response, '<?xml') === 0) {
    $dte_xml = $response;
    echo "✅ XML recibido directamente\n";
} else {
    $dte_data = json_decode($response, true);

    if (!isset($dte_data['xml']) && !isset($dte_data['dte_xml']) && !isset($dte_data['DTE'])) {
        echo "❌ No se recibió XML del DTE\n";
        echo "Respuesta JSON: " . json_encode($dte_data, JSON_PRETTY_PRINT) . "\n";
        exit(1);
    }

    $dte_xml = $dte_data['xml'] ?? $dte_data['dte_xml'] ?? $dte_data['DTE'];
}

// Guardar folio usado
file_put_contents($folios_usados_file, $folio_actual);

// ============================================================
// PASO 2: Enviar al SII
// ============================================================
echo "\n🚀 PASO 2: Enviando al SII...\n";

// Crear archivo temporal para el DTE XML
$temp_dte = tempnam(sys_get_temp_dir(), 'dte_') . '.xml';
file_put_contents($temp_dte, $dte_xml);

// Crear multipart para enviar
$boundary2 = '----WebKitFormBoundary' . uniqid();
$body2 = '';

// Parte 1: certificado
$body2 .= '--' . $boundary2 . $eol;
$body2 .= 'Content-Disposition: form-data; name="files"; filename="' . basename($CERT_PATH) . '"' . $eol;
$body2 .= 'Content-Type: application/octet-stream' . $eol . $eol;
$body2 .= file_get_contents($CERT_PATH) . $eol;

// Parte 2: XML del DTE
$body2 .= '--' . $boundary2 . $eol;
$body2 .= 'Content-Disposition: form-data; name="files2"; filename="dte.xml"' . $eol;
$body2 .= 'Content-Type: application/xml' . $eol . $eol;
$body2 .= $dte_xml . $eol;

$body2 .= '--' . $boundary2 . '--' . $eol;

// Enviar
$ch = curl_init($API_BASE . '/api/v1/dte/enviar');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body2,
    CURLOPT_HTTPHEADER => [
        'Authorization: ' . $API_KEY,
        'Content-Type: multipart/form-data; boundary=' . $boundary2,
    ],
    CURLOPT_TIMEOUT => 60
]);

$response2 = curl_exec($ch);
$http_code2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

@unlink($temp_dte);

if ($http_code2 !== 200) {
    echo "❌ Error al enviar al SII (HTTP $http_code2)\n";
    echo "Respuesta: " . substr($response2, 0, 500) . "\n";
    exit(1);
}

$envio_data = json_decode($response2, true);

if (!isset($envio_data['track_id']) && !isset($envio_data['trackId'])) {
    echo "❌ No se recibió track_id del envío\n";
    echo "Respuesta: " . json_encode($envio_data, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}

$track_id = $envio_data['track_id'] ?? $envio_data['trackId'];

echo "✅ Enviado exitosamente al SII\n";
echo "📋 Track ID: $track_id\n";

// ============================================================
// PASO 3: Consultar Estado
// ============================================================
echo "\n🔍 PASO 3: Consultando estado del envío...\n";
echo "⏳ Esperando 5 segundos para que el SII procese...\n";
sleep(5);

$ch = curl_init($API_BASE . '/api/v1/dte/estado/' . $track_id);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: ' . $API_KEY,
    ],
    CURLOPT_TIMEOUT => 30
]);

$response3 = curl_exec($ch);
$http_code3 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code3 !== 200) {
    echo "❌ Error al consultar estado (HTTP $http_code3)\n";
    echo "Respuesta: " . substr($response3, 0, 500) . "\n";
    exit(1);
}

$estado_data = json_decode($response3, true);

echo "✅ Consulta de estado exitosa\n\n";

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  RESULTADO DE LA CONSULTA DE ESTADO                       ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

if (is_array($estado_data)) {
    foreach ($estado_data as $key => $value) {
        if (is_array($value) || is_object($value)) {
            echo str_pad($key, 20) . ": " . json_encode($value, JSON_PRETTY_PRINT) . "\n";
        } else {
            echo str_pad($key, 20) . ": " . $value . "\n";
        }
    }
} else {
    echo $response3 . "\n";
}

echo "\n═══════════════════════════════════════════════════════════\n";
echo "✅ Prueba completada exitosamente\n";
echo "═══════════════════════════════════════════════════════════\n\n";

echo "📋 Resumen:\n";
echo "   Folio: $folio_actual\n";
echo "   Track ID: $track_id\n";
echo "   Estado consultado: " . ($estado_data['estado'] ?? $estado_data['Estado'] ?? 'Ver arriba') . "\n\n";
