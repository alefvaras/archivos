<?php
/**
 * Plugin Name: Facturación Simple API Integration
 * Plugin URI: https://tu-sitio.cl
 * Description: Integración de facturación electrónica para WooCommerce
 * Version: 1.0.0
 * Author: Tu Nombre
 * License: GPL v2 or later
 * Text Domain: simple-facturacion
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
define('SF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SF_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SF_PLUGIN_VERSION', '1.0.0');

/**
 * Clase principal del plugin
 */
class SimpleFacturacion {
    
    private static $instance = null;
    private $api_endpoint;
    private $api_key;
    private $api_secret;
    
    /**
     * Singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->load_settings();
    }
    
    /**
     * Inicializar hooks de WordPress/WooCommerce
     */
    private function init_hooks() {
        // Hook para cuando se completa una orden
        add_action('woocommerce_order_status_completed', array($this, 'generar_factura'));
        add_action('woocommerce_order_status_processing', array($this, 'generar_factura'));
        
        // Agregar menú de configuración
        add_action('admin_menu', array($this, 'agregar_menu_admin'));
        
        // Registrar configuraciones
        add_action('admin_init', array($this, 'registrar_configuraciones'));
        
        // Agregar campos personalizados al checkout
        add_action('woocommerce_after_order_notes', array($this, 'campos_facturacion_checkout'));
        
        // Guardar campos personalizados
        add_action('woocommerce_checkout_update_order_meta', array($this, 'guardar_campos_facturacion'));
        
        // Validar campos requeridos
        add_action('woocommerce_checkout_process', array($this, 'validar_campos_facturacion'));
        
        // Agregar columna en listado de órdenes
        add_filter('manage_edit-shop_order_columns', array($this, 'agregar_columna_factura'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'mostrar_columna_factura'), 10, 2);
        
        // Ajax handlers
        add_action('wp_ajax_sf_reenviar_factura', array($this, 'ajax_reenviar_factura'));
        add_action('wp_ajax_sf_validar_rut', array($this, 'ajax_validar_rut'));
    }
    
    /**
     * Cargar configuraciones
     */
    private function load_settings() {
        $this->api_endpoint = get_option('sf_api_endpoint', '');
        $this->api_key = get_option('sf_api_key', '');
        $this->api_secret = get_option('sf_api_secret', '');
    }
    
    /**
     * Agregar menú en el admin
     */
    public function agregar_menu_admin() {
        add_submenu_page(
            'woocommerce',
            'Configuración Facturación',
            'Facturación Simple',
            'manage_woocommerce',
            'simple-facturacion',
            array($this, 'pagina_configuracion')
        );
    }
    
