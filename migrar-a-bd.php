#!/usr/bin/env php
<?php
/**
 * Script de Migración a Base de Datos
 * Migra datos existentes desde archivos a la base de datos
 *
 * - folios_usados.txt → tabla folios_usados
 * - archivos CAF → tabla cafs
 * - XMLs generados → tabla boletas
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== MIGRACIÓN A BASE DE DATOS ===\n\n";

// ============================================================
// Verificar requisitos
// ============================================================

echo "📋 Verificando requisitos...\n";

// Verificar variables de entorno
$db_vars = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS'];
$missing = [];

foreach ($db_vars as $var) {
    if (!getenv($var)) {
        $missing[] = $var;
    }
}

if (!empty($missing)) {
    echo "\n❌ ERROR: Faltan variables de entorno:\n";
    foreach ($missing as $var) {
        echo "  - {$var}\n";
    }
    echo "\nConfigura las variables con:\n";
    echo "  export DB_HOST=localhost\n";
    echo "  export DB_PORT=3306\n";
    echo "  export DB_NAME=boletas_electronicas\n";
    echo "  export DB_USER=root\n";
    echo "  export DB_PASS=tu_password\n\n";
    exit(1);
}

echo "  ✓ Variables de entorno configuradas\n";

// Verificar conexión a BD
try {
    require_once(__DIR__ . '/lib/Database.php');
    $db = Database::getInstance();
    $db->query('SELECT 1');
    echo "  ✓ Conexión a base de datos exitosa\n";
} catch (Exception $e) {
    echo "\n❌ ERROR: No se pudo conectar a la base de datos\n";
    echo "  {$e->getMessage()}\n\n";
    echo "Verifica:\n";
    echo "  1. MySQL/MariaDB está corriendo\n";
    echo "  2. Credenciales correctas\n";
    echo "  3. Base de datos existe (ejecuta: php db/setup.php)\n\n";
    exit(1);
}

echo "\n";

// ============================================================
// Paso 1: Migrar CAFs
// ============================================================

echo "📦 Paso 1: Migrando archivos CAF...\n\n";

require_once(__DIR__ . '/lib/BoletaRepository.php');
$repo = new BoletaRepository();

// Buscar archivos CAF en el directorio actual
$caf_files = glob(__DIR__ . '/*.xml');
$cafs_migrados = 0;
$cafs_saltados = 0;

foreach ($caf_files as $caf_file) {
    $filename = basename($caf_file);

    // Solo procesar archivos que parezcan CAFs
    if (!preg_match('/FoliosSII/i', $filename)) {
        continue;
    }

    echo "  Procesando: {$filename}\n";

    try {
        // Leer CAF
        $caf_xml = simplexml_load_file($caf_file);

        if (!isset($caf_xml->DA->RNG->D)) {
            echo "    ⚠️  No es un CAF válido, saltando...\n";
            $cafs_saltados++;
            continue;
        }

        $tipo_dte = (int) $caf_xml->DA->TD;
        $folio_desde = (int) $caf_xml->DA->RNG->D;
        $folio_hasta = (int) $caf_xml->DA->RNG->H;
        $fecha_auth = (string) $caf_xml->DA->FA;

        // Verificar si ya existe
        $existente = $db->fetchOne(
            'SELECT id FROM cafs WHERE tipo_dte = ? AND folio_desde = ? AND folio_hasta = ?',
            [$tipo_dte, $folio_desde, $folio_hasta]
        );

        if ($existente) {
            echo "    ⊘ Ya existe en BD, saltando...\n";
            $cafs_saltados++;
            continue;
        }

        // Insertar CAF
        $caf_content = file_get_contents($caf_file);
        $caf_id = $db->insert('cafs', [
            'tipo_dte' => $tipo_dte,
            'folio_desde' => $folio_desde,
            'folio_hasta' => $folio_hasta,
            'fecha_autorizacion' => $fecha_auth,
            'archivo_caf' => $caf_content,
            'archivo_nombre' => $filename,
            'activo' => true
        ]);

        echo "    ✓ Migrado: DTE {$tipo_dte}, Folios {$folio_desde}-{$folio_hasta} (ID: {$caf_id})\n";
        $cafs_migrados++;

    } catch (Exception $e) {
        echo "    ❌ Error: {$e->getMessage()}\n";
    }
}

echo "\n  Resumen:\n";
echo "    Migrados: {$cafs_migrados}\n";
echo "    Saltados: {$cafs_saltados}\n\n";

// ============================================================
// Paso 2: Migrar folios usados
// ============================================================

echo "🔢 Paso 2: Migrando folios usados...\n\n";

$archivo_folios = __DIR__ . '/folios_usados.txt';

if (!file_exists($archivo_folios)) {
    echo "  ℹ️  No se encontró folios_usados.txt, saltando...\n\n";
} else {
    $ultimo_folio = (int) trim(file_get_contents($archivo_folios));

    echo "  Último folio en archivo: {$ultimo_folio}\n";

    // Obtener el primer CAF activo para DTE 39 (Boletas)
    $caf = $db->fetchOne(
        'SELECT id, folio_desde, folio_hasta FROM cafs WHERE tipo_dte = 39 AND activo = TRUE ORDER BY folio_desde LIMIT 1'
    );

    if (!$caf) {
        echo "  ⚠️  No hay CAF para DTE 39 en la BD\n";
        echo "  Registra primero un CAF para Boletas Electrónicas\n\n";
    } else {
        $folio_desde = $caf['folio_desde'];
        $caf_id = $caf['id'];

        echo "  CAF encontrado: Folios {$caf['folio_desde']}-{$caf['folio_hasta']}\n";
        echo "  Migrando folios {$folio_desde} a {$ultimo_folio}...\n";

        $folios_migrados = 0;

        // Insertar todos los folios usados
        for ($folio = $folio_desde; $folio <= $ultimo_folio; $folio++) {
            try {
                // Verificar si ya existe
                $existe = $db->fetchOne(
                    'SELECT id FROM folios_usados WHERE tipo_dte = 39 AND folio = ?',
                    [$folio]
                );

                if (!$existe) {
                    $db->insert('folios_usados', [
                        'tipo_dte' => 39,
                        'folio' => $folio,
                        'caf_id' => $caf_id,
                        'usado_en' => date('Y-m-d H:i:s')
                    ]);
                    $folios_migrados++;
                }
            } catch (Exception $e) {
                // Ignorar duplicados
            }
        }

        echo "  ✓ Folios migrados: {$folios_migrados}\n";

        // Crear backup del archivo
        $backup = $archivo_folios . '.backup.' . date('Ymd_His');
        copy($archivo_folios, $backup);
        echo "  ✓ Backup creado: {$backup}\n\n";
    }
}

// ============================================================
// Paso 3: Migrar XMLs generados (opcional)
// ============================================================

echo "📄 Paso 3: Buscando XMLs de boletas...\n\n";

$xml_dir = '/tmp';
$xml_files = glob("{$xml_dir}/boleta_*.xml");

echo "  Encontrados: " . count($xml_files) . " archivos XML\n";

if (count($xml_files) > 0) {
    echo "  ¿Deseas migrar estos XMLs a la BD? (s/n): ";
    $handle = fopen("php://stdin", "r");
    $respuesta = trim(fgets($handle));
    fclose($handle);

    if (strtolower($respuesta) === 's') {
        $xmls_migrados = 0;

        foreach ($xml_files as $xml_file) {
            try {
                $xml_content = file_get_contents($xml_file);
                $xml = simplexml_load_string($xml_content);

                if (!isset($xml->Documento->Encabezado->IdDoc->Folio)) {
                    continue;
                }

                $folio = (int) $xml->Documento->Encabezado->IdDoc->Folio;
                $tipo_dte = (int) $xml->Documento->Encabezado->IdDoc->TipoDTE;

                // Verificar si ya existe
                $existe = $db->fetchOne(
                    'SELECT id FROM boletas WHERE tipo_dte = ? AND folio = ?',
                    [$tipo_dte, $folio]
                );

                if ($existe) {
                    continue;
                }

                // Extraer datos
                $encabezado = $xml->Documento->Encabezado;
                $totales = $encabezado->Totales;

                // Insertar boleta
                $boleta_id = $db->insert('boletas', [
                    'tipo_dte' => $tipo_dte,
                    'folio' => $folio,
                    'fecha_emision' => (string) $encabezado->IdDoc->FchEmis,
                    'rut_emisor' => (string) $encabezado->Emisor->RUTEmisor,
                    'razon_social_emisor' => (string) $encabezado->Emisor->RznSocEmisor,
                    'rut_receptor' => (string) $encabezado->Receptor->RUTRecep,
                    'razon_social_receptor' => (string) $encabezado->Receptor->RznSocRecep,
                    'monto_neto' => (int) $totales->MntNeto,
                    'monto_iva' => (int) $totales->IVA,
                    'monto_total' => (int) $totales->MntTotal,
                    'xml_dte' => $xml_content
                ]);

                echo "    ✓ Migrado: Folio {$folio} (ID: {$boleta_id})\n";
                $xmls_migrados++;

            } catch (Exception $e) {
                // Ignorar errores
            }
        }

        echo "\n  ✓ XMLs migrados: {$xmls_migrados}\n\n";
    } else {
        echo "  Migración de XMLs saltada\n\n";
    }
} else {
    echo "  No hay XMLs para migrar\n\n";
}

// ============================================================
// Paso 4: Verificación
// ============================================================

echo "🔍 Paso 4: Verificando migración...\n\n";

// Contar registros
$stats = [
    'cafs' => $db->fetchOne('SELECT COUNT(*) as count FROM cafs')['count'],
    'folios' => $db->fetchOne('SELECT COUNT(*) as count FROM folios_usados')['count'],
    'boletas' => $db->fetchOne('SELECT COUNT(*) as count FROM boletas')['count'],
    'clientes' => $db->fetchOne('SELECT COUNT(*) as count FROM clientes')['count']
];

echo "  Registros en base de datos:\n";
echo "    CAFs: {$stats['cafs']}\n";
echo "    Folios usados: {$stats['folios']}\n";
echo "    Boletas: {$stats['boletas']}\n";
echo "    Clientes: {$stats['clientes']}\n\n";

// Ver folios disponibles
if ($stats['cafs'] > 0) {
    echo "  Folios disponibles por CAF:\n";
    $disponibles = $db->fetchAll('SELECT * FROM v_folios_disponibles');

    foreach ($disponibles as $row) {
        $tipo_dte_nombres = [
            33 => 'Factura Afecta',
            34 => 'Factura Exenta',
            39 => 'Boleta Electrónica',
            56 => 'Nota de Débito',
            61 => 'Nota de Crédito'
        ];

        $nombre_dte = $tipo_dte_nombres[$row['tipo_dte']] ?? "DTE {$row['tipo_dte']}";

        echo "    {$nombre_dte}: {$row['folios_disponibles']} disponibles de {$row['total_folios']}\n";
    }
    echo "\n";
}

// ============================================================
// Resumen final
// ============================================================

echo str_repeat('=', 60) . "\n";
echo "MIGRACIÓN COMPLETADA\n";
echo str_repeat('=', 60) . "\n\n";

echo "✅ Datos migrados exitosamente:\n";
echo "  ✓ CAFs: {$cafs_migrados} archivos\n";
echo "  ✓ Folios: Hasta folio " . ($ultimo_folio ?? 'N/A') . "\n";
echo "  ✓ Boletas: " . ($xmls_migrados ?? 0) . " registros\n\n";

echo "📝 Próximos pasos:\n";
echo "  1. Verifica los datos en la BD\n";
echo "  2. Configura generar-boleta.php para usar BD\n";
echo "  3. Prueba con: php ejemplo-integracion-completa.php\n\n";

echo "⚠️  IMPORTANTE:\n";
echo "  - Los archivos originales se mantienen como backup\n";
echo "  - folios_usados.txt tiene backup en: " . ($backup ?? 'N/A') . "\n";
echo "  - Puedes seguir usando modo archivo si lo prefieres\n\n";

echo "=== FIN DE LA MIGRACIÓN ===\n";
