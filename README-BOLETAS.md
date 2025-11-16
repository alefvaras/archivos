# Sistema de Generación de Boletas Electrónicas

Sistema automatizado para generar, enviar y gestionar Boletas Electrónicas (DTE tipo 39) usando Simple API y el SII de Chile.

## Características

- ✅ Generación automática de boletas electrónicas
- ✅ Envío automático al SII
- ✅ Consulta automática de estado
- ✅ Envío automático por email (opcional)
- ✅ Generación de PDF estilo SII **con Timbre PDF417** (requisito oficial SII)
- ✅ Control automático de folios (archivo o base de datos)
- ✅ Guardado de XMLs generados
- ✅ Configuración flexible
- ✅ Compatible con Hostinger
- ✅ **Base de datos robusta** para gestión escalable
- ✅ **Sistema de logging estructurado** para auditoría
- ✅ Cumplimiento 100% con especificaciones SII

## Archivos del Sistema

### Scripts Principales
- `generar-boleta.php` - Script principal del sistema
- `ejemplo-uso-boletas.php` - Ejemplos de uso interactivos
- `ejemplo-integracion-completa.php` - Ejemplo completo con BD + Logging + PDF417
- `migrar-a-bd.php` - Migración de datos desde archivos a base de datos
- `gestor-cafs.php` - Gestor de archivos CAF (cambiar entre múltiples CAFs)
- `woocommerce-boletas-electronicas.php` - Plugin WooCommerce (e-commerce)

### Tests de Certificación SII
- `test-simple-dte.php` - Test Boleta Electrónica (DTE 39)
- `test-caso2-nota-credito.php` - Test Nota de Crédito (DTE 61)
- `test-caso3-nota-debito.php` - Test Nota de Débito (DTE 56)
- `test-caso4-factura-afecta.php` - Test Factura Afecta (DTE 33)
- `test-caso5-factura-exenta.php` - Test Factura Exenta (DTE 34)
- `test-timbre-pdf417.php` - Test de generación de Timbre PDF417
- `test-pdf-completo.php` - Test integral de PDF con timbre

### Librerías Core
- `lib/fpdf.php` - Librería FPDF para generación de PDF
- `lib/generar-pdf-boleta.php` - Generador de PDF con Timbre PDF417
- `lib/generar-timbre-pdf417.php` - Generación de código de barras PDF417
- `lib/pdf417/` - Librería PDF417 (leongrdic/php-pdf417)
- `lib/pdf417-simple-autoload.php` - Autoloader para PDF417

### Base de Datos (Opcional)
- `lib/Database.php` - Clase de conexión PDO (Singleton)
- `lib/BoletaRepository.php` - Repositorio CRUD para boletas
- `lib/DTELogger.php` - Sistema de logging estructurado
- `db/schema.sql` - Schema completo de base de datos
- `db/setup.php` - Script de instalación de BD

### Control de Folios
- `folios_usados.txt` - Control de folios (modo archivo)
- Base de datos - Control de folios (modo BD, recomendado)

## Instalación Inicial

### 1. Requisitos del Sistema

**PHP Extensiones Requeridas:**
```bash
# Para Timbre PDF417
sudo apt-get install php-bcmath php-gd php-dom

# Para Base de Datos (opcional)
sudo apt-get install php-mysql php-pdo

# Verificar extensiones
php -m | grep -E "(bcmath|gd|dom|pdo|mysql)"
```

**MySQL/MariaDB (opcional):**
- MySQL 5.7+ o MariaDB 10.3+
- Usuario con permisos CREATE DATABASE

### 2. Configurar Base de Datos (Opcional - Recomendado)

La base de datos es **opcional** pero **altamente recomendada** para producción, ya que proporciona:
- ✅ Control robusto de folios con transacciones
- ✅ Gestión escalable de clientes y boletas
- ✅ Auditoría completa con logs
- ✅ Consultas y reportes avanzados

**Configurar variables de entorno:**
```bash
export DB_HOST=localhost
export DB_PORT=3306
export DB_NAME=boletas_electronicas
export DB_USER=root
export DB_PASS=tu_password_seguro
```

**Ejecutar instalación automática:**
```bash
php db/setup.php
```

El script creará:
- Base de datos `boletas_electronicas`
- 6 tablas (clientes, cafs, folios_usados, boletas, boleta_items, logs)
- 3 vistas útiles
- Índices optimizados
- Cliente genérico "66666666-6"

**Verificar instalación:**
```bash
mysql -u root -p boletas_electronicas -e "SHOW TABLES;"
```

Deberías ver:
```
+-------------------------------+
| Tables_in_boletas_electronicas|
+-------------------------------+
| boleta_items                  |
| boletas                       |
| cafs                          |
| clientes                      |
| folios_usados                 |
| logs                          |
+-------------------------------+
```

### 3. Validar Instalación de Componentes

**Test Timbre PDF417:**
```bash
php test-timbre-pdf417.php
```

Salida esperada:
```
✓ Todas las extensiones están disponibles
✓ TED extraído correctamente
✓ PDF417 generado exitosamente
✓ Imagen PNG válida
```

