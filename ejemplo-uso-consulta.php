#!/usr/bin/env php
<?php
/**
 * EJEMPLO COMPLETO: Cómo usar la consulta de estado de DTEs
 *
 * Este script muestra diferentes formas de consultar el estado de un DTE
 * usando el track_id proporcionado por el SII.
 */

// ============================================================
// EJEMPLO 1: Consulta simple usando la API Client
// ============================================================
echo "\n═══════════════════════════════════════════════════════════\n";
echo "  EJEMPLO 1: Consulta Simple\n";
echo "═══════════════════════════════════════════════════════════\n\n";

echo "Código PHP:\n\n";
echo <<<'PHP'
<?php
// Supongamos que tienes un track_id del SII
$track_id = 'ABC123XYZ';  // Este lo obtienes al enviar al SII
$rut_emisor = '78274225-6';

// Hacer la consulta
$resultado = Simple_DTE_API_Client::consultar_estado_envio($track_id, $rut_emisor);

// Verificar resultado
if (is_wp_error($resultado)) {
    echo "Error: " . $resultado->get_error_message();
} else {
    echo "Estado: " . $resultado['estado'];
    echo "Glosa: " . $resultado['glosa'];
}
?>
PHP;

echo "\n\n";

// ============================================================
// EJEMPLO 2: Consulta desde una orden WooCommerce
// ============================================================
echo "═══════════════════════════════════════════════════════════\n";
echo "  EJEMPLO 2: Consulta desde Orden WooCommerce\n";
echo "═══════════════════════════════════════════════════════════\n\n";

echo "Código PHP:\n\n";
echo <<<'PHP'
<?php
/**
 * Función para consultar y actualizar el estado de una orden
 */
function actualizar_estado_dte_orden($order_id) {
    // Obtener la orden
    $order = wc_get_order($order_id);

    if (!$order) {
        return new WP_Error('orden_no_encontrada', 'Orden no existe');
    }

    // Obtener el track_id guardado
    $track_id = $order->get_meta('_boleta_track_id');

    if (empty($track_id)) {
        return new WP_Error('sin_track_id', 'Esta orden no tiene track_id');
    }

    // Consultar estado en el SII
    $resultado = Simple_DTE_Consultas::consultar_estado_envio($track_id);

    if (is_wp_error($resultado)) {
        return $resultado;
    }

    // Actualizar estado en la orden
    $order->update_meta_data('_boleta_estado_sii', $resultado['estado']);
    $order->update_meta_data('_boleta_fecha_consulta', current_time('mysql'));
    $order->save();

    // Agregar nota a la orden
    $order->add_order_note(
        sprintf(
            'Estado DTE actualizado: %s - %s',
            $resultado['estado'],
            $resultado['glosa'] ?? ''
        )
    );

    return $resultado;
}

// Usar la función
$order_id = 14;
$resultado = actualizar_estado_dte_orden($order_id);

if (!is_wp_error($resultado)) {
    echo "✅ Estado actualizado: " . $resultado['estado'];
}
?>
PHP;

echo "\n\n";

// ============================================================
// EJEMPLO 3: Consulta masiva de órdenes pendientes
// ============================================================
echo "═══════════════════════════════════════════════════════════\n";
echo "  EJEMPLO 3: Consulta Masiva de Órdenes Pendientes\n";
echo "═══════════════════════════════════════════════════════════\n\n";

echo "Código PHP:\n\n";
echo <<<'PHP'
<?php
/**
 * Script para actualizar el estado de todas las órdenes
 * que tienen track_id pero estado pendiente
 */
function actualizar_estados_masivo() {
    global $wpdb;

    // Buscar órdenes con track_id y estado REC o EPR
    $query = "
        SELECT p.ID
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_boleta_track_id'
        INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_boleta_estado'
        WHERE p.post_type = 'shop_order'
        AND pm1.meta_value != ''
        AND pm2.meta_value IN ('REC', 'EPR')
        LIMIT 50
    ";

    $order_ids = $wpdb->get_col($query);

    echo "🔍 Encontradas " . count($order_ids) . " órdenes para actualizar\n\n";

    $actualizadas = 0;
    $errores = 0;

    foreach ($order_ids as $order_id) {
        echo "Orden #$order_id... ";

        $resultado = actualizar_estado_dte_orden($order_id);

        if (is_wp_error($resultado)) {
            echo "❌ Error: " . $resultado->get_error_message() . "\n";
            $errores++;
        } else {
            echo "✅ " . $resultado['estado'] . "\n";
            $actualizadas++;
        }

        // Pausa entre consultas para no saturar la API
        sleep(2);
    }

    echo "\n═══════════════════════════════════════════════════════════\n";
    echo "Resumen:\n";
    echo "  Actualizadas: $actualizadas\n";
    echo "  Errores: $errores\n";
    echo "═══════════════════════════════════════════════════════════\n";
}

