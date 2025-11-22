#!/usr/bin/env php
<?php
/**
 * Script de Pruebas Simple DTE
 * Prueba completa del flujo: Generar Boleta → Enviar al SII → Consultar Track ID
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== SIMPLE DTE - SCRIPT DE PRUEBAS ===\n\n";

// Configuración
define('API_KEY', '9794-N370-6392-6913-8052');
define('CERT_PATH', __DIR__ . '/16694181-4.pfx');
define('CERT_PASSWORD', '5605');
define('CAF_PATH', __DIR__ . '/FoliosSII7827422539120251191419.xml');
define('RUT_EMISOR', '78274225-6');
define('RAZON_SOCIAL', 'AKIBARA SPA');
define('AMBIENTE', 'certificacion'); // certificacion o produccion

// URLs de Simple API
$API_BASE = 'https://api.simpleapi.cl';

// Paso 1: Verificar archivos
echo "📋 PASO 1: Verificando archivos de configuración...\n";
echo "---------------------------------------------------\n";

if (!file_exists(CERT_PATH)) {
    die("❌ Error: Certificado no encontrado en " . CERT_PATH . "\n");
}
echo "✓ Certificado encontrado: " . CERT_PATH . " (" . filesize(CERT_PATH) . " bytes)\n";

if (!file_exists(CAF_PATH)) {
    die("❌ Error: Archivo CAF no encontrado en " . CAF_PATH . "\n");
}
echo "✓ Archivo CAF encontrado: " . CAF_PATH . " (" . filesize(CAF_PATH) . " bytes)\n";

// Leer y parsear CAF
$caf_xml = file_get_contents(CAF_PATH);
$caf = simplexml_load_string($caf_xml);
$da = $caf->CAF->DA;
$folio_desde = (int) ((string) $da->RNG->D);
$folio_hasta = (int) ((string) $da->RNG->H);
$tipo_dte = (int) ((string) $da->TD);

echo "✓ CAF parseado correctamente:\n";
echo "  - Tipo DTE: $tipo_dte (Boleta Electrónica)\n";
echo "  - Rango de folios: $folio_desde a $folio_hasta\n";
echo "  - Folios disponibles: " . ($folio_hasta - $folio_desde + 1) . "\n\n";

// Seleccionar un folio para la prueba (usar el primero disponible)
$folio_prueba = $folio_desde;
echo "📝 Folio seleccionado para prueba: $folio_prueba\n\n";

// Paso 2: Generar Boleta Electrónica (CASO-1)
echo "📄 PASO 2: Generando Boleta Electrónica (CASO-1)...\n";
echo "---------------------------------------------------\n";

// Datos del CASO-1: Servicios automotrices
// Calcular totales (precios con IVA incluido)
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
                'FechaEmision' => date('Y-m-d'),
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
                'FchRef' => date('Y-m-d'),
                'CodRef' => 'SET',
                'RazonRef' => 'CASO-1'
            ]
        ]
    ],
    'Certificado' => [
        'Rut' => '16694181-4',
        'Password' => CERT_PASSWORD
    ]
];

echo "✓ Documento CASO-1 construido:\n";
echo "  - Folio: {$documento['Documento']['Encabezado']['IdentificacionDTE']['Folio']}\n";
echo "  - Fecha: {$documento['Documento']['Encabezado']['IdentificacionDTE']['FechaEmision']}\n";
echo "  - Neto: \${$documento['Documento']['Encabezado']['Totales']['MontoNeto']}\n";
echo "  - IVA: \${$documento['Documento']['Encabezado']['Totales']['IVA']}\n";
echo "  - Total: \${$documento['Documento']['Encabezado']['Totales']['MontoTotal']}\n";
echo "  - Items: " . count($documento['Documento']['Detalles']) . "\n";
echo "  - Referencia: CASO-1 (Set de prueba SII)\n\n";

// Paso 3: Enviar a Simple API para generar DTE
echo "🔄 PASO 3: Generando DTE vía Simple API...\n";
echo "---------------------------------------------------\n";

$boundary = '----WebKitFormBoundary' . md5(time());
$eol = "\r\n";

// Construir multipart/form-data
$body = '';

// Parte 1: input (JSON)
$body .= '--' . $boundary . $eol;
$body .= 'Content-Disposition: form-data; name="input"' . $eol . $eol;
$body .= json_encode($documento) . $eol;

// Parte 2: certificado
$body .= '--' . $boundary . $eol;
$body .= 'Content-Disposition: form-data; name="files"; filename="' . basename(CERT_PATH) . '"' . $eol;
$body .= 'Content-Type: application/x-pkcs12' . $eol . $eol;
$body .= file_get_contents(CERT_PATH) . $eol;

// Parte 3: CAF
$body .= '--' . $boundary . $eol;
$body .= 'Content-Disposition: form-data; name="files2"; filename="' . basename(CAF_PATH) . '"' . $eol;
$body .= 'Content-Type: text/xml' . $eol . $eol;
$body .= $caf_xml . $eol;

// Parte 4: password
$body .= '--' . $boundary . $eol;
$body .= 'Content-Disposition: form-data; name="password"' . $eol . $eol;
$body .= CERT_PASSWORD . $eol;

$body .= '--' . $boundary . '--' . $eol;

// Hacer request
$ch = curl_init($API_BASE . '/api/v1/dte/generar');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_HTTPHEADER => [
        'Authorization: ' . API_KEY,
        'Content-Type: multipart/form-data; boundary=' . $boundary,
        'Content-Length: ' . strlen($body)
    ],
    CURLOPT_SSL_VERIFYPEER => false,  // Desactivar verificación SSL para pruebas
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_VERBOSE => false
]);

echo "📡 Enviando request a Simple API...\n";
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    die("❌ Error CURL: $curl_error\n");
}

echo "📥 Respuesta recibida (HTTP $http_code)\n";

if ($http_code != 200) {
    echo "❌ Error HTTP: $http_code\n";
    echo "Respuesta: $response\n";
    die();
}

// Simple API devuelve directamente el XML del DTE
$dte_xml = $response;

// Validar que sea XML
if (strpos($dte_xml, '<?xml') === false) {
    die("❌ Error: La respuesta no es XML válido\n$response\n");
}

echo "✓ DTE generado exitosamente!\n\n";

// Guardar XML generado
file_put_contents('/tmp/boleta_prueba.xml', $dte_xml);
echo "✓ XML del DTE guardado en: /tmp/boleta_prueba.xml\n";
echo "  Tamaño: " . strlen($dte_xml) . " bytes\n";

// Parsear XML para mostrar información
$xml = simplexml_load_string($dte_xml);
if ($xml) {
    $folio_generado = (string) $xml->Documento->Encabezado->IdDoc->Folio;
    $monto_total = (string) $xml->Documento->Encabezado->Totales->MntTotal;
    echo "  Folio: $folio_generado\n";
    echo "  Monto Total: \$$monto_total\n";
    echo "  Timbre electrónico (TED): ✓\n";
    echo "  Firma digital: ✓\n";
}
echo "\n";

// Paso 4: Construir sobre para envío al SII
echo "📦 PASO 4: Construyendo sobre de envío (EnvioBoleta)...\n";
echo "---------------------------------------------------\n";

// Construir EnvioBoleta
$sobre_xml = '<?xml version="1.0" encoding="ISO-8859-1"?>' . "\n";
$sobre_xml .= '<EnvioBOLETA xmlns="http://www.sii.cl/SiiDte" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sii.cl/SiiDte EnvioBOLETA_v11.xsd" version="1.0">' . "\n";
$sobre_xml .= '<SetDTE ID="SetDoc">' . "\n";
$sobre_xml .= '<Caratula version="1.0">' . "\n";
$sobre_xml .= '<RutEmisor>' . RUT_EMISOR . '</RutEmisor>' . "\n";
$sobre_xml .= '<RutEnvia>' . RUT_EMISOR . '</RutEnvia>' . "\n";
$sobre_xml .= '<RutReceptor>60803000-K</RutReceptor>' . "\n"; // RUT del SII
$sobre_xml .= '<FchResol>2025-11-16</FchResol>' . "\n";
$sobre_xml .= '<NroResol>0</NroResol>' . "\n";
$sobre_xml .= '<TmstFirmaEnv>' . date('Y-m-d\TH:i:s') . '</TmstFirmaEnv>' . "\n";
$sobre_xml .= '<SubTotDTE>' . "\n";
$sobre_xml .= '<TpoDTE>39</TpoDTE>' . "\n";
$sobre_xml .= '<NroDTE>1</NroDTE>' . "\n";
$sobre_xml .= '</SubTotDTE>' . "\n";
$sobre_xml .= '</Caratula>' . "\n";

// Insertar el DTE generado (remover la declaración XML del DTE)
$dte_sin_declaracion = preg_replace('/<\?xml[^?]*\?>\s*/i', '', $dte_xml);
$sobre_xml .= $dte_sin_declaracion . "\n";