**Test PDF Completo:**
```bash
php test-pdf-completo.php
```

Salida esperada:
```
✓ PDF generado correctamente
✓ Timbre PDF417 integrado
✓ PDF válido y completo
Tamaño: 8,939 bytes (incluye barcode)
```

### 4. Modo de Operación

El sistema puede operar en **dos modos**:

**Modo Archivo (por defecto):**
- Control de folios en `folios_usados.txt`
- Sin dependencias de BD
- Ideal para desarrollo y pruebas

**Modo Base de Datos (recomendado producción):**
- Control de folios en tabla `folios_usados`
- Gestión completa de clientes y boletas
- Logging en base de datos
- Requiere configuración de variables de entorno

Para cambiar de modo, simplemente configura las variables de entorno y modifica tu código para usar `BoletaRepository`:

```php
// Modo BD
require_once 'lib/BoletaRepository.php';
$repo = new BoletaRepository();
$folio_info = $repo->obtenerProximoFolio(39); // Tipo DTE 39 = Boleta
```

## Migración y Ejemplo de Integración Completa

### Migración de Datos a Base de Datos

Si ya tienes datos en archivos (CAFs, folios_usados.txt, XMLs) y quieres migrarlos a la base de datos:

```bash
php migrar-a-bd.php
```

**El script migra automáticamente:**
1. ✅ **Archivos CAF** → tabla `cafs`
   - Detecta archivos `.xml` que contengan `FoliosSII`
   - Extrae rangos de folios y fechas de autorización
   - Verifica duplicados antes de insertar

2. ✅ **folios_usados.txt** → tabla `folios_usados`
   - Migra todos los folios desde el inicio del CAF hasta el último usado
   - Crea respaldo automático: `folios_usados.txt.backup.YYYYMMDD_HHMMSS`
   - Mantiene el archivo original intacto

3. ✅ **XMLs generados** → tabla `boletas` (opcional, interactivo)
   - Busca archivos `boleta_*.xml` en `/tmp`
   - Extrae datos completos del DTE
   - Pregunta confirmación antes de migrar

**Requisitos previos:**
- Base de datos instalada (`php db/setup.php`)
- Variables de entorno configuradas

**Salida esperada:**
```
=== MIGRACIÓN A BASE DE DATOS ===

📋 Verificando requisitos...
  ✓ Variables de entorno configuradas
  ✓ Conexión a base de datos exitosa

📦 Paso 1: Migrando archivos CAF...
  Procesando: FoliosSII78274225391889202511161321.xml
    ✓ Migrado: DTE 39, Folios 1889-2088 (ID: 1)

  Resumen:
    Migrados: 1
    Saltados: 0

🔢 Paso 2: Migrando folios usados...
  Último folio en archivo: 1890
  CAF encontrado: Folios 1889-2088
  Migrando folios 1889 a 1890...
  ✓ Folios migrados: 2
  ✓ Backup creado: folios_usados.txt.backup.20251116_213045

📄 Paso 3: Buscando XMLs de boletas...
  Encontrados: 5 archivos XML
  ¿Deseas migrar estos XMLs a la BD? (s/n): s
    ✓ Migrado: Folio 1889 (ID: 1)
    ✓ Migrado: Folio 1890 (ID: 2)
  ✓ XMLs migrados: 2

🔍 Paso 4: Verificando migración...
  Registros en base de datos:
    CAFs: 1
    Folios usados: 2
    Boletas: 2
    Clientes: 1

  Folios disponibles por CAF:
    Boleta Electrónica: 198 disponibles de 200

=== MIGRACIÓN COMPLETADA ===
```

**Importante:**
- Los archivos originales **NO se eliminan**, solo se crea backup
- Puedes seguir usando modo archivo si prefieres
- La migración es **idempotente** (puedes ejecutarla múltiples veces)

### Ejemplo de Integración Completa

Para ver cómo funciona el sistema completo con **todas las características integradas**:

```bash
php ejemplo-integracion-completa.php
```

**Este script demuestra:**
1. ✅ Auto-detección de base de datos (usa BD si está disponible, sino modo archivo)
2. ✅ Inicialización del sistema de logging estructurado
3. ✅ Obtención de folios desde BD o archivo (con fallback)
4. ✅ Generación de boleta con Simple API
5. ✅ Guardado en base de datos (si está disponible)
6. ✅ Envío al SII y consulta de estado
7. ✅ Generación de PDF con Timbre PDF417
8. ✅ Envío por email (si está configurado)
9. ✅ Estadísticas y reportes

