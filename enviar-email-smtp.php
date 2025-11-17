<?php
/**
 * Enviar Email usando SMTP directo (sin dependencias)
 * Envía emails usando conexión directa por sockets a SMTP
 */

// Argumentos
$pdf_path = $argv[1] ?? null;
$xml_path = $argv[2] ?? null;
$email_destinatario = $argv[3] ?? 'ale.fvaras@gmail.com';

if (!$pdf_path || !file_exists($pdf_path)) {
    die("❌ Error: PDF no encontrado\n");
}

if (!$xml_path || !file_exists($xml_path)) {
    die("❌ Error: XML no encontrado\n");
}

echo "═══════════════════════════════════════════════════════════════════════════\n";
echo "                   ENVÍO DE BOLETA POR SMTP DIRECTO                        \n";
echo "═══════════════════════════════════════════════════════════════════════════\n\n";

// ========================================
// CONFIGURACIÓN SMTP
// ========================================

$smtp = [
    'host' => 'smtp.hostinger.com',
    'port' => 587,
    'username' => 'contacto@akibara.cl',
    'password' => 'Gc53d0fu78@,',
    'from_email' => 'contacto@akibara.cl',
    'from_name' => 'AKIBARA SPA',
    'timeout' => 30,
];

echo "📧 Configuración SMTP\n";
echo "────────────────────────────────────────────────────────\n";
echo "Servidor: {$smtp['host']}:{$smtp['port']}\n";
echo "Usuario: {$smtp['username']}\n";
echo "Para: {$email_destinatario}\n\n";

// ========================================
// PREPARAR MENSAJE
// ========================================

// Extraer folio
preg_match('/boleta_(?:prueba_)?(\d+)_/', basename($pdf_path), $matches);
$folio = $matches[1] ?? '0000';

$asunto = "Boleta Electrónica N° {$folio} - AKIBARA SPA";

// Boundary para multipart
$boundary = md5(time());

// Headers
$headers = "From: {$smtp['from_name']} <{$smtp['from_email']}>\r\n";
$headers .= "Reply-To: {$smtp['from_email']}\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

