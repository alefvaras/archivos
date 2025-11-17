<?php
/**
 * Configuración RCV para PRODUCCIÓN - NO ENVIAR
 *
 * Esta es la configuración RECOMENDADA para producción con Boletas Electrónicas.
 * Desde 2024, el RCV de boletas NO es obligatorio en producción.
 *
 * Para usar esta configuración:
 * 1. cp config-rcv.PRODUCCION-NO-ENVIAR.php config-rcv.php
 * 2. php enviar-rcv-certificacion.php
 *
 * Resultado: Se generará el XML para respaldo pero NO se enviará al SII.
 */

return [
    // DESHABILITADO: No enviar nunca
    'envio_habilitado' => false,

    // Vacío: No permitido en ningún ambiente
    'ambientes_permitidos' => [],

    // Generar XML para respaldo
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