**Salida esperada (con BD):**
```
=== EJEMPLO DE INTEGRACIÓN COMPLETA ===

Paso 1: Configuración
  ✓ Modo: Base de Datos
  ✓ Logging: Habilitado

Paso 2: Inicialización de componentes
  ✓ Logger inicializado: logs/dte_2025-11-16.log
  ✓ Repositorio BD inicializado

Paso 3: Obtener próximo folio
  ✓ Folio obtenido desde BD: 1891
  ℹ️  CAF: Folios 1889-2088 (197 restantes)

Paso 4: Generar boleta
  ✓ Boleta generada: Folio 1891
  ✓ Total: $29,800

Paso 5: Guardar en base de datos
  ✓ Boleta guardada: ID 3

Paso 6: Enviar al SII
  ✓ Enviado al SII: Track ID 25790877

Paso 7: Consultar estado SII
  ✓ Estado: EPR (Envío Procesado)
  ✓ Aceptados: 1

Paso 8: Generar PDF con Timbre PDF417
  ✓ PDF generado: /tmp/boleta_1891.pdf (8,939 bytes)
  ✓ Incluye código PDF417

Paso 9: Estadísticas
  📊 Resumen del día:
     Boletas: 3
     Total facturado: $89,400
     Estado SII: 3 aceptadas

✅ INTEGRACIÓN COMPLETA EXITOSA
```

**Salida esperada (sin BD, modo archivo):**
```
=== EJEMPLO DE INTEGRACIÓN COMPLETA ===

Paso 1: Configuración
  ⚠️  Modo: Archivo (BD no disponible)
  ✓ Logging: Habilitado (solo archivos)

Paso 3: Obtener próximo folio
  ✓ Folio obtenido desde archivo: 1892
  ℹ️  CAF: Folios 1889-2088 (196 restantes)

Paso 4: Generar boleta
  ✓ Boleta generada: Folio 1892
  ...
```

**Beneficios del script de ejemplo:**
- Ver todas las capacidades del sistema en acción
- Entender el flujo completo de una boleta
- Base para tu propia implementación
- Debugging de configuración

## Configuración

### Configuración Principal

Editar las constantes en `generar-boleta.php`:

```php
define('API_KEY', 'tu-api-key-aqui');
define('CERT_PATH', __DIR__ . '/16694181-4.pfx');
define('CERT_PASSWORD', 'tu-password');
define('CAF_PATH', __DIR__ . '/FoliosSII78274225391889202511161321.xml');
define('RUT_EMISOR', '78274225-6');
define('RAZON_SOCIAL', 'AKIBARA SPA');
define('AMBIENTE', 'certificacion'); // certificacion o produccion
```

### Opciones Configurables

```php
$CONFIG = [
    'envio_automatico_email' => false,  // true = enviar email al cliente
    'consulta_automatica' => true,      // true = consultar estado automáticamente
    'espera_consulta_segundos' => 5,    // Tiempo de espera antes de consultar
    'guardar_xml' => true,               // Guardar XMLs generados
    'directorio_xml' => '/tmp',          // Directorio para XMLs
    'email_remitente' => 'boletas@akibara.cl',
    'adjuntar_pdf' => true,              // true = adjuntar PDF de la boleta
    'adjuntar_xml' => false,             // true = adjuntar XML de la boleta
];
```

## Uso Básico

### 1. Boleta Simple (Sin Email)

```php
<?php
require_once 'generar-boleta.php';

// Configuración
$CONFIG['envio_automatico_email'] = false;
$CONFIG['consulta_automatica'] = true;

// Datos del cliente
$cliente = [
    'rut' => '12345678-9',
    'razon_social' => 'Juan Pérez',
    'email' => 'cliente@ejemplo.cl'
];

// Items de la boleta
$items = [
    [
        'nombre' => 'Producto 1',
        'cantidad' => 2,
        'precio' => 10000
    ]
];

// Generar boleta
$resultado = generar_boleta($cliente, $items, $CONFIG, $API_BASE);

echo "Folio: {$resultado['folio']}\n";
echo "Track ID: {$resultado['track_id']}\n";
```

### 2. Boleta con Envío Automático por Email

```php
<?php
require_once 'generar-boleta.php';

// Habilitar envío automático
$CONFIG['envio_automatico_email'] = true;
$CONFIG['consulta_automatica'] = true;

$cliente = [
    'rut' => '12345678-9',
    'razon_social' => 'Cliente Ejemplo',
    'email' => 'cliente@ejemplo.cl',  // Email requerido
    'direccion' => 'Av. Providencia 123',
    'comuna' => 'Providencia'
];

$items = [
    [
        'nombre' => 'Servicio de consultoría',
        'descripcion' => 'Consultoría técnica',
        'cantidad' => 2,
        'precio' => 25000,
        'unidad' => 'hrs'
    ]
];

$resultado = generar_boleta($cliente, $items, $CONFIG, $API_BASE);
```

### 3. Boleta sin Consulta Automática

```php
<?php
require_once 'generar-boleta.php';

// Deshabilitar consulta automática
$CONFIG['envio_automatico_email'] = false;
$CONFIG['consulta_automatica'] = false;

$cliente = [
    'rut' => '66666666-6',
    'razon_social' => 'Cliente Final'
];

$items = [
    [
        'nombre' => 'Venta',
        'cantidad' => 1,
        'precio' => 15000
    ]
];

// Generar boleta
$resultado = generar_boleta($cliente, $items, $CONFIG, $API_BASE);

// Consultar estado manualmente después
sleep(10);
$estado = consultar_estado($resultado['track_id'], $API_BASE);
print_r($estado);
```

## Desde Línea de Comandos

### Generar boleta con ejemplo por defecto:

```bash
php generar-boleta.php
```

### Ejecutar ejemplos interactivos:

