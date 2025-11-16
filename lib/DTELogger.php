<?php
/**
 * Sistema de logging estructurado para DTEs
 * Maneja logs a archivos diarios y opcionalmente a base de datos
 */

class DTELogger {
    const NIVEL_DEBUG = 'DEBUG';
    const NIVEL_INFO = 'INFO';
    const NIVEL_WARNING = 'WARNING';
    const NIVEL_ERROR = 'ERROR';
    const NIVEL_CRITICAL = 'CRITICAL';

    private $log_dir;
    private $usar_bd;
    private $repository;
    private $niveles_activos;

    /**
     * Constructor
     *
     * @param string $log_dir Directorio para logs
     * @param bool $usar_bd Usar base de datos para logs
     * @param array $niveles_activos Niveles de log a registrar
     */
    public function __construct($log_dir = null, $usar_bd = false, $niveles_activos = null) {
        $this->log_dir = $log_dir ?: __DIR__ . '/../logs';
        $this->usar_bd = $usar_bd;

        // Por defecto, registrar todos los niveles excepto DEBUG
        $this->niveles_activos = $niveles_activos ?: [
            self::NIVEL_INFO,
            self::NIVEL_WARNING,
            self::NIVEL_ERROR,
            self::NIVEL_CRITICAL
        ];

        // Crear directorio de logs si no existe
        if (!is_dir($this->log_dir)) {
            mkdir($this->log_dir, 0755, true);
        }

        // Inicializar repository si usa BD
        if ($this->usar_bd) {
            require_once(__DIR__ . '/BoletaRepository.php');
            $this->repository = new BoletaRepository();
        }
    }

    /**
     * Registrar mensaje de log
     *
     * @param string $nivel Nivel del log
     * @param string $operacion Operación realizada
     * @param string $mensaje Mensaje descriptivo
     * @param array $contexto Contexto adicional
     */
    private function log($nivel, $operacion, $mensaje, $contexto = []) {
        // Verificar si este nivel está activo
        if (!in_array($nivel, $this->niveles_activos)) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $contexto_str = !empty($contexto) ? json_encode($contexto, JSON_UNESCAPED_UNICODE) : '';

        // Formato de log: [TIMESTAMP] [NIVEL] [OPERACION] Mensaje {contexto}
        $log_line = sprintf(
            "[%s] [%s] [%s] %s",
            $timestamp,
            str_pad($nivel, 8),
            str_pad($operacion, 15),
            $mensaje
        );

        if ($contexto_str) {
            $log_line .= " {$contexto_str}";
        }

        $log_line .= "\n";

        // Escribir a archivo diario
        $this->escribirAArchivo($log_line, $nivel);

        // Escribir a base de datos si está habilitado
        if ($this->usar_bd && $this->repository) {
            try {
                $this->repository->registrarLog(
                    $nivel,
                    $operacion,
                    $mensaje,
                    $contexto,
                    $contexto['boleta_id'] ?? null
                );
            } catch (Exception $e) {
                // Si falla el log a BD, solo escribir a archivo de error
                error_log("Error guardando log en BD: " . $e->getMessage());
            }
        }
    }

