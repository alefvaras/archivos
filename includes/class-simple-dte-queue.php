<?php
/**
 * Sistema de Cola de Reintentos para DTEs Fallidos
 *
 * @package Simple_DTE
 */

if (!defined('ABSPATH')) {
    exit;
}

class Simple_DTE_Queue {

    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    /**
     * Tabla de cola
     */
    private static $table_name;

    /**
     * Máximo de reintentos
     */
    const MAX_RETRIES = 5;

    /**
     * Intervalo entre reintentos (en minutos)
     */
    const RETRY_INTERVAL = 5;

    /**
     * Inicializar cola
     */
    public static function init() {
        global $wpdb;

        self::$table_name = $wpdb->prefix . 'simple_dte_queue';

        // Crear tabla si no existe
        self::create_table();

        // Registrar WP-Cron
        self::register_cron();
    }

    /**
     * Crear tabla de cola
     */
    private static function create_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS " . self::$table_name . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            dte_tipo varchar(10) NOT NULL,
            dte_data longtext NOT NULL,
            error_message text,
            retry_count int(11) DEFAULT 0,
            max_retries int(11) DEFAULT " . self::MAX_RETRIES . ",
            status varchar(20) DEFAULT '" . self::STATUS_PENDING . "',
            created_at datetime NOT NULL,
            next_retry_at datetime,
            updated_at datetime,
            completed_at datetime,
            PRIMARY KEY  (id),
            KEY order_id (order_id),
            KEY status (status),
            KEY next_retry_at (next_retry_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Registrar WP-Cron
     */
    private static function register_cron() {
        if (!wp_next_scheduled('simple_dte_process_queue')) {
            wp_schedule_event(time(), 'every_five_minutes', 'simple_dte_process_queue');
        }
    }

    /**
     * Agregar DTE fallido a la cola
     */
    public static function add_to_queue($order_id, $dte_tipo, $dte_data, $error_message = '') {
        global $wpdb;

        // Verificar si ya existe en cola
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::$table_name . "
             WHERE order_id = %d
             AND dte_tipo = %s
             AND (status = %s OR status = %s)",
            $order_id,
            $dte_tipo,
            self::STATUS_PENDING,
            self::STATUS_PROCESSING
        ));

        if ($existing) {
            Simple_DTE_Logger::warning('DTE ya existe en cola', [
                'order_id' => $order_id,
                'dte_tipo' => $dte_tipo,
                'operacion' => 'queue_duplicate'
            ]);
            return false;
        }

        // Calcular próximo reintento
        $next_retry = date('Y-m-d H:i:s', strtotime('+' . self::RETRY_INTERVAL . ' minutes'));

        // Insertar en cola
        $inserted = $wpdb->insert(
            self::$table_name,
            [
                'order_id' => $order_id,
                'dte_tipo' => $dte_tipo,
                'dte_data' => json_encode($dte_data, JSON_UNESCAPED_UNICODE),
                'error_message' => $error_message,
                'retry_count' => 0,
                'max_retries' => self::MAX_RETRIES,
                'status' => self::STATUS_PENDING,
                'created_at' => current_time('mysql'),
                'next_retry_at' => $next_retry
            ],
            [
                '%d', // order_id
                '%s', // dte_tipo
                '%s', // dte_data
                '%s', // error_message
                '%d', // retry_count
                '%d', // max_retries
                '%s', // status
                '%s', // created_at
                '%s'  // next_retry_at
            ]
        );

        if ($inserted) {
            Simple_DTE_Logger::info('DTE agregado a cola de reintentos', [
                'order_id' => $order_id,
                'dte_tipo' => $dte_tipo,
                'next_retry_at' => $next_retry,
                'operacion' => 'queue_add'
            ]);

            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Procesar cola de reintentos
     */
    public static function process_queue() {
        global $wpdb;

        Simple_DTE_Logger::debug('Iniciando procesamiento de cola', [
            'operacion' => 'queue_process_start'
        ]);

        // Obtener items pendientes listos para reintentar
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . self::$table_name . "
             WHERE status = %s
             AND next_retry_at <= %s
             ORDER BY created_at ASC
             LIMIT 10",
            self::STATUS_PENDING,
            current_time('mysql')
        ));

        if (empty($items)) {
            Simple_DTE_Logger::debug('No hay items en cola para procesar', [
                'operacion' => 'queue_process_empty'
            ]);
            return;
        }

        Simple_DTE_Logger::info('Procesando cola de reintentos', [
            'items_count' => count($items),
            'operacion' => 'queue_process'
        ]);