```bash
php ejemplo-uso-boletas.php
```

Luego seleccionar la opción deseada:
1. Boleta simple sin envío automático
2. Boleta con envío automático por email
3. Boleta sin consulta automática
4. Configuración completa personalizada

## Estructura de Datos

### Datos del Cliente

```php
$cliente = [
    'rut' => '12345678-9',              // Obligatorio
    'razon_social' => 'Nombre Cliente', // Obligatorio
    'email' => 'cliente@ejemplo.cl',    // Opcional (requerido si envio_automatico_email = true)
    'direccion' => 'Dirección',         // Opcional
    'comuna' => 'Comuna'                // Opcional
];
```

### Items de la Boleta

```php
$items = [
    [
        'nombre' => 'Nombre del producto/servicio',  // Obligatorio
        'descripcion' => 'Descripción detallada',    // Opcional
        'cantidad' => 1,                              // Obligatorio
        'precio' => 10000,                            // Obligatorio (con IVA incluido)
        'unidad' => 'un',                             // Opcional (default: 'un')
        'descuento' => 0,                             // Opcional
        'recargo' => 0                                // Opcional
    ]
];
```

### Resultado

```php
$resultado = [
    'folio' => 1890,                    // Folio asignado
    'total' => 29800,                   // Total de la boleta
    'track_id' => 25790877,             // Track ID del SII
    'dte_xml' => '<?xml...',            // XML del DTE generado
    'estado' => [...]                   // Estado de la consulta (si consulta_automatica = true)
];
```

## Control de Folios

El sistema controla automáticamente los folios usados mediante el archivo `folios_usados.txt`. Cada vez que se genera una boleta, el folio se incrementa automáticamente.

### Advertencias Automáticas

El sistema automáticamente:
- Muestra archivos CAF disponibles al generar boletas
- Advierte cuando quedan menos de 10 folios
- Proporciona instrucciones para solicitar más folios al SII

### Gestor de CAFs

Para gestionar múltiples archivos CAF:

```bash
php gestor-cafs.php
```

**Opciones disponibles:**
1. Mostrar información detallada de un CAF
2. Cambiar CAF actual
3. Resetear contador de folios
4. Listar CAFs por fecha

El gestor permite:
- Ver todos los archivos CAF disponibles
- Ver folios restantes en cada CAF
- Cambiar entre CAFs cuando se agoten los folios
- Resetear el contador de folios

Para reiniciar el control de folios manualmente (solo en desarrollo):

```bash
rm folios_usados.txt
```

## Estados del SII

Después de enviar una boleta, el SII puede devolver los siguientes estados:

- **REC** - Recibido (aún procesando)
- **EPR** - Envío Procesado
- **RCH** - Rechazado
- **RPR** - Aceptado con Reparos

### Verificar Estado Manualmente

```php
$track_id = 25790877;
$estado = consultar_estado($track_id, $API_BASE);

if ($estado) {
    echo "Estado: {$estado['estado']}\n";

    foreach ($estado['estadistica'] as $stat) {
        echo "Aceptados: {$stat['aceptados']}\n";
        echo "Rechazados: {$stat['rechazados']}\n";
        echo "Reparos: {$stat['reparos']}\n";
    }
}
```

## Envío de Emails

El sistema soporta envío automático de emails con la boleta adjunta en formato XML.

### Configuración de Email

```php
$CONFIG['envio_automatico_email'] = true;
$CONFIG['email_remitente'] = 'boletas@akibara.cl';
```

### Integración con MailPoet

El sistema está integrado con **MailPoet** para WordPress y utiliza un sistema de fallback inteligente:

**Orden de prioridad:**
1. **MailPoet** (`mailpoet_send_transactional_email`) - Preferido
2. **wp_mail()** - Fallback para WordPress
3. **mail()** de PHP - Fallback final (sin adjuntos)

**Características del email:**
- ✅ Diseño HTML responsive
- ✅ Información completa de la boleta
- ✅ Archivo XML adjunto
- ✅ Personalizado con nombre del cliente
- ✅ Formato moneda chileno

### Template del Email

El email incluye:
- Folio de la boleta
- Fecha de emisión
- Total formateado
- Datos del emisor
- XML de la boleta adjunto

### Personalizar Email

Editar la función `enviar_email()` en `generar-boleta.php` para personalizar:
- Asunto del email
- Contenido HTML y estilos CSS
- Colores y diseño
- Información adicional

**Para desarrollo/testing:**
- Si MailPoet no está disponible, el sistema usa wp_mail()
- Si WordPress no está disponible, usa mail() (sin adjuntos)

## Generación de PDF

El sistema incluye generación automática de PDF para boletas electrónicas usando **FPDF** con **Timbre PDF417 oficial SII**.

### Características del PDF

- ✅ Formato tipo ticket (80mm de ancho)
- ✅ Diseño estilo boletas SII Chile
- ✅ Incluye todos los datos del DTE
- ✅ **Timbre Electrónico PDF417** (requisito oficial SII)
- ✅ Código de barras 2D con TED completo
- ✅ Nivel de seguridad 5 (especificación SII)
- ✅ Sin dependencias externas complejas
- ✅ Compatible con Hostinger y hosting compartido

