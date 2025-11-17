<?php
/**
 * Plugin Name: Boletas Electrónicas para WooCommerce
 * Plugin URI: https://github.com/tuusuario/woocommerce-boletas-electronicas
 * Description: Integración automática de Boletas Electrónicas SII con WooCommerce. Genera boletas automáticamente al completar órdenes usando Simple API.
 * Version: 1.0.0
 * Author: Tu Nombre
 * Author URI: https://tudominio.cl
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wc-boletas-electronicas
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 8.0
 * WC requires at least: 6.0
 * WC tested up to: 8.5
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Plugin principal para integración de Boletas Electrónicas con WooCommerce
 */
class WC_Boletas_Electronicas {

    /**
     * Versión del plugin
     */
    const VERSION = '1.0.0';

    /**
     * Instancia singleton
     */
    private static $instance = null;

    /**
     * Path del sistema de boletas
     */
    private $boletas_path;

    /**
     * Logger
     */
    private $logger;

    /**
     * Repositorio BD
     */
    private $repo;

    /**
     * Usar base de datos
     */
    private $usar_bd;

    /**
     * Obtener instancia singleton
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Path del sistema de boletas (mismo directorio del plugin)
        $this->boletas_path = plugin_dir_path(__FILE__);

        // Verificar que WooCommerce esté activo
        add_action('plugins_loaded', [$this, 'init']);
    }

    /**
     * Inicializar plugin
     */
    public function init() {
        // Verificar WooCommerce
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'woocommerce_missing_notice']);
            return;
        }

        // Inicializar componentes del sistema de boletas
        $this->init_boletas_system();

        // Hooks de WooCommerce
        $this->register_hooks();

        // Hooks de administración
        if (is_admin()) {
            $this->register_admin_hooks();
        }
    }

    /**
     * Inicializar sistema de boletas
     */
    private function init_boletas_system() {
        // Auto-detección de BD
        $this->usar_bd = getenv('DB_NAME') && getenv('DB_USER');

        // Inicializar logger
        if (file_exists($this->boletas_path . 'lib/DTELogger.php')) {
            require_once $this->boletas_path . 'lib/DTELogger.php';
            $this->logger = new DTELogger($this->boletas_path . 'logs', $this->usar_bd);
        }

        // Inicializar repositorio BD
        if ($this->usar_bd && file_exists($this->boletas_path . 'lib/BoletaRepository.php')) {
            try {
                require_once $this->boletas_path . 'lib/BoletaRepository.php';
                $this->repo = new BoletaRepository();
            } catch (Exception $e) {
                $this->log_error('init', 'Error inicializando repositorio BD: ' . $e->getMessage());
                $this->usar_bd = false;
            }
        }

        // Cargar generar-boleta.php
        if (file_exists($this->boletas_path . 'generar-boleta.php')) {
            require_once $this->boletas_path . 'generar-boleta.php';
        }

        // Cargar nuevas clases del sistema
        if (file_exists($this->boletas_path . 'includes/class-simple-dte-logger.php')) {
            require_once $this->boletas_path . 'includes/class-simple-dte-logger.php';
        }

        if (file_exists($this->boletas_path . 'includes/class-simple-dte-rut-cache.php')) {
            require_once $this->boletas_path . 'includes/class-simple-dte-rut-cache.php';
        }

        if (file_exists($this->boletas_path . 'includes/class-simple-dte-queue.php')) {
            require_once $this->boletas_path . 'includes/class-simple-dte-queue.php';
        }

        if (file_exists($this->boletas_path . 'includes/class-simple-dte-export.php')) {
            require_once $this->boletas_path . 'includes/class-simple-dte-export.php';
        }

        if (file_exists($this->boletas_path . 'includes/class-simple-dte-nota-credito-generator.php')) {
            require_once $this->boletas_path . 'includes/class-simple-dte-nota-credito-generator.php';
        }

        if (file_exists($this->boletas_path . 'includes/admin/class-simple-dte-dashboard.php')) {
            require_once $this->boletas_path . 'includes/admin/class-simple-dte-dashboard.php';
        }
    }

    /**
     * Registrar hooks de WooCommerce
     */
    private function register_hooks() {
        // Campo RUT en checkout
        add_filter('woocommerce_checkout_fields', [$this, 'add_rut_field']);

        // Validar RUT en checkout
        add_action('woocommerce_checkout_process', [$this, 'validate_rut_field']);

        // Guardar RUT en orden
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_rut_field']);

        // Generar boleta al completar orden
        add_action('woocommerce_order_status_completed', [$this, 'generar_boleta_automatica'], 10, 1);

        // Mostrar RUT en email y orden
        add_action('woocommerce_email_customer_details', [$this, 'display_rut_in_email'], 20, 3);
        add_action('woocommerce_order_details_after_customer_details', [$this, 'display_rut_in_order']);

        // Botón de descarga de boleta en "Mi cuenta"
        add_action('woocommerce_order_details_after_order_table', [$this, 'add_download_boleta_button']);

        // Endpoint para descargar PDF
        add_action('init', [$this, 'register_download_endpoint']);
        add_action('template_redirect', [$this, 'handle_download_request']);
    }

    /**
     * Registrar hooks de administración
     */
    private function register_admin_hooks() {
        // Metabox en orden con datos de boleta
        add_action('add_meta_boxes', [$this, 'add_boleta_metabox']);

        // Columna en lista de órdenes
        add_filter('manage_edit-shop_order_columns', [$this, 'add_boleta_column']);
        add_action('manage_shop_order_posts_custom_column', [$this, 'display_boleta_column'], 10, 2);

        // Botón para generar boleta manualmente
        add_action('woocommerce_order_actions', [$this, 'add_generate_boleta_action']);
        add_action('woocommerce_order_action_generate_boleta', [$this, 'process_generate_boleta_action']);
    }

    /**
     * Agregar campo RUT al checkout (solo si no existe)
     */
    public function add_rut_field($fields) {
        // Solo agregar si no existe el campo billing_rut
        if (!isset($fields['billing']['billing_rut'])) {
            $fields['billing']['billing_rut'] = [
                'type' => 'text',
                'label' => __('RUT', 'wc-boletas-electronicas'),
                'placeholder' => __('12345678-9', 'wc-boletas-electronicas'),
                'required' => true,
                'class' => ['form-row-wide'],
                'priority' => 25, // Después del nombre
                'description' => __('RUT para la boleta electrónica', 'wc-boletas-electronicas')
            ];
        }

        return $fields;
    }

    /**
     * Validar campo RUT
     */
    public function validate_rut_field() {
        if (isset($_POST['billing_rut']) && !empty($_POST['billing_rut'])) {
            $rut = sanitize_text_field($_POST['billing_rut']);

            // Validación básica de formato RUT chileno
            if (!$this->validar_rut($rut)) {
                wc_add_notice(
                    __('Por favor ingresa un RUT válido (formato: 12345678-9)', 'wc-boletas-electronicas'),
                    'error'
                );
            }
        }
    }

    /**
     * Validar formato de RUT chileno
     */
    private function validar_rut($rut) {
        // Formato básico: 12345678-9 o 12.345.678-9
        $rut = preg_replace('/[^0-9kK\-]/', '', $rut);

        if (!preg_match('/^(\d{1,8})-([0-9kK])$/', $rut, $matches)) {
            return false;
        }

        $numero = $matches[1];
        $dv = strtoupper($matches[2]);

        // Calcular dígito verificador
        $suma = 0;
        $multiplo = 2;

        for ($i = strlen($numero) - 1; $i >= 0; $i--) {
            $suma += intval($numero[$i]) * $multiplo;
            $multiplo = $multiplo < 7 ? $multiplo + 1 : 2;
        }

        $resto = $suma % 11;
        $dv_calculado = $resto === 0 ? '0' : ($resto === 1 ? 'K' : strval(11 - $resto));

        return $dv === $dv_calculado;
    }

    /**
     * Guardar campo RUT en la orden (compatible HPOS)
     */
    public function save_rut_field($order_id) {
        if (!empty($_POST['billing_rut'])) {
            $order = wc_get_order($order_id);
            if ($order) {
                $order->update_meta_data('_billing_rut', sanitize_text_field($_POST['billing_rut']));
                $order->save();
            }
        }
    }

    /**
     * Mostrar RUT en email (compatible HPOS)
     */
    public function display_rut_in_email($order, $sent_to_admin, $plain_text) {
        $rut = $order->get_meta('_billing_rut');
        if ($rut) {
            if ($plain_text) {
                echo "RUT: " . esc_html($rut) . "\n";
            } else {
                echo '<p><strong>' . __('RUT:', 'wc-boletas-electronicas') . '</strong> ' . esc_html($rut) . '</p>';
            }
        }
    }

    /**
     * Mostrar RUT en detalles de orden (compatible HPOS)
     */
    public function display_rut_in_order($order) {
        $rut = $order->get_meta('_billing_rut');
        if ($rut) {
            echo '<p><strong>' . __('RUT:', 'wc-boletas-electronicas') . '</strong> ' . esc_html($rut) . '</p>';
        }
    }

    /**
     * Generar boleta automáticamente al completar orden
     */
    public function generar_boleta_automatica($order_id) {
        // Obtener orden (compatible HPOS)
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Verificar que no se haya generado ya
        $folio = $order->get_meta('_boleta_folio');
        if ($folio) {
            $this->log_info('woocommerce', "Boleta ya generada para orden #{$order_id}: Folio {$folio}");

            // Log usando nuevo sistema
            if (class_exists('Simple_DTE_Logger')) {
                Simple_DTE_Logger::info('Boleta ya generada, omitiendo', [
                    'order_id' => $order_id,
                    'folio' => $folio,
                    'operacion' => 'boleta_auto_skip'
                ]);
            }

            return;
        }

        $this->log_info('woocommerce', "Iniciando generación automática de boleta para orden #{$order_id}");

        // Log usando nuevo sistema
        if (class_exists('Simple_DTE_Logger')) {
            Simple_DTE_Logger::info('Iniciando generación automática de boleta', [
                'order_id' => $order_id,
                'operacion' => 'boleta_auto_start'
            ]);
        }

        try {
            $resultado = $this->generar_boleta_desde_orden($order_id);

            if ($resultado && isset($resultado['folio'])) {
                // Guardar datos de la boleta en la orden (compatible HPOS)
                $order->update_meta_data('_boleta_folio', $resultado['folio']);
                $order->update_meta_data('_boleta_track_id', $resultado['track_id'] ?? '');
                $order->update_meta_data('_boleta_estado', $resultado['estado']['estado'] ?? 'REC');
                $order->update_meta_data('_boleta_fecha', date('Y-m-d H:i:s'));
                $order->update_meta_data('_boleta_pdf_path', $resultado['pdf_path'] ?? '');
                $order->update_meta_data('_simple_dte_generada', 'yes');
                $order->save();

                // Agregar nota a la orden
                $order->add_order_note(
                    sprintf(
                        __('Boleta electrónica generada automáticamente. Folio: %s, Track ID: %s', 'wc-boletas-electronicas'),
                        $resultado['folio'],
                        $resultado['track_id'] ?? 'N/A'
                    )
                );

                $this->log_info('woocommerce', "Boleta generada exitosamente: Folio {$resultado['folio']}");

                // Log usando nuevo sistema
                if (class_exists('Simple_DTE_Logger')) {
                    Simple_DTE_Logger::info('Boleta generada exitosamente', [
                        'order_id' => $order_id,
                        'folio' => $resultado['folio'],
                        'track_id' => $resultado['track_id'] ?? '',
                        'operacion' => 'boleta_auto_success'
                    ]);
                }
            } else {
                throw new Exception('Error generando boleta: resultado inválido');
            }
        } catch (Exception $e) {
            $this->log_error('woocommerce', "Error generando boleta para orden #{$order_id}: " . $e->getMessage());

            // Log usando nuevo sistema
            if (class_exists('Simple_DTE_Logger')) {
                Simple_DTE_Logger::error('Error generando boleta automática', [
                    'order_id' => $order_id,
                    'error' => $e->getMessage(),
                    'operacion' => 'boleta_auto_error'
                ]);
            }

            // Agregar nota de error en la orden
            $order->add_order_note(
                sprintf(
                    __('Error al generar boleta electrónica: %s. Se agregó a la cola de reintentos.', 'wc-boletas-electronicas'),
                    $e->getMessage()
                )
            );

            // Agregar a cola de reintentos
            if (class_exists('Simple_DTE_Queue')) {
                $dte_data = [
                    'order_id' => $order_id,
                    'tipo_dte' => '39', // Boleta
                ];

                Simple_DTE_Queue::add_to_queue(
                    $order_id,
                    '39',
                    $dte_data,
                    $e->getMessage()
                );

                $this->log_info('woocommerce', "Boleta agregada a cola de reintentos para orden #{$order_id}");
            }
        }
    }

    /**
     * Generar boleta desde datos de orden WooCommerce
     */
    private function generar_boleta_desde_orden($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            throw new Exception("Orden #{$order_id} no encontrada");
        }

        // Extraer datos del cliente (compatible HPOS)
        $rut = $order->get_meta('_billing_rut');
        if (!$rut) {
            $rut = '66666666-6'; // Cliente genérico si no hay RUT
        }

        $cliente = [
            'rut' => $rut,
            'razon_social' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'email' => $order->get_billing_email(),
            'direccion' => $order->get_billing_address_1(),
            'comuna' => $order->get_billing_city()
        ];

        // Extraer items de la orden
        $items = [];
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();

            $items[] = [
                'nombre' => $item->get_name(),
                'descripcion' => $product ? wp_trim_words($product->get_description(), 20) : '',
                'cantidad' => $item->get_quantity(),
                'precio' => $order->get_item_total($item, true), // Con IVA incluido
                'unidad' => 'un'
            ];
        }

        // Agregar costos de envío como item si existe
        if ($order->get_shipping_total() > 0) {
            $items[] = [
                'nombre' => 'Envío',
                'descripcion' => $order->get_shipping_method(),
                'cantidad' => 1,
                'precio' => $order->get_shipping_total() + $order->get_shipping_tax(),
                'unidad' => 'un'
            ];
        }

        // Configuración
        global $CONFIG, $API_BASE;

        if (!isset($CONFIG)) {
            $CONFIG = [
                'envio_automatico_email' => true, // Enviar email con boleta
                'consulta_automatica' => true,
                'espera_consulta_segundos' => 5,
                'guardar_xml' => true,
                'directorio_xml' => $this->boletas_path . 'xmls',
                'email_remitente' => get_option('admin_email'),
                'adjuntar_pdf' => true,
                'adjuntar_xml' => false,
            ];
        }

        // Generar boleta usando el sistema existente
        $resultado = generar_boleta($cliente, $items, $CONFIG, $API_BASE);

        // Generar PDF
        if ($resultado && isset($resultado['folio'])) {
            require_once $this->boletas_path . 'lib/generar-pdf-boleta.php';

            $datos_pdf = [
                'folio' => $resultado['folio'],
                'fecha' => date('d/m/Y'),
                'hora' => date('H:i:s'),
                'cliente' => $cliente,
                'items' => $items,
                'subtotal' => $order->get_subtotal(),
                'iva' => $order->get_total_tax(),
                'total' => $order->get_total(),
                'emisor' => [
                    'rut' => RUT_EMISOR,
                    'razon_social' => RAZON_SOCIAL,
                ]
            ];

            $pdf_dir = $this->boletas_path . 'pdfs';
            if (!is_dir($pdf_dir)) {
                mkdir($pdf_dir, 0755, true);
            }

            $pdf_path = $pdf_dir . '/boleta_' . $resultado['folio'] . '.pdf';
            generar_pdf_boleta($datos_pdf, $resultado['dte_xml'], $pdf_path);

            $resultado['pdf_path'] = $pdf_path;
        }

        return $resultado;
    }

    /**
     * Agregar metabox en admin de orden
     */
    public function add_boleta_metabox() {
        // HPOS compatible - works for both post and order screens
        $screen = wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';

        add_meta_box(
            'wc_boleta_electronica',
            __('Boleta Electrónica SII', 'wc-boletas-electronicas'),
            [$this, 'render_boleta_metabox'],
            $screen,
            'side',
            'high'
        );
    }

    /**
     * Renderizar metabox de boleta (compatible HPOS)
     */
    public function render_boleta_metabox($post_or_order) {
        // HPOS compatible - $post_or_order puede ser WP_Post o WC_Order
        $order = $post_or_order instanceof \WC_Order ? $post_or_order : wc_get_order($post_or_order->ID);

        if (!$order) {
            echo '<p>' . __('Error: No se pudo cargar la orden.', 'wc-boletas-electronicas') . '</p>';
            return;
        }

        $folio = $order->get_meta('_boleta_folio');
        $track_id = $order->get_meta('_boleta_track_id');
        $estado = $order->get_meta('_boleta_estado');
        $fecha = $order->get_meta('_boleta_fecha');
        $pdf_path = $order->get_meta('_boleta_pdf_path');

        if ($folio) {
            echo '<div class="boleta-info">';
            echo '<p><strong>' . __('Folio:', 'wc-boletas-electronicas') . '</strong> ' . esc_html($folio) . '</p>';
            echo '<p><strong>' . __('Track ID:', 'wc-boletas-electronicas') . '</strong> ' . esc_html($track_id) . '</p>';
            echo '<p><strong>' . __('Estado SII:', 'wc-boletas-electronicas') . '</strong> ' . esc_html($estado) . '</p>';
            echo '<p><strong>' . __('Fecha:', 'wc-boletas-electronicas') . '</strong> ' . esc_html($fecha) . '</p>';

            if ($pdf_path && file_exists($pdf_path)) {
                $download_url = add_query_arg([
                    'action' => 'download_boleta',
                    'order_id' => $order->get_id(),
                    'nonce' => wp_create_nonce('download_boleta_' . $order->get_id())
                ], admin_url('admin-ajax.php'));

                echo '<p><a href="' . esc_url($download_url) . '" class="button button-primary">' . __('Descargar PDF', 'wc-boletas-electronicas') . '</a></p>';
            }
            echo '</div>';
        } else {
            echo '<p>' . __('Boleta no generada aún.', 'wc-boletas-electronicas') . '</p>';
            echo '<p><em>' . __('Se generará automáticamente al completar la orden.', 'wc-boletas-electronicas') . '</em></p>';
        }
    }

    /**
     * Agregar columna de boleta en lista de órdenes
     */
    public function add_boleta_column($columns) {
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'order_total') {
                $new_columns['boleta'] = __('Boleta', 'wc-boletas-electronicas');
            }
        }
        return $new_columns;
    }

    /**
     * Mostrar contenido de columna de boleta (compatible HPOS)
     */
    public function display_boleta_column($column, $post_id) {
        if ($column === 'boleta') {
            $order = wc_get_order($post_id);
            if (!$order) {
                echo '<span class="boleta-error">Error</span>';
                return;
            }

            $folio = $order->get_meta('_boleta_folio');
            if ($folio) {
                echo '<span class="boleta-folio">#' . esc_html($folio) . '</span>';
            } else {
                echo '<span class="boleta-pendiente">—</span>';
            }
        }
    }

    /**
     * Agregar acción para generar boleta manualmente
     */
    public function add_generate_boleta_action($actions) {
        $actions['generate_boleta'] = __('Generar Boleta Electrónica', 'wc-boletas-electronicas');
        return $actions;
    }

    /**
     * Procesar acción de generar boleta manualmente
     */
    public function process_generate_boleta_action($order) {
        $this->generar_boleta_automatica($order->get_id());
    }

    /**
     * Registrar endpoint de descarga
     */
    public function register_download_endpoint() {
        add_action('wp_ajax_download_boleta', [$this, 'handle_download_request']);
        add_action('wp_ajax_nopriv_download_boleta', [$this, 'handle_download_request']);
    }

    /**
     * Manejar solicitud de descarga de PDF
     */
    public function handle_download_request() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'download_boleta') {
            return;
        }

        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        $nonce = isset($_GET['nonce']) ? sanitize_text_field($_GET['nonce']) : '';

        // Verificar nonce
        if (!wp_verify_nonce($nonce, 'download_boleta_' . $order_id)) {
            wp_die(__('Acceso no autorizado', 'wc-boletas-electronicas'));
        }

        // Verificar permisos
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_die(__('Orden no encontrada', 'wc-boletas-electronicas'));
        }

        if (!current_user_can('manage_woocommerce') && get_current_user_id() !== $order->get_customer_id()) {
            wp_die(__('No tienes permiso para descargar esta boleta', 'wc-boletas-electronicas'));
        }

        // Obtener PDF
        $pdf_path = get_post_meta($order_id, '_boleta_pdf_path', true);
        if (!$pdf_path || !file_exists($pdf_path)) {
            wp_die(__('PDF de boleta no encontrado', 'wc-boletas-electronicas'));
        }

        // Enviar PDF
        $folio = get_post_meta($order_id, '_boleta_folio', true);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="boleta_' . $folio . '.pdf"');
        header('Content-Length: ' . filesize($pdf_path));
        readfile($pdf_path);
        exit;
    }

    /**
     * Agregar botón de descarga en "Mi cuenta"
     */
    public function add_download_boleta_button($order) {
        $folio = get_post_meta($order->get_id(), '_boleta_folio', true);
        $pdf_path = get_post_meta($order->get_id(), '_boleta_pdf_path', true);

        if ($folio && $pdf_path && file_exists($pdf_path)) {
            $download_url = add_query_arg([
                'action' => 'download_boleta',
                'order_id' => $order->get_id(),
                'nonce' => wp_create_nonce('download_boleta_' . $order->get_id())
            ], admin_url('admin-ajax.php'));

            echo '<div class="boleta-download" style="margin-top: 20px;">';
            echo '<h3>' . __('Boleta Electrónica', 'wc-boletas-electronicas') . '</h3>';
            echo '<p><strong>' . __('Folio:', 'wc-boletas-electronicas') . '</strong> ' . esc_html($folio) . '</p>';
            echo '<p><a href="' . esc_url($download_url) . '" class="button">' . __('Descargar Boleta (PDF)', 'wc-boletas-electronicas') . '</a></p>';
            echo '</div>';
        }
    }

    /**
     * Log de información
     */
    private function log_info($operacion, $mensaje) {
        if ($this->logger) {
            $this->logger->info($operacion, $mensaje);
        }
        error_log("[WC Boletas] [{$operacion}] {$mensaje}");
    }

    /**
     * Log de error
     */
    private function log_error($operacion, $mensaje) {
        if ($this->logger) {
            $this->logger->error($operacion, $mensaje);
        }
        error_log("[WC Boletas ERROR] [{$operacion}] {$mensaje}");
    }

    /**
     * Aviso de WooCommerce faltante
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p><?php _e('El plugin Boletas Electrónicas requiere que WooCommerce esté instalado y activo.', 'wc-boletas-electronicas'); ?></p>
        </div>
        <?php
    }
}

// Inicializar plugin
add_action('plugins_loaded', function() {
    WC_Boletas_Electronicas::get_instance();
});
