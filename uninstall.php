<?php
/**
 * Desinstalación del plugin
 *
 * Este archivo se ejecuta cuando el plugin es eliminado desde WordPress
 * Elimina TODOS los datos: tablas, archivos, opciones, meta datos
 *
 * @package Simple_DTE
 */

// Si no se está desinstalando, salir
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Eliminar tablas de base de datos
 */
function simple_dte_drop_tables() {
    global $wpdb;

    $tables = array(
        $wpdb->prefix . 'simple_dte_logs',
        $wpdb->prefix . 'simple_dte_folios',
        $wpdb->prefix . 'simple_dte_queue'
    );

    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

/**
 * Eliminar todas las opciones del plugin
 */
function simple_dte_delete_options() {
    $options = array(
        // Configuración general
        'simple_dte_ambiente',
        'simple_dte_api_key',
        'simple_dte_debug',
        'simple_dte_rvd_auto',

        // Datos del emisor
        'simple_dte_rut_emisor',
        'simple_dte_razon_social',
        'simple_dte_giro',
        'simple_dte_direccion',
        'simple_dte_comuna',
        'simple_dte_logo_url',

        // Certificado digital
        'simple_dte_cert_rut',
        'simple_dte_cert_password',
        'simple_dte_cert_path',

        // Boletas de Ajuste
        'simple_dte_auto_ajuste_enabled',

        // Email y SMTP
        'simple_dte_auto_email_enabled',
        'simple_dte_smtp_enabled',
        'simple_dte_smtp_host',
        'simple_dte_smtp_port',
        'simple_dte_smtp_secure',
        'simple_dte_smtp_auth',
        'simple_dte_smtp_username',
        'simple_dte_smtp_password',

        // Versión
        'simple_dte_version',
    );

    foreach ($options as $option) {
        delete_option($option);
    }
}

/**
 * Eliminar meta datos de órdenes
 */
function simple_dte_delete_order_meta() {
    global $wpdb;

    $meta_keys = array(
        '_simple_dte_generada',
        '_simple_dte_folio',
        '_simple_dte_tipo',
        '_simple_dte_fecha_generacion',
        '_simple_dte_xml',
        '_simple_dte_pdf_path',
        '_simple_dte_anulada',
        '_simple_dte_fecha_anulacion',
        '_simple_dte_email_enviado',
        '_simple_dte_email_fecha',
        '_billing_rut',
    );

    foreach ($meta_keys as $meta_key) {
        // Eliminar de wp_postmeta (órdenes tradicionales)
        $wpdb->delete(
            $wpdb->postmeta,
            array('meta_key' => $meta_key),
            array('%s')
        );

        // Eliminar de wp_wc_orders_meta (HPOS)
        $orders_meta_table = $wpdb->prefix . 'wc_orders_meta';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$orders_meta_table}'") === $orders_meta_table) {
            $wpdb->delete(
                $orders_meta_table,
                array('meta_key' => $meta_key),
                array('%s')
            );
        }
    }
}

/**
 * Eliminar archivos subidos
 */
function simple_dte_delete_uploaded_files() {
    $upload_dir = wp_upload_dir();
    $simple_dte_dir = $upload_dir['basedir'] . '/simple-dte/';

    if (file_exists($simple_dte_dir)) {
        simple_dte_recursive_delete($simple_dte_dir);
    }
}

/**
 * Eliminar directorio recursivamente
 */
function simple_dte_recursive_delete($dir) {
    if (!file_exists($dir)) {
        return;
    }

    $files = array_diff(scandir($dir), array('.', '..'));

    foreach ($files as $file) {
        $path = $dir . '/' . $file;

        if (is_dir($path)) {
            simple_dte_recursive_delete($path);
        } else {
            @unlink($path);
        }
    }

    @rmdir($dir);
}

/**
 * Eliminar cron jobs
 */
function simple_dte_delete_cron_jobs() {
    $crons = array(
        'simple_dte_process_queue',
        'simple_dte_envio_resumen_diario',
        'simple_dte_envio_rvd',
    );

    foreach ($crons as $cron) {
        $timestamp = wp_next_scheduled($cron);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $cron);
        }
        wp_clear_scheduled_hook($cron);
    }
}

/**
 * Limpiar caché de transients
 */
function simple_dte_delete_transients() {
    global $wpdb;

    // Eliminar transients que empiecen con simple_dte_
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '_transient_simple_dte_%'
         OR option_name LIKE '_transient_timeout_simple_dte_%'"
    );
}

/**
 * Ejecutar desinstalación completa
 */
function simple_dte_uninstall() {
    // Verificar permisos
    if (!current_user_can('activate_plugins')) {
        return;
    }

    // Log antes de eliminar (último registro)
    error_log('Simple DTE: Iniciando desinstalación completa del plugin');

    // Eliminar en orden
    simple_dte_delete_cron_jobs();
    simple_dte_delete_order_meta();
    simple_dte_delete_uploaded_files();
    simple_dte_delete_options();
    simple_dte_delete_transients();
    simple_dte_drop_tables();

    // Limpiar cache de WordPress
    wp_cache_flush();

    error_log('Simple DTE: Desinstalación completada - Todos los datos eliminados');
}

// Ejecutar desinstalación
simple_dte_uninstall();
