#!/usr/bin/env php
<?php
/**
 * Ejemplos de uso del sistema de generación de boletas
 */

require_once __DIR__ . '/generar-boleta.php';

// ========================================
// EJEMPLO 1: Boleta simple sin envío automático
// ========================================
function ejemplo_boleta_simple() {
    global $CONFIG, $API_BASE;

    echo "\n=== EJEMPLO 1: Boleta Simple ===\n\n";

    // Configuración: sin envío automático de email
    $CONFIG['envio_automatico_email'] = false;
    $CONFIG['consulta_automatica'] = true;

    $cliente = [
        'rut' => '12345678-9',
        'razon_social' => 'Juan Pérez',
        'email' => 'juan.perez@example.com'
    ];

    $items = [
        [
            'nombre' => 'Producto 1',
            'cantidad' => 2,
            'precio' => 10000
        ]
    ];

    $resultado = generar_boleta($cliente, $items, $CONFIG, $API_BASE);
    return $resultado;
}

// ========================================
// EJEMPLO 2: Boleta con envío automático por email
// ========================================
function ejemplo_boleta_con_email() {
    global $CONFIG, $API_BASE;

    echo "\n=== EJEMPLO 2: Boleta con Envío Automático ===\n\n";

    // Configuración: CON envío automático de email
    $CONFIG['envio_automatico_email'] = true;
    $CONFIG['consulta_automatica'] = true;

    $cliente = [
        'rut' => '98765432-1',
        'razon_social' => 'María González',
        'email' => 'maria.gonzalez@example.com',
        'direccion' => 'Av. Providencia 123',
        'comuna' => 'Providencia'
    ];

    $items = [
        [
            'nombre' => 'Servicio de consultoría',
            'descripcion' => 'Consultoría técnica 2 horas',
            'cantidad' => 2,
            'precio' => 25000,
            'unidad' => 'hrs'
        ],
        [
            'nombre' => 'Material de oficina',
            'cantidad' => 1,
            'precio' => 5000
        ]
    ];

    $resultado = generar_boleta($cliente, $items, $CONFIG, $API_BASE);
    return $resultado;
}

// ========================================
// EJEMPLO 3: Boleta sin consulta automática
// ========================================
function ejemplo_boleta_sin_consulta() {
    global $CONFIG, $API_BASE;

    echo "\n=== EJEMPLO 3: Boleta Sin Consulta Automática ===\n\n";

    // Configuración: sin consulta automática
    $CONFIG['envio_automatico_email'] = false;
    $CONFIG['consulta_automatica'] = false;  // NO consultar automáticamente

    $cliente = [
        'rut' => '66666666-6',
        'razon_social' => 'Cliente Final'
    ];

    $items = [
        [
            'nombre' => 'Venta al por menor',
            'cantidad' => 1,
            'precio' => 15000
        ]
    ];

    $resultado = generar_boleta($cliente, $items, $CONFIG, $API_BASE);

    // Consultar manualmente después
    echo "\n🔍 Consultando estado manualmente...\n";
    sleep(3);
    $estado = consultar_estado($resultado['track_id'], $API_BASE);

    if ($estado) {
        echo "Estado: {$estado['estado']}\n";
        if (isset($estado['estadistica'])) {
            foreach ($estado['estadistica'] as $stat) {
                echo "Aceptados: {$stat['aceptados']}, Rechazados: {$stat['rechazados']}\n";
            }
        }
    }

    return $resultado;
}

// ========================================
// EJEMPLO 4: Configuración completa personalizada
// ========================================
function ejemplo_configuracion_personalizada() {
    global $CONFIG, $API_BASE;

    echo "\n=== EJEMPLO 4: Configuración Personalizada ===\n\n";

    // Configuración personalizada completa
    $CONFIG['envio_automatico_email'] = true;
    $CONFIG['consulta_automatica'] = true;
    $CONFIG['espera_consulta_segundos'] = 10;  // Esperar más tiempo
    $CONFIG['guardar_xml'] = true;
    $CONFIG['directorio_xml'] = '/tmp/boletas';
    $CONFIG['email_remitente'] = 'ventas@akibara.cl';

    // Crear directorio si no existe
    if (!is_dir($CONFIG['directorio_xml'])) {
        mkdir($CONFIG['directorio_xml'], 0755, true);
    }

    $cliente = [
        'rut' => '11111111-1',
        'razon_social' => 'Empresa XYZ Ltda',
        'email' => 'facturacion@xyz.cl',
        'direccion' => 'Los Leones 456',
        'comuna' => 'Providencia'
    ];

    $items = [
        [
            'nombre' => 'Hosting mensual',
            'descripcion' => 'Plan Premium 100GB',
            'cantidad' => 1,
            'precio' => 35000,
            'unidad' => 'mes'
        ],
        [
            'nombre' => 'Dominio .cl',
            'descripcion' => 'Renovación anual',
            'cantidad' => 1,
            'precio' => 12000,
            'unidad' => 'año'
        ]
    ];

    $resultado = generar_boleta($cliente, $items, $CONFIG, $API_BASE);
    return $resultado;
}

// ========================================
// MENÚ INTERACTIVO (si se ejecuta desde CLI)
// ========================================
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    echo "=== EJEMPLOS DE USO - SISTEMA DE BOLETAS ===\n\n";
    echo "Seleccione un ejemplo:\n";
    echo "1. Boleta simple sin envío automático\n";
    echo "2. Boleta con envío automático por email\n";
    echo "3. Boleta sin consulta automática\n";
    echo "4. Configuración completa personalizada\n";
    echo "0. Salir\n\n";

    echo "Opción [1-4]: ";
    $opcion = trim(fgets(STDIN));

    try {
        switch ($opcion) {
            case '1':
                ejemplo_boleta_simple();
                break;
            case '2':
                ejemplo_boleta_con_email();
                break;
            case '3':
                ejemplo_boleta_sin_consulta();
                break;
            case '4':
                ejemplo_configuracion_personalizada();
                break;
            case '0':
                echo "Saliendo...\n";
                exit(0);
            default:
                echo "Opción inválida\n";
                exit(1);
        }
    } catch (Exception $e) {
        echo "\n❌ Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
