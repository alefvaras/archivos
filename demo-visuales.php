<?php
/**
 * Demostración de Mejoras Visuales
 *
 * Muestra todas las capacidades visuales y de configuración del sistema
 */

require_once __DIR__ . '/lib/VisualHelper.php';
require_once __DIR__ . '/config/settings.php';

$v = VisualHelper::getInstance();
$config = ConfiguracionSistema::getInstance();

// Limpiar pantalla
$v->limpiar();

// ========================================
// TÍTULO Y BIENVENIDA
// ========================================

$v->titulo("DEMOSTRACIÓN DE MEJORAS VISUALES Y UX", "═");

$v->caja(
    "Este script demuestra todas las mejoras visuales implementadas:\n" .
    "• Colores en consola (con soporte ANSI)\n" .
    "• Emojis y símbolos Unicode\n" .
    "• Barras de progreso animadas\n" .
    "• Tablas formateadas\n" .
    "• Cajas y secciones\n" .
    "• Sistema de configuración centralizado\n" .
    "• Dashboard de estadísticas\n" .
    "• Panel de configuración interactivo",
    'info'
);

$v->pausar();

// ========================================
// DEMOSTRACIÓN DE COLORES
// ========================================

$v->limpiar();
$v->titulo("COLORES Y FORMATOS", "═");

$v->subtitulo("Tipos de Mensajes");

$v->mensaje('success', 'Mensaje de éxito - operación completada correctamente');
$v->mensaje('error', 'Mensaje de error - algo salió mal');
$v->mensaje('warning', 'Mensaje de advertencia - ten cuidado');
$v->mensaje('info', 'Mensaje informativo - información útil');

echo "\n";
$v->subtitulo("Textos con Colores");

echo "  " . $v->success("✓ Texto en verde (éxito)") . "\n";
echo "  " . $v->error("✗ Texto en rojo (error)") . "\n";
echo "  " . $v->warning("⚠ Texto en amarillo (advertencia)") . "\n";
echo "  " . $v->info("ℹ Texto en cyan (info)") . "\n";
echo "  " . $v->primary("★ Texto en azul (primario)") . "\n";
echo "  " . $v->dim("Texto atenuado (dim)") . "\n";

$v->pausar();

// ========================================
// DEMOSTRACIÓN DE LISTAS
// ========================================

$v->limpiar();
$v->titulo("LISTAS Y VIÑETAS", "═");

$v->subtitulo("Lista Simple");

$v->lista([
    'Generación de boletas electrónicas',
    'Envío automático al SII',
    'Generación de PDF con timbre',
    'Consulta de estados',
    'Logging estructurado'
]);

echo "\n";
$v->subtitulo("Lista con Valores");

$v->lista([
    ['texto' => 'Ambiente', 'valor' => 'Certificación'],
    ['texto' => 'API Key', 'valor' => 'Configurada'],
    ['texto' => 'Certificado', 'valor' => 'Válido'],
    ['texto' => 'CAF', 'valor' => '100 folios disponibles'],
]);

$v->pausar();

// ========================================
// DEMOSTRACIÓN DE TABLAS
// ========================================

$v->limpiar();
$v->titulo("TABLAS FORMATEADAS", "═");

$v->subtitulo("Tabla de Boletas");

$headers = ['Folio', 'Cliente', 'Total', 'Estado', 'Fecha'];
$rows = [
    ['1890', 'Juan Pérez', '$29,800', 'EPR', '2025-11-16'],
    ['1891', 'María García', '$45,000', 'EPR', '2025-11-16'],
    ['1892', 'Pedro Rodríguez', '$120,000', 'EPR', '2025-11-16'],
    ['1893', 'Ana Martínez', '$75,500', 'REC', '2025-11-16'],
];

$v->tabla($headers, $rows);

$v->pausar();

// ========================================
// DEMOSTRACIÓN DE BARRAS DE PROGRESO
// ========================================

$v->limpiar();
$v->titulo("BARRAS DE PROGRESO", "═");

$v->subtitulo("Generando 10 Boletas...");

for ($i = 1; $i <= 10; $i++) {
    $v->barraProgreso($i, 10, 50, "Generando boletas");
    usleep(300000); // 300ms
}

echo "\n\n";
$v->subtitulo("Procesando Múltiples Tareas");

$tareas = [
    'Leyendo CAF',
    'Generando DTE',
    'Firmando documento',
    'Creando PDF',
    'Enviando a SII',
];

foreach ($tareas as $index => $tarea) {
    $v->barraProgreso($index + 1, count($tareas), 40, $tarea);
    usleep(400000); // 400ms
}

echo "\n";
$v->mensaje('success', 'Todas las tareas completadas correctamente');

$v->pausar();

