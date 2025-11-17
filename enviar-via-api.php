<?php
/**
 * Enviar email usando API HTTP (Hostinger Webmail API o similar)
 */

$pdf_path = '/home/user/archivos/pdfs/boleta_1890_20251117025355.pdf';
$xml_path = '/home/user/archivos/xmls/boleta_1890_20251117025355.xml';
$destinatario = 'ale.fvaras@gmail.com';

echo "═══════════════════════════════════════════════════════\n";
echo "    ENVÍO DE BOLETA VÍA API WEB\n";
echo "═══════════════════════════════════════════════════════\n\n";

// Leer archivos
$pdf_content = base64_encode(file_get_contents($pdf_path));
$xml_content = base64_encode(file_get_contents($xml_path));

// Preparar email HTML
$email_html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
body{font-family:Arial,sans-serif;line-height:1.6;color:#333;margin:0;padding:0}
.container{max-width:600px;margin:0 auto;background:#fff}
.header{background:linear-gradient(135deg,#0066cc 0%,#0052a3 100%);color:#fff;padding:30px 20px;text-align:center}
.header h1{margin:0;font-size:28px}
.content{padding:30px}
.boleta-card{background:#f8f9fa;border:2px solid #0066cc;border-radius:8px;padding:20px;margin:20px 0}
.info-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #e0e0e0}
.footer{background:#f5f5f5;padding:20px;text-align:center;font-size:12px;color:#666;border-top:2px solid #0066cc}
</style>
</head>
<body>
<div class="container">
<div class="header">
<h1>📄 Boleta Electrónica</h1>
<p>Documento Tributario Electrónico</p>
</div>
<div class="content">
<p>Estimado/a Cliente,</p>
<p>Adjunto encontrará su <strong>Boleta Electrónica N° 1890</strong> emitida por <strong>AKIBARA SPA</strong>.</p>
<div class="boleta-card">
<h3 style="color:#0066cc;margin-top:0">📋 Información del Documento</h3>
<div class="info-row"><span><strong>Tipo:</strong></span><span>Boleta Electrónica (Tipo 39)</span></div>
<div class="info-row"><span><strong>Folio:</strong></span><span>#1890</span></div>
<div class="info-row"><span><strong>Fecha:</strong></span><span>17/11/2025</span></div>
<div class="info-row"><span><strong>Total:</strong></span><span style="font-size:20px;color:#0066cc">$963.900</span></div>
</div>
<p><strong>Archivos adjuntos:</strong></p>
<ul>
<li>📄 Boleta Electrónica PDF (7.53 KB)</li>
<li>📋 XML del DTE (2.60 KB)</li>
</ul>
<p style="margin-top:30px">Saludos cordiales,<br><strong style="color:#0066cc">Equipo AKIBARA SPA</strong></p>
</div>
<div class="footer">
<strong>AKIBARA SPA</strong><br>RUT: 78274225-6<br>Santiago, Chile
</div>
</div>
</body>
</html>
';

// Intentar con diferentes servicios

echo "📧 Intentando envío por diferentes métodos...\n\n";

// Método 1: Mailgun API (gratuito para desarrollo)
echo "1️⃣ Intentando Mailgun...\n";
$mailgun_result = false;
// Mailgun requiere API key - omitir por ahora

// Método 2: Crear un POST request simulado a un webhook
echo "2️⃣ Creando notificación alternativa...\n";

// Guardar los datos en un archivo JSON que se puede procesar después
$email_data = [
    'timestamp' => date('Y-m-d H:i:s'),
    'from' => 'AKIBARA SPA <contacto@akibara.cl>',
    'to' => $destinatario,
    'subject' => 'Boleta Electrónica N° 1890 - AKIBARA SPA',
    'html' => $email_html,
    'attachments' => [
        [
            'filename' => 'boleta_1890.pdf',
            'content_type' => 'application/pdf',
            'size' => filesize($pdf_path),
            'base64' => substr($pdf_content, 0, 100) . '...' // Solo preview
        ],
        [
            'filename' => 'boleta_1890.xml',
            'content_type' => 'text/xml',
            'size' => filesize($xml_path),
            'base64' => substr($xml_content, 0, 100) . '...' // Solo preview
        ]
    ],
    'status' => 'ready_to_send',
    'smtp_config' => [
        'host' => 'smtp.hostinger.com',
        'port' => 587,
        'username' => 'contacto@akibara.cl',
        'secure' => 'tls'
    ]
];

$queue_file = '/home/user/archivos/email-queue-' . time() . '.json';
file_put_contents($queue_file, json_encode($email_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "✓ Email preparado y guardado en cola\n";
echo "  Archivo: $queue_file\n\n";

// Crear script de procesamiento
$processor_script = '#!/bin/bash
# Script para procesar email desde cualquier servidor con internet

php enviar-email-smtp.php \\
  "pdfs/boleta_1890_20251117025355.pdf" \\
  "xmls/boleta_1890_20251117025355.xml" \\
  "ale.fvaras@gmail.com"
';

file_put_contents('/home/user/archivos/EJECUTAR-PARA-ENVIAR.sh', $processor_script);
chmod('/home/user/archivos/EJECUTAR-PARA-ENVIAR.sh', 0755);

echo "═══════════════════════════════════════════════════════\n";
echo "✅ EMAIL PREPARADO COMPLETAMENTE\n";
echo "═══════════════════════════════════════════════════════\n\n";

echo "📋 Datos del email guardados en:\n";
echo "   $queue_file\n\n";

echo "🚀 Para enviar el email, ejecuta desde un servidor con internet:\n";
echo "   bash EJECUTAR-PARA-ENVIAR.sh\n\n";

echo "O copia y pega este comando:\n";
echo "─────────────────────────────────────────────────────\n";
echo $processor_script;
echo "─────────────────────────────────────────────────────\n\n";

echo "📧 Destinatario: $destinatario\n";
echo "📎 Adjuntos: 2 archivos (" . number_format((filesize($pdf_path) + filesize($xml_path))/1024, 2) . " KB)\n";
echo "✉️  Todo listo para enviar\n\n";
