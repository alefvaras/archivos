<?php
/**
 * VERIFICACIÓN RÁPIDA DEL AMBIENTE DE CERTIFICACIÓN
 *
 * Script para verificar que el ambiente esté correctamente configurado
 * antes de ejecutar pruebas con datos reales.
 *
 * Uso:
 *   php verificar-ambiente.php
 *   php verificar-ambiente.php --verbose
 *
 * @package SimpleDTE
 * @version 1.0.0
 */

// Cargar configuración
if (file_exists(__DIR__ . '/config/settings.php')) {
    require_once __DIR__ . '/config/settings.php';
} else {
    die("ERROR: No se encuentra config/settings.php\n");
}

class VerificadorAmbiente {

    private $config;
    private $verbose = false;
    private $errores = [];
    private $advertencias = [];
    private $checks_ok = 0;
    private $checks_total = 0;

    public function __construct($verbose = false) {
        $this->verbose = $verbose;
        $this->config = ConfiguracionSistema::getInstance();
    }

    public function ejecutar() {
        $this->mostrarEncabezado();

        // Ejecutar todas las verificaciones
        $this->checkAmbiente();
        $this->checkEmisor();
        $this->checkSimpleAPI();
        $this->checkCertificado();
        $this->checkCAF();
        $this->checkDatabase();
        $this->checkDirectorios();
        $this->checkPHP();

        // Mostrar resumen
        $this->mostrarResumen();

        // Retornar éxito si no hay errores críticos
        return empty($this->errores);
    }

    private function checkAmbiente() {
        $this->titulo("1. CONFIGURACIÓN DE AMBIENTE");

        $ambiente = $this->config->get('general.ambiente');
        $this->check(
            "Ambiente configurado",
            !empty($ambiente),
            "Ambiente: $ambiente"
        );

        if ($ambiente === 'certificacion') {
            $this->success("✓ Ambiente CERTIFICACIÓN (seguro para pruebas)");
        } elseif ($ambiente === 'produccion') {
            $this->warning("⚠ Ambiente PRODUCCIÓN (usar con precaución)");
        } else {
            $this->error("✗ Ambiente desconocido: $ambiente");
        }

        $debug = $this->config->get('general.debug');
        $this->check("Debug habilitado", true, "Debug: " . ($debug ? 'SÍ' : 'NO'));

        $timezone = $this->config->get('general.timezone');
        $this->check("Timezone", !empty($timezone), "Timezone: $timezone");
    }

    private function checkEmisor() {
        $this->titulo("2. DATOS DEL EMISOR");

        $rut = $this->config->get('emisor.rut');
        $this->check("RUT Emisor", !empty($rut), "RUT: $rut");

        if (!empty($rut)) {
            $valido = $this->validarRUT($rut);
            $this->check("RUT Emisor válido", $valido, $valido ? "✓ RUT válido" : "✗ RUT inválido");
        }

        $razon_social = $this->config->get('emisor.razon_social');
        $this->check("Razón Social", !empty($razon_social), "Razón Social: $razon_social");

        $giro = $this->config->get('emisor.giro');
        $this->check("Giro", !empty($giro), "Giro: $giro");

        $direccion = $this->config->get('emisor.direccion');
        $this->check("Dirección", !empty($direccion), "Dirección: $direccion");

        $comuna = $this->config->get('emisor.comuna');
        $this->check("Comuna", !empty($comuna), "Comuna: $comuna");

        $email = $this->config->get('emisor.email');
        $this->check("Email", !empty($email), "Email: $email");
    }

