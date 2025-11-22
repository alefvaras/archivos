# Manual: RCV y Gestión de Folios

## 📊 1. RCV (Registro de Compras y Ventas)

### ¿Qué es el RCV?

El RCV es un **libro electrónico** que registra todas las ventas (boletas/facturas) de un período. Es obligatorio enviarlo al SII mensualmente.

### Tipos de Documentos que Genera el Sistema:

1. **RCV de Ventas** - Registro mensual de todas las ventas
2. **Resumen Diario (RCOF)** - Consumo de folios diario (boletas)

---

## 🔍 Cómo Usar el Sistema RCV

### A. Generar RCV Mensual

```
WordPress Admin → WooCommerce → Simple DTE → RCV
```

**Pasos:**
1. Selecciona **fecha desde** (ej: 2025-11-01)
2. Selecciona **fecha hasta** (ej: 2025-11-30)
3. Click **"Generar RCV"**
4. Sistema genera XML del libro
5. **Descargar XML** para enviarlo al SII

**XML Generado:**
- Carátula con datos del emisor
- Resumen por tipo de documento (39, 41, etc.)
- Detalle de cada documento emitido
- Totales: Neto, IVA, Monto Total

### B. Generar Resumen Diario (RCOF)

El Resumen Diario se genera **automáticamente cada noche a las 23:00** mediante un cron job.

**También puedes generarlo manualmente:**

```
WordPress Admin → WooCommerce → Simple DTE → Resumen Diario
```

**Pasos:**
1. Selecciona **fecha** (ej: 2025-11-16)
2. Click **"Generar Resumen Diario"**
3. Sistema genera XML ConsumoFolios
4. **Descargar XML** para enviarlo al SII

**Qué Incluye:**
- Rangos de folios utilizados
- Folios anulados (Boletas de Ajuste)
- Totales del día
- Separado por tipo (Boleta afecta 39, Boleta exenta 41)

### C. AJAX Endpoints Disponibles

```php
// Generar RCV
wp_ajax_simple_dte_generar_rcv

// Generar Resumen Diario
wp_ajax_simple_dte_generar_resumen_diario

// Enviar al SII
wp_ajax_simple_dte_enviar_rcv
```

---

## 📋 2. Sistema de Folios

### Cómo Funciona Actualmente

1. **Subir CAF del SII**
   - Descargar CAF desde sitio del SII
   - Subir archivo .xml en Simple DTE
   - Sistema guarda: folio_desde, folio_hasta, folio_actual

2. **Uso de Folios**
   - Cada boleta consume 1 folio
   - `folio_actual` se incrementa: 1889 → 1890 → 1891...
   - Al llegar a `folio_hasta`, se agotan los folios

3. **¿Qué Pasa Cuando Se Agotan?**
   - ❌ **ACTUAL:** Error "Se agotaron los folios del CAF actual"
   - ❌ **PROBLEMA:** No busca automáticamente otro CAF
   - ❌ **EFECTO:** Debes subir manualmente un nuevo CAF

---

## ⚠️ Problema Identificado: Folios Agotados

### Código Actual (línea 289)

```php
if ($siguiente_folio > $caf->folio_hasta) {
    return new WP_Error('folios_agotados', __('Se agotaron los folios del CAF actual', 'simple-dte'));
}
```

### Limitaciones:

1. ❌ No busca automáticamente el siguiente CAF
2. ❌ No alerta cuando quedan pocos folios (ej: 10%)
3. ❌ No marca el CAF agotado como "usado"
4. ❌ No activa automáticamente el siguiente CAF

---

## ✅ Solución Propuesta: Sistema Inteligente de Folios

### Mejoras a Implementar:

#### 1. **Cambio Automático de CAF**

Cuando se agote un CAF:
- ✅ Marcar CAF agotado como `estado = 'usado'`
- ✅ Buscar siguiente CAF disponible con `estado = 'activo'`
- ✅ Si existe, usar automáticamente
- ✅ Si no existe, entonces mostrar error

#### 2. **Alertas de Folios Bajos**

Cuando quedan menos del 10% de folios:
- ✅ Mostrar alerta en dashboard
- ✅ Enviar email al administrador
- ✅ Registrar en logs

#### 3. **Validación en Admin**

Al subir nuevo CAF:
- ✅ Verificar que no se solape con CAF existente
- ✅ Marcar como `estado = 'pendiente'` si hay otro activo
- ✅ Activar automáticamente cuando el anterior se agote

#### 4. **Dashboard de Folios**

```
┌────────────────────────────────────────────────┐
│ 📋 Estado de Folios                            │
├────────────────────────────────────────────────┤
│ CAF Activo:      #123                          │
│ Folio actual:    1920 / 1988                   │
│ Folios restantes: 68 (6.8%)                    │
│                                                │
│ ⚠️ ALERTA: Quedan menos de 70 folios          │
│ 💡 Sube un nuevo CAF pronto                    │
└────────────────────────────────────────────────┘
```

