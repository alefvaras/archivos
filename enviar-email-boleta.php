<?php
/**
 * Enviar Email con Boleta Electrónica
 *
 * Uso: php enviar-email-boleta.php <pdf_path> <xml_path> <email_destinatario>
 */

// Argumentos
$pdf_path = $argv[1] ?? null;
$xml_path = $argv[2] ?? null;
$email_destinatario = $argv[3] ?? 'ale.fvaras@gmail.com';

if (!$pdf_path || !file_exists($pdf_path)) {
    die("❌ Error: PDF no encontrado\nUso: php enviar-email-boleta.php <pdf_path> <xml_path> <email>\n");
}

if (!$xml_path || !file_exists($xml_path)) {
    die("❌ Error: XML no encontrado\nUso: php enviar-email-boleta.php <pdf_path> <xml_path> <email>\n");
}

echo "═══════════════════════════════════════════════════════════════════════════\n";
echo "                   ENVÍO DE BOLETA ELECTRÓNICA POR EMAIL                   \n";
echo "═══════════════════════════════════════════════════════════════════════════\n\n";

// ========================================
// CONFIGURACIÓN SMTP (Hostinger)
// ========================================

// Basado en los registros MX proporcionados:
// mx1.hostinger.com (prioridad 5)
// mx2.hostinger.com (prioridad 10)

$smtp_config = [
    'host' => 'smtp.hostinger.com',  // SMTP de Hostinger
    'port' => 587,                    // Puerto TLS
    'username' => 'contacto@akibara.cl', // Email de envío
    'password' => '',                  // DEBE configurarse (obtener de Hostinger)
    'from_email' => 'contacto@akibara.cl',
    'from_name' => 'AKIBARA SPA - Boletas Electrónicas',
    'encryption' => 'tls',            // TLS para puerto 587
];

echo "📧 Configuración SMTP\n";
echo "────────────────────────────────────────────────────────\n";
echo "Host: {$smtp_config['host']}\n";
echo "Puerto: {$smtp_config['port']}\n";
echo "Usuario: {$smtp_config['username']}\n";
echo "Cifrado: {$smtp_config['encryption']}\n";
echo "Desde: {$smtp_config['from_name']} <{$smtp_config['from_email']}>\n\n";

// ========================================
// VERIFICAR CONTRASEÑA SMTP
// ========================================

if (empty($smtp_config['password'])) {
    echo "⚠️  ATENCIÓN: No hay contraseña SMTP configurada\n\n";
    echo "Para enviar emails reales, necesitas:\n";
    echo "1. Crear una cuenta de email en Hostinger: boletas@akibara.cl\n";
    echo "2. Obtener la contraseña SMTP\n";
    echo "3. Configurarla en la línea 22 de este archivo\n\n";
    echo "Por ahora, se generará un email de prueba (sin envío real).\n\n";

    $envio_real = false;
} else {
    $envio_real = true;
}

// ========================================
// LEER DATOS DE LA BOLETA
// ========================================

echo "📄 Archivos a Enviar\n";
echo "────────────────────────────────────────────────────────\n";
echo "PDF: " . basename($pdf_path) . " (" . number_format(filesize($pdf_path) / 1024, 2) . " KB)\n";
echo "XML: " . basename($xml_path) . " (" . number_format(filesize($xml_path) / 1024, 2) . " KB)\n";
echo "Para: {$email_destinatario}\n\n";

// Extraer folio del nombre del archivo PDF
preg_match('/boleta_(?:prueba_)?(\d+)_/', basename($pdf_path), $matches);
$folio = $matches[1] ?? '0000';

// ========================================
// PREPARAR EMAIL
// ========================================

$asunto = "Boleta Electrónica N° {$folio} - AKIBARA SPA";

