#!/usr/bin/env php
<?php
/**
 * Script de Pruebas Simple DTE - Versión 4 (Con fecha de resolución CORRECTA)
 * Fecha de Resolución: 2025-11-18 (dato real del usuario)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== SIMPLE DTE - SCRIPT DE PRUEBAS V4 (FECHA RESOLUCIÓN CORRECTA) ===\n\n";

// Configuración
define('API_KEY', '9794-N370-6392-6913-8052');
define('CERT_PATH', __DIR__ . '/16694181-4.pfx');
define('CERT_PASSWORD', '5605');
define('CERT_RUT', '16694181-4');
define('CAF_PATH', __DIR__ . '/FoliosSII7827422539120251191419.xml');
define('RUT_EMISOR', '78274225-6');
define('RAZON_SOCIAL', 'AKIBARA SPA');

// FECHAS Y VALORES OFICIALES PARA CERTIFICACIÓN SII
define('FECHA_ACTUAL', '2025-11-19');
define('FECHA_RESOLUCION', '2020-10-28');  // ⭐ Resolución 74/2020 - VALOR OFICIAL CERTIFICACIÓN
define('NRO_RESOLUCION', '74');  // Número oficial de certificación SII

$API_BASE = 'https://api.simpleapi.cl';

echo "📋 PASO 1: Configuración\n";
echo "---------------------------------------------------\n";
echo "✓ Fecha DTE: " . FECHA_ACTUAL . "\n";
echo "✓ Fecha Resolución: " . FECHA_RESOLUCION . " ⭐ RESOLUCIÓN 74/2020 (CERTIFICACIÓN)\n";
echo "✓ Nro Resolución: " . NRO_RESOLUCION . "\n\n";

if (!file_exists(CERT_PATH) || !file_exists(CAF_PATH)) {
    die("❌ Error: Archivos no encontrados\n");
}

$caf_xml = file_get_contents(CAF_PATH);
$folio_prueba = 1894;  // Siguiente folio disponible (1889-1893 ya usados)

echo "📝 Folio: $folio_prueba\n\n";

// Paso 2: Construir documento
echo "📄 PASO 2: Construyendo documento CASO-1...\n";
echo "---------------------------------------------------\n";

$item1 = 19900;
$item2 = 9900;
$total = $item1 + $item2;
$neto = round($total / 1.19);
$iva = $total - $neto;

$documento = [
    'Documento' => [
        'Encabezado' => [
            'IdentificacionDTE' => [
                'TipoDTE' => 39,
                'Folio' => $folio_prueba,
                'FechaEmision' => FECHA_ACTUAL,
                'IndicadorServicio' => 3
            ],
            'Emisor' => [
                'Rut' => RUT_EMISOR,
                'RazonSocial' => RAZON_SOCIAL,
                'Giro' => 'Comercio minorista de coleccionables',
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
                'TasaIVA' => 19,
                'IVA' => $iva,
                'MontoTotal' => $total
            ]
        ],
        'Detalles' => [
            [
                'IndicadorExento' => 0,
                'Nombre' => 'Cambio de aceite',
                'Cantidad' => 1,
                'UnidadMedida' => 'un',
                'Precio' => $item1,
                'MontoItem' => $item1
            ],
            [
                'IndicadorExento' => 0,
                'Nombre' => 'Alineación y balanceo',
                'Cantidad' => 1,
                'UnidadMedida' => 'un',
                'Precio' => $item2,
                'MontoItem' => $item2
            ]
        ]
        // Referencias eliminadas - SimpleAPI tiene bug con TpoDocRef vacío
    ],
    'Certificado' => [
        'Rut' => CERT_RUT,
        'Password' => CERT_PASSWORD
    ]
];

echo "✓ Documento construido\n\n";

// Paso 3: Generar DTE
echo "🔄 PASO 3: Generando DTE...\n";
echo "---------------------------------------------------\n";

$boundary = '----WebKitFormBoundary' . md5(time());
$eol = "\r\n";
$body = '';

$body .= '--' . $boundary . $eol;
$body .= 'Content-Disposition: form-data; name="input"' . $eol . $eol;
$body .= json_encode($documento) . $eol;

$body .= '--' . $boundary . $eol;
$body .= 'Content-Disposition: form-data; name="files"; filename="cert.pfx"' . $eol;
$body .= 'Content-Type: application/x-pkcs12' . $eol . $eol;
$body .= file_get_contents(CERT_PATH) . $eol;

$body .= '--' . $boundary . $eol;
$body .= 'Content-Disposition: form-data; name="files2"; filename="caf.xml"' . $eol;
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
        'Content-Type: multipart/form-data; boundary=' . $boundary
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 60
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code != 200) {
    die("❌ Error HTTP $http_code\n");
}

$dte_xml = $response;
file_put_contents('/tmp/boleta_v4.xml', $dte_xml);
echo "✓ DTE generado (" . strlen($dte_xml) . " bytes)\n\n";

// Paso 4: Generar Sobre con FECHA CORRECTA
echo "📦 PASO 4: Generando sobre con fecha correcta...\n";
echo "---------------------------------------------------\n";

$input_sobre = [
    'Certificado' => [
        'Password' => CERT_PASSWORD,
        'Rut' => CERT_RUT
    ],
    'Caratula' => [
        'RutEmisor' => RUT_EMISOR,
        'RutReceptor' => '60803000-K',
        'FechaResolucion' => FECHA_RESOLUCION,  // ⭐ FECHA CORRECTA
        'NumeroResolucion' => NRO_RESOLUCION
    ]
];

$boundary2 = '----WebKitFormBoundary' . md5(time() . 'sobre');
$body2 = '';

$body2 .= '--' . $boundary2 . $eol;
$body2 .= 'Content-Disposition: form-data; name="input"' . $eol . $eol;
$body2 .= json_encode($input_sobre) . $eol;

$body2 .= '--' . $boundary2 . $eol;
$body2 .= 'Content-Disposition: form-data; name="files"; filename="cert.pfx"' . $eol;
$body2 .= 'Content-Type: application/x-pkcs12' . $eol . $eol;
$body2 .= file_get_contents(CERT_PATH) . $eol;

$body2 .= '--' . $boundary2 . $eol;
$body2 .= 'Content-Disposition: form-data; name="files2"; filename="boleta.xml"' . $eol;
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
        'Content-Type: multipart/form-data; boundary=' . $boundary2
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 60
]);

$response2 = curl_exec($ch2);
$http_code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

if ($http_code2 != 200) {
    die("❌ Error HTTP $http_code2\n");
}

$sobre_xml = $response2;
file_put_contents('/tmp/sobre_v4.xml', $sobre_xml);
echo "✓ Sobre generado con valores certificación (FchResol: " . FECHA_RESOLUCION . ", NroResol: " . NRO_RESOLUCION . ") - " . strlen($sobre_xml) . " bytes\n\n";

// Paso 5: Enviar al SII
echo "📤 PASO 5: Enviando al SII...\n";
echo "---------------------------------------------------\n";

$input_envio = [
    'Certificado' => ['Password' => CERT_PASSWORD, 'Rut' => CERT_RUT],
    'Ambiente' => 0,
    'Tipo' => 2
];

$boundary3 = '----WebKitFormBoundary' . md5(time() . 'envio');
$body3 = '';

$body3 .= '--' . $boundary3 . $eol;
$body3 .= 'Content-Disposition: form-data; name="input"' . $eol . $eol;
$body3 .= json_encode($input_envio) . $eol;

$body3 .= '--' . $boundary3 . $eol;
$body3 .= 'Content-Disposition: form-data; name="files"; filename="cert.pfx"' . $eol;
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
        'Content-Type: multipart/form-data; boundary=' . $boundary3
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 90
]);

$response3 = curl_exec($ch3);
$http_code3 = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
curl_close($ch3);

echo "📥 HTTP $http_code3\n\n";

if ($http_code3 != 200) {
    die("❌ Error: $response3\n");
}

$result = json_decode($response3, true);

if ($result) {
    echo "✅ ENVIADO AL SII!\n\n";
    echo "Track ID: " . ($result['trackId'] ?? 'N/A') . "\n";
    echo "Estado: " . ($result['estado'] ?? 'N/A') . "\n";
    echo "Fecha: " . ($result['fecha'] ?? 'N/A') . "\n\n";

    $track_id = $result['trackId'] ?? null;

    if ($track_id && $track_id > 0) {
        file_put_contents('/tmp/track_id_v4.txt', $track_id);

        echo "🔍 PASO 6: Consultando estado (esperando 10 segundos)...\n";
        echo "---------------------------------------------------\n";
        sleep(10);

        $input_consulta = [
            'Certificado' => ['Password' => CERT_PASSWORD, 'Rut' => CERT_RUT],
            'RutEmpresa' => RUT_EMISOR,
            'TrackId' => $track_id,
            'Ambiente' => 0,
            'ServidorBoletaREST' => true
        ];

        $boundary4 = '----WebKitFormBoundary' . md5(time() . 'consulta');
        $body4 = '';

        $body4 .= '--' . $boundary4 . $eol;
        $body4 .= 'Content-Disposition: form-data; name="input"' . $eol . $eol;
        $body4 .= json_encode($input_consulta) . $eol;

        $body4 .= '--' . $boundary4 . $eol;
        $body4 .= 'Content-Disposition: form-data; name="files"; filename="cert.pfx"' . $eol;
        $body4 .= 'Content-Type: application/x-pkcs12' . $eol . $eol;
        $body4 .= file_get_contents(CERT_PATH) . $eol;

        $body4 .= '--' . $boundary4 . '--' . $eol;

        $ch4 = curl_init($API_BASE . '/api/v1/consulta/envio');
        curl_setopt_array($ch4, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body4,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . API_KEY,
                'Content-Type: multipart/form-data; boundary=' . $boundary4
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);

        $response4 = curl_exec($ch4);
        $http_code4 = curl_getinfo($ch4, CURLINFO_HTTP_CODE);
        curl_close($ch4);

        if ($http_code4 == 200) {
            $consulta = json_decode($response4, true);
            if ($consulta) {
                $estados = [
                    'REC' => '📥 Recibido',
                    'RSC' => '❌ Rechazado Schema',
                    'SOK' => '✅ Schema Válido',
                    'CRT' => '✅ Carátula OK',
                    'FOK' => '✅ Firma OK',
                    'PDR' => '⏳ En Proceso',
                    'EPR' => '✅✅✅ PROCESADO EXITOSAMENTE'
                ];

                $estado = $consulta['estado'] ?? 'N/A';
                echo "\n📊 ESTADO: $estado " . ($estados[$estado] ?? '') . "\n";

                if (!empty($consulta['errores'])) {
                    echo "\n⚠️  ERRORES:\n" . $consulta['errores'] . "\n";
                } else {
                    echo "\n✅ Sin errores!\n";
                }

                echo "\nJSON:\n";
                echo json_encode($consulta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            }
        }
    }
}

echo "\n=== COMPLETADO ===\n";
echo "Archivos: /tmp/boleta_v4.xml, /tmp/sobre_v4.xml\n";
if (isset($track_id)) {
    echo "Track ID V4: $track_id (guardado en /tmp/track_id_v4.txt)\n";
}
echo "\n";
