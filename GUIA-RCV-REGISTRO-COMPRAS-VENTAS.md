# Guía Completa: RCV (Registro de Compras y Ventas)

## ¿Qué es el RCV?

El **RCV (Registro de Compras y Ventas)** es un libro electrónico que registra todas las operaciones de compra y venta de un período tributario y debe enviarse mensualmente al SII.

---

## 📅 ¿CUÁNDO SE DEBE ENVIAR EL RCV?

### ⚠️ IMPORTANTE: Cambio de Normativa 2024

| Ambiente | Boletas Electrónicas | Facturas |
|----------|---------------------|----------|
| **PRODUCCIÓN** | ❌ **NO es obligatorio** (desde 2024) | ✅ Obligatorio |
| **CERTIFICACIÓN** | ✅ **SÍ es requerido** (para certificar) | ✅ Requerido |

### Plazos SOLO para Certificación SII

| Tipo | Plazo de Envío |
|------|----------------|
| **RCV de Boletas (Certificación)** | Durante proceso de certificación |
| **Facturas (Producción)** | Hasta el **día 13 del mes siguiente** |

### Calendario Mensual Típico

```
Mes de Ventas: NOVIEMBRE 2024
├─ 1-30 Nov: Emisión de boletas/facturas
├─ 1-13 Dic: Generar y enviar RCV al SII
└─ 14 Dic: Multa si no se envió
```

---

## 🔄 Frecuencia de Envío

### ⚠️ Boletas Electrónicas (DTE 39, 41)

**PRODUCCIÓN:**
- ❌ **NO es obligatorio enviar RCV** (cambio normativa 2024)
- ℹ️ El SII obtiene la información directamente de cada boleta enviada
- ✅ Puedes enviar si quieres, pero NO es requerido

**CERTIFICACIÓN:**
- ✅ **SÍ es requerido** para pasar las pruebas del SII
- 📋 Debes demostrar que tu sistema puede generar y enviar RCV
- 🎯 Se envía durante el proceso de certificación

### Envío MENSUAL (solo Facturas en Producción)
- **Período:** Un mes completo (Ej: 01-30 Nov)
- **Tipo:** TOTAL
- **Cuándo:** Hasta el día 13 del mes siguiente
- **Aplica a:** Facturas, Notas de Crédito, Notas de Débito

### Envío RECTIFICATORIO (si hay errores)
- **Período:** Mismo mes que se quiere corregir
- **Tipo:** RECTIFICA
- **Cuándo:** Después del envío original, cuando se detecten errores

### Envío PARCIAL (casos especiales)
- **Período:** Parte del mes
- **Tipo:** PARCIAL
- **Cuándo:** Solo en casos excepcionales autorizados por el SII

---

## 📋 ¿Qué Debe Incluir el RCV de Ventas?

### Documentos que SE INCLUYEN:
✅ Boletas electrónicas (DTE 39)
✅ Boletas exentas (DTE 41)
✅ Facturas electrónicas (DTE 33)
✅ Notas de crédito (DTE 61)
✅ Notas de débito (DTE 56)

### Documentos que NO se incluyen:
❌ Guías de despacho
❌ Documentos anulados antes de ser enviados al SII
❌ Documentos rechazados por el SII

---

## 🔧 Estado Actual de tu Sistema

### ✅ Implementado

**Generación de XML RCV:**
- ✅ Genera XML del libro de ventas
- ✅ Incluye resumen por tipo de documento
- ✅ Incluye detalle de cada documento
- ✅ Formato correcto según esquema SII (LibroCV_v10.xsd)

**Período que cubre:**
- Busca órdenes de WooCommerce en rango de fechas
- Filtra solo órdenes con DTE generada
- Calcula totales (Neto, IVA, Total)

### ❌ FALTA Implementar

**Envío al SII:**
- ❌ No hay función para enviar el XML al SII
- ❌ No hay firma electrónica del libro
- ❌ No hay validación de respuesta del SII

---

## 📊 Estructura del XML Generado

