# 📋 Documentación: Consulta de Estado de DTEs

## 📖 Resumen

Este documento explica cómo consultar el estado de documentos tributarios electrónicos (DTEs) enviados al SII usando el **track_id**.

---

## 🔍 ¿Qué es el Track ID?

El **Track ID** es un identificador único que el SII (Servicio de Impuestos Internos) asigna cuando se envía un documento electrónico. Este ID permite rastrear el estado del documento en el sistema del SII.

**Ejemplo de Track ID:** `123456789` o `ABC123XYZ`

---

## ✅ Funcionalidad Implementada

### 1. En el Código del Plugin

El plugin tiene **completamente implementada** la funcionalidad de consulta de estado:

#### Clases y Métodos:

**`Simple_DTE_API_Client::consultar_estado_envio($track_id, $rut_emisor)`**
- Ubicación: `includes/class-simple-dte-api-client.php:185`
- Función: Consultar estado en la API externa
- Parámetros:
  - `$track_id` - ID de seguimiento del SII
  - `$rut_emisor` - RUT del emisor

**`Simple_DTE_Consultas::consultar_estado_envio($track_id)`**
- Ubicación: `includes/class-simple-dte-consultas.php:29`
- Función: Wrapper para consultar estado con logging
- Retorna: Array con estado o WP_Error

#### Base de Datos:

La tabla `wp_simple_dte_boletas` incluye el campo:
```sql
track_id VARCHAR(50)  -- Track ID del SII
```

#### Interfaz de Administración:

Existe una página de administración en:
- Template: `templates/admin-consultas.php`
- Incluye formulario para ingresar track_id manualmente

### 2. Scripts CLI Creados

Se crearon 3 scripts para facilitar las pruebas:

#### **`consultar-estado-manual.php`** ⭐ (Principal)
Script para consultar el estado de forma manual.

**Uso:**
```bash
php consultar-estado-manual.php <track_id>
```

**Ejemplo:**
```bash
php consultar-estado-manual.php 123456789
```

**Salida esperada:**
```
═══════════════════════════════════════════════════════════
  CONSULTA DE ESTADO DE ENVÍO AL SII
═══════════════════════════════════════════════════════════

Track ID: 123456789
RUT Emisor: 78274225-6
Ambiente: Certificación

🔍 Consultando estado...
✅ Consulta exitosa (HTTP 200)

╔════════════════════════════════════════════════════════════╗
║  RESULTADO DE LA CONSULTA                                  ║
╚════════════════════════════════════════════════════════════╝

estado                   : ACEPTADO
glosa                    : DTE Aceptado por el SII
fecha_proceso            : 2025-11-18 15:30:00
```

#### **`test-track-id-simple.php`**
Script de prueba completo que:
1. Genera un DTE con SimpleAPI ✅
2. Intenta enviarlo al SII (endpoint no disponible actualmente)
3. Consulta el estado (endpoint no disponible actualmente)

#### **`test-consulta-estado.php`**
Framework completo de pruebas end-to-end.

---

## 🚫 Limitación Actual: SimpleAPI

### Problema Identificado

SimpleAPI en su plan actual **NO ofrece** los siguientes endpoints:

```
❌ POST /api/v1/dte/enviar          (Devuelve HTTP 404)
❌ GET  /api/v1/dte/estado/{track_id}  (Devuelve HTTP 404)
```

### Lo que SÍ funciona:

```
✅ POST /api/v1/dte/generar         (Genera DTE firmado)
```

### Implicación:

Actualmente **no es posible**:
- Enviar automáticamente el DTE al SII a través de SimpleAPI
- Consultar el estado automáticamente a través de SimpleAPI

---

## 🔄 Flujo Actual de Trabajo

### Opción 1: Envío Manual (Recomendado)

1. **Generar el DTE con el plugin**
   ```php
   $resultado = Simple_DTE_Boleta_Generator::generar_desde_orden($order);
   // El DTE se genera y firma con SimpleAPI ✅
   ```

2. **Descargar el XML generado**
   - El XML se guarda en `xmls/boleta_FOLIO_FECHA.xml`

