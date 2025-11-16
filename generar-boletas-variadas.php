<?php
/**
 * Generador de Boletas con Datos Variados
 *
 * Genera múltiples boletas con datos ÚNICOS para evitar
 * el error "DTE Repetido" del SII
 */

require_once __DIR__ . '/generar-boleta.php';

echo "=== GENERADOR DE BOLETAS CON DATOS VARIADOS ===\n\n";

// Cuántas boletas generar
$cantidad = isset($argv[1]) ? intval($argv[1]) : 5;

echo "📋 Generando {$cantidad} boletas con datos únicos...\n\n";

// Datos variados para generar boletas únicas
$clientes_variados = [
    ['rut' => '11111111-1', 'nombre' => 'Juan Pérez González'],
    ['rut' => '22222222-2', 'nombre' => 'María García López'],
    ['rut' => '12345678-5', 'nombre' => 'Pedro Rodríguez Silva'],
    ['rut' => '98765432-1', 'nombre' => 'Ana Martínez Torres'],
    ['rut' => '66666666-6', 'nombre' => 'Cliente Genérico'],
    ['rut' => '77777777-7', 'nombre' => 'Carlos Fernández Ruiz'],
    ['rut' => '88888888-8', 'nombre' => 'Laura Sánchez Morales'],
    ['rut' => '13579246-8', 'nombre' => 'Roberto Castro Vargas'],
];

$productos = [
    ['nombre' => 'Laptop Dell Inspiron 15', 'precio_base' => 450000],
    ['nombre' => 'Mouse Logitech MX Master', 'precio_base' => 65000],
    ['nombre' => 'Teclado Mecánico RGB', 'precio_base' => 89000],
    ['nombre' => 'Monitor LG 24 pulgadas', 'precio_base' => 180000],
    ['nombre' => 'Webcam HD 1080p', 'precio_base' => 45000],
    ['nombre' => 'Auriculares Sony WH-1000XM4', 'precio_base' => 280000],
    ['nombre' => 'Disco SSD 1TB Samsung', 'precio_base' => 120000],
    ['nombre' => 'Router WiFi 6 TP-Link', 'precio_base' => 95000],
    ['nombre' => 'Memoria RAM 16GB DDR4', 'precio_base' => 55000],
    ['nombre' => 'Gabinete ATX RGB', 'precio_base' => 75000],
];

$servicios = [
    ['nombre' => 'Consultoría IT', 'precio_base' => 80000],
    ['nombre' => 'Soporte Técnico', 'precio_base' => 45000],
    ['nombre' => 'Instalación Software', 'precio_base' => 35000],
    ['nombre' => 'Mantenimiento Equipos', 'precio_base' => 50000],
    ['nombre' => 'Capacitación Office', 'precio_base' => 120000],
];

// Configuración para todas las boletas
$CONFIG['envio_automatico_email'] = false;
$CONFIG['consulta_automatica'] = true;
$CONFIG['espera_consulta_segundos'] = 3; // Reducir espera para ir más rápido

$resultados = [];
$exitosos = 0;
$rechazados = 0;

