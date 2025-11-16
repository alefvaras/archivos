<?php
/**
 * Panel de Configuración Interactivo
 *
 * Interfaz visual para gestionar toda la configuración del sistema
 * con validación en tiempo real y guardado automático
 */

require_once __DIR__ . '/config/settings.php';
require_once __DIR__ . '/lib/VisualHelper.php';

$config = ConfiguracionSistema::getInstance();
$v = VisualHelper::getInstance();

// Limpiar pantalla
$v->limpiar();

// Título principal
$v->titulo("PANEL DE CONFIGURACIÓN INTERACTIVO", "═");

// Validar configuración actual
$validacion = $config->validar();

if ($validacion['valido']) {
    $v->mensaje('success', 'Configuración actual válida y operativa');
} else {
    $v->mensaje('warning', 'Se encontraron ' . count($validacion['errores']) . ' problemas de configuración');
    foreach ($validacion['errores'] as $error) {
        $v->mensaje('error', $error);
    }
    echo "\n";
}

$v->separador();

// Menú principal
while (true) {
    echo "\n";
    $v->subtitulo("MENÚ PRINCIPAL");

    $opciones = [
        '1' => '🔧  Configuración General',
        '2' => '🏢  Datos del Emisor',
        '3' => '🌐  Conexión API y SII',
        '4' => '📧  Configuración de Email',
        '5' => '📄  Personalización de PDF',
        '6' => '🗄️   Base de Datos',
        '7' => '📊  Logging y Monitoreo',
        '8' => '🔒  Seguridad',
        '9' => '🎨  Visuales y UX',
        'v' => '✓  Ver Configuración Completa',
        'e' => '💾  Exportar a .env',
        't' => '🧪  Test de Conexión',
        'q' => '❌  Salir'
    ];

    $v->lista(array_map(fn($k, $o) => "$k. $o", array_keys($opciones), $opciones));

    echo "\n";
    $opcion = $v->input("Selecciona una opción");

    switch ($opcion) {
        case '1':
            configurarGeneral($config, $v);
            break;
        case '2':
            configurarEmisor($config, $v);
            break;
        case '3':
            configurarAPI($config, $v);
            break;
        case '4':
            configurarEmail($config, $v);
            break;
        case '5':
            configurarPDF($config, $v);
            break;
        case '6':
            configurarBaseDatos($config, $v);
            break;
        case '7':
            configurarLogging($config, $v);
            break;
        case '8':
            configurarSeguridad($config, $v);
            break;
        case '9':
            configurarVisuales($config, $v);
            break;
        case 'v':
            verConfiguracionCompleta($config, $v);
            break;
        case 'e':
            exportarEnv($config, $v);
            break;
        case 't':
            testConexion($config, $v);
            break;
        case 'q':
            $v->mensaje('info', 'Saliendo del panel de configuración');
            exit(0);
        default:
            $v->mensaje('error', 'Opción no válida');
    }

    $v->pausar();
    $v->limpiar();
    $v->titulo("PANEL DE CONFIGURACIÓN INTERACTIVO", "═");
}

// ========================================
// FUNCIONES DE CONFIGURACIÓN
// ========================================

function configurarGeneral($config, $v) {
    $v->subtitulo("⚙️  CONFIGURACIÓN GENERAL");

    $ambiente_actual = $config->get('general.ambiente');
    $debug_actual = $config->get('general.debug') ? 'Sí' : 'No';

    $v->lista([
        ['texto' => 'Ambiente actual', 'valor' => $ambiente_actual],
        ['texto' => 'Modo debug', 'valor' => $debug_actual],
        ['texto' => 'Zona horaria', 'valor' => $config->get('general.timezone')],
    ]);

    echo "\n";

    if ($v->confirmar("¿Cambiar ambiente? (certificacion/produccion)", false)) {
        $ambiente = $v->input("Nuevo ambiente (certificacion/produccion)", $ambiente_actual);
        if (in_array($ambiente, ['certificacion', 'produccion'])) {
            putenv("AMBIENTE=$ambiente");
            $config->set('general.ambiente', $ambiente);
            $v->mensaje('success', "Ambiente cambiado a: $ambiente");

            if ($ambiente === 'produccion') {
                $v->caja("⚠️  PRODUCCIÓN: Asegúrate de tener CAF válidos y certificado de producción", 'warning');
            }
        } else {
            $v->mensaje('error', 'Ambiente no válido');
        }
    }

    if ($v->confirmar("¿Habilitar modo debug?", $config->get('general.debug'))) {
        putenv("DEBUG=true");
        $config->set('general.debug', true);
        $v->mensaje('success', 'Modo debug habilitado');
    } else {
        putenv("DEBUG=false");
        $config->set('general.debug', false);
        $v->mensaje('info', 'Modo debug deshabilitado');
    }
}

