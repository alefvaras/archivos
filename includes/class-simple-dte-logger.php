<?php
/**
 * Sistema de logging para Simple DTE
 *
 * @package Simple_DTE
 */

if (!defined('ABSPATH')) {
    exit;
}

class Simple_DTE_Logger {

    private static $table_name = null;

    /**
     * Inicializar
     */
    public static function init() {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'simple_dte_logs';
    }

    /**
     * Log nivel DEBUG
     */
    public static function debug($message, $context = array(), $order_id = null) {
        self::log('DEBUG', $message, $context, $order_id);
    }

    /**
     * Log nivel INFO
     */
    public static function info($message, $context = array(), $order_id = null) {
        self::log('INFO', $message, $context, $order_id);
    }

    /**
     * Log nivel WARNING
     */
    public static function warning($message, $context = array(), $order_id = null) {
        self::log('WARNING', $message, $context, $order_id);
    }

    /**
     * Log nivel ERROR
     */
    public static function error($message, $context = array(), $order_id = null) {
        self::log('ERROR', $message, $context, $order_id);
    }

    /**
     * Escribir log
     */
    private static function log($level, $message, $context = array(), $order_id = null) {
        global $wpdb;

        // Log en error_log si debug está activado
        if (get_option('simple_dte_debug', false)) {
            $log_message = sprintf('[Simple DTE] [%s] %s', $level, $message);
            if (!empty($context)) {
                $log_message .= ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE);
            }
            error_log($log_message);
        }

        // Guardar en base de datos
        if (self::$table_name) {
            $wpdb->insert(
                self::$table_name,
                array(
                    'fecha_hora' => current_time('mysql'),
                    'nivel' => $level,
                    'mensaje' => $message,
                    'contexto' => !empty($context) ? wp_json_encode($context, JSON_UNESCAPED_UNICODE) : null,
                    'order_id' => $order_id
                ),
                array('%s', '%s', '%s', '%s', '%d')
            );
        }
    }

    /**
     * Obtener logs recientes
     */
    public static function get_recent_logs($limit = 100, $level = null) {
        global $wpdb;

        if (!self::$table_name) {
            return array();
        }

        $where = '';
        if ($level) {
            $where = $wpdb->prepare(' WHERE nivel = %s', $level);
        }

        $query = "SELECT * FROM " . self::$table_name . $where . " ORDER BY fecha_hora DESC LIMIT %d";

        return $wpdb->get_results($wpdb->prepare($query, $limit), ARRAY_A);
    }

    /**
     * Limpiar logs antiguos
     */
    public static function clean_old_logs($days = 30) {
        global $wpdb;

        if (!self::$table_name) {
            return 0;
        }

        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM " . self::$table_name . " WHERE fecha_hora < %s",
                $date
            )
        );
    }

    /**
     * Obtener logs de una orden específica
     */
    public static function get_order_logs($order_id) {
        global $wpdb;

        if (!self::$table_name) {
            return array();
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . self::$table_name . " WHERE order_id = %d ORDER BY fecha_hora DESC",
                $order_id
            ),
            ARRAY_A
        );
    }
}
