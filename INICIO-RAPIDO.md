# 🚀 Inicio Rápido - Pruebas en Ambiente de Certificación

## TL;DR - Comandos Rápidos

```bash
# 1. Configurar
cp .env.certificacion.ejemplo .env
nano .env  # Editar con tus datos

# 2. Verificar
php verificar-ambiente.php

# 3. Probar
php prueba-ambiente-certificacion.php

# 4. Ver resultados
php ver-reportes.php --ultimo
```

## 📝 Checklist Rápido

### Antes de Empezar
- [ ] Tienes cuenta en SimpleAPI ([registrarse](https://www.simpleapi.cl))
- [ ] Tienes certificado digital `.pfx`
- [ ] Descargaste CAF desde [www.sii.cl](https://www.sii.cl)
- [ ] PHP 7.4+ con extensiones `curl`, `openssl`, `simplexml`

### Configuración (5 minutos)
```bash
# Copiar archivo de configuración
cp .env.certificacion.ejemplo .env

# Editar .env con tus datos:
RUT_EMISOR=12345678-9
RAZON_SOCIAL=MI EMPRESA SPA
API_KEY=tu_api_key_de_simpleapi
CERT_PATH=/ruta/completa/al/certificado.pfx
CERT_PASSWORD=tu_password
CAF_PATH=/ruta/completa/al/FoliosSII.xml
AMBIENTE=certificacion  # ¡IMPORTANTE!
```

### Verificar (1 minuto)
```bash
php verificar-ambiente.php
```

**Salida esperada:** `¡AMBIENTE CORRECTAMENTE CONFIGURADO!`

### Ejecutar Pruebas (2-5 minutos)
```bash
# Opción 1: Prueba completa con envío al SII
php prueba-ambiente-certificacion.php

# Opción 2: Solo generación (sin envío)
php prueba-ambiente-certificacion.php --skip-envio

# Opción 3: Modo detallado
php prueba-ambiente-certificacion.php --verbose
```

### Ver Resultados
```bash
# Ver último reporte
php ver-reportes.php --ultimo

# Listar todos los reportes
php ver-reportes.php

# Ver reporte específico
php ver-reportes.php reportes/prueba-certificacion-2025-11-17-143022.json
```

## ❌ Solución de Errores Comunes

### "API Key no configurado"
```bash
# Obtén API Key en: https://www.simpleapi.cl
# Pégalo en .env:
API_KEY=tu_api_key_base64
```

### "Certificado no se puede leer"
```bash
# Verifica la contraseña:
openssl pkcs12 -info -in certificado.pfx -noout

# Actualiza en .env:
CERT_PASSWORD=password_correcto
```

### "CAF no encontrado"
```bash
# Descarga CAF desde www.sii.cl
# Sección: Factura Electrónica > Folios
# Configura ruta absoluta en .env:
CAF_PATH=/home/user/archivos/FoliosSII.xml
```

### "El sistema NO está en ambiente de certificación"
```bash
# Verifica .env:
grep AMBIENTE .env

# Debe decir:
AMBIENTE=certificacion
```

## 📚 Documentación Completa

Para más detalles, consulta:
- **[PRUEBAS-CERTIFICACION.md](PRUEBAS-CERTIFICACION.md)** - Guía completa paso a paso
- **[README.md](README.md)** - Documentación general del plugin

## 🆘 Soporte

¿Problemas? Ejecuta diagnóstico completo:
```bash
php verificar-ambiente.php --verbose > diagnostico.txt
```

Comparte `diagnostico.txt` al reportar problemas.

---

**¡Listo! En menos de 10 minutos tendrás pruebas reales funcionando.** 🎉
