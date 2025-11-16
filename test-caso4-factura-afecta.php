#!/usr/bin/env php
<?php
/**
 * CASO-4: Factura Afecta Electrónica (DTE 33)
 * Documento tributario para ventas afectas a IVA a empresas
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== CASO-4: FACTURA AFECTA ELECTRÓNICA ===\n\n";

// Configuración
define('API_KEY', '9794-N370-6392-6913-8052');
define('CERT_PATH', __DIR__ . '/16694181-4.pfx');
define('CERT_PASSWORD', '5605');
define('CAF_PATH_33', __DIR__ . '/FoliosSII782742253320251191419.xml'); // CAF para tipo 33
define('RUT_EMISOR', '78274225-6');
define('RAZON_SOCIAL', 'AKIBARA SPA');

$API_BASE = 'https://api.simpleapi.cl';

// Verificar CAF
if (!file_exists(CAF_PATH_33)) {
    echo "❌ ATENCIÓN: No se encontró el archivo CAF para Factura Afecta (Tipo 33)\n\n";
    echo "Para generar una Factura Afecta, necesitas:\n";
    echo "1. Ingresar a https://mipyme.sii.cl\n";
    echo "2. Ir a 'Folios' → 'Generar Folios'\n";
    echo "3. Seleccionar DTE tipo 33 (Factura Afecta Electrónica)\n";
    echo "4. Solicitar folios (ejemplo: 100 folios)\n";
    echo "5. Descargar el archivo CAF\n";
    echo "6. Guardar como: " . CAF_PATH_33 . "\n\n";
    exit(1);
}

$caf_xml = file_get_contents(CAF_PATH_33);
$caf = simplexml_load_string($caf_xml);
$folio = (int) ((string) $caf->CAF->DA->RNG->D);

echo "📋 CAF Factura Afecta cargado\n";
echo "  Folio a usar: {$folio}\n\n";

echo "📄 Generando Factura Afecta...\n";
echo "  Cliente: Empresa de Prueba S.A.\n";
echo "  RUT: 77777777-7\n\n";

// Calcular totales
$items_data = [
    ['nombre' => 'Servicio de desarrollo web', 'cantidad' => 10, 'precio_unitario' => 50000],
    ['nombre' => 'Hosting anual', 'cantidad' => 1, 'precio_unitario' => 120000],
];

$monto_neto = 0;
foreach ($items_data as $item) {
    $monto_neto += $item['cantidad'] * $item['precio_unitario'];
}

$iva = round($monto_neto * 0.19);
$monto_total = $monto_neto + $iva;

echo "  Monto Neto: $" . number_format($monto_neto, 0, ',', '.') . "\n";
echo "  IVA (19%): $" . number_format($iva, 0, ',', '.') . "\n";
echo "  Total: $" . number_format($monto_total, 0, ',', '.') . "\n\n";

// Construir detalles
$detalles = [];
foreach ($items_data as $item) {
    $monto_item = $item['cantidad'] * $item['precio_unitario'];
    $detalles[] = [
        'IndicadorExento' => 0,
        'Nombre' => $item['nombre'],
        'Cantidad' => $item['cantidad'],
        'UnidadMedida' => 'un',
        'Precio' => $item['precio_unitario'],
        'MontoItem' => $monto_item
    ];
}

$documento = [
    'Documento' => [
        'Encabezado' => [
            'IdentificacionDTE' => [
                'TipoDTE' => 33,
                'Folio' => $folio,
                'FechaEmision' => date('Y-m-d')
            ],
            'Emisor' => [
                'Rut' => RUT_EMISOR,
                'RazonSocial' => RAZON_SOCIAL,
                'Giro' => 'Comercio minorista de coleccionables',
                'Acteco' => 477300,
                'DireccionOrigen' => 'BARTOLO SOTO 3700 DP 1402 PISO 14',
                'ComunaOrigen' => 'San Miguel',
                'CiudadOrigen' => 'Santiago'
            ],
            'Receptor' => [
                'Rut' => '77777777-7',
                'RazonSocial' => 'Empresa de Prueba S.A.',
                'Giro' => 'Servicios de tecnología',
                'Direccion' => 'Av. Libertador Bernardo O\'Higgins 1234',
                'Comuna' => 'Santiago',
                'Ciudad' => 'Santiago'
            ],
            'Totales' => [
                'MontoNeto' => $monto_neto,
                'TasaIVA' => 19,
                'IVA' => $iva,
                'MontoTotal' => $monto_total
            ]
        ],
        'Detalles' => $detalles
    ],
    'Certificado' => [
        'Rut' => '16694181-4',
        'Password' => CERT_PASSWORD
    ]
];

echo "🔄 Generando DTE vía Simple API...\n";

$boundary = '----WebKitFormBoundary' . md5(time());
$eol = "\r\n";
$body = '';

$body .= '--' . $boundary . $eol;
$body .= 'Content-Disposition: form-data; name="input"' . $eol . $eol;
$body .= json_encode($documento) . $eol;

$body .= '--' . $boundary . $eol;
$body .= 'Content-Disposition: form-data; name="files"; filename="' . basename(CERT_PATH) . '"' . $eol;
$body .= 'Content-Type: application/x-pkcs12' . $eol . $eol;
$body .= file_get_contents(CERT_PATH) . $eol;

$body .= '--' . $boundary . $eol;
$body .= 'Content-Disposition: form-data; name="files2"; filename="' . basename(CAF_PATH_33) . '"' . $eol;
$body .= 'Content-Type: text/xml' . $eol . $eol;
$body .= $caf_xml . $eol;

$body .= '--' . $boundary . $eol;
$body .= 'Content-Disposition: form-data; name="password"' . $eol . $eol;
$body .= CERT_PASSWORD . $eol;

$body .= '--' . $boundary . '--' . $eol;

$ch = curl_init($API_BASE . '/api/v1/dte/generar');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_HTTPHEADER => [
        'Authorization: ' . API_KEY,
        'Content-Type: multipart/form-data; boundary=' . $boundary,
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 60,
]);

$dte_xml = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code != 200) {
    die("❌ Error al generar DTE: HTTP {$http_code}\n{$dte_xml}\n");
}

echo "✓ Factura Afecta generada\n\n";
file_put_contents('/tmp/factura_afecta_caso4.xml', $dte_xml);

echo "📦 Generando sobre y enviando al SII...\n";

// Generar sobre
$boundary2 = '----WebKitFormBoundary' . md5(time() . 'sobre');
$body2 = '';

$sobre_config = [
    'Certificado' => ['Rut' => '16694181-4', 'Password' => CERT_PASSWORD],
    'Caratula' => [
        'RutEmisor' => RUT_EMISOR,
        'RutReceptor' => '60803000-K',
        'FechaResolucion' => date('Y-m-d'),
        'NumeroResolucion' => 0
    ]
];

$body2 .= '--' . $boundary2 . $eol;
$body2 .= 'Content-Disposition: form-data; name="input"' . $eol . $eol;
$body2 .= json_encode($sobre_config) . $eol;

$body2 .= '--' . $boundary2 . $eol;
$body2 .= 'Content-Disposition: form-data; name="files"; filename="' . basename(CERT_PATH) . '"' . $eol;
$body2 .= 'Content-Type: application/x-pkcs12' . $eol . $eol;
$body2 .= file_get_contents(CERT_PATH) . $eol;

$body2 .= '--' . $boundary2 . $eol;
$body2 .= 'Content-Disposition: form-data; name="files"; filename="factura.xml"' . $eol;
$body2 .= 'Content-Type: text/xml' . $eol . $eol;
$body2 .= $dte_xml . $eol;

$body2 .= '--' . $boundary2 . '--' . $eol;

$ch2 = curl_init($API_BASE . '/api/v1/envio/generar');
curl_setopt_array($ch2, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body2,
    CURLOPT_HTTPHEADER => [
        'Authorization: ' . API_KEY,
        'Content-Type: multipart/form-data; boundary=' . $boundary2,
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 60
]);

$sobre_xml = curl_exec($ch2);
curl_close($ch2);

// Enviar al SII
$boundary3 = '----WebKitFormBoundary' . md5(time() . 'enviar');
$body3 = '';

$envio_config = [
    'Certificado' => ['Rut' => '16694181-4', 'Password' => CERT_PASSWORD],
    'Ambiente' => 0,
    'Tipo' => 1  // Tipo 1 para facturas
];

$body3 .= '--' . $boundary3 . $eol;
$body3 .= 'Content-Disposition: form-data; name="input"' . $eol . $eol;
$body3 .= json_encode($envio_config) . $eol;

$body3 .= '--' . $boundary3 . $eol;
$body3 .= 'Content-Disposition: form-data; name="files"; filename="' . basename(CERT_PATH) . '"' . $eol;
$body3 .= 'Content-Type: application/x-pkcs12' . $eol . $eol;
$body3 .= file_get_contents(CERT_PATH) . $eol;

$body3 .= '--' . $boundary3 . $eol;
$body3 .= 'Content-Disposition: form-data; name="files"; filename="sobre.xml"' . $eol;
$body3 .= 'Content-Type: text/xml' . $eol . $eol;
$body3 .= $sobre_xml . $eol;

$body3 .= '--' . $boundary3 . '--' . $eol;

$ch3 = curl_init($API_BASE . '/api/v1/envio/enviar');
curl_setopt_array($ch3, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body3,
    CURLOPT_HTTPHEADER => [
        'Authorization: ' . API_KEY,
        'Content-Type: multipart/form-data; boundary=' . $boundary3,
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 90
]);

$response = curl_exec($ch3);
curl_close($ch3);

$result = json_decode($response, true);

if ($result && isset($result['trackId'])) {
    echo "✓ Enviado al SII exitosamente\n";
    echo "  Track ID: {$result['trackId']}\n\n";
    echo "Para consultar el estado:\n";
    echo "  php /tmp/check_track.php {$result['trackId']}\n\n";
} else {
    echo "Respuesta: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
}

echo "\n=== CASO-4 COMPLETADO ===\n";
echo "Archivo generado: /tmp/factura_afecta_caso4.xml\n\n";