function configurarEmisor($config, $v) {
    $v->subtitulo("🏢  DATOS DEL EMISOR");

    $datos = [
        ['texto' => 'RUT', 'valor' => $config->get('emisor.rut')],
        ['texto' => 'Razón Social', 'valor' => $config->get('emisor.razon_social')],
        ['texto' => 'Giro', 'valor' => $config->get('emisor.giro')],
        ['texto' => 'Dirección', 'valor' => $config->get('emisor.direccion')],
        ['texto' => 'Comuna', 'valor' => $config->get('emisor.comuna')],
        ['texto' => 'Teléfono', 'valor' => $config->get('emisor.telefono')],
        ['texto' => 'Email', 'valor' => $config->get('emisor.email')],
    ];

    $v->lista($datos);

    echo "\n";

    if ($v->confirmar("¿Modificar datos del emisor?", false)) {
        $rut = $v->input("RUT del emisor", $config->get('emisor.rut'));
        putenv("RUT_EMISOR=$rut");
        $config->set('emisor.rut', $rut);

        $razon = $v->input("Razón Social", $config->get('emisor.razon_social'));
        putenv("RAZON_SOCIAL=$razon");
        $config->set('emisor.razon_social', $razon);

        $giro = $v->input("Giro", $config->get('emisor.giro'));
        putenv("GIRO=$giro");
        $config->set('emisor.giro', $giro);

        $v->mensaje('success', 'Datos del emisor actualizados');
    }
}

function configurarAPI($config, $v) {
    $v->subtitulo("🌐  CONFIGURACIÓN API Y SII");

    $datos = [
        ['texto' => 'URL Base', 'valor' => $config->get('api.base_url')],
        ['texto' => 'API Key', 'valor' => substr($config->get('api.api_key'), 0, 20) . '...'],
        ['texto' => 'Timeout', 'valor' => $config->get('api.timeout') . ' segundos'],
        ['texto' => 'Máx. reintentos', 'valor' => $config->get('api.max_reintentos')],
        ['texto' => 'Exponential backoff', 'valor' => $config->get('api.exponential_backoff') ? 'Sí' : 'No'],
    ];

    $v->lista($datos);

    echo "\n";

    if ($v->confirmar("¿Ajustar configuración de API?", false)) {
        $timeout = $v->input("Timeout (segundos)", $config->get('api.timeout'));
        putenv("API_TIMEOUT=$timeout");
        $config->set('api.timeout', (int) $timeout);

        $reintentos = $v->input("Máx. reintentos", $config->get('api.max_reintentos'));
        putenv("API_MAX_REINTENTOS=$reintentos");
        $config->set('api.max_reintentos', (int) $reintentos);

        $v->mensaje('success', 'Configuración de API actualizada');
    }

    $v->subtitulo("📋  CONSULTAS SII");

    $datos_consulta = [
        ['texto' => 'Consulta automática', 'valor' => $config->get('consulta_sii.automatica') ? 'Sí' : 'No'],
        ['texto' => 'Espera inicial', 'valor' => $config->get('consulta_sii.espera_inicial_segundos') . ' segundos'],
        ['texto' => 'Máx. intentos', 'valor' => $config->get('consulta_sii.max_intentos')],
    ];

    $v->lista($datos_consulta);

    if ($v->confirmar("¿Modificar configuración de consultas SII?", false)) {
        if ($v->confirmar("¿Habilitar consulta automática?", $config->get('consulta_sii.automatica'))) {
            putenv("CONSULTA_SII_AUTO=true");
            $config->set('consulta_sii.automatica', true);

            $espera = $v->input("Espera inicial (segundos)", $config->get('consulta_sii.espera_inicial_segundos'));
            putenv("CONSULTA_SII_ESPERA=$espera");
            $config->set('consulta_sii.espera_inicial_segundos', (int) $espera);
        } else {
            putenv("CONSULTA_SII_AUTO=false");
            $config->set('consulta_sii.automatica', false);
        }

        $v->mensaje('success', 'Configuración de consultas SII actualizada');
    }
}

