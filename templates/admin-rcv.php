<?php
/**
 * Template: RCV
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Registro de Compras y Ventas (RCV)', 'simple-dte'); ?></h1>

    <div class="card">
        <h2><?php _e('Generar RCV de Ventas', 'simple-dte'); ?></h2>
        <form id="form-generar-rcv">
            <table class="form-table">
                <tr>
                    <th><label for="fecha_desde"><?php _e('Fecha Desde:', 'simple-dte'); ?></label></th>
                    <td>
                        <input type="date" id="fecha_desde" name="fecha_desde" required />
                    </td>
                </tr>
                <tr>
                    <th><label for="fecha_hasta"><?php _e('Fecha Hasta:', 'simple-dte'); ?></label></th>
                    <td>
                        <input type="date" id="fecha_hasta" name="fecha_hasta" required />
                    </td>
                </tr>
            </table>
            <p><button type="submit" class="button button-primary"><?php _e('Generar RCV', 'simple-dte'); ?></button></p>
        </form>
        <div id="resultado-rcv"></div>
    </div>
</div>
