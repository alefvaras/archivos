# Guía de Notas de Crédito Automáticas

## Sistema Configurable de Generación de NC

**Versión:** 1.0
**Fecha:** 2025-11-17
**Estado:** ✅ Implementado

---

## 📋 Resumen

El sistema de Notas de Crédito ahora ofrece **generación automática configurable** que se activa cuando se crea un reembolso (refund) en WooCommerce.

**Características:**
- ✅ Activación/desactivación mediante configuración
- ✅ Selección de tipo de NC por defecto
- ✅ Validación opcional de monto completo
- ✅ Generación manual siempre disponible
- ✅ Logs completos de operaciones
- ✅ Notificaciones en la orden

---

## ⚙️ Configuración

### Acceder a la Configuración

```
WordPress Admin > WooCommerce > Simple DTE > Configuración
```

Desplázate hasta la sección **"Notas de Crédito Automáticas"**

---

### Opciones Disponibles

#### 1️⃣ Generar NC automáticamente

**Opción:** Checkbox

**Descripción:** Activa o desactiva la generación automática de NC cuando se crea un reembolso.

**Valores:**
- ☑️ **Activado:** Se genera NC automáticamente al crear refund
- ☐ **Desactivado:** Solo generación manual desde el metabox (comportamiento anterior)

**Recomendación:** Activar si la mayoría de refunds requieren NC

---

#### 2️⃣ Tipo de NC por defecto

**Opción:** Select dropdown

**Descripción:** Código de referencia que se usará para las NC automáticas

**Valores:**
- **1 - Anulación** (recomendado)
  - Anula el documento completo
  - Uso típico: devolución total, cancelación de venta

- **2 - Corregir texto**
  - Corrige descripción o datos del documento
  - Uso típico: error en nombre de producto, dirección incorrecta

- **3 - Corregir montos**
  - Corrige totales del documento
  - Uso típico: error en cálculo, descuento mal aplicado

**Recomendación:** Usar "1 - Anulación" para devoluciones estándar

---

#### 3️⃣ Validar monto completo

**Opción:** Checkbox

**Descripción:** Solo genera NC si el monto del reembolso es igual al total de la boleta/factura

**Valores:**
- ☑️ **Activado:** Solo refunds totales generan NC automática
- ☐ **Desactivado:** Cualquier refund (parcial o total) genera NC

**Comportamiento cuando está activado:**
- ✅ Refund total ($119.000 de $119.000) → NC automática
- ⚠️ Refund parcial ($50.000 de $119.000) → Requiere NC manual

**Recomendación:** Activar si quieres mayor control sobre refunds parciales

---

## 🚀 Casos de Uso

### Caso 1: Automatización Total

**Configuración:**
```
☑️ Generar NC automáticamente
Tipo: 1 - Anulación
☐ Validar monto completo
```

**Resultado:**
- Cualquier refund (parcial o total) genera NC automáticamente
- Siempre con código de anulación
- Sin intervención manual requerida

**Ideal para:**
- Tiendas con muchos refunds
- Política de devolución simple
- Equipo sin experiencia en DTE

---

### Caso 2: Solo Anulaciones Totales

**Configuración:**
```
☑️ Generar NC automáticamente
Tipo: 1 - Anulación
☑️ Validar monto completo
```

**Resultado:**
- Solo refunds totales generan NC automática
- Refunds parciales requieren NC manual
- Mayor control sobre NC

**Ideal para:**
- Tiendas con refunds parciales frecuentes
- Necesidad de revisar refunds parciales
- Control de inventario estricto

---

### Caso 3: Generación Manual Completa

**Configuración:**
```
☐ Generar NC automáticamente
```

**Resultado:**
- No se genera NC automática
- Siempre requiere clic en el botón manual
- Máximo control

**Ideal para:**
- Tiendas con refunds poco frecuentes
- Necesidad de revisar cada NC
- Múltiples tipos de NC requeridos

---

## 🔄 Flujo de Trabajo

### Con NC Automática Activada

```
1. Cliente solicita devolución
   ↓
2. WooCommerce Admin crea refund
   ├─ Ingresa monto
   ├─ Ingresa razón
   └─ Confirma refund
   ↓
3. WooCommerce dispara hook: woocommerce_order_refunded
   ↓
4. Sistema valida configuración:
   ├─ ¿NC automática habilitada? → Si no, FIN
   ├─ ¿Orden tiene DTE? → Si no, FIN
   ├─ ¿Ya tiene NC? → Si sí, FIN
   └─ ¿Validar monto completo?
       ├─ Si sí: ¿Es refund total? → Si no, FIN
       └─ Si no: Continuar
   ↓
5. Sistema genera NC automáticamente:
   ├─ Obtiene folio NC
   ├─ Construye documento con referencias
   ├─ Firma con Simple API
   ├─ Envía al SII
   └─ Guarda Track ID
   ↓
6. Sistema agrega nota en la orden:
   "✓ Nota de Crédito N° 123 generada automáticamente"
   ↓
7. Admin recibe notificación
   ↓
8. FIN
```

