<?php
/**
 * Test: Generar Boleta con Envío de Email
 *
 * Genera una boleta de prueba y la envía por email a ale.fvaras@gmail.com
 */

require_once __DIR__ . '/lib/VisualHelper.php';
require_once __DIR__ . '/config/settings.php';

$v = VisualHelper::getInstance();
$config = ConfiguracionSistema::getInstance();

// Limpiar pantalla
$v->limpiar();
$v->titulo("TEST: GENERACIÓN DE BOLETA CON ENVÍO DE EMAIL", "═");

echo "\n";
$v->mensaje('info', 'Configurando sistema para envío de email...');

// ========================================
// CONFIGURACIÓN
// ========================================

define('API_KEY', '9794-N370-6392-6913-8052');
define('CERT_PATH', __DIR__ . '/16694181-4.pfx');
define('CERT_PASSWORD', '5605');
define('CAF_PATH', __DIR__ . '/FoliosSII78274225391889202511161321.xml');
define('RUT_EMISOR', '78274225-6');
define('RAZON_SOCIAL', 'AKIBARA SPA');
define('AMBIENTE', 'certificacion');

$API_BASE = 'https://api.simpleapi.cl';

// Email del destinatario
$EMAIL_DESTINATARIO = 'ale.fvaras@gmail.com';

$CONFIG = [
    'envio_automatico_email' => true,
    'email_destinatario' => $EMAIL_DESTINATARIO,
    'consulta_automatica' => true,
    'espera_consulta_segundos' => 10,
    'guardar_xml' => true,
    'directorio_xml' => __DIR__ . '/xmls',
    'email_remitente' => 'boletas@akibara.cl',
    'adjuntar_pdf' => true,
    'adjuntar_xml' => true,
];

// ========================================
// DATOS DE PRUEBA
// ========================================

$v->subtitulo("Datos de la Boleta de Prueba");

$cliente = [
    'rut' => '66666666-6',
    'nombre' => 'Alejandro Varas',
    'direccion' => 'Santiago, Chile',
    'email' => $EMAIL_DESTINATARIO,
];

$items = [
    [
        'nombre' => 'Servicio de Desarrollo de Software',
        'cantidad' => 1,
        'precio' => 350000,
    ],
    [
        'nombre' => 'Consultoría Técnica',
        'cantidad' => 2,
        'precio' => 85000,
    ],
    [
        'nombre' => 'Soporte Mensual',
        'cantidad' => 1,
        'precio' => 120000,
    ],
];

// Calcular total
$total_neto = 0;
foreach ($items as $item) {
    $total_neto += $item['cantidad'] * $item['precio'];
}
$iva = round($total_neto * 0.19);
$total = $total_neto + $iva;

$v->lista([
    ['texto' => 'Cliente', 'valor' => $cliente['nombre']],
    ['texto' => 'RUT', 'valor' => $cliente['rut']],
    ['texto' => 'Email', 'valor' => $cliente['email']],
    ['texto' => 'Items', 'valor' => count($items)],
    ['texto' => 'Total Neto', 'valor' => '$' . number_format($total_neto, 0, ',', '.')],
    ['texto' => 'IVA', 'valor' => '$' . number_format($iva, 0, ',', '.')],
    ['texto' => 'Total', 'valor' => '$' . number_format($total, 0, ',', '.')],
]);

echo "\n";
if (!$v->confirmar("¿Generar boleta y enviar a {$EMAIL_DESTINATARIO}?", true)) {
    $v->mensaje('info', 'Operación cancelada');
    exit(0);
}

// ========================================
// CARGAR COMPONENTES
// ========================================

echo "\n";
$v->subtitulo("Cargando Componentes del Sistema");

require_once __DIR__ . '/lib/generar-pdf-boleta.php';

$v->mensaje('success', 'PDF generator cargado');

// ========================================
// OBTENER FOLIO
// ========================================

$v->subtitulo("Obteniendo Folio");

// Leer CAF
$caf_xml = simplexml_load_file(CAF_PATH);
if (!$caf_xml || !isset($caf_xml->CAF->DA->RNG)) {
    $v->mensaje('error', 'Error al leer archivo CAF');
    exit(1);
}

$folio_desde = (int) $caf_xml->CAF->DA->RNG->D;
$folio_hasta = (int) $caf_xml->CAF->DA->RNG->H;

// Obtener próximo folio desde archivo de control
$control_file = __DIR__ . '/folios_usados.txt';
$folio = $folio_desde;

if (file_exists($control_file)) {
    $contenido = file_get_contents($control_file);
    if (preg_match('/Próximo folio: (\d+)/', $contenido, $matches)) {
        $folio = (int) $matches[1];
    }
}

