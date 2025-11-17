# Errores Encontrados en Revisión de Código

## ❌ ERRORES CRÍTICOS

### 1. Dashboard NO Compatible con HPOS (CRÍTICO)
**Archivo:** `includes/admin/class-simple-dte-dashboard.php`
**Líneas:** 81, 97, 113, 126

**Problema:**
Las queries SQL usan directamente `{$wpdb->postmeta}` y `{$wpdb->posts}`, lo cual NO funciona cuando HPOS está habilitado. Cuando HPOS está activo, los datos de órdenes están en tablas custom de WooCommerce, no en wp_posts/wp_postmeta.

**Código actual (INCORRECTO):**
```php
$dtes_hoy = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
     INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
     WHERE pm.meta_key = '_simple_dte_generada'
     AND pm.meta_value = 'yes'
     AND p.post_type = 'shop_order'
     AND p.post_date >= %s
     AND p.post_date <= %s",
    $hoy_inicio,
    $hoy_fin
));
```

**Solución:**
Usar `wc_get_orders()` con argumentos de fecha en lugar de queries SQL directas.

**Impacto:**
- ⚠️ Dashboard mostrará 0 DTEs cuando HPOS esté habilitado
- ⚠️ Estadísticas completamente incorrectas

---

### 2. Query SQL con IN clause mal preparado
**Archivo:** `includes/class-simple-dte-queue.php`
**Líneas:** 96-103

**Problema:**
El uso de `IN (%s, %s)` con `$wpdb->prepare()` no funciona correctamente en todas las versiones de WordPress. Genera una advertencia y puede fallar.

**Código actual (PROBLEMÁTICO):**
```php
$existing = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM " . self::$table_name . "
     WHERE order_id = %d
     AND dte_tipo = %s
     AND status IN (%s, %s)",
    $order_id,
    $dte_tipo,
    self::STATUS_PENDING,
    self::STATUS_PROCESSING
));
```

**Solución:**
Usar placeholders separados o usar `sprintf()` para los valores constantes.

**Impacto:**
- ⚠️ Puede generar advertencias de WordPress
- ⚠️ Duplicados en cola si la query falla

---

### 3. Compatibilidad con WooCommerce Antiguo
**Archivo:** `woocommerce-boletas-electronicas.php`
**Línea:** 516

**Problema:**
`wc_get_container()` solo existe en WooCommerce 3.6+. En versiones anteriores, esto causará un fatal error.

**Código actual (PUEDE FALLAR):**
```php
$screen = wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
    ? wc_get_page_screen_id('shop-order')
    : 'shop_order';
```

**Solución:**
Usar función alternativa o verificar existencia con `class_exists()` y `function_exists()`.

**Impacto:**
- ❌ Fatal error en WooCommerce < 3.6
- ❌ Plugin completamente roto en versiones antiguas

---

## ⚠️ ERRORES MENORES

### 4. Tabla de logs puede no inicializarse correctamente
**Archivo:** `includes/class-simple-dte-logger.php`
**Línea:** 41-56

**Problema:**
Si `init()` no se llama antes de `log()`, `$table_name` será NULL y las operaciones fallarán silenciosamente.

**Solución:**
Agregar lazy initialization en el método `log()`.

**Impacto:**
- ⚠️ Logs no se guardan en base de datos si init() falla
- ⚠️ Solo se guardan en archivos

---

### 5. Directorio admin/ puede no existir
**Archivo:** `woocommerce-boletas-electronicas.php`
**Línea:** 151-153

**Problema:**
Si el directorio `includes/admin/` no existe, `file_exists()` retorna false pero no hay manejo de error.

**Solución:**
Crear directorio automáticamente o agregar verificación.

**Impacto:**
- ⚠️ Dashboard no se carga si falta el directorio
- ⚠️ No hay notificación al usuario

---

## 📊 RESUMEN

| Severidad | Cantidad | Archivos Afectados |
|-----------|----------|-------------------|
| ❌ Crítico | 3 | class-simple-dte-dashboard.php, class-simple-dte-queue.php, woocommerce-boletas-electronicas.php |
| ⚠️ Menor | 2 | class-simple-dte-logger.php, woocommerce-boletas-electronicas.php |

**Total:** 5 errores encontrados

---

## 🔧 PRIORIDAD DE FIXES

1. **URGENTE:** Fix Dashboard HPOS compatibility (Error #1)
2. **ALTO:** Fix query SQL con IN clause (Error #2)
3. **ALTO:** Fix compatibilidad WooCommerce antiguo (Error #3)
4. **MEDIO:** Lazy initialization de logger (Error #4)
5. **BAJO:** Verificación de directorio admin (Error #5)

---

## ✅ COSAS QUE ESTÁN BIEN

- ✅ Sintaxis PHP correcta (sin errores de sintaxis)
- ✅ Uso de `$wpdb->prepare()` para la mayoría de queries
- ✅ Escape correcto de HTML con `esc_html()`, `esc_url()`, etc.
- ✅ Verificación de permisos con `current_user_can()`
- ✅ Protección ABSPATH en todos los archivos
- ✅ Uso de WooCommerce APIs en la mayoría del código
- ✅ WP-Cron configurado correctamente
- ✅ Limpieza automática de datos antiguos
