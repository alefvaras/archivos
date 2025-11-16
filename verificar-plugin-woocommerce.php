<?php
/**
 * Script de Verificación del Plugin WooCommerce Boletas Electrónicas
 *
 * Ejecutar ANTES de instalar el plugin en WordPress para verificar
 * que todos los componentes estén correctos y funcionales.
 *
 * Uso: php verificar-plugin-woocommerce.php
 */

echo "=== VERIFICACIÓN DEL PLUGIN WOOCOMMERCE BOLETAS ELECTRÓNICAS ===\n\n";

$errores = [];
$warnings = [];
$exitos = 0;

// ========================================
// 1. VERIFICAR PHP Y EXTENSIONES
// ========================================
echo "📋 Paso 1: Verificando PHP y extensiones...\n";

// PHP Version
if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
    echo "  ✓ PHP " . PHP_VERSION . " (mínimo 8.0)\n";
    $exitos++;
} else {
    $errores[] = "PHP version " . PHP_VERSION . " es menor que 8.0";
    echo "  ❌ PHP " . PHP_VERSION . " (requiere 8.0+)\n";
}

// Extensiones requeridas
$extensiones_requeridas = ['bcmath', 'gd', 'dom', 'pdo', 'pdo_mysql'];
$extensiones_faltantes = [];

foreach ($extensiones_requeridas as $ext) {
    if (extension_loaded($ext)) {
        echo "  ✓ Extensión $ext\n";
        $exitos++;
    } else {
        $extensiones_faltantes[] = $ext;
        echo "  ❌ Extensión $ext faltante\n";
    }
}

if (!empty($extensiones_faltantes)) {
    $errores[] = "Faltan extensiones: " . implode(', ', $extensiones_faltantes);
}

echo "\n";

// ========================================
// 2. VERIFICAR ARCHIVOS DEL SISTEMA
// ========================================
echo "📦 Paso 2: Verificando archivos del sistema de boletas...\n";

$archivos_requeridos = [
    'woocommerce-boletas-electronicas.php' => 'Plugin principal',
    'generar-boleta.php' => 'Script generador de boletas',
    'lib/DTELogger.php' => 'Sistema de logging',
    'lib/Database.php' => 'Conexión a BD',
    'lib/BoletaRepository.php' => 'Repositorio de boletas',
    'lib/generar-timbre-pdf417.php' => 'Generador de timbre PDF417',
    'lib/generar-pdf-boleta.php' => 'Generador de PDF',
    'lib/fpdf.php' => 'Librería FPDF',
    'lib/pdf417/src/PDF417.php' => 'Librería PDF417',
    'db/schema.sql' => 'Schema de BD',
    'db/setup.php' => 'Setup de BD',
];

$archivos_faltantes = [];

foreach ($archivos_requeridos as $archivo => $descripcion) {
    if (file_exists(__DIR__ . '/' . $archivo)) {
        echo "  ✓ $descripcion ($archivo)\n";
        $exitos++;
    } else {
        $archivos_faltantes[] = $archivo;
        echo "  ❌ $descripcion faltante ($archivo)\n";
    }
}

if (!empty($archivos_faltantes)) {
    $errores[] = "Faltan archivos: " . implode(', ', $archivos_faltantes);
}

echo "\n";

// ========================================
// 3. VERIFICAR SINTAXIS PHP
// ========================================
echo "🔍 Paso 3: Verificando sintaxis PHP del plugin...\n";

$plugin_path = __DIR__ . '/woocommerce-boletas-electronicas.php';
if (file_exists($plugin_path)) {
    exec("php -l " . escapeshellarg($plugin_path) . " 2>&1", $output, $return_code);

    if ($return_code === 0) {
        echo "  ✓ Sintaxis PHP correcta\n";
        $exitos++;
    } else {
        $errores[] = "Error de sintaxis en plugin: " . implode("\n", $output);
        echo "  ❌ Error de sintaxis PHP\n";
    }
} else {
    $errores[] = "Archivo del plugin no encontrado";
    echo "  ❌ Archivo del plugin no encontrado\n";
}

