#!/usr/bin/env python3
"""
Enviar Email con Boleta Electrónica usando Python
"""

import os
import sys
import smtplib
import base64
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from email.mime.base import MIMEBase
from email import encoders
from urllib.parse import urlparse
import socket
import re

# Argumentos
pdf_path = sys.argv[1] if len(sys.argv) > 1 else 'pdfs/boleta_1890_20251117025355.pdf'
xml_path = sys.argv[2] if len(sys.argv) > 2 else 'xmls/boleta_1890_20251117025355.xml'
email_destino = sys.argv[3] if len(sys.argv) > 3 else 'ale.fvaras@gmail.com'

if not os.path.exists(pdf_path):
    print(f"❌ PDF no encontrado: {pdf_path}")
    sys.exit(1)

if not os.path.exists(xml_path):
    print(f"❌ XML no encontrado: {xml_path}")
    sys.exit(1)

print("═" * 79)
print("                   ENVÍO DE BOLETA POR EMAIL (Python)                     ")
print("═" * 79)
print()

# Extraer folio del nombre del archivo
match = re.search(r'boleta_(?:prueba_)?(\d+)_', os.path.basename(pdf_path))
folio = match.group(1) if match else '0000'

# Configuración SMTP
smtp_host = 'smtp.hostinger.com'
smtp_port = 587
smtp_user = 'contacto@akibara.cl'
smtp_pass = 'Gc53d0fu78@,'

print(f"📧 Configuración:")
print(f"─" * 60)
print(f"Servidor: {smtp_host}:{smtp_port}")
print(f"De: {smtp_user}")
print(f"Para: {email_destino}")
print(f"Folio: {folio}")
print()

# Preparar mensaje
msg = MIMEMultipart()
msg['From'] = f'AKIBARA SPA <{smtp_user}>'
msg['To'] = email_destino
msg['Subject'] = f'Boleta Electrónica N° {folio} - AKIBARA SPA'

# Cuerpo HTML
html_body = f"""
<html>
<head>
<style>
body {{ font-family: Arial, sans-serif; line-height: 1.6; color: #333; }}
.header {{ background: linear-gradient(135deg, #0066cc 0%, #0052a3 100%); color: white; padding: 30px; text-align: center; }}
.content {{ padding: 30px; }}
.boleta-card {{ background: #f8f9fa; border: 2px solid #0066cc; border-radius: 8px; padding: 20px; margin: 20px 0; }}
.footer {{ background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666; }}
</style>
</head>
<body>
<div class="header">
<h1>📄 Boleta Electrónica</h1>
<p>Documento Tributario Electrónico - SII Chile</p>
</div>
<div class="content">
<p>Estimado/a Cliente,</p>
<p>Adjunto encontrará su <strong>Boleta Electrónica N° {folio}</strong> emitida por <strong>AKIBARA SPA</strong>.</p>
<div class="boleta-card">
<h3 style="color: #0066cc;">📋 Información del Documento</h3>
<p><strong>Tipo:</strong> Boleta Electrónica (DTE Tipo 39)<br>
<strong>Folio:</strong> #{folio}<br>
<strong>RUT Emisor:</strong> 78274225-6<br>
<strong>Razón Social:</strong> AKIBARA SPA</p>
</div>
<p><strong>Archivos adjuntos:</strong></p>
<ul>
<li>📄 Boleta Electrónica en PDF</li>
<li>📋 XML del Documento Tributario Electrónico</li>
</ul>
<p>Saludos cordiales,<br><strong style="color: #0066cc;">Equipo AKIBARA SPA</strong></p>
</div>
<div class="footer">
<strong>AKIBARA SPA</strong><br>
RUT: 78274225-6<br>
Santiago, Chile
</div>
</body>
</html>
"""

msg.attach(MIMEText(html_body, 'html', 'utf-8'))

# Adjuntar PDF
print(f"📎 Adjuntando PDF: {os.path.basename(pdf_path)} ({os.path.getsize(pdf_path)} bytes)")
with open(pdf_path, 'rb') as f:
    pdf_attachment = MIMEBase('application', 'pdf')
    pdf_attachment.set_payload(f.read())
    encoders.encode_base64(pdf_attachment)
    pdf_attachment.add_header('Content-Disposition', f'attachment; filename={os.path.basename(pdf_path)}')
    msg.attach(pdf_attachment)

# Adjuntar XML
print(f"📎 Adjuntando XML: {os.path.basename(xml_path)} ({os.path.getsize(xml_path)} bytes)")
with open(xml_path, 'rb') as f:
    xml_attachment = MIMEBase('text', 'xml')
    xml_attachment.set_payload(f.read())
    encoders.encode_base64(xml_attachment)
    xml_attachment.add_header('Content-Disposition', f'attachment; filename={os.path.basename(xml_path)}')
    msg.attach(xml_attachment)

print()
print("📤 Conectando al servidor SMTP...")

try:
    # Intentar conexión SMTP
    server = smtplib.SMTP(smtp_host, smtp_port, timeout=30)
    server.set_debuglevel(0)

    print("✓ Conectado")

    server.ehlo()
    print("✓ EHLO enviado")

    if server.has_extn('STARTTLS'):
        print("🔒 Iniciando TLS...")
        server.starttls()
        server.ehlo()
        print("✓ TLS habilitado")

    print("🔑 Autenticando...")
    server.login(smtp_user, smtp_pass)
    print("✓ Autenticado")

    print()
    print("📬 Enviando email...")
    server.sendmail(smtp_user, email_destino, msg.as_string())

    print()
    print("═" * 79)
    print("✅ EMAIL ENVIADO EXITOSAMENTE")
    print("═" * 79)
    print()
    print(f"Para:     {email_destino}")
    print(f"Asunto:   Boleta Electrónica N° {folio} - AKIBARA SPA")
    print(f"Adjuntos: {os.path.basename(pdf_path)}, {os.path.basename(xml_path)}")
    print()
    print("🎉 El email debería llegar en unos segundos.")
    print(f"📥 Revisa tu bandeja de entrada: {email_destino}")
    print("⚠️  Si no aparece, revisa la carpeta de Spam")
    print()

    server.quit()

except socket.gaierror as e:
    print(f"\n❌ Error de DNS: {e}")
    print("\nℹ️  El entorno tiene limitaciones de conectividad externa.")
    print("   Todo está configurado correctamente, pero se requiere")
    print("   ejecutar desde un entorno con acceso a internet.\n")
    sys.exit(1)
except smtplib.SMTPAuthenticationError as e:
    print(f"\n❌ Error de autenticación: {e}")
    print("   Verifica usuario y contraseña SMTP\n")
    sys.exit(1)
except Exception as e:
    print(f"\n❌ Error: {e}\n")
    sys.exit(1)

print("═" * 79)
print("                         PROCESO COMPLETADO                                ")
print("═" * 79)
