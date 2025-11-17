<?php
/**
 * Configuración del plugin
 *
 * @package Simple_DTE
 */

if (!defined('ABSPATH')) {
    exit;
}

class Simple_DTE_Settings {

    /**
     * Inicializar
     */
    public static function init() {
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_media_uploader'));
    }

    /**
     * Cargar WordPress Media Uploader
     */
    public static function enqueue_media_uploader($hook) {
        // Solo cargar en la página de configuración
        if ('toplevel_page_simple-dte' !== $hook && 'simple-dte_page_simple-dte-settings' !== $hook) {
            return;
        }
        wp_enqueue_media();
    }

    /**
     * Registrar configuraciones
     */
    public static function register_settings() {
        // Sección API
        register_setting('simple_dte_settings', 'simple_dte_ambiente');
        register_setting('simple_dte_settings', 'simple_dte_api_key');
        register_setting('simple_dte_settings', 'simple_dte_debug');

        // Sección Emisor
        register_setting('simple_dte_settings', 'simple_dte_rut_emisor');
        register_setting('simple_dte_settings', 'simple_dte_razon_social');
        register_setting('simple_dte_settings', 'simple_dte_giro');
        register_setting('simple_dte_settings', 'simple_dte_direccion');
        register_setting('simple_dte_settings', 'simple_dte_comuna');
        register_setting('simple_dte_settings', 'simple_dte_logo_url');

        // Sección Certificado
        register_setting('simple_dte_settings', 'simple_dte_cert_rut');
        register_setting('simple_dte_settings', 'simple_dte_cert_password');
        register_setting('simple_dte_settings', 'simple_dte_cert_path');

        // Sección Boletas de Ajuste
        register_setting('simple_dte_settings', 'simple_dte_auto_ajuste_enabled');
    }