echo "\n";

// ========================================
// 4. VERIFICAR ESTRUCTURA DE DIRECTORIOS
// ========================================
echo "📁 Paso 4: Verificando estructura de directorios...\n";

$directorios_necesarios = ['logs', 'pdfs', 'xmls'];

foreach ($directorios_necesarios as $dir) {
    $dir_path = __DIR__ . '/' . $dir;

    if (!is_dir($dir_path)) {
        // Intentar crear
        if (mkdir($dir_path, 0755, true)) {
            echo "  ✓ Directorio '$dir' creado\n";
            $exitos++;
        } else {
            $warnings[] = "No se pudo crear directorio '$dir'";
            echo "  ⚠️  Directorio '$dir' no existe y no se pudo crear\n";
        }
    } else {
        // Verificar permisos
        if (is_writable($dir_path)) {
            echo "  ✓ Directorio '$dir' existe y es escribible\n";
            $exitos++;
        } else {
            $warnings[] = "Directorio '$dir' no es escribible";
            echo "  ⚠️  Directorio '$dir' existe pero no es escribible\n";
        }
    }
}

echo "\n";

// ========================================
// 5. VERIFICAR CONFIGURACIÓN
// ========================================
echo "⚙️  Paso 5: Verificando configuración del sistema...\n";

if (file_exists(__DIR__ . '/generar-boleta.php')) {
    require_once __DIR__ . '/generar-boleta.php';

    // Verificar constantes
    $constantes_requeridas = ['API_KEY', 'CERT_PATH', 'CERT_PASSWORD', 'CAF_PATH', 'RUT_EMISOR', 'RAZON_SOCIAL', 'AMBIENTE'];
    $constantes_faltantes = [];

    foreach ($constantes_requeridas as $const) {
        if (defined($const)) {
            $valor = constant($const);
            if (!empty($valor) && $valor !== 'tu-api-key-aqui' && $valor !== 'tu-password') {
                echo "  ✓ Constante $const configurada\n";
                $exitos++;
            } else {
                $warnings[] = "Constante $const no configurada (valor por defecto)";
                echo "  ⚠️  Constante $const con valor por defecto\n";
            }
        } else {
            $constantes_faltantes[] = $const;
            echo "  ❌ Constante $const no definida\n";
        }
    }

    if (!empty($constantes_faltantes)) {
        $errores[] = "Faltan constantes: " . implode(', ', $constantes_faltantes);
    }

    // Verificar que $API_BASE esté definido
    if (isset($API_BASE)) {
        echo "  ✓ Variable \$API_BASE definida: $API_BASE\n";
        $exitos++;
    } else {
        $errores[] = "Variable global \$API_BASE no definida en generar-boleta.php";
        echo "  ❌ Variable \$API_BASE no definida\n";
    }

    // Verificar función generar_boleta
    if (function_exists('generar_boleta')) {
        echo "  ✓ Función generar_boleta() disponible\n";
        $exitos++;
    } else {
        $errores[] = "Función generar_boleta() no encontrada";
        echo "  ❌ Función generar_boleta() no encontrada\n";
    }
}

echo "\n";

// ========================================
// 6. VERIFICAR BASE DE DATOS (OPCIONAL)
// ========================================
echo "🗄️  Paso 6: Verificando base de datos (opcional)...\n";

$db_name = getenv('DB_NAME');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASS');
$db_host = getenv('DB_HOST') ?: 'localhost';

