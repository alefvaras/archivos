# Plugin: Boletas Electrónicas para WooCommerce

Plugin de WordPress/WooCommerce que genera automáticamente Boletas Electrónicas SII al completar órdenes de compra.

## Características

- ✅ **Generación automática de boletas** al completar órdenes
- ✅ **Campo RUT en checkout** con validación de dígito verificador
- ✅ **Envío automático por email** con PDF adjunto
- ✅ **Descarga de PDF** desde "Mi cuenta" del cliente
- ✅ **Integración con sistema de logging** y base de datos
- ✅ **Metabox en admin** con datos de boleta (folio, track ID, estado)
- ✅ **Columna de boleta** en lista de órdenes
- ✅ **Generación manual** desde panel de orden
- ✅ **Compatible con campo RUT existente** (detecta automáticamente)

## Requisitos

### WordPress y WooCommerce
- WordPress 5.8 o superior
- WooCommerce 6.0 o superior
- PHP 8.0 o superior

### Sistema de Boletas Electrónicas
- Sistema de boletas ya configurado (ver README-BOLETAS.md)
- Extensiones PHP: bcmath, gd, dom, pdo
- Certificado digital (.pfx)
- API Key de Simple API
- Archivo CAF del SII

## Instalación

### Opción 1: Instalación Manual (Desarrollo)

1. **Copiar archivos del plugin al directorio de tu sistema de boletas**

```bash
# El plugin debe estar en el mismo directorio que generar-boleta.php
cd /ruta/a/tu/sistema-boletas
# Verificar que woocommerce-boletas-electronicas.php esté aquí
ls -la woocommerce-boletas-electronicas.php
```

2. **Crear enlace simbólico en WordPress**

```bash
# Opción A: Enlace simbólico (recomendado para desarrollo)
ln -s /ruta/a/tu/sistema-boletas /var/www/html/wp-content/plugins/woocommerce-boletas-electronicas

# Opción B: Copiar plugin directamente
cp -r /ruta/a/tu/sistema-boletas /var/www/html/wp-content/plugins/woocommerce-boletas-electronicas
```

3. **Activar el plugin en WordPress**

- Ir a WordPress Admin → Plugins
- Buscar "Boletas Electrónicas para WooCommerce"
- Click en "Activar"

### Opción 2: Instalación como ZIP (Producción)

1. **Crear archivo ZIP del plugin**

```bash
cd /ruta/a/tu/sistema-boletas
zip -r woocommerce-boletas-electronicas.zip \
  woocommerce-boletas-electronicas.php \
  generar-boleta.php \
  lib/ \
  db/ \
  README-BOLETAS.md
```

2. **Subir plugin a WordPress**

- WordPress Admin → Plugins → Añadir nuevo
- Click en "Subir plugin"
- Seleccionar archivo ZIP
- Click en "Instalar ahora"
- Click en "Activar plugin"

## Configuración

### 1. Configurar Variables de Entorno

El plugin utiliza la misma configuración que el sistema de boletas.

**En wp-config.php** agregar:

```php
// Configuración de Base de Datos (opcional pero recomendado)
define('DB_BOLETAS_NAME', 'boletas_electronicas');
define('DB_BOLETAS_USER', 'root');
define('DB_BOLETAS_PASS', 'tu_password');

// O usar variables de entorno
putenv('DB_NAME=boletas_electronicas');
putenv('DB_USER=root');
putenv('DB_PASS=tu_password');
```

### 2. Configurar Sistema de Boletas

Editar `/ruta/sistema-boletas/generar-boleta.php`:

```php
define('API_KEY', 'tu-api-key-simple-api');
define('CERT_PATH', '/ruta/certificado.pfx');
define('CERT_PASSWORD', 'password-certificado');
define('CAF_PATH', '/ruta/FoliosSII.xml');
define('RUT_EMISOR', '12345678-9');
define('RAZON_SOCIAL', 'MI EMPRESA SPA');
define('AMBIENTE', 'certificacion'); // o 'produccion'
```

