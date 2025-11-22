<?php
/**
 * Helper para Salidas Visuales Mejoradas
 *
 * Proporciona colores, emojis, barras de progreso y formato visual
 * para mejorar la experiencia de usuario en consola
 */

class VisualHelper {

    private static $instance = null;
    private $coloresHabilitados = true;
    private $emojisHabilitados = true;

    // Códigos de color ANSI
    const COLOR_RESET = "\033[0m";
    const COLOR_BOLD = "\033[1m";
    const COLOR_DIM = "\033[2m";
    const COLOR_UNDERLINE = "\033[4m";

    // Colores de texto
    const COLOR_BLACK = "\033[30m";
    const COLOR_RED = "\033[31m";
    const COLOR_GREEN = "\033[32m";
    const COLOR_YELLOW = "\033[33m";
    const COLOR_BLUE = "\033[34m";
    const COLOR_MAGENTA = "\033[35m";
    const COLOR_CYAN = "\033[36m";
    const COLOR_WHITE = "\033[37m";

    // Colores de fondo
    const BG_BLACK = "\033[40m";
    const BG_RED = "\033[41m";
    const BG_GREEN = "\033[42m";
    const BG_YELLOW = "\033[43m";
    const BG_BLUE = "\033[44m";
    const BG_MAGENTA = "\033[45m";
    const BG_CYAN = "\033[46m";
    const BG_WHITE = "\033[47m";

    // Colores brillantes
    const COLOR_BRIGHT_BLACK = "\033[90m";
    const COLOR_BRIGHT_RED = "\033[91m";
    const COLOR_BRIGHT_GREEN = "\033[92m";
    const COLOR_BRIGHT_YELLOW = "\033[93m";
    const COLOR_BRIGHT_BLUE = "\033[94m";
    const COLOR_BRIGHT_MAGENTA = "\033[95m";
    const COLOR_BRIGHT_CYAN = "\033[96m";
    const COLOR_BRIGHT_WHITE = "\033[97m";