if ($db_name && $db_user) {
    echo "  ℹ️  Variables de entorno BD configuradas\n";

    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
        echo "  ✓ Conexión a BD exitosa ($db_name)\n";
        $exitos++;

        // Verificar tablas
        $tablas_requeridas = ['clientes', 'cafs', 'folios_usados', 'boletas', 'boleta_items', 'logs'];
        $result = $pdo->query("SHOW TABLES");
        $tablas_existentes = $result->fetchAll(PDO::FETCH_COLUMN);

        $tablas_faltantes = array_diff($tablas_requeridas, $tablas_existentes);

        if (empty($tablas_faltantes)) {
            echo "  ✓ Todas las tablas existen\n";
            $exitos++;
        } else {
            $warnings[] = "Faltan tablas en BD: " . implode(', ', $tablas_faltantes) . " (ejecutar: php db/setup.php)";
            echo "  ⚠️  Faltan tablas: " . implode(', ', $tablas_faltantes) . "\n";
            echo "      Solución: php db/setup.php\n";
        }
    } catch (PDOException $e) {
        $warnings[] = "Error conectando a BD: " . $e->getMessage();
        echo "  ⚠️  No se pudo conectar a BD: " . $e->getMessage() . "\n";
        echo "      (BD es opcional, puede usar modo archivo)\n";
    }
} else {
    echo "  ℹ️  Variables de entorno BD no configuradas (usará modo archivo)\n";
}

echo "\n";

// ========================================
// 7. VALIDAR ALGORITMO DE RUT
// ========================================
echo "🔐 Paso 7: Validando algoritmo de validación de RUT...\n";

// Función de validación extraída del plugin
function validar_rut_test($rut) {
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

// Tests de RUTs válidos e inválidos
$ruts_test = [
    '12345678-5' => true,
    '11111111-1' => true,
    '22222222-2' => true,
    '66666666-6' => true,
    '12345678-9' => false,
    '11111111-2' => false,
];

$rut_tests_ok = true;
foreach ($ruts_test as $rut => $esperado) {
    $resultado = validar_rut_test($rut);
    if ($resultado === $esperado) {
        echo "  ✓ RUT $rut validado correctamente\n";
        $exitos++;
    } else {
        echo "  ❌ RUT $rut falló validación (esperado: " . ($esperado ? 'válido' : 'inválido') . ", obtenido: " . ($resultado ? 'válido' : 'inválido') . ")\n";
        $rut_tests_ok = false;
    }
}

if (!$rut_tests_ok) {
    $errores[] = "Algoritmo de validación de RUT tiene errores";
}

echo "\n";

// ========================================
// RESUMEN FINAL
// ========================================
echo "=== RESUMEN DE VERIFICACIÓN ===\n\n";

echo "✅ Verificaciones exitosas: $exitos\n";
echo "⚠️  Advertencias: " . count($warnings) . "\n";
echo "❌ Errores críticos: " . count($errores) . "\n\n";

if (!empty($warnings)) {
    echo "ADVERTENCIAS:\n";
    foreach ($warnings as $i => $warning) {
        echo "  " . ($i + 1) . ". $warning\n";
    }
    echo "\n";
}

if (!empty($errores)) {
    echo "ERRORES CRÍTICOS:\n";
    foreach ($errores as $i => $error) {
        echo "  " . ($i + 1) . ". $error\n";
    }
    echo "\n";
    echo "❌ EL PLUGIN NO ESTÁ LISTO PARA INSTALAR\n";
    echo "   Corrige los errores críticos antes de continuar.\n\n";
    exit(1);
} else {
    echo "✅ EL PLUGIN ESTÁ LISTO PARA INSTALAR EN WORDPRESS\n\n";

    if (count($warnings) > 0) {
        echo "ℹ️  Hay algunas advertencias, pero no son críticas.\n";
        echo "   El plugin funcionará, pero revisa las advertencias para optimizar.\n\n";
    }

    echo "PRÓXIMOS PASOS:\n";
    echo "1. Crear enlace simbólico o copiar a WordPress:\n";
    echo "   ln -s " . __DIR__ . " /var/www/html/wp-content/plugins/woocommerce-boletas-electronicas\n\n";
    echo "2. O crear ZIP para subir:\n";
    echo "   zip -r woocommerce-boletas-electronicas.zip \\\n";
    echo "     woocommerce-boletas-electronicas.php generar-boleta.php lib/ db/\n\n";
    echo "3. Activar plugin en WordPress Admin → Plugins\n\n";
    echo "4. Configurar variables de entorno en wp-config.php (opcional para BD)\n\n";

    exit(0);
}