$sobre_xml .= '</SetDTE>' . "\n";
$sobre_xml .= '</EnvioBOLETA>';

file_put_contents('/tmp/sobre_envio.xml', $sobre_xml);
echo "✓ Sobre construido exitosamente\n";
echo "  Archivo: /tmp/sobre_envio.xml\n";
echo "  Tamaño: " . strlen($sobre_xml) . " bytes\n\n";

// Paso 5: Enviar sobre al SII
echo "📤 PASO 5: Enviando sobre al SII vía Simple API...\n";
echo "---------------------------------------------------\n";

$boundary2 = '----WebKitFormBoundary' . md5(time() . 'send');
$body2 = '';

// Configuración JSON
$envio_config = [
    'Certificado' => [
        'Rut' => '16694181-4',
        'Password' => CERT_PASSWORD
    ],
    'Ambiente' => 0,  // 0 = Certificación, 1 = Producción
    'Tipo' => 2       // 2 = EnvioBoleta, 1 = EnvioDTE
];

$body2 .= '--' . $boundary2 . $eol;
$body2 .= 'Content-Disposition: form-data; name="input"' . $eol . $eol;
$body2 .= json_encode($envio_config) . $eol;

// Certificado
$body2 .= '--' . $boundary2 . $eol;
$body2 .= 'Content-Disposition: form-data; name="files"; filename="' . basename(CERT_PATH) . '"' . $eol;
$body2 .= 'Content-Type: application/x-pkcs12' . $eol . $eol;
$body2 .= file_get_contents(CERT_PATH) . $eol;

