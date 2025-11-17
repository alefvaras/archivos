<?php
/**
 * Caché de Validaciones de RUT
 *
 * @package Simple_DTE
 */

if (!defined('ABSPATH')) {
    exit;
}

class Simple_DTE_RUT_Cache {

    /**
     * Grupo de caché
     */
    const CACHE_GROUP = 'simple_dte_rut';

    /**
     * Tiempo de caché (24 horas)
     */
    const CACHE_EXPIRATION = 86400;

    /**
     * Validar RUT con caché
     */
    public static function validar_rut($rut) {
        // Limpiar RUT
        $rut_limpio = self::limpiar_rut($rut);
        
        // Buscar en caché
        $cache_key = 'rut_' . md5($rut_limpio);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            // Log hit de caché
            Simple_DTE_Logger::debug('RUT validado desde caché', [
                'rut' => $rut_limpio,
                'operacion' => 'rut_cache_hit'
            ]);

            return $cached;
        }

        // Validar RUT
        $resultado = self::validar_formato_rut($rut_limpio);

        // Guardar en caché
        wp_cache_set($cache_key, $resultado, self::CACHE_GROUP, self::CACHE_EXPIRATION);

        // Log miss de caché
        Simple_DTE_Logger::debug('RUT validado y guardado en caché', [
            'rut' => $rut_limpio,
            'valido' => $resultado !== false,
            'operacion' => 'rut_cache_miss'
        ]);

        return $resultado;
    }

    /**
     * Limpiar RUT
     */
    private static function limpiar_rut($rut) {
        return str_replace(['.', ' ', ','], '', $rut);
    }

    /**
     * Validar formato de RUT chileno
     */
    private static function validar_formato_rut($rut) {
        if (empty($rut)) {
            return false;
        }

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

        if ($dv === $dv_calculado) {
            return $numero . '-' . $dv; // Retornar RUT formateado
        }

        return false;
    }

    /**
     * Formatear RUT
     */
    public static function formatear_rut($rut, $con_puntos = false) {
        $rut_limpio = self::limpiar_rut($rut);
        
        if (strlen($rut_limpio) < 2) {
            return $rut;
        }

        $dv = substr($rut_limpio, -1);
        $numero = substr($rut_limpio, 0, -1);

        if ($con_puntos) {
            // Formato: 12.345.678-9
            $numero_formateado = number_format($numero, 0, '', '.');
            return $numero_formateado . '-' . strtoupper($dv);
        }

        // Formato: 12345678-9
        return $numero . '-' . strtoupper($dv);
    }

    /**
     * Invalidar caché de un RUT
     */
    public static function invalidar_rut($rut) {
        $rut_limpio = self::limpiar_rut($rut);
        $cache_key = 'rut_' . md5($rut_limpio);
        
        wp_cache_delete($cache_key, self::CACHE_GROUP);

        Simple_DTE_Logger::debug('Caché de RUT invalidado', [
            'rut' => $rut_limpio,
            'operacion' => 'rut_cache_invalidate'
        ]);
    }

    /**
     * Limpiar toda la caché de RUTs
     */
    public static function limpiar_cache() {
        wp_cache_flush_group(self::CACHE_GROUP);

        Simple_DTE_Logger::info('Caché de RUTs limpiada completamente', [
            'operacion' => 'rut_cache_flush'
        ]);
    }

    /**
     * Obtener estadísticas de caché
     */
    public static function get_stats() {
        // Estas estadísticas se obtendrían del logger
        $logs = Simple_DTE_Logger::get_logs([
            'operacion' => 'rut_cache_hit',
            'limit' => 1000
        ]);

        $hits = count($logs);

        $logs_miss = Simple_DTE_Logger::get_logs([
            'operacion' => 'rut_cache_miss',
            'limit' => 1000
        ]);

        $misses = count($logs_miss);

        $total = $hits + $misses;
        $hit_rate = $total > 0 ? round(($hits / $total) * 100, 2) : 0;

        return [
            'hits' => $hits,
            'misses' => $misses,
            'total' => $total,
            'hit_rate' => $hit_rate
        ];
    }
}
