<?php
/**
 * Sistema de Logs Estructurados PSR-3 Compatible
 *
 * @package Simple_DTE
 */

if (!defined('ABSPATH')) {
    exit;
}

class Simple_DTE_Logger {

    const EMERGENCY = 'emergency';
    const ALERT     = 'alert';
    const CRITICAL  = 'critical';
    const ERROR     = 'error';
    const WARNING   = 'warning';
    const NOTICE    = 'notice';
    const INFO      = 'info';
    const DEBUG     = 'debug';

    /**
     * Directorio de logs
     */
    private static $log_dir;

    /**
     * Usar base de datos para logs
     */
    private static $use_db = false;

    /**
     * Tabla de logs
     */
    private static $table_name;

    /**
     * Inicializar logger
     */
    public static function init() {
        global $wpdb;

        // Directorio de logs
        self::$log_dir = __DIR__ . '/../logs';
        if (!is_dir(self::$log_dir)) {
            mkdir(self::$log_dir, 0755, true);
        }

        // Tabla de logs
        self::$table_name = $wpdb->prefix . 'simple_dte_logs';
        self::$use_db = true;

        // Crear tabla si no existe
        self::create_table();
    }

    /**
     * Crear tabla de logs
     */
    private static function create_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS " . self::$table_name . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            nivel varchar(20) NOT NULL,
            mensaje text NOT NULL,
            contexto longtext,
            order_id bigint(20),
            rut varchar(20),
            folio varchar(20),
            tipo_dte varchar(10),
            operacion varchar(50),
            usuario_id bigint(20),
            fecha_creacion datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY nivel (nivel),
            KEY order_id (order_id),
            KEY rut (rut),
            KEY folio (folio),
            KEY fecha_creacion (fecha_creacion)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Log nivel emergency
     */
    public static function emergency($mensaje, $contexto = []) {
        return self::log(self::EMERGENCY, $mensaje, $contexto);
    }

    /**
     * Log nivel alert
     */
    public static function alert($mensaje, $contexto = []) {
        return self::log(self::ALERT, $mensaje, $contexto);
    }

    /**
     * Log nivel critical
     */
    public static function critical($mensaje, $contexto = []) {
        return self::log(self::CRITICAL, $mensaje, $contexto);
    }

    /**
     * Log nivel error
     */
    public static function error($mensaje, $contexto = []) {
        return self::log(self::ERROR, $mensaje, $contexto);
    }

    /**
     * Log nivel warning
     */
    public static function warning($mensaje, $contexto = []) {
        return self::log(self::WARNING, $mensaje, $contexto);
    }

    /**
     * Log nivel notice
     */
    public static function notice($mensaje, $contexto = []) {
        return self::log(self::NOTICE, $mensaje, $contexto);
    }

    /**
     * Log nivel info
     */
    public static function info($mensaje, $contexto = []) {
        return self::log(self::INFO, $mensaje, $contexto);
    }

    /**
     * Log nivel debug
     */
    public static function debug($mensaje, $contexto = []) {
        return self::log(self::DEBUG, $mensaje, $contexto);
    }

    /**
     * Log genérico
     */
    public static function log($nivel, $mensaje, $contexto = []) {
        // Inicializar si no se ha hecho
        if (!self::$log_dir) {
            self::init();
        }

        // Preparar datos del log
        $log_data = self::prepare_log_data($nivel, $mensaje, $contexto);

        // Guardar en archivo
        self::log_to_file($log_data);

        // Guardar en base de datos
        if (self::$use_db) {
            self::log_to_database($log_data);
        }

        return true;
    }

    /**
     * Preparar datos del log
     */
    private static function prepare_log_data($nivel, $mensaje, $contexto) {
        // Extraer datos del contexto
        $order_id = $contexto['order_id'] ?? null;
        $rut = $contexto['rut'] ?? null;
        $folio = $contexto['folio'] ?? null;
        $tipo_dte = $contexto['tipo_dte'] ?? null;
        $operacion = $contexto['operacion'] ?? 'general';

        // Usuario actual
        $usuario_id = get_current_user_id();

        return [
            'nivel' => $nivel,
            'mensaje' => $mensaje,
            'contexto' => $contexto,
            'order_id' => $order_id,
            'rut' => $rut,
            'folio' => $folio,
            'tipo_dte' => $tipo_dte,
            'operacion' => $operacion,
            'usuario_id' => $usuario_id,
            'fecha' => current_time('mysql'),
            'timestamp' => time()
        ];
    }

    /**
     * Guardar log en archivo
     */
    private static function log_to_file($log_data) {
        $fecha = date('Y-m-d', $log_data['timestamp']);
        $archivo = self::$log_dir . "/simple-dte-{$fecha}.log";

        $hora = date('H:i:s', $log_data['timestamp']);
        $nivel = strtoupper($log_data['nivel']);
        $mensaje = $log_data['mensaje'];

        // Formato del log
        $linea = "[{$hora}] [{$nivel}]";

        if ($log_data['operacion']) {
            $linea .= " [{$log_data['operacion']}]";
        }

        if ($log_data['order_id']) {
            $linea .= " [Order: {$log_data['order_id']}]";
        }

        if ($log_data['rut']) {
            $linea .= " [RUT: {$log_data['rut']}]";
        }

        if ($log_data['folio']) {
            $linea .= " [Folio: {$log_data['folio']}]";
        }

        $linea .= " {$mensaje}";

        // Agregar contexto adicional
        if (!empty($log_data['contexto'])) {
            $contexto_filtrado = array_diff_key(
                $log_data['contexto'],
                array_flip(['order_id', 'rut', 'folio', 'tipo_dte', 'operacion'])
            );

            if (!empty($contexto_filtrado)) {
                $linea .= " | Contexto: " . json_encode($contexto_filtrado, JSON_UNESCAPED_UNICODE);
            }
        }

        $linea .= "\n";

        // Escribir en archivo
        file_put_contents($archivo, $linea, FILE_APPEND | LOCK_EX);
    }