// Validar folio
if ($folio > $folio_hasta) {
    $v->mensaje('error', "Sin folios disponibles (límite: $folio_hasta)");
    exit(1);
}

$v->lista([
    ['texto' => 'Folio a usar', 'valor' => $folio],
    ['texto' => 'Rango disponible', 'valor' => "$folio_desde - $folio_hasta"],
    ['texto' => 'Folios restantes', 'valor' => ($folio_hasta - $folio + 1)],
]);

// ========================================
// GENERAR DTE
// ========================================

echo "\n";
$v->subtitulo("Generando DTE (Documento Tributario Electrónico)");

$dte = [
    'Encabezado' => [
        'IdDoc' => [
            'TipoDTE' => 39,
            'Folio' => $folio,
            'FchEmis' => date('Y-m-d'),
        ],
        'Emisor' => [
            'RUTEmisor' => RUT_EMISOR,
            'RznSoc' => RAZON_SOCIAL,
            'GiroEmis' => 'Servicios de Tecnología',
            'Acteco' => 620200,
            'DirOrigen' => 'Av. Providencia 1234',
            'CmnaOrigen' => 'Providencia',
        ],
        'Receptor' => [
            'RUTRecep' => $cliente['rut'],
            'RznSocRecep' => $cliente['nombre'],
            'DirRecep' => $cliente['direccion'],
            'CmnaRecep' => 'Santiago',
        ],
        'Totales' => [
            'MntNeto' => $total_neto,
            'TasaIVA' => 19,
            'IVA' => $iva,
            'MntTotal' => $total,
        ],
    ],
    'Detalle' => $items,
];

$v->cargando("Preparando documento DTE", 1);
$v->mensaje('success', 'DTE generado correctamente');

// ========================================
// FIRMAR Y ENVIAR AL SII
// ========================================

$v->subtitulo("Enviando al SII");

$dte_json = json_encode($dte);

$ch = curl_init($API_BASE . '/dte/document');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $dte_json);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Basic ' . API_KEY,
    'Content-Type: application/json',
]);

$v->cargando("Conectando con Simple API", 2);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    $v->mensaje('error', "Error al enviar al SII (HTTP $http_code)");
    echo "Respuesta: $response\n";
    exit(1);
}

$resultado_sii = json_decode($response, true);

if (!$resultado_sii || !isset($resultado_sii['trackId'])) {
    $v->mensaje('error', 'Error en respuesta del SII');
    echo "Respuesta: $response\n";
    exit(1);
}

$track_id = $resultado_sii['trackId'];

$v->mensaje('success', "DTE enviado al SII exitosamente");
$v->lista([
    ['texto' => 'Track ID', 'valor' => $track_id],
    ['texto' => 'Folio', 'valor' => $folio],
]);

// ========================================
// GENERAR PDF
// ========================================

echo "\n";
$v->subtitulo("Generando PDF de la Boleta");

$pdf_dir = __DIR__ . '/pdfs';
if (!is_dir($pdf_dir)) {
    mkdir($pdf_dir, 0755, true);
}

$pdf_filename = "boleta_{$folio}_" . date('Y-m-d') . ".pdf";
$pdf_path = $pdf_dir . '/' . $pdf_filename;

