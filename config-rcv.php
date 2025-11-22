<?php
/**
 * Configuración de RCV (Registro de Compras y Ventas)
 *
 * IMPORTANTE: En PRODUCCIÓN, el RCV de boletas NO es obligatorio desde 2024.
 *             Solo se requiere en CERTIFICACIÓN para pasar las pruebas del SII.
 *
 * Edita este archivo para cambiar el comportamiento del envío de RCV.
 */

return [
    /**
     * Habilitar envío de RCV
     *
     * true = El script puede enviar RCV al SII
     * false = El script NUNCA enviará RCV (solo generará el XML)
     */
    'envio_habilitado' => true,

    /**
     * Ambientes permitidos para envío
     *
     * Opciones:
     * - ['certificacion'] = Solo permite envío en certificación
     * - ['produccion'] = Solo permite envío en producción
     * - ['certificacion', 'produccion'] = Permite envío en ambos
     * - [] = No permite envío en ningún ambiente
     *
     * RECOMENDADO: ['certificacion'] para boletas electrónicas
     */
    'ambientes_permitidos' => ['certificacion'],

    /**
     * Generar XML aunque no se envíe
     *
     * true = Siempre genera el XML para respaldo
     * false = No genera XML si no se va a enviar
     */
    'generar_xml_siempre' => true,

    /**
     * Directorio para guardar XMLs generados
     */
    'directorio_rcv' => __DIR__ . '/rcv',

    /**
     * Directorio de XMLs de boletas
     */
    'directorio_xmls' => __DIR__ . '/xmls',

    /**
     * Configuración de alertas
     */
    'alertas' => [
        /**
         * Mostrar advertencia si se intenta enviar en producción
         */
        'advertir_produccion' => true,

        /**
         * Requerir confirmación antes de enviar
         */
        'requiere_confirmacion' => false,

        /**
         * Email para notificaciones (opcional)
         */
        'email_notificacion' => 'ale.fvaras@gmail.com',
    ],

    /**
     * Configuración de registro/log
     */
    'log' => [
        /**
         * Guardar log de envíos
         */
        'habilitar_log' => true,

        /**
         * Archivo de log
         */
        'archivo_log' => __DIR__ . '/rcv/rcv_log.txt',
    ],

    /**
     * Notas:
     *
     * - Si 'envio_habilitado' = false, el script solo generará el XML pero NO lo enviará
     * - Si 'ambientes_permitidos' no incluye el ambiente actual, el envío será bloqueado
     * - Si 'advertir_produccion' = true, se mostrará advertencia antes de enviar en producción
     *
     * Configuración recomendada para PRODUCCIÓN con Boletas Electrónicas:
     *
     * 'envio_habilitado' => false,  // No enviar nunca
     * 'generar_xml_siempre' => true,  // Pero generar XML para respaldo
     *
     * Configuración recomendada para CERTIFICACIÓN:
     *
     * 'envio_habilitado' => true,
     * 'ambientes_permitidos' => ['certificacion'],
     */
];