// Ejecutar
actualizar_estados_masivo();
?>
PHP;

echo "\n\n";

// ============================================================
// EJEMPLO 4: Uso desde línea de comandos (CLI)
// ============================================================
echo "═══════════════════════════════════════════════════════════\n";
echo "  EJEMPLO 4: Uso desde Línea de Comandos (CLI)\n";
echo "═══════════════════════════════════════════════════════════\n\n";

echo "Bash:\n\n";
echo <<<'BASH'
# Consulta simple con track_id
php consultar-estado-manual.php ABC123XYZ

# Consultar estado de múltiples track_ids
for track_id in ABC123 DEF456 GHI789; do
    echo "Consultando $track_id..."
    php consultar-estado-manual.php $track_id
    sleep 1
done

# Guardar resultado en archivo
php consultar-estado-manual.php ABC123XYZ > estado-ABC123XYZ.txt

# Consultar y parsear resultado con jq (si la respuesta es JSON)
php consultar-estado-manual.php ABC123XYZ | grep -o '"estado":"[^"]*"'
BASH;

echo "\n\n";

// ============================================================
// EJEMPLO 5: Integración con WP-CLI
// ============================================================
echo "═══════════════════════════════════════════════════════════\n";
echo "  EJEMPLO 5: Integración con WP-CLI\n";
echo "═══════════════════════════════════════════════════════════\n\n";

echo "Puedes crear un comando WP-CLI personalizado:\n\n";
echo <<<'PHP'
<?php
/**
 * Archivo: wp-content/plugins/simple-dte/includes/class-simple-dte-cli.php
 */
if (defined('WP_CLI') && WP_CLI) {
    class Simple_DTE_CLI {

        /**
         * Consultar estado de un DTE por track_id
         *
         * ## EJEMPLOS
         *
         *     wp dte consultar-estado ABC123XYZ
         *
         * @param array $args
         */
        public function consultar_estado($args) {
            if (empty($args[0])) {
                WP_CLI::error('Debes proporcionar un track_id');
            }

            $track_id = $args[0];

            WP_CLI::log("Consultando estado del track_id: $track_id");

            $resultado = Simple_DTE_Consultas::consultar_estado_envio($track_id);

            if (is_wp_error($resultado)) {
                WP_CLI::error($resultado->get_error_message());
            }

            WP_CLI::success("Estado: " . $resultado['estado']);
            WP_CLI::log("Glosa: " . ($resultado['glosa'] ?? 'N/A'));
        }

        /**
         * Actualizar estados de órdenes pendientes
         *
         * ## EJEMPLOS
         *
         *     wp dte actualizar-estados
         *     wp dte actualizar-estados --limit=10
         *
         * @param array $args
         * @param array $assoc_args
         */
        public function actualizar_estados($args, $assoc_args) {
            $limit = isset($assoc_args['limit']) ? (int)$assoc_args['limit'] : 50;

            // [Implementar lógica de actualización masiva]

            WP_CLI::success("Estados actualizados");
        }
    }

    WP_CLI::add_command('dte', 'Simple_DTE_CLI');
}
?>
PHP;

echo "\n\nUso:\n";
echo <<<'BASH'
# Consultar estado
wp dte consultar-estado ABC123XYZ

# Actualizar estados masivamente
wp dte actualizar-estados --limit=20
BASH;

echo "\n\n";

// ============================================================
// RESUMEN
// ============================================================
echo "═══════════════════════════════════════════════════════════\n";
echo "  RESUMEN DE OPCIONES\n";
echo "═══════════════════════════════════════════════════════════\n\n";

echo "1. Script CLI:           php consultar-estado-manual.php <track_id>\n";
echo "2. Código PHP:           Simple_DTE_Consultas::consultar_estado_envio()\n";
echo "3. WordPress Admin:      Simple DTE → Consultas → Ingresar Track ID\n";
echo "4. WooCommerce Meta:     \$order->get_meta('_boleta_track_id')\n";
echo "5. Actualización masiva: Script personalizado con WP_Query\n";
echo "6. WP-CLI (custom):      wp dte consultar-estado <track_id>\n";

echo "\n═══════════════════════════════════════════════════════════\n\n";

echo "✅ Documentación completa en: DOCUMENTACION-CONSULTA-ESTADO.md\n\n";
