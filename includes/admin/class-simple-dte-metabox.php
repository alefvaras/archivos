<?php
/**
 * Metabox para órdenes de WooCommerce
 *
 * @package Simple_DTE
 */

if (!defined('ABSPATH')) {
    exit;
}

class Simple_DTE_Metabox {

    /**
     * Inicializar
     */
    public static function init() {
        add_action('add_meta_boxes', array(__CLASS__, 'add_metabox'));
    }

    /**
     * Agregar metabox
     */
    public static function add_metabox() {
        $screen = class_exists('Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController') &&
                  wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';

        add_meta_box(
            'simple_dte_metabox',
            __('Simple DTE - Documentos Tributarios', 'simple-dte'),
            array(__CLASS__, 'render_metabox'),
            $screen,
            'side',
            'high'
        );
    }

    /**
     * Renderizar metabox
     */
    public static function render_metabox($post_or_order) {
        $order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order($post_or_order->ID);

        if (!$order) {
            echo '<p>' . __('No se pudo cargar la orden', 'simple-dte') . '</p>';
            return;
        }

        $tiene_dte = $order->get_meta('_simple_dte_generada');
        $folio = $order->get_meta('_simple_dte_folio');
        $tipo = $order->get_meta('_simple_dte_tipo');
        $fecha = $order->get_meta('_simple_dte_fecha_generacion');

        $tiene_nc = $order->get_meta('_simple_dte_nc_generada');
        $folio_nc = $order->get_meta('_simple_dte_nc_folio');

        $ambiente = Simple_DTE_Helpers::get_ambiente();

        wp_nonce_field('simple_dte_metabox', 'simple_dte_metabox_nonce');
        ?>

        <div class="simple-dte-metabox">
            <p>
                <strong><?php _e('Ambiente:', 'simple-dte'); ?></strong>
                <span class="badge badge-<?php echo esc_attr($ambiente); ?>">
                    <?php echo esc_html(ucfirst($ambiente)); ?>
                </span>
            </p>

            <?php if ($tiene_dte === 'yes'): ?>
                <div class="dte-info">
                    <p><strong><?php _e('Boleta/Factura:', 'simple-dte'); ?></strong></p>
                    <p>Folio: <strong><?php echo esc_html($folio); ?></strong></p>
                    <p>Tipo: <?php echo esc_html(self::get_tipo_nombre($tipo)); ?></p>
                    <?php if ($fecha): ?>
                        <p>Fecha: <?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($fecha))); ?></p>
                    <?php endif; ?>
                </div>

                <hr>

                <div class="dte-actions">
                    <?php if ($tiene_nc !== 'yes'): ?>
                        <p>
                            <label for="codigo_ref"><?php _e('Tipo de Nota de Crédito:', 'simple-dte'); ?></label>
                            <select id="codigo_ref" class="widefat">
                                <option value="1"><?php _e('1 - Anulación', 'simple-dte'); ?></option>
                                <option value="2"><?php _e('2 - Corregir texto', 'simple-dte'); ?></option>
                                <option value="3"><?php _e('3 - Corregir montos', 'simple-dte'); ?></option>
                            </select>
                        </p>
                        <p>
                            <button type="button" class="button button-secondary widefat simple-dte-generar-nc"
                                    data-order-id="<?php echo esc_attr($order->get_id()); ?>">
                                <?php _e('Generar Nota de Crédito', 'simple-dte'); ?>
                            </button>
                        </p>
                    <?php else: ?>
                        <p><strong><?php _e('Nota de Crédito generada', 'simple-dte'); ?></strong></p>
                        <p>Folio N/C: <strong><?php echo esc_html($folio_nc); ?></strong></p>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <p class="description"><?php _e('No hay documento generado', 'simple-dte'); ?></p>

                <hr>

                <p>
                    <label for="caso_prueba"><?php _e('Caso de Prueba (opcional):', 'simple-dte'); ?></label>
                    <select id="caso_prueba" class="widefat">
                        <option value=""><?php _e('Sin set de prueba', 'simple-dte'); ?></option>
                        <option value="CASO-1">CASO-1</option>
                        <option value="CASO-2">CASO-2</option>
                        <option value="CASO-3">CASO-3</option>
                        <option value="CASO-4">CASO-4</option>
                        <option value="CASO-5">CASO-5</option>
                    </select>
                </p>

                <p>
                    <button type="button" class="button button-primary widefat simple-dte-generar-boleta"
                            data-order-id="<?php echo esc_attr($order->get_id()); ?>">
                        <?php _e('Generar Boleta Electrónica', 'simple-dte'); ?>
                    </button>
                </p>
            <?php endif; ?>

            <div class="simple-dte-messages" style="margin-top: 10px;"></div>
        </div>
        <?php
    }

    /**
     * Obtener nombre del tipo de DTE
     */
    private static function get_tipo_nombre($tipo) {
        $tipos = Simple_DTE_Helpers::get_tipos_dte();
        return isset($tipos[$tipo]) ? $tipos[$tipo] : 'Tipo ' . $tipo;
    }
}
