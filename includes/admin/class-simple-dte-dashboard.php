<?php
/**
 * Dashboard de Métricas para Simple DTE
 *
 * @package Simple_DTE
 */

if (!defined('ABSPATH')) {
    exit;
}

class Simple_DTE_Dashboard {

    /**
     * Inicializar dashboard
     */
    public static function init() {
        // Agregar widget al dashboard
        add_action('wp_dashboard_setup', [__CLASS__, 'add_dashboard_widget']);

        // Agregar estilos
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_styles']);
    }

    /**
     * Agregar widget al dashboard
     */
    public static function add_dashboard_widget() {
        // Solo para usuarios con permisos
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        wp_add_dashboard_widget(
            'simple_dte_dashboard',
            __('Boletas Electrónicas - Métricas', 'simple-dte'),
            [__CLASS__, 'render_dashboard_widget']
        );
    }

    /**
     * Renderizar widget del dashboard
     */
    public static function render_dashboard_widget() {
        echo '<div class="simple-dte-dashboard">';

        // Sección 1: DTEs Generadas
        self::render_dte_statistics();

        echo '<hr style="margin: 15px 0;">';

        // Sección 2: Cola de Reintentos
        self::render_queue_statistics();

        echo '<hr style="margin: 15px 0;">';

        // Sección 3: Folios Disponibles
        self::render_folio_statistics();

        echo '<hr style="margin: 15px 0;">';

        // Sección 4: Estado del Sistema
        self::render_system_status();

        echo '</div>';
    }

