<?php
/**
 * Enviar Email usando SMTP a través de HTTP Proxy
 */

// Argumentos
$pdf_path = $argv[1] ?? 'pdfs/boleta_1890_20251117025355.pdf';
$xml_path = $argv[2] ?? 'xmls/boleta_1890_20251117025355.xml';
$email_destinatario = $argv[3] ?? 'ale.fvaras@gmail.com';

if (!file_exists($pdf_path)) {
    die("❌ Error: PDF no encontrado: {$pdf_path}\n");
}

if (!file_exists($xml_path)) {
    die("❌ Error: XML no encontrado: {$xml_path}\n");
}

echo "═══════════════════════════════════════════════════════════════════════════\n";
echo "                   ENVÍO VÍA SMTP CON PROXY HTTP                           \n";
echo "═══════════════════════════════════════════════════════════════════════════\n\n";

// Detectar configuración del proxy desde variables de entorno
$https_proxy = getenv('https_proxy') ?: getenv('HTTPS_PROXY');
echo "🔍 Proxy detectado: " . ($https_proxy ?: 'ninguno') . "\n\n";

if (!$https_proxy) {
    die("❌ No se encontró configuración de proxy\n");
}

// Parsear URL del proxy
$proxy_parts = parse_url($https_proxy);
if (!$proxy_parts) {
    die("❌ Error parseando URL del proxy\n");
}

echo "📧 Configuración:\n";
echo "────────────────────────────────────────────────────────\n";
echo "Proxy Host: " . ($proxy_parts['host'] ?? 'unknown') . "\n";
echo "Proxy Port: " . ($proxy_parts['port'] ?? '80') . "\n";
echo "SMTP: smtp.hostinger.com:587\n";
echo "De: contacto@akibara.cl\n";
echo "Para: {$email_destinatario}\n\n";

// Extraer folio
preg_match('/boleta_(?:prueba_)?(\d+)_/', basename($pdf_path), $matches);
$folio = $matches[1] ?? '0000';

// SMTP config
$smtp_host = 'smtp.hostinger.com';
$smtp_port = 587;
$smtp_user = 'contacto@akibara.cl';
$smtp_pass = 'Gc53d0fu78@,';

// Proxy config
$proxy_host = $proxy_parts['host'];
$proxy_port = $proxy_parts['port'] ?? 3128;
$proxy_auth = '';
if (isset($proxy_parts['user'])) {
    $proxy_auth = base64_encode($proxy_parts['user'] . ':' . ($proxy_parts['pass'] ?? ''));
}

echo "🔌 Conectando al proxy...\n";

// Conectar al proxy
$proxy_socket = @fsockopen($proxy_host, $proxy_port, $errno, $errstr, 30);

if (!$proxy_socket) {
    die("❌ Error conectando al proxy: {$errstr} ({$errno})\n");
}

echo "✓ Conectado al proxy\n\n";

// Enviar CONNECT al proxy
$connect_request = "CONNECT {$smtp_host}:{$smtp_port} HTTP/1.1\r\n";
$connect_request .= "Host: {$smtp_host}:{$smtp_port}\r\n";

if ($proxy_auth) {
    $connect_request .= "Proxy-Authorization: Basic {$proxy_auth}\r\n";
}

$connect_request .= "User-Agent: PHP-SMTP-Client\r\n";
$connect_request .= "Proxy-Connection: Keep-Alive\r\n";
$connect_request .= "\r\n";

echo "📤 Enviando CONNECT al proxy...\n";
fputs($proxy_socket, $connect_request);

// Leer respuesta del proxy
$proxy_response = '';
while ($line = fgets($proxy_socket, 1024)) {
    $proxy_response .= $line;
    if (trim($line) === '') break;
}

echo "Respuesta: " . substr($proxy_response, 0, 100) . "...\n";

if (strpos($proxy_response, '200') === false) {
    fclose($proxy_socket);
    die("❌ Error en CONNECT: {$proxy_response}\n");
}

echo "✓ Túnel establecido\n\n";

// Ahora el socket está conectado directamente a SMTP
// Continuar con protocolo SMTP normal

function smtp_read($socket) {
    $response = '';
    while ($line = fgets($socket, 515)) {
        $response .= $line;
        if (substr($line, 3, 1) == ' ') break;
    }
    return $response;
}

function smtp_send($socket, $command, $show = true) {
    fputs($socket, $command . "\r\n");
    $response = smtp_read($socket);
    if ($show) {
        echo "  → " . trim($command) . "\n";
        echo "  ← " . trim($response) . "\n";
    }
    return $response;
}

