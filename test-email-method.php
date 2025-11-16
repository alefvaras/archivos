#!/usr/bin/env php
<?php
/**
 * Test: Detectar método de envío de email disponible
 * Valida qué función de email usará el sistema
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== TEST: MÉTODOS DE ENVÍO DE EMAIL DISPONIBLES ===\n\n";

echo "🔍 Detectando funciones de email disponibles...\n\n";

$metodos = [];

// 1. Verificar MailPoet
if (function_exists('mailpoet_send_transactional_email')) {
    $metodos[] = [
        'nombre' => 'MailPoet',
        'funcion' => 'mailpoet_send_transactional_email()',
        'disponible' => true,
        'prioridad' => 1,
        'soporta_adjuntos' => true,
        'caracteristicas' => [
            'Envío transaccional',
            'Tracking de emails',
            'Mejor deliverability',
            'Soporte completo de adjuntos',
            'Logs y estadísticas'
        ]
    ];
} else {
    $metodos[] = [
        'nombre' => 'MailPoet',
        'funcion' => 'mailpoet_send_transactional_email()',
        'disponible' => false,
        'prioridad' => 1,
        'soporta_adjuntos' => true,
        'caracteristicas' => []
    ];
}

// 2. Verificar WordPress wp_mail
if (function_exists('wp_mail')) {
    $metodos[] = [
        'nombre' => 'WordPress wp_mail',
        'funcion' => 'wp_mail()',
        'disponible' => true,
        'prioridad' => 2,
        'soporta_adjuntos' => true,
        'caracteristicas' => [
            'Integrado en WordPress',
            'Usa PHPMailer internamente',
            'Soporte de adjuntos',
            'Configurable vía plugins',
            'Hooks disponibles'
        ]
    ];
} else {
    $metodos[] = [
        'nombre' => 'WordPress wp_mail',
        'funcion' => 'wp_mail()',
        'disponible' => false,
        'prioridad' => 2,
        'soporta_adjuntos' => true,
        'caracteristicas' => []
    ];
}

// 3. Verificar PHP mail (siempre disponible)
if (function_exists('mail')) {
    $metodos[] = [
        'nombre' => 'PHP mail()',
        'funcion' => 'mail()',
        'disponible' => true,
        'prioridad' => 3,
        'soporta_adjuntos' => false,
        'caracteristicas' => [
            'Función nativa de PHP',
            'Depende de configuración del servidor',
            'NO soporta adjuntos fácilmente',
            'Puede ir a spam',
            'Configuración limitada'
        ]
    ];
}

// Mostrar resultados
foreach ($metodos as $metodo) {
    $estado = $metodo['disponible'] ? '✅ DISPONIBLE' : '❌ NO DISPONIBLE';
    $adjuntos = $metodo['soporta_adjuntos'] ? '✅ SÍ' : '❌ NO';

    echo str_repeat('─', 60) . "\n";
    echo "Método #{$metodo['prioridad']}: {$metodo['nombre']}\n";
    echo str_repeat('─', 60) . "\n";
    echo "Estado: {$estado}\n";
    echo "Función: {$metodo['funcion']}\n";
    echo "Adjuntos: {$adjuntos}\n";

    if ($metodo['disponible'] && !empty($metodo['caracteristicas'])) {
        echo "\nCaracterísticas:\n";
        foreach ($metodo['caracteristicas'] as $car) {
            echo "  • {$car}\n";
        }
    }
    echo "\n";
}

// Determinar qué método se usará
echo str_repeat('=', 60) . "\n";
echo "MÉTODO QUE SE USARÁ EN TU SERVIDOR\n";
echo str_repeat('=', 60) . "\n\n";

$metodo_a_usar = null;
foreach ($metodos as $metodo) {
    if ($metodo['disponible']) {
        $metodo_a_usar = $metodo;
        break;
    }
}

if ($metodo_a_usar) {
    echo "🎯 Se usará: {$metodo_a_usar['nombre']}\n";
    echo "   Prioridad: #{$metodo_a_usar['prioridad']}\n";
    echo "   Adjuntos PDF: " . ($metodo_a_usar['soporta_adjuntos'] ? '✅ SOPORTADOS' : '❌ NO SOPORTADOS') . "\n\n";

    if (!$metodo_a_usar['soporta_adjuntos']) {
        echo "⚠️  ADVERTENCIA:\n";
        echo "   El método {$metodo_a_usar['nombre']} NO soporta adjuntos.\n";
        echo "   Los clientes recibirán el email sin el PDF de la boleta.\n";
        echo "   Solo verán la información en el cuerpo del email.\n\n";

        echo "💡 RECOMENDACIÓN:\n";
        echo "   Instala WordPress y MailPoet para enviar PDFs adjuntos.\n\n";
    }
} else {
    echo "❌ No hay ningún método de email disponible!\n\n";
}

// Información adicional
echo str_repeat('=', 60) . "\n";
echo "CONFIGURACIÓN DEL SERVIDOR\n";
echo str_repeat('=', 60) . "\n\n";

echo "PHP Version: " . PHP_VERSION . "\n";
echo "Sistema: " . PHP_OS . "\n";

// Verificar si estamos en CLI o web
echo "SAPI: " . php_sapi_name() . "\n";

// Verificar configuración de mail
if (ini_get('sendmail_path')) {
    echo "Sendmail path: " . ini_get('sendmail_path') . "\n";
} else {
    echo "Sendmail path: (no configurado)\n";
}

if (ini_get('SMTP')) {
    echo "SMTP Server: " . ini_get('SMTP') . "\n";
} else {
    echo "SMTP Server: (no configurado)\n";
}

echo "\n";

// Simulación de uso
echo str_repeat('=', 60) . "\n";
echo "EJEMPLO DE USO EN TU SISTEMA\n";
echo str_repeat('=', 60) . "\n\n";

echo "Con la configuración actual, cuando generes una boleta:\n\n";

if ($metodo_a_usar) {
    if ($metodo_a_usar['soporta_adjuntos']) {
        echo "✅ El cliente recibirá:\n";
        echo "   1. Email HTML con información de la boleta\n";
        echo "   2. PDF adjunto de la boleta\n";
        echo "   3. Formato profesional\n\n";

        echo "📧 Salida esperada:\n";
        echo "   \"📧 Email enviado vía {$metodo_a_usar['nombre']} a: cliente@ejemplo.cl\"\n";
        echo "   \"   Asunto: Boleta Electrónica N° 1890 - AKIBARA SPA\"\n";
        echo "   \"   Adjunto: PDF\"\n\n";
    } else {
        echo "⚠️  El cliente recibirá:\n";
        echo "   1. Email HTML con información de la boleta\n";
        echo "   2. SIN PDF adjunto (limitación del método)\n\n";

        echo "📧 Salida esperada:\n";
        echo "   \"📧 Email enviado vía {$metodo_a_usar['nombre']} a: cliente@ejemplo.cl\"\n";
        echo "   \"   Asunto: Boleta Electrónica N° 1890 - AKIBARA SPA\"\n";
        echo "   \"   ⚠️ Nota: Adjuntos no soportados\"\n\n";
    }
}

echo str_repeat('=', 60) . "\n";
echo "RECOMENDACIONES\n";
echo str_repeat('=', 60) . "\n\n";

if (function_exists('mailpoet_send_transactional_email')) {
    echo "✅ MailPoet está instalado - CONFIGURACIÓN ÓPTIMA\n";
    echo "   Tu servidor está perfectamente configurado.\n\n";
} elseif (function_exists('wp_mail')) {
    echo "✅ WordPress detectado - CONFIGURACIÓN BUENA\n";
    echo "   Para mejorar, considera instalar MailPoet:\n";
    echo "   1. Ir a WordPress Admin → Plugins → Añadir nuevo\n";
    echo "   2. Buscar 'MailPoet'\n";
    echo "   3. Instalar y activar\n";
    echo "   4. Configurar emails transaccionales\n\n";
} else {
    echo "⚠️  PHP mail() solamente - CONFIGURACIÓN BÁSICA\n";
    echo "   PROBLEMA: No podrás enviar PDFs adjuntos.\n\n";
    echo "   SOLUCIÓN:\n";
    echo "   1. Instalar WordPress en tu hosting\n";
    echo "   2. Instalar MailPoet plugin\n";
    echo "   3. Reiniciar este test\n\n";
    echo "   ALTERNATIVA:\n";
    echo "   - Guardar PDFs en servidor\n";
    echo "   - Enviar link de descarga en email\n";
    echo "   - Usar servicio SMTP externo (SendGrid, Mailgun)\n\n";
}

echo "=== FIN DEL TEST ===\n";