    /**
     * Renderizar formulario de configuración
     */
    public static function render_settings_form() {
        ?>
        <form method="post" action="options.php" enctype="multipart/form-data">
            <?php settings_fields('simple_dte_settings'); ?>

            <table class="form-table">
                <tr>
                    <th colspan="2">
                        <h2><?php _e('Configuración de API', 'simple-dte'); ?></h2>
                    </th>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="simple_dte_ambiente"><?php _e('Ambiente', 'simple-dte'); ?></label>
                    </th>
                    <td>
                        <select name="simple_dte_ambiente" id="simple_dte_ambiente">
                            <option value="certificacion" <?php selected(get_option('simple_dte_ambiente'), 'certificacion'); ?>>
                                <?php _e('Certificación/Pruebas', 'simple-dte'); ?>
                            </option>
                            <option value="produccion" <?php selected(get_option('simple_dte_ambiente'), 'produccion'); ?>>
                                <?php _e('Producción', 'simple-dte'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php _e('IMPORTANTE: Use Certificación para pruebas. Cambie a Producción solo cuando esté listo.', 'simple-dte'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="simple_dte_api_key"><?php _e('API Key', 'simple-dte'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="simple_dte_api_key" id="simple_dte_api_key"
                               value="<?php echo esc_attr(get_option('simple_dte_api_key')); ?>"
                               class="regular-text" />
                        <p class="description">
                            <?php _e('Obtenga su API Key en simpleapi.cl', 'simple-dte'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="simple_dte_debug"><?php _e('Modo Debug', 'simple-dte'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" name="simple_dte_debug" id="simple_dte_debug" value="1"
                               <?php checked(get_option('simple_dte_debug'), 1); ?> />
                        <?php _e('Activar logs detallados', 'simple-dte'); ?>
                    </td>
                </tr>

                <tr>
                    <th colspan="2">
                        <h2><?php _e('Datos del Emisor', 'simple-dte'); ?></h2>
                    </th>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="simple_dte_rut_emisor"><?php _e('RUT Emisor', 'simple-dte'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="simple_dte_rut_emisor" id="simple_dte_rut_emisor"
                               value="<?php echo esc_attr(get_option('simple_dte_rut_emisor')); ?>"
                               placeholder="12345678-9" />
                        <p class="description"><?php _e('Con guión y dígito verificador', 'simple-dte'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="simple_dte_razon_social"><?php _e('Razón Social', 'simple-dte'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="simple_dte_razon_social" id="simple_dte_razon_social"
                               value="<?php echo esc_attr(get_option('simple_dte_razon_social')); ?>"
                               class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="simple_dte_giro"><?php _e('Giro', 'simple-dte'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="simple_dte_giro" id="simple_dte_giro"
                               value="<?php echo esc_attr(get_option('simple_dte_giro')); ?>"
                               class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="simple_dte_direccion"><?php _e('Dirección', 'simple-dte'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="simple_dte_direccion" id="simple_dte_direccion"
                               value="<?php echo esc_attr(get_option('simple_dte_direccion')); ?>"
                               class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="simple_dte_comuna"><?php _e('Comuna', 'simple-dte'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="simple_dte_comuna" id="simple_dte_comuna"
                               value="<?php echo esc_attr(get_option('simple_dte_comuna')); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="simple_dte_logo_url"><?php _e('Logo de la Empresa', 'simple-dte'); ?></label>
                    </th>
                    <td>
                        <?php
                        $logo_url = get_option('simple_dte_logo_url');
                        ?>
                        <div class="simple-dte-logo-upload">
                            <input type="hidden" name="simple_dte_logo_url" id="simple_dte_logo_url"
                                   value="<?php echo esc_attr($logo_url); ?>" />

                            <div class="logo-preview" style="margin-bottom: 10px;">
                                <?php if ($logo_url): ?>
                                    <img src="<?php echo esc_url($logo_url); ?>"
                                         style="max-width: 200px; max-height: 100px; border: 1px solid #ddd; padding: 5px; background: #fff;"
                                         alt="Logo" id="logo-preview-img" />
                                <?php else: ?>
                                    <div id="logo-preview-img" style="display: none;">
                                        <img src="" style="max-width: 200px; max-height: 100px; border: 1px solid #ddd; padding: 5px; background: #fff;" alt="Logo" />
                                    </div>
                                <?php endif; ?>
                            </div>

                            <button type="button" class="button button-secondary" id="upload-logo-button">
                                <?php echo $logo_url ? __('Cambiar Logo', 'simple-dte') : __('Subir Logo', 'simple-dte'); ?>
                            </button>

                            <?php if ($logo_url): ?>
                                <button type="button" class="button button-link-delete" id="remove-logo-button" style="margin-left: 10px;">
                                    <?php _e('Eliminar Logo', 'simple-dte'); ?>
                                </button>
                            <?php endif; ?>

                            <p class="description">
                                <?php _e('Sube el logo de tu empresa para que aparezca en las boletas. Tamaño recomendado: 200x100 píxeles. Formatos: JPG, PNG.', 'simple-dte'); ?>
                            </p>
                        </div>

                        <script type="text/javascript">
                        jQuery(document).ready(function($) {
                            var logoFrame;

                            // Abrir media uploader
                            $('#upload-logo-button').on('click', function(e) {
                                e.preventDefault();

                                if (logoFrame) {
                                    logoFrame.open();
                                    return;
                                }

                                logoFrame = wp.media({
                                    title: '<?php _e('Seleccionar Logo', 'simple-dte'); ?>',
                                    button: {
                                        text: '<?php _e('Usar este logo', 'simple-dte'); ?>'
                                    },
                                    library: {
                                        type: ['image']
                                    },
                                    multiple: false
                                });

                                logoFrame.on('select', function() {
                                    var attachment = logoFrame.state().get('selection').first().toJSON();
                                    $('#simple_dte_logo_url').val(attachment.url);
                                    $('#logo-preview-img').html('<img src="' + attachment.url + '" style="max-width: 200px; max-height: 100px; border: 1px solid #ddd; padding: 5px; background: #fff;" alt="Logo" />').show();
                                    $('#upload-logo-button').text('<?php _e('Cambiar Logo', 'simple-dte'); ?>');

                                    if ($('#remove-logo-button').length === 0) {
                                        $('#upload-logo-button').after('<button type="button" class="button button-link-delete" id="remove-logo-button" style="margin-left: 10px;"><?php _e('Eliminar Logo', 'simple-dte'); ?></button>');
                                    }
                                });

                                logoFrame.open();
                            });

                            // Eliminar logo
                            $(document).on('click', '#remove-logo-button', function(e) {
                                e.preventDefault();
                                $('#simple_dte_logo_url').val('');
                                $('#logo-preview-img').hide().html('');
                                $('#upload-logo-button').text('<?php _e('Subir Logo', 'simple-dte'); ?>');
                                $(this).remove();
                            });
                        });
                        </script>
                    </td>
                </tr>

                <tr>
                    <th colspan="2">
                        <h2><?php _e('Certificado Digital', 'simple-dte'); ?></h2>
                    </th>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="simple_dte_cert_rut"><?php _e('RUT del Certificado', 'simple-dte'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="simple_dte_cert_rut" id="simple_dte_cert_rut"
                               value="<?php echo esc_attr(get_option('simple_dte_cert_rut')); ?>"
                               placeholder="12345678-9" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="simple_dte_cert_password"><?php _e('Contraseña del Certificado', 'simple-dte'); ?></label>
                    </th>
                    <td>
                        <input type="password" name="simple_dte_cert_password" id="simple_dte_cert_password"
                               value="<?php echo esc_attr(get_option('simple_dte_cert_password')); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="cert_file_upload"><?php _e('Archivo PFX', 'simple-dte'); ?></label>
                    </th>
                    <td>
                        <input type="file" name="cert_file" id="cert_file_upload" accept=".pfx,.p12" />
                        <p class="description">
                            <?php
                            $cert_path = get_option('simple_dte_cert_path');
                            if ($cert_path && file_exists($cert_path)) {
                                echo '✓ ' . __('Certificado cargado', 'simple-dte');
                            } else {
                                _e('No hay certificado cargado', 'simple-dte');
                            }
                            ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th colspan="2">
                        <h2><?php _e('Boletas de Ajuste', 'simple-dte'); ?></h2>
                    </th>
                </tr>
                <tr>
                    <td colspan="2">
                        <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin: 10px 0;">
                            <p style="margin: 0;"><strong><?php _e('ℹ️ Importante:', 'simple-dte'); ?></strong> <?php _e('Según normativa SII, las boletas NO usan Notas de Crédito. Para anular o corregir boletas se usan Boletas de Ajuste, que se reportan en el Resumen Diario como folios anulados.', 'simple-dte'); ?></p>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="simple_dte_auto_ajuste_enabled"><?php _e('Anular boletas automáticamente', 'simple-dte'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" name="simple_dte_auto_ajuste_enabled" id="simple_dte_auto_ajuste_enabled" value="1"
                               <?php checked(get_option('simple_dte_auto_ajuste_enabled'), 1); ?> />
                        <?php _e('Registrar boleta como anulada cuando se cree un reembolso total en WooCommerce', 'simple-dte'); ?>
                        <p class="description">
                            <?php _e('Las boletas anuladas se reportarán automáticamente en el Resumen Diario (RCOF) del día siguiente.', 'simple-dte'); ?><br>
                            <?php _e('<strong>Nota:</strong> Las boletas electrónicas NO se pueden anular una vez emitidas al SII. Este registro es solo para efectos contables internos y reporte en el resumen diario.', 'simple-dte'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>

        <?php
        // Procesar upload del certificado
        if (!empty($_FILES['cert_file']['name'])) {
            self::process_cert_upload();
        }
    }

    /**
     * Procesar upload del certificado
     */
    private static function process_cert_upload() {
        if (empty($_FILES['cert_file'])) {
            return;
        }

        $file = $_FILES['cert_file'];

        $validation = Simple_DTE_Helpers::validate_upload($file, array('pfx', 'p12'));

        if (!$validation['success']) {
            add_settings_error('simple_dte_settings', 'cert_upload', $validation['message']);
            return;
        }

        $upload_dir = Simple_DTE_Helpers::create_secure_upload_dir();
        $filename = 'certificado-' . time() . '.pfx';
        $filepath = $upload_dir . $filename;

        if (@move_uploaded_file($file['tmp_name'], $filepath)) {
            @chmod($filepath, 0600);
            update_option('simple_dte_cert_path', $filepath);
            add_settings_error('simple_dte_settings', 'cert_upload', __('Certificado cargado correctamente', 'simple-dte'), 'updated');
        } else {
            add_settings_error('simple_dte_settings', 'cert_upload', __('Error al cargar certificado', 'simple-dte'));
        }
    }
}