    private function checkSimpleAPI() {
        $this->titulo("3. SIMPLEAPI");

        $api_url = $this->config->get('api.base_url');
        $this->check("URL API", !empty($api_url), "URL: $api_url");

        $api_key = $this->config->get('api.api_key');
        $api_key_presente = !empty($api_key) && strlen($api_key) > 20;
        $this->check(
            "API Key",
            $api_key_presente,
            $api_key_presente ? "API Key: Configurado (***...)" : "API Key: NO configurado"
        );

        $timeout = $this->config->get('api.timeout');
        $this->check("Timeout", $timeout > 0, "Timeout: {$timeout}s");

        // Probar conectividad
        if ($api_key_presente) {
            $this->info("Probando conectividad con SimpleAPI...");

            $ch = curl_init($api_url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_NOBODY => true,
            ]);

            $result = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($http_code > 0) {
                $this->success("✓ SimpleAPI accesible (HTTP $http_code)");
            } else {
                $this->error("✗ SimpleAPI NO accesible: $error");
            }
        }
    }

    private function checkCertificado() {
        $this->titulo("4. CERTIFICADO DIGITAL");

        $cert_path = $this->config->get('certificado.path');
        $this->check("Ruta certificado", !empty($cert_path), "Path: $cert_path");

        if (!empty($cert_path)) {
            $existe = file_exists($cert_path);
            $this->check("Certificado existe", $existe, $existe ? "✓ Archivo existe" : "✗ Archivo NO existe");

            if ($existe) {
                $legible = is_readable($cert_path);
                $this->check("Certificado legible", $legible, $legible ? "✓ Archivo legible" : "✗ Sin permisos de lectura");

                // Intentar leer el certificado
                $cert_password = $this->config->get('certificado.password');

                if ($legible && !empty($cert_password)) {
                    $contenido = file_get_contents($cert_path);
                    $certs = [];

                    if (openssl_pkcs12_read($contenido, $certs, $cert_password)) {
                        $this->success("✓ Certificado se puede leer correctamente");

                        // Verificar expiración
                        $cert_data = openssl_x509_parse($certs['cert']);
                        $expiration = $cert_data['validTo_time_t'];
                        $dias_restantes = floor(($expiration - time()) / 86400);

                        if ($dias_restantes < 0) {
                            $this->error("✗ Certificado EXPIRADO hace " . abs($dias_restantes) . " días");
                        } elseif ($dias_restantes < 30) {
                            $this->warning("⚠ Certificado expira en $dias_restantes días");
                        } else {
                            $this->success("✓ Certificado válido por $dias_restantes días");
                        }

                        // Mostrar información del certificado si es verbose
                        if ($this->verbose) {
                            $this->debug("  Subject: " . $cert_data['name']);
                            $this->debug("  Emisor: " . $cert_data['issuer']['CN']);
                            $this->debug("  Válido desde: " . date('Y-m-d', $cert_data['validFrom_time_t']));
                            $this->debug("  Válido hasta: " . date('Y-m-d', $cert_data['validTo_time_t']));
                        }
                    } else {
                        $this->error("✗ No se puede leer certificado (contraseña incorrecta?)");
                    }
                }
            }
        }
    }

    private function checkCAF() {
        $this->titulo("5. CAF (FOLIOS)");

        $caf_path = $this->config->get('caf.path');
        $this->check("Ruta CAF", !empty($caf_path), "Path: $caf_path");

        if (!empty($caf_path)) {
            $existe = file_exists($caf_path);
            $this->check("CAF existe", $existe, $existe ? "✓ Archivo existe" : "✗ Archivo NO existe");

            if ($existe) {
                // Intentar cargar como XML
                libxml_use_internal_errors(true);
                $xml = simplexml_load_file($caf_path);

                if ($xml) {
                    $this->success("✓ CAF es un XML válido");

                    // Extraer información
                    $tipo_dte = (string) $xml->CAF->DA->TD;
                    $rut_emisor = (string) $xml->CAF->DA->RE;
                    $desde = (int) $xml->CAF->DA->RNG->D;
                    $hasta = (int) $xml->CAF->DA->RNG->H;
                    $fecha_emision = (string) $xml->CAF->DA->FA;

                    $total_folios = $hasta - $desde + 1;

                    $this->info("  Tipo DTE: $tipo_dte");
                    $this->info("  RUT Emisor CAF: $rut_emisor");
                    $this->info("  Rango: $desde a $hasta ($total_folios folios)");
                    $this->info("  Fecha emisión: $fecha_emision");

                    // Verificar que el RUT del CAF coincida con el emisor
                    $rut_config = $this->config->get('emisor.rut');
                    if ($rut_emisor !== $rut_config) {
                        $this->warning("⚠ RUT del CAF ($rut_emisor) no coincide con RUT emisor ($rut_config)");
                    } else {
                        $this->success("✓ RUT del CAF coincide con emisor");
                    }

                    // Advertir si quedan pocos folios
                    if ($total_folios < 10) {
                        $this->warning("⚠ Quedan pocos folios ($total_folios). Solicitar más en www.sii.cl");
                    } else {
                        $this->success("✓ Folios disponibles: $total_folios");
                    }
                } else {
                    $this->error("✗ CAF no es un XML válido");
                    if ($this->verbose) {
                        $errors = libxml_get_errors();
                        foreach ($errors as $error) {
                            $this->debug("  Error XML: " . trim($error->message));
                        }
                    }
                    libxml_clear_errors();
                }
            }
        }
    }

    private function checkDatabase() {
        $this->titulo("6. BASE DE DATOS");

        $db_habilitado = $this->config->get('database.habilitado');

        if ($db_habilitado) {
            $db_host = $this->config->get('database.host');
            $db_name = $this->config->get('database.name');
            $db_user = $this->config->get('database.user');

            $this->check("DB Host", !empty($db_host), "Host: $db_host");
            $this->check("DB Name", !empty($db_name), "Database: $db_name");
            $this->check("DB User", !empty($db_user), "User: $db_user");

            // Intentar conectar
            try {
                $dsn = sprintf(
                    "mysql:host=%s;dbname=%s;charset=%s",
                    $db_host,
                    $db_name,
                    $this->config->get('database.charset')
                );

                $pdo = new PDO(
                    $dsn,
                    $db_user,
                    $this->config->get('database.password'),
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );

                $this->success("✓ Conexión a base de datos exitosa");

                // Verificar tablas si es verbose
                if ($this->verbose) {
                    $stmt = $pdo->query("SHOW TABLES");
                    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    $this->debug("  Tablas: " . implode(', ', $tables));
                }

            } catch (PDOException $e) {
                $this->error("✗ Error al conectar a base de datos: " . $e->getMessage());
            }
        } else {
            $this->info("Base de datos NO habilitada (opcional)");
        }
    }

    private function checkDirectorios() {
        $this->titulo("7. DIRECTORIOS");

        $directorios = [
            'Logs' => __DIR__ . '/logs',
            'Reportes' => __DIR__ . '/reportes',
            'XMLs' => __DIR__ . '/xmls',
            'PDFs' => __DIR__ . '/pdfs',
            'Temp' => __DIR__ . '/temp',
        ];

        foreach ($directorios as $nombre => $ruta) {
            $existe = is_dir($ruta);
            $escribible = $existe && is_writable($ruta);

            if (!$existe) {
                $this->warning("⚠ Directorio $nombre no existe: $ruta");
                $this->info("  Intentando crear...");

                if (mkdir($ruta, 0755, true)) {
                    $this->success("  ✓ Directorio creado");
                    $escribible = true;
                } else {
                    $this->error("  ✗ No se pudo crear directorio");
                }
            }

            if ($escribible) {
                $this->success("✓ $nombre: Escribible");
            } else {
                $this->error("✗ $nombre: Sin permisos de escritura");
            }
        }
    }

    private function checkPHP() {
        $this->titulo("8. ENTORNO PHP");

        $version = PHP_VERSION;
        $version_ok = version_compare($version, '7.4', '>=');
        $this->check("Versión PHP", $version_ok, "PHP $version");

        if (!$version_ok) {
            $this->warning("⚠ Se recomienda PHP 7.4 o superior");
        }

        // Extensiones requeridas
        $extensiones = [
            'curl' => 'Requerida para comunicación con SimpleAPI',
            'openssl' => 'Requerida para certificados digitales',
            'simplexml' => 'Requerida para procesar XML de DTEs',
            'mbstring' => 'Requerida para manejo de caracteres',
            'json' => 'Requerida para respuestas de API',
            'pdo_mysql' => 'Opcional para base de datos',
        ];

        foreach ($extensiones as $ext => $descripcion) {
            $cargada = extension_loaded($ext);
            $opcional = ($ext === 'pdo_mysql');

            if ($cargada) {
                $this->success("✓ Extensión $ext: Disponible");
            } elseif ($opcional) {
                $this->warning("⚠ Extensión $ext: NO disponible (opcional)");
            } else {
                $this->error("✗ Extensión $ext: NO disponible (requerida)");
            }

            if ($this->verbose) {
                $this->debug("  $descripcion");
            }
        }

        // Límites PHP
        if ($this->verbose) {
            $this->debug("memory_limit: " . ini_get('memory_limit'));
            $this->debug("max_execution_time: " . ini_get('max_execution_time'));
            $this->debug("upload_max_filesize: " . ini_get('upload_max_filesize'));
        }
    }

    // =================================================================
    // MÉTODOS AUXILIARES
    // =================================================================

    private function validarRUT($rut) {
        // Limpiar RUT
        $rut = strtoupper(str_replace(['.', '-', ' '], '', $rut));

        if (strlen($rut) < 2) {
            return false;
        }

        $dv = substr($rut, -1);
        $numero = substr($rut, 0, -1);

        if (!is_numeric($numero)) {
            return false;
        }

        // Calcular DV
        $suma = 0;
        $multiplo = 2;

        for ($i = strlen($numero) - 1; $i >= 0; $i--) {
            $suma += $numero[$i] * $multiplo;
            $multiplo = ($multiplo < 7) ? $multiplo + 1 : 2;
        }

        $dvEsperado = 11 - ($suma % 11);
        $dvEsperado = ($dvEsperado == 11) ? '0' : (($dvEsperado == 10) ? 'K' : (string)$dvEsperado);

        return $dv === $dvEsperado;
    }

    private function check($nombre, $exitoso, $mensaje = '') {
        $this->checks_total++;

        if ($exitoso) {
            $this->checks_ok++;
            if ($this->verbose && $mensaje) {
                $this->info("  $mensaje");
            }
        } else {
            if ($mensaje) {
                $this->error("  $mensaje");
            }
        }
    }

    // =================================================================
    // MÉTODOS DE PRESENTACIÓN
    // =================================================================

    private function mostrarEncabezado() {
        echo "\n";
        echo str_repeat("=", 70) . "\n";
        echo "  VERIFICACIÓN DE AMBIENTE - CERTIFICACIÓN SII\n";
        echo str_repeat("=", 70) . "\n";
        echo "Fecha: " . date('Y-m-d H:i:s') . "\n";
        echo str_repeat("=", 70) . "\n\n";
    }

    private function mostrarResumen() {
        echo "\n";
        echo str_repeat("=", 70) . "\n";
        echo "RESUMEN DE VERIFICACIÓN\n";
        echo str_repeat("=", 70) . "\n";

        $porcentaje = $this->checks_total > 0
            ? round(($this->checks_ok / $this->checks_total) * 100, 1)
            : 0;

        echo "Checks completados: {$this->checks_ok}/{$this->checks_total} ($porcentaje%)\n";
        echo "Errores críticos: " . count($this->errores) . "\n";
        echo "Advertencias: " . count($this->advertencias) . "\n";

        if (empty($this->errores)) {
            echo "\n";
            $this->success("¡AMBIENTE CORRECTAMENTE CONFIGURADO!");
            $this->success("Puede ejecutar: php prueba-ambiente-certificacion.php");
        } else {
            echo "\n";
            $this->error("HAY ERRORES QUE DEBEN CORREGIRSE:");
            foreach ($this->errores as $error) {
                $this->error("  - $error");
            }
        }

        if (!empty($this->advertencias)) {
            echo "\n";
            $this->warning("ADVERTENCIAS:");
            foreach ($this->advertencias as $adv) {
                $this->warning("  - $adv");
            }
        }

        echo str_repeat("=", 70) . "\n\n";
    }

    private function titulo($texto) {
        echo "\n" . $texto . "\n";
        echo str_repeat("-", strlen($texto)) . "\n";
    }

    private function info($msg) {
        echo "[INFO] $msg\n";
    }

    private function success($msg) {
        echo "\033[32m[OK]\033[0m $msg\n";
    }

    private function error($msg) {
        echo "\033[31m[ERROR]\033[0m $msg\n";
        $this->errores[] = $msg;
    }

    private function warning($msg) {
        echo "\033[33m[WARN]\033[0m $msg\n";
        $this->advertencias[] = $msg;
    }

    private function debug($msg) {
        if ($this->verbose) {
            echo "\033[36m[DEBUG]\033[0m $msg\n";
        }
    }
}

// =================================================================
// EJECUCIÓN
// =================================================================

$verbose = in_array('--verbose', $argv) || in_array('-v', $argv);

$verificador = new VerificadorAmbiente($verbose);
$exitoso = $verificador->ejecutar();

exit($exitoso ? 0 : 1);