try {
    $v->cargando("Creando PDF con timbre PDF417", 2);

    generar_pdf_boleta(
        folio: $folio,
        fecha_emision: date('Y-m-d'),
        rut_emisor: RUT_EMISOR,
        razon_social: RAZON_SOCIAL,
        rut_cliente: $cliente['rut'],
        nombre_cliente: $cliente['nombre'],
        direccion_cliente: $cliente['direccion'],
        items: $items,
        total_neto: $total_neto,
        iva: $iva,
        total: $total,
        track_id: $track_id,
        pdf_path: $pdf_path
    );

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
// GUARDAR XML
// ========================================

if ($CONFIG['guardar_xml']) {
    $xml_dir = $CONFIG['directorio_xml'];
    if (!is_dir($xml_dir)) {
        mkdir($xml_dir, 0755, true);
    }

    $xml_filename = "boleta_{$folio}_" . date('Y-m-d') . ".json";
    $xml_path = $xml_dir . '/' . $xml_filename;

    file_put_contents($xml_path, json_encode($dte, JSON_PRETTY_PRINT));
    $v->mensaje('success', "DTE guardado: $xml_filename");
}

// ========================================
// ACTUALIZAR CONTROL DE FOLIOS
// ========================================

$proximo_folio = $folio + 1;
$registro = sprintf(
    "[%s] DTE 39 - Folio usado: %d - Próximo folio: %d - Track ID: %s\n",
    date('Y-m-d H:i:s'),
    $folio,
    $proximo_folio,
    $track_id
);

file_put_contents($control_file, $registro, FILE_APPEND);
$v->mensaje('success', "Control de folios actualizado (próximo: $proximo_folio)");

// ========================================
// ENVIAR EMAIL
// ========================================

echo "\n";
$v->subtitulo("Enviando Email con PDF Adjunto");

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
            <strong>Neto:</strong> $" . number_format($total_neto, 0, ',', '.') . "<br>
            <strong>IVA (19%):</strong> $" . number_format($iva, 0, ',', '.') . "<br>
            <span class='total'>TOTAL: $" . number_format($total, 0, ',', '.') . "</span>
        </div>

        <p><strong>El PDF de la boleta se encuentra adjunto a este correo.</strong></p>

        <p>Este es un documento tributario electrónico válido ante el Servicio de Impuestos Internos (SII) de Chile.</p>
    </div>

    <div class='footer'>
        <p>" . RAZON_SOCIAL . " - RUT: " . RUT_EMISOR . "</p>
        <p>Este correo fue generado automáticamente. Por favor no responder.</p>
        <p>Ambiente: " . strtoupper(AMBIENTE) . "</p>
    </div>
</body>
</html>
";

// Preparar email con adjunto
$boundary = md5(time());

$headers = [
    "From: " . $CONFIG['email_remitente'],
    "Reply-To: " . $CONFIG['email_remitente'],
    "MIME-Version: 1.0",
    "Content-Type: multipart/mixed; boundary=\"$boundary\"",
];

$body = "--$boundary\r\n";
$body .= "Content-Type: text/html; charset=UTF-8\r\n";
$body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
$body .= $mensaje_html . "\r\n\r\n";

// Adjuntar PDF
if ($CONFIG['adjuntar_pdf'] && file_exists($pdf_path)) {
    $pdf_content = file_get_contents($pdf_path);
    $pdf_encoded = chunk_split(base64_encode($pdf_content));

    $body .= "--$boundary\r\n";
    $body .= "Content-Type: application/pdf; name=\"$pdf_filename\"\r\n";
    $body .= "Content-Disposition: attachment; filename=\"$pdf_filename\"\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= $pdf_encoded . "\r\n\r\n";
}

// Adjuntar JSON (DTE)
if ($CONFIG['adjuntar_xml'] && isset($xml_path) && file_exists($xml_path)) {
    $json_content = file_get_contents($xml_path);
    $json_encoded = chunk_split(base64_encode($json_content));

    $body .= "--$boundary\r\n";
    $body .= "Content-Type: application/json; name=\"$xml_filename\"\r\n";
    $body .= "Content-Disposition: attachment; filename=\"$xml_filename\"\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= $json_encoded . "\r\n\r\n";
}

$body .= "--$boundary--";

// Enviar email
$v->cargando("Enviando email a {$EMAIL_DESTINATARIO}", 2);

$email_enviado = mail(
    $EMAIL_DESTINATARIO,
    $asunto,
    $body,
    implode("\r\n", $headers)
);

if ($email_enviado) {
    $v->mensaje('success', "Email enviado exitosamente a {$EMAIL_DESTINATARIO}");
} else {
    $v->mensaje('warning', 'No se pudo enviar el email (verifica configuración SMTP del servidor)');
    $v->caja(
        "El PDF se generó correctamente en: $pdf_path\n" .
        "Puedes enviarlo manualmente o configurar SMTP en el servidor.",
        'info'
    );
}

// ========================================
// CONSULTAR ESTADO SII
// ========================================

if ($CONFIG['consulta_automatica']) {
    echo "\n";
    $v->subtitulo("Consultando Estado en SII");

    $v->mensaje('info', "Esperando {$CONFIG['espera_consulta_segundos']} segundos antes de consultar...");
    sleep($CONFIG['espera_consulta_segundos']);

    $v->cargando("Consultando Track ID $track_id", 2);

    $ch = curl_init($API_BASE . "/dte/document/$track_id/status");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . API_KEY,
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $estado = json_decode($response, true);

        if ($estado && isset($estado['estado'])) {
            $estado_sii = $estado['estado'];
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
        'valor' => '$' . number_format($total, 0, ',', '.'),
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
    "Revisa tu bandeja de entrada (y spam si no lo ves).",
    'success'
);

echo "\n";
echo $v->dim("Test completado - " . date('Y-m-d H:i:s')) . "\n\n";