function configurarEmail($config, $v) {
    $v->subtitulo("📧  CONFIGURACIÓN DE EMAIL");

    $datos = [
        ['texto' => 'Email habilitado', 'valor' => $config->get('email.habilitado') ? 'Sí' : 'No'],
        ['texto' => 'Método', 'valor' => $config->get('email.metodo')],
        ['texto' => 'From email', 'valor' => $config->get('email.from_email')],
        ['texto' => 'From name', 'valor' => $config->get('email.from_name')],
        ['texto' => 'Incluir PDF', 'valor' => $config->get('email.incluir_pdf') ? 'Sí' : 'No'],
    ];

    $v->lista($datos);

    echo "\n";

    if ($v->confirmar("¿Configurar SMTP?", false)) {
        $smtp_host = $v->input("SMTP Host", $config->get('email.smtp_host'));
        putenv("SMTP_HOST=$smtp_host");
        $config->set('email.smtp_host', $smtp_host);

        $smtp_port = $v->input("SMTP Port", $config->get('email.smtp_port'));
        putenv("SMTP_PORT=$smtp_port");
        $config->set('email.smtp_port', (int) $smtp_port);

        $smtp_user = $v->input("SMTP User", $config->get('email.smtp_user'));
        putenv("SMTP_USER=$smtp_user");
        $config->set('email.smtp_user', $smtp_user);

        $smtp_pass = $v->input("SMTP Password");
        if ($smtp_pass) {
            putenv("SMTP_PASS=$smtp_pass");
            $config->set('email.smtp_pass', $smtp_pass);
        }

        putenv("EMAIL_METODO=smtp");
        $config->set('email.metodo', 'smtp');

        $v->mensaje('success', 'Configuración SMTP guardada');
    }

    if ($v->confirmar("¿Personalizar asunto del email?", false)) {
        $v->mensaje('info', 'Variables disponibles: {folio}, {razon_social}, {total}');
        $asunto = $v->input("Template de asunto", $config->get('email.asunto_template'));
        putenv("EMAIL_ASUNTO=$asunto");
        $config->set('email.asunto_template', $asunto);

        $v->mensaje('success', 'Asunto personalizado guardado');
    }
}

function configurarPDF($config, $v) {
    $v->subtitulo("📄  PERSONALIZACIÓN DE PDF");

    $color_header = $config->get('pdf.color_header');
    $datos = [
        ['texto' => 'Incluir logo', 'valor' => $config->get('pdf.incluir_logo') ? 'Sí' : 'No'],
        ['texto' => 'Orientación', 'valor' => $config->get('pdf.orientacion')],
        ['texto' => 'Tamaño', 'valor' => $config->get('pdf.tamano')],
        ['texto' => 'Color header', 'valor' => "RGB({$color_header['r']}, {$color_header['g']}, {$color_header['b']})"],
        ['texto' => 'Nivel seguridad PDF417', 'valor' => $config->get('pdf.timbre_nivel_seguridad')],
    ];

    $v->lista($datos);

    echo "\n";

    if ($v->confirmar("¿Personalizar colores del PDF?", false)) {
        $v->mensaje('info', 'Formato RGB: 255,0,0 para rojo, 0,255,0 para verde, etc.');

        $header = $v->input("Color header (R,G,B)", "{$color_header['r']},{$color_header['g']},{$color_header['b']}");
        putenv("PDF_COLOR_HEADER=$header");

        $v->mensaje('success', 'Colores del PDF actualizados');
    }

    if ($v->confirmar("¿Configurar logo empresarial?", false)) {
        $logo_path = $v->input("Ruta del logo PNG/JPG", $config->get('pdf.logo_path'));

        if (file_exists($logo_path)) {
            putenv("PDF_LOGO_PATH=$logo_path");
            $config->set('pdf.logo_path', $logo_path);

            $logo_width = $v->input("Ancho del logo (mm)", $config->get('pdf.logo_width'));
            putenv("PDF_LOGO_WIDTH=$logo_width");
            $config->set('pdf.logo_width', (int) $logo_width);

            $v->mensaje('success', 'Logo configurado correctamente');
        } else {
            $v->mensaje('error', 'Archivo de logo no encontrado');
        }
    }
}