### Timbre PDF417 (Código de Barras 2D)

El sistema genera automáticamente el **Timbre Electrónico DTE (TED)** en formato **PDF417**, que es un **requisito oficial del SII** para documentos tributarios electrónicos impresos.

**Especificaciones Técnicas:**
- Formato: PDF417 (ISO/IEC 15438:2006)
- Nivel de corrección de errores: **5** (requerido por SII)
- Contenido: TED completo del XML firmado
- Tamaño: Ajustado automáticamente al ancho del ticket (80mm)
- Librería: `leongrdic/php-pdf417` con renderer GD nativo

**El timbre incluye:**
- RUT Emisor y Receptor
- Tipo de DTE y Folio
- Fecha de emisión
- Monto total
- Primer item
- CAF (Código de Autorización de Folios)
- Timestamp del timbre
- Firma digital

**Generación automática:**
```php
// El timbre se genera automáticamente al crear el PDF
$pdf = new BoletaPDF($datos_boleta, $dte_xml);
$pdf->generarBoleta();
$pdf->Output('F', 'boleta.pdf');

// El PDF incluirá automáticamente el código PDF417
```

**Fallback inteligente:**
Si la generación del PDF417 falla, el sistema automáticamente muestra información básica del timbre (folio, fecha, RUT, monto) para que el PDF siempre se genere correctamente.

**Test del timbre:**
```bash
php test-timbre-pdf417.php   # Test de generación de PDF417
php test-pdf-completo.php    # Test de PDF completo con timbre
```

### Configuración de Adjuntos

```php
$CONFIG['adjuntar_pdf'] = true;   // Adjuntar PDF al email
$CONFIG['adjuntar_xml'] = false;  // Adjuntar XML al email
```

**Opciones disponibles:**
1. Solo PDF: `adjuntar_pdf = true, adjuntar_xml = false` (Recomendado)
2. Solo XML: `adjuntar_pdf = false, adjuntar_xml = true`
3. Ambos: `adjuntar_pdf = true, adjuntar_xml = true`
4. Ninguno: `adjuntar_pdf = false, adjuntar_xml = false` (solo email informativo)

### Librería FPDF

El sistema usa FPDF para generar PDFs sin necesidad de extensiones PHP especiales:
- Ubicación: `lib/fpdf.php`
- Generador: `lib/generar-pdf-boleta.php`
- Licencia: Libre y gratuita
- Requisitos: Solo PHP (5.6+)

**Ventajas de FPDF:**
- No requiere Composer
- No requiere extensiones especiales
- Ligero (50KB)
- Compatible con todos los hostings

### Generar PDF Manualmente

```php
require_once 'lib/generar-pdf-boleta.php';

// Generar y guardar PDF
generar_pdf_boleta($datos_boleta, $dte_xml, '/ruta/boleta.pdf');

// O generar en memoria
$pdf_string = generar_pdf_boleta($datos_boleta, $dte_xml);
```

## Base de Datos (Opcional - Recomendado)

El sistema puede operar con o sin base de datos. El uso de base de datos es **altamente recomendado para producción**.

### Ventajas de Usar Base de Datos

- ✅ **Control robusto de folios** con transacciones ACID
- ✅ **Escalabilidad** - Maneja miles de boletas sin problemas
- ✅ **Consultas avanzadas** - Reportes y estadísticas
- ✅ **Integridad referencial** - Foreign keys y constraints
- ✅ **Auditoría completa** - Logs estructurados en BD
- ✅ **Gestión de clientes** - Historial y estadísticas
- ✅ **Respaldos** - Backup automático de MySQL/MariaDB

### Tablas del Sistema

**6 Tablas principales:**
1. `clientes` - Gestión de clientes y contactos
2. `cafs` - Archivos CAF con rangos de folios
3. `folios_usados` - Control preciso de folios usados
4. `boletas` - DTEs completos con XML y estado SII
5. `boleta_items` - Detalles línea por línea
6. `logs` - Auditoría del sistema

**3 Vistas útiles:**
- `v_folios_disponibles` - Folios restantes por CAF
- `v_resumen_boletas` - Estadísticas por fecha/estado
- `v_clientes_estadisticas` - Métricas por cliente

### Uso de BoletaRepository

```php
require_once 'lib/BoletaRepository.php';

// Crear instancia
$repo = new BoletaRepository();

// Obtener próximo folio disponible
$folio_info = $repo->obtenerProximoFolio(39); // DTE tipo 39
echo "Folio: {$folio_info['folio']}\n";

// Guardar boleta completa
$boleta_id = $repo->guardarBoleta([
    'tipo_dte' => 39,
    'folio' => 1890,
    'fecha_emision' => date('Y-m-d'),
    'rut_emisor' => '78274225-6',
    'razon_social_emisor' => 'AKIBARA SPA',
    'rut_receptor' => '12345678-9',
    'razon_social_receptor' => 'Juan Pérez',
    'email_receptor' => 'cliente@ejemplo.cl',
    'monto_total' => 29800,
    'items' => [
        [
            'nombre' => 'Producto 1',
            'cantidad' => 1,
            'precio_unitario' => 25042,
            'monto_item' => 25042
        ]
    ]
], $dte_xml);

// Actualizar estado SII
$repo->actualizarEstadoSII($boleta_id, $track_id, 'EPR', $respuesta_sii);

// Marcar email enviado
$repo->marcarEmailEnviado($boleta_id);

// Marcar PDF generado
$repo->marcarPDFGenerado($boleta_id, '/ruta/al/pdf.pdf');

// Obtener estadísticas
$stats = $repo->obtenerEstadisticas(
    fecha_desde: '2025-01-01',
    fecha_hasta: '2025-12-31'
);
print_r($stats);
```