        foreach ($items as $item) {
            self::process_item($item);
        }
    }

    /**
     * Procesar un item de la cola
     */
    private static function process_item($item) {
        global $wpdb;

        // Marcar como procesando
        $wpdb->update(
            self::$table_name,
            [
                'status' => self::STATUS_PROCESSING,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $item->id],
            ['%s', '%s'],
            ['%d']
        );

        Simple_DTE_Logger::info('Procesando item de cola', [
            'queue_id' => $item->id,
            'order_id' => $item->order_id,
            'dte_tipo' => $item->dte_tipo,
            'retry_count' => $item->retry_count,
            'operacion' => 'queue_item_process'
        ]);

        try {
            // Obtener orden
            $order = wc_get_order($item->order_id);
            if (!$order) {
                throw new Exception('Orden no encontrada');
            }

            // Decodificar datos del DTE
            $dte_data = json_decode($item->dte_data, true);

            // Intentar generar DTE según el tipo
            $resultado = false;
            switch ($item->dte_tipo) {
                case '39': // Boleta
                case '41': // Boleta Exenta
                    $resultado = self::retry_boleta($order, $dte_data);
                    break;
                case '61': // Nota de Crédito
                    $resultado = self::retry_nota_credito($order, $dte_data);
                    break;
                default:
                    throw new Exception('Tipo de DTE no soportado: ' . $item->dte_tipo);
            }

            if ($resultado) {
                // Éxito - marcar como completado
                $wpdb->update(
                    self::$table_name,
                    [
                        'status' => self::STATUS_COMPLETED,
                        'completed_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ],
                    ['id' => $item->id],
                    ['%s', '%s', '%s'],
                    ['%d']
                );

                Simple_DTE_Logger::info('Item de cola procesado exitosamente', [
                    'queue_id' => $item->id,
                    'order_id' => $item->order_id,
                    'dte_tipo' => $item->dte_tipo,
                    'retry_count' => $item->retry_count,
                    'operacion' => 'queue_item_success'
                ]);
            } else {
                throw new Exception('Error al generar DTE');
            }

        } catch (Exception $e) {
            // Error - incrementar contador de reintentos
            $retry_count = $item->retry_count + 1;
            $error_message = $e->getMessage();

            if ($retry_count >= $item->max_retries) {
                // Máximo de reintentos alcanzado
                $wpdb->update(
                    self::$table_name,
                    [
                        'status' => self::STATUS_FAILED,
                        'retry_count' => $retry_count,
                        'error_message' => $error_message,
                        'updated_at' => current_time('mysql')
                    ],
                    ['id' => $item->id],
                    ['%s', '%d', '%s', '%s'],
                    ['%d']
                );

                Simple_DTE_Logger::error('Item de cola falló permanentemente', [
                    'queue_id' => $item->id,
                    'order_id' => $item->order_id,
                    'dte_tipo' => $item->dte_tipo,
                    'retry_count' => $retry_count,
                    'error' => $error_message,
                    'operacion' => 'queue_item_failed'
                ]);

                // Enviar notificación al administrador
                self::notify_admin_failure($item, $error_message);

            } else {
                // Programar próximo reintento
                $next_retry = date('Y-m-d H:i:s', strtotime('+' . self::RETRY_INTERVAL . ' minutes'));

                $wpdb->update(
                    self::$table_name,
                    [
                        'status' => self::STATUS_PENDING,
                        'retry_count' => $retry_count,
                        'error_message' => $error_message,
                        'next_retry_at' => $next_retry,
                        'updated_at' => current_time('mysql')
                    ],
                    ['id' => $item->id],
                    ['%s', '%d', '%s', '%s', '%s'],
                    ['%d']
                );

                Simple_DTE_Logger::warning('Item de cola falló, programando reintento', [
                    'queue_id' => $item->id,
                    'order_id' => $item->order_id,
                    'dte_tipo' => $item->dte_tipo,
                    'retry_count' => $retry_count,
                    'next_retry_at' => $next_retry,
                    'error' => $error_message,
                    'operacion' => 'queue_item_retry'
                ]);
            }
        }
    }

    /**
     * Reintentar generación de boleta
     */
    private static function retry_boleta($order, $dte_data) {
        // Verificar que no se haya generado ya
        $ya_generada = $order->get_meta('_simple_dte_generada');
        if ($ya_generada === 'yes') {
            Simple_DTE_Logger::info('DTE ya fue generada, removiendo de cola', [
                'order_id' => $order->get_id(),
                'operacion' => 'queue_retry_skip'
            ]);
            return true;
        }

        // Intentar generar boleta usando la clase principal
        if (class_exists('WC_Boletas_Electronicas')) {
            $plugin = WC_Boletas_Electronicas::get_instance();
            if (method_exists($plugin, 'generar_boleta_desde_orden')) {
                return $plugin->generar_boleta_desde_orden($order->get_id());
            }
        }

        return false;
    }

    /**
     * Reintentar generación de nota de crédito
     */
    private static function retry_nota_credito($order, $dte_data) {
        // Verificar que no se haya generado ya
        $ya_generada = $order->get_meta('_simple_dte_nc_generada');
        if ($ya_generada === 'yes') {
            Simple_DTE_Logger::info('NC ya fue generada, removiendo de cola', [
                'order_id' => $order->get_id(),
                'operacion' => 'queue_retry_skip'
            ]);
            return true;
        }

        // Intentar generar NC usando la clase NC
        if (class_exists('Simple_DTE_Nota_Credito_Generator')) {
            return Simple_DTE_Nota_Credito_Generator::generar_desde_orden($order->get_id(), $dte_data);
        }

        return false;
    }

    /**
     * Notificar al administrador sobre falla permanente
     */
    private static function notify_admin_failure($item, $error_message) {
        $admin_email = get_option('admin_email');
        $order = wc_get_order($item->order_id);

        if (!$order) {
            return;
        }

        $subject = 'Error permanente generando DTE - Orden #' . $item->order_id;

        $message = "Se ha producido un error permanente al intentar generar el DTE para la orden #{$item->order_id}.\n\n";
        $message .= "Detalles:\n";
        $message .= "- Orden: #{$item->order_id}\n";
        $message .= "- Tipo DTE: {$item->dte_tipo}\n";
        $message .= "- Intentos realizados: {$item->retry_count}\n";
        $message .= "- Error: {$error_message}\n";
        $message .= "- Fecha: " . current_time('mysql') . "\n\n";
        $message .= "Por favor, revise la orden y genere el DTE manualmente.\n\n";
        $message .= "Ver orden: " . admin_url('post.php?post=' . $item->order_id . '&action=edit');

        wp_mail($admin_email, $subject, $message);

        Simple_DTE_Logger::alert('Notificación de fallo enviada a administrador', [
            'order_id' => $item->order_id,
            'admin_email' => $admin_email,
            'operacion' => 'queue_admin_notification'
        ]);
    }

    /**
     * Obtener estadísticas de la cola
     */
    public static function get_statistics() {
        global $wpdb;

        $stats = [];

        // Total por estado
        $stats['por_estado'] = $wpdb->get_results(
            "SELECT status, COUNT(*) as total
             FROM " . self::$table_name . "
             GROUP BY status"
        );

        // Total pendientes
        $stats['pendientes'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::$table_name . " WHERE status = %s",
            self::STATUS_PENDING
        ));

        // Total completados hoy
        $stats['completados_hoy'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::$table_name . "
             WHERE status = %s AND DATE(completed_at) = %s",
            self::STATUS_COMPLETED,
            date('Y-m-d')
        ));

        // Total fallidos
        $stats['fallidos'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::$table_name . " WHERE status = %s",
            self::STATUS_FAILED
        ));

        // Próximo reintento programado
        $stats['proximo_reintento'] = $wpdb->get_var($wpdb->prepare(
            "SELECT MIN(next_retry_at) FROM " . self::$table_name . "
             WHERE status = %s AND next_retry_at > %s",
            self::STATUS_PENDING,
            current_time('mysql')
        ));

        return $stats;
    }

    /**
     * Limpiar items completados antiguos
     */
    public static function cleanup_old_items($dias = 30) {
        global $wpdb;

        $fecha_limite = date('Y-m-d H:i:s', strtotime("-{$dias} days"));

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM " . self::$table_name . "
             WHERE status = %s AND completed_at < %s",
            self::STATUS_COMPLETED,
            $fecha_limite
        ));

        Simple_DTE_Logger::info('Limpieza de cola completada', [
            'deleted_count' => $deleted,
            'dias' => $dias,
            'operacion' => 'queue_cleanup'
        ]);

        return $deleted;
    }
}

// Inicializar al cargar
add_action('plugins_loaded', ['Simple_DTE_Queue', 'init']);

// Procesar cola cada 5 minutos
add_action('simple_dte_process_queue', ['Simple_DTE_Queue', 'process_queue']);

// Limpieza mensual
add_action('simple_dte_cleanup_queue', ['Simple_DTE_Queue', 'cleanup_old_items']);
if (!wp_next_scheduled('simple_dte_cleanup_queue')) {
    wp_schedule_event(time(), 'monthly', 'simple_dte_cleanup_queue');
}

// Agregar intervalo personalizado de 5 minutos
add_filter('cron_schedules', function($schedules) {
    if (!isset($schedules['every_five_minutes'])) {
        $schedules['every_five_minutes'] = [
            'interval' => 300, // 5 minutos en segundos
            'display' => __('Cada 5 minutos')
        ];
    }
    return $schedules;
});