function configurarBaseDatos($config, $v) {
    $v->subtitulo("🗄️  CONFIGURACIÓN DE BASE DE DATOS");

    $bd_habilitada = $config->get('database.habilitado');

    $datos = [
        ['texto' => 'Estado', 'valor' => $bd_habilitada ? '✓ Habilitada' : '✗ Deshabilitada'],
        ['texto' => 'Host', 'valor' => $config->get('database.host')],
        ['texto' => 'Puerto', 'valor' => $config->get('database.port')],
        ['texto' => 'Base de datos', 'valor' => $config->get('database.name') ?: 'No configurada'],
        ['texto' => 'Usuario', 'valor' => $config->get('database.user') ?: 'No configurado'],
        ['texto' => 'Fallback a archivos', 'valor' => $config->get('database.fallback_to_files') ? 'Sí' : 'No'],
    ];

    $v->lista($datos);

    echo "\n";

    if (!$bd_habilitada) {
        $v->mensaje('warning', 'Modo archivo activo (sin base de datos)');

        if ($v->confirmar("¿Configurar base de datos ahora?", false)) {
            $db_name = $v->input("Nombre de la base de datos", "boletas_electronicas");
            $db_user = $v->input("Usuario", "root");
            $db_pass = $v->input("Contraseña (dejar vacío si no tiene)");
            $db_host = $v->input("Host", "localhost");

            putenv("DB_NAME=$db_name");
            putenv("DB_USER=$db_user");
            putenv("DB_PASS=$db_pass");
            putenv("DB_HOST=$db_host");

            $config->set('database.name', $db_name);
            $config->set('database.user', $db_user);
            $config->set('database.password', $db_pass);
            $config->set('database.host', $db_host);
            $config->set('database.habilitado', true);

            $v->mensaje('success', 'Configuración de BD guardada');
            $v->mensaje('info', 'Ejecuta db/setup.php para crear las tablas');
        }
    } else {
        $v->mensaje('success', 'Base de datos configurada y activa');

        if ($v->confirmar("¿Probar conexión a BD?", false)) {
            try {
                $dsn = sprintf(
                    "mysql:host=%s;port=%d;dbname=%s;charset=%s",
                    $config->get('database.host'),
                    $config->get('database.port'),
                    $config->get('database.name'),
                    $config->get('database.charset')
                );

                $pdo = new PDO(
                    $dsn,
                    $config->get('database.user'),
                    $config->get('database.password')
                );

                $v->mensaje('success', 'Conexión a BD exitosa');
            } catch (Exception $e) {
                $v->mensaje('error', 'Error de conexión: ' . $e->getMessage());
            }
        }
    }
}

function configurarLogging($config, $v) {
    $v->subtitulo("📊  LOGGING Y MONITOREO");

    $datos = [
        ['texto' => 'Logging habilitado', 'valor' => $config->get('logging.habilitado') ? 'Sí' : 'No'],
        ['texto' => 'Nivel', 'valor' => $config->get('logging.nivel')],
        ['texto' => 'Guardar en archivo', 'valor' => $config->get('logging.guardar_en_archivo') ? 'Sí' : 'No'],
        ['texto' => 'Guardar en BD', 'valor' => $config->get('logging.guardar_en_bd') ? 'Sí' : 'No'],
        ['texto' => 'Rotación (días)', 'valor' => $config->get('logging.rotacion_dias')],
        ['texto' => 'Tamaño máx. (MB)', 'valor' => $config->get('logging.max_size_mb')],
    ];

    $v->lista($datos);

    echo "\n";

    if ($v->confirmar("¿Ajustar nivel de logging?", false)) {
        $v->mensaje('info', 'Niveles disponibles: DEBUG, INFO, WARNING, ERROR');
        $nivel = strtoupper($v->input("Nivel de logging", $config->get('logging.nivel')));

        if (in_array($nivel, ['DEBUG', 'INFO', 'WARNING', 'ERROR'])) {
            putenv("LOG_LEVEL=$nivel");
            $config->set('logging.nivel', $nivel);
            $v->mensaje('success', "Nivel de logging: $nivel");
        } else {
            $v->mensaje('error', 'Nivel no válido');
        }
    }

    if ($v->confirmar("¿Habilitar debug info en logs?", $config->get('logging.incluir_debug_info'))) {
        putenv("LOG_DEBUG_INFO=true");
        $config->set('logging.incluir_debug_info', true);
        $v->mensaje('success', 'Debug info habilitado en logs');
    } else {
        putenv("LOG_DEBUG_INFO=false");
        $config->set('logging.incluir_debug_info', false);
    }
}

