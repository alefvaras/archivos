# Resumen de Pruebas - Integración SimpleAPI

## Problema

Todos los envíos al SII son rechazados con error **LSX-00204: "extra data at end of complex element"**

## Pruebas Realizadas

### Test 1 - Manual (test-simple-dte.php)
- **Folio**: 1889
- **FchResol**: 2025-11-16, NroResol: 0
- **Con Referencias**: TpoDocRef='SET', RazonRef='CASO-1'
- **Track ID**: 25813090
- **Resultado**: ❌ RSC - Error LSX-00204

### Test 2 - SimpleAPI 3-step (test-simple-dte-v2.php)
- **Folio**: 1890
- **Flujo**: DTE → EnvioBoleta → Enviar al SII
- **Con Referencias**: TpoDocRef='SET'
- **Track ID**: 25813112
- **Resultado**: ❌ RSC - Error LSX-00204

### Test 3 - Fechas Correctas (test-simple-dte-v3.php)
- **Folio**: 1891
- **FchEmis**: 2025-11-19 (corregido)
- **FchResol**: 2014-08-22, NroResol: 80
- **Con Referencias**: TpoDocRef='SET'
- **Track ID**: 25814339
- **Resultado**: ❌ RSC - Error LSX-00204

### Test 4a - Sin Referencias (test-simple-dte-v4.php - primera versión)
- **Folio**: 1892
- **FchResol**: 2025-11-18, NroResol: 0
- **Sin Referencias**: Se eliminó elemento completo
- **Track ID**: 25814345
- **Resultado**: ❌ RSC - Error LSX-00204

### Test 4b - Sin Referencias (segunda versión)
- **Folio**: 1893
- **FchResol**: 2025-11-18, NroResol: 0
- **Sin Referencias**: Eliminado
- **Track ID**: 25814348
- **Resultado**: ❌ RSC - Error LSX-00204

### Test 4c - Valores Oficiales Certificación ⭐
- **Folio**: 1894
- **FchResol**: 2020-10-28, NroResol: 74 (Resolución 74/2020 - VALORES OFICIALES SII)
- **Sin Referencias**: Eliminado
- **Track ID**: 25814353
- **Resultado**: ❌ RSC - Error LSX-00204

## Análisis del Error

### Error LSX-00204
- **Descripción**: "extra data at end of complex element"
- **Causa**: El SII detecta datos adicionales al final de un elemento complejo donde no deberían estar
- **Parser**: Oracle XML Parser usado por el SII

### Hallazgos

1. **Referencias con TpoDocRef vacío**: SimpleAPI genera `<TpoDocRef />` en lugar de `<TpoDocRef>SET</TpoDocRef>`, causando error de schema

2. **Orden de elementos Carátula**: CORRECTO según schema EnvioBOLETA_v11.xsd
   - RutEmisor → RutEnvia → RutReceptor → FchResol → NroResol → TmstFirmaEnv → SubTotDTE

3. **Estructura SetDTE**: CORRECTA según schema
   - SetDTE contiene Caratula y DTEs
   - Signature del SetDTE está fuera (correcto según schema)

4. **Valores de Certificación**: Según documentación oficial SII
   - **FchResol**: 2020-10-28 (Resolución 74/2020)
   - **NroResol**: 74

5. **El problema persiste** independientemente de:
   - Presencia/ausencia de Referencias
   - Fecha de resolución utilizada
   - Número de resolución utilizado

## Configuración Utilizada

```php
define('API_KEY', '9794-N370-6392-6913-8052');
define('CERT_PATH', '16694181-4.pfx');
define('CERT_PASSWORD', '5605');
define('CERT_RUT', '16694181-4');
define('CAF_PATH', 'FoliosSII7827422539120251191419.xml');
define('RUT_EMISOR', '78274225-6');
define('RAZON_SOCIAL', 'AKIBARA SPA');
```

### Folios Usados
- Rango CAF: 1889 - 1988
- Usados: 1889, 1890, 1891, 1892, 1893, 1894
- Siguiente disponible: 1895

## Endpoints SimpleAPI

1. **Generar DTE**: POST /api/v1/dte/generar
2. **Generar Sobre**: POST /api/v1/envio/generar
3. **Enviar al SII**: POST /api/v1/envio/enviar
4. **Consultar Estado**: POST /api/v1/consulta/envio

## Archivos Generados

- `/tmp/boleta_v4.xml` - DTE individual generado
- `/tmp/sobre_v4.xml` - EnvioBoleta con firma
- `/tmp/track_id_v4.txt` - Track ID del último envío

## Conclusión

El error LSX-00204 persiste en todas las variaciones probadas. El XML generado por SimpleAPI parece tener un problema estructural que causa rechazo del SII independientemente de los parámetros enviados.

## Próximos Pasos

1. **Contactar Soporte SimpleAPI**:
   - Email: contacto@simpleapi.cl
   - Teléfono: +56 9 5500 5611
   - Información a proporcionar:
     - Track IDs de los envíos fallidos
     - XMLs generados (/tmp/sobre_v4.xml)
     - Error específico: LSX-00204

2. **Validar XML contra Schema Oficial**:
   - Schema: EnvioBOLETA_v11.xsd
   - Usar validador XML externo
   - Comparar con ejemplos exitosos

3. **Verificar Certificado y CAF**:
   - Confirmar que el certificado esté activo
   - Verificar fechas del CAF
   - Confirmar permisos en ambiente de certificación

## Referencias

- [Instructivo Técnico Boleta Electrónica - SII](https://www.sii.cl/factura_electronica/factura_mercado/Instructivo_Emision_Boleta_Elect.pdf)
- [Schema EnvioBOLETA_v11.xsd](https://github.com/niclabs/DTE/blob/master/schemas/EnvioBOLETA_v11.xsd)
- [SimpleAPI Documentación](https://www.simpleapi.cl/Documentacion)
- [Resolución 74/2020 SII](https://www.sii.cl)

---

**Fecha**: 2025-11-23
**Versión Final Script**: test-simple-dte-v4.php
**Estado**: Pendiente resolución con soporte técnico SimpleAPI