```xml
<?xml version="1.0" encoding="ISO-8859-1"?>
<LibroCompraVenta>
  <EnvioLibro>
    <Caratula>
      <RutEmisorLibro>76063822-6</RutEmisorLibro>
      <PeriodoTributario>2024-11</PeriodoTributario>
      <TipoOperacion>VENTA</TipoOperacion>
      <TipoLibro>ESPECIAL</TipoLibro>
      <TipoEnvio>TOTAL</TipoEnvio>
    </Caratula>

    <ResumenPeriodo>
      <TpoDoc>39</TpoDoc>
      <TotDoc>150</TotDoc>
      <TotMntNeto>1000000</TotMntNeto>
      <TotMntIVA>190000</TotMntIVA>
      <TotMntTotal>1190000</TotMntTotal>
    </ResumenPeriodo>

    <Detalle>
      <TpoDoc>39</TpoDoc>
      <Folio>1902</Folio>
      <FchDoc>2024-11-16</FchDoc>
      <RUTDoc>11111111-1</RUTDoc>
      <RznSoc>Cliente Ejemplo</RznSoc>
      <MntNeto>10000</MntNeto>
      <TasaIVA>19</TasaIVA>
      <IVA>1900</IVA>
      <MntTotal>11900</MntTotal>
    </Detalle>
    <!-- ... más detalles -->
  </EnvioLibro>
</LibroCompraVenta>
```

---

## 🚀 Proceso Completo para Enviar RCV

### 1. Generar el XML del Libro (✅ Ya implementado)
```php
$resultado = Simple_DTE_RCV::generar_rcv_ventas('2024-11-01', '2024-11-30');
$xml = $resultado['xml'];
```

### 2. Firmar el XML (❌ FALTA)
```php
// Se debe firmar con el certificado digital
$xml_firmado = firmar_libro_electronico($xml, $certificado);
```

### 3. Enviar al SII (❌ FALTA)
```php
// Endpoint SII: /cgi_rtc/RTC/RTCComun.cgi
$response = enviar_rcv_sii($xml_firmado, $rut, $ambiente);
```

### 4. Verificar Respuesta
```php
// El SII devuelve Track ID para seguimiento
if ($response['estado'] == 'OK') {
    $track_id = $response['track_id'];
    // Consultar estado después
}
```

---

## 🎯 Casos de Uso Típicos

### Caso 1: RCV Mensual Normal (Noviembre 2024)

**Escenario:**
- Tienda emitió 150 boletas en Noviembre
- Total ventas: $1,190,000 (Neto: $1,000,000 + IVA: $190,000)
- Fecha actual: 5 de Diciembre 2024

**Acción:**
```bash
1. Ir a WooCommerce > RCV
2. Seleccionar: 01/11/2024 - 30/11/2024
3. Clic "Generar RCV"
4. Clic "Enviar al SII" (cuando esté implementado)
5. Guardar Track ID del SII
6. Verificar aceptación al día siguiente
```

**Resultado esperado:**
- ✅ RCV aceptado por el SII
- ✅ Track ID: 12345678
- ✅ Estado: ACEPTADO (al día siguiente)

---

### Caso 2: RCV Rectificatorio (Corregir error)

**Escenario:**
- RCV de Octubre ya fue enviado
- Se detectó que faltó incluir 5 boletas
- Necesitas corregir

**Acción:**
```bash
1. Generar nuevo RCV del mismo período (Octubre)
2. Cambiar TipoEnvio de "TOTAL" a "RECTIFICA"
3. Incluir TODOS los documentos (no solo los que faltaban)
4. Enviar al SII
5. Este RCV reemplaza al anterior
```

---

### Caso 3: Período con Notas de Crédito

**Escenario:**
- Noviembre: 100 boletas + 10 notas de crédito por devoluciones
- Las NC están asociadas a boletas de Octubre

**Acción:**
```bash
1. El RCV de Noviembre incluye las 10 NC
2. Las NC aparecen con TpoDoc=61
3. Los montos de NC se restan del total
```

**En el XML:**
```xml
<ResumenPeriodo>
  <TpoDoc>39</TpoDoc>  <!-- Boletas -->
  <TotDoc>100</TotDoc>
  <TotMntTotal>1000000</TotMntTotal>
</ResumenPeriodo>

<ResumenPeriodo>
  <TpoDoc>61</TpoDoc>  <!-- Notas de Crédito -->
  <TotDoc>10</TotDoc>
  <TotMntTotal>-50000</TotMntTotal>  <!-- Negativo! -->
</ResumenPeriodo>
```

---

## ⚠️ Errores Comunes

### Error 1: Período Incorrecto
```
❌ Problema: Enviar RCV de Noviembre el 15 de Diciembre (fuera de plazo)
✅ Solución: Enviar hasta el 13 de Diciembre
💰 Multa: $100.000+ por envío fuera de plazo
```

### Error 2: Documentos Faltantes
```
❌ Problema: El RCV tiene 150 documentos pero emitiste 160
✅ Solución: Verificar que incluiste TODOS los DTEs aceptados por el SII
🔍 Revisar: Órdenes de WooCommerce con meta _simple_dte_generada = 'yes'
```

