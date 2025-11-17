<?php
/**
 * Verificar respuesta del SII para Track ID 25791176
 */

require_once(__DIR__ . '/lib/VisualHelper.php');

$v = VisualHelper::getInstance();
$v->limpiar();

echo "\n";
$v->titulo("Verificación de Respuesta SII - Track ID 25791176");
echo "\n";

// Configuración
define('API_BASE', 'https://api.simple.cl');
define('API_KEY', '9794-N370-6392-6913-8052');
define('CERT_PATH', __DIR__ . '/16694181-4.pfx');
define('CERT_PASSWORD', 'Santiaguino.2017');
define('RUT_EMISOR', '76063822-6');

$track_id = '25791176';
$folio = 1909;

$v->subtitulo("Datos del Envío");
$v->lista([
    ['texto' => 'Track ID', 'valor' => $track_id],
    ['texto' => 'Folio', 'valor' => $folio],
    ['texto' => 'Fecha envío', 'valor' => '2025-11-17'],
    ['texto' => 'Tipo DTE', 'valor' => '39 (Boleta Electrónica)'],
]);

echo "\n";

// ============================================================================
// CONSULTAR ESTADO EN SIMPLE API
// ============================================================================

$v->subtitulo("Consultando Estado en Simple API");

$boundary = '----WebKitFormBoundary' . md5(time());
$eol = "\r\n";
$body = '';

// Configuración de consulta
$consulta_config = [
    'Certificado' => [
        'Rut' => '16694181-4',
        'Password' => CERT_PASSWORD
    ],
    'Ambiente' => 0,  // 0 = Certificación
    'TrackId' => (int)$track_id,
    'RutEmisor' => RUT_EMISOR
];

$body .= '--' . $boundary . $eol;
$body .= 'Content-Disposition: form-data; name="input"' . $eol . $eol;
$body .= json_encode($consulta_config) . $eol;

// Certificado
$body .= '--' . $boundary . $eol;
$body .= 'Content-Disposition: form-data; name="files"; filename="' . basename(CERT_PATH) . '"' . $eol;
$body .= 'Content-Type: application/x-pkcs12' . $eol . $eol;
$body .= file_get_contents(CERT_PATH) . $eol;

$body .= '--' . $boundary . '--' . $eol;

$ch = curl_init(API_BASE . '/api/v1/envio/consultar');
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
    CURLOPT_TIMEOUT => 60,
]);

$v->cargando("Consultando estado", 1);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "\n";

if ($curl_error) {
    $v->mensaje('error', "Error CURL: $curl_error");
    exit(1);
}

$v->lista([
    ['texto' => 'HTTP Code', 'valor' => $http_code],
    ['texto' => 'Tamaño respuesta', 'valor' => strlen($response) . ' bytes'],
]);

echo "\n";

// Analizar respuesta
if ($http_code == 200) {
    $v->subtitulo("Respuesta del SII");
    echo "\n";

    // Intentar parsear como XML
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($response);

    if ($xml) {
        echo "  📄 Formato: XML\n\n";

        // Buscar información relevante
        if (isset($xml->RESP_HDR)) {
            echo "  📋 ENCABEZADO:\n";
            foreach ($xml->RESP_HDR->children() as $key => $value) {
                echo "     • $key: $value\n";
            }
            echo "\n";
        }

        if (isset($xml->RESP_BODY)) {
            echo "  📦 CUERPO DE RESPUESTA:\n";
            foreach ($xml->RESP_BODY->children() as $key => $value) {
                if ($key == 'ESTADO_TRACKID') {
                    echo "     🎯 Estado Track ID: $value\n";
                } elseif ($key == 'ERR_CODE') {
                    echo "     ⚠️  Código Error: $value\n";
                } elseif ($key == 'GLOSA_ESTADO') {
                    echo "     📝 Glosa: $value\n";
                } elseif ($key == 'NUM_ATENCION') {
                    echo "     🔢 Número Atención: $value\n";
                } else {
                    echo "     • $key: $value\n";
                }
            }
            echo "\n";
        }

        if (isset($xml->RESP_BODY->REVISIONENVIO)) {
            echo "  🔍 REVISIÓN DEL ENVÍO:\n";
            foreach ($xml->RESP_BODY->REVISIONENVIO->children() as $key => $value) {
                echo "     • $key: $value\n";
            }
            echo "\n";
        }

        if (isset($xml->RESP_BODY->REVISIONDTE)) {
            echo "  📋 REVISIÓN DEL DTE:\n";
            if (is_array($xml->RESP_BODY->REVISIONDTE) || $xml->RESP_BODY->REVISIONDTE instanceof Traversable) {
                foreach ($xml->RESP_BODY->REVISIONDTE as $dte) {
                    echo "     ─────────────────────\n";
                    foreach ($dte as $key => $value) {
                        if ($key == 'ESTADO') {
                            $icono = $value == 'EPR' ? '✅' : ($value == 'RCH' ? '❌' : '⚠️');
                            echo "     $icono Estado: $value\n";
                        } elseif ($key == 'FOLIO') {
                            echo "     🔢 Folio: $value\n";
                        } elseif ($key == 'DETALLE') {
                            echo "     📝 Detalle: $value\n";
                        } else {
                            echo "     • $key: $value\n";
                        }
                    }
                }
            } else {
                foreach ($xml->RESP_BODY->REVISIONDTE as $key => $value) {
                    echo "     • $key: $value\n";
                }
            }
            echo "\n";
        }

        // Mostrar XML formateado
        $v->subtitulo("XML Completo de Respuesta");
        echo "\n";
        $dom = new DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($response);
        $formatted_xml = $dom->saveXML();

        // Mostrar solo primeras 100 líneas
        $lines = explode("\n", $formatted_xml);
        $to_show = array_slice($lines, 0, 100);
        echo implode("\n", $to_show);

        if (count($lines) > 100) {
            echo "\n... (" . (count($lines) - 100) . " líneas más)\n";
        }

    } else {
        // Intentar como JSON
        $json = json_decode($response, true);

        if ($json) {
            echo "  📄 Formato: JSON\n\n";
            echo json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            echo "\n\n";
        } else {
            // Mostrar como texto
            echo "  📄 Formato: Texto plano\n\n";
            echo $response . "\n\n";
        }
    }

} else {
    $v->mensaje('error', "Error al consultar: HTTP $http_code");
    echo "\nRespuesta:\n$response\n";
}

echo "\n";
$v->subtitulo("Interpretación de Estados SII");
echo "\n";

echo "  Estados posibles del Track ID:\n";
echo "  • EPR = En Proceso (el SII está procesando)\n";
echo "  • DOK = Documento OK (aceptado)\n";
echo "  • RCH = Rechazado por el SII\n";
echo "  • RCT = Rechazado por errores en carátula\n";
echo "  • RFR = Rechazado por error de firma\n";
echo "  • RSC = Rechazado por error de schema\n";
echo "\n";

echo "  Estados de DTE individual:\n";
echo "  • EPR = En Proceso\n";
echo "  • ACD = Aceptado con Discrepancias\n";
echo "  • ACT = Aceptado\n";
echo "  • RCH = Rechazado\n";
echo "\n";

echo "═══════════════════════════════════════════════════════════════════\n";
echo "\n";