try {
    echo "📨 Comunicación SMTP:\n";
    echo "────────────────────────────────────────────────────────\n";

    // Leer banner
    $response = smtp_read($proxy_socket);
    echo "Banner: " . trim($response) . "\n\n";

    // EHLO
    smtp_send($proxy_socket, "EHLO localhost");

    // STARTTLS
    echo "\n🔒 Iniciando TLS...\n";
    smtp_send($proxy_socket, "STARTTLS");

    // Habilitar TLS
    if (!stream_socket_enable_crypto($proxy_socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        throw new Exception("Error habilitando TLS");
    }
    echo "✓ TLS habilitado\n\n";

    // EHLO post-TLS
    smtp_send($proxy_socket, "EHLO localhost", false);

    // AUTH LOGIN
    echo "🔑 Autenticando...\n";
    smtp_send($proxy_socket, "AUTH LOGIN", false);
    smtp_send($proxy_socket, base64_encode($smtp_user), false);
    $auth_response = smtp_send($proxy_socket, base64_encode($smtp_pass), false);

    if (strpos($auth_response, '235') !== false) {
        echo "✓ Autenticación exitosa\n\n";
    } else {
        throw new Exception("Error de autenticación: {$auth_response}");
    }

    // Preparar mensaje
    echo "📝 Preparando mensaje...\n";

    $boundary = md5(time());
    $asunto = "Boleta Electrónica N° {$folio} - AKIBARA SPA";

    $headers = "From: AKIBARA SPA <contacto@akibara.cl>\r\n";
    $headers .= "Reply-To: contacto@akibara.cl\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

    // Cuerpo HTML simple
    $html_body = "<html><body>";
    $html_body .= "<h1>Boleta Electrónica N° {$folio}</h1>";
    $html_body .= "<p>Estimado/a Cliente,</p>";
    $html_body .= "<p>Adjunto encontrará su Boleta Electrónica emitida por AKIBARA SPA.</p>";
    $html_body .= "<p><strong>Folio:</strong> {$folio}<br>";
    $html_body .= "<strong>Fecha:</strong> " . date('d/m/Y') . "<br>";
    $html_body .= "<strong>Emisor:</strong> AKIBARA SPA (RUT: 78274225-6)</p>";
    $html_body .= "<p>Saludos cordiales,<br>AKIBARA SPA</p>";
    $html_body .= "</body></html>";

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

    echo "✓ Mensaje preparado (" . number_format(strlen($message) / 1024, 2) . " KB)\n\n";

    // Enviar email
    echo "📬 Enviando email...\n";
    smtp_send($proxy_socket, "MAIL FROM: <contacto@akibara.cl>", false);
    smtp_send($proxy_socket, "RCPT TO: <{$email_destinatario}>", false);
    smtp_send($proxy_socket, "DATA", false);

    // Enviar headers y mensaje
    fputs($proxy_socket, "Subject: {$asunto}\r\n");
    fputs($proxy_socket, $headers);
    fputs($proxy_socket, "\r\n");
    fputs($proxy_socket, $message);
    fputs($proxy_socket, "\r\n.\r\n");

    $response = smtp_read($proxy_socket);

    if (strpos($response, '250') !== false) {
        echo "\n";
        echo "═══════════════════════════════════════════════════════════════════════════\n";
        echo "✅ EMAIL ENVIADO EXITOSAMENTE\n";
        echo "═══════════════════════════════════════════════════════════════════════════\n\n";
        echo "Para:     {$email_destinatario}\n";
        echo "Asunto:   {$asunto}\n";
        echo "Adjuntos: " . basename($pdf_path) . ", " . basename($xml_path) . "\n";
        echo "Tamaño:   " . number_format((filesize($pdf_path) + filesize($xml_path)) / 1024, 2) . " KB\n\n";
        echo "🎉 El email debería llegar en unos segundos.\n";
        echo "📥 Revisa tu bandeja de entrada: {$email_destinatario}\n";
        echo "⚠️  Si no aparece, revisa la carpeta de Spam\n\n";
    } else {
        throw new Exception("Error enviando: {$response}");
    }

    // QUIT
    smtp_send($proxy_socket, "QUIT", false);

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
} finally {
    if ($proxy_socket) {
        fclose($proxy_socket);
    }
}

echo "═══════════════════════════════════════════════════════════════════════════\n";
echo "                            PROCESO COMPLETADO                              \n";
echo "═══════════════════════════════════════════════════════════════════════════\n";