### Consultas SQL Útiles

```sql
-- Ver folios disponibles
SELECT * FROM v_folios_disponibles WHERE tipo_dte = 39;

-- Boletas del mes
SELECT COUNT(*), SUM(monto_total)
FROM boletas
WHERE MONTH(fecha_emision) = MONTH(CURRENT_DATE);

-- Top 10 clientes
SELECT c.razon_social, COUNT(b.id) as total_boletas, SUM(b.monto_total) as monto_total
FROM clientes c
JOIN boletas b ON c.id = b.cliente_id
GROUP BY c.id
ORDER BY monto_total DESC
LIMIT 10;

-- Boletas pendientes SII
SELECT * FROM boletas WHERE estado_sii IS NULL OR estado_sii = 'REC';
```

## Sistema de Logging Estructurado

El sistema incluye un logger completo para auditoría y debugging.

### Características del Logger

- ✅ **5 niveles de log** - DEBUG, INFO, WARNING, ERROR, CRITICAL
- ✅ **Logs a archivos diarios** - `logs/dte_YYYY-MM-DD.log`
- ✅ **Logs de errores separados** - `logs/errors_YYYY-MM-DD.log`
- ✅ **Logs a base de datos** (opcional) - Tabla `logs`
- ✅ **Contexto JSON** - Metadata adicional
- ✅ **Métodos especializados** - Para cada operación
- ✅ **Búsqueda y limpieza** - Utilidades integradas

### Uso del Logger

```php
require_once 'lib/DTELogger.php';

// Crear logger (archivos + BD opcional)
$logger = new DTELogger(
    log_dir: __DIR__ . '/logs',
    usar_bd: true,  // true = guardar también en BD
    niveles_activos: [
        DTELogger::NIVEL_INFO,
        DTELogger::NIVEL_WARNING,
        DTELogger::NIVEL_ERROR,
        DTELogger::NIVEL_CRITICAL
    ]
);

// Logs básicos
$logger->info('generar', 'Boleta generada exitosamente', [
    'folio' => 1890,
    'tipo_dte' => 39,
    'monto' => 29800
]);

$logger->error('enviar_sii', 'Error de conexión al SII', [
    'error_code' => 500,
    'mensaje' => 'Connection timeout'
]);

// Métodos especializados
$logger->logGenerarBoleta($folio, $tipo_dte, [
    'exito' => true
], ['monto' => 29800]);

$logger->logEnviarSII($folio, $track_id, [
    'exito' => true
], ['tiempo_respuesta' => 2.5]);

$logger->logConsultarEstado($track_id, 'EPR', [
    'aceptados' => 1
]);

$logger->logEnviarEmail($folio, 'cliente@ejemplo.cl', [
    'exito' => true
], ['metodo' => 'MailPoet']);

$logger->logGenerarPDF($folio, [
    'exito' => true
], ['tamaño' => 8939]);
```

### Formato de Log

```
[2025-11-16 21:30:45] [INFO    ] [generar       ] Boleta generada: Folio 1890 {"folio":1890,"tipo_dte":39,"monto":29800}
[2025-11-16 21:30:50] [INFO    ] [enviar_sii    ] Boleta enviada al SII: Track ID 25790877 {"track_id":25790877}
[2025-11-16 21:30:55] [ERROR   ] [enviar_email  ] Error enviando email: SMTP no disponible {"folio":1890}
```

### Utilidades del Logger

```php
// Ver últimos 100 logs de hoy
$ultimos = $logger->obtenerUltimosLogs(100);
foreach ($ultimos as $linea) {
    echo $linea . "\n";
}

// Buscar en logs
$errores = $logger->buscarEnLogs('ERROR', '2025-11-16');
foreach ($errores as $linea) {
    echo $linea . "\n";
}

// Limpiar logs antiguos (más de 30 días)
$eliminados = $logger->limpiarLogsAntiguos(30);
echo "Logs eliminados: {$eliminados}\n";
```

### Ver Logs en Tiempo Real

```bash
# Ver logs de hoy en tiempo real
tail -f logs/dte_$(date +%Y-%m-%d).log

# Ver solo errores
tail -f logs/errors_$(date +%Y-%m-%d).log

# Buscar boletas generadas hoy
grep "Boleta generada" logs/dte_$(date +%Y-%m-%d).log

# Contar errores del día
grep -c "ERROR" logs/dte_$(date +%Y-%m-%d).log
```

## Integración con WooCommerce (E-commerce)