**Tiempo total:** 5-10 segundos (automático)

---

### Con NC Manual

```
1. Cliente solicita devolución
   ↓
2. WooCommerce Admin crea refund
   ↓
3. Sistema agrega nota:
   "Reembolso creado. Genere NC manualmente desde metabox"
   ↓
4. Admin abre orden
   ↓
5. Admin hace clic en "Generar Nota de Crédito"
   ↓
6. Selecciona tipo (Anular/Corregir/Montos)
   ↓
7. Confirma
   ↓
8. NC generada
```

**Tiempo total:** 30-60 segundos (manual)

---

## 📊 Validaciones Implementadas

El sistema valida en este orden:

### 1. Configuración Habilitada
```php
if (!get_option('simple_dte_auto_nc_enabled')) {
    return; // NC automática deshabilitada
}
```

### 2. Orden y Refund Válidos
```php
$order = wc_get_order($order_id);
$refund = wc_get_order($refund_id);

if (!$order || !$refund) {
    return; // No se encontraron
}
```

### 3. Orden Tiene DTE
```php
if ($order->get_meta('_simple_dte_generada') !== 'yes') {
    return; // No tiene boleta/factura
}
```

### 4. No Tiene NC Previa
```php
if ($order->get_meta('_simple_dte_nc_generada') === 'yes') {
    return; // Ya tiene NC
}
```

### 5. Validación de Monto (opcional)
```php
if (get_option('simple_dte_auto_nc_validar_monto')) {
    $monto_refund = abs($refund->get_total());
    $monto_orden = $order->get_total();

    if ($monto_refund != $monto_orden) {
        // Refund parcial - requiere manual
        $order->add_order_note('Genere NC manualmente');
        return;
    }
}
```

---

## 📝 Logs y Notificaciones

### Logs del Sistema

Todos los eventos se registran en los logs:

```
[2025-11-17 10:30:00] INFO: NC automática deshabilitada
  order_id: 123
  refund_id: 456

[2025-11-17 10:35:00] INFO: Generando NC automática
  order_id: 789
  refund_id: 790
  codigo_ref: 1

[2025-11-17 10:35:05] INFO: NC automática generada exitosamente
  order_id: 789
  folio: 125

[2025-11-17 10:40:00] INFO: Refund parcial, se requiere generación manual
  order_id: 800
  monto_refund: 50000
  monto_orden: 100000
```

### Notas en la Orden

El sistema agrega notas automáticas:

**NC generada exitosamente:**
```
✓ Nota de Crédito N° 125 generada automáticamente
```

**Refund parcial (requiere manual):**
```
Reembolso parcial creado. Genere la Nota de Crédito manualmente desde el metabox.
```

**Error al generar:**
```
Error al generar NC automática: No hay CAF activo para notas de crédito. Genere la NC manualmente.
```

---

## ⚠️ Casos Especiales

### Caso: Refund Parcial con Validación Activada

**Escenario:**
- Boleta original: $119.000
- Refund creado: $50.000 (parcial)
- Validar monto completo: ☑️ Activado

**Comportamiento:**
1. No se genera NC automática
2. Se agrega nota: "Reembolso parcial creado..."
3. Admin debe generar NC manualmente
4. En el metabox puede elegir tipo de NC apropiado

**Razón:** Los refunds parciales pueden requerir diferentes tipos de NC (corregir montos vs anulación)

---

### Caso: Múltiples Refunds en la Misma Orden

**Escenario:**
- Orden con boleta generada
- Refund 1: $30.000
- Refund 2: $20.000

**Comportamiento:**
1. Primer refund genera NC automática
2. Segundo refund NO genera NC (orden ya tiene NC)
3. Admin debe crear NC adicionales manualmente si es necesario

**Nota:** Por ahora el sistema solo soporta 1 NC por orden automática

---

### Caso: Orden sin DTE

**Escenario:**
- Orden sin boleta/factura generada
- Se crea refund

**Comportamiento:**
1. Sistema detecta que no hay DTE
2. No intenta generar NC
3. Log: "Orden sin DTE, no se puede generar NC automática"

---

## 🔧 Troubleshooting