    /**
     * Guardar log en base de datos
     */
    private static function log_to_database($log_data) {
        global $wpdb;

        $wpdb->insert(
            self::$table_name,
            [
                'nivel' => $log_data['nivel'],
                'mensaje' => $log_data['mensaje'],
                'contexto' => json_encode($log_data['contexto'], JSON_UNESCAPED_UNICODE),
                'order_id' => $log_data['order_id'],
                'rut' => $log_data['rut'],
                'folio' => $log_data['folio'],
                'tipo_dte' => $log_data['tipo_dte'],
                'operacion' => $log_data['operacion'],
                'usuario_id' => $log_data['usuario_id'],
                'fecha_creacion' => $log_data['fecha']
            ],
            [
                '%s', // nivel
                '%s', // mensaje
                '%s', // contexto
                '%d', // order_id
                '%s', // rut
                '%s', // folio
                '%s', // tipo_dte
                '%s', // operacion
                '%d', // usuario_id
                '%s'  // fecha_creacion
            ]
        );
    }

    /**
     * Obtener logs
     */
    public static function get_logs($filtros = []) {
        global $wpdb;

        $where = ['1=1'];
        $params = [];

        // Filtro por nivel
        if (!empty($filtros['nivel'])) {
            $where[] = 'nivel = %s';
            $params[] = $filtros['nivel'];
        }

        // Filtro por orden
        if (!empty($filtros['order_id'])) {
            $where[] = 'order_id = %d';
            $params[] = $filtros['order_id'];
        }

        // Filtro por RUT
        if (!empty($filtros['rut'])) {
            $where[] = 'rut = %s';
            $params[] = $filtros['rut'];
        }

        // Filtro por folio
        if (!empty($filtros['folio'])) {
            $where[] = 'folio = %s';
            $params[] = $filtros['folio'];
        }

        // Filtro por operación
        if (!empty($filtros['operacion'])) {
            $where[] = 'operacion = %s';
            $params[] = $filtros['operacion'];
        }

        // Filtro por fecha desde
        if (!empty($filtros['fecha_desde'])) {
            $where[] = 'fecha_creacion >= %s';
            $params[] = $filtros['fecha_desde'];
        }

        // Filtro por fecha hasta
        if (!empty($filtros['fecha_hasta'])) {
            $where[] = 'fecha_creacion <= %s';
            $params[] = $filtros['fecha_hasta'];
        }

        // Límite
        $limit = isset($filtros['limit']) ? absint($filtros['limit']) : 100;

        // Query
        $sql = "SELECT * FROM " . self::$table_name . "
                WHERE " . implode(' AND ', $where) . "
                ORDER BY fecha_creacion DESC
                LIMIT {$limit}";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return $wpdb->get_results($sql);
    }

    /**
     * Limpiar logs antiguos
     */
    public static function cleanup_old_logs($dias = 90) {
        global $wpdb;

        $fecha_limite = date('Y-m-d H:i:s', strtotime("-{$dias} days"));

        // Limpiar base de datos
        $wpdb->query($wpdb->prepare(
            "DELETE FROM " . self::$table_name . " WHERE fecha_creacion < %s",
            $fecha_limite
        ));

        // Limpiar archivos
        if (is_dir(self::$log_dir)) {
            $archivos = glob(self::$log_dir . '/simple-dte-*.log');
            foreach ($archivos as $archivo) {
                if (filemtime($archivo) < strtotime("-{$dias} days")) {
                    unlink($archivo);
                }
            }
        }
    }

    /**
     * Obtener estadísticas
     */
    public static function get_statistics($fecha_desde = null, $fecha_hasta = null) {
        global $wpdb;

        $where = ['1=1'];
        $params = [];

        if ($fecha_desde) {
            $where[] = 'fecha_creacion >= %s';
            $params[] = $fecha_desde;
        }

        if ($fecha_hasta) {
            $where[] = 'fecha_creacion <= %s';
            $params[] = $fecha_hasta;
        }

        $sql_where = implode(' AND ', $where);

        if (!empty($params)) {
            $sql_where = $wpdb->prepare($sql_where, $params);
        }

        // Total por nivel
        $stats_nivel = $wpdb->get_results(
            "SELECT nivel, COUNT(*) as total
             FROM " . self::$table_name . "
             WHERE {$sql_where}
             GROUP BY nivel"
        );

        // Total por operación
        $stats_operacion = $wpdb->get_results(
            "SELECT operacion, COUNT(*) as total
             FROM " . self::$table_name . "
             WHERE {$sql_where}
             GROUP BY operacion
             ORDER BY total DESC
             LIMIT 10"
        );

        // Total por día
        $stats_dia = $wpdb->get_results(
            "SELECT DATE(fecha_creacion) as fecha, COUNT(*) as total
             FROM " . self::$table_name . "
             WHERE {$sql_where}
             GROUP BY DATE(fecha_creacion)
             ORDER BY fecha DESC
             LIMIT 30"
        );

        return [
            'por_nivel' => $stats_nivel,
            'por_operacion' => $stats_operacion,
            'por_dia' => $stats_dia
        ];
    }
}

// Inicializar al cargar
add_action('plugins_loaded', ['Simple_DTE_Logger', 'init']);

// Tarea de limpieza semanal
add_action('simple_dte_cleanup_logs', ['Simple_DTE_Logger', 'cleanup_old_logs']);
if (!wp_next_scheduled('simple_dte_cleanup_logs')) {
    wp_schedule_event(time(), 'weekly', 'simple_dte_cleanup_logs');
}
