# Pruebas en Ambiente de Certificación - Guía Completa

## 📋 Tabla de Contenidos

1. [Introducción](#introducción)
2. [¿Qué es el Ambiente de Certificación?](#qué-es-el-ambiente-de-certificación)
3. [Requisitos Previos](#requisitos-previos)
4. [Configuración Inicial](#configuración-inicial)
5. [Ejecutar Verificación del Ambiente](#ejecutar-verificación-del-ambiente)
6. [Ejecutar Pruebas con Datos Reales](#ejecutar-pruebas-con-datos-reales)
7. [Interpretar Resultados](#interpretar-resultados)
8. [Solución de Problemas](#solución-de-problemas)
9. [Pasar a Producción](#pasar-a-producción)

---

## 🎯 Introducción

Esta guía te ayudará a realizar **pruebas de verdad con datos reales** en el **ambiente de certificación** del SII (Servicio de Impuestos Internos de Chile).

El ambiente de certificación es un entorno seguro donde puedes:
- ✅ Probar con tus datos empresariales reales
- ✅ Generar DTEs (Boletas, Facturas, etc.) sin validez tributaria
- ✅ Enviar documentos al SII para validación
- ✅ Verificar que todo funciona antes de pasar a producción
- ❌ **NO** emitir documentos con validez legal/tributaria

---

## 🏢 ¿Qué es el Ambiente de Certificación?

El **ambiente de certificación** del SII es un entorno de pruebas oficial que permite:

| Característica | Certificación | Producción |
|----------------|---------------|------------|
| **Datos** | Reales | Reales |
| **Certificado** | Real | Real |
| **Validez tributaria** | ❌ NO | ✅ SÍ |
| **Envío al SII** | ✅ Sí (servidor de pruebas) | ✅ Sí (servidor real) |
| **Errores** | 🛡️ Sin consecuencias | ⚠️ Pueden generar problemas |
| **Ideal para** | Aprender, probar, validar | Operación real |

> **💡 Importante:** Los DTEs generados en certificación **NO tienen validez tributaria** y **NO pueden usarse** para efectos legales o contables.

---

## ✅ Requisitos Previos

Antes de comenzar, necesitas:

### 1. Datos Empresariales
- [x] RUT de la empresa emisora
- [x] Razón social
- [x] Giro comercial
- [x] Dirección completa
- [x] Comuna y ciudad

### 2. Certificado Digital
- [x] Certificado digital formato `.pfx` (PKCS#12)
- [x] Emitido por una autoridad certificadora reconocida por el SII
- [x] Asociado al RUT de tu empresa
- [x] Contraseña del certificado
- [x] Fecha de expiración vigente (mínimo 30 días)

📌 **Obtener certificado:** [www.sii.cl](https://www.sii.cl) → Factura Electrónica → Certificado Digital

### 3. CAF (Código de Autorización de Folios)
- [x] Archivo XML de folios descargado del SII
- [x] Tipo de DTE correspondiente (ej: 39 para Boletas)
- [x] Al menos 10 folios disponibles

📌 **Obtener CAF:** [www.sii.cl](https://www.sii.cl) → Factura Electrónica → Folios

### 4. Cuenta en SimpleAPI
- [x] Cuenta registrada en [SimpleAPI](https://www.simpleapi.cl)
- [x] API Key generado
- [x] Créditos o plan activo

📌 **Crear cuenta:** [https://www.simpleapi.cl](https://www.simpleapi.cl)

### 5. Servidor PHP
- [x] PHP 7.4 o superior
- [x] Extensiones: `curl`, `openssl`, `simplexml`, `mbstring`, `json`
- [x] Permisos de escritura en directorios del proyecto

---

## 🔧 Configuración Inicial

### Paso 1: Configurar Variables de Entorno

Copia el archivo de ejemplo y ajusta los valores:

```bash
cp .env.certificacion.ejemplo .env
nano .env  # o vim, code, etc.
```

### Paso 2: Configurar Datos del Emisor

Edita el archivo `.env` con tus datos reales:

```bash
# Datos de tu empresa
RUT_EMISOR=12345678-9
RAZON_SOCIAL=MI EMPRESA SPA
GIRO=Servicios de Tecnología
DIRECCION=Av. Principal 123
COMUNA=Santiago
EMAIL_EMISOR=contacto@miempresa.cl
```

### Paso 3: Configurar SimpleAPI

Ingresa tu API Key de SimpleAPI:

```bash
API_KEY=tu_api_key_base64_aqui
```

### Paso 4: Configurar Certificado Digital

Coloca tu certificado `.pfx` en el directorio del proyecto y configura:

```bash
CERT_PATH=/ruta/completa/al/certificado.pfx
CERT_PASSWORD=tu_password_del_certificado
```

### Paso 5: Configurar CAF (Folios)

Descarga el CAF desde el SII y configura:

```bash
CAF_PATH=/ruta/completa/al/FoliosSII.xml
```

### Paso 6: Verificar Ambiente

**CRÍTICO:** Asegúrate de que el ambiente esté en certificación:

```bash
AMBIENTE=certificacion
```

---

## 🔍 Ejecutar Verificación del Ambiente

Antes de realizar pruebas, verifica que todo esté correctamente configurado:

```bash
php verificar-ambiente.php
```

### Salida Esperada

```
======================================================================
  VERIFICACIÓN DE AMBIENTE - CERTIFICACIÓN SII
======================================================================

1. CONFIGURACIÓN DE AMBIENTE
-----------------------------
[OK] ✓ Ambiente CERTIFICACIÓN (seguro para pruebas)
[OK] ✓ Debug habilitado
[OK] ✓ Timezone: America/Santiago

2. DATOS DEL EMISOR
-------------------
[OK] ✓ RUT Emisor: 12345678-9
[OK] ✓ RUT Emisor válido
[OK] ✓ Razón Social: MI EMPRESA SPA
...

======================================================================
RESUMEN DE VERIFICACIÓN
======================================================================
Checks completados: 28/28 (100%)
Errores críticos: 0
Advertencias: 0

¡AMBIENTE CORRECTAMENTE CONFIGURADO!
Puede ejecutar: php prueba-ambiente-certificacion.php
```

### Modo Verbose

Para ver información detallada:

```bash
php verificar-ambiente.php --verbose
```

---

## 🧪 Ejecutar Pruebas con Datos Reales

Una vez verificado el ambiente, ejecuta las pruebas:

### Opción 1: Prueba Completa (Con Envío al SII)

```bash
php prueba-ambiente-certificacion.php
```

Esto ejecutará:
1. ✅ Verificación de ambiente (debe ser certificación)
2. ✅ Health check completo del sistema
3. ✅ Verificación de credenciales y certificados
4. ✅ Generación de **Boleta Electrónica** (Tipo 39)
5. ✅ Generación de **Factura Electrónica** (Tipo 33)
6. ✅ Generación de **Boleta Exenta** (Tipo 41)
7. ✅ **Envío al SII** (servidor de certificación)
8. ✅ Consulta de estados en el SII
9. ✅ Generación de reporte completo

### Opción 2: Solo Generación (Sin Envío)

Para probar la generación de DTEs sin enviarlos al SII:

```bash
php prueba-ambiente-certificacion.php --skip-envio
```

### Opción 3: Modo Verbose

Para ver información detallada de cada paso:

```bash
php prueba-ambiente-certificacion.php --verbose
```

### Opción 4: Combinar Opciones

```bash
php prueba-ambiente-certificacion.php --verbose --skip-envio
```

---

## 📊 Interpretar Resultados

### Salida de Consola

```
======================================================================
  PRUEBA DE VERDAD - AMBIENTE DE CERTIFICACIÓN
  Datos Reales | Entorno Seguro | SII Certificación
======================================================================

Verificando ambiente...
[OK] ✓ Ambiente: CERTIFICACIÓN (seguro para pruebas reales)

Ejecutando health check del sistema...
[OK] ✓ Health check: OK

Verificando credenciales...
[OK] ✓ API Key: Configurado
[OK] ✓ Certificado: Válido y legible
[OK] ✓ Certificado válido por 365 días

=== PRUEBA 1: BOLETA ELECTRÓNICA (Tipo 39) ===
[INFO] Generando Boleta Electrónica...
[OK] ✓ DTE generado - Folio: 1889
[INFO] Enviando al SII (ambiente certificación)...
[OK] ✓ Enviado al SII - Track ID: ABC123XYZ
[INFO] Consultando estado de Boleta Electrónica...
[OK]   Estado SII: ACEPTADO

...

======================================================================
REPORTE FINAL DE PRUEBAS - AMBIENTE DE CERTIFICACIÓN
======================================================================

[boleta] Boleta Electrónica:
  Generado: ✓ SÍ
  Folio: 1889
  Enviado: ✓ SÍ
  Track ID: ABC123XYZ
  Estado SII: ACEPTADO

...

----------------------------------------------------------------------
RESUMEN:
  Total de pruebas: 3
  DTEs generados: 3/3
  DTEs enviados al SII: 3/3
  Errores totales: 0
======================================================================

¡TODAS LAS PRUEBAS COMPLETADAS EXITOSAMENTE!
```

### Archivo de Reporte JSON

Cada ejecución genera un reporte en:

```
reportes/prueba-certificacion-2025-11-17-143022.json
```

Contenido del reporte:

```json
{
  "fecha": "2025-11-17 14:30:22",
  "ambiente": "certificacion",
  "skip_envio": false,
  "resultados": {
    "boleta": {
      "nombre": "Boleta Electrónica",
      "generado": true,
      "enviado": true,
      "folio": 1889,
      "track_id": "ABC123XYZ",
      "xml_path": "/ruta/al/DTE_39_1889.xml",
      "pdf_path": "/ruta/al/DTE_39_1889.pdf",
      "estado_sii": "ACEPTADO",
      "glosa_sii": "DTE Aceptado por el SII",
      "errores": []
    },
    ...
  }
}
```

### Archivos Generados

Después de ejecutar las pruebas, encontrarás:

```
📁 archivos/
├── 📁 xmls/
│   ├── DTE_39_1889.xml    ← Boleta XML
│   ├── DTE_33_1890.xml    ← Factura XML
│   └── DTE_41_1891.xml    ← Boleta Exenta XML
├── 📁 pdfs/
│   ├── DTE_39_1889.pdf    ← Boleta PDF
│   ├── DTE_33_1890.pdf    ← Factura PDF
│   └── DTE_41_1891.pdf    ← Boleta Exenta PDF
└── 📁 reportes/
    └── prueba-certificacion-2025-11-17-143022.json
```

---

## 🔧 Solución de Problemas

### Error: "El sistema NO está en ambiente de certificación"

**Causa:** La variable `AMBIENTE` no está configurada correctamente.

**Solución:**
```bash
# Verificar archivo .env
grep AMBIENTE .env

# Debe decir:
AMBIENTE=certificacion
```

### Error: "Certificado no se puede leer"

**Causa:** Contraseña incorrecta o certificado corrupto.

**Solución:**
```bash
# Verificar que el certificado es válido
openssl pkcs12 -info -in tu-certificado.pfx -noout

# Si pide contraseña, ingresa la correcta
```

### Error: "API Key no configurado"

**Causa:** Falta configurar la API Key de SimpleAPI.

**Solución:**
1. Ingresa a [SimpleAPI](https://www.simpleapi.cl)
2. Genera un API Key
3. Copia el valor y pégalo en `.env`:
   ```bash
   API_KEY=tu_api_key_base64
   ```

### Error: "CAF no encontrado"

**Causa:** Ruta incorrecta al archivo CAF.

**Solución:**
```bash
# Verificar que el archivo existe
ls -la /ruta/al/FoliosSII.xml

# Ajustar en .env con la ruta absoluta correcta
CAF_PATH=/ruta/completa/al/FoliosSII.xml
```

### Error: "SimpleAPI NO accesible"

**Causa:** Problemas de conectividad o firewall.

**Solución:**
```bash
# Probar conectividad manual
curl -I https://api.simpleapi.cl

# Verificar extensión curl de PHP
php -m | grep curl
```

### Advertencia: "Certificado expira en X días"

**Causa:** El certificado está próximo a vencer.

**Solución:**
1. Si quedan menos de 30 días, renueva el certificado
2. Descarga el nuevo certificado del SII
3. Actualiza `CERT_PATH` en `.env`

---

## 🚀 Pasar a Producción

Una vez que todas las pruebas en certificación sean exitosas, puedes pasar a producción:

### ⚠️ ADVERTENCIAS CRÍTICAS

1. **Verifica TODO antes de cambiar a producción**
2. Los DTEs en producción **SÍ tienen validez tributaria**
3. Los errores en producción pueden generar multas del SII
4. Asegúrate de que tus sistemas estén listos para operar

### Checklist de Pre-Producción

- [ ] Todas las pruebas en certificación son exitosas (100%)
- [ ] Certificado digital válido y vigente (+30 días)
- [ ] CAF de producción descargados del SII
- [ ] API Key de SimpleAPI para producción configurado
- [ ] Base de datos de producción configurada
- [ ] Backups configurados y probados
- [ ] Plan de rollback definido
- [ ] Equipo capacitado en el uso del sistema

### Cambiar a Producción

**Paso 1:** Descargar nuevos CAF desde el SII

En producción, debes descargar **CAFs de producción** (no uses los de certificación).

**Paso 2:** Actualizar `.env`

```bash
# Cambiar ambiente
AMBIENTE=produccion

# Actualizar CAF de producción
CAF_PATH=/ruta/al/CAF_PRODUCCION.xml

# Verificar que el certificado es el correcto
CERT_PATH=/ruta/al/certificado-produccion.pfx
```

**Paso 3:** Verificar Configuración

```bash
php verificar-ambiente.php
```

**Paso 4:** Emitir DTE de Prueba Real

Emite un documento de bajo monto como prueba:

```bash
# Por ejemplo, una boleta de $100
```

**Paso 5:** Monitorear

Monitorea el sistema durante las primeras horas/días en producción:
- Revisa logs
- Verifica estados en el SII
- Confirma recepción de confirmaciones

---

## 📚 Recursos Adicionales

- **SII Factura Electrónica:** [https://www.sii.cl/servicios_online/1039-.html](https://www.sii.cl/servicios_online/1039-.html)
- **SimpleAPI Documentación:** [https://docs.simpleapi.cl](https://docs.simpleapi.cl)
- **Soporte Simple DTE:** Abre un issue en el repositorio

---

## 🤝 Soporte

Si encuentras problemas:

1. Revisa esta documentación completa
2. Ejecuta `php verificar-ambiente.php --verbose`
3. Revisa los logs en `logs/`
4. Abre un issue con los detalles del error

---

**¡Éxito con tus pruebas! 🎉**