3. **Enviar manualmente al SII**
   - Ingresar a [www.sii.cl](https://www.sii.cl)
   - Sección: Factura Electrónica → Envío de Documentos
   - Subir el XML
   - El SII retornará un **Track ID**

4. **Guardar el Track ID en WordPress**
   ```php
   $order->update_meta_data('_boleta_track_id', 'ABC123XYZ');
   $order->save();
   ```

5. **Consultar el estado cuando necesites**
   ```bash
   php consultar-estado-manual.php ABC123XYZ
   ```

### Opción 2: Uso de WordPress Admin

1. Ir a **WP Admin → Simple DTE → Consultas**
2. Ingresar el Track ID en el formulario
3. Hacer clic en "Consultar"
4. Ver el resultado en pantalla

---

## 💻 Ejemplos de Código

### Consultar Estado desde PHP

```php
// Método 1: Usando la clase API Client directamente
$track_id = 'ABC123XYZ';
$rut_emisor = '78274225-6';

$resultado = Simple_DTE_API_Client::consultar_estado_envio($track_id, $rut_emisor);

if (is_wp_error($resultado)) {
    echo "Error: " . $resultado->get_error_message();
} else {
    echo "Estado: " . $resultado['estado'];
    echo "Glosa: " . $resultado['glosa'];
}
```

```php
// Método 2: Usando la clase Consultas (con logging automático)
$track_id = 'ABC123XYZ';

$resultado = Simple_DTE_Consultas::consultar_estado_envio($track_id);

if (is_wp_error($resultado)) {
    echo "Error: " . $resultado->get_error_message();
} else {
    echo "Estado: " . $resultado['estado'];
    echo "Glosa: " . $resultado['glosa'];
    print_r($resultado['data']); // Datos completos
}
```

### Consultar Estado de una Orden WooCommerce

```php
// Obtener track_id de una orden
$order_id = 14;
$order = wc_get_order($order_id);
$track_id = $order->get_meta('_boleta_track_id');

if (!empty($track_id)) {
    $resultado = Simple_DTE_Consultas::consultar_estado_envio($track_id);

    if (!is_wp_error($resultado)) {
        // Actualizar estado en la orden
        $order->update_meta_data('_boleta_estado_sii', $resultado['estado']);
        $order->save();

        echo "Track ID: $track_id\n";
        echo "Estado actualizado: " . $resultado['estado'] . "\n";
    }
}
```

---

## 📊 Estados Posibles del SII

Cuando consultas el estado de un DTE, el SII puede retornar:

| Código | Descripción | Significado |
|--------|-------------|-------------|
| **REC** | Recibido | El DTE fue recibido por el SII |
| **EPR** | En Proceso | El SII está procesando el documento |
| **RCH** | Rechazado | El DTE fue rechazado |
| **RPR** | Reparo | El DTE tiene observaciones |
| **ACE** | Aceptado | El DTE fue aceptado por el SII ✅ |

---

## 🔧 Troubleshooting

### Error: "API Key no configurado"
**Solución:**
```bash
# Verifica que la API Key esté configurada
grep API_KEY .env.certificacion.ejemplo
```

### Error: "HTTP 404 - Resource not found"
**Causa:** SimpleAPI no ofrece el endpoint de consulta de estado.

**Solución:**
- Usar el envío manual al SII
- O cambiar a otro proveedor de API que sí ofrezca estos servicios

### Error: "Track ID requerido"
**Causa:** No se proporcionó un track_id válido.

**Solución:**
```bash
# Asegúrate de pasar el track_id como argumento
php consultar-estado-manual.php TU_TRACK_ID_AQUI
```

---

## 🚀 Próximos Pasos

### Cuando SimpleAPI agregue los endpoints:

Una vez que SimpleAPI habilite los endpoints de envío y consulta:

1. **No será necesario modificar el código** - Ya está preparado
2. Los métodos funcionarán automáticamente:
   - `Simple_DTE_API_Client::enviar_sobre()`
   - `Simple_DTE_API_Client::consultar_estado_envio()`

### Alternativas mientras tanto:

Si necesitas funcionalidad completa ahora, puedes:

1. **Usar otro proveedor de API** como:
   - LibreDTE
   - Facturando.cl
   - Chilesystems
   - Otros

2. **Implementar integración directa con SII** (complejo, requiere:
   - Implementar cliente SOAP
   - Manejar certificados digitales
   - Procesar respuestas XML del SII

---

## 📁 Archivos Relacionados

```
├── includes/
│   ├── class-simple-dte-api-client.php      # Cliente API (línea 185)
│   ├── class-simple-dte-consultas.php       # Lógica de consultas (línea 29)
│   └── class-simple-dte-sobre-generator.php # Generador de sobres
├── templates/
│   └── admin-consultas.php                   # Interfaz de admin
├── db/
│   └── schema.sql                            # Campo track_id (línea 86)
├── consultar-estado-manual.php               # ⭐ Script principal CLI
├── test-track-id-simple.php                  # Script de pruebas
└── test-consulta-estado.php                  # Framework de pruebas
```

---

## 📞 Soporte

Para más información:
- [README.md](readme.md) - Documentación general del plugin
- [PRUEBAS-CERTIFICACION.md](PRUEBAS-CERTIFICACION.md) - Guía de certificación
- [INICIO-RAPIDO.md](INICIO-RAPIDO.md) - Guía de inicio rápido

---

## 📝 Changelog

**2025-11-18:**
- ✅ Implementados scripts de consulta manual
- ✅ Verificada estructura del código
- ✅ Identificada limitación de SimpleAPI
- ✅ Documentado flujo de trabajo alternativo

---

**Autor:** Sistema de Facturación Electrónica Simple DTE
**Fecha:** 18 de Noviembre 2025
