<?php
/**
 * Template: Página principal de administración
 *
 * @package Simple_DTE
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Simple DTE - Integración Simple API', 'simple-dte'); ?></h1>

    <div class="simple-dte-dashboard">
        <!-- Estado de conexión -->
        <div class="card">
            <h2><?php _e('Estado del Sistema', 'simple-dte'); ?></h2>
            <table class="widefat">
                <tr>
                    <th><?php _e('Ambiente:', 'simple-dte'); ?></th>
                    <td>
                        <strong><?php echo esc_html(ucfirst($ambiente)); ?></strong>
                        <?php if ($ambiente === 'certificacion'): ?>
                            <span class="badge badge-warning"><?php _e('PRUEBAS', 'simple-dte'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('API Key:', 'simple-dte'); ?></th>
                    <td>
                        <?php if (!empty($api_key)): ?>
                            <span style="color: green;">✓ <?php _e('Configurada', 'simple-dte'); ?></span>
                        <?php else: ?>
                            <span style="color: red;">✗ <?php _e('No configurada', 'simple-dte'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Estado de folios -->
        <div class="card">
            <h2><?php _e('Folios Disponibles', 'simple-dte'); ?></h2>

            <?php if (!empty($folios)): ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Tipo DTE', 'simple-dte'); ?></th>
                            <th><?php _e('Rango', 'simple-dte'); ?></th>
                            <th><?php _e('Actual', 'simple-dte'); ?></th>
                            <th><?php _e('Disponibles', 'simple-dte'); ?></th>
                            <th><?php _e('Uso', 'simple-dte'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($folios as $folio): ?>
                            <tr>
                                <td><strong><?php echo esc_html($folio['tipo_nombre']); ?></strong></td>
                                <td><?php echo esc_html($folio['desde'] . ' - ' . $folio['hasta']); ?></td>
                                <td><?php echo esc_html($folio['actual']); ?></td>
                                <td>
                                    <strong><?php echo esc_html($folio['disponibles']); ?></strong>
                                    <?php if ($folio['disponibles'] < 10): ?>
                                        <span style="color: red;">⚠</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo esc_attr($folio['porcentaje_usado']); ?>%"></div>
                                    </div>
                                    <?php echo esc_html($folio['porcentaje_usado']); ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e('No hay CAFs cargados', 'simple-dte'); ?></p>
            <?php endif; ?>

            <h3><?php _e('Solicitar Folios a SimpleAPI', 'simple-dte'); ?></h3>
            <div style="background: #f0f6fc; border-left: 4px solid #0073aa; padding: 15px; margin-bottom: 15px;">
                <p style="margin: 0;"><strong>ℹ️ <?php _e('Solicitud Automática de Folios', 'simple-dte'); ?></strong></p>
                <p style="margin: 5px 0 0 0; font-size: 13px;">
                    <?php _e('Solicita folios CAF directamente al SII a través de SimpleAPI sin necesidad de descargar archivos manualmente.', 'simple-dte'); ?>
                </p>
            </div>
            <form id="solicitar-folios-form">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="tipo_dte_solicitar"><?php _e('Tipo de DTE:', 'simple-dte'); ?></label>
                        </th>
                        <td>
                            <select name="tipo_dte" id="tipo_dte_solicitar" required>
                                <option value=""><?php _e('Seleccione...', 'simple-dte'); ?></option>
                                <option value="39"><?php _e('39 - Boleta Electrónica', 'simple-dte'); ?></option>
                                <option value="41"><?php _e('41 - Boleta Exenta', 'simple-dte'); ?></option>
                                <option value="33"><?php _e('33 - Factura Electrónica', 'simple-dte'); ?></option>
                                <option value="61"><?php _e('61 - Nota de Crédito', 'simple-dte'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cantidad_folios"><?php _e('Cantidad de Folios:', 'simple-dte'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="cantidad" id="cantidad_folios" min="1" max="1000" value="50" required />
                            <p class="description"><?php _e('Entre 1 y 1000 folios', 'simple-dte'); ?></p>
                        </td>
                    </tr>
                </table>
                <p>
                    <button type="submit" class="button button-primary" id="btn-solicitar-folios">
                        <?php _e('Solicitar Folios', 'simple-dte'); ?>
                    </button>
                    <span id="solicitar-folios-spinner" class="spinner" style="display: none; float: none; margin: 0 10px;"></span>
                </p>
                <div id="solicitar-folios-result" style="margin-top: 10px;"></div>
            </form>

            <hr style="margin: 30px 0;">

            <h3><?php _e('Cargar CAF Manualmente', 'simple-dte'); ?></h3>
            <p class="description"><?php _e('Si ya tienes un archivo CAF descargado del SII, puedes cargarlo aquí.', 'simple-dte'); ?></p>
            <form id="upload-caf-form" enctype="multipart/form-data">
                <p>
                    <label for="tipo_dte_caf"><?php _e('Tipo de DTE:', 'simple-dte'); ?></label>
                    <select name="tipo_dte" id="tipo_dte_caf" required>
                        <option value=""><?php _e('Seleccione...', 'simple-dte'); ?></option>
                        <option value="39"><?php _e('39 - Boleta Electrónica', 'simple-dte'); ?></option>
                        <option value="41"><?php _e('41 - Boleta Exenta', 'simple-dte'); ?></option>
                        <option value="61"><?php _e('61 - Nota de Crédito', 'simple-dte'); ?></option>
                    </select>
                </p>
                <p>
                    <input type="file" name="caf_file" accept=".xml" required />
                </p>
                <p>
                    <button type="submit" class="button button-primary"><?php _e('Subir CAF', 'simple-dte'); ?></button>
                </p>
            </form>
        </div>

        <!-- Configuración -->
        <div class="card">
            <h2><?php _e('Configuración', 'simple-dte'); ?></h2>
            <?php Simple_DTE_Settings::render_settings_form(); ?>
        </div>
    </div>
</div>

<style>
.simple-dte-dashboard {
    display: grid;
    gap: 20px;
    margin-top: 20px;
}

.card {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.badge-warning {
    background: #f0b849;
    color: #fff;
}

.progress-bar {
    width: 100px;
    height: 20px;
    background: #f0f0f1;
    border-radius: 3px;
    overflow: hidden;
    display: inline-block;
}

.progress-fill {
    height: 100%;
    background: #2271b1;
    transition: width 0.3s;
}
</style>