    private function __construct() {
        // Detectar soporte de colores
        $this->coloresHabilitados = $this->detectarSoporteColores();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Detectar si la terminal soporta colores
     */
    private function detectarSoporteColores() {
        if (DIRECTORY_SEPARATOR === '\\') {
            return getenv('ANSICON') !== false || getenv('ConEmuANSI') === 'ON';
        }
        return function_exists('posix_isatty') && @posix_isatty(STDOUT);
    }

    /**
     * Aplicar color a texto
     */
    public function color($texto, $color, $bold = false) {
        if (!$this->coloresHabilitados) {
            return $texto;
        }

        $output = $color;
        if ($bold) {
            $output .= self::COLOR_BOLD;
        }
        $output .= $texto . self::COLOR_RESET;

        return $output;
    }

    /**
     * Métodos de conveniencia para colores comunes
     */
    public function success($texto, $bold = false) {
        return $this->color($texto, self::COLOR_BRIGHT_GREEN, $bold);
    }

    public function error($texto, $bold = false) {
        return $this->color($texto, self::COLOR_BRIGHT_RED, $bold);
    }

    public function warning($texto, $bold = false) {
        return $this->color($texto, self::COLOR_BRIGHT_YELLOW, $bold);
    }

    public function info($texto, $bold = false) {
        return $this->color($texto, self::COLOR_BRIGHT_CYAN, $bold);
    }

    public function primary($texto, $bold = false) {
        return $this->color($texto, self::COLOR_BRIGHT_BLUE, $bold);
    }

    public function dim($texto) {
        if (!$this->coloresHabilitados) {
            return $texto;
        }
        return self::COLOR_DIM . $texto . self::COLOR_RESET;
    }

    /**
     * Título grande con borde
     */
    public function titulo($texto, $char = '=') {
        $width = 80;
        $padding = floor(($width - strlen($texto) - 2) / 2);

        echo "\n";
        echo $this->primary(str_repeat($char, $width), true) . "\n";
        echo $this->primary(str_repeat(' ', $padding) . $texto . str_repeat(' ', $padding), true) . "\n";
        echo $this->primary(str_repeat($char, $width), true) . "\n";
        echo "\n";
    }

    /**
     * Subtítulo con línea
     */
    public function subtitulo($texto, $char = '-') {
        echo "\n";
        echo $this->info($texto, true) . "\n";
        echo $this->dim(str_repeat($char, min(strlen($texto), 70))) . "\n";
        echo "\n";
    }

    /**
     * Sección con recuadro
     */
    public function seccion($titulo, $contenido = null) {
        echo "\n";
        echo $this->primary("╔═══ " . $titulo . " ", true) . $this->dim(str_repeat("═", 70 - strlen($titulo))) . "\n";

        if ($contenido) {
            if (is_array($contenido)) {
                foreach ($contenido as $linea) {
                    echo $this->dim("║ ") . $linea . "\n";
                }
            } else {
                echo $this->dim("║ ") . $contenido . "\n";
            }
            echo $this->dim("╚" . str_repeat("═", 78)) . "\n";
        }
    }

    /**
     * Mensaje con icono
     */
    public function mensaje($tipo, $texto) {
        $iconos = [
            'success' => ['✓', 'SUCCESS'],
            'error' => ['✗', 'ERROR'],
            'warning' => ['⚠', 'WARNING'],
            'info' => ['ℹ', 'INFO'],
            'question' => ['?', 'QUESTION'],
            'bolt' => ['⚡', 'ACTION'],
            'rocket' => ['🚀', 'LAUNCH'],
            'check' => ['✅', 'OK'],
            'cross' => ['❌', 'FAIL'],
        ];

        $icono = $this->emojisHabilitados ? $iconos[$tipo][0] : '[' . $iconos[$tipo][1] . ']';

        switch ($tipo) {
            case 'success':
                echo $this->success("  $icono  $texto") . "\n";
                break;
            case 'error':
                echo $this->error("  $icono  $texto") . "\n";
                break;
            case 'warning':
                echo $this->warning("  $icono  $texto") . "\n";
                break;
            case 'info':
                echo $this->info("  $icono  $texto") . "\n";
                break;
            default:
                echo "  $icono  $texto\n";
        }
    }

    /**
     * Lista con viñetas
     */
    public function lista($items, $icono = '•') {
        foreach ($items as $item) {
            if (is_array($item)) {
                echo $this->primary("  $icono ", true) . $item['texto'];
                if (isset($item['valor'])) {
                    echo $this->dim(" → ") . $this->info($item['valor']);
                }
                echo "\n";
            } else {
                echo $this->primary("  $icono ", true) . $item . "\n";
            }
        }
    }

    /**
     * Tabla simple
     */
    public function tabla($headers, $rows, $width = 70) {
        $numCols = count($headers);
        $colWidth = floor($width / $numCols);

        // Header
        echo "\n";
        echo $this->primary(str_repeat("═", $width), true) . "\n";
        echo $this->primary("║ ", true);
        foreach ($headers as $header) {
            echo $this->primary(str_pad($header, $colWidth - 1), true);
        }
        echo "\n";
        echo $this->primary(str_repeat("═", $width), true) . "\n";

        // Rows
        foreach ($rows as $row) {
            echo $this->dim("║ ");
            foreach ($row as $cell) {
                echo str_pad(substr($cell, 0, $colWidth - 2), $colWidth - 1);
            }
            echo "\n";
        }

        echo $this->dim(str_repeat("═", $width)) . "\n\n";
    }

    /**
     * Barra de progreso
     */
    public function barraProgreso($actual, $total, $width = 50, $label = '') {
        $porcentaje = $total > 0 ? ($actual / $total) * 100 : 0;
        $completado = floor(($actual / $total) * $width);
        $restante = $width - $completado;

        $barra = $this->success(str_repeat('█', $completado)) .
                 $this->dim(str_repeat('░', $restante));

        $info = sprintf("%d/%d", $actual, $total);
        $pct = sprintf("%5.1f%%", $porcentaje);

        echo "\r"; // Volver al inicio de la línea
        echo $label ? $this->info($label . ": ", true) : "";
        echo "[$barra] ";
        echo $this->primary($pct, true) . " ";
        echo $this->dim("($info)");

        if ($actual >= $total) {
            echo "\n"; // Nueva línea al completar
        }

        flush();
    }

    /**
     * Spinner animado
     */
    public function spinner($mensaje, $callback) {
        $frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        $i = 0;

        // Ejecutar en background y mostrar spinner
        $start = time();

        echo "\n";
        while (true) {
            $frame = $frames[$i % count($frames)];
            echo "\r" . $this->info($frame . " " . $mensaje . " ", true) .
                 $this->dim("(" . (time() - $start) . "s)");

            $i++;

            // Verificar si el callback terminó
            if (is_callable($callback)) {
                $result = $callback();
                if ($result !== null) {
                    echo "\r" . $this->success("✓ " . $mensaje, true) .
                         $this->dim(" (" . (time() - $start) . "s)") . "\n";
                    return $result;
                }
            }

            usleep(100000); // 100ms
        }
    }

    /**
     * Resumen con estadísticas
     */
    public function resumen($titulo, $stats) {
        $this->seccion($titulo);

        foreach ($stats as $key => $value) {
            if (is_array($value)) {
                // Estadística con icono y color
                $icono = $value['icono'] ?? '•';
                $texto = $value['texto'] ?? $key;
                $val = $value['valor'] ?? '';
                $tipo = $value['tipo'] ?? 'info';

                echo "  " . $icono . " " . $texto . ": ";

                switch ($tipo) {
                    case 'success':
                        echo $this->success($val, true);
                        break;
                    case 'error':
                        echo $this->error($val, true);
                        break;
                    case 'warning':
                        echo $this->warning($val, true);
                        break;
                    default:
                        echo $this->info($val, true);
                }

                echo "\n";
            } else {
                // Estadística simple
                echo "  • " . $key . ": " . $this->info($value, true) . "\n";
            }
        }

        echo "\n";
    }

    /**
     * Caja con mensaje destacado
     */
    public function caja($mensaje, $tipo = 'info', $width = 70) {
        $lineas = explode("\n", wordwrap($mensaje, $width - 6));

        echo "\n";
        echo $this->dim("╔" . str_repeat("═", $width - 2) . "╗") . "\n";

        foreach ($lineas as $linea) {
            $padding = $width - strlen($linea) - 4;
            echo $this->dim("║ ");

            switch ($tipo) {
                case 'success':
                    echo $this->success($linea);
                    break;
                case 'error':
                    echo $this->error($linea);
                    break;
                case 'warning':
                    echo $this->warning($linea);
                    break;
                default:
                    echo $this->info($linea);
            }

            echo str_repeat(" ", max(0, $padding)) . $this->dim(" ║") . "\n";
        }

        echo $this->dim("╚" . str_repeat("═", $width - 2) . "╝") . "\n\n";
    }

    /**
     * Separador visual
     */
    public function separador($char = '─', $width = 80) {
        echo $this->dim(str_repeat($char, $width)) . "\n";
    }

    /**
     * Confirmar acción (sí/no)
     */
    public function confirmar($mensaje, $default = true) {
        $opciones = $default ? '[S/n]' : '[s/N]';
        echo $this->warning("⚠  $mensaje $opciones: ");

        $respuesta = strtolower(trim(fgets(STDIN)));

        if (empty($respuesta)) {
            return $default;
        }

        return in_array($respuesta, ['s', 'si', 'sí', 'y', 'yes']);
    }

    /**
     * Solicitar input del usuario
     */
    public function input($mensaje, $default = '') {
        if ($default) {
            echo $this->info("$mensaje ", true) . $this->dim("[$default]: ");
        } else {
            echo $this->info("$mensaje: ", true);
        }

        $respuesta = trim(fgets(STDIN));

        return empty($respuesta) ? $default : $respuesta;
    }

    /**
     * Limpiar pantalla
     */
    public function limpiar() {
        if (DIRECTORY_SEPARATOR === '\\') {
            system('cls');
        } else {
            system('clear');
        }
    }

    /**
     * Pausar ejecución
     */
    public function pausar($mensaje = 'Presiona Enter para continuar...') {
        echo "\n" . $this->dim($mensaje);
        fgets(STDIN);
    }

    /**
     * Animación de carga
     */
    public function cargando($mensaje, $segundos = 3) {
        $frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        $iteraciones = $segundos * 10;

        for ($i = 0; $i < $iteraciones; $i++) {
            $frame = $frames[$i % count($frames)];
            echo "\r" . $this->info($frame . " " . $mensaje, true);
            usleep(100000); // 100ms
        }

        echo "\r" . $this->success("✓ " . $mensaje, true) . "\n";
    }

    /**
     * Deshabilitar/habilitar colores
     */
    public function setColoresHabilitados($habilitado) {
        $this->coloresHabilitados = $habilitado;
    }

    /**
     * Deshabilitar/habilitar emojis
     */
    public function setEmojisHabilitados($habilitado) {
        $this->emojisHabilitados = $habilitado;
    }
}

// Alias global para fácil acceso
function visual() {
    return VisualHelper::getInstance();
}
