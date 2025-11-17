# Plugin de Boletas Electrónicas para WooCommerce

Sistema completo de facturación electrónica chilena integrado con WooCommerce.

## Características

- ✅ Generación automática de Boletas Electrónicas (DTE Tipo 39)
- ✅ Notas de Crédito automáticas
- ✅ Compatible con HPOS (High-Performance Order Storage)
- ✅ Integración con Simple API (SII Chile)
- ✅ PDF profesional con timbre PDF417
- ✅ **Logo personalizable en las boletas**
- ✅ Sistema de logs y métricas
- ✅ Cola de reintentos automáticos
- ✅ Exportación de reportes
- ✅ Dashboard de estadísticas

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