    /**
     * Página de configuración
     */
    public function pagina_configuracion() {
        ?>
        <div class="wrap">
            <h1>Configuración de Facturación Simple</h1>
            <form method="post" action="options.php">
                <?php settings_fields('sf_settings_group'); ?>
                <?php do_settings_sections('simple-facturacion'); ?>
                <?php submit_button(); ?>
            </form>
            
            <div class="sf-stats">
                <h2>Estadísticas</h2>
                <?php $this->mostrar_estadisticas(); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Registrar configuraciones
     */
    public function registrar_configuraciones() {
        register_setting('sf_settings_group', 'sf_api_endpoint');
        register_setting('sf_settings_group', 'sf_api_key');
        register_setting('sf_settings_group', 'sf_api_secret');
        register_setting('sf_settings_group', 'sf_modo_prueba');
        register_setting('sf_settings_group', 'sf_tipo_documento_default');
        register_setting('sf_settings_group', 'sf_emisor_rut');
        register_setting('sf_settings_group', 'sf_emisor_razon_social');
        register_setting('sf_settings_group', 'sf_emisor_giro');
        register_setting('sf_settings_group', 'sf_emisor_direccion');
        register_setting('sf_settings_group', 'sf_emisor_comuna');
        
        add_settings_section(
            'sf_api_section',
            'Configuración de API',
            array($this, 'sf_api_section_callback'),
            'simple-facturacion'
        );
        
        add_settings_field(
            'sf_api_endpoint',
            'URL del Endpoint API',
            array($this, 'sf_api_endpoint_callback'),
            'simple-facturacion',
            'sf_api_section'
        );
        
        add_settings_field(
            'sf_api_key',
            'API Key',
            array($this, 'sf_api_key_callback'),
            'simple-facturacion',
            'sf_api_section'
        );
        
        add_settings_field(
            'sf_api_secret',
            'API Secret',
            array($this, 'sf_api_secret_callback'),
            'simple-facturacion',
            'sf_api_section'
        );
        
        add_settings_field(
            'sf_modo_prueba',
            'Modo Prueba',
            array($this, 'sf_modo_prueba_callback'),
            'simple-facturacion',
            'sf_api_section'
        );
        
        // Sección datos emisor
        add_settings_section(
            'sf_emisor_section',
            'Datos del Emisor',
            array($this, 'sf_emisor_section_callback'),
            'simple-facturacion'
        );
        
        add_settings_field(
            'sf_emisor_rut',
            'RUT Emisor',
            array($this, 'sf_emisor_rut_callback'),
            'simple-facturacion',
            'sf_emisor_section'
        );
        
        add_settings_field(
            'sf_emisor_razon_social',
            'Razón Social',
            array($this, 'sf_emisor_razon_social_callback'),
            'simple-facturacion',
            'sf_emisor_section'
        );
    }
    
    /**
     * Callbacks para campos de configuración
     */
    public function sf_api_section_callback() {
        echo '<p>Configure los datos de conexión con su servicio de facturación electrónica.</p>';
    }
    
    public function sf_api_endpoint_callback() {
        $value = get_option('sf_api_endpoint');
        echo '<input type="url" name="sf_api_endpoint" value="' . esc_attr($value) . '" class="regular-text" placeholder="https://api.tuservicio.cl/v1/" />';
    }
    
    public function sf_api_key_callback() {
        $value = get_option('sf_api_key');
        echo '<input type="text" name="sf_api_key" value="' . esc_attr($value) . '" class="regular-text" />';
    }
    
    public function sf_api_secret_callback() {
        $value = get_option('sf_api_secret');
        echo '<input type="password" name="sf_api_secret" value="' . esc_attr($value) . '" class="regular-text" />';
    }
    
    public function sf_modo_prueba_callback() {
        $value = get_option('sf_modo_prueba');
        echo '<input type="checkbox" name="sf_modo_prueba" value="1" ' . checked(1, $value, false) . ' /> Activar modo prueba';
    }
    
    public function sf_emisor_section_callback() {
        echo '<p>Datos de su empresa que aparecerán en las facturas.</p>';
    }
    
    public function sf_emisor_rut_callback() {
        $value = get_option('sf_emisor_rut');
        echo '<input type="text" name="sf_emisor_rut" value="' . esc_attr($value) . '" placeholder="12.345.678-9" />';
    }
    
    public function sf_emisor_razon_social_callback() {
        $value = get_option('sf_emisor_razon_social');
        echo '<input type="text" name="sf_emisor_razon_social" value="' . esc_attr($value) . '" class="regular-text" />';
    }
    
    /**
     * Campos adicionales en el checkout
     */
    public function campos_facturacion_checkout($checkout) {
        echo '<div id="campos_facturacion_chile"><h3>Datos para Facturación Electrónica</h3>';
        
        // Tipo de documento
        woocommerce_form_field('tipo_documento', array(
            'type' => 'select',
            'class' => array('form-row-wide'),
            'label' => __('Tipo de Documento'),
            'required' => true,
            'options' => array(
                '' => 'Seleccione...',
                'boleta' => 'Boleta',
                'factura' => 'Factura'
            )
        ), $checkout->get_value('tipo_documento'));
        
        // RUT
        woocommerce_form_field('rut_cliente', array(
            'type' => 'text',
            'class' => array('form-row-first'),
            'label' => __('RUT'),
            'placeholder' => '12.345.678-9',
            'required' => false,
        ), $checkout->get_value('rut_cliente'));
        
        // Razón Social (para facturas)
        woocommerce_form_field('razon_social', array(
            'type' => 'text',
            'class' => array('form-row-last'),
            'label' => __('Razón Social'),
            'required' => false,
        ), $checkout->get_value('razon_social'));
        
        // Giro (para facturas)
        woocommerce_form_field('giro', array(
            'type' => 'text',
            'class' => array('form-row-wide'),
            'label' => __('Giro'),
            'required' => false,
        ), $checkout->get_value('giro'));
        
        echo '</div>';
        
        // JavaScript para mostrar/ocultar campos según tipo de documento
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#tipo_documento').change(function() {
                if ($(this).val() == 'factura') {
                    $('#rut_cliente_field, #razon_social_field, #giro_field').show();
                    $('#rut_cliente, #razon_social, #giro').prop('required', true);
                } else {
                    $('#razon_social_field, #giro_field').hide();
                    $('#razon_social, #giro').prop('required', false);
                }
            }).change();
            
