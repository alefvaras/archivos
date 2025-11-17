<?php
/**
 * Configuración RCV para CERTIFICACIÓN - SÍ ENVIAR
 *
 * Esta es la configuración para ambiente de certificación SII.
 * En certificación, el RCV SÍ es requerido para pasar las pruebas.
 *
 * Para usar esta configuración:
 * 1. cp config-rcv.CERTIFICACION-ENVIAR.php config-rcv.php
 * 2. php enviar-rcv-certificacion.php
 *
 * Resultado: Se generará el XML Y se enviará al SII certificación.
 */

return [
    // HABILITADO: Sí enviar
    'envio_habilitado' => true,

    // Solo permitido en certificación
    'ambientes_permitidos' => ['certificacion'],

    // Generar XML siempre
    'generar_xml_siempre' => true,

    'directorio_rcv' => __DIR__ . '/rcv',
    'directorio_xmls' => __DIR__ . '/xmls',

    'alertas' => [
        'advertir_produccion' => true,
        'requiere_confirmacion' => false,
        'email_notificacion' => 'ale.fvaras@gmail.com',
    ],

    'log' => [
        'habilitar_log' => true,
        'archivo_log' => __DIR__ . '/rcv/rcv_log.txt',
    ],
];