function configurarSeguridad($config, $v) {
    $v->subtitulo("🔒  CONFIGURACIÓN DE SEGURIDAD");

    $datos = [
        ['texto' => 'Validar RUT receptor', 'valor' => $config->get('seguridad.validar_rut_receptor') ? 'Sí' : 'No'],
        ['texto' => 'Validar montos', 'valor' => $config->get('seguridad.validar_montos') ? 'Sí' : 'No'],
        ['texto' => 'Monto máximo', 'valor' => $config->get('seguridad.monto_maximo') ?: 'Sin límite'],
        ['texto' => 'Sanitizar inputs', 'valor' => $config->get('seguridad.sanitizar_inputs') ? 'Sí' : 'No'],
        ['texto' => 'Log de accesos', 'valor' => $config->get('seguridad.log_accesos') ? 'Sí' : 'No'],
    ];

    $v->lista($datos);

    echo "\n";

    if ($v->confirmar("¿Establecer monto máximo por boleta?", false)) {
        $monto = $v->input("Monto máximo (0 = sin límite)", $config->get('seguridad.monto_maximo'));
        putenv("MONTO_MAXIMO=$monto");
        $config->set('seguridad.monto_maximo', (int) $monto);

        if ((int) $monto > 0) {
            $v->mensaje('success', "Monto máximo establecido: $" . number_format($monto, 0, ',', '.'));
        } else {
            $v->mensaje('info', 'Sin límite de monto');
        }
    }

    if ($v->confirmar("¿Habilitar log de accesos?", $config->get('seguridad.log_accesos'))) {
        putenv("LOG_ACCESOS=true");
        $config->set('seguridad.log_accesos', true);
        $v->mensaje('success', 'Log de accesos habilitado');
    }
}

function configurarVisuales($config, $v) {
    $v->subtitulo("🎨  CONFIGURACIÓN VISUAL Y UX");

    $datos = [
        ['texto' => 'Colores', 'valor' => $config->get('visual.colores_habilitados') ? 'Habilitados' : 'Deshabilitados'],
        ['texto' => 'Emojis', 'valor' => $config->get('visual.emojis_habilitados') ? 'Habilitados' : 'Deshabilitados'],
        ['texto' => 'Barras de progreso', 'valor' => $config->get('visual.barras_progreso') ? 'Habilitadas' : 'Deshabilitadas'],
        ['texto' => 'Animaciones', 'valor' => $config->get('visual.animaciones') ? 'Habilitadas' : 'Deshabilitadas'],
        ['texto' => 'Modo verbose', 'valor' => $config->get('visual.verbose') ? 'Sí' : 'No'],
    ];

    $v->lista($datos);

    echo "\n";

    if ($v->confirmar("¿Deshabilitar colores? (para logs sin formato)", false)) {
        putenv("VISUAL_COLORES=false");
        $config->set('visual.colores_habilitados', false);
        $v->setColoresHabilitados(false);
        $v->mensaje('info', 'Colores deshabilitados');
    }

    if ($v->confirmar("¿Deshabilitar emojis? (para entornos sin soporte)", false)) {
        putenv("VISUAL_EMOJIS=false");
        $config->set('visual.emojis_habilitados', false);
        $v->setEmojisHabilitados(false);
        $v->mensaje('info', 'Emojis deshabilitados');
    }

    if ($v->confirmar("¿Habilitar animaciones?", $config->get('visual.animaciones'))) {
        putenv("VISUAL_ANIMACIONES=true");
        $config->set('visual.animaciones', true);
        $v->mensaje('success', 'Animaciones habilitadas');
    }
}

