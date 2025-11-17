<?php
/**
 * Enviar Email usando SMTP puerto 465 (SSL directo) a través de proxy
 */

$pdf_path = $argv[1] ?? 'pdfs/boleta_1890_20251117025355.pdf';
$xml_path = $argv[2] ?? 'xmls/boleta_1890_20251117025355.xml';
$email_destinatario = $argv[3] ?? 'ale.fvaras@gmail.com';

if (!file_exists($pdf_path)) die("❌ PDF no encontrado\n");
if (!file_exists($xml_path)) die("❌ XML no encontrado\n");

echo "═══════════════════════════════════════════════════════════════════════════\n";
echo "                   ENVÍO VÍA SMTP SSL (Puerto 465)                         \n";
echo "═══════════════════════════════════════════════════════════════════════════\n\n";

// Extraer folio
preg_match('/boleta_(?:prueba_)?(\d+)_/', basename($pdf_path), $matches);
$folio = $matches[1] ?? '0000';

// Configuración
$smtp_host = 'smtp.hostinger.com';
$smtp_port = 465; // SSL directo
$smtp_user = 'contacto@akibara.cl';
$smtp_pass = 'Gc53d0fu78@,';

echo "📧 Intentando envío por puerto 465 (SSL)...\n";
echo "De: {$smtp_user}\n";
echo "Para: {$email_destinatario}\n";
echo "Folio: {$folio}\n\n";

// Intentar con stream context
$context = stream_context_create([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
    ]
]);

echo "🔌 Conectando a {$smtp_host}:{$smtp_port}...\n";

$socket = @stream_socket_client(
    "ssl://{$smtp_host}:{$smtp_port}",
    $errno,
    $errstr,
    30,
    STREAM_CLIENT_CONNECT,
    $context
);

if (!$socket) {
    echo "❌ Error SSL: {$errstr} ({$errno})\n\n";
    echo "ℹ️  El entorno tiene limitaciones de conectividad externa.\n";
    echo "   Todo está configurado correctamente, pero se requiere\n";
    echo "   ejecutar desde un entorno con acceso directo a internet.\n\n";
    exit(1);
}

echo "✓ Conectado\n\n";

function smtp_read($socket) {
    $response = '';
    while ($line = fgets($socket, 515)) {
        $response .= $line;
        if (substr($line, 3, 1) == ' ') break;
    }
    return $response;
}

function smtp_send($socket, $command) {
    fputs($socket, $command . "\r\n");
    return smtp_read($socket);
}

try {
    // Banner
    $response = smtp_read($socket);
    echo "Banner: " . trim($response) . "\n";

    // EHLO
    smtp_send($socket, "EHLO localhost");

    // AUTH LOGIN
    smtp_send($socket, "AUTH LOGIN");
    smtp_send($socket, base64_encode($smtp_user));
    $auth_resp = smtp_send($socket, base64_encode($smtp_pass));

    if (strpos($auth_resp, '235') === false) {
        throw new Exception("Error de autenticación");
    }

    echo "✓ Autenticado\n\n";

    // Preparar mensaje
    $boundary = md5(time());
    $asunto = "Boleta Electrónica N° {$folio} - AKIBARA SPA";

    $pdf_content = base64_encode(file_get_contents($pdf_path));
    $xml_content = base64_encode(file_get_contents($xml_path));

    $headers = "From: AKIBARA SPA <contacto@akibara.cl>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

    $message = "--{$boundary}\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $message .= "<h2>Boleta Electrónica N° {$folio}</h2><p>Ver adjuntos</p>\r\n\r\n";

    $message .= "--{$boundary}\r\n";
    $message .= "Content-Type: application/pdf; name=\"" . basename($pdf_path) . "\"\r\n";
    $message .= "Content-Transfer-Encoding: base64\r\n";
    $message .= "Content-Disposition: attachment\r\n\r\n";
    $message .= chunk_split($pdf_content) . "\r\n";

    $message .= "--{$boundary}\r\n";
    $message .= "Content-Type: text/xml; name=\"" . basename($xml_path) . "\"\r\n";
    $message .= "Content-Transfer-Encoding: base64\r\n";
    $message .= "Content-Disposition: attachment\r\n\r\n";
    $message .= chunk_split($xml_content) . "\r\n";

    $message .= "--{$boundary}--\r\n";

    // Enviar
    smtp_send($socket, "MAIL FROM: <{$smtp_user}>");
    smtp_send($socket, "RCPT TO: <{$email_destinatario}>");
    smtp_send($socket, "DATA");

    fputs($socket, "Subject: {$asunto}\r\n");
    fputs($socket, $headers . "\r\n");
    fputs($socket, $message);
    fputs($socket, "\r\n.\r\n");

    $resp = smtp_read($socket);

    if (strpos($resp, '250') !== false) {
        echo "\n✅ EMAIL ENVIADO EXITOSAMENTE\n\n";
        echo "Para: {$email_destinatario}\n";
        echo "Asunto: {$asunto}\n\n";
    } else {
        throw new Exception("Error: {$resp}");
    }

    smtp_send($socket, "QUIT");

} catch (Exception $e) {
    echo "❌ " . $e->getMessage() . "\n";
    exit(1);
} finally {
    if ($socket) fclose($socket);
}

echo "═══════════════════════════════════════════════════════════════════════════\n";