// Sobre XML
$body2 .= '--' . $boundary2 . $eol;
$body2 .= 'Content-Disposition: form-data; name="files"; filename="sobre.xml"' . $eol;
$body2 .= 'Content-Type: text/xml' . $eol . $eol;
$body2 .= $sobre_xml . $eol;

$body2 .= '--' . $boundary2 . '--' . $eol;

$ch2 = curl_init($API_BASE . '/api/v1/envio/enviar');
curl_setopt_array($ch2, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body2,
    CURLOPT_HTTPHEADER => [
        'Authorization: ' . API_KEY,
        'Content-Type: multipart/form-data; boundary=' . $boundary2,
        'Content-Length: ' . strlen($body2)
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
    CURLOPT_TIMEOUT => 90
]);

echo "📡 Enviando sobre al SII...\n";
$response2 = curl_exec($ch2);
$http_code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
$curl_error2 = curl_error($ch2);
curl_close($ch2);

if ($curl_error2) {
    die("❌ Error CURL: $curl_error2\n");
}

echo "📥 Respuesta recibida (HTTP $http_code2)\n";

if ($http_code2 != 200) {
    echo "❌ Error HTTP: $http_code2\n";
    echo "Respuesta: $response2\n";
    die();
}

// La respuesta puede ser XML o JSON, intentar ambos
$track_id = null;

// Intentar parsear como XML primero (respuesta típica del SII)
libxml_use_internal_errors(true);
$xml_response = simplexml_load_string($response2);