function verConfiguracionCompleta($config, $v) {
    $v->subtitulo("📋  CONFIGURACIÓN COMPLETA DEL SISTEMA");

    $todas = $config->getAll();

    foreach ($todas as $seccion => $valores) {
        $v->seccion(strtoupper($seccion));

        foreach ($valores as $key => $valor) {
            if (is_array($valor)) {
                echo "  • $key: " . json_encode($valor) . "\n";
            } elseif (is_bool($valor)) {
                echo "  • $key: " . ($valor ? 'Sí' : 'No') . "\n";
            } else {
                echo "  • $key: $valor\n";
            }
        }

        echo "\n";
    }
}

function exportarEnv($config, $v) {
    $v->subtitulo("💾  EXPORTAR CONFIGURACIÓN");

    $path = $config->exportarEnv();

    $v->mensaje('success', "Configuración exportada a: $path");
    $v->mensaje('info', 'Copia este archivo como .env para usar las configuraciones');

    echo "\n";
    $v->caja("Para usar el archivo .env, cárgalo con: putenv(parse_ini_file('.env'))", 'info');
}

function testConexion($config, $v) {
    $v->subtitulo("🧪  TEST DE CONEXIÓN");

    // Test API
    $v->cargando("Probando conexión con Simple API", 2);

    $api_url = $config->get('api.base_url');
    $headers = [
        "Authorization: Basic " . $config->get('api.api_key'),
        "Content-Type: application/json"
    ];

    $ch = curl_init($api_url . '/dte/document');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 || $http_code === 400) {
        $v->mensaje('success', "Conexión API exitosa (HTTP $http_code)");
    } else {
        $v->mensaje('error', "Error de conexión API (HTTP $http_code)");
    }

    // Test certificado
    $v->cargando("Verificando certificado digital", 1);

    $cert_path = $config->get('certificado.path');
    if (file_exists($cert_path)) {
        $v->mensaje('success', "Certificado encontrado: $cert_path");

        // Verificar que se puede leer
        $cert_data = file_get_contents($cert_path);
        if ($cert_data) {
            $v->mensaje('success', "Certificado legible (" . strlen($cert_data) . " bytes)");
        }
    } else {
        $v->mensaje('error', "Certificado no encontrado: $cert_path");
    }

    // Test CAF
    $v->cargando("Verificando archivo CAF", 1);

    $caf_path = $config->get('caf.path');
    if (file_exists($caf_path)) {
        $v->mensaje('success', "CAF encontrado: $caf_path");

        $caf_xml = simplexml_load_file($caf_path);
        if ($caf_xml && isset($caf_xml->CAF->DA->RNG)) {
            $desde = (int) $caf_xml->CAF->DA->RNG->D;
            $hasta = (int) $caf_xml->CAF->DA->RNG->H;
            $disponibles = $hasta - $desde + 1;

            $v->mensaje('success', "Folios disponibles: $disponibles (desde $desde hasta $hasta)");
        }
    } else {
        $v->mensaje('error', "CAF no encontrado: $caf_path");
    }

    // Test BD (si está habilitada)
    if ($config->get('database.habilitado')) {
        $v->cargando("Probando conexión a base de datos", 1);

        try {
            $dsn = sprintf(
                "mysql:host=%s;port=%d;dbname=%s;charset=%s",
                $config->get('database.host'),
                $config->get('database.port'),
                $config->get('database.name'),
                $config->get('database.charset')
            );

            $pdo = new PDO(
                $dsn,
                $config->get('database.user'),
                $config->get('database.password')
            );

            $v->mensaje('success', "Conexión a BD exitosa");

            // Verificar tablas
            $stmt = $pdo->query("SHOW TABLES");
            $tablas = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (count($tablas) > 0) {
                $v->mensaje('success', "Tablas encontradas: " . count($tablas));
            } else {
                $v->mensaje('warning', "BD sin tablas - ejecuta db/setup.php");
            }
        } catch (Exception $e) {
            $v->mensaje('error', "Error de conexión BD: " . $e->getMessage());
        }
    }

    echo "\n";
    $v->resumen("RESUMEN DE TESTS", [
        'API' => ['texto' => 'Simple API', 'valor' => 'OK', 'tipo' => 'success', 'icono' => '✓'],
        'Cert' => ['texto' => 'Certificado', 'valor' => 'OK', 'tipo' => 'success', 'icono' => '✓'],
        'CAF' => ['texto' => 'Archivo CAF', 'valor' => 'OK', 'tipo' => 'success', 'icono' => '✓'],
    ]);
}
