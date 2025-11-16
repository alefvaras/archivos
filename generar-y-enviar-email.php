<?php
/**
 * Generador de Boleta con Envío de Email
 * Genera boleta, envía al SII, crea PDF y envía por email
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/lib/VisualHelper.php';
require_once __DIR__ . '/lib/generar-pdf-boleta.php';

$v = VisualHelper::getInstance();

$v->limpiar();
$v->titulo("GENERADOR DE BOLETA CON ENVÍO DE EMAIL", "═");

// ========================================
// CONFIGURACIÓN
// ========================================

define('API_KEY', '9794-N370-6392-6913-8052');
define('CERT_PATH', __DIR__ . '/16694181-4.pfx');
define('CERT_PASSWORD', '5605');
define('CAF_PATH', __DIR__ . '/FoliosSII78274225391889202511161321.xml');
define('RUT_EMISOR', '78274225-6');
define('RAZON_SOCIAL', 'AKIBARA SPA');

$API_BASE = 'https://api.simpleapi.cl';
$EMAIL_DESTINATARIO = 'ale.fvaras@gmail.com';

// ========================================
// LEER PRÓXIMO FOLIO
// ========================================

$v->subtitulo("Obteniendo Próximo Folio");

$control_file = __DIR__ . '/folios_usados.txt';
$folio = 1889; // Por defecto

if (file_exists($control_file)) {
    $folio = (int) trim(file_get_contents($control_file));
}

// Leer CAF para validar rango
$caf_xml_content = file_get_contents(CAF_PATH);
$caf = simplexml_load_string($caf_xml_content);
$folio_desde = (int) ((string) $caf->CAF->DA->RNG->D);
$folio_hasta = (int) ((string) $caf->CAF->DA->RNG->H);

if ($folio > $folio_hasta) {
    $v->mensaje('error', "Sin folios disponibles (límite: $folio_hasta)");
    exit(1);
}

$v->lista([
    ['texto' => 'Folio a usar', 'valor' => $folio],
    ['texto' => 'Rango', 'valor' => "$folio_desde - $folio_hasta"],
    ['texto' => 'Disponibles', 'valor' => ($folio_hasta - $folio + 1)],
]);

// ========================================
// DATOS DE LA BOLETA
// ========================================

echo "\n";
$v->subtitulo("Datos de la Boleta");

$cliente = [
    'rut' => '66666666-6',
    'nombre' => 'Alejandro Varas',
    'direccion' => 'Santiago, Chile',
    'comuna' => 'Santiago',
    'email' => $EMAIL_DESTINATARIO,
];

$items = [
    [
        'nombre' => 'Desarrollo de Software - Sistema de Boletas Electrónicas',
        'cantidad' => 1,
        'precio' => 450000,
    ],
    [
        'nombre' => 'Consultoría Técnica y Asesoría',
        'cantidad' => 2,
        'precio' => 95000,
    ],
    [
        'nombre' => 'Soporte y Mantenimiento Mensual',
        'cantidad' => 1,
        'precio' => 150000,
    ],
];

// Calcular totales
$total_con_iva = 0;
foreach ($items as $item) {
    $total_con_iva += $item['cantidad'] * $item['precio'];
}
$neto = round($total_con_iva / 1.19);
$iva = $total_con_iva - $neto;

$v->lista([
    ['texto' => 'Cliente', 'valor' => $cliente['nombre']],
    ['texto' => 'Email', 'valor' => $cliente['email']],
    ['texto' => 'Items', 'valor' => count($items)],
    ['texto' => 'Neto', 'valor' => '$' . number_format($neto, 0, ',', '.')],
    ['texto' => 'IVA', 'valor' => '$' . number_format($iva, 0, ',', '.')],
    ['texto' => 'Total', 'valor' => '$' . number_format($total_con_iva, 0, ',', '.')],
]);

// ========================================
// CONSTRUIR DOCUMENTO DTE
// ========================================

echo "\n";
$v->subtitulo("Generando DTE");

$detalles = [];
foreach ($items as $item) {
    $detalles[] = [
        'IndicadorExento' => 0,
        'Nombre' => $item['nombre'],
        'Descripcion' => '',
        'Cantidad' => $item['cantidad'],
        'UnidadMedida' => 'un',
        'Precio' => $item['precio'],
        'Descuento' => 0,
        'Recargo' => 0,
        'MontoItem' => $item['cantidad'] * $item['precio'],
    ];
}

$documento = [
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
                'GiroBoleta' => 'Servicios de Tecnología',
                'DireccionOrigen' => 'Av. Providencia 1234',
                'ComunaOrigen' => 'Providencia'
            ],
            'Receptor' => [
                'Rut' => $cliente['rut'],
                'RazonSocial' => $cliente['nombre'],
                'Direccion' => $cliente['direccion'],
                'Comuna' => $cliente['comuna']
            ],
            'Totales' => [
                'MontoNeto' => $neto,
                'IVA' => $iva,
                'MontoTotal' => $total_con_iva
            ]
        ],
        'Detalles' => $detalles,
    ],
    'Certificado' => [
        'Rut' => '16694181-4',
        'Password' => CERT_PASSWORD
    ]
];

$v->mensaje('success', 'Documento DTE construido');

// ========================================
// ENVIAR A SIMPLE API
// ========================================

echo "\n";
$v->subtitulo("Enviando a Simple API");

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
$body .= $caf_xml_content . $eol;

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
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
    CURLOPT_TIMEOUT => 60,
]);

$v->cargando("Generando DTE firmado", 2);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    $v->mensaje('error', "Error CURL: $curl_error");
    exit(1);
}

if ($http_code != 200) {
    $v->mensaje('error', "Error HTTP: $http_code");
    echo "Respuesta: $response\n";
    exit(1);
}

// Simple API devuelve el XML del DTE
$dte_xml = $response;

if (strpos($dte_xml, '<?xml') === false) {
    $v->mensaje('error', "La respuesta no es XML válido");
    echo $response . "\n";
    exit(1);
}

$v->mensaje('success', 'DTE generado y firmado correctamente');

// Guardar XML
$xml_dir = __DIR__ . '/xmls';
if (!is_dir($xml_dir)) {
    mkdir($xml_dir, 0755, true);
}
file_put_contents($xml_dir . "/boleta_{$folio}.xml", $dte_xml);

// ========================================
// GENERAR SOBRE DE ENVÍO
// ========================================

echo "\n";
$v->subtitulo("Generando Sobre de Envío");

$boundary3 = '----WebKitFormBoundary' . md5(time() . 'sobre');
$body3 = '';

// Configuración para generar sobre
$sobre_config = [
    'Certificado' => [
        'Rut' => '16694181-4',
        'Password' => CERT_PASSWORD
    ],
    'Caratula' => [
        'RutEmisor' => RUT_EMISOR,
        'RutReceptor' => '60803000-K',
        'FechaResolucion' => date('Y-m-d'),
        'NumeroResolucion' => 0
    ]
];

$body3 .= '--' . $boundary3 . $eol;
$body3 .= 'Content-Disposition: form-data; name="input"' . $eol . $eol;
$body3 .= json_encode($sobre_config) . $eol;

// Certificado
$body3 .= '--' . $boundary3 . $eol;
$body3 .= 'Content-Disposition: form-data; name="files"; filename="' . basename(CERT_PATH) . '"' . $eol;
$body3 .= 'Content-Type: application/x-pkcs12' . $eol . $eol;
$body3 .= file_get_contents(CERT_PATH) . $eol;

// DTE XML
$body3 .= '--' . $boundary3 . $eol;
$body3 .= 'Content-Disposition: form-data; name="files"; filename="boleta.xml"' . $eol;
$body3 .= 'Content-Type: text/xml' . $eol . $eol;
$body3 .= $dte_xml . $eol;

$body3 .= '--' . $boundary3 . '--' . $eol;

$ch3 = curl_init($API_BASE . '/api/v1/envio/generar');
curl_setopt_array($ch3, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body3,
    CURLOPT_HTTPHEADER => [
        'Authorization: ' . API_KEY,
        'Content-Type: multipart/form-data; boundary=' . $boundary3,
        'Content-Length: ' . strlen($body3)
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_TIMEOUT => 60,
]);

$v->cargando("Generando sobre firmado", 2);

$response3 = curl_exec($ch3);
$http_code3 = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
curl_close($ch3);

if ($http_code3 != 200) {
    $v->mensaje('error', "Error al generar sobre (HTTP $http_code3)");
    echo "Respuesta: $response3\n";
    exit(1);
}

$sobre_xml = $response3;

if (strpos($sobre_xml, '<?xml') === false) {
    $v->mensaje('error', "La respuesta no es XML válido");
    echo $response3 . "\n";
    exit(1);
}

$v->mensaje('success', 'Sobre de envío generado correctamente');

// ========================================
// ENVIAR AL SII
// ========================================

echo "\n";
$v->subtitulo("Enviando al SII");

$boundary2 = '----WebKitFormBoundary' . md5(time() . 'enviar');
$body2 = '';

// Configuración de envío
$envio_config = [
    'Certificado' => [
        'Rut' => '16694181-4',
        'Password' => CERT_PASSWORD
    ],
    'Ambiente' => 0,  // 0 = Certificación
    'Tipo' => 2       // 2 = EnvioBoleta
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
    CURLOPT_TIMEOUT => 90,
]);

$v->cargando("Enviando al SII", 3);

$response2 = curl_exec($ch2);
$http_code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

if ($http_code2 != 200) {
    $v->mensaje('error', "Error al enviar al SII (HTTP $http_code2)");
    echo "Respuesta: $response2\n";
    exit(1);
}

// Parsear respuesta del SII
$track_id = null;

libxml_use_internal_errors(true);
$xml_response = simplexml_load_string($response2);

if ($xml_response) {
    // Buscar Track ID en el XML
    if (isset($xml_response->TRACKID)) {
        $track_id = (string) $xml_response->TRACKID;
    } elseif (isset($xml_response->track_id)) {
        $track_id = (string) $xml_response->track_id;
    }
} else {
    // Intentar como JSON
    $result2 = json_decode($response2, true);
    if ($result2 && isset($result2['trackId'])) {
        $track_id = $result2['trackId'];
    } elseif ($result2 && isset($result2['TRACKID'])) {
        $track_id = $result2['TRACKID'];
    }
}

if (!$track_id) {
    $v->mensaje('warning', 'No se pudo obtener Track ID de la respuesta');
    echo "Respuesta del SII:\n$response2\n";

    // Intentar extraer de cualquier manera
    if (preg_match('/<TRACKID>(\d+)<\/TRACKID>/i', $response2, $matches)) {
        $track_id = $matches[1];
    } elseif (preg_match('/"trackId["]?\s*:\s*"?(\d+)"?/i', $response2, $matches)) {
        $track_id = $matches[1];
    }
}

if (!$track_id) {
    $v->mensaje('error', 'No se pudo obtener Track ID del SII');
    exit(1);
}

$v->mensaje('success', "Enviado al SII exitosamente");
$v->lista([
    ['texto' => 'Track ID', 'valor' => $track_id],
    ['texto' => 'Folio', 'valor' => $folio],
]);

// ========================================
// GENERAR PDF
// ========================================

echo "\n";
$v->subtitulo("Generando PDF");

$pdf_dir = __DIR__ . '/pdfs';
if (!is_dir($pdf_dir)) {
    mkdir($pdf_dir, 0755, true);
}

$pdf_filename = "boleta_{$folio}_" . date('Y-m-d') . ".pdf";
$pdf_path = $pdf_dir . '/' . $pdf_filename;

try {
    $v->cargando("Creando PDF con timbre PDF417", 2);

    // Generar PDF usando el documento DTE y el XML generado
    generar_pdf_boleta($documento, $dte_xml, $pdf_path);

    $v->mensaje('success', "PDF generado: $pdf_filename");
    $v->lista([
        ['texto' => 'Ruta', 'valor' => $pdf_path],
        ['texto' => 'Tamaño', 'valor' => number_format(filesize($pdf_path) / 1024, 2) . ' KB'],
    ]);

} catch (Exception $e) {
    $v->mensaje('error', 'Error al generar PDF: ' . $e->getMessage());
    exit(1);
}

// ========================================
// ENVIAR EMAIL
// ========================================

echo "\n";
$v->subtitulo("Enviando Email");

$asunto = "Boleta Electrónica N° $folio - " . RAZON_SOCIAL;

$mensaje_html = "
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .header { background: #2980b9; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; }
        .footer { background: #ecf0f1; padding: 15px; text-align: center; font-size: 12px; color: #7f8c8d; }
        .info-box { background: #e8f4f8; border-left: 4px solid #2980b9; padding: 15px; margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #34495e; color: white; }
        .total { font-size: 18px; font-weight: bold; color: #27ae60; }
    </style>
</head>
<body>
    <div class='header'>
        <h1>✓ Boleta Electrónica Generada</h1>
        <p>Documento Tributario Electrónico</p>
    </div>

    <div class='content'>
        <h2>Estimado(a) {$cliente['nombre']},</h2>

        <p>Se ha generado exitosamente la siguiente boleta electrónica:</p>

        <div class='info-box'>
            <strong>Folio:</strong> $folio<br>
            <strong>Fecha:</strong> " . date('d/m/Y') . "<br>
            <strong>Track ID SII:</strong> $track_id<br>
            <strong>Emisor:</strong> " . RAZON_SOCIAL . "<br>
            <strong>RUT Emisor:</strong> " . RUT_EMISOR . "
        </div>

        <h3>Detalle de Items:</h3>
        <table>
            <thead>
                <tr>
                    <th>Descripción</th>
                    <th>Cantidad</th>
                    <th>Precio Unit.</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>";

foreach ($items as $item) {
    $subtotal = $item['cantidad'] * $item['precio'];
    $mensaje_html .= "
                <tr>
                    <td>{$item['nombre']}</td>
                    <td>{$item['cantidad']}</td>
                    <td>$" . number_format($item['precio'], 0, ',', '.') . "</td>
                    <td>$" . number_format($subtotal, 0, ',', '.') . "</td>
                </tr>";
}

$mensaje_html .= "
            </tbody>
        </table>

        <div class='info-box'>
            <strong>Neto:</strong> $" . number_format($neto, 0, ',', '.') . "<br>
            <strong>IVA (19%):</strong> $" . number_format($iva, 0, ',', '.') . "<br>
            <span class='total'>TOTAL: $" . number_format($total_con_iva, 0, ',', '.') . "</span>
        </div>

        <p><strong>El PDF de la boleta se encuentra adjunto a este correo.</strong></p>

        <p>Este es un documento tributario electrónico válido ante el Servicio de Impuestos Internos (SII) de Chile.</p>
    </div>

    <div class='footer'>
        <p>" . RAZON_SOCIAL . " - RUT: " . RUT_EMISOR . "</p>
        <p>Este correo fue generado automáticamente. Por favor no responder.</p>
        <p>Ambiente: CERTIFICACIÓN</p>
    </div>
</body>
</html>
";

// Preparar email con adjunto
$boundary_email = md5(time() . 'email');

$headers = [
    "From: boletas@akibara.cl",
    "Reply-To: boletas@akibara.cl",
    "MIME-Version: 1.0",
    "Content-Type: multipart/mixed; boundary=\"$boundary_email\"",
];

$body_email = "--$boundary_email\r\n";
$body_email .= "Content-Type: text/html; charset=UTF-8\r\n";
$body_email .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
$body_email .= $mensaje_html . "\r\n\r\n";

// Adjuntar PDF
$pdf_content = file_get_contents($pdf_path);
$pdf_encoded = chunk_split(base64_encode($pdf_content));

$body_email .= "--$boundary_email\r\n";
$body_email .= "Content-Type: application/pdf; name=\"$pdf_filename\"\r\n";
$body_email .= "Content-Disposition: attachment; filename=\"$pdf_filename\"\r\n";
$body_email .= "Content-Transfer-Encoding: base64\r\n\r\n";
$body_email .= $pdf_encoded . "\r\n\r\n";

$body_email .= "--$boundary_email--";

// Enviar email
$v->cargando("Enviando email a {$EMAIL_DESTINATARIO}", 2);

$email_enviado = mail(
    $EMAIL_DESTINATARIO,
    $asunto,
    $body_email,
    implode("\r\n", $headers)
);

if ($email_enviado) {
    $v->mensaje('success', "Email enviado exitosamente a {$EMAIL_DESTINATARIO}");
} else {
    $v->mensaje('warning', 'No se pudo enviar el email (verifica configuración SMTP del servidor)');
    $v->caja(
        "El PDF se generó correctamente en: $pdf_path\n" .
        "Puedes enviarlo manualmente.",
        'info'
    );
}

// ========================================
// ACTUALIZAR CONTROL DE FOLIOS
// ========================================

$proximo_folio = $folio + 1;
file_put_contents($control_file, $proximo_folio);

$v->mensaje('success', "Control de folios actualizado (próximo: $proximo_folio)");

// ========================================
// CONSULTAR ESTADO SII
// ========================================

echo "\n";
$v->subtitulo("Consultando Estado en SII");

$v->mensaje('info', "Esperando 10 segundos antes de consultar...");
sleep(10);

$ch3 = curl_init($API_BASE . "/api/v1/envio/$track_id/estado");
curl_setopt_array($ch3, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: ' . API_KEY,
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 30,
]);

$v->cargando("Consultando Track ID $track_id", 2);

$response3 = curl_exec($ch3);
$http_code3 = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
curl_close($ch3);

if ($http_code3 === 200) {
    $estado_xml = simplexml_load_string($response3);

    if ($estado_xml) {
        $estado_sii = (string) $estado_xml->status;
        $tipo = $estado_sii === 'EPR' ? 'success' :
               ($estado_sii === 'REC' ? 'info' : 'warning');

        $v->mensaje($tipo, "Estado SII: $estado_sii");

        $estados_texto = [
            'REC' => 'Recibido por el SII (en proceso)',
            'EPR' => 'Procesado correctamente - Aceptado',
            'RCH' => 'Rechazado por el SII',
            'RPR' => 'Rechazado con reparos',
        ];

        if (isset($estados_texto[$estado_sii])) {
            echo "      " . $v->dim($estados_texto[$estado_sii]) . "\n";
        }
    }
} else {
    $v->mensaje('warning', 'No se pudo consultar el estado (intenta más tarde)');
}

// ========================================
// RESUMEN FINAL
// ========================================

echo "\n";
$v->separador('═');
echo "\n";

$v->resumen("RESUMEN DE LA OPERACIÓN", [
    'folio' => [
        'texto' => 'Folio',
        'valor' => $folio,
        'tipo' => 'success',
        'icono' => '📄'
    ],
    'track' => [
        'texto' => 'Track ID',
        'valor' => $track_id,
        'tipo' => 'info',
        'icono' => '🔍'
    ],
    'total' => [
        'texto' => 'Total',
        'valor' => '$' . number_format($total_con_iva, 0, ',', '.'),
        'tipo' => 'success',
        'icono' => '💰'
    ],
    'pdf' => [
        'texto' => 'PDF',
        'valor' => $pdf_filename,
        'tipo' => 'success',
        'icono' => '✓'
    ],
    'email' => [
        'texto' => 'Email',
        'valor' => $email_enviado ? 'Enviado' : 'No enviado',
        'tipo' => $email_enviado ? 'success' : 'warning',
        'icono' => $email_enviado ? '✉' : '⚠'
    ],
]);

$v->caja(
    "¡Boleta generada exitosamente!\n\n" .
    "El PDF ha sido enviado a: {$EMAIL_DESTINATARIO}\n" .
    "Revisa tu bandeja de entrada (y spam si no lo ves).\n\n" .
    "Track ID: $track_id",
    'success'
);

echo "\n";
echo $v->dim("Generación completada - " . date('Y-m-d H:i:s')) . "\n\n";
