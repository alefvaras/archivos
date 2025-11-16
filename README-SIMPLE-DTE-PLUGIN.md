# Simple DTE - Plugin WordPress/WooCommerce

Plugin completo de integración con Simple API para emisión de Boletas Electrónicas, Notas de Crédito y gestión de documentos tributarios electrónicos del SII Chile.

## 🚀 Características

### Funcionalidades Implementadas

✅ **Emisión de Boletas Electrónicas (Tipo 39)**
- Generación automática desde órdenes de WooCommerce
- Soporte para set de pruebas SII (CASO-1 al CASO-5)
- Asignación automática de folios
- Almacenamiento de XML generado

✅ **Notas de Crédito Electrónicas (Tipo 61)**
- Generación desde órdenes existentes
- 3 tipos de notas: Anulación, Corrección de texto, Corrección de montos
- Referencias automáticas al documento original

✅ **Envío de Sobres al SII**
- Construcción de EnvioBoleta y EnvioDTE
- Envío automatizado a través de Simple API
- Track ID para seguimiento

✅ **Consultas**
- Consulta de estado de envíos (por Track ID)
- Consulta de DTEs específicos (por tipo y folio)
- Consulta de folios disponibles en tiempo real

✅ **RCV (Registro de Compras y Ventas)**
- Generación de libro de ventas
- Exportación en formato XML
- Filtrado por rango de fechas

✅ **RVD (Registro de Ventas Diarias)**
- Solo disponible en ambiente de certificación
- Generación diaria de consumo de folios
- Envío automático programado (23:00 hrs)
- Historial de envíos con Track IDs
- Exportación XML en formato ConsumoFolios

✅ **Gestión de Folios**
- Carga de archivos CAF (XML)
- Control de folios disponibles/usados
- Alertas de folios bajos
- Soporte multi-CAF

✅ **Administración**
- Panel de configuración completo
- Metabox en órdenes de WooCommerce
- Columna de DTE en lista de órdenes
- Sistema de logs con niveles (DEBUG, INFO, WARNING, ERROR)

## 📋 Requisitos

- WordPress 5.8+
- WooCommerce 5.0+
- PHP 7.4+
- Certificado Digital (.pfx o .p12)
- Archivos CAF del SII
- API Key de Simple API

## 🔧 Instalación

### 1. Subir el Plugin

```bash
# Opción A: Subir como carpeta
wp-content/plugins/simple-dte-plugin/

# Opción B: Comprimir y subir
zip -r simple-dte-plugin.zip simple-dte-plugin/
# Subir desde WordPress admin: Plugins > Añadir nuevo > Subir plugin
```

### 2. Activar el Plugin

- Ir a Plugins > Plugins instalados
- Buscar "Simple DTE"
- Hacer clic en "Activar"

### 3. Configurar API Key

**API Key de Pruebas:**
```
9794-N370-6392-6913-8052
```

- Ir a WooCommerce > Simple DTE
- En "API Key" pegar: `9794-N370-6392-6913-8052`
- Ambiente: Seleccionar **Certificación/Pruebas**
- Activar "Modo Debug" para ver logs detallados

### 4. Configurar Datos del Emisor

Completar todos los campos requeridos:

```
RUT Emisor: 78274225-6
Razón Social: AKIBARA SPA
Giro: Comercio minorista de coleccionables
Dirección: BARTOLO SOTO 3700 DP 1402 PISO 14
Comuna: San Miguel
```

### 5. Cargar Certificado Digital

El certificado ya está disponible en el repositorio:

```
Archivo: 16694181-4.pfx
RUT: 16694181-4
Contraseña: 5605
```

Pasos:
1. En la sección "Certificado Digital"
2. RUT del Certificado: `16694181-4`
3. Contraseña: `5605`
4. Subir archivo: Seleccionar `16694181-4.pfx`

### 6. Cargar Archivos CAF

El archivo CAF para boletas ya está disponible:

```
Archivo: FoliosSII7827422539120251191419.xml
Tipo DTE: 39 (Boleta Electrónica)
Rango de Folios: 1889 - 1988 (100 folios)
```

Pasos:
1. Ir a WooCommerce > Simple DTE
2. Sección "Cargar nuevo CAF"
3. Tipo de DTE: **39 - Boleta Electrónica**
4. Seleccionar archivo: `FoliosSII7827422539120251191419.xml`
5. Clic en "Subir CAF"

## 🎯 Uso del Plugin

### Generar Boleta Electrónica

#### Desde una Orden:

1. Ir a WooCommerce > Pedidos
2. Abrir cualquier orden
3. En el sidebar derecho ver metabox "Simple DTE"
4. Opcional: Seleccionar caso de prueba (CASO-1 al CASO-5)
5. Clic en "Generar Boleta Electrónica"

#### Con Set de Pruebas SII:

Para certificación, usar los casos de prueba:

**CASO-1**: Servicios automotrices
- Cambio de aceite: 1 x $19,900
- Alineación y balanceo: 1 x $9,900

**CASO-2**: Papelería
- Papel de regalo: 17 x $120

**CASO-3**: Alimentos
- Sandwich: 2 x $1,500
- Bebida: 2 x $550

**CASO-4**: Mixto (afecto + exento)
- Item afecto: 8 x $1,590
- Item exento: 2 x $1,000

**CASO-5**: Con unidad de medida especial
- Arroz: 5 x $700 (en Kg)

### Generar Nota de Crédito

1. Abrir una orden que YA tiene boleta generada
2. En metabox "Simple DTE"
3. Seleccionar tipo de nota:
   - **1 - Anulación**: Anula el documento completamente
   - **2 - Corregir texto**: Corrige información textual
   - **3 - Corregir montos**: Corrige montos del documento
4. Clic en "Generar Nota de Crédito"

### Consultar Estado de Envío

1. Ir a WooCommerce > Consultas DTE
2. Ingresar Track ID del envío
3. Clic en "Consultar"
4. Ver estado y glosa del SII

### Consultar DTE Específico

1. Ir a WooCommerce > Consultas DTE
2. Seleccionar Tipo DTE (39, 61, etc.)
3. Ingresar Folio
4. Clic en "Consultar"
5. Verificar si existe en el SII

### Generar RCV (Libro de Ventas)

1. Ir a WooCommerce > RCV
2. Seleccionar "Fecha Desde"
3. Seleccionar "Fecha Hasta"
4. Clic en "Generar RCV"
5. Se descargará archivo XML automáticamente

### RVD - Registro de Ventas Diarias (Solo Certificación)

El RVD es un reporte diario obligatorio que debe enviarse al SII con las boletas emitidas en el día.

**IMPORTANTE**: Solo disponible en ambiente de Certificación/Pruebas

#### Generar y Enviar RVD Manual:

1. Ir a WooCommerce > RVD Diario
2. Seleccionar fecha (generalmente el día anterior)
3. Clic en "Generar RVD"
4. Revisar el XML generado y cantidad de boletas
5. Clic en "Enviar RVD al SII"
6. Verificar Track ID del envío

#### Configurar Envío Automático:

1. En la misma página RVD
2. Activar "Enviar RVD automáticamente todos los días a las 23:00"
3. Guardar configuración
4. El sistema enviará automáticamente el RVD del día anterior cada noche

**Nota**: El RVD incluye todas las boletas electrónicas (tipos 39 y 41) emitidas en el día seleccionado.

## 📁 Estructura del Plugin

```
simple-dte-plugin/
├── simple-dte-plugin.php          # Archivo principal
├── includes/
│   ├── class-simple-dte-logger.php
│   ├── class-simple-dte-helpers.php
│   ├── class-simple-dte-api-client.php
│   ├── class-simple-dte-boleta-generator.php
│   ├── class-simple-dte-nota-credito-generator.php
│   ├── class-simple-dte-sobre-generator.php
│   ├── class-simple-dte-consultas.php
│   ├── class-simple-dte-rcv.php
│   ├── class-simple-dte-rvd.php
│   └── admin/
│       ├── class-simple-dte-admin.php
│       ├── class-simple-dte-settings.php
│       └── class-simple-dte-metabox.php
├── templates/
│   ├── admin-main.php
│   ├── admin-consultas.php
│   ├── admin-rcv.php
│   └── admin-rvd.php
├── assets/
│   ├── css/
│   │   └── admin.css
│   └── js/
│       └── admin.js
└── README.md
```

## 🔐 Seguridad

### Medidas Implementadas:

✅ Validación de nonces en todas las peticiones AJAX
✅ Verificación de permisos (`manage_woocommerce`)
✅ Sanitización de inputs
✅ Archivos protegidos (.htaccess, index.php)
✅ Permisos 0600 para certificados y CAFs
✅ Prevención de acceso directo a archivos PHP
✅ Validación de uploads (tipo, tamaño)

## 🗄️ Base de Datos

### Tablas Creadas:

#### wp_simple_dte_logs
```sql
- id (bigint, auto_increment)
- fecha_hora (datetime)
- nivel (varchar 20) - DEBUG|INFO|WARNING|ERROR
- mensaje (text)
- contexto (longtext) - JSON
- order_id (bigint, nullable)
```

#### wp_simple_dte_folios
```sql
- id (bigint, auto_increment)
- tipo_dte (int) - 39, 61, etc.
- folio_desde (int)
- folio_hasta (int)
- folio_actual (int)
- fecha_carga (datetime)
- archivo_caf (text) - ruta al archivo
- estado (varchar 20) - activo|agotado
```

### Metadatos de Órdenes:

- `_simple_dte_generada`: yes|no
- `_simple_dte_folio`: Número de folio
- `_simple_dte_tipo`: Tipo de DTE (39, 61, etc.)
- `_simple_dte_fecha_generacion`: Fecha/hora
- `_simple_dte_xml`: XML del documento
- `_simple_dte_nc_generada`: yes|no (Nota de Crédito)
- `_simple_dte_nc_folio`: Folio de N/C

## 🔧 Endpoints de Simple API Utilizados

### Generar DTE
```
POST /api/v1/dte/generar
Headers: Authorization: {API_KEY}
Body: multipart/form-data
  - input (JSON)
  - files (certificado.pfx)
  - files2 (caf.xml)
```

### Enviar Sobre al SII
```
POST /api/v1/dte/enviar
Headers: Authorization: {API_KEY}
Body: multipart/form-data
  - files (certificado.pfx)
  - files2 (sobre.xml)
```

### Consultar Estado
```
GET /api/v1/dte/estado/{track_id}
Headers: Authorization: {API_KEY}
```

### Consultar DTE
```
GET /api/v1/dte/consulta/{tipo_dte}/{folio}/{rut_emisor}
Headers: Authorization: {API_KEY}
```

## 📊 Rate Limits de Simple API

- **API DTE**: 3 req/seg, 40 req/min
- **APIs auxiliares**: 1 req/seg, 5 req/min, 100 req/hora

## 🐛 Debugging

### Ver Logs:

1. Activar "Modo Debug" en configuración
2. Ver en servidor: `wp-content/debug.log`
3. Ver en base de datos:
```sql
SELECT * FROM wp_simple_dte_logs
ORDER BY fecha_hora DESC
LIMIT 100;
```

### Logs por Orden:

```php
$logs = Simple_DTE_Logger::get_order_logs($order_id);
```

### Limpiar Logs Antiguos:

```php
Simple_DTE_Logger::clean_old_logs(30); // Elimina logs > 30 días
```

## ❓ Troubleshooting

### Error: "Certificado no encontrado"
- Verificar que se subió el archivo .pfx
- Revisar permisos del directorio uploads
- Verificar que la ruta está en opciones

### Error: "No hay CAF activo"
- Cargar archivo CAF para el tipo de DTE
- Verificar que los folios no estén agotados
- Revisar tabla wp_simple_dte_folios

### Error: "API Key no configurada"
- Ir a configuración y pegar la API Key
- Formato: 9794-N370-6392-6913-8052

### Error: "Folios agotados"
- Solicitar nuevo CAF al SII
- Cargar nuevo CAF en el plugin

## 📞 Soporte

- **Simple API**: [www.simpleapi.cl](https://www.simpleapi.cl)
- **Documentación SII**: [www.sii.cl](https://www.sii.cl/factura_electronica/)
- **Postman Collection**: Incluida en el repositorio

## 📝 Licencia

GPL v2 or later

## ✅ Checklist de Certificación SII

- [ ] Configurar datos del emisor
- [ ] Cargar certificado digital
- [ ] Cargar CAF para boletas (tipo 39)
- [ ] Cargar CAF para notas de crédito (tipo 61)
- [ ] Generar CASO-1
- [ ] Generar CASO-2
- [ ] Generar CASO-3
- [ ] Generar CASO-4
- [ ] Generar CASO-5
- [ ] Enviar sobre con los 5 casos al SII
- [ ] Verificar estados de envío
- [ ] Generar RCV del periodo
- [ ] Enviar RCV al SII
- [ ] Generar y enviar RVD diario
- [ ] Configurar envío automático de RVD

## 🎉 ¡Listo!

El plugin está completamente configurado y listo para usar en ambiente de **Certificación/Pruebas**.

**IMPORTANTE**: NO cambiar a Producción hasta completar la certificación SII.

---

**Versión**: 1.0.0
**Autor**: Tu Nombre
**Última actualización**: 2025-11-16