$mensaje_html = "
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #0066cc 0%, #0052a3 100%); color: white; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 28px; font-weight: 300; }
        .header p { margin: 5px 0 0 0; font-size: 14px; opacity: 0.9; }
        .content { padding: 30px 20px; background: white; }
        .boleta-card { background: #f8f9fa; border: 2px solid #0066cc; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .boleta-card h3 { margin: 0 0 15px 0; color: #0066cc; font-size: 18px; }
        .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e0e0e0; }
        .info-row:last-child { border-bottom: none; }
        .info-label { font-weight: bold; color: #555; }
        .info-value { color: #333; }
        .attachments { background: #e8f4fd; padding: 15px; border-radius: 8px; margin: 20px 0; }
        .attachments h4 { margin: 0 0 10px 0; color: #0066cc; }
        .attachment-item { padding: 8px 0; }
        .attachment-item:before { content: '📎 '; }
        .note { background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin: 20px 0; font-size: 14px; }
        .footer { background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666; border-top: 2px solid #0066cc; }
        .footer strong { color: #333; display: block; margin-bottom: 5px; }
        .btn { display: inline-block; padding: 12px 24px; background: #0066cc; color: white; text-decoration: none; border-radius: 4px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>📄 Boleta Electrónica</h1>
            <p>Documento Tributario Electrónico - SII Chile</p>
        </div>

        <div class='content'>
            <p style='font-size: 16px;'>Estimado/a Cliente,</p>

            <p>Adjunto encontrará su <strong>Boleta Electrónica</strong> emitida por <strong>AKIBARA SPA</strong>.</p>

            <div class='boleta-card'>
                <h3>📋 Información del Documento</h3>
                <div class='info-row'>
                    <span class='info-label'>Tipo de Documento:</span>
                    <span class='info-value'>Boleta Electrónica (DTE Tipo 39)</span>
                </div>
                <div class='info-row'>
                    <span class='info-label'>Número de Folio:</span>
                    <span class='info-value'>#{$folio}</span>
                </div>
                <div class='info-row'>
                    <span class='info-label'>Fecha de Emisión:</span>
                    <span class='info-value'>" . date('d/m/Y H:i:s') . "</span>
                </div>
                <div class='info-row'>
                    <span class='info-label'>RUT Emisor:</span>
                    <span class='info-value'>78274225-6</span>
                </div>
                <div class='info-row'>
                    <span class='info-label'>Razón Social:</span>
                    <span class='info-value'>AKIBARA SPA</span>
                </div>
            </div>

            <div class='attachments'>
                <h4>📎 Archivos Adjuntos</h4>
                <div class='attachment-item'><strong>Boleta Electrónica en PDF</strong> - Para imprimir o guardar</div>
                <div class='attachment-item'><strong>Archivo XML del DTE</strong> - Respaldo electrónico con validez tributaria</div>
            </div>

            <div class='note'>
                <strong>ℹ️ Nota Importante:</strong><br>
                Este documento ha sido generado electrónicamente de acuerdo a la normativa del Servicio de Impuestos Internos (SII) de Chile y tiene plena validez tributaria.
            </div>

            <p style='margin-top: 30px;'>Si tiene alguna consulta sobre este documento, por favor no dude en contactarnos.</p>

            <p style='margin-top: 20px; color: #666; font-size: 14px;'>
                Saludos cordiales,<br>
                <strong style='color: #0066cc;'>Equipo AKIBARA SPA</strong>
            </p>
        </div>

        <div class='footer'>
            <strong>AKIBARA SPA</strong>
            RUT: 78274225-6<br>
            Giro: Servicios de Desarrollo de Software<br>
            Santiago, Chile
            <p style='margin-top: 15px; font-size: 11px;'>
                Este correo fue generado automáticamente por nuestro sistema de facturación electrónica.<br>
                Por favor no responder a este email.
            </p>
        </div>
    </div>
</body>
</html>
";

$mensaje_texto = "
BOLETA ELECTRÓNICA N° {$folio}
AKIBARA SPA
RUT: 78274225-6

Estimado/a Cliente,

Adjunto encontrará su Boleta Electrónica emitida por AKIBARA SPA.

INFORMACIÓN DEL DOCUMENTO:
- Tipo: Boleta Electrónica (DTE Tipo 39)
- Folio: {$folio}
- Fecha: " . date('d/m/Y H:i:s') . "
- RUT Emisor: 78274225-6
- Razón Social: AKIBARA SPA

ARCHIVOS ADJUNTOS:
- Boleta Electrónica en PDF
- Archivo XML del DTE (respaldo con validez tributaria)

Este documento ha sido generado electrónicamente de acuerdo a la normativa del SII de Chile.

Saludos cordiales,
AKIBARA SPA
Santiago, Chile

---
Este es un mensaje automático. Por favor no responder.
";

// ========================================
// ENVIAR EMAIL
// ========================================

echo "📨 Preparando envío...\n";
echo "────────────────────────────────────────────────────────\n";

if (!$envio_real) {
    // MODO SIMULACIÓN - Guardar email en archivo HTML
    $preview_path = __DIR__ . '/email_enviado_' . date('YmdHis') . '.html';
    file_put_contents($preview_path, $mensaje_html);

    echo "⚠️  MODO SIMULACIÓN (sin contraseña SMTP)\n\n";
    echo "✓ Email guardado en: {$preview_path}\n";
    echo "✓ Abrir en navegador para ver el resultado\n\n";

    echo "📋 Detalles del email:\n";
    echo "   Para: {$email_destinatario}\n";
    echo "   Asunto: {$asunto}\n";
    echo "   Adjuntos: " . basename($pdf_path) . ", " . basename($xml_path) . "\n\n";

} else {
    // ENVÍO REAL usando mail() de PHP
    // Nota: En producción se recomienda usar PHPMailer o similar

    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = "From: {$smtp_config['from_name']} <{$smtp_config['from_email']}>";
    $headers[] = "Reply-To: {$smtp_config['from_email']}";
    $headers[] = 'X-Mailer: PHP/' . phpversion();

    // Leer archivos para adjuntar
    $pdf_content = file_get_contents($pdf_path);
    $xml_content = file_get_contents($xml_path);

    // Crear boundary
    $boundary = md5(time());

    // Headers para multipart
    $headers_multipart = [];
    $headers_multipart[] = "From: {$smtp_config['from_name']} <{$smtp_config['from_email']}>";
    $headers_multipart[] = 'MIME-Version: 1.0';
    $headers_multipart[] = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";

    // Mensaje multipart
    $body = "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= $mensaje_html . "\r\n\r\n";

    // Adjuntar PDF
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: application/pdf; name=\"" . basename($pdf_path) . "\"\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n";
    $body .= "Content-Disposition: attachment; filename=\"" . basename($pdf_path) . "\"\r\n\r\n";
    $body .= chunk_split(base64_encode($pdf_content)) . "\r\n";

    // Adjuntar XML
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/xml; name=\"" . basename($xml_path) . "\"\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n";
    $body .= "Content-Disposition: attachment; filename=\"" . basename($xml_path) . "\"\r\n\r\n";
    $body .= chunk_split(base64_encode($xml_content)) . "\r\n";

    $body .= "--{$boundary}--";

    echo "📤 Enviando email...\n\n";

    $enviado = mail(
        $email_destinatario,
        $asunto,
        $body,
        implode("\r\n", $headers_multipart)
    );

    if ($enviado) {
        echo "✅ EMAIL ENVIADO EXITOSAMENTE\n\n";
        echo "Para: {$email_destinatario}\n";
        echo "Asunto: {$asunto}\n";
        echo "Adjuntos: 2 archivos (" . number_format((filesize($pdf_path) + filesize($xml_path)) / 1024, 2) . " KB)\n\n";
    } else {
        echo "❌ ERROR AL ENVIAR EMAIL\n\n";
        echo "Revisa la configuración SMTP y los logs del servidor.\n\n";
    }
}

echo "═══════════════════════════════════════════════════════════════════════════\n";
echo "                            PROCESO COMPLETADO                            \n";
echo "═══════════════════════════════════════════════════════════════════════════\n";