for ($i = 1; $i <= $cantidad; $i++) {
    echo str_repeat("=", 70) . "\n";
    echo "📄 BOLETA #{$i} de {$cantidad}\n";
    echo str_repeat("-", 70) . "\n\n";

    // Seleccionar cliente aleatorio
    $cliente_data = $clientes_variados[array_rand($clientes_variados)];

    $cliente = [
        'rut' => $cliente_data['rut'],
        'razon_social' => $cliente_data['nombre'],
        'email' => strtolower(str_replace(' ', '.', $cliente_data['nombre'])) . '@example.cl',
        'direccion' => 'Av. Providencia ' . rand(100, 9999),
        'comuna' => ['Providencia', 'Santiago', 'Las Condes', 'Vitacura'][array_rand(['Providencia', 'Santiago', 'Las Condes', 'Vitacura'])]
    ];

    echo "Cliente: {$cliente['razon_social']} ({$cliente['rut']})\n";

    // Generar items variados (1 a 3 items)
    $num_items = rand(1, 3);
    $items = [];

    // Mezclar productos y servicios
    $todos_items = array_merge($productos, $servicios);
    shuffle($todos_items);

    for ($j = 0; $j < $num_items; $j++) {
        $item_data = $todos_items[$j];

        // Variación de precio ±20%
        $variacion = (rand(80, 120) / 100);
        $precio = round($item_data['precio_base'] * $variacion);

        // Cantidad aleatoria
        $cantidad_item = rand(1, 3);

        $items[] = [
            'nombre' => $item_data['nombre'],
            'descripcion' => 'Descripción del producto/servicio',
            'cantidad' => $cantidad_item,
            'precio' => $precio,
            'unidad' => 'un'
        ];

        echo "  - {$item_data['nombre']}: \${$precio} x {$cantidad_item}\n";
    }

    // Calcular total esperado
    $total_esperado = 0;
    foreach ($items as $item) {
        $total_esperado += $item['cantidad'] * $item['precio'];
    }

    echo "Total esperado: \$" . number_format($total_esperado, 0, ',', '.') . "\n\n";

    try {
        // Generar boleta
        $resultado = generar_boleta($cliente, $items, $CONFIG, $API_BASE);

        if ($resultado && isset($resultado['folio'])) {
            echo "✅ Boleta generada exitosamente\n";
            echo "   Folio: {$resultado['folio']}\n";
            echo "   Total: \$" . number_format($resultado['total'], 0, ',', '.') . "\n";
            echo "   Track ID: {$resultado['track_id']}\n";

            // Verificar estado
            if (isset($resultado['estado'])) {
                $estado_desc = $resultado['estado']['estado'] ?? 'N/A';
                echo "   Estado SII: {$estado_desc}\n";

                // Contar aceptados/rechazados
                if (isset($resultado['estado']['estadistica'][0])) {
                    $stats = $resultado['estado']['estadistica'][0];
                    $aceptados_item = $stats['aceptados'] ?? 0;
                    $rechazados_item = $stats['rechazados'] ?? 0;

                    if ($aceptados_item > 0) {
                        echo "   ✅ ACEPTADO por SII\n";
                        $exitosos++;
                    } elseif ($rechazados_item > 0) {
                        echo "   ❌ RECHAZADO por SII\n";
                        $rechazados++;

                        // Mostrar razón del rechazo
                        if (isset($resultado['estado']['detalles'][0]['errores'])) {
                            echo "   Razón: ";
                            foreach ($resultado['estado']['detalles'][0]['errores'] as $error) {
                                echo $error['descripcion'] ?? 'Error desconocido';
                            }
                            echo "\n";
                        }
                    }
                }
            }

            $resultados[] = [
                'numero' => $i,
                'folio' => $resultado['folio'],
                'track_id' => $resultado['track_id'],
                'cliente' => $cliente['razon_social'],
                'total' => $resultado['total'],
                'estado' => $resultado['estado']['estado'] ?? 'N/A'
            ];

        } else {
            echo "❌ Error generando boleta\n";
            $rechazados++;
        }

    } catch (Exception $e) {
        echo "❌ Excepción: " . $e->getMessage() . "\n";
        $rechazados++;
    }

    echo "\n";

    // Pausa entre boletas para no saturar el SII
    if ($i < $cantidad) {
        echo "⏳ Esperando 2 segundos antes de siguiente boleta...\n\n";
        sleep(2);
    }
}

// Resumen final
echo str_repeat("=", 70) . "\n";
echo "=== RESUMEN FINAL ===\n";
echo str_repeat("=", 70) . "\n\n";

echo "📊 ESTADÍSTICAS:\n";
echo "  Total generadas: {$cantidad}\n";
echo "  ✅ Aceptadas: {$exitosos}\n";
echo "  ❌ Rechazadas: {$rechazados}\n";

if ($cantidad > 0) {
    $tasa_exito = round(($exitosos / $cantidad) * 100, 1);
    echo "  📈 Tasa de éxito: {$tasa_exito}%\n";
}

echo "\n📋 DETALLE DE BOLETAS GENERADAS:\n\n";

foreach ($resultados as $r) {
    $status = $exitosos > $rechazados ? '✅' : '❌';
    echo "  #{$r['numero']} - Folio {$r['folio']} - Track ID {$r['track_id']}\n";
    echo "       Cliente: {$r['cliente']}\n";
    echo "       Total: \$" . number_format($r['total'], 0, ',', '.') . "\n";
    echo "       Estado: {$r['estado']}\n\n";
}

// Guardar resultados
$resultado_file = __DIR__ . '/logs/boletas_variadas_' . date('Y-m-d_H-i-s') . '.json';
file_put_contents($resultado_file, json_encode([
    'fecha' => date('Y-m-d H:i:s'),
    'cantidad_generada' => $cantidad,
    'exitosos' => $exitosos,
    'rechazados' => $rechazados,
    'tasa_exito' => isset($tasa_exito) ? $tasa_exito : 0,
    'boletas' => $resultados
], JSON_PRETTY_PRINT));

echo "💾 Resultados guardados en: {$resultado_file}\n\n";

if ($exitosos === $cantidad) {
    echo "🎉 ¡EXCELENTE! TODAS LAS BOLETAS FUERON ACEPTADAS (100%)\n\n";
    exit(0);
} elseif ($tasa_exito >= 80) {
    echo "✅ Buen resultado. La mayoría de las boletas fueron aceptadas.\n\n";
    exit(0);
} else {
    echo "⚠️  Revisar boletas rechazadas para identificar problemas.\n\n";
    exit(1);
}