    /**
     * Renderizar estadísticas de DTEs
     */
    private static function render_dte_statistics() {
        global $wpdb;

        echo '<h3 style="margin-top: 0;">📊 DTEs Generadas</h3>';

        // DTEs hoy
        $hoy_inicio = date('Y-m-d 00:00:00');
        $hoy_fin = date('Y-m-d 23:59:59');

        $dtes_hoy = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = '_simple_dte_generada'
             AND pm.meta_value = 'yes'
             AND p.post_type = 'shop_order'
             AND p.post_date >= %s
             AND p.post_date <= %s",
            $hoy_inicio,
            $hoy_fin
        ));

        // DTEs esta semana
        $semana_inicio = date('Y-m-d 00:00:00', strtotime('monday this week'));
        $semana_fin = date('Y-m-d 23:59:59');

        $dtes_semana = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = '_simple_dte_generada'
             AND pm.meta_value = 'yes'
             AND p.post_type = 'shop_order'
             AND p.post_date >= %s
             AND p.post_date <= %s",
            $semana_inicio,
            $semana_fin
        ));

        // DTEs este mes
        $mes_inicio = date('Y-m-01 00:00:00');
        $mes_fin = date('Y-m-t 23:59:59');

        $dtes_mes = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = '_simple_dte_generada'
             AND pm.meta_value = 'yes'
             AND p.post_type = 'shop_order'
             AND p.post_date >= %s
             AND p.post_date <= %s",
            $mes_inicio,
            $mes_fin
        ));

        // Notas de crédito este mes
        $nc_mes = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = '_simple_dte_nc_generada'
             AND pm.meta_value = 'yes'
             AND p.post_type = 'shop_order'
             AND p.post_date >= %s
             AND p.post_date <= %s",
            $mes_inicio,
            $mes_fin
        ));

        echo '<div class="simple-dte-stats">';
        echo '<div class="stat-box">';
        echo '<div class="stat-label">Hoy</div>';
        echo '<div class="stat-value">' . esc_html($dtes_hoy) . '</div>';
        echo '</div>';

        echo '<div class="stat-box">';
        echo '<div class="stat-label">Esta Semana</div>';
        echo '<div class="stat-value">' . esc_html($dtes_semana) . '</div>';
        echo '</div>';

        echo '<div class="stat-box">';
        echo '<div class="stat-label">Este Mes</div>';
        echo '<div class="stat-value">' . esc_html($dtes_mes) . '</div>';
        echo '</div>';

        echo '<div class="stat-box">';
        echo '<div class="stat-label">NC Este Mes</div>';
        echo '<div class="stat-value">' . esc_html($nc_mes) . '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Renderizar estadísticas de cola
     */
    private static function render_queue_statistics() {
        if (!class_exists('Simple_DTE_Queue')) {
            return;
        }

        echo '<h3>🔄 Cola de Reintentos</h3>';

        $stats = Simple_DTE_Queue::get_statistics();

        $pendientes = $stats['pendientes'] ?? 0;
        $completados_hoy = $stats['completados_hoy'] ?? 0;
        $fallidos = $stats['fallidos'] ?? 0;
        $proximo_reintento = $stats['proximo_reintento'] ?? null;

        echo '<div class="simple-dte-stats">';
        echo '<div class="stat-box">';
        echo '<div class="stat-label">Pendientes</div>';
        echo '<div class="stat-value' . ($pendientes > 0 ? ' stat-warning' : '') . '">' . esc_html($pendientes) . '</div>';
        echo '</div>';

        echo '<div class="stat-box">';
        echo '<div class="stat-label">Completados Hoy</div>';
        echo '<div class="stat-value stat-success">' . esc_html($completados_hoy) . '</div>';
        echo '</div>';

        echo '<div class="stat-box">';
        echo '<div class="stat-label">Fallidos</div>';
        echo '<div class="stat-value' . ($fallidos > 0 ? ' stat-error' : '') . '">' . esc_html($fallidos) . '</div>';
        echo '</div>';
        echo '</div>';

        if ($proximo_reintento && $pendientes > 0) {
            $tiempo_restante = human_time_diff(strtotime($proximo_reintento), current_time('timestamp'));
            echo '<p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">';
            echo '⏱️ Próximo reintento en: ' . esc_html($tiempo_restante);
            echo '</p>';
        }

        if ($fallidos > 0) {
            echo '<p style="margin: 10px 0 0 0;">';
            echo '<a href="' . admin_url('admin.php?page=simple-dte-logs&status=failed') . '" class="button button-small">';
            echo 'Ver DTEs Fallidos';
            echo '</a>';
            echo '</p>';
        }
    }

    /**
     * Renderizar estadísticas de folios
     */
    private static function render_folio_statistics() {
        echo '<h3>📄 Folios Disponibles</h3>';

        // Buscar archivos CAF en el directorio folios/
        $folios_dir = plugin_dir_path(dirname(dirname(__FILE__))) . 'folios';
        $caf_files = [];

        if (is_dir($folios_dir)) {
            $archivos = glob($folios_dir . '/folio_*.xml');
            foreach ($archivos as $archivo) {
                $xml = simplexml_load_file($archivo);
                if ($xml && isset($xml->CAF->DA->RNG)) {
                    $tipo_dte = (string) $xml->CAF->DA->TD;
                    $desde = (int) $xml->CAF->DA->RNG->D;
                    $hasta = (int) $xml->CAF->DA->RNG->H;

                    if (!isset($caf_files[$tipo_dte])) {
                        $caf_files[$tipo_dte] = [
                            'desde' => $desde,
                            'hasta' => $hasta,
                            'total' => ($hasta - $desde + 1)
                        ];
                    } else {
                        // Si hay múltiples CAF, sumar
                        $caf_files[$tipo_dte]['total'] += ($hasta - $desde + 1);
                        $caf_files[$tipo_dte]['hasta'] = max($caf_files[$tipo_dte]['hasta'], $hasta);
                    }
                }
            }
        }

        if (empty($caf_files)) {
            echo '<p style="color: #d63638; font-weight: bold;">⚠️ No se encontraron archivos CAF</p>';
            echo '<p style="font-size: 12px;">Agregue archivos CAF (.xml) al directorio <code>folios/</code></p>';
            return;
        }

        echo '<div class="simple-dte-stats">';

        foreach ($caf_files as $tipo_dte => $info) {
            $tipo_nombre = self::get_tipo_dte_nombre($tipo_dte);
            $clase_alerta = $info['total'] < 100 ? 'stat-error' : ($info['total'] < 500 ? 'stat-warning' : '');

            echo '<div class="stat-box">';
            echo '<div class="stat-label">' . esc_html($tipo_nombre) . '</div>';
            echo '<div class="stat-value ' . $clase_alerta . '">' . esc_html($info['total']) . '</div>';
            echo '<div class="stat-detail">Rango: ' . esc_html($info['desde']) . ' - ' . esc_html($info['hasta']) . '</div>';
            echo '</div>';
        }

        echo '</div>';

        // Alerta si hay pocos folios
        foreach ($caf_files as $tipo_dte => $info) {
            if ($info['total'] < 100) {
                $tipo_nombre = self::get_tipo_dte_nombre($tipo_dte);
                echo '<p style="color: #d63638; margin: 10px 0 0 0;">';
                echo '⚠️ <strong>ALERTA:</strong> Quedan menos de 100 folios para ' . esc_html($tipo_nombre);
                echo '</p>';
            }
        }
    }

    /**
     * Renderizar estado del sistema
     */
    private static function render_system_status() {
        echo '<h3>🔧 Estado del Sistema</h3>';

        $checks = [];

        // Check 1: WooCommerce activo
        $checks[] = [
            'label' => 'WooCommerce',
            'status' => class_exists('WooCommerce'),
            'message' => class_exists('WooCommerce') ? 'Activo' : 'No instalado'
        ];

        // Check 2: HPOS habilitado
        $hpos_enabled = false;
        if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
            $hpos_enabled = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        }
        $checks[] = [
            'label' => 'HPOS (High-Performance Order Storage)',
            'status' => true,
            'message' => $hpos_enabled ? 'Habilitado ✓' : 'Deshabilitado (modo legacy)'
        ];

        // Check 3: Logger funcionando
        $checks[] = [
            'label' => 'Sistema de Logs',
            'status' => class_exists('Simple_DTE_Logger'),
            'message' => class_exists('Simple_DTE_Logger') ? 'Funcionando' : 'No disponible'
        ];

        // Check 4: Cola de reintentos
        $checks[] = [
            'label' => 'Cola de Reintentos',
            'status' => class_exists('Simple_DTE_Queue'),
            'message' => class_exists('Simple_DTE_Queue') ? 'Funcionando' : 'No disponible'
        ];

        // Check 5: Caché de RUT
        $checks[] = [
            'label' => 'Caché de RUT',
            'status' => class_exists('Simple_DTE_RUT_Cache'),
            'message' => class_exists('Simple_DTE_RUT_Cache') ? 'Funcionando' : 'No disponible'
        ];

        // Check 6: Directorio de logs
        $logs_dir = plugin_dir_path(dirname(dirname(__FILE__))) . 'logs';
        $logs_writable = is_dir($logs_dir) && is_writable($logs_dir);
        $checks[] = [
            'label' => 'Directorio de Logs',
            'status' => $logs_writable,
            'message' => $logs_writable ? 'Escribible' : 'No escribible'
        ];

        // Check 7: Directorio de PDFs
        $pdfs_dir = plugin_dir_path(dirname(dirname(__FILE__))) . 'pdfs';
        $pdfs_writable = is_dir($pdfs_dir) && is_writable($pdfs_dir);
        $checks[] = [
            'label' => 'Directorio de PDFs',
            'status' => $pdfs_writable,
            'message' => $pdfs_writable ? 'Escribible' : 'No escribible'
        ];

        echo '<table class="widefat" style="margin-top: 10px;">';
        echo '<tbody>';
        foreach ($checks as $check) {
            $icon = $check['status'] ? '✅' : '❌';
            echo '<tr>';
            echo '<td style="width: 30px; text-align: center;">' . $icon . '</td>';
            echo '<td><strong>' . esc_html($check['label']) . '</strong></td>';
            echo '<td>' . esc_html($check['message']) . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';

        // Última actualización de logs
        if (class_exists('Simple_DTE_Logger')) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'simple_dte_logs';

            $ultimo_log = $wpdb->get_var(
                "SELECT MAX(fecha_creacion) FROM {$table_name}"
            );

            if ($ultimo_log) {
                echo '<p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">';
                echo '📝 Última actividad: ' . esc_html(human_time_diff(strtotime($ultimo_log), current_time('timestamp'))) . ' atrás';
                echo '</p>';
            }
        }
    }

    /**
     * Obtener nombre del tipo de DTE
     */
    private static function get_tipo_dte_nombre($tipo) {
        $tipos = [
            '33' => 'Factura Electrónica',
            '34' => 'Factura Exenta',
            '39' => 'Boleta Electrónica',
            '41' => 'Boleta Exenta',
            '56' => 'Nota de Débito',
            '61' => 'Nota de Crédito',
        ];

        return $tipos[$tipo] ?? 'Tipo ' . $tipo;
    }

    /**
     * Cargar estilos del dashboard
     */
    public static function enqueue_styles($hook) {
        if ($hook !== 'index.php') {
            return;
        }

        // CSS inline para el widget
        $css = "
        .simple-dte-dashboard h3 {
            font-size: 14px;
            margin: 0 0 10px 0;
            color: #1d2327;
        }

        .simple-dte-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            margin: 10px 0;
        }

        .stat-box {
            background: #f6f7f7;
            border: 1px solid #dcdcde;
            border-radius: 4px;
            padding: 12px;
            text-align: center;
        }

        .stat-label {
            font-size: 11px;
            color: #646970;
            text-transform: uppercase;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #1d2327;
        }

        .stat-value.stat-success {
            color: #00a32a;
        }

        .stat-value.stat-warning {
            color: #dba617;
        }

        .stat-value.stat-error {
            color: #d63638;
        }

        .stat-detail {
            font-size: 11px;
            color: #646970;
            margin-top: 5px;
        }
        ";

        wp_add_inline_style('dashboard', $css);
    }
}

// Inicializar dashboard
add_action('admin_init', ['Simple_DTE_Dashboard', 'init']);
