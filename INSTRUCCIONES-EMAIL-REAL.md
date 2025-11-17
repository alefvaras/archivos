# 📧 Cómo Enviar Emails Reales con contacto@akibara.cl

## 🎯 Objetivo
Configurar el sistema para enviar boletas electrónicas por email usando la cuenta `contacto@akibara.cl` de Hostinger.

---

## 📋 Paso 1: Obtener Contraseña SMTP de Hostinger

### Opción A: Desde hPanel de Hostinger

1. **Iniciar sesión en Hostinger:**
   - Ir a: https://hpanel.hostinger.com/
   - Ingresar con tus credenciales

2. **Acceder a Emails:**
   - En el panel, ir a: **Emails** → **Cuentas de Email**
   - Buscar: `contacto@akibara.cl`

3. **Obtener Configuración SMTP:**
   - Click en el email `contacto@akibara.cl`
   - Ir a **Configuración** o **Ver detalles**
   - Anotar:
     ```
     SMTP Server: smtp.hostinger.com
     Puerto: 587 (TLS) o 465 (SSL)
     Usuario: contacto@akibara.cl
     Contraseña: [Tu contraseña del email]
     ```

### Opción B: Crear Nueva Contraseña (si la olvidaste)

1. En **Emails** → Click en `contacto@akibara.cl`
2. Click en **Cambiar contraseña**
3. Crear una nueva contraseña segura
4. **¡IMPORTANTE!** Guardarla en un lugar seguro

---

## ⚙️ Paso 2: Configurar la Contraseña en el Sistema

### Editar el archivo `enviar-email-boleta.php`

```bash
nano /home/user/archivos/enviar-email-boleta.php
```

### Buscar la línea 37 y agregar tu contraseña:

**ANTES:**
```php
'password' => '',  // DEBE configurarse
```

**DESPUÉS:**
```php
'password' => 'TU_CONTRASEÑA_SMTP_AQUI',
```

**Ejemplo:**
```php
'password' => 'MiContraseña123!',
```

### Guardar y salir:
- `Ctrl + O` (guardar)
- `Enter` (confirmar)
- `Ctrl + X` (salir)

---

## 🚀 Paso 3: Enviar Email Real

### Ejecutar el comando:

```bash
php enviar-email-boleta.php \
  '/home/user/archivos/pdfs/boleta_1890_20251117025355.pdf' \
  '/home/user/archivos/xmls/boleta_1890_20251117025355.xml' \
  'ale.fvaras@gmail.com'
```

### Deberías ver:

```
═══════════════════════════════════════════════════════════
                   ENVÍO DE BOLETA ELECTRÓNICA
═══════════════════════════════════════════════════════════

📧 Configuración SMTP
Host: smtp.hostinger.com
Usuario: contacto@akibara.cl

📤 Enviando email...

✅ EMAIL ENVIADO EXITOSAMENTE

Para: ale.fvaras@gmail.com
Asunto: Boleta Electrónica N° 1890 - AKIBARA SPA
Adjuntos: 2 archivos (10.13 KB)
```

---

## 🔒 Alternativa: Variables de Entorno (Más Seguro)

En lugar de guardar la contraseña en el código, puedes usar variables de entorno:

### Crear archivo `.env`:

```bash
cat > /home/user/archivos/.env << 'EOF'
SMTP_HOST=smtp.hostinger.com
SMTP_PORT=587
SMTP_USER=contacto@akibara.cl
SMTP_PASS=TU_CONTRASEÑA_AQUI
SMTP_FROM=contacto@akibara.cl
EOF
```

### Proteger el archivo:
```bash
chmod 600 /home/user/archivos/.env
```

---

## 🧪 Paso 4: Verificar que Llegó el Email

### En tu bandeja de entrada (ale.fvaras@gmail.com):

1. **Buscar:** "Boleta Electrónica N° 1890"
2. **Verificar remitente:** AKIBARA SPA <contacto@akibara.cl>
3. **Verificar adjuntos:**
   - ✅ boleta_1890_20251117025355.pdf (7.53 KB)
   - ✅ boleta_1890_20251117025355.xml (2.60 KB)

### Si no llega, revisar:

1. **Carpeta de Spam/Correo no deseado**
2. **Logs del servidor:**
   ```bash
   tail -f /var/log/mail.log
   ```

---

## 🛠️ Solución de Problemas

### Error: "Authentication failed"
```
❌ ERROR: SMTP Error: Could not authenticate
```

**Solución:**
- Verificar contraseña SMTP
- Verificar que el email existe en Hostinger
- Probar con puerto 465 (SSL) en lugar de 587 (TLS)

### Error: "Connection refused"
```
❌ ERROR: Could not connect to SMTP host
```

**Solución:**
- Verificar que `smtp.hostinger.com` es accesible
- Verificar firewall del servidor
- Probar con:
  ```bash
  telnet smtp.hostinger.com 587
  ```

### Email no llega pero no hay errores
```
✅ EMAIL ENVIADO EXITOSAMENTE
(pero no llega)
```

**Posibles causas:**
1. **Filtros anti-spam:** Gmail marcó como spam
2. **SPF/DKIM:** Falta configurar registros DNS
3. **Rate limiting:** Hostinger tiene límite de emails/hora

**Solución:**
1. Revisar spam
2. Agregar registros SPF/DKIM en DNS (siguiente sección)

---

## 🔐 Paso 5: Mejorar Deliverability (Opcional pero Recomendado)

### Configurar SPF en DNS

Agregar registro TXT en tu dominio `akibara.cl`:

```
Tipo: TXT
Nombre: @
Valor: v=spf1 include:spf.hostinger.com ~all
TTL: 14400
```

### Configurar DKIM

1. En Hostinger hPanel → **Emails** → **DKIM**
2. Copiar el registro DKIM generado
3. Agregar en DNS como registro TXT

### Configurar DMARC

```
Tipo: TXT
Nombre: _dmarc
Valor: v=DMARC1; p=quarantine; rua=mailto:contacto@akibara.cl
TTL: 14400
```

---

## ✅ Checklist Final

Antes de enviar el primer email real:

- [ ] Contraseña SMTP obtenida de Hostinger
- [ ] Contraseña configurada en `enviar-email-boleta.php` línea 37
- [ ] Cuenta `contacto@akibara.cl` existe y funciona
- [ ] Probado envío a un email de prueba primero
- [ ] Verificado que el PDF y XML se adjuntan correctamente
- [ ] Revisado que el email no va a spam
- [ ] (Opcional) SPF/DKIM configurados para mejor deliverability

---

## 📞 Soporte

Si tienes problemas:

1. **Hostinger Support:** https://www.hostinger.com/support
2. **Documentación SMTP:** https://support.hostinger.com/es/articles/1583229-como-configurar-smtp
3. **Verificar estado:** https://www.hostinger.com/status

---

## 🎯 Comando Rápido (una vez configurado)

Guardar este alias en tu `.bashrc` o `.zshrc`:

```bash
alias enviar-boleta='php /home/user/archivos/enviar-email-boleta.php'
```

Luego puedes enviar boletas con:

```bash
enviar-boleta ruta/al/pdf.pdf ruta/al/xml.xml email@destino.com
```

---

**¡Todo listo!** Una vez configures la contraseña SMTP, el sistema enviará emails reales automáticamente. 📧✨