El sistema incluye un **plugin completo de WooCommerce** que genera boletas electrónicas automáticamente al completar órdenes de compra.

### ¿Qué es WooCommerce?

**WooCommerce** es el plugin de e-commerce (tienda online) más popular para WordPress. Permite vender productos/servicios por internet con carrito de compras, pasarelas de pago, gestión de inventario, etc.

### Características del Plugin

- ✅ **Generación automática** de boletas al completar órdenes
- ✅ **Campo RUT en checkout** con validación de dígito verificador
- ✅ **Envío automático por email** con PDF adjunto al cliente
- ✅ **Descarga de PDF** desde "Mi cuenta" del cliente
- ✅ **Metabox en admin** mostrando folio, track ID y estado SII
- ✅ **Columna de boleta** en lista de órdenes
- ✅ **Generación manual** desde panel de orden (si falla automática)
- ✅ **Compatible con campo RUT existente** (detecta automáticamente)
- ✅ **Integración completa** con logging y base de datos

### Flujo Automático

```
1. Cliente compra en tu tienda WooCommerce
   ↓
2. Cliente ingresa RUT en checkout
   ↓
3. Orden se marca como "Completada"
   ↓
4. 🔥 Plugin genera boleta automáticamente
   ↓
5. Boleta se envía al SII
   ↓
6. Se genera PDF con Timbre PDF417
   ↓
7. Cliente recibe email con PDF adjunto
   ↓
8. Cliente puede descargar desde "Mi cuenta"
```

### Instalación del Plugin

**Archivo del plugin:** `woocommerce-boletas-electronicas.php`

**Requisitos:**
- WordPress 5.8+
- WooCommerce 6.0+
- PHP 8.0+
- Sistema de boletas ya configurado

**Opción 1: Instalación Manual (Desarrollo)**

```bash
# El plugin debe estar en el mismo directorio que generar-boleta.php
# Crear enlace simbólico en WordPress
ln -s /ruta/a/tu/sistema-boletas /var/www/html/wp-content/plugins/woocommerce-boletas-electronicas

# Activar desde WordPress Admin → Plugins
```

**Opción 2: Instalación como ZIP (Producción)**

```bash
# Crear archivo ZIP
zip -r woocommerce-boletas-electronicas.zip \
  woocommerce-boletas-electronicas.php \
  generar-boleta.php \
  lib/ \
  db/ \
  README-BOLETAS.md

# Subir ZIP en WordPress Admin → Plugins → Añadir nuevo → Subir plugin
```

### Configuración del Plugin

**1. En wp-config.php agregar variables de entorno:**

```php
// Base de datos (opcional pero recomendado)
putenv('DB_NAME=boletas_electronicas');
putenv('DB_USER=root');
putenv('DB_PASS=tu_password');
```

**2. Verificar configuración en generar-boleta.php:**

```php
define('API_KEY', 'tu-api-key-simple-api');
define('CERT_PATH', '/ruta/certificado.pfx');
define('CERT_PASSWORD', 'password-certificado');
define('CAF_PATH', '/ruta/FoliosSII.xml');
define('RUT_EMISOR', '12345678-9');
define('RAZON_SOCIAL', 'MI EMPRESA SPA');
define('AMBIENTE', 'certificacion'); // o 'produccion'
```

**3. Activar plugin en WordPress Admin → Plugins**

### Uso del Plugin

#### Para el Cliente:

1. **Checkout:**
   - Ingresa datos personales
   - Ingresa RUT (campo obligatorio con validación)
   - Completa pago

2. **Email:**
   - Recibe confirmación de orden
   - PDF de boleta electrónica adjunto

3. **Mi cuenta:**
   - Ver órdenes
   - Descargar PDF de boleta

#### Para el Administrador:

1. **Ver lista de órdenes:**
   - Columna "Boleta" muestra folio
   - Si no tiene boleta muestra "—"

2. **Ver detalles de orden:**
   - Metabox "Boleta Electrónica SII" muestra:
     - Folio
     - Track ID
     - Estado SII
     - Fecha de generación
     - Botón "Descargar PDF"

3. **Generar manualmente:**
   - Acciones de orden → "Generar Boleta Electrónica"
   - Útil si generación automática falló

### Datos Extraídos de WooCommerce

El plugin automáticamente extrae:

```php
// Del cliente
'rut' => campo _billing_rut (del checkout)
'razon_social' => billing_first_name + billing_last_name
'email' => billing_email
'direccion' => billing_address_1
'comuna' => billing_city

// De los items
'nombre' => nombre del producto
'descripcion' => descripción corta del producto
'cantidad' => cantidad del item
'precio' => precio con IVA incluido

// Costos adicionales
'envio' => se agrega como item separado si existe
```

### Campo RUT en Checkout

El plugin agrega automáticamente el campo RUT al checkout con:

- **Formato:** 12345678-9 (con guión)
- **Validación:** Dígito verificador según algoritmo oficial
- **Obligatorio:** No puede completar compra sin RUT válido
- **Compatible:** Si ya tienes campo `_billing_rut`, lo usa automáticamente

### Troubleshooting WooCommerce

**Boleta no se genera automáticamente:**

