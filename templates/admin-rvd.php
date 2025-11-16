<?php
/**
 * Template: RVD (Registro de Ventas Diarias)
 * Solo disponible en ambiente de certificación
 */

if (!defined('ABSPATH')) {
    exit;
}

// Verificar que estamos en certificación
if (!Simple_DTE_Helpers::is_certificacion()) {
    ?>
    <div class="wrap">
        <h1><?php _e('RVD - Registro de Ventas Diarias', 'simple-dte'); ?></h1>
        <div class="notice notice-warning">
            <p><?php _e('El RVD solo está disponible en ambiente de certificación.', 'simple-dte'); ?></p>
        </div>
    </div>
    <?php
    return;
}
?>

<div class="wrap">
    <h1><?php _e('RVD - Registro de Ventas Diarias', 'simple-dte'); ?></h1>

    <div class="notice notice-info">
        <p>
            <strong><?php _e('¿Qué es el RVD?', 'simple-dte'); ?></strong><br>
            <?php _e('El Registro de Ventas Diarias (antes RCOF) es un reporte que debe enviarse diariamente al SII con las boletas emitidas en el día. Es obligatorio en producción y requerido para la certificación.', 'simple-dte'); ?>
        </p>
    </div>

    <!-- Generar y Enviar RVD -->
    <div class="card">
        <h2><?php _e('Generar RVD', 'simple-dte'); ?></h2>

        <form id="form-generar-rvd">
            <table class="form-table">
                <tr>
                    <th>
                        <label for="fecha_rvd"><?php _e('Fecha:', 'simple-dte'); ?></label>
                    </th>
                    <td>
                        <input type="date" id="fecha_rvd" name="fecha"
                               value="<?php echo esc_attr(date('Y-m-d', strtotime('-1 day'))); ?>"
                               max="<?php echo esc_attr(date('Y-m-d')); ?>" required />
                        <p class="description">
                            <?php _e('Seleccione la fecha para la cual generar el RVD (generalmente el día anterior)', 'simple-dte'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <p>
                <button type="submit" class="button button-primary">
                    <?php _e('Generar RVD', 'simple-dte'); ?>
                </button>
            </p>
        </form>

        <div id="resultado-rvd"></div>

        <!-- Área para mostrar XML y botón de envío -->
        <div id="area-envio-rvd" style="display: none; margin-top: 20px;">
            <h3><?php _e('RVD Generado', 'simple-dte'); ?></h3>

            <div class="rvd-info" style="background: #f0f0f1; padding: 15px; margin-bottom: 15px;">
                <p><strong><?php _e('Fecha:', 'simple-dte'); ?></strong> <span id="rvd-fecha"></span></p>
                <p><strong><?php _e('Cantidad de Boletas:', 'simple-dte'); ?></strong> <span id="rvd-cantidad"></span></p>
            </div>

            <div style="margin-bottom: 15px;">
                <label>
                    <strong><?php _e('XML Generado:', 'simple-dte'); ?></strong>
                </label>
                <textarea id="rvd-xml" readonly style="width: 100%; height: 200px; font-family: monospace; font-size: 12px;"></textarea>
            </div>

            <p>
                <button type="button" id="btn-enviar-rvd" class="button button-primary button-large">
                    <?php _e('Enviar RVD al SII', 'simple-dte'); ?>
                </button>
                <button type="button" id="btn-descargar-rvd" class="button">
                    <?php _e('Descargar XML', 'simple-dte'); ?>
                </button>
            </p>
        </div>
    </div>

    <!-- Configuración de envío automático -->
    <div class="card">
        <h2><?php _e('Envío Automático', 'simple-dte'); ?></h2>

        <form method="post" action="options.php">
            <?php settings_fields('simple_dte_settings'); ?>

            <table class="form-table">
                <tr>
                    <th><?php _e('Activar envío automático:', 'simple-dte'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="simple_dte_rvd_auto" value="1"
                                   <?php checked(get_option('simple_dte_rvd_auto'), 1); ?> />
                            <?php _e('Enviar RVD automáticamente todos los días a las 23:00', 'simple-dte'); ?>
                        </label>
                        <p class="description">
                            <?php _e('El sistema enviará automáticamente el RVD del día anterior cada noche.', 'simple-dte'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button(__('Guardar Configuración', 'simple-dte')); ?>
        </form>
    </div>

    <!-- Historial de envíos -->
    <?php if (!empty($historial)): ?>
    <div class="card">
        <h2><?php _e('Historial de Envíos', 'simple-dte'); ?></h2>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php _e('Fecha RVD', 'simple-dte'); ?></th>
                    <th><?php _e('Fecha de Envío', 'simple-dte'); ?></th>
                    <th><?php _e('Track ID', 'simple-dte'); ?></th>
                    <th><?php _e('Estado', 'simple-dte'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($historial as $registro): ?>
                    <tr>
                        <td><?php echo esc_html(date_i18n('d/m/Y', strtotime($registro['fecha']))); ?></td>
                        <td><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($registro['fecha_envio']))); ?></td>
                        <td><?php echo esc_html($registro['track_id'] ?: '—'); ?></td>
                        <td>
                            <span class="badge badge-success">
                                <?php echo esc_html(ucfirst($registro['estado'])); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<style>
.badge-success {
    background: #28a745;
    color: #fff;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}
</style>
