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

    <div class="notice notice-info">
        <p>
            <strong><?php _e('¿Qué es el RCV?', 'simple-dte'); ?></strong><br>
            <?php _e('El Registro de Compras y Ventas es un libro auxiliar que registra todas las operaciones de compra y venta del período. Es obligatorio para efectos tributarios.', 'simple-dte'); ?>
        </p>
    </div>

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

        <!-- Área para mostrar XML y botón de envío -->
        <div id="area-envio-rcv" style="display: none; margin-top: 20px;">
            <h3><?php _e('RCV Generado', 'simple-dte'); ?></h3>

            <div class="rcv-info" style="background: #f0f0f1; padding: 15px; margin-bottom: 15px;">
                <p><strong><?php _e('Período:', 'simple-dte'); ?></strong> <span id="rcv-periodo"></span></p>
                <p><strong><?php _e('Cantidad de Documentos:', 'simple-dte'); ?></strong> <span id="rcv-cantidad"></span></p>
            </div>

            <div style="margin-bottom: 15px;">
                <label>
                    <strong><?php _e('XML Generado:', 'simple-dte'); ?></strong>
                </label>
                <textarea id="rcv-xml" readonly style="width: 100%; height: 200px; font-family: monospace; font-size: 12px;"></textarea>
            </div>

            <p>
                <button type="button" id="btn-enviar-rcv" class="button button-primary button-large">
                    <?php _e('Enviar RCV al SII', 'simple-dte'); ?>
                </button>
                <button type="button" id="btn-descargar-rcv" class="button">
                    <?php _e('Descargar XML', 'simple-dte'); ?>
                </button>
            </p>
        </div>
    </div>
</div>
