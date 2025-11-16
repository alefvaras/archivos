#!/usr/bin/env php
<?php
/**
 * CASO-2: Nota de Crédito Electrónica (DTE 61)
 * Anula o corrige una boleta o factura previamente emitida
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== CASO-2: NOTA DE CRÉDITO ELECTRÓNICA ===\n\n";

// Configuración
define('API_KEY', '9794-N370-6392-6913-8052');
define('CERT_PATH', __DIR__ . '/16694181-4.pfx');
define('CERT_PASSWORD', '5605');
define('CAF_PATH_61', __DIR__ . '/FoliosSII782742256120251191419.xml'); // Necesitarás un CAF para tipo 61
define('RUT_EMISOR', '78274225-6');
define('RAZON_SOCIAL', 'AKIBARA SPA');

$API_BASE = 'https://api.simpleapi.cl';

// Verificar que exista el CAF para Nota de Crédito
if (!file_exists(CAF_PATH_61)) {
    echo "❌ ATENCIÓN: No se encontró el archivo CAF para Nota de Crédito (Tipo 61)\n\n";
    echo "Para generar una Nota de Crédito, necesitas:\n";
    echo "1. Ingresar a https://mipyme.sii.cl\n";
    echo "2. Ir a 'Folios' → 'Generar Folios'\n";
    echo "3. Seleccionar DTE tipo 61 (Nota de Crédito Electrónica)\n";
    echo "4. Solicitar folios (ejemplo: 100 folios)\n";
    echo "5. Descargar el archivo CAF\n";
    echo "6. Guardar como: " . CAF_PATH_61 . "\n\n";
    exit(1);
}

// Leer CAF
$caf_xml = file_get_contents(CAF_PATH_61);
$caf = simplexml_load_string($caf_xml);
$folio = (int) ((string) $caf->CAF->DA->RNG->D);

echo "📋 CAF Nota de Crédito cargado\n";
echo "  Folio a usar: {$folio}\n\n";

// IMPORTANTE: Una Nota de Crédito debe referenciar un documento existente
// En este ejemplo, vamos a anular la boleta del CASO-1 (folio 1890)
$folio_referencia = 1890; // Cambiar por el folio de la boleta a anular
$tipo_doc_referencia = 39; // 39 = Boleta Electrónica

echo "📄 Generando Nota de Crédito...\n";
echo "  Anula: Boleta Electrónica Folio {$folio_referencia}\n";
echo "  Razón: Anulación de documento\n\n";

// Calcular totales (debe coincidir con el documento original)
$total_con_iva = 29800;
$neto = round($total_con_iva / 1.19);
$iva = $total_con_iva - $neto;

// Construir Nota de Crédito
$documento = [
    'Documento' => [
        'Encabezado' => [
            'IdentificacionDTE' => [
                'TipoDTE' => 61,
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
                'Rut' => '66666666-6',
                'RazonSocial' => 'Cliente Final',
                'Direccion' => 'Santiago',
                'Comuna' => 'Santiago'
            ],
            'Totales' => [
                'MontoNeto' => $neto,
                'IVA' => $iva,
                'MontoTotal' => $total_con_iva
            ]
        ],
        'Detalles' => [
            [
                'IndicadorExento' => 0,
                'Nombre' => 'Cambio de aceite',
                'Cantidad' => 1,
                'UnidadMedida' => 'un',
                'Precio' => 19900,
                'MontoItem' => 19900
            ],
            [
                'IndicadorExento' => 0,
                'Nombre' => 'Alineación y balanceo',
                'Cantidad' => 1,
                'UnidadMedida' => 'un',
                'Precio' => 9900,
                'MontoItem' => 9900
            ]
        ],
        'Referencias' => [
            [
                'NroLinRef' => 1,
                'TpoDocRef' => (string)$tipo_doc_referencia,
                'FolioRef' => $folio_referencia,
                'FchRef' => date('Y-m-d'),
                'CodRef' => 1,  // 1 = Anula Documento de Referencia
                'RazonRef' => 'Anulación de boleta electrónica'
            ]
        ]
    ],
    'Certificado' => [
        'Rut' => '16694181-4',
        'Password' => CERT_PASSWORD
    ]
];

echo "🔄 Generando DTE vía Simple API...\n";

// Generar DTE
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
$body .= 'Content-Disposition: form-data; name="files2"; filename="' . basename(CAF_PATH_61) . '"' . $eol;
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

echo "✓ Nota de Crédito generada\n\n";
file_put_contents('/tmp/nota_credito_caso2.xml', $dte_xml);

// Generar sobre y enviar al SII
echo "📦 Generando sobre de envío...\n";

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
$body2 .= 'Content-Disposition: form-data; name="files"; filename="nota_credito.xml"' . $eol;
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

echo "✓ Sobre generado\n\n";

// Enviar al SII
echo "📤 Enviando al SII...\n";

$boundary3 = '----WebKitFormBoundary' . md5(time() . 'enviar');
$body3 = '';

$envio_config = [
    'Certificado' => ['Rut' => '16694181-4', 'Password' => CERT_PASSWORD],
    'Ambiente' => 0,
    'Tipo' => 2
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

echo "\n=== CASO-2 COMPLETADO ===\n";
echo "Archivo generado: /tmp/nota_credito_caso2.xml\n\n";