// Cuerpo HTML
$html_body = "
<html>
<head>
<meta charset='UTF-8'>
<style>
body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
.container { max-width: 600px; margin: 0 auto; background: white; }
.header { background: linear-gradient(135deg, #0066cc 0%, #0052a3 100%); color: white; padding: 30px 20px; text-align: center; }
.header h1 { margin: 0; font-size: 28px; }
.content { padding: 30px; }
.boleta-card { background: #f8f9fa; border: 2px solid #0066cc; border-radius: 8px; padding: 20px; margin: 20px 0; }
.info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e0e0e0; }
.footer { background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666; border-top: 2px solid #0066cc; }
</style>
</head>
<body>
<div class='container'>
    <div class='header'>
        <h1>📄 Boleta Electrónica</h1>
        <p>Documento Tributario Electrónico - SII Chile</p>
    </div>
    <div class='content'>
        <p>Estimado/a Cliente,</p>
        <p>Adjunto encontrará su <strong>Boleta Electrónica N° {$folio}</strong> emitida por <strong>AKIBARA SPA</strong>.</p>

        <div class='boleta-card'>
            <h3 style='color: #0066cc; margin-top: 0;'>📋 Información del Documento</h3>
            <div class='info-row'>
                <span><strong>Tipo:</strong></span>
                <span>Boleta Electrónica (DTE Tipo 39)</span>
            </div>
            <div class='info-row'>
                <span><strong>Folio:</strong></span>
                <span>#{$folio}</span>
            </div>
            <div class='info-row'>
                <span><strong>Fecha:</strong></span>
                <span>" . date('d/m/Y') . "</span>
            </div>
            <div class='info-row' style='border-bottom: none;'>
                <span><strong>RUT Emisor:</strong></span>
                <span>78274225-6</span>
            </div>
        </div>

        <p><strong>Archivos adjuntos:</strong></p>
        <ul>
            <li>📄 Boleta Electrónica en formato PDF</li>
            <li>📋 XML del Documento Tributario Electrónico</li>
        </ul>

        <p style='margin-top: 30px;'>Saludos cordiales,<br>
        <strong style='color: #0066cc;'>Equipo AKIBARA SPA</strong></p>
    </div>
    <div class='footer'>
        <strong>AKIBARA SPA</strong><br>
        RUT: 78274225-6<br>
        Santiago, Chile
        <p style='margin-top: 15px;'>Este correo fue generado automáticamente.</p>
    </div>
</div>
</body>
</html>
";

// Leer archivos
$pdf_content = base64_encode(file_get_contents($pdf_path));
$xml_content = base64_encode(file_get_contents($xml_path));

// Construir mensaje multipart
$message = "--{$boundary}\r\n";
$message .= "Content-Type: text/html; charset=UTF-8\r\n";
$message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
$message .= $html_body . "\r\n\r\n";

// Adjuntar PDF
$message .= "--{$boundary}\r\n";
$message .= "Content-Type: application/pdf; name=\"" . basename($pdf_path) . "\"\r\n";
$message .= "Content-Transfer-Encoding: base64\r\n";
$message .= "Content-Disposition: attachment; filename=\"" . basename($pdf_path) . "\"\r\n\r\n";
$message .= chunk_split($pdf_content) . "\r\n";

// Adjuntar XML
$message .= "--{$boundary}\r\n";
$message .= "Content-Type: text/xml; name=\"" . basename($xml_path) . "\"\r\n";
$message .= "Content-Transfer-Encoding: base64\r\n";
$message .= "Content-Disposition: attachment; filename=\"" . basename($xml_path) . "\"\r\n\r\n";
$message .= chunk_split($xml_content) . "\r\n";

$message .= "--{$boundary}--\r\n";

// ========================================
// ENVIAR VÍA SMTP
// ========================================

echo "🔌 Conectando a servidor SMTP...\n";

// Conectar al servidor SMTP
$socket = @fsockopen($smtp['host'], $smtp['port'], $errno, $errstr, $smtp['timeout']);

if (!$socket) {
    die("❌ Error de conexión: {$errstr} ({$errno})\n");
}

// Configurar timeout
stream_set_timeout($socket, $smtp['timeout']);

// Función para leer respuesta
function smtp_read($socket) {
    $response = '';
    while ($line = fgets($socket, 515)) {
        $response .= $line;
        if (substr($line, 3, 1) == ' ') break;
    }
    return $response;
}

// Función para enviar comando
function smtp_send($socket, $command, $show_response = true) {
    fputs($socket, $command . "\r\n");
    $response = smtp_read($socket);
    if ($show_response) {
        echo "    → " . trim($response) . "\n";
    }
    return $response;
}

try {
    // Leer banner
    $response = smtp_read($socket);
    echo "✓ Conectado: " . trim($response) . "\n\n";

    // EHLO
    echo "📨 Enviando comandos SMTP...\n";
    smtp_send($socket, "EHLO {$smtp['host']}");

    // STARTTLS
    smtp_send($socket, "STARTTLS");

    // Habilitar encriptación
    if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        throw new Exception("Error habilitando TLS");
    }

    // EHLO después de TLS
    smtp_send($socket, "EHLO {$smtp['host']}", false);

    // AUTH LOGIN
    smtp_send($socket, "AUTH LOGIN", false);
    smtp_send($socket, base64_encode($smtp['username']), false);
    smtp_send($socket, base64_encode($smtp['password']), false);

    echo "✓ Autenticación exitosa\n\n";

    // MAIL FROM
    echo "📬 Enviando email...\n";
    smtp_send($socket, "MAIL FROM: <{$smtp['from_email']}>", false);

    // RCPT TO
    smtp_send($socket, "RCPT TO: <{$email_destinatario}>", false);

    // DATA
    smtp_send($socket, "DATA", false);

    // Enviar headers y mensaje
    fputs($socket, "Subject: {$asunto}\r\n");
    fputs($socket, $headers);
    fputs($socket, "\r\n");
    fputs($socket, $message);
    fputs($socket, "\r\n.\r\n");

    $response = smtp_read($socket);

    if (strpos($response, '250') !== false) {
        echo "\n✅ EMAIL ENVIADO EXITOSAMENTE\n\n";
        echo "Para: {$email_destinatario}\n";
        echo "Asunto: {$asunto}\n";
        echo "Adjuntos: " . basename($pdf_path) . ", " . basename($xml_path) . "\n";
        echo "Tamaño total: " . number_format((filesize($pdf_path) + filesize($xml_path)) / 1024, 2) . " KB\n";
    } else {
        throw new Exception("Error en DATA: " . $response);
    }

    // QUIT
    smtp_send($socket, "QUIT", false);

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
} finally {
    if ($socket) {
        fclose($socket);
    }
}

echo "\n═══════════════════════════════════════════════════════════════════════════\n";
echo "                            PROCESO COMPLETADO                              \n";
echo "═══════════════════════════════════════════════════════════════════════════\n";
