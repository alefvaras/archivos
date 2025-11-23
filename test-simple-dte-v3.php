#!/usr/bin/env php
<?php
/**
 * Script de Pruebas Simple DTE - Versión 3 (Con fechas correctas)
 * Flujo: Generar DTE → Generar Sobre (firmado) → Enviar al SII → Consultar Track ID
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== SIMPLE DTE - SCRIPT DE PRUEBAS V3 (FECHAS CORRECTAS) ===\n\n";

// Configuración
define('API_KEY', '9794-N370-6392-6913-8052');
define('CERT_PATH', __DIR__ . '/16694181-4.pfx');
define('CERT_PASSWORD', '5605');
define('CERT_RUT', '16694181-4');
define('CAF_PATH', __DIR__ . '/FoliosSII7827422539120251191419.xml');
define('RUT_EMISOR', '78274225-6');
define('RAZON_SOCIAL', 'AKIBARA SPA');

// FECHAS CORRECTAS
define('FECHA_ACTUAL', '2025-11-19');  // Fecha real
define('FECHA_RESOLUCION', '2014-08-22');  // Fecha típica de resolución SII (pasada)
define('NRO_RESOLUCION', '80');  // Número de resolución típico para certificación

$API_BASE = 'https://api.simpleapi.cl';

// Paso 1: Verificar archivos
echo "📋 PASO 1: Verificando archivos de configuración...\n";
echo "---------------------------------------------------\n";

if (!file_exists(CERT_PATH)) {
    die("❌ Error: Certificado no encontrado\n");
}
echo "✓ Certificado encontrado\n";

if (!file_exists(CAF_PATH)) {
    die("❌ Error: Archivo CAF no encontrado\n");
}
echo "✓ Archivo CAF encontrado\n";

$caf_xml = file_get_contents(CAF_PATH);
$caf = simplexml_load_string($caf_xml);
$da = $caf->CAF->DA;
$folio_desde = (int) ((string) $da->RNG->D);
$folio_hasta = (int) ((string) $da->RNG->H);

echo "✓ CAF parseado: Folios $folio_desde a $folio_hasta\n";
echo "✓ Fecha de emisión: " . FECHA_ACTUAL . "\n";
echo "✓ Fecha de resolución: " . FECHA_RESOLUCION . "\n";
echo "✓ Número de resolución: " . NRO_RESOLUCION . "\n\n";

// Usar folio 1891
$folio_prueba = 1891;
echo "📝 Folio seleccionado: $folio_prueba\n\n";

// Paso 2: Generar Boleta Electrónica (CASO-1)
echo "📄 PASO 2: Generando Boleta Electrónica (CASO-1)...\n";
echo "---------------------------------------------------\n";

$item1_con_iva = 19900;
$item2_con_iva = 9900;
$total_con_iva = $item1_con_iva + $item2_con_iva;
$neto = round($total_con_iva / 1.19);
$iva = $total_con_iva - $neto;

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
                'MontoTotal' => $total_con_iva
            ]
        ],
        'Detalles' => [
            [
                'IndicadorExento' => 0,
                'Nombre' => 'Cambio de aceite',
                'Descripcion' => '',
                'Cantidad' => 1,
                'UnidadMedida' => 'un',
                'Precio' => $item1_con_iva,
                'Descuento' => 0,
                'Recargo' => 0,
                'MontoItem' => $item1_con_iva
            ],
            [
                'IndicadorExento' => 0,
                'Nombre' => 'Alineación y balanceo',
                'Descripcion' => '',
                'Cantidad' => 1,
                'UnidadMedida' => 'un',
                'Precio' => $item2_con_iva,
                'Descuento' => 0,
                'Recargo' => 0,
                'MontoItem' => $item2_con_iva
            ]
        ],
        'Referencias' => [
            [
                'NroLinRef' => 1,
                'TpoDocRef' => 'SET',
                'FolioRef' => 0,
                'FchRef' => FECHA_ACTUAL,
                'CodRef' => 'SET',
                'RazonRef' => 'CASO-1'
            ]
        ]
    ],
    'Certificado' => [
        'Rut' => CERT_RUT,
        'Password' => CERT_PASSWORD
    ]
];

echo "✓ Documento CASO-1 construido\n\n";

// Paso 3: Generar DTE
echo "🔄 PASO 3: Generando DTE vía Simple API...\n";
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

echo "📡 Generando DTE...\n";
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code != 200) {
    die("❌ Error HTTP $http_code\n");
}

$dte_xml = $response;
file_put_contents('/tmp/boleta_v3.xml', $dte_xml);
echo "✓ DTE generado (" . strlen($dte_xml) . " bytes)\n\n";

// Paso 4: Generar Sobre
echo "📦 PASO 4: Generando sobre de envío vía Simple API...\n";
echo "---------------------------------------------------\n";

$input_sobre = [
    'Certificado' => [
        'Password' => CERT_PASSWORD,
        'Rut' => CERT_RUT
    ],
    'Caratula' => [
        'RutEmisor' => RUT_EMISOR,
        'RutReceptor' => '60803000-K',
        'FechaResolucion' => FECHA_RESOLUCION,
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

echo "📡 Generando sobre firmado...\n";
$response2 = curl_exec($ch2);
$http_code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

if ($http_code2 != 200) {
    die("❌ Error HTTP $http_code2\n");
}

$sobre_xml = $response2;
file_put_contents('/tmp/sobre_v3.xml', $sobre_xml);
echo "✓ Sobre firmado (" . strlen($sobre_xml) . " bytes)\n\n";

// Paso 5: Enviar al SII
echo "📤 PASO 5: Enviando sobre al SII...\n";
echo "---------------------------------------------------\n";

$input_envio = [
    'Certificado' => [
        'Password' => CERT_PASSWORD,
        'Rut' => CERT_RUT
    ],
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

echo "📡 Enviando...\n";
$response3 = curl_exec($ch3);
$http_code3 = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
curl_close($ch3);

echo "📥 Respuesta (HTTP $http_code3)\n\n";

if ($http_code3 != 200) {
    die("❌ Error HTTP $http_code3: $response3\n");
}

$result = json_decode($response3, true);

if ($result) {
    echo "✅ ENVIADO AL SII!\n\n";
    echo "Track ID: " . ($result['trackId'] ?? 'N/A') . "\n";
    echo "Estado: " . ($result['estado'] ?? 'N/A') . "\n";
    echo "Fecha: " . ($result['fecha'] ?? 'N/A') . "\n\n";

    $track_id = $result['trackId'] ?? null;

    if ($track_id && $track_id > 0) {
        file_put_contents('/tmp/track_id_v3.txt', $track_id);

        echo "🔍 PASO 6: Consultando estado (esperando 5 segundos)...\n";
        echo "---------------------------------------------------\n";
        sleep(5);

        $input_consulta = [
            'Certificado' => [
                'Password' => CERT_PASSWORD,
                'Rut' => CERT_RUT
            ],
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
                echo "Estado: " . ($consulta['estado'] ?? 'N/A') . "\n";
                if (!empty($consulta['errores'])) {
                    echo "Errores: " . $consulta['errores'] . "\n";
                }
                echo "\nRespuesta completa:\n";
                echo json_encode($consulta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            }
        }
    }
}

echo "\n=== COMPLETADO ===\n";
echo "Archivos: /tmp/boleta_v3.xml, /tmp/sobre_v3.xml\n";
if (isset($track_id)) {
    echo "Track ID guardado en: /tmp/track_id_v3.txt\n";
}
echo "\n";