    /**
     * Escribir log a archivo
     */
    private function escribirAArchivo($log_line, $nivel) {
        // Archivo principal del día
        $fecha = date('Y-m-d');
        $archivo_principal = "{$this->log_dir}/dte_{$fecha}.log";

        // Archivo específico por nivel para errores y críticos
        $archivo_nivel = null;
        if ($nivel === self::NIVEL_ERROR || $nivel === self::NIVEL_CRITICAL) {
            $archivo_nivel = "{$this->log_dir}/errors_{$fecha}.log";
        }

        // Escribir a archivo principal
        file_put_contents($archivo_principal, $log_line, FILE_APPEND | LOCK_EX);

        // Escribir a archivo de nivel si corresponde
        if ($archivo_nivel) {
            file_put_contents($archivo_nivel, $log_line, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * Log nivel DEBUG
     */
    public function debug($operacion, $mensaje, $contexto = []) {
        $this->log(self::NIVEL_DEBUG, $operacion, $mensaje, $contexto);
    }

    /**
     * Log nivel INFO
     */
    public function info($operacion, $mensaje, $contexto = []) {
        $this->log(self::NIVEL_INFO, $operacion, $mensaje, $contexto);
    }

    /**
     * Log nivel WARNING
     */
    public function warning($operacion, $mensaje, $contexto = []) {
        $this->log(self::NIVEL_WARNING, $operacion, $mensaje, $contexto);
    }

    /**
     * Log nivel ERROR
     */
    public function error($operacion, $mensaje, $contexto = []) {
        $this->log(self::NIVEL_ERROR, $operacion, $mensaje, $contexto);
    }

    /**
     * Log nivel CRITICAL
     */
    public function critical($operacion, $mensaje, $contexto = []) {
        $this->log(self::NIVEL_CRITICAL, $operacion, $mensaje, $contexto);
    }

    /**
     * Log específico para generación de boleta
     */
    public function logGenerarBoleta($folio, $tipo_dte, $resultado, $contexto = []) {
        $contexto['folio'] = $folio;
        $contexto['tipo_dte'] = $tipo_dte;

        if ($resultado['exito']) {
            $this->info('generar', "Boleta generada: Folio {$folio}", $contexto);
        } else {
            $this->error('generar', "Error generando boleta: {$resultado['error']}", $contexto);
        }
    }

    /**
     * Log específico para envío al SII
     */
    public function logEnviarSII($folio, $track_id, $resultado, $contexto = []) {
        $contexto['folio'] = $folio;
        $contexto['track_id'] = $track_id;

        if ($resultado['exito']) {
            $this->info('enviar_sii', "Boleta enviada al SII: Track ID {$track_id}", $contexto);
        } else {
            $this->error('enviar_sii', "Error enviando al SII: {$resultado['error']}", $contexto);
        }
    }

    /**
     * Log específico para consulta de estado
     */
    public function logConsultarEstado($track_id, $estado, $contexto = []) {
        $contexto['track_id'] = $track_id;
        $contexto['estado'] = $estado;

        $this->info('consultar_estado', "Estado SII consultado: {$estado}", $contexto);
    }

    /**
     * Log específico para envío de email
     */
    public function logEnviarEmail($folio, $email_destino, $resultado, $contexto = []) {
        $contexto['folio'] = $folio;
        $contexto['email_destino'] = $email_destino;

        if ($resultado['exito']) {
            $this->info('enviar_email', "Email enviado a {$email_destino}", $contexto);
        } else {
            $this->error('enviar_email', "Error enviando email: {$resultado['error']}", $contexto);
        }
    }

    /**
     * Log específico para generación de PDF
     */
    public function logGenerarPDF($folio, $resultado, $contexto = []) {
        $contexto['folio'] = $folio;

        if ($resultado['exito']) {
            $this->info('generar_pdf', "PDF generado para folio {$folio}", $contexto);
        } else {
            $this->error('generar_pdf', "Error generando PDF: {$resultado['error']}", $contexto);
        }
    }

    /**
     * Limpiar logs antiguos
     *
     * @param int $dias Días de antigüedad para eliminar
     */
    public function limpiarLogsAntiguos($dias = 30) {
        $archivos = glob($this->log_dir . '/*.log');
        $fecha_limite = strtotime("-{$dias} days");
        $eliminados = 0;

        foreach ($archivos as $archivo) {
            if (filemtime($archivo) < $fecha_limite) {
                if (unlink($archivo)) {
                    $eliminados++;
                }
            }
        }

        $this->info('mantenimiento', "Logs antiguos eliminados: {$eliminados} archivos");
        return $eliminados;
    }

    /**
     * Obtener últimos logs de un archivo
     *
     * @param int $lineas Número de líneas a obtener
     * @param string $fecha Fecha del log (Y-m-d), null = hoy
     */
    public function obtenerUltimosLogs($lineas = 100, $fecha = null) {
        $fecha = $fecha ?: date('Y-m-d');
        $archivo = "{$this->log_dir}/dte_{$fecha}.log";

        if (!file_exists($archivo)) {
            return [];
        }

        // Leer últimas N líneas
        $comando = "tail -n {$lineas} " . escapeshellarg($archivo);
        exec($comando, $output);

        return $output;
    }

    /**
     * Buscar en logs
     *
     * @param string $patron Patrón a buscar
     * @param string $fecha Fecha del log (Y-m-d), null = hoy
     */
    public function buscarEnLogs($patron, $fecha = null) {
        $fecha = $fecha ?: date('Y-m-d');
        $archivo = "{$this->log_dir}/dte_{$fecha}.log";

        if (!file_exists($archivo)) {
            return [];
        }

        $comando = "grep " . escapeshellarg($patron) . " " . escapeshellarg($archivo);
        exec($comando, $output);

        return $output;
    }
}
