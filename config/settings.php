<?php
/**
 * Sistema de Configuración Centralizado
 *
 * Todas las configuraciones del sistema en un solo lugar
 * con validación, ambientes y opciones avanzadas
 */

class ConfiguracionSistema {

    private static $instance = null;
    private $config = [];
    private $ambiente = 'certificacion'; // certificacion | produccion

    private function __construct() {
        $this->cargarConfiguracion();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function cargarConfiguracion() {
        // Detectar ambiente desde variable de entorno
        $this->ambiente = getenv('AMBIENTE') ?: 'certificacion';

        // ========================================
        // CONFIGURACIÓN GENERAL
        // ========================================
        $this->config['general'] = [
            'ambiente' => $this->ambiente,
            'debug' => getenv('DEBUG') === 'true',
            'timezone' => 'America/Santiago',
            'locale' => 'es_CL.UTF-8',
        ];

        // ========================================
        // CONFIGURACIÓN DEL EMISOR
        // ========================================
        $this->config['emisor'] = [
            'rut' => getenv('RUT_EMISOR') ?: '78274225-6',
            'razon_social' => getenv('RAZON_SOCIAL') ?: 'AKIBARA SPA',
            'giro' => getenv('GIRO') ?: 'Servicios de Tecnología',
            'direccion' => getenv('DIRECCION') ?: 'Av. Providencia 1234',
            'comuna' => getenv('COMUNA') ?: 'Providencia',
            'ciudad' => getenv('CIUDAD') ?: 'Santiago',
            'telefono' => getenv('TELEFONO') ?: '+56 2 2222 3333',
            'email' => getenv('EMAIL_EMISOR') ?: 'contacto@akibara.cl',
            'sitio_web' => getenv('SITIO_WEB') ?: 'https://akibara.cl',
        ];

        // ========================================
        // CONFIGURACIÓN SIMPLE API
        // ========================================
        $this->config['api'] = [
            'base_url' => $this->ambiente === 'produccion'
                ? 'https://api.simpleapi.cl'
                : 'https://api.simpleapi.cl',
            'api_key' => getenv('API_KEY') ?: 'ZmNhMzYyMzYtZDFmNy00MGU2LWExYzYtOTA1NjQ3NjEwYjg2OjJjOTkyNjIzLTA5MTEtNDMwNi1hZGY3LWQwN2JlMjQzM2RkNQ==',
            'timeout' => (int) (getenv('API_TIMEOUT') ?: 30),
            'max_reintentos' => (int) (getenv('API_MAX_REINTENTOS') ?: 3),
            'espera_entre_reintentos' => (int) (getenv('API_ESPERA_REINTENTOS') ?: 2),
            'exponential_backoff' => getenv('API_EXPONENTIAL_BACKOFF') !== 'false',
        ];

        // ========================================
        // CONFIGURACIÓN DE CERTIFICADOS
        // ========================================
        $this->config['certificado'] = [
            'path' => getenv('CERT_PATH') ?: __DIR__ . '/../16694181-4.pfx',
            'password' => getenv('CERT_PASSWORD') ?: 'Prueba123',
            'validar_expiracion' => getenv('CERT_VALIDAR_EXPIRACION') !== 'false',
            'dias_alerta_expiracion' => (int) (getenv('CERT_DIAS_ALERTA') ?: 30),
        ];

        // ========================================
        // CONFIGURACIÓN DE CAF (Folios)
        // ========================================
        $this->config['caf'] = [
            'path' => getenv('CAF_PATH') ?: __DIR__ . '/../FoliosSII78274225391889202511161321.xml',
            'alertar_folios_bajos' => getenv('CAF_ALERTAR_FOLIOS_BAJOS') !== 'false',
            'umbral_folios_bajos' => (int) (getenv('CAF_UMBRAL_FOLIOS_BAJOS') ?: 10),
            'auto_solicitar_folios' => getenv('CAF_AUTO_SOLICITAR') === 'true',
        ];

        // ========================================
        // CONFIGURACIÓN DE BASE DE DATOS
        // ========================================
        $this->config['database'] = [
            'habilitado' => getenv('DB_NAME') && getenv('DB_USER'),
            'host' => getenv('DB_HOST') ?: 'localhost',
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'name' => getenv('DB_NAME') ?: '',
            'user' => getenv('DB_USER') ?: '',
            'password' => getenv('DB_PASS') ?: '',
            'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
            'pool_size' => (int) (getenv('DB_POOL_SIZE') ?: 5),
            'timeout' => (int) (getenv('DB_TIMEOUT') ?: 5),
            'fallback_to_files' => getenv('DB_FALLBACK_FILES') !== 'false',
        ];

        // ========================================
        // CONFIGURACIÓN DE LOGGING
        // ========================================
        $this->config['logging'] = [
            'habilitado' => getenv('LOGGING_ENABLED') !== 'false',
            'nivel' => getenv('LOG_LEVEL') ?: 'INFO', // DEBUG | INFO | WARNING | ERROR
            'path' => getenv('LOG_PATH') ?: __DIR__ . '/../logs',
            'guardar_en_bd' => getenv('LOG_BD') === 'true' && $this->config['database']['habilitado'],
            'guardar_en_archivo' => getenv('LOG_FILE') !== 'false',
            'rotacion_dias' => (int) (getenv('LOG_ROTACION_DIAS') ?: 30),
            'max_size_mb' => (int) (getenv('LOG_MAX_SIZE_MB') ?: 100),
            'incluir_debug_info' => getenv('LOG_DEBUG_INFO') === 'true',
        ];

        // ========================================
        // CONFIGURACIÓN DE EMAIL
        // ========================================
        $this->config['email'] = [
            'habilitado' => getenv('EMAIL_ENABLED') !== 'false',
            'metodo' => getenv('EMAIL_METODO') ?: 'auto', // auto | smtp | wp_mail | mail
            'from_email' => getenv('EMAIL_FROM') ?: (function_exists('get_option') ? get_option('admin_email', 'noreply@ejemplo.cl') : 'noreply@ejemplo.cl'),
            'from_name' => getenv('EMAIL_FROM_NAME') ?: $this->config['emisor']['razon_social'],
            'reply_to' => getenv('EMAIL_REPLY_TO') ?: '',

            // SMTP
            'smtp_host' => getenv('SMTP_HOST') ?: '',
            'smtp_port' => (int) (getenv('SMTP_PORT') ?: 587),
            'smtp_user' => getenv('SMTP_USER') ?: '',
            'smtp_pass' => getenv('SMTP_PASS') ?: '',
            'smtp_secure' => getenv('SMTP_SECURE') ?: 'tls', // tls | ssl

            // Contenido
            'asunto_template' => getenv('EMAIL_ASUNTO') ?: 'Boleta Electrónica #{folio} - {razon_social}',
            'incluir_pdf' => getenv('EMAIL_INCLUIR_PDF') !== 'false',
            'incluir_xml' => getenv('EMAIL_INCLUIR_XML') === 'true',

            // Reintentos
            'max_reintentos' => (int) (getenv('EMAIL_MAX_REINTENTOS') ?: 3),
            'espera_entre_reintentos' => (int) (getenv('EMAIL_ESPERA_REINTENTOS') ?: 5),
        ];

        // ========================================
        // CONFIGURACIÓN DE PDF
        // ========================================
        $this->config['pdf'] = [
            'path_salida' => getenv('PDF_PATH') ?: __DIR__ . '/../pdfs',
            'incluir_logo' => getenv('PDF_INCLUIR_LOGO') !== 'false',
            'logo_path' => getenv('PDF_LOGO_PATH') ?: __DIR__ . '/../assets/logo.png',
            'logo_width' => (int) (getenv('PDF_LOGO_WIDTH') ?: 40),

            // Colores (RGB)
            'color_header' => $this->parseColorRGB(getenv('PDF_COLOR_HEADER') ?: '41,128,185'), // Azul
            'color_footer' => $this->parseColorRGB(getenv('PDF_COLOR_FOOTER') ?: '127,140,141'), // Gris
            'color_accent' => $this->parseColorRGB(getenv('PDF_COLOR_ACCENT') ?: '46,204,113'), // Verde

            // Formato
            'orientacion' => getenv('PDF_ORIENTACION') ?: 'P', // P (portrait) | L (landscape)
            'tamano' => getenv('PDF_TAMANO') ?: 'Letter', // Letter | A4
            'margenes' => [
                'top' => (int) (getenv('PDF_MARGEN_TOP') ?: 10),
                'bottom' => (int) (getenv('PDF_MARGEN_BOTTOM') ?: 10),
                'left' => (int) (getenv('PDF_MARGEN_LEFT') ?: 10),
                'right' => (int) (getenv('PDF_MARGEN_RIGHT') ?: 10),
            ],

            // Timbre PDF417
            'timbre_nivel_seguridad' => (int) (getenv('PDF417_NIVEL_SEGURIDAD') ?: 5),
            'timbre_escala' => (int) (getenv('PDF417_ESCALA') ?: 2),

            // Personalización
            'footer_texto' => getenv('PDF_FOOTER_TEXTO') ?: 'Documento Tributario Electrónico - SII Chile',
            'incluir_leyenda_sii' => getenv('PDF_INCLUIR_LEYENDA_SII') !== 'false',
        ];

        // ========================================
        // CONFIGURACIÓN DE CONSULTAS SII
        // ========================================
        $this->config['consulta_sii'] = [
            'automatica' => getenv('CONSULTA_SII_AUTO') !== 'false',
            'espera_inicial_segundos' => (int) (getenv('CONSULTA_SII_ESPERA') ?: 10),
            'max_intentos' => (int) (getenv('CONSULTA_SII_MAX_INTENTOS') ?: 3),
            'intervalo_intentos' => (int) (getenv('CONSULTA_SII_INTERVALO') ?: 30),
            'guardar_respuesta' => getenv('CONSULTA_SII_GUARDAR') !== 'false',
        ];

        // ========================================
        // CONFIGURACIÓN DE CACHE
        // ========================================
        $this->config['cache'] = [
            'habilitado' => getenv('CACHE_ENABLED') === 'true',
            'driver' => getenv('CACHE_DRIVER') ?: 'file', // file | redis | memcached
            'ttl_segundos' => (int) (getenv('CACHE_TTL') ?: 3600),
            'path' => getenv('CACHE_PATH') ?: __DIR__ . '/../cache',

            // Redis
            'redis_host' => getenv('REDIS_HOST') ?: 'localhost',
            'redis_port' => (int) (getenv('REDIS_PORT') ?: 6379),
            'redis_password' => getenv('REDIS_PASSWORD') ?: '',
        ];

        // ========================================
        // CONFIGURACIÓN DE SEGURIDAD
        // ========================================
        $this->config['seguridad'] = [
            'validar_rut_receptor' => getenv('VALIDAR_RUT_RECEPTOR') !== 'false',
            'validar_montos' => getenv('VALIDAR_MONTOS') !== 'false',
            'monto_maximo' => (int) (getenv('MONTO_MAXIMO') ?: 0), // 0 = sin límite
            'permitir_monto_cero' => getenv('PERMITIR_MONTO_CERO') === 'true',
            'sanitizar_inputs' => getenv('SANITIZAR_INPUTS') !== 'false',
            'log_accesos' => getenv('LOG_ACCESOS') === 'true',
        ];

        // ========================================
        // CONFIGURACIÓN VISUAL (CONSOLA)
        // ========================================
        $this->config['visual'] = [
            'colores_habilitados' => getenv('VISUAL_COLORES') !== 'false' && $this->soportaColores(),
            'emojis_habilitados' => getenv('VISUAL_EMOJIS') !== 'false',
            'barras_progreso' => getenv('VISUAL_BARRAS_PROGRESO') !== 'false',
            'animaciones' => getenv('VISUAL_ANIMACIONES') === 'true',
            'verbose' => getenv('VISUAL_VERBOSE') === 'true',
        ];

        // Aplicar timezone
        date_default_timezone_set($this->config['general']['timezone']);
    }

    /**
     * Obtener configuración
     */
    public function get($key, $default = null) {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Establecer configuración en runtime
     */
    public function set($key, $value) {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $k) {
            if (!isset($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config = $value;
    }

    /**
     * Obtener toda la configuración
     */
    public function getAll() {
        return $this->config;
    }

    /**
     * Validar configuración
     */
    public function validar() {
        $errores = [];

        // Validar certificado
        if (!file_exists($this->config['certificado']['path'])) {
            $errores[] = "Certificado no encontrado: {$this->config['certificado']['path']}";
        }

        // Validar CAF
        if (!file_exists($this->config['caf']['path'])) {
            $errores[] = "CAF no encontrado: {$this->config['caf']['path']}";
        }

        // Validar API Key
        if (empty($this->config['api']['api_key'])) {
            $errores[] = "API Key no configurada";
        }

        // Validar RUT emisor
        if (!$this->validarRUT($this->config['emisor']['rut'])) {
            $errores[] = "RUT emisor inválido: {$this->config['emisor']['rut']}";
        }

        return [
            'valido' => empty($errores),
            'errores' => $errores
        ];
    }

    /**
     * Parsear color RGB desde string
     */
    private function parseColorRGB($color) {
        $parts = explode(',', $color);
        return [
            'r' => (int) ($parts[0] ?? 0),
            'g' => (int) ($parts[1] ?? 0),
            'b' => (int) ($parts[2] ?? 0),
        ];
    }

    /**
     * Detectar si la terminal soporta colores
     */
    private function soportaColores() {
        if (DIRECTORY_SEPARATOR === '\\') {
            return getenv('ANSICON') !== false || getenv('ConEmuANSI') === 'ON';
        }
        return function_exists('posix_isatty') && @posix_isatty(STDOUT);
    }

    /**
     * Validar RUT chileno
     */
    private function validarRUT($rut) {
        $rut = preg_replace('/[^0-9kK\-]/', '', $rut);
        if (!preg_match('/^(\d{1,8})-([0-9kK])$/', $rut, $matches)) {
            return false;
        }

        $numero = $matches[1];
        $dv = strtoupper($matches[2]);

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
     * Exportar configuración a archivo .env
     */
    public function exportarEnv($path = null) {
        if ($path === null) {
            $path = __DIR__ . '/../.env.example';
        }

        $env = "# Configuración del Sistema de Boletas Electrónicas\n";
        $env .= "# Generado automáticamente el " . date('Y-m-d H:i:s') . "\n\n";

        $env .= "# AMBIENTE\n";
        $env .= "AMBIENTE=certificacion\n";
        $env .= "DEBUG=false\n\n";

        $env .= "# EMISOR\n";
        $env .= "RUT_EMISOR={$this->config['emisor']['rut']}\n";
        $env .= "RAZON_SOCIAL=\"{$this->config['emisor']['razon_social']}\"\n";
        $env .= "GIRO=\"{$this->config['emisor']['giro']}\"\n\n";

        $env .= "# API\n";
        $env .= "API_KEY={$this->config['api']['api_key']}\n";
        $env .= "API_TIMEOUT={$this->config['api']['timeout']}\n\n";

        $env .= "# CERTIFICADO\n";
        $env .= "CERT_PATH={$this->config['certificado']['path']}\n";
        $env .= "CERT_PASSWORD={$this->config['certificado']['password']}\n\n";

        $env .= "# BASE DE DATOS (Opcional)\n";
        $env .= "#DB_NAME=boletas_electronicas\n";
        $env .= "#DB_USER=root\n";
        $env .= "#DB_PASS=\n\n";

        $env .= "# EMAIL\n";
        $env .= "EMAIL_FROM={$this->config['email']['from_email']}\n";
        $env .= "#SMTP_HOST=smtp.gmail.com\n";
        $env .= "#SMTP_PORT=587\n";
        $env .= "#SMTP_USER=\n";
        $env .= "#SMTP_PASS=\n\n";

        file_put_contents($path, $env);

        return $path;
    }
}

// Alias global para fácil acceso
function config($key = null, $default = null) {
    $config = ConfiguracionSistema::getInstance();
    if ($key === null) {
        return $config;
    }
    return $config->get($key, $default);
}
