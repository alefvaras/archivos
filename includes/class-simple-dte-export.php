<?php
/**
 * Exportación de Reportes - Libro de Ventas
 *
 * @package Simple_DTE
 */

if (!defined('ABSPATH')) {
    exit;
}

class Simple_DTE_Export {

    /**
     * Exportar Libro de Ventas a CSV
     */
    public static function exportar_libro_ventas_csv($fecha_inicio, $fecha_fin, $formato = 'csv') {
        // Obtener órdenes con DTEs generados en el período
        $args = [
            'limit' => -1,
            'status' => ['wc-completed', 'wc-processing'],
            'date_created' => $fecha_inicio . '...' . $fecha_fin,
            'meta_query' => [
                [
                    'key' => '_simple_dte_generada',
                    'value' => 'yes'
                ]
            ]
        ];

        $orders = wc_get_orders($args);

        if (empty($orders)) {
            return false;
        }

        // Preparar datos
        $datos = [];
        $datos[] = [
            'Fecha',
            'Tipo DTE',
            'Folio',
            'RUT Receptor',
            'Razón Social',
            'Neto',
            'IVA',
            'Total',
            'Estado SII',
            'Track ID'
        ];

        foreach ($orders as $order) {
            $folio = $order->get_meta('_simple_dte_folio');
            $tipo_dte = $order->get_meta('_simple_dte_tipo');
            $fecha = $order->get_meta('_simple_dte_fecha_generacion');
            $rut = $order->get_meta('_billing_rut');

            if (empty($rut)) {
                $rut = '66666666-6';
            }

            $nombre = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
            $neto = $order->get_total() - $order->get_total_tax();
            $iva = $order->get_total_tax();
            $total = $order->get_total();

            // Estado SII (si está disponible)
            $track_id = $order->get_meta('_simple_dte_track_id');
            $estado_sii = 'REC'; // Por defecto

            $datos[] = [
                date('d/m/Y', strtotime($fecha)),
                $tipo_dte == 39 ? 'Boleta' : 'DTE ' . $tipo_dte,
                $folio,
                $rut,
                $nombre,
                number_format($neto, 0, ',', '.'),
                number_format($iva, 0, ',', '.'),
                number_format($total, 0, ',', '.'),
                $estado_sii,
                $track_id
            ];
        }

        // Generar archivo según formato
        if ($formato === 'excel') {
            return self::generar_excel($datos, 'libro-ventas');
        } else {
            return self::generar_csv($datos, 'libro-ventas');
        }
    }

