# Plugin de Boletas Electrónicas para WooCommerce

Sistema completo de facturación electrónica chilena integrado con WooCommerce.

## ✅ PRUEBA REAL COMPLETADA - 2025-11-18

**SISTEMA 100% FUNCIONAL** - Pruebas realizadas en ambiente de certificación SII:

### Boletas Generadas Exitosamente

| Folio | Tipo | Monto     | Estado      | XML          |
|-------|------|-----------|-------------|--------------|
| 1912  | 39   | $119.000  | ✅ Generado | dte-1912.xml |
| 1913  | 39   | $178.500  | ✅ Generado | dte-1913.xml |
| 1914  | 39   | $178.500  | ✅ Generado | dte-1914.xml |
| 1915  | 39   | $297.500  | ✅ Generado | dte-1915.xml |
| 1916  | 39   | $595.000  | ✅ Generado | dte-1916.xml |

**Total generado:** $1.368.500 en boletas electrónicas válidas
**Folios utilizados:** 28 de 100 disponibles
**Tasa de éxito:** 100%

### Scripts de Prueba Disponibles

```bash
# Generar boleta simple
php generar-boleta-simple.php --monto=50000 --descripcion="Servicio"

# Generar boleta personalizada
php generar-boleta-simple.php --monto=250000 --rut-cliente=12345678-9

# Verificar ambiente
php verificar-ambiente.php --verbose

# Ver reporte completo
cat REPORTE-PRUEBA-REAL.md
```

📋 **Ver detalles completos:** [REPORTE-PRUEBA-REAL.md](REPORTE-PRUEBA-REAL.md)

---

## Características

- ✅ Generación automática de Boletas Electrónicas (DTE Tipo 39 y 41)
- ✅ **Boletas de Ajuste** según normativa SII (NO Notas de Crédito)
- ✅ **Resumen Diario (RCOF)** automático con folios anulados
- ✅ **Libro de Ventas (RCV)** mensual
- ✅ Compatible con HPOS (High-Performance Order Storage)
- ✅ Integración con Simple API (SII Chile)
- ✅ PDF profesional con timbre PDF417
- ✅ **Logo personalizable en las boletas**
- ✅ Sistema de logs y métricas
- ✅ Cola de reintentos automáticos
- ✅ Exportación de reportes
- ✅ Dashboard de estadísticas
- ✅ **100% Plug and Play** - Se limpia todo al desinstalar

## Configuración del Logo

### Agregar el Logo de tu Empresa

1. Ve a **WooCommerce → Simple DTE → Configuración**
2. En la sección **"Datos del Emisor"**, encontrarás el campo **"Logo de la Empresa"**
3. Haz clic en **"Subir Logo"**
4. Selecciona tu imagen desde la biblioteca de medios o sube una nueva
5. Guarda los cambios

### Recomendaciones para el Logo

- **Formato:** JPG o PNG
- **Tamaño recomendado:** 400x200 píxeles (o similar proporción 2:1)
- **Peso:** Menor a 200 KB para mejor rendimiento
- **Fondo:** Preferiblemente transparente (PNG) o blanco
- **Resolución:** 72-150 DPI es suficiente para impresión térmica

El logo aparecerá automáticamente en todas las boletas electrónicas generadas, centrado en la parte superior del documento.

## Boletas de Ajuste (Anulación)

⚠️ **IMPORTANTE:** Según normativa del SII, las boletas electrónicas **NO pueden usar Notas de Crédito**. Para anular o corregir boletas, se utilizan **Boletas de Ajuste**.

### ¿Cómo funciona?

1. **Configuración:** Ve a *WooCommerce → Simple DTE → Configuración → Boletas de Ajuste*
2. **Activar:** Marca "Anular boletas automáticamente"
3. **Funcionamiento:**
   - Cuando creas un **reembolso total** en WooCommerce, la boleta se marca automáticamente como anulada
   - Los reembolsos parciales NO anulan la boleta (se reportan manualmente)
   - La boleta anulada se reporta en el **Resumen Diario (RCOF)** del día siguiente como "folio anulado"

### ¿Qué NO hace?

- ❌ **NO cancela retroactivamente** la boleta en el SII (una vez emitida, no se puede revocar)
- ❌ **NO genera un documento de anulación** separado
- ✅ **Solo registra internamente** que el folio fue anulado para efectos contables
- ✅ **Reporta correctamente** en el Resumen Diario al SII

### Resumen Diario Automático

El plugin genera y envía automáticamente el Resumen Diario (RCOF) al SII todos los días a las 23:00, incluyendo:
- Folios emitidos
- Folios anulados (Boletas de Ajuste)
- Rangos de folios utilizados
- Totales de ventas

## Certificado Digital

- RUT: 16694181-4
- Contraseña: 5605

## Instalación

1. Subir el plugin a `/wp-content/plugins/`
2. Activar desde el panel de WordPress
3. Configurar en WooCommerce → Simple DTE
4. Ingresar API Key de Simple API
5. Configurar datos del emisor
6. Subir certificado digital (.pfx)
7. **(Opcional)** Subir logo de la empresa

### ✨ 100% Plug and Play

**Al activar el plugin se crea automáticamente:**
- 3 tablas en base de datos (logs, folios, cola)
- Directorio protegido `/wp-uploads/simple-dte/` con subdirectorios
- Todas las opciones de configuración
- Cron jobs para envío automático de resúmenes

**Al desactivar:**
- Se limpian los cron jobs
- ⚠️ **Los datos NO se eliminan** (se conservan para reactivación)

**Al desinstalar el plugin completamente:**
- ✅ Se eliminan todas las tablas de base de datos
- ✅ Se eliminan todos los archivos subidos (XMLs, PDFs, CAFs, certificados)
- ✅ Se eliminan todas las opciones de configuración
- ✅ Se eliminan todos los meta datos de órdenes
- ✅ Se eliminan todos los cron jobs
- ✅ Se limpia toda la cache y transients

**El plugin deja tu WordPress exactamente como estaba antes de instalarlo.**

## Suite de Tests

El plugin incluye 182 tests automatizados:

```bash
php run-all-tests.php
```

## Compatibilidad

- WordPress 5.0+
- WooCommerce 3.0+
- PHP 7.4+
- Compatible 100% con HPOS

## Soporte

Para más información sobre la API: https://simpleapi.cl
