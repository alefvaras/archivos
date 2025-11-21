#!/usr/bin/env php
<?php
/**
 * PRUEBA DE ENVÍO DE EMAIL CON PDF
 *
 * Genera una boleta electrónica de prueba y la envía por correo con el PDF adjunto
 *
 * Uso: php prueba-envio-email-pdf.php tu@email.com
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Verificar que se proporcionó un email
if ($argc < 2 || empty($argv[1])) {
    echo "\nUso: php prueba-envio-email-pdf.php tu@email.com\n\n";
    echo "Ejemplo: php prueba-envio-email-pdf.php mimail@ejemplo.com\n\n";
    exit(1);
}

$emailDestino = $argv[1];

// Validar email
if (!filter_var($emailDestino, FILTER_VALIDATE_EMAIL)) {
    die("\n❌ Error: Email inválido '{$emailDestino}'\n\n");
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "  PRUEBA DE ENVÍO DE BOLETA POR EMAIL\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n";
echo "Email destino: {$emailDestino}\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Cargar configuración
require_once __DIR__ . '/config/settings.php';
$config = ConfiguracionSistema::getInstance();

// Verificar configuración de email
echo "📧 Verificando configuración de email...\n";
$emailConfig = $config->get('email');
echo "   Método: " . $emailConfig['metodo'] . "\n";
echo "   From: " . $emailConfig['from_email'] . "\n";

if (!empty($emailConfig['smtp_host'])) {
    echo "   SMTP Host: " . $emailConfig['smtp_host'] . "\n";
    echo "   SMTP Port: " . $emailConfig['smtp_port'] . "\n";
}
echo "\n";

echo "════════════════════════════════════════════════════════════════\n";
echo " PASO 1: GENERAR BOLETA DE PRUEBA\n";
echo "════════════════════════════════════════════════════════════════\n\n";

// Datos de la boleta
$datosCliente = [
    'rut' => '66666666-6',
    'razon_social' => 'Cliente de Prueba Email',
    'giro' => 'Servicios',
    'direccion' => 'Calle Prueba 123',
    'comuna' => 'Santiago',
    'email' => $emailDestino,
];

$items = [
    [
        'nombre' => 'Producto de Prueba Email',
        'descripcion' => 'Este es un producto de prueba para validar el envío de email',
        'cantidad' => 1,
        'precio' => 10000,
        'descuento' => 0,
    ],
];

// Calcular totales
$neto = 0;
foreach ($items as $item) {
    $subtotal = ($item['precio'] * $item['cantidad']) - $item['descuento'];
    $neto += $subtotal;
}

$iva = round($neto * 0.19);
$total = $neto + $iva;

echo "Items de la boleta:\n";
foreach ($items as $i => $item) {
    $subtotal = $item['precio'] * $item['cantidad'];
    echo sprintf("  %d. %s x%d = $%s\n",
        $i+1,
        $item['nombre'],
        $item['cantidad'],
        number_format($subtotal, 0, ',', '.')
    );
}
echo "\nTotal: $" . number_format($total, 0, ',', '.') . "\n\n";

// Preparar datos para SimpleAPI
$datosSimpleAPI = [
    'dte' => [
        'Encabezado' => [
            'IdDoc' => [
                'TipoDTE' => 39,
                'Folio' => null,
                'FchEmis' => date('Y-m-d'),
            ],
            'Emisor' => [
                'RUTEmisor' => $config->get('emisor.rut'),
                'RznSoc' => $config->get('emisor.razon_social'),
                'GiroEmis' => $config->get('emisor.giro'),
                'Acteco' => 620200,
                'DirOrigen' => $config->get('emisor.direccion'),
                'CmnaOrigen' => $config->get('emisor.comuna'),
            ],
            'Receptor' => [
                'RUTRecep' => $datosCliente['rut'],
                'RznSocRecep' => $datosCliente['razon_social'],
                'GiroRecep' => $datosCliente['giro'],
                'DirRecep' => $datosCliente['direccion'],
                'CmnaRecep' => $datosCliente['comuna'],
                'CorreoRecep' => $datosCliente['email'],
            ],
            'Totales' => [
                'MntNeto' => $neto,
                'TasaIVA' => 19,
                'IVA' => $iva,
                'MntTotal' => $total,
            ],
        ],
        'Detalle' => [],
    ],
];

// Agregar items
foreach ($items as $i => $item) {
    $datosSimpleAPI['dte']['Detalle'][] = [
        'NroLinDet' => $i + 1,
        'NmbItem' => $item['nombre'],
        'DscItem' => $item['descripcion'],
        'QtyItem' => $item['cantidad'],
        'PrcItem' => $item['precio'],
        'MontoItem' => $item['precio'] * $item['cantidad'],
    ];
}

// Enviar a SimpleAPI
$apiKey = $config->get('api.api_key');
$apiUrl = $config->get('api.base_url');

echo "Enviando boleta a SimpleAPI...\n";
$ch = curl_init($apiUrl . '/dte/document');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode($datosSimpleAPI),
    CURLOPT_TIMEOUT => 60,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    die("❌ ERROR cURL: {$curlError}\n");
}

echo "Respuesta HTTP: {$httpCode}\n";

$resultado = json_decode($response, true);

if ($httpCode < 200 || $httpCode >= 300) {
    echo "\n❌ ERROR AL GENERAR BOLETA\n\n";
    echo "Código HTTP: {$httpCode}\n";
    echo "Respuesta:\n";
    echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    exit(1);
}

echo "\n✓ BOLETA GENERADA EXITOSAMENTE\n\n";

$trackId = $resultado['trackId'] ?? $resultado['track_id'] ?? null;
$folio = $resultado['folio'] ?? null;
$tipoDte = $resultado['tipo'] ?? 39;
$pdfUrl = $resultado['pdf'] ?? $resultado['pdfUrl'] ?? null;

echo "Track ID:  {$trackId}\n";
echo "Folio:     {$folio}\n";
echo "PDF URL:   " . ($pdfUrl ? 'Disponible' : 'No disponible') . "\n\n";

// Guardar respuesta
$archivoResultado = __DIR__ . '/logs/resultado-email-' . date('Y-m-d_His') . '.json';
file_put_contents($archivoResultado, json_encode($resultado, JSON_PRETTY_PRINT));

echo "════════════════════════════════════════════════════════════════\n";
echo " PASO 2: DESCARGAR PDF\n";
echo "════════════════════════════════════════════════════════════════\n\n";

$pdfPath = null;

// Opción 1: Descargar desde URL si está disponible
if ($pdfUrl) {
    echo "Descargando PDF desde SimpleAPI...\n";

    $ch = curl_init($pdfUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $pdfContent = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300 && $pdfContent) {
        $pdfPath = __DIR__ . '/pdfs/boleta-' . $folio . '-' . date('YmdHis') . '.pdf';
        file_put_contents($pdfPath, $pdfContent);
        echo "✓ PDF descargado: " . basename($pdfPath) . "\n";
        echo "  Tamaño: " . number_format(strlen($pdfContent) / 1024, 2) . " KB\n\n";
    } else {
        echo "⚠ No se pudo descargar el PDF (HTTP {$httpCode})\n\n";
    }
}

// Opción 2: Usar un PDF existente como fallback
if (!$pdfPath || !file_exists($pdfPath)) {
    echo "Buscando PDFs existentes en el directorio...\n";
    $pdfsExistentes = glob(__DIR__ . '/pdfs/*.pdf');

    if (!empty($pdfsExistentes)) {
        $pdfPath = $pdfsExistentes[0];
        echo "✓ Usando PDF existente: " . basename($pdfPath) . "\n\n";
    } else {
        // Buscar cualquier PDF en el directorio raíz
        $pdfsRaiz = glob(__DIR__ . '/*.pdf');
        if (!empty($pdfsRaiz)) {
            $pdfPath = $pdfsRaiz[0];
            echo "✓ Usando PDF de ejemplo: " . basename($pdfPath) . "\n\n";
        }
    }
}

if (!$pdfPath || !file_exists($pdfPath)) {
    echo "⚠ ADVERTENCIA: No se encontró PDF para adjuntar\n";
    echo "   El email se enviará sin adjunto\n\n";
}

echo "════════════════════════════════════════════════════════════════\n";
echo " PASO 3: ENVIAR EMAIL\n";
echo "════════════════════════════════════════════════════════════════\n\n";

// Preparar contenido del email
$asunto = "Boleta Electrónica N° {$folio} - " . $config->get('emisor.razon_social');

$mensaje = "Estimado cliente,\n\n";
$mensaje .= "Adjunto encontrará su Boleta Electrónica.\n\n";
$mensaje .= "Detalles del documento:\n";
$mensaje .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$mensaje .= "• Emisor: " . $config->get('emisor.razon_social') . "\n";
$mensaje .= "• RUT Emisor: " . $config->get('emisor.rut') . "\n";
$mensaje .= "• Tipo: Boleta Electrónica (DTE 39)\n";
$mensaje .= "• Folio: N° {$folio}\n";
$mensaje .= "• Fecha: " . date('d/m/Y') . "\n";
$mensaje .= "• Monto Total: $" . number_format($total, 0, ',', '.') . "\n";
if ($trackId) {
    $mensaje .= "• Track ID: {$trackId}\n";
}
$mensaje .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
$mensaje .= "Este documento tiene validez tributaria según normativa del SII.\n\n";
$mensaje .= "Esta es una prueba del sistema de facturación electrónica.\n\n";
$mensaje .= "Saludos cordiales,\n";
$mensaje .= $config->get('emisor.razon_social') . "\n";

// Headers del email
$headers = [];
$headers[] = 'From: ' . $config->get('emisor.razon_social') . ' <' . $config->get('email.from_email') . '>';
$headers[] = 'Reply-To: ' . $config->get('email.from_email');
$headers[] = 'X-Mailer: PHP/' . phpversion();
$headers[] = 'MIME-Version: 1.0';

// Si hay PDF, crear email multipart
if ($pdfPath && file_exists($pdfPath)) {
    echo "Preparando email con PDF adjunto...\n";

    $boundary = md5(time());

    $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';

    // Cuerpo del mensaje multipart
    $body = "--{$boundary}\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\n";
    $body .= "Content-Transfer-Encoding: 7bit\n\n";
    $body .= $mensaje . "\n\n";

    // Adjuntar PDF
    $pdfContent = file_get_contents($pdfPath);
    $pdfEncoded = chunk_split(base64_encode($pdfContent));
    $pdfFilename = basename($pdfPath);

    $body .= "--{$boundary}\n";
    $body .= "Content-Type: application/pdf; name=\"{$pdfFilename}\"\n";
    $body .= "Content-Transfer-Encoding: base64\n";
    $body .= "Content-Disposition: attachment; filename=\"{$pdfFilename}\"\n\n";
    $body .= $pdfEncoded . "\n";
    $body .= "--{$boundary}--";

    echo "   PDF: {$pdfFilename} (" . number_format(filesize($pdfPath) / 1024, 2) . " KB)\n\n";
} else {
    echo "Preparando email sin adjunto...\n\n";

    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $body = $mensaje;
}

// Enviar email
echo "Enviando email a: {$emailDestino}\n";
echo "Asunto: {$asunto}\n\n";

$enviado = mail($emailDestino, $asunto, $body, implode("\r\n", $headers));

if ($enviado) {
    echo "\n✓✓✓ EMAIL ENVIADO EXITOSAMENTE ✓✓✓\n\n";
    echo "Por favor revise su bandeja de entrada (y spam) en:\n";
    echo "  {$emailDestino}\n\n";

    // Registrar en log
    $logEntry = [
        'fecha' => date('Y-m-d H:i:s'),
        'email_destino' => $emailDestino,
        'folio' => $folio,
        'track_id' => $trackId,
        'pdf_adjunto' => $pdfPath ? basename($pdfPath) : 'Sin adjunto',
        'resultado' => 'EXITOSO'
    ];

    $logFile = __DIR__ . '/logs/emails-enviados.log';
    file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND);

    echo "Registro guardado en: logs/emails-enviados.log\n";

} else {
    echo "\n❌ ERROR AL ENVIAR EMAIL\n\n";
    echo "Posibles causas:\n";
    echo "  • El servidor no tiene configurado un servidor de correo\n";
    echo "  • Se requiere configuración SMTP\n";
    echo "  • El email está bloqueado por políticas del servidor\n\n";

    echo "Solución:\n";
    echo "  Configure las variables de entorno SMTP en su sistema:\n";
    echo "    SMTP_HOST=smtp.gmail.com\n";
    echo "    SMTP_PORT=587\n";
    echo "    SMTP_USER=su_email@gmail.com\n";
    echo "    SMTP_PASS=su_contraseña\n\n";

    // Registrar error
    $logEntry = [
        'fecha' => date('Y-m-d H:i:s'),
        'email_destino' => $emailDestino,
        'folio' => $folio,
        'resultado' => 'ERROR'
    ];

    $logFile = __DIR__ . '/logs/emails-enviados.log';
    file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND);

    exit(1);
}

echo "\n════════════════════════════════════════════════════════════════\n";
echo " PRUEBA COMPLETADA\n";
echo "════════════════════════════════════════════════════════════════\n\n";

echo "Archivos generados:\n";
echo "  • Resultado: " . basename($archivoResultado) . "\n";
if ($pdfPath) {
    echo "  • PDF: " . basename($pdfPath) . "\n";
}
echo "\n";

exit(0);