    /**
     * Generar archivo CSV
     */
    private static function generar_csv($datos, $nombre_archivo) {
        $filename = $nombre_archivo . '-' . date('Y-m-d') . '.csv';
        $filepath = sys_get_temp_dir() . '/' . $filename;

        $fp = fopen($filepath, 'w');

        // BOM para UTF-8
        fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));

        foreach ($datos as $fila) {
            fputcsv($fp, $fila, ';');
        }

        fclose($fp);

        Simple_DTE_Logger::info('Libro de Ventas exportado (CSV)', [
            'operacion' => 'export_libro_ventas',
            'registros' => count($datos) - 1,
            'archivo' => $filename
        ]);

        return $filepath;
    }

    /**
     * Generar archivo Excel (usando formato CSV compatible)
     */
    private static function generar_excel($datos, $nombre_archivo) {
        // Por ahora usar CSV, se puede mejorar con PHPSpreadsheet
        return self::generar_csv($datos, $nombre_archivo);
    }

    /**
     * Exportar Notas de Crédito a CSV
     */
    public static function exportar_nc_csv($fecha_inicio, $fecha_fin) {
        // Obtener órdenes con NC generadas en el período
        $args = [
            'limit' => -1,
            'status' => ['wc-completed', 'wc-processing', 'wc-refunded'],
            'date_created' => $fecha_inicio . '...' . $fecha_fin,
            'meta_query' => [
                [
                    'key' => '_simple_dte_nc_generada',
                    'value' => 'yes'
                ]
            ]
        ];

        $orders = wc_get_orders($args);

        if (empty($orders)) {
            return false;
        }

        // Preparar datos
        $datos = [];
        $datos[] = [
            'Fecha',
            'Folio NC',
            'Folio Referencia',
            'RUT Receptor',
            'Razón Social',
            'Monto',
            'Motivo'
        ];

        foreach ($orders as $order) {
            $folio_nc = $order->get_meta('_simple_dte_nc_folio');
            $folio_ref = $order->get_meta('_simple_dte_folio');
            $fecha_nc = $order->get_meta('_simple_dte_nc_fecha');
            $rut = $order->get_meta('_billing_rut');

            if (empty($rut)) {
                $rut = '66666666-6';
            }

            $nombre = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
            $monto = $order->get_total();

            $datos[] = [
                date('d/m/Y', strtotime($fecha_nc)),
                $folio_nc,
                $folio_ref,
                $rut,
                $nombre,
                number_format($monto, 0, ',', '.'),
                'Anulación'
            ];
        }

        return self::generar_csv($datos, 'notas-credito');
    }

    /**
     * Exportar resumen mensual
     */
    public static function exportar_resumen_mensual($mes, $anio) {
        $fecha_inicio = "{$anio}-{$mes}-01";
        $ultimo_dia = date('t', strtotime($fecha_inicio));
        $fecha_fin = "{$anio}-{$mes}-{$ultimo_dia}";

        // Estadísticas del mes
        $stats = self::get_estadisticas_periodo($fecha_inicio, $fecha_fin);

        $datos = [];
        $datos[] = ['RESUMEN MENSUAL - ' . date('F Y', strtotime($fecha_inicio))];
        $datos[] = [];
        $datos[] = ['Concepto', 'Cantidad', 'Monto'];
        $datos[] = ['Total Boletas Emitidas', $stats['total_boletas'], number_format($stats['monto_boletas'], 0, ',', '.')];
        $datos[] = ['Total Notas de Crédito', $stats['total_nc'], number_format($stats['monto_nc'], 0, ',', '.')];
        $datos[] = [];
        $datos[] = ['Monto Neto', '', number_format($stats['neto_total'], 0, ',', '.')];
        $datos[] = ['IVA', '', number_format($stats['iva_total'], 0, ',', '.')];
        $datos[] = ['Total', '', number_format($stats['total_general'], 0, ',', '.')];

        return self::generar_csv($datos, 'resumen-mensual');
    }

    /**
     * Obtener estadísticas de un período
     */
    private static function get_estadisticas_periodo($fecha_inicio, $fecha_fin) {
        // Boletas
        $args_boletas = [
            'limit' => -1,
            'status' => ['wc-completed', 'wc-processing'],
            'date_created' => $fecha_inicio . '...' . $fecha_fin,
            'meta_query' => [
                [
                    'key' => '_simple_dte_generada',
                    'value' => 'yes'
                ]
            ]
        ];

        $boletas = wc_get_orders($args_boletas);

        $total_boletas = count($boletas);
        $monto_boletas = 0;
        $neto_total = 0;
        $iva_total = 0;

        foreach ($boletas as $orden) {
            $monto_boletas += $orden->get_total();
            $neto_total += ($orden->get_total() - $orden->get_total_tax());
            $iva_total += $orden->get_total_tax();
        }

        // Notas de Crédito
        $args_nc = [
            'limit' => -1,
            'status' => ['wc-completed', 'wc-processing', 'wc-refunded'],
            'date_created' => $fecha_inicio . '...' . $fecha_fin,
            'meta_query' => [
                [
                    'key' => '_simple_dte_nc_generada',
                    'value' => 'yes'
                ]
            ]
        ];

        $ncs = wc_get_orders($args_nc);

        $total_nc = count($ncs);
        $monto_nc = 0;

        foreach ($ncs as $orden) {
            $monto_nc += $orden->get_total();
        }

        return [
            'total_boletas' => $total_boletas,
            'monto_boletas' => $monto_boletas,
            'total_nc' => $total_nc,
            'monto_nc' => $monto_nc,
            'neto_total' => $neto_total,
            'iva_total' => $iva_total,
            'total_general' => $monto_boletas - $monto_nc
        ];
    }

    /**
     * Descargar archivo
     */
    public static function descargar_archivo($filepath, $filename) {
        if (!file_exists($filepath)) {
            return false;
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);

        // Eliminar archivo temporal
        unlink($filepath);

        exit;
    }
}