### 3. Verificar Permisos de Directorios

```bash
# Crear y dar permisos a directorios necesarios
mkdir -p logs pdfs xmls
chmod 755 logs pdfs xmls
```

## Uso

### Flujo Automático (Recomendado)

1. **Cliente realiza compra en WooCommerce**
   - Ingresa sus datos incluyendo RUT
   - Completa el pago

2. **Orden cambia a estado "Completada"**
   - Automáticamente se genera la boleta electrónica
   - Se envía al SII
   - Se genera PDF con timbre PDF417
   - Se envía email al cliente con PDF adjunto

3. **Cliente recibe email**
   - Email de WooCommerce con confirmación
   - PDF de boleta electrónica adjunto
   - Link para descargar desde "Mi cuenta"

### Flujo Manual (Desde Admin)

1. **Ir a WooCommerce → Órdenes**

2. **Abrir la orden**

3. **En metabox "Boleta Electrónica SII"**
   - Ver estado de boleta
   - Si no está generada, usar acción "Generar Boleta Electrónica"

4. **Desde acciones de orden**
   - Seleccionar "Generar Boleta Electrónica"
   - Click en botón →

### Descargar Boleta (Cliente)

1. **Cliente inicia sesión en "Mi cuenta"**

2. **Ir a "Pedidos"**

3. **Ver detalles de orden**

4. **Click en "Descargar Boleta (PDF)"**

### Descargar Boleta (Admin)

1. **WooCommerce → Órdenes → Ver orden**

2. **En metabox "Boleta Electrónica SII"**

3. **Click en "Descargar PDF"**

## Funcionalidades Detalladas

### Campo RUT en Checkout

- Se agrega automáticamente al checkout
- Si ya existe campo `_billing_rut`, se usa el existente
- Validación de formato: 12345678-9
- Validación de dígito verificador
- Campo obligatorio

### Generación Automática

- Hook: `woocommerce_order_status_completed`
- Solo genera una vez por orden
- Verifica que no exista boleta previa
- Guarda datos en metadatos de orden:
  - `_boleta_folio` - Número de folio
  - `_boleta_track_id` - Track ID del SII
  - `_boleta_estado` - Estado SII (REC, EPR, etc.)
  - `_boleta_fecha` - Fecha de generación
  - `_boleta_pdf_path` - Ruta del PDF

### Integración con Logging

- Todos los eventos se registran en logs
- Integración con `DTELogger.php`
- Logs en: `logs/dte_YYYY-MM-DD.log`
- Errores en: `logs/errors_YYYY-MM-DD.log`

### Integración con Base de Datos

- Auto-detección de BD disponible
- Si BD no disponible, usa modo archivo
- Compatible con `BoletaRepository.php`
- Transacciones ACID para folios

## Datos que se Extraen de WooCommerce

### Del Cliente:
```php
- RUT → campo _billing_rut
- Nombre → billing_first_name + billing_last_name
- Email → billing_email
- Dirección → billing_address_1
- Comuna → billing_city
```

### De los Items:
```php
- Nombre producto → nombre del item
- Descripción → descripción corta del producto
- Cantidad → quantity
- Precio → item_total (con IVA incluido)
```

### Costos Adicionales:
```php
- Envío → se agrega como item separado
- Total orden → se usa para validación
```

## Troubleshooting

### Error: "WooCommerce no encontrado"

**Problema:** Plugin requiere WooCommerce activo

**Solución:**
```bash
# Instalar WooCommerce
WordPress Admin → Plugins → Añadir nuevo → Buscar "WooCommerce"
```

### Error: "Boleta no se genera automáticamente"

**Problema:** Orden no cambia a "Completada" o hay error en generación

**Solución:**
1. Verificar estado de orden sea "Completada"
2. Ver logs: `logs/errors_YYYY-MM-DD.log`
3. Verificar configuración en `generar-boleta.php`
4. Generar manualmente desde admin

