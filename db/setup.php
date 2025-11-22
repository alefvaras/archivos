#!/usr/bin/env php
<?php
/**
 * Script de configuración de base de datos
 * Crea la base de datos y ejecuta el schema.sql
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== CONFIGURACIÓN DE BASE DE DATOS ===\n\n";

// Configuración
$config = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'port' => getenv('DB_PORT') ?: 3306,
    'database' => getenv('DB_NAME') ?: 'boletas_electronicas',
    'username' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASS') ?: '',
];

echo "📋 Configuración:\n";
echo "  Host: {$config['host']}:{$config['port']}\n";
echo "  Base de datos: {$config['database']}\n";
echo "  Usuario: {$config['username']}\n\n";

// Verificar que MySQL esté disponible
$output = [];
$return_var = 0;
exec('which mysql 2>/dev/null', $output, $return_var);

if ($return_var !== 0) {
    echo "⚠️  Cliente MySQL no encontrado en el sistema\n";
    echo "   Este script requiere el cliente mysql para ejecutar.\n\n";
    echo "   Instalar con: apt-get install mysql-client\n";
    echo "   O ejecutar manualmente: mysql -u{$config['username']} -p < db/schema.sql\n\n";
    exit(1);
}

try {
    // Conectar a MySQL (sin especificar base de datos)
    $dsn = "mysql:host={$config['host']};port={$config['port']}";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "✓ Conexión a MySQL exitosa\n\n";

    // Crear base de datos si no existe
    echo "📦 Creando base de datos '{$config['database']}'...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['database']}`
                CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Base de datos creada/verificada\n\n";

    // Usar la base de datos
    $pdo->exec("USE `{$config['database']}`");

    // Leer schema.sql
    $schema_file = __DIR__ . '/schema.sql';

    if (!file_exists($schema_file)) {
        throw new Exception("No se encuentra el archivo schema.sql en: {$schema_file}");
    }

    echo "📄 Leyendo schema.sql...\n";
    $schema = file_get_contents($schema_file);

    // Ejecutar schema (dividir por bloques usando DELIMITER)
    echo "🔄 Ejecutando schema SQL...\n";

    // Separar el SQL en statements
    $statements = preg_split('/;\\s*(?=CREATE|INSERT|DELIMITER|DROP|ALTER|--)/i', $schema);

    $executed = 0;
    $skipped = 0;
    $errors = 0;

    foreach ($statements as $statement) {
        $statement = trim($statement);

        // Saltar comentarios y líneas vacías
        if (empty($statement) || strpos($statement, '--') === 0) {
            $skipped++;
            continue;
        }

        // Saltar DELIMITERs
        if (stripos($statement, 'DELIMITER') !== false) {
            $skipped++;
            continue;
        }

        // Saltar CREATE PROCEDURE por ahora (requiere DELIMITER handling especial)
        if (stripos($statement, 'CREATE PROCEDURE') !== false) {
            echo "  ⚠️  Saltando stored procedure (ejecutar manualmente si es necesario)\n";
            $skipped++;
            continue;
        }

        // Saltar CREATE VIEW si ya existe
        if (stripos($statement, 'CREATE OR REPLACE VIEW') !== false) {
            // Reemplazar con DROP VIEW IF EXISTS + CREATE VIEW
            $view_name = null;
            if (preg_match('/CREATE OR REPLACE VIEW\s+(\w+)/i', $statement, $matches)) {
                $view_name = $matches[1];
                $pdo->exec("DROP VIEW IF EXISTS {$view_name}");
                $statement = preg_replace('/CREATE OR REPLACE VIEW/i', 'CREATE VIEW', $statement);
            }
        }

        try {
            $pdo->exec($statement);
            $executed++;
        } catch (PDOException $e) {
            // Ignorar errores de "ya existe"
            if (strpos($e->getMessage(), 'already exists') !== false ||
                strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $skipped++;
            } else {
                echo "  ❌ Error: " . substr($e->getMessage(), 0, 100) . "...\n";
                $errors++;
            }
        }
    }

    echo "\n📊 Resumen de ejecución:\n";
    echo "  ✓ Statements ejecutados: {$executed}\n";
    echo "  ⊘ Saltados/Ya existen: {$skipped}\n";

    if ($errors > 0) {
        echo "  ❌ Errores: {$errors}\n";
    }

    // Verificar tablas creadas
    echo "\n📋 Tablas creadas:\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "  ✓ {$table}\n";
    }

    // Insertar datos de prueba
    echo "\n🎯 Insertando datos iniciales...\n";

    // Cliente genérico
    try {
        $pdo->exec("INSERT IGNORE INTO clientes (rut, razon_social, email, activo)
                    VALUES ('66666666-6', 'Cliente Final', NULL, TRUE)");
        echo "  ✓ Cliente genérico creado\n";
    } catch (PDOException $e) {
        // Ya existe
    }

    echo "\n✅ CONFIGURACIÓN COMPLETADA EXITOSAMENTE\n\n";

    echo "🔐 Variables de entorno (opcional):\n";
    echo "  export DB_HOST={$config['host']}\n";
    echo "  export DB_PORT={$config['port']}\n";
    echo "  export DB_NAME={$config['database']}\n";
    echo "  export DB_USER={$config['username']}\n";
    echo "  export DB_PASS=****\n\n";

    echo "📚 Siguiente paso:\n";
    echo "  Ejecutar: php test-database.php\n\n";

} catch (PDOException $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n\n";
    echo "Verifica:\n";
    echo "  1. MySQL/MariaDB está instalado y corriendo\n";
    echo "  2. Credenciales de acceso correctas\n";
    echo "  3. Usuario tiene permisos para crear bases de datos\n\n";
    exit(1);
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n\n";
    exit(1);
}