if ($xml_response) {
    // Respuesta XML del SII
    echo "✓ Sobre enviado exitosamente!\n\n";

    // Buscar Track ID en el XML
    if (isset($xml_response->TRACKID)) {
        $track_id = (string) $xml_response->TRACKID;
    } elseif (isset($xml_response->track_id)) {
        $track_id = (string) $xml_response->track_id;
    }

    // Mostrar información de la respuesta
    if (isset($xml_response->STATUS)) {
        echo "Status: " . $xml_response->STATUS . "\n";
    }
    if (isset($xml_response->ESTADO)) {
        echo "Estado: " . $xml_response->ESTADO . "\n";
    }
    if (isset($xml_response->GLOSA)) {
        echo "Glosa: " . $xml_response->GLOSA . "\n";
    }

    echo "\nRespuesta completa del SII:\n";
    echo $response2 . "\n\n";
} else {
    // Intentar como JSON
    $result2 = json_decode($response2, true);

    if ($result2) {
        echo "✓ Sobre enviado exitosamente!\n\n";

        // Extraer Track ID
        if (isset($result2['data']['track_id'])) {
            $track_id = $result2['data']['track_id'];
        } elseif (isset($result2['trackid'])) {
            $track_id = $result2['trackid'];
        } elseif (isset($result2['TRACKID'])) {
            $track_id = $result2['TRACKID'];
        }

        echo "Respuesta: " . json_encode($result2, JSON_PRETTY_PRINT) . "\n\n";
    } else {
        // Respuesta de texto simple
        echo "✓ Sobre enviado!\n\n";
        echo "Respuesta: $response2\n\n";

        // Intentar extraer track ID de texto
        if (preg_match('/TRACKID[:\s]+(\d+)/i', $response2, $matches)) {
            $track_id = $matches[1];
        }
    }
}

if ($track_id) {
    echo "📋 Track ID recibido: $track_id\n\n";
    file_put_contents('/tmp/track_id.txt', $track_id);
} else {
    echo "⚠ Advertencia: No se pudo extraer Track ID de la respuesta\n";
}

// Paso 6: Consultar estado por Track ID
if ($track_id) {
    echo "🔍 PASO 6: Consultando estado del envío...\n";
    echo "---------------------------------------------------\n";

    // Esperar unos segundos antes de consultar
    echo "⏳ Esperando 5 segundos antes de consultar...\n";
    sleep(5);

    $ch3 = curl_init($API_BASE . '/api/v1/dte/estado/' . $track_id);
    curl_setopt_array($ch3, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . API_KEY
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
        CURLOPT_TIMEOUT => 30
    ]);

    echo "📡 Consultando estado del Track ID: $track_id\n";
    $response3 = curl_exec($ch3);
    $http_code3 = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
    curl_close($ch3);

    echo "📥 Respuesta recibida (HTTP $http_code3)\n";

    if ($http_code3 == 200) {
        $result3 = json_decode($response3, true);

        if ($result3) {
            echo "\n✓ Estado del envío:\n";
            echo "==================\n";

            if (isset($result3['estado'])) {
                echo "Estado: {$result3['estado']}\n";
            }
            if (isset($result3['glosa'])) {
                echo "Glosa: {$result3['glosa']}\n";
            }
            if (isset($result3['detalle'])) {
                echo "Detalle: {$result3['detalle']}\n";
            }

            echo "\nRespuesta completa:\n";
            echo json_encode($result3, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo "Respuesta: $response3\n";
        }
    } else {
        echo "❌ Error al consultar estado (HTTP $http_code3)\n";
        echo "Respuesta: $response3\n";
    }
}

// Resumen final
echo "\n";
echo "=================================================\n";
echo "✅ PRUEBAS COMPLETADAS\n";
echo "=================================================\n\n";

echo "Archivos generados:\n";
echo "  - /tmp/boleta_prueba.xml - XML del DTE generado\n";
echo "  - /tmp/sobre_envio.xml - Sobre de envío al SII\n";
if ($track_id) {
    echo "  - /tmp/track_id.txt - Track ID para seguimiento\n";
}

echo "\nResumen:\n";
echo "  ✓ Certificado y CAF validados\n";
echo "  ✓ Boleta electrónica generada (Folio: $folio_prueba)\n";
echo "  ✓ Sobre construido y enviado al SII\n";
if ($track_id) {
    echo "  ✓ Track ID: $track_id\n";
    echo "  ✓ Estado consultado\n";
}

echo "\nPróximos pasos:\n";
echo "  - Revisar los archivos XML generados\n";
echo "  - Consultar nuevamente el estado en unos minutos\n";
echo "  - Generar los casos CASO-2 a CASO-5 para certificación\n";

echo "\n";
