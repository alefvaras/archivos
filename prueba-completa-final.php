#!/usr/bin/env php
<?php
/**
 * PRUEBA COMPLETA FINAL - SIN LIMITACIONES
 * Usando la API correcta de SimpleAPI
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuración
$API_KEY = '9794-N370-6392-6913-8052';
$API_BASE = 'https://api.simpleapi.cl';
$CERT_PATH = '/home/user/archivos/16694181-4.pfx';
$CAF_PATH = '/home/user/archivos/FoliosSII78274225391889202511161321.xml';
$RUT_EMISOR = '78274225-6';
$CERT_PASSWORD = '5605';

// Leer folio actual
$folios_usados_file = __DIR__ . '/folios_usados.txt';
$folio_actual = file_exists($folios_usados_file) ? (int) trim(file_get_contents($folios_usados_file)) : 1889;
$folio_actual++;

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "  PRUEBA COMPLETA FINAL - SISTEMA DE FACTURACIÓN ELECTRÓNICA\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n";
echo "API: SimpleAPI DTE v1\n";
echo "Ambiente: Certificación SII\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// ============================================================
// PASO 1: Generar DTE
// ============================================================
echo "📝 PASO 1: Generando DTE (Boleta 39) con folio $folio_actual...\n\n";

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
                'RazonSocial' => 'Cliente de Prueba SII',
                'Direccion' => 'Av. Prueba 123',
                'Comuna' => 'Santiago'
            ],
            'Totales' => [
                'MontoNeto' => 150000,
                'IVA' => 28500,
                'MontoTotal' => 178500
            ]
        ],
        'Detalles' => [
            [
                'NmbItem' => 'Servicio de Consultoría - Prueba Completa',
                'Cantidad' => 3,
                'Precio' => 50000,
                'MontoItem' => 150000
            ]
        ]
    ],
    'Certificado' => [
        'Rut' => '16694181-4',
        'Password' => $CERT_PASSWORD
    ]
];

echo "Items:\n";
echo "  - Servicio de Consultoría - Prueba Completa\n";
echo "    Cantidad: 3 x \$50.000 = \$150.000\n\n";
echo "Neto:  \$150.000\n";
echo "IVA:   \$28.500\n";
echo "Total: \$178.500\n\n";

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

// Generar DTE con endpoint correcto
$ch = curl_init($API_BASE . '/api/v1/DTE/generar');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_HTTPHEADER => [
        'Authorization: ' . $API_KEY,
        'Content-Type: multipart/form-data; boundary=' . $boundary,
    ],
    CURLOPT_TIMEOUT => 60,
    CURLOPT_VERBOSE => false
]);

echo "Enviando a: {$API_BASE}/api/v1/DTE/generar\n";
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    die("❌ Error cURL: {$curl_error}\n");
}

if ($http_code !== 200) {
    echo "❌ Error al generar DTE (HTTP $http_code)\n";
    echo "Respuesta: " . substr($response, 0, 1000) . "\n";
    exit(1);
}

echo "✅ DTE generado exitosamente!\n\n";

// La respuesta es el XML directamente
if (strpos($response, '<?xml') === 0) {
    $dte_xml = $response;
    echo "✅ XML recibido:\n";
    echo substr($dte_xml, 0, 500) . "...\n\n";
} else {
    $dte_data = json_decode($response, true);
    if (isset($dte_data['xml']) || isset($dte_data['dte_xml']) || isset($dte_data['DTE'])) {
        $dte_xml = $dte_data['xml'] ?? $dte_data['dte_xml'] ?? $dte_data['DTE'];
    } else {
        echo "❌ Respuesta inesperada:\n";
        echo json_encode($dte_data, JSON_PRETTY_PRINT) . "\n";
        exit(1);
    }
}

// Guardar folio usado
file_put_contents($folios_usados_file, $folio_actual);

// Guardar XML generado
$xml_file = __DIR__ . '/xmls/dte-' . $folio_actual . '.xml';
@mkdir(dirname($xml_file), 0755, true);
file_put_contents($xml_file, $dte_xml);
echo "📄 XML guardado en: $xml_file\n\n";

// ============================================================
// PASO 2: Generar Sobre de Envío
// ============================================================
echo "═══════════════════════════════════════════════════════════════\n";
echo "📦 PASO 2: Generando sobre de envío...\n\n";

$sobre_data = [
    'SetDTE' => [
        'Caratula' => [
            'RutEmisor' => $RUT_EMISOR,
            'RutEnvia' => '16694181-4',
            'FechResol' => '2014-12-10',
            'NumResol' => '80',
            'SubTotDTE' => [
                [
                    'TipoDTE' => 39,
                    'NroDTE' => 1
                ]
            ]
        ]
    ],
    'Certificado' => [
        'Rut' => '16694181-4',
        'Password' => $CERT_PASSWORD
    ]
];

$boundary2 = '----WebKitFormBoundary' . uniqid();
$body2 = '';

// input
$body2 .= '--' . $boundary2 . $eol;
$body2 .= 'Content-Disposition: form-data; name="input"' . $eol;
$body2 .= 'Content-Type: text/plain' . $eol . $eol;
$body2 .= json_encode($sobre_data, JSON_UNESCAPED_UNICODE) . $eol;

// certificado
$body2 .= '--' . $boundary2 . $eol;
$body2 .= 'Content-Disposition: form-data; name="files"; filename="' . basename($CERT_PATH) . '"' . $eol;
$body2 .= 'Content-Type: application/octet-stream' . $eol . $eol;
$body2 .= file_get_contents($CERT_PATH) . $eol;

// DTE XML como archivo
$body2 .= '--' . $boundary2 . $eol;
$body2 .= 'Content-Disposition: form-data; name="files2"; filename="dte.xml"' . $eol;
$body2 .= 'Content-Type: application/xml' . $eol . $eol;
$body2 .= $dte_xml . $eol;

$body2 .= '--' . $boundary2 . '--' . $eol;

// Generar sobre
$ch = curl_init($API_BASE . '/api/v1/Envio/generar');
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

echo "Enviando a: {$API_BASE}/api/v1/Envio/generar\n";
$response_sobre = curl_exec($ch);
$http_code_sobre = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code_sobre !== 200) {
    echo "❌ Error al generar sobre (HTTP $http_code_sobre)\n";
    echo "Respuesta: " . substr($response_sobre, 0, 1000) . "\n";
    exit(1);
}

echo "✅ Sobre generado exitosamente!\n\n";

$sobre_xml = $response_sobre;
if (strpos($sobre_xml, '<?xml') !== 0) {
    $sobre_decode = json_decode($response_sobre, true);
    $sobre_xml = $sobre_decode['xml'] ?? $sobre_decode['sobre'] ?? $response_sobre;
}

// ============================================================
// PASO 3: Enviar al SII
// ============================================================
echo "═══════════════════════════════════════════════════════════════\n";
echo "🚀 PASO 3: Enviando al SII...\n\n";

$envio_data = [
    'Sobre' => $sobre_xml,
    'Certificado' => [
        'Rut' => '16694181-4',
        'Password' => $CERT_PASSWORD
    ]
];

$boundary3 = '----WebKitFormBoundary' . uniqid();
$body3 = '';

// input
$body3 .= '--' . $boundary3 . $eol;
$body3 .= 'Content-Disposition: form-data; name="input"' . $eol;
$body3 .= 'Content-Type: text/plain' . $eol . $eol;
$body3 .= json_encode($envio_data, JSON_UNESCAPED_UNICODE) . $eol;

// certificado
$body3 .= '--' . $boundary3 . $eol;
$body3 .= 'Content-Disposition: form-data; name="files"; filename="' . basename($CERT_PATH) . '"' . $eol;
$body3 .= 'Content-Type: application/octet-stream' . $eol . $eol;
$body3 .= file_get_contents($CERT_PATH) . $eol;

$body3 .= '--' . $boundary3 . '--' . $eol;

// Enviar al SII
$ch = curl_init($API_BASE . '/api/v1/Envio/enviar');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body3,
    CURLOPT_HTTPHEADER => [
        'Authorization: ' . $API_KEY,
        'Content-Type: multipart/form-data; boundary=' . $boundary3,
    ],
    CURLOPT_TIMEOUT => 60
]);

echo "Enviando a: {$API_BASE}/api/v1/Envio/enviar\n";
$response_envio = curl_exec($ch);
$http_code_envio = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code_envio !== 200) {
    echo "❌ Error al enviar al SII (HTTP $http_code_envio)\n";
    echo "Respuesta: " . substr($response_envio, 0, 1000) . "\n";

    // Guardar respuesta para análisis
    file_put_contents(__DIR__ . '/logs/error-envio.txt', $response_envio);
    exit(1);
}

echo "✅ Enviado al SII exitosamente!\n\n";

$envio_result = json_decode($response_envio, true);

$track_id = $envio_result['trackid'] ?? $envio_result['trackId'] ?? $envio_result['TRACKID'] ?? null;

if (!$track_id) {
    echo "⚠️ No se recibió Track ID. Respuesta:\n";
    echo json_encode($envio_result, JSON_PRETTY_PRINT) . "\n\n";

    // Buscar en XML si es respuesta XML
    if (strpos($response_envio, '<?xml') === 0) {
        $xml_envio = simplexml_load_string($response_envio);
        $track_id = (string) ($xml_envio->TRACKID ?? $xml_envio->TrackID ?? '');
        if ($track_id) {
            echo "✅ Track ID encontrado en XML: $track_id\n\n";
        }
    }
}

if (!$track_id) {
    echo "❌ No se pudo obtener Track ID\n";
    exit(1);
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "📋 TRACK ID: $track_id\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// ============================================================
// PASO 4: Consultar Estado
// ============================================================
echo "🔍 PASO 4: Consultando estado en el SII...\n";
echo "⏳ Esperando 10 segundos para que el SII procese...\n\n";

for ($i = 10; $i > 0; $i--) {
    echo "$i... ";
    flush();
    sleep(1);
}
echo "\n\n";

$consulta_data = [
    'TrackID' => $track_id,
    'Certificado' => [
        'Rut' => '16694181-4',
        'Password' => $CERT_PASSWORD
    ]
];

$boundary4 = '----WebKitFormBoundary' . uniqid();
$body4 = '';

// input
$body4 .= '--' . $boundary4 . $eol;
$body4 .= 'Content-Disposition: form-data; name="input"' . $eol;
$body4 .= 'Content-Type: text/plain' . $eol . $eol;
$body4 .= json_encode($consulta_data, JSON_UNESCAPED_UNICODE) . $eol;

// certificado
$body4 .= '--' . $boundary4 . $eol;
$body4 .= 'Content-Disposition: form-data; name="files"; filename="' . basename($CERT_PATH) . '"' . $eol;
$body4 .= 'Content-Type: application/octet-stream' . $eol . $eol;
$body4 .= file_get_contents($CERT_PATH) . $eol;

$body4 .= '--' . $boundary4 . '--' . $eol;

// Consultar estado
$ch = curl_init($API_BASE . '/api/v1/Consulta/envio');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body4,
    CURLOPT_HTTPHEADER => [
        'Authorization: ' . $API_KEY,
        'Content-Type: multipart/form-data; boundary=' . $boundary4,
    ],
    CURLOPT_TIMEOUT => 30
]);

echo "Consultando: {$API_BASE}/api/v1/Consulta/envio\n";
$response_estado = curl_exec($ch);
$http_code_estado = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code_estado !== 200) {
    echo "⚠️ Error al consultar estado (HTTP $http_code_estado)\n";
    echo "Respuesta: " . substr($response_estado, 0, 1000) . "\n";
} else {
    echo "✅ Consulta exitosa!\n\n";
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "📊 RESPUESTA DEL SII:\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Guardar respuesta
$estado_file = __DIR__ . '/logs/estado-' . $track_id . '.txt';
@mkdir(dirname($estado_file), 0755, true);
file_put_contents($estado_file, $response_estado);

// Mostrar respuesta
if (strpos($response_estado, '<?xml') === 0) {
    echo "Respuesta XML:\n";
    $xml = simplexml_load_string($response_estado);
    echo $xml->asXML() . "\n";
} else {
    $estado_json = json_decode($response_estado, true);
    if ($estado_json) {
        echo json_encode($estado_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo $response_estado . "\n";
    }
}

echo "\n═══════════════════════════════════════════════════════════════\n";
echo "✅ PRUEBA COMPLETA FINALIZADA\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "📋 RESUMEN:\n";
echo "   Tipo DTE: 39 (Boleta Electrónica)\n";
echo "   Folio: $folio_actual\n";
echo "   Track ID: $track_id\n";
echo "   Monto Total: \$178.500\n\n";

echo "📁 Archivos generados:\n";
echo "   XML: $xml_file\n";
echo "   Estado: $estado_file\n\n";

exit(0);