```bash
# 1. Verificar que orden esté en estado "Completada"
# 2. Ver logs
tail -f logs/errors_$(date +%Y-%m-%d).log | grep woocommerce

# 3. Verificar configuración
cat generar-boleta.php | grep "define("

# 4. Generar manualmente desde admin de orden
```

**Error "WooCommerce no encontrado":**

```bash
# Instalar WooCommerce primero
WordPress Admin → Plugins → Añadir nuevo → Buscar "WooCommerce"
```

**Cliente no recibe PDF por email:**

```php
// Verificar configuración de email en generar-boleta.php
$CONFIG['envio_automatico_email'] = true;
$CONFIG['adjuntar_pdf'] = true;
```

### Documentación Completa del Plugin

Ver **PLUGIN-WOOCOMMERCE-README.md** para:
- Instalación detallada paso a paso
- Personalización del plugin
- Compatibilidad con temas y plugins
- Logs y debugging avanzado
- Seguridad y permisos

### Beneficios de la Integración

| Sin Plugin | Con Plugin WooCommerce |
|------------|------------------------|
| Generar boleta manualmente después de cada venta | ✅ Generación automática al completar orden |
| Copiar datos del cliente de WooCommerce | ✅ Datos extraídos automáticamente |
| Enviar PDF manualmente por email | ✅ Email automático con PDF adjunto |
| Cliente no tiene acceso a boleta | ✅ Cliente descarga desde "Mi cuenta" |
| Sin trazabilidad en órdenes | ✅ Folio visible en lista de órdenes |
| Propenso a olvidos | ✅ Cada venta = boleta garantizada |

## Ambiente de Certificación vs Producción

### Certificación (Pruebas)

```php
define('AMBIENTE', 'certificacion');
```

- URL SII: https://maullin.sii.cl
- Folios de prueba
- Sin valor tributario

### Producción

```php
define('AMBIENTE', 'produccion');
```

- URL SII: https://palena.sii.cl
- Folios reales del SII
- Con valor tributario oficial

## Troubleshooting

### Error: "No hay más folios disponibles"

Solución: Obtener un nuevo CAF del SII y actualizar `CAF_PATH`.

### Error 514: "Firma del CAF Incorrecta"

Solución: Verificar que el CAF sea oficial del SII, no generado localmente.

### Error 100: "DTE Repetido"

Solución: El folio ya fue usado. Verificar `folios_usados.txt` o eliminar para reiniciar.

### Estado "REC" permanente

Solución: El SII aún está procesando. Esperar más tiempo antes de consultar.

## Testing

Ejecutar el script de pruebas completo:

```bash
php test-simple-dte.php
```

Este script ejecuta un caso de prueba completo (CASO-1) para verificar la integración.

## Archivos Generados

- `/tmp/boleta_XXXX.xml` - XML de cada boleta generada
- `/tmp/sobre_envio.xml` - Sobre de envío al SII
- `/tmp/track_id.txt` - Último Track ID generado
- `folios_usados.txt` - Control de folios

## Seguridad

- ⚠️ NO versionar archivos con credenciales (.pfx, passwords)
- ⚠️ NO compartir API_KEY
- ⚠️ Usar HTTPS en producción
- ⚠️ Validar datos de entrada antes de generar boletas

## Mejoras Recientes

Ver el archivo **[MEJORAS-IMPLEMENTADAS.md](MEJORAS-IMPLEMENTADAS.md)** para documentación detallada de las últimas mejoras:

**Mejoras Críticas Implementadas:**
1. ✅ **Timbre PDF417** - Código de barras oficial SII en PDFs
2. ✅ **Base de Datos** - Sistema escalable con MySQL/MariaDB
3. ✅ **Logging Estructurado** - Auditoría completa del sistema

**Beneficios:**
- Cumplimiento 100% con especificaciones SII
- Arquitectura escalable y robusta
- Auditoría completa de operaciones
- Sistema listo para producción

## Soporte

Para problemas o consultas:
- **Documentación completa:** Ver este README y `MEJORAS-IMPLEMENTADAS.md`
- **Tests de validación:** Ejecutar scripts `test-*.php`
- **Logs del sistema:** Revisar `logs/dte_YYYY-MM-DD.log`
- Documentación Simple API: https://documentacion.simpleapi.cl/
- GitHub: https://github.com/chilesystems/SIMPLEAPI_Standard_Demo

### Troubleshooting Avanzado

**Error al generar PDF417:**
```bash
# Verificar extensiones
php -m | grep -E "(bcmath|gd|dom)"

# Instalar si faltan
sudo apt-get install php-bcmath php-gd php-dom
```

**Error de conexión a base de datos:**
```bash
# Verificar variables de entorno
env | grep DB_

# Verificar MySQL está corriendo
sudo systemctl status mysql

# Verificar conexión
mysql -u $DB_USER -p $DB_NAME -e "SELECT 1;"
```

**Logs no se generan:**
```bash
# Crear directorio de logs
mkdir -p logs
chmod 755 logs

# Verificar permisos
ls -la logs/
```

## Licencia

Este código es de ejemplo para integración con Simple API y el SII de Chile.