            // Validar RUT
            $('#rut_cliente').on('blur', function() {
                var rut = $(this).val();
                if (rut) {
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'sf_validar_rut',
                            rut: rut
                        },
                        success: function(response) {
                            if (!response.success) {
                                alert('RUT inválido');
                            }
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Guardar campos de facturación
     */
    public function guardar_campos_facturacion($order_id) {
        if (!empty($_POST['tipo_documento'])) {
            update_post_meta($order_id, 'tipo_documento', sanitize_text_field($_POST['tipo_documento']));
        }
        
        if (!empty($_POST['rut_cliente'])) {
            update_post_meta($order_id, 'rut_cliente', sanitize_text_field($_POST['rut_cliente']));
        }
        
        if (!empty($_POST['razon_social'])) {
            update_post_meta($order_id, 'razon_social', sanitize_text_field($_POST['razon_social']));
        }
        
        if (!empty($_POST['giro'])) {
            update_post_meta($order_id, 'giro', sanitize_text_field($_POST['giro']));
        }
    }
    
    /**
     * Validar campos requeridos
     */
    public function validar_campos_facturacion() {
        if ($_POST['tipo_documento'] == 'factura') {
            if (empty($_POST['rut_cliente'])) {
                wc_add_notice('Por favor ingrese el RUT para la factura.', 'error');
            }
            if (empty($_POST['razon_social'])) {
                wc_add_notice('Por favor ingrese la Razón Social para la factura.', 'error');
            }
            if (empty($_POST['giro'])) {
                wc_add_notice('Por favor ingrese el Giro para la factura.', 'error');
            }
        }
    }
    
    /**
     * Generar factura cuando se completa una orden
     */
    public function generar_factura($order_id) {
        $order = wc_get_order($order_id);
        
        // Verificar si ya se generó factura
        $factura_id = get_post_meta($order_id, '_factura_id', true);
        if ($factura_id) {
            return; // Ya existe factura
        }
        
        // Preparar datos para la API
        $datos_factura = $this->preparar_datos_factura($order);
        
        // Enviar a la API
        $respuesta = $this->enviar_factura_api($datos_factura);
        
        if ($respuesta && isset($respuesta['id'])) {
            // Guardar ID de factura
            update_post_meta($order_id, '_factura_id', $respuesta['id']);
            update_post_meta($order_id, '_factura_numero', $respuesta['numero']);
            update_post_meta($order_id, '_factura_fecha', current_time('mysql'));
            
            // Agregar nota a la orden
            $order->add_order_note(sprintf(
                'Factura electrónica generada exitosamente. Número: %s',
                $respuesta['numero']
            ));
            
            // Enviar email al cliente con la factura
            $this->enviar_email_factura($order, $respuesta);
        } else {
            // Log error
            $order->add_order_note('Error al generar factura electrónica. Por favor revisar logs.');
            error_log('Error generando factura para orden ' . $order_id);
        }
    }
    
    /**
     * Preparar datos de la factura
     */
    private function preparar_datos_factura($order) {
        $tipo_documento = get_post_meta($order->get_id(), 'tipo_documento', true);
        
        $datos = array(
            'tipo_documento' => $tipo_documento ?: 'boleta',
            'fecha' => current_time('Y-m-d'),
            'orden_id' => $order->get_id(),
            
            // Datos del emisor (desde configuración)
            'emisor' => array(
                'rut' => get_option('sf_emisor_rut'),
                'razon_social' => get_option('sf_emisor_razon_social'),
                'giro' => get_option('sf_emisor_giro'),
                'direccion' => get_option('sf_emisor_direccion'),
                'comuna' => get_option('sf_emisor_comuna')
            ),
            
            // Datos del receptor
            'receptor' => array(
                'rut' => get_post_meta($order->get_id(), 'rut_cliente', true),
                'razon_social' => get_post_meta($order->get_id(), 'razon_social', true) ?: $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'giro' => get_post_meta($order->get_id(), 'giro', true),
                'direccion' => $order->get_billing_address_1(),
                'comuna' => $order->get_billing_city(),
                'email' => $order->get_billing_email(),
                'telefono' => $order->get_billing_phone()
            ),
            
            // Detalle de items
            'detalle' => array(),
            
            // Totales
            'neto' => 0,
            'iva' => 0,
            'total' => $order->get_total()
        );
        
        // Agregar items
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $precio_unitario = $item->get_total() / $item->get_quantity();
            
            $datos['detalle'][] = array(
                'codigo' => $product->get_sku() ?: $product->get_id(),
                'nombre' => $item->get_name(),
                'cantidad' => $item->get_quantity(),
                'precio_unitario' => round($precio_unitario),
                'descuento' => 0,
                'total' => round($item->get_total())
            );
        }
        
        // Calcular IVA y neto (asumiendo que todos los precios incluyen IVA)
        $datos['neto'] = round($datos['total'] / 1.19);
        $datos['iva'] = $datos['total'] - $datos['neto'];
        
        // Agregar envío si existe
        if ($order->get_shipping_total() > 0) {
            $datos['detalle'][] = array(
                'codigo' => 'ENVIO',
                'nombre' => 'Gastos de envío',
                'cantidad' => 1,
                'precio_unitario' => round($order->get_shipping_total()),
                'total' => round($order->get_shipping_total())
            );
        }
        
        return $datos;
    }
    
    /**
     * Enviar factura a la API
     */
    private function enviar_factura_api($datos) {
        if (empty($this->api_endpoint) || empty($this->api_key)) {
            return false;
        }
        
        $headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->api_key,
            'X-API-Secret' => $this->api_secret
        );
        
        $response = wp_remote_post($this->api_endpoint . '/facturas', array(
            'headers' => $headers,
            'body' => json_encode($datos),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('Error enviando factura: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (wp_remote_retrieve_response_code($response) != 200) {
            error_log('Error API facturación: ' . $body);
            return false;
        }
        
        return $data;
    }
    
    /**
     * Enviar email con la factura
     */
    private function enviar_email_factura($order, $factura_data) {
        $to = $order->get_billing_email();
        $subject = 'Su factura electrónica - Orden #' . $order->get_id();
        
        $message = 'Estimado(a) ' . $order->get_billing_first_name() . ",\n\n";
        $message .= 'Su factura electrónica ha sido generada exitosamente.\n';
        $message .= 'Número de factura: ' . $factura_data['numero'] . "\n";
        
        if (isset($factura_data['pdf_url'])) {
            $message .= 'Puede descargar su factura en: ' . $factura_data['pdf_url'] . "\n";
        }
        
        $message .= "\n\nGracias por su compra.\n";
        
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        
        wp_mail($to, $subject, $message, $headers);
    }
    
    /**
     * Agregar columna de factura en listado de órdenes
     */
    public function agregar_columna_factura($columns) {
        $columns['factura'] = 'Factura';
        return $columns;
    }
    
    /**
     * Mostrar contenido de columna factura
     */
    public function mostrar_columna_factura($column, $post_id) {
        if ($column === 'factura') {
            $factura_numero = get_post_meta($post_id, '_factura_numero', true);
            if ($factura_numero) {
                echo '<span style="color: green;">✓ ' . esc_html($factura_numero) . '</span>';
                echo '<br><a href="#" class="sf-reenviar-factura" data-order-id="' . $post_id . '">Reenviar</a>';
            } else {
                echo '<span style="color: gray;">Sin factura</span>';
                echo '<br><a href="#" class="sf-generar-factura" data-order-id="' . $post_id . '">Generar</a>';
            }
        }
    }
    
    /**
     * AJAX: Reenviar factura
     */
    public function ajax_reenviar_factura() {
        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);
        
        if ($order) {
            $factura_data = array(
                'numero' => get_post_meta($order_id, '_factura_numero', true),
                'id' => get_post_meta($order_id, '_factura_id', true)
            );
            
            $this->enviar_email_factura($order, $factura_data);
            wp_send_json_success('Factura reenviada');
        } else {
            wp_send_json_error('Orden no encontrada');
        }
    }
    
    /**
     * AJAX: Validar RUT
     */
    public function ajax_validar_rut() {
        $rut = sanitize_text_field($_POST['rut']);
        $valido = $this->validar_rut_chile($rut);
        
        if ($valido) {
            wp_send_json_success('RUT válido');
        } else {
            wp_send_json_error('RUT inválido');
        }
    }
    
    /**
     * Validar RUT chileno
     */
    private function validar_rut_chile($rut) {
        $rut = preg_replace('/[^0-9kK]/', '', $rut);
        
        if (strlen($rut) < 8 || strlen($rut) > 9) {
            return false;
        }
        
        $dv = substr($rut, -1);
        $numero = substr($rut, 0, -1);
        
        $suma = 0;
        $factor = 2;
        
        for ($i = strlen($numero) - 1; $i >= 0; $i--) {
            $suma += $factor * $numero[$i];
            $factor = $factor == 7 ? 2 : $factor + 1;
        }
        
        $dv_calculado = 11 - ($suma % 11);
        
        if ($dv_calculado == 11) {
            $dv_calculado = '0';
        } elseif ($dv_calculado == 10) {
            $dv_calculado = 'K';
        }
        
        return strtoupper($dv) == strtoupper($dv_calculado);
    }
    
    /**
     * Mostrar estadísticas
     */
    private function mostrar_estadisticas() {
        global $wpdb;
        
        // Contar facturas emitidas este mes
        $facturas_mes = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_factura_id' 
            AND MONTH(FROM_UNIXTIME(meta_value)) = MONTH(CURRENT_DATE())
        ");
        
        echo '<p>Facturas emitidas este mes: <strong>' . $facturas_mes . '</strong></p>';
        
        // Mostrar estado de conexión API
        $test = $this->test_conexion_api();
        if ($test) {
            echo '<p style="color: green;">✓ Conexión API activa</p>';
        } else {
            echo '<p style="color: red;">✗ Error de conexión API</p>';
        }
    }
    
    /**
     * Test de conexión con la API
     */
    private function test_conexion_api() {
        if (empty($this->api_endpoint) || empty($this->api_key)) {
            return false;
        }
        
        $response = wp_remote_get($this->api_endpoint . '/status', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key
            ),
            'timeout' => 10
        ));
        
        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200;
    }
}

// Inicializar el plugin
add_action('plugins_loaded', function() {
    if (class_exists('WooCommerce')) {
        SimpleFacturacion::get_instance();
    } else {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>El plugin de Facturación Simple requiere WooCommerce para funcionar.</p></div>';
        });
    }
});

// Activación del plugin
register_activation_hook(__FILE__, function() {
    // Crear tablas si es necesario
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}facturacion_log (
        id int(11) NOT NULL AUTO_INCREMENT,
        order_id int(11) NOT NULL,
        fecha datetime DEFAULT CURRENT_TIMESTAMP,
        tipo varchar(50),
        mensaje text,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
});

// Desactivación del plugin
register_deactivation_hook(__FILE__, function() {
    // Limpiar opciones temporales si es necesario
});
