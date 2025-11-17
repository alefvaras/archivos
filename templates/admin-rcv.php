<?php
/**
 * Template: RCV (Registro de Compras y Ventas)
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Libro de Ventas y Resumen Diario', 'simple-dte'); ?></h1>

    <div style="background: #fff; border-left: 4px solid #2271b1; padding: 12px; margin: 20px 0;">
        <h3 style="margin-top: 0;"><?php _e('📊 Sistema de Reportes al SII', 'simple-dte'); ?></h3>
        <p><strong><?php _e('Libro de Ventas (RCV):', 'simple-dte'); ?></strong> <?php _e('Reporte mensual de todas las ventas. Opcional para certificación, obligatorio en producción.', 'simple-dte'); ?></p>
        <p><strong><?php _e('Resumen Diario (RCOF):', 'simple-dte'); ?></strong> <?php _e('Consolidado diario de boletas. Obligatorio en producción, se envía automáticamente a las 23:00.', 'simple-dte'); ?></p>
    </div>

    <!-- Resumen Diario de Boletas -->
    <div class="card" style="margin-bottom: 20px;">
        <h2><?php _e('🗓️ Resumen Diario de Boletas (RCOF)', 'simple-dte'); ?></h2>
        <p class="description"><?php _e('Consolidado diario de todas las boletas electrónicas (Tipo 39 y 41). Se envía automáticamente al SII todos los días a las 23:00.', 'simple-dte'); ?></p>

        <form id="form-resumen-diario" style="margin-top: 20px;">
            <table class="form-table">
                <tr>
                    <th><label for="fecha_resumen"><?php _e('Fecha:', 'simple-dte'); ?></label></th>
                    <td>
                        <input type="date" id="fecha_resumen" name="fecha" value="<?php echo date('Y-m-d', strtotime('yesterday')); ?>" required />
                        <p class="description"><?php _e('Selecciona el día para generar el resumen. Por defecto muestra ayer.', 'simple-dte'); ?></p>
                    </td>
                </tr>
            </table>

            <p>
                <button type="submit" class="button button-primary">
                    <?php _e('Generar Resumen Diario', 'simple-dte'); ?>
                </button>
                <button type="button" id="btn-enviar-resumen" class="button button-secondary" style="display:none;">
                    <?php _e('Enviar al SII', 'simple-dte'); ?>
                </button>
            </p>
        </form>

        <div id="resultado-resumen-diario"></div>
    </div>

    <!-- Libro de Ventas (RCV) -->
    <div class="card">
        <h2><?php _e('📚 Libro de Ventas (RCV)', 'simple-dte'); ?></h2>
        <p class="description"><?php _e('Reporte de todas las ventas en un período (mensual). Incluye boletas, facturas y notas de crédito.', 'simple-dte'); ?></p>

        <form id="form-generar-rcv" style="margin-top: 20px;">
            <table class="form-table">
                <tr>
                    <th><label for="fecha_desde"><?php _e('Fecha Desde:', 'simple-dte'); ?></label></th>
                    <td>
                        <input type="date" id="fecha_desde" name="fecha_desde" value="<?php echo date('Y-m-01'); ?>" required />
                    </td>
                </tr>
                <tr>
                    <th><label for="fecha_hasta"><?php _e('Fecha Hasta:', 'simple-dte'); ?></label></th>
                    <td>
                        <input type="date" id="fecha_hasta" name="fecha_hasta" value="<?php echo date('Y-m-t'); ?>" required />
                        <p class="description"><?php _e('Generalmente se genera el libro de ventas mensual (del 1 al último día del mes).', 'simple-dte'); ?></p>
                    </td>
                </tr>
            </table>

            <p>
                <button type="submit" class="button button-primary">
                    <?php _e('Generar Libro de Ventas', 'simple-dte'); ?>
                </button>
                <button type="button" id="btn-enviar-rcv" class="button button-secondary" style="display:none;">
                    <?php _e('Enviar al SII', 'simple-dte'); ?>
                </button>
            </p>
        </form>

        <div id="resultado-rcv"></div>
    </div>

    <!-- Estado del Cron -->
    <div class="card" style="margin-top: 20px;">
        <h2><?php _e('⚙️ Envío Automático', 'simple-dte'); ?></h2>
        <?php
        $next_scheduled = wp_next_scheduled('simple_dte_envio_resumen_diario');
        if ($next_scheduled) {
            $next_run = date('d/m/Y H:i:s', $next_scheduled);
            echo '<p><strong>' . __('Próximo envío automático:', 'simple-dte') . '</strong> ' . $next_run . '</p>';
        } else {
            echo '<p style="color: #d63638;"><strong>' . __('⚠️ Envío automático NO programado', 'simple-dte') . '</strong></p>';
        }
        ?>
        <p class="description"><?php _e('El sistema enviará automáticamente el resumen diario de boletas al SII todos los días a las 23:00.', 'simple-dte'); ?></p>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    var xml_resumen = '';
    var xml_rcv = '';

    // Generar Resumen Diario
    $('#form-resumen-diario').on('submit', function(e) {
        e.preventDefault();

        var fecha = $('#fecha_resumen').val();

        $('#resultado-resumen-diario').html('<p><?php _e('Generando resumen diario...', 'simple-dte'); ?></p>');
        $('#btn-enviar-resumen').hide();

        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'simple_dte_generar_resumen_diario',
                nonce: '<?php echo wp_create_nonce('simple_dte_nonce'); ?>',
                fecha: fecha
            },
            success: function(response) {
                if (response.success) {
                    xml_resumen = response.data.xml;

                    var html = '<div class="notice notice-success"><p><strong><?php _e('✅ Resumen diario generado exitosamente', 'simple-dte'); ?></strong></p></div>';
                    html += '<p><strong><?php _e('Cantidad de boletas:', 'simple-dte'); ?></strong> ' + response.data.cantidad_documentos + '</p>';
                    html += '<p><strong><?php _e('Archivo:', 'simple-dte'); ?></strong> ' + response.data.filepath + '</p>';
                    html += '<div style="max-height: 300px; overflow-y: auto; background: #f5f5f5; padding: 10px; border: 1px solid #ddd;"><pre>' + escapeHtml(response.data.xml) + '</pre></div>';

                    $('#resultado-resumen-diario').html(html);
                    $('#btn-enviar-resumen').show();
                } else {
                    $('#resultado-resumen-diario').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                $('#resultado-resumen-diario').html('<div class="notice notice-error"><p><?php _e('Error al generar resumen diario', 'simple-dte'); ?></p></div>');
            }
        });
    });

    // Enviar Resumen al SII
    $('#btn-enviar-resumen').on('click', function() {
        if (!xml_resumen) {
            alert('<?php _e('Primero debes generar el resumen diario', 'simple-dte'); ?>');
            return;
        }

        if (!confirm('<?php _e('¿Enviar resumen diario al SII?', 'simple-dte'); ?>')) {
            return;
        }

        $(this).prop('disabled', true).text('<?php _e('Enviando...', 'simple-dte'); ?>');

        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'simple_dte_enviar_rcv',
                nonce: '<?php echo wp_create_nonce('simple_dte_nonce'); ?>',
                xml: xml_resumen,
                tipo: 'resumen_diario'
            },
            success: function(response) {
                if (response.success) {
                    var html = '<div class="notice notice-success"><p><strong><?php _e('✅ Resumen enviado al SII exitosamente', 'simple-dte'); ?></strong></p>';
                    if (response.data.track_id) {
                        html += '<p><strong>Track ID:</strong> ' + response.data.track_id + '</p>';
                    }
                    html += '</div>';
                    $('#resultado-resumen-diario').prepend(html);
                } else {
                    alert('Error: ' + response.data.message);
                }
                $('#btn-enviar-resumen').prop('disabled', false).text('<?php _e('Enviar al SII', 'simple-dte'); ?>');
            },
            error: function() {
                alert('<?php _e('Error al enviar al SII', 'simple-dte'); ?>');
                $('#btn-enviar-resumen').prop('disabled', false).text('<?php _e('Enviar al SII', 'simple-dte'); ?>');
            }
        });
    });

    // Generar RCV
    $('#form-generar-rcv').on('submit', function(e) {
        e.preventDefault();

        var fecha_desde = $('#fecha_desde').val();
        var fecha_hasta = $('#fecha_hasta').val();

        $('#resultado-rcv').html('<p><?php _e('Generando libro de ventas...', 'simple-dte'); ?></p>');
        $('#btn-enviar-rcv').hide();

        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'simple_dte_generar_rcv',
                nonce: '<?php echo wp_create_nonce('simple_dte_nonce'); ?>',
                fecha_desde: fecha_desde,
                fecha_hasta: fecha_hasta
            },
            success: function(response) {
                if (response.success) {
                    xml_rcv = response.data.xml;

                    var html = '<div class="notice notice-success"><p><strong><?php _e('✅ Libro de ventas generado exitosamente', 'simple-dte'); ?></strong></p></div>';
                    html += '<p><strong><?php _e('Cantidad de documentos:', 'simple-dte'); ?></strong> ' + response.data.cantidad_documentos + '</p>';
                    html += '<p><strong><?php _e('Archivo:', 'simple-dte'); ?></strong> ' + response.data.filepath + '</p>';
                    html += '<div style="max-height: 300px; overflow-y: auto; background: #f5f5f5; padding: 10px; border: 1px solid #ddd;"><pre>' + escapeHtml(response.data.xml) + '</pre></div>';

                    $('#resultado-rcv').html(html);
                    $('#btn-enviar-rcv').show();
                } else {
                    $('#resultado-rcv').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                $('#resultado-rcv').html('<div class="notice notice-error"><p><?php _e('Error al generar libro de ventas', 'simple-dte'); ?></p></div>');
            }
        });
    });

    // Enviar RCV al SII
    $('#btn-enviar-rcv').on('click', function() {
        if (!xml_rcv) {
            alert('<?php _e('Primero debes generar el libro de ventas', 'simple-dte'); ?>');
            return;
        }

        if (!confirm('<?php _e('¿Enviar libro de ventas al SII?', 'simple-dte'); ?>')) {
            return;
        }

        $(this).prop('disabled', true).text('<?php _e('Enviando...', 'simple-dte'); ?>');

        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'simple_dte_enviar_rcv',
                nonce: '<?php echo wp_create_nonce('simple_dte_nonce'); ?>',
                xml: xml_rcv,
                tipo: 'rcv'
            },
            success: function(response) {
                if (response.success) {
                    var html = '<div class="notice notice-success"><p><strong><?php _e('✅ Libro de ventas enviado al SII exitosamente', 'simple-dte'); ?></strong></p>';
                    if (response.data.track_id) {
                        html += '<p><strong>Track ID:</strong> ' + response.data.track_id + '</p>';
                    }
                    html += '</div>';
                    $('#resultado-rcv').prepend(html);
                } else {
                    alert('Error: ' + response.data.message);
                }
                $('#btn-enviar-rcv').prop('disabled', false).text('<?php _e('Enviar al SII', 'simple-dte'); ?>');
            },
            error: function() {
                alert('<?php _e('Error al enviar al SII', 'simple-dte'); ?>');
                $('#btn-enviar-rcv').prop('disabled', false).text('<?php _e('Enviar al SII', 'simple-dte'); ?>');
            }
        });
    });

    // Escape HTML helper
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});
</script>