// ========================================
// DEMOSTRACIÓN DE ANIMACIONES
// ========================================

$v->limpiar();
$v->titulo("ANIMACIONES DE CARGA", "═");

$v->subtitulo("Simulando Operaciones");

$v->cargando("Conectando con SII", 2);
$v->mensaje('success', 'Conexión establecida');

echo "\n";

$v->cargando("Validando certificado digital", 2);
$v->mensaje('success', 'Certificado válido');

echo "\n";

$v->cargando("Consultando estado de Track ID", 2);
$v->mensaje('success', 'Estado: EPR (Procesado correctamente)');

$v->pausar();

// ========================================
// DEMOSTRACIÓN DE SECCIONES Y CAJAS
// ========================================

$v->limpiar();
$v->titulo("SECCIONES Y CAJAS", "═");

$v->seccion("Configuración del Sistema", [
    'Ambiente: Certificación',
    'RUT Emisor: 78274225-6',
    'Razón Social: AKIBARA SPA',
    'API: Simple API',
]);

$v->caja(
    "IMPORTANTE: Este sistema está configurado en ambiente de certificación. " .
    "Los documentos generados son válidos para pruebas pero no tienen validez tributaria.",
    'warning'
);

$v->caja(
    "ÉXITO: El sistema ha sido probado y está listo para producción. " .
    "Tasa de aceptación del 100% en las últimas 5 boletas generadas.",
    'success'
);

$v->pausar();

// ========================================
// DEMOSTRACIÓN DE RESUMEN
// ========================================

$v->limpiar();
$v->titulo("RESUMEN CON ESTADÍSTICAS", "═");

$v->resumen("Estadísticas del Día", [
    'boletas' => [
        'texto' => 'Boletas generadas',
        'valor' => '23',
        'tipo' => 'success',
        'icono' => '📄'
    ],
    'aceptadas' => [
        'texto' => 'Aceptadas por SII',
        'valor' => '23',
        'tipo' => 'success',
        'icono' => '✅'
    ],
    'rechazadas' => [
        'texto' => 'Rechazadas',
        'valor' => '0',
        'tipo' => 'success',
        'icono' => '❌'
    ],
    'tasa' => [
        'texto' => 'Tasa de éxito',
        'valor' => '100%',
        'tipo' => 'success',
        'icono' => '📈'
    ],
    'tiempo' => [
        'texto' => 'Tiempo promedio',
        'valor' => '2.3s',
        'tipo' => 'info',
        'icono' => '⏱️'
    ],
]);

$v->pausar();

// ========================================
// DEMOSTRACIÓN DE CONFIGURACIÓN
// ========================================

$v->limpiar();
$v->titulo("SISTEMA DE CONFIGURACIÓN CENTRALIZADO", "═");

$v->subtitulo("Configuraciones Disponibles");

$categorias = [
    '⚙️  General' => 'Ambiente, debug, timezone, locale',
    '🏢  Emisor' => 'RUT, razón social, giro, dirección',
    '🌐  API' => 'URL, API key, timeouts, reintentos',
    '📧  Email' => 'SMTP, plantillas, adjuntos',
    '📄  PDF' => 'Colores, logo, formato, márgenes',
    '🗄️  Base de Datos' => 'Conexión, pool, fallback',
    '📊  Logging' => 'Nivel, destinos, rotación',
    '🔒  Seguridad' => 'Validaciones, límites, sanitización',
    '🎨  Visuales' => 'Colores, emojis, animaciones',
];

$v->lista(array_map(fn($k, $v) => "$k → $v", array_keys($categorias), $categorias));

echo "\n";
$v->mensaje('info', 'Ejecuta: php panel-configuracion.php para configurar interactivamente');

$v->pausar();

// ========================================
// DEMOSTRACIÓN DE VALIDACIONES
// ========================================

$v->limpiar();
$v->titulo("VALIDACIÓN DE CONFIGURACIÓN", "═");

$v->subtitulo("Verificando Sistema...");

sleep(1);

$validacion = $config->validar();

if ($validacion['valido']) {
    $v->caja(
        "SISTEMA VALIDADO CORRECTAMENTE\n\n" .
        "✓ Certificado digital encontrado y accesible\n" .
        "✓ Archivo CAF disponible con folios activos\n" .
        "✓ API Key configurada\n" .
        "✓ RUT emisor válido\n" .
        "✓ Todas las configuraciones correctas",
        'success'
    );
} else {
    $v->caja(
        "ERRORES DE CONFIGURACIÓN DETECTADOS:\n\n" .
        implode("\n", array_map(fn($e) => "✗ $e", $validacion['errores'])),
        'error'
    );
}

$v->pausar();

// ========================================
// DEMOSTRACIÓN DE INTERACTIVIDAD
// ========================================

