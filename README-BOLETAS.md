# Sistema de Generación de Boletas Electrónicas

Sistema automatizado para generar, enviar y gestionar Boletas Electrónicas (DTE tipo 39) usando Simple API y el SII de Chile.

## Características

- ✅ Generación automática de boletas electrónicas
- ✅ Envío automático al SII
- ✅ Consulta automática de estado
- ✅ Envío automático por email (opcional)
- ✅ Control automático de folios
- ✅ Guardado de XMLs generados
- ✅ Configuración flexible

## Archivos del Sistema

- `generar-boleta.php` - Script principal del sistema
- `ejemplo-uso-boletas.php` - Ejemplos de uso interactivos
- `gestor-cafs.php` - Gestor de archivos CAF (cambiar entre múltiples CAFs)
- `test-simple-dte.php` - Script de pruebas para certificación SII
- `folios_usados.txt` - Control automático de folios usados

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

## Soporte

Para problemas o consultas:
- Documentación Simple API: https://documentacion.simpleapi.cl/
- GitHub: https://github.com/chilesystems/SIMPLEAPI_Standard_Demo

## Licencia

Este código es de ejemplo para integración con Simple API y el SII de Chile.