### Error 3: Totales Incorrectos
```
❌ Problema: Suma manual no coincide con suma de detalles
✅ Solución: El sistema calcula automáticamente, verificar redondeos
```

### Error 4: RUT Receptor Vacío
```
❌ Problema: Boleta sin RUT del cliente
✅ Solución: Sistema usa '66666666-6' (consumidor final)
📝 Esto es NORMAL para boletas a consumidor final
```

---

## 📈 Recomendaciones

### 1. Envío Proactivo
- ⏰ Enviar el RCV el día **1-3 del mes siguiente** (no esperar al día 13)
- 📅 Configurar recordatorio mensual
- ✅ Verificar aceptación al día siguiente

### 2. Validación Antes de Enviar
```bash
✓ Contar documentos emitidos en el período
✓ Verificar que todos estén aceptados por el SII
✓ Revisar que totales sean consistentes
✓ Confirmar que no hay DTEs rechazados incluidos
```

### 3. Respaldo
```bash
✓ Guardar XML generado
✓ Guardar Track ID del SII
✓ Capturar pantalla de aceptación
✓ Mantener registro en base de datos
```

### 4. Automatización Futura
```bash
✓ Cron job que genere RCV el día 1 de cada mes
✓ Envío automático al SII
✓ Email de confirmación al administrador
✓ Alerta si hay errores
```

---

## 🔍 Consultar Estado del RCV Enviado

Una vez enviado el RCV al SII, puedes consultar su estado:

### Endpoint de Consulta
```
GET /api/v1/libro/{track_id}/estado
```

### Posibles Estados
- **PROCESANDO**: El SII está revisando el libro
- **ACEPTADO**: Libro aceptado correctamente ✅
- **RECHAZADO**: Hay errores, revisar detalle ❌
- **REPAROS**: Aceptado con observaciones ⚠️

---

## 🛠️ Próximos Pasos para Implementar Envío

### 1. Agregar Firma Electrónica
```php
// includes/class-simple-dte-rcv.php
public static function firmar_rcv($xml, $certificado_path, $password) {
    // Usar openssl para firmar el XML
    // Agregar nodo <Signature> al XML
}
```

### 2. Agregar Envío al SII
```php
public static function enviar_rcv_sii($xml_firmado) {
    // POST a https://maullin.sii.cl/cgi_rtc/RTC/RTCComun.cgi (certificación)
    // POST a https://palena.sii.cl/cgi_rtc/RTC/RTCComun.cgi (producción)
}
```

### 3. Agregar Consulta de Estado
```php
public static function consultar_estado_rcv($track_id) {
    // GET al endpoint de consulta del SII
}
```

### 4. Agregar a la UI de WordPress
```php
// templates/admin-rcv.php
<button onclick="enviarRCV()">Enviar al SII</button>
<div id="resultado-envio"></div>
```

---

## 📚 Referencias

- **SII - Libros Electrónicos:** https://www.sii.cl/servicios_online/1039-1209.html
- **Esquema XML:** LibroCV_v10.xsd
- **Plazos:** Hasta el día 13 de cada mes
- **Ambiente de Certificación:** https://maullin.sii.cl
- **Ambiente de Producción:** https://palena.sii.cl

---

## ✅ Checklist Mensual de RCV

```
Cada mes (ejemplo: para Noviembre 2024):

□ 1 Diciembre: Verificar que todas las boletas de Noviembre estén aceptadas por SII
□ 2 Diciembre: Generar RCV de Noviembre (01/11 - 30/11)
□ 2 Diciembre: Revisar totales y cantidad de documentos
□ 3 Diciembre: Enviar RCV al SII
□ 4 Diciembre: Consultar estado y confirmar ACEPTADO
□ 5 Diciembre: Guardar respaldo del XML y Track ID
```

---

## 🎯 Resumen Ejecutivo

**¿Cuándo enviar RCV?**
- ✅ **Mensualmente, hasta el día 13 del mes siguiente**
- ✅ Ejemplo: Ventas de Noviembre → Enviar hasta 13 Diciembre

**¿Qué incluye?**
- ✅ Todas las boletas y facturas electrónicas del período
- ✅ Notas de crédito y débito
- ✅ Resumen de totales por tipo de documento

**Estado actual del sistema:**
- ✅ Genera XML correctamente
- ❌ FALTA: Envío automático al SII
- ❌ FALTA: Firma electrónica
- ❌ FALTA: Consulta de estado

**Próximo paso:**
- Implementar envío al SII vía Simple API o directamente
