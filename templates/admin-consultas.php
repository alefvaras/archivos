<?php
/**
 * Template: Consultas DTE
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Consultas DTE', 'simple-dte'); ?></h1>

    <div class="card">
        <h2><?php _e('Consultar Estado de Envío', 'simple-dte'); ?></h2>
        <form id="form-consultar-estado">
            <table class="form-table">
                <tr>
                    <th><label for="track_id"><?php _e('Track ID:', 'simple-dte'); ?></label></th>
                    <td>
                        <input type="text" id="track_id" name="track_id" class="regular-text" required />
                        <p class="description"><?php _e('ID de seguimiento del envío', 'simple-dte'); ?></p>
                    </td>
                </tr>
            </table>
            <p><button type="submit" class="button button-primary"><?php _e('Consultar', 'simple-dte'); ?></button></p>
        </form>
        <div id="resultado-estado"></div>
    </div>

    <div class="card">
        <h2><?php _e('Consultar DTE Específico', 'simple-dte'); ?></h2>
        <form id="form-consultar-dte">
            <table class="form-table">
                <tr>
                    <th><label for="tipo_dte"><?php _e('Tipo DTE:', 'simple-dte'); ?></label></th>
                    <td>
                        <select id="tipo_dte" name="tipo_dte" required>
                            <option value="39">39 - Boleta Electrónica</option>
                            <option value="61">61 - Nota de Crédito</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="folio"><?php _e('Folio:', 'simple-dte'); ?></label></th>
                    <td>
                        <input type="number" id="folio" name="folio" required />
                    </td>
                </tr>
            </table>
            <p><button type="submit" class="button button-primary"><?php _e('Consultar', 'simple-dte'); ?></button></p>
        </form>
        <div id="resultado-dte"></div>
    </div>
</div>