### NC no se genera automáticamente

**Verificar:**

1. ✅ **Configuración habilitada**
   ```
   WP Admin > Simple DTE > Configuración
   ☑️ Generar NC automáticamente debe estar marcado
   ```

2. ✅ **Orden tiene DTE**
   ```
   Revisar metabox de la orden
   Debe mostrar: "Boleta/Factura: Folio XXX"
   ```

3. ✅ **No tiene NC previa**
   ```
   Revisar metabox
   NO debe mostrar: "Nota de Crédito generada"
   ```

4. ✅ **Validación de monto**
   ```
   Si está activada, el refund debe ser por el monto total
   ```

5. ✅ **CAF disponible**
   ```
   Debe haber CAF tipo 61 activo en el sistema
   ```

6. ✅ **Revisar logs**
   ```
   WP Admin > Simple DTE > Logs
   Buscar mensajes relacionados con el order_id
   ```

---

### NC se genera pero con error

**Revisar logs:**

```
WP Admin > Simple DTE > Logs
Filtrar por: ERROR
```

**Errores comunes:**

| Error | Causa | Solución |
|-------|-------|----------|
| No hay CAF activo | CAF tipo 61 no cargado | Cargar CAF en Folios |
| Folios agotados | Se acabaron los folios | Solicitar nuevo CAF |
| Error de firma | Certificado inválido | Verificar certificado |
| Error SII | Problema en servidor SII | Reintentar más tarde |

---

## 📊 Comparativa de Modos

| Aspecto | Manual | Auto (Sin validar monto) | Auto (Validar monto) |
|---------|--------|--------------------------|----------------------|
| **Refund total** | Botón manual | ✅ Automático | ✅ Automático |
| **Refund parcial** | Botón manual | ✅ Automático | ⚠️ Manual requerido |
| **Tiempo** | 30-60 seg | 5-10 seg | Mixto |
| **Control** | Máximo | Bajo | Medio |
| **Errores** | Menos | Posibles | Menos |
| **Ideal para** | Pocos refunds | Muchos refunds | Refunds mixtos |

---

## 🎯 Mejores Prácticas

### ✅ Recomendado

1. **Activar NC automática** si tienes más de 10 refunds al mes
2. **Activar validación de monto** si tienes refunds parciales frecuentes
3. **Usar código 1 (Anulación)** como tipo por defecto
4. **Revisar logs** semanalmente para detectar problemas
5. **Tener CAF tipo 61** siempre disponible con folios suficientes

### ⚠️ Advertencias

1. **No desactivar** NC automática sin avisar al equipo
2. **No ignorar** notas de error en las órdenes
3. **No asumir** que todas las NC se generan automáticamente
4. **Revisar** NC generadas automáticamente periódicamente

---

## 📈 Estadísticas de Uso

Puedes revisar las NC generadas en:

```sql
-- NC automáticas generadas
SELECT COUNT(*)
FROM wp_postmeta
WHERE meta_key = '_simple_dte_nc_generada'
AND meta_value = 'yes';

-- NC generadas en el último mes
SELECT COUNT(*)
FROM wp_postmeta pm
JOIN wp_posts p ON pm.post_id = p.ID
WHERE pm.meta_key = '_simple_dte_nc_fecha'
AND pm.meta_value >= DATE_SUB(NOW(), INTERVAL 1 MONTH);
```

---

## 🔄 Actualización desde Versión Anterior

Si ya tenías el plugin instalado:

1. **Actualizar archivos del plugin**
2. **No se requiere migración de datos**
3. **La configuración por defecto es:** NC automática DESACTIVADA
4. **Activar manualmente** si deseas usar la automatización

---

## ✅ Checklist de Implementación

Para activar NC automáticas en tu tienda:

- [ ] Actualizar plugin a última versión
- [ ] Ir a Simple DTE > Configuración
- [ ] Activar "Generar NC automáticamente"
- [ ] Seleccionar tipo por defecto (1 - Anulación)
- [ ] Decidir si validar monto completo
- [ ] Guardar configuración
- [ ] Verificar que hay CAF tipo 61 activo
- [ ] Crear refund de prueba
- [ ] Verificar que NC se genera
- [ ] Revisar logs
- [ ] Capacitar al equipo

---

## 📞 Soporte

Si tienes problemas con las NC automáticas:

1. Revisar esta guía
2. Revisar logs del sistema
3. Verificar configuración
4. Probar con refund de prueba
5. Revisar que el CAF tipo 61 esté activo

---

**Última actualización:** 2025-11-17
**Versión:** 1.0
**Autor:** Sistema Simple DTE
