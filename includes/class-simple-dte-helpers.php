<?php
/**
 * Funciones helper para Simple DTE
 *
 * @package Simple_DTE
 */

if (!defined('ABSPATH')) {
    exit;
}

class Simple_DTE_Helpers {

    /**
     * Limpiar RUT (quitar puntos y dejar solo guion)
     */
    public static function limpiar_rut($rut) {
        $rut = str_replace('.', '', $rut);
        $rut = strtoupper(trim($rut));
        return $rut;
    }

    /**
     * Validar formato de RUT chileno
     */
    public static function validar_rut($rut) {
        $rut = self::limpiar_rut($rut);

        if (!preg_match('/^(\d{1,8})-([0-9K])$/', $rut, $matches)) {
            return false;
        }

        $numero = $matches[1];
        $dv = $matches[2];

        return self::calcular_dv($numero) === $dv;
    }

    /**
     * Calcular dígito verificador de RUT
     */
    public static function calcular_dv($numero) {
        $suma = 0;
        $multiplicador = 2;

        for ($i = strlen($numero) - 1; $i >= 0; $i--) {
            $suma += (int)$numero[$i] * $multiplicador;
            $multiplicador = $multiplicador === 7 ? 2 : $multiplicador + 1;
        }

        $resto = $suma % 11;
        $dv = 11 - $resto;

        if ($dv === 11) return '0';
        if ($dv === 10) return 'K';

        return (string)$dv;
    }

    /**
     * Formatear RUT con puntos y guion
     */
    public static function formatear_rut($rut) {
        $rut = self::limpiar_rut($rut);

        if (!preg_match('/^(\d{1,8})-([0-9K])$/', $rut, $matches)) {
            return $rut;
        }

        $numero = $matches[1];
        $dv = $matches[2];

        return number_format($numero, 0, '', '.') . '-' . $dv;
    }

    /**
     * Calcular monto neto desde monto bruto (incluye IVA)
     */
    public static function calcular_neto($monto_bruto, $tasa_iva = 19) {
        return round($monto_bruto / (1 + ($tasa_iva / 100)));
    }

    /**
     * Calcular IVA desde monto bruto
     */
    public static function calcular_iva($monto_bruto, $tasa_iva = 19) {
        $neto = self::calcular_neto($monto_bruto, $tasa_iva);
        return $monto_bruto - $neto;
    }

    /**
     * Formatear monto para Chile (separador de miles con punto)
     */
    public static function formatear_monto($monto) {
        return '$' . number_format($monto, 0, ',', '.');
    }

    /**
     * Crear directorio seguro para uploads
     */
    public static function create_secure_upload_dir() {
        $upload_dir = wp_upload_dir();
        $simple_dte_dir = trailingslashit($upload_dir['basedir']) . 'simple-dte-secure/';

        if (!file_exists($simple_dte_dir)) {
            wp_mkdir_p($simple_dte_dir);

            // Proteger directorio
            file_put_contents($simple_dte_dir . '.htaccess', 'Deny from all');
            file_put_contents($simple_dte_dir . 'index.php', '<?php // Silence is golden');
        }

        return $simple_dte_dir;
    }

    /**
     * Validar archivo subido
     */
    public static function validate_upload($file, $allowed_extensions = array()) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return array(
                'success' => false,
                'message' => __('Error al subir el archivo', 'simple-dte')
            );
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!empty($allowed_extensions) && !in_array($ext, $allowed_extensions, true)) {
            return array(
                'success' => false,
                'message' => sprintf(
                    __('Extensión no permitida. Extensiones permitidas: %s', 'simple-dte'),
                    implode(', ', $allowed_extensions)
                )
            );
        }

        if ($file['size'] > 10 * 1024 * 1024) { // 10MB
            return array(
                'success' => false,
                'message' => __('El archivo es demasiado grande. Máximo 10MB', 'simple-dte')
            );
        }

        return array('success' => true);
    }

    /**
     * Obtener ambiente actual
     */
    public static function get_ambiente() {
        return get_option('simple_dte_ambiente', 'certificacion');
    }

    /**
     * Verificar si estamos en certificación
     */
    public static function is_certificacion() {
        return self::get_ambiente() === 'certificacion';
    }

    /**
     * Obtener URL base de la API según ambiente
     */
    public static function get_api_base_url() {
        if (self::is_certificacion()) {
            return SIMPLE_DTE_API_URL_CERT;
        }
        return SIMPLE_DTE_API_URL_PROD;
    }

    /**
     * Sanitizar XML
     */
    public static function sanitize_xml($xml) {
        // Eliminar BOM
        $xml = str_replace("\xEF\xBB\xBF", '', $xml);

        // Normalizar saltos de línea
        $xml = str_replace("\r\n", "\n", $xml);
        $xml = str_replace("\r", "\n", $xml);

        return trim($xml);
    }

    /**
     * Obtener tipos de DTE
     */
    public static function get_tipos_dte() {
        return array(
            33 => 'Factura Electrónica',
            34 => 'Factura No Afecta o Exenta',
            39 => 'Boleta Electrónica',
            41 => 'Boleta No Afecta o Exenta',
            46 => 'Factura de Compra',
            52 => 'Guía de Despacho',
            56 => 'Nota de Débito',
            61 => 'Nota de Crédito',
            110 => 'Factura de Exportación',
            111 => 'Nota de Débito de Exportación',
            112 => 'Nota de Crédito de Exportación'
        );
    }

    /**
     * Normalizar nombre de comuna
     */
    public static function normalizar_comuna($comuna) {
        $comunas_map = array(
            'santiago' => 'Santiago',
            'providencia' => 'Providencia',
            'las condes' => 'Las Condes',
            'vitacura' => 'Vitacura',
            'ñuñoa' => 'Ñuñoa',
            'nunoa' => 'Ñuñoa',
            'maipu' => 'Maipú',
            'maipú' => 'Maipú',
            'puente alto' => 'Puente Alto',
            'la florida' => 'La Florida',
            'san bernardo' => 'San Bernardo',
            'estacion central' => 'Estación Central',
            'estación central' => 'Estación Central',
            'san miguel' => 'San Miguel',
            // Agregar más según necesidad
        );

        $comuna_lower = mb_strtolower(trim($comuna), 'UTF-8');

        if (isset($comunas_map[$comuna_lower])) {
            return $comunas_map[$comuna_lower];
        }

        return mb_convert_case($comuna, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Obtener fecha actual en formato SII (AAAA-MM-DD)
     */
    public static function get_fecha_actual() {
        return current_time('Y-m-d');
    }

    /**
     * Validar fecha formato SII
     */
    public static function validar_fecha($fecha) {
        $d = DateTime::createFromFormat('Y-m-d', $fecha);
        return $d && $d->format('Y-m-d') === $fecha;
    }
}