$v->limpiar();
$v->titulo("FUNCIONES INTERACTIVAS", "═");

$v->subtitulo("Ejemplo de Inputs");

if ($v->confirmar("¿Quieres ver un ejemplo de input del usuario?", true)) {
    $nombre = $v->input("¿Cuál es tu nombre?", "Usuario");
    $v->mensaje('success', "¡Hola, $nombre!");

    echo "\n";

    $email = $v->input("¿Cuál es tu email?", "ejemplo@email.cl");
    $v->mensaje('info', "Email registrado: $email");
} else {
    $v->mensaje('info', 'Saltando ejemplo interactivo');
}

echo "\n";
$v->pausar();

// ========================================
// DEMOSTRACIÓN DE DASHBOARD
// ========================================

$v->limpiar();
$v->titulo("DASHBOARD DE ESTADÍSTICAS", "═");

$v->caja(
    "El sistema incluye un dashboard visual completo que muestra:\n\n" .
    "• Estadísticas generales del sistema\n" .
    "• Gráfico de barras de boletas por día\n" .
    "• Estado de folios (disponibles, usados, alertas)\n" .
    "• Track IDs recientes con su estado\n" .
    "• Registro de errores y advertencias\n" .
    "• Métricas de rendimiento\n" .
    "• Estado de componentes del sistema\n" .
    "• Alertas y recomendaciones automáticas",
    'info'
);

echo "\n";
$v->mensaje('info', 'Ejecuta: php dashboard-estadisticas.php para ver estadísticas en tiempo real');

echo "\n";
$v->pausar();

// ========================================
// FINAL
// ========================================

$v->limpiar();
$v->titulo("RESUMEN DE MEJORAS IMPLEMENTADAS", "═");

$v->seccion("🎨 Mejoras Visuales");

$mejoras = [
    "✓ Sistema de colores ANSI con soporte multi-plataforma",
    "✓ Emojis y símbolos Unicode para mejor UX",
    "✓ Barras de progreso animadas con porcentajes",
    "✓ Tablas formateadas con bordes",
    "✓ Cajas y secciones con estilos",
    "✓ Animaciones de carga (spinners)",
    "✓ Mensajes categorizados (success, error, warning, info)",
    "✓ Textos con formato (bold, dim, underline)",
];

foreach ($mejoras as $mejora) {
    echo "  " . $v->success($mejora) . "\n";
}

echo "\n";
$v->seccion("⚙️ Sistema de Configuración");

$config_features = [
    "✓ Configuración centralizada en config/settings.php",
    "✓ Soporte para variables de entorno",
    "✓ 9 categorías de configuración",
    "✓ Validación automática de configuraciones",
    "✓ Exportación a archivo .env",
    "✓ Detección automática de capacidades (BD, colores, etc.)",
    "✓ Panel interactivo de configuración",
    "✓ Tests de conexión integrados",
];

foreach ($config_features as $feature) {
    echo "  " . $v->success($feature) . "\n";
}

echo "\n";
$v->seccion("📊 Dashboard y Monitoreo");

$dashboard_features = [
    "✓ Dashboard visual con estadísticas en tiempo real",
    "✓ Gráficos ASCII de boletas por día",
    "✓ Tablas de folios con alertas automáticas",
    "✓ Monitoreo de Track IDs recientes",
    "✓ Registro y análisis de errores",
    "✓ Métricas de rendimiento",
    "✓ Estado de componentes del sistema",
    "✓ Recomendaciones automáticas",
];

foreach ($dashboard_features as $feature) {
    echo "  " . $v->success($feature) . "\n";
}

echo "\n";
$v->seccion("🚀 Scripts Disponibles");

echo "  " . $v->primary("• panel-configuracion.php", true) . " - Panel interactivo de configuración\n";
echo "  " . $v->primary("• dashboard-estadisticas.php", true) . " - Dashboard visual de estadísticas\n";
echo "  " . $v->primary("• demo-visuales.php", true) . " - Demostración de mejoras visuales (este script)\n";
echo "  " . $v->primary("• consultar-track-ids.php", true) . " - Consulta de Track IDs con formato mejorado\n";
echo "  " . $v->primary("• generar-boletas-variadas.php", true) . " - Generación con datos variados y progreso visual\n";

echo "\n";
$v->separador('═');
echo "\n";

$v->caja(
    "¡SISTEMA COMPLETO Y LISTO PARA USAR!\n\n" .
    "Todas las mejoras visuales y de configuración están implementadas y funcionando.\n" .
    "El sistema ahora ofrece una experiencia de usuario profesional con feedback visual claro.",
    'success'
);

echo "\n";
echo $v->dim("Demostración completada - " . date('Y-m-d H:i:s')) . "\n\n";
