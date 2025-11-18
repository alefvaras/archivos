# 🚀 Quick Start: Consulta de Estado de DTEs

## ⚡ Uso Rápido

### Opción 1: Desde terminal (Más rápido)

```bash
php consultar-estado-manual.php <TU_TRACK_ID>
```

### Opción 2: Desde código PHP

```php
$resultado = Simple_DTE_Consultas::consultar_estado_envio('ABC123XYZ');
echo $resultado['estado'];
```

### Opción 3: Desde WordPress Admin

1. Ir a: **Simple DTE → Consultas**
2. Ingresar Track ID
3. Click en "Consultar"

---

## 📋 ¿Qué necesito?

Solo necesitas un **Track ID** que obtienes cuando envías un DTE al SII.

---

## ⚠️ Importante

SimpleAPI **no tiene activos** los endpoints de envío/consulta.

**Solución temporal:**
1. Genera DTE con el plugin ✅
2. Envía manualmente en www.sii.cl
3. Guarda el track_id que te da el SII
4. Consulta con el script CLI

---

## 📚 Más Información

- **Documentación completa:** `DOCUMENTACION-CONSULTA-ESTADO.md`
- **Ejemplos de código:** `ejemplo-uso-consulta.php`
- **Scripts de prueba:** `test-track-id-simple.php`

---

## 💡 Estados del SII

- **REC** = Recibido
- **EPR** = En Proceso
- **ACE** = ✅ Aceptado
- **RCH** = ❌ Rechazado
- **RPR** = ⚠️ Con Reparos

---

✅ **Todo está listo y funcionando**