---

## 🔧 Implementación Técnica

### Función Mejorada: `obtener_siguiente_folio()`

```php
private static function obtener_siguiente_folio() {
    global $wpdb;

    $table = $wpdb->prefix . 'simple_dte_folios';

    // 1. Obtener CAF activo
    $caf = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE tipo_dte = %d AND estado = 'activo' ORDER BY id DESC LIMIT 1",
        39
    ));

    if (!$caf) {
        return new WP_Error('no_caf', __('No hay CAF activo para boletas', 'simple-dte'));
    }

    $siguiente_folio = (int) $caf->folio_actual + 1;

    // 2. Si se agotó el CAF actual
    if ($siguiente_folio > $caf->folio_hasta) {

        // 2.1 Marcar CAF actual como usado
        $wpdb->update(
            $table,
            array('estado' => 'usado'),
            array('id' => $caf->id),
            array('%s'),
            array('%d')
        );

        // 2.2 Buscar siguiente CAF disponible
        $siguiente_caf = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE tipo_dte = %d AND estado = 'pendiente' ORDER BY folio_desde ASC LIMIT 1",
            39
        ));

        if ($siguiente_caf) {
            // 2.3 Activar siguiente CAF
            $wpdb->update(
                $table,
                array('estado' => 'activo'),
                array('id' => $siguiente_caf->id),
                array('%s'),
                array('%d')
            );

            Simple_DTE_Logger::info('CAF automáticamente activado', array(
                'caf_id' => $siguiente_caf->id,
                'folio_desde' => $siguiente_caf->folio_desde,
                'folio_hasta' => $siguiente_caf->folio_hasta
            ));

            return (int) $siguiente_caf->folio_desde;
        }

        // 2.4 No hay más CAFs disponibles
        return new WP_Error('folios_agotados', __('Se agotaron todos los folios. Por favor sube un nuevo CAF.', 'simple-dte'));
    }

    // 3. Verificar folios bajos (menos del 10%)
    $total_folios = $caf->folio_hasta - $caf->folio_desde + 1;
    $folios_restantes = $caf->folio_hasta - $siguiente_folio + 1;
    $porcentaje = ($folios_restantes / $total_folios) * 100;

    if ($porcentaje < 10) {
        self::alertar_folios_bajos($folios_restantes);
    }

    return $siguiente_folio;
}

/**
 * Alertar cuando quedan pocos folios
 */
private static function alertar_folios_bajos($folios_restantes) {
    // Solo alertar una vez por CAF
    $alerta_enviada = get_transient('simple_dte_alerta_folios_bajos');

    if (!$alerta_enviada) {
        // Registrar en logs
        Simple_DTE_Logger::warning('Folios bajos', array(
            'folios_restantes' => $folios_restantes
        ));

        // Enviar email al administrador
        $admin_email = get_option('admin_email');
        $subject = '⚠️ Alerta: Quedan Pocos Folios - Simple DTE';
        $message = sprintf(
            "Quedan solo %d folios disponibles.\n\nPor favor sube un nuevo archivo CAF pronto para evitar interrupciones.",
            $folios_restantes
        );

        wp_mail($admin_email, $subject, $message);

        // Marcar alerta como enviada (válido por 24 horas)
        set_transient('simple_dte_alerta_folios_bajos', true, DAY_IN_SECONDS);
    }
}
```

---

## 📝 Estados de CAF

| Estado | Descripción |
|--------|-------------|
| `activo` | CAF en uso actualmente |
| `pendiente` | CAF subido, esperando a ser activado |
| `usado` | CAF agotado, todos los folios consumidos |

---

## 🎯 Flujo de Uso Recomendado

### Configuración Inicial

1. **Descargar CAF del SII** (ej: 100 folios)
2. **Subir CAF** en Simple DTE
3. Sistema lo marca como `activo`
4. Generar boletas normalmente

### Cuando Quedan Pocos Folios

1. Sistema detecta < 10% de folios
2. **Alerta enviada** al administrador
3. Administrador descarga nuevo CAF del SII
4. **Sube nuevo CAF** → marcado como `pendiente`

### Cuando Se Agota CAF

1. Sistema detecta folio > folio_hasta
2. Marca CAF actual como `usado`
3. **Activa automáticamente** CAF pendiente
4. Continúa generando boletas sin interrupción

---

## 🚀 Próximos Pasos

1. ✅ Implementar cambio automático de CAF
2. ✅ Añadir alertas de folios bajos
3. ✅ Crear dashboard de estado de folios
4. ✅ Mejorar UI para gestión de CAFs
5. ✅ Tests automatizados

---

## 📞 Soporte

Si tienes problemas:
1. Revisa **WooCommerce → Simple DTE → Logs**
2. Verifica que tengas CAFs subidos
3. Asegúrate de tener permisos `manage_woocommerce`

---

**Última actualización:** 2025-11-17