### Error: "PDF no se descarga"

**Problema:** Permisos de directorio o PDF no generado

**Solución:**
```bash
# Verificar permisos
chmod 755 pdfs/
ls -la pdfs/

# Verificar que PDF existe
ls -la pdfs/boleta_*.pdf
```

### Error: "RUT no válido"

**Problema:** Formato de RUT incorrecto

**Solución:**
- Formato correcto: 12345678-9
- Incluir guión antes del dígito verificador
- Dígito verificador puede ser 0-9 o K

### Error: "No hay más folios disponibles"

**Problema:** CAF agotado

**Solución:**
1. Obtener nuevo CAF del SII
2. Actualizar `CAF_PATH` en configuración
3. Si usa BD, migrar nuevo CAF: `php migrar-a-bd.php`

## Logs y Debugging

### Ver logs del plugin

```bash
# Logs generales
tail -f logs/dte_$(date +%Y-%m-%d).log | grep woocommerce

# Solo errores
tail -f logs/errors_$(date +%Y-%m-%d).log | grep woocommerce

# Buscar boletas generadas hoy
grep "Boleta generada exitosamente" logs/dte_$(date +%Y-%m-%d).log
```

### Verificar estado de orden

```php
// En functions.php de tu tema
add_action('woocommerce_order_status_changed', function($order_id, $old_status, $new_status) {
    error_log("Orden #{$order_id}: {$old_status} → {$new_status}");
}, 10, 3);
```

### Verificar que hook se ejecuta

```php
// En functions.php de tu tema
add_action('woocommerce_order_status_completed', function($order_id) {
    error_log("Hook completado ejecutado para orden #{$order_id}");
}, 5, 1);
```

## Personalización

### Cambiar email automático

Editar en `woocommerce-boletas-electronicas.php` línea ~390:

```php
$CONFIG = [
    'envio_automatico_email' => true,  // false para deshabilitar
    'adjuntar_pdf' => true,
    'adjuntar_xml' => false,
];
```

### Cambiar estado que dispara boleta

Por defecto se genera al completar orden. Para cambiar:

```php
// En woocommerce-boletas-electronicas.php línea ~149
// Cambiar de:
add_action('woocommerce_order_status_completed', [$this, 'generar_boleta_automatica'], 10, 1);

// A (por ejemplo, al procesar pago):
add_action('woocommerce_order_status_processing', [$this, 'generar_boleta_automatica'], 10, 1);
```

### Agregar más datos a la boleta

Editar método `generar_boleta_desde_orden()` en línea ~340.

## Compatibilidad

### Temas Compatibles
- ✅ Storefront (oficial WooCommerce)
- ✅ Astra
- ✅ OceanWP
- ✅ Flatsome
- ✅ Cualquier tema compatible con WooCommerce

### Plugins Compatibles
- ✅ WooCommerce Subscriptions
- ✅ WooCommerce Memberships
- ✅ YITH plugins
- ✅ Cualquier plugin que use hooks estándar

### Multisite
- ⚠️ Compatible, pero requiere configuración por sitio
- Cada sitio necesita su propio certificado y CAF

## Seguridad

- ✅ Validación de nonce en descargas
- ✅ Verificación de permisos de usuario
- ✅ Sanitización de datos de entrada
- ✅ Prepared statements en BD
- ✅ Validación de RUT con dígito verificador

## Soporte

Para problemas o consultas:

1. **Revisar logs:** `logs/errors_YYYY-MM-DD.log`
2. **Verificar configuración:** `generar-boleta.php`
3. **Ver documentación completa:** `README-BOLETAS.md`
4. **Ejecutar tests:** `php test-simple-dte.php`

## Licencia

GPL v2 or later

## Créditos

- Sistema de Boletas Electrónicas por [Tu Nombre]
- Integración Simple API: https://simpleapi.cl
- Especificaciones SII Chile
