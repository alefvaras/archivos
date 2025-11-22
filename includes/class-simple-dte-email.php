<?php
/**
 * Sistema de envío de boletas por email
 *
 * @package Simple_DTE
 */

if (!defined('ABSPATH')) {
    exit;
}

class Simple_DTE_Email {

    /**
     * Enviar boleta por email al cliente
     *
     * @param WC_Order $order Orden de WooCommerce
     * @param string $pdf_path Ruta al PDF de la boleta
     * @param array $opciones Opciones adicionales
     * @return bool|WP_Error True si se envió, WP_Error si falló
     */
    public static function enviar_boleta_cliente($order, $pdf_path = null, $opciones = array()) {
        if (!$order) {
            return new WP_Error('no_order', __('Orden no encontrada', 'simple-dte'));
        }

        // Obtener email del cliente
        $email_cliente = $order->get_billing_email();
        if (empty($email_cliente)) {
            return new WP_Error('no_email', __('El cliente no tiene email configurado', 'simple-dte'));
        }

        // Verificar que el PDF exista
        if ($pdf_path && !file_exists($pdf_path)) {
            $pdf_path = null;
        }

        // Obtener datos de la boleta
        $folio = $order->get_meta('_simple_dte_folio');
        $tipo_dte = $order->get_meta('_simple_dte_tipo');
        $fecha = $order->get_meta('_simple_dte_fecha_generacion');

        // Construir email
        $subject = self::get_subject($order, $folio);
        $message = self::get_message($order, $folio, $tipo_dte, $fecha);
        $headers = self::get_headers();

        // Adjuntar PDF si existe
        $attachments = array();
        if ($pdf_path) {
            $attachments[] = $pdf_path;
        }

        // Enviar email
        $enviado = wp_mail($email_cliente, $subject, $message, $headers, $attachments);

        if ($enviado) {
            // Registrar envío exitoso
            $order->add_order_note(
                sprintf(
                    __('✉️ Boleta N° %d enviada por email a: %s', 'simple-dte'),
                    $folio,
                    $email_cliente
                )
            );

            $order->update_meta_data('_simple_dte_email_enviado', 'yes');
            $order->update_meta_data('_simple_dte_email_fecha', current_time('mysql'));
            $order->save();

            Simple_DTE_Logger::info('Boleta enviada por email', array(
                'order_id' => $order->get_id(),
                'folio' => $folio,
                'email' => $email_cliente
            ), $order->get_id());

            return true;
        } else {
            Simple_DTE_Logger::error('Error al enviar boleta por email', array(
                'order_id' => $order->get_id(),
                'folio' => $folio,
                'email' => $email_cliente
            ), $order->get_id());

            return new WP_Error('email_failed', __('No se pudo enviar el email', 'simple-dte'));
        }
    }

    /**
     * Obtener asunto del email
     */
    private static function get_subject($order, $folio) {
        $razon_social = get_option('simple_dte_razon_social', get_bloginfo('name'));

        $subject = sprintf(
            __('Boleta Electrónica N° %d - %s', 'simple-dte'),
            $folio,
            $razon_social
        );

        return apply_filters('simple_dte_email_subject', $subject, $order, $folio);
    }

    /**
     * Obtener contenido del email
     */
    private static function get_message($order, $folio, $tipo_dte, $fecha) {
        $razon_social = get_option('simple_dte_razon_social', get_bloginfo('name'));
        $rut_emisor = get_option('simple_dte_rut_emisor', '');

        $cliente_nombre = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $order_total = wc_price($order->get_total());
        $fecha_formateada = date_i18n('d/m/Y', strtotime($fecha));

        $message = "Estimado/a {$cliente_nombre},\n\n";
        $message .= "Adjunto encontrará su Boleta Electrónica correspondiente a su compra.\n\n";
        $message .= "Detalles del documento:\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "• Emisor: {$razon_social}\n";
        $message .= "• RUT Emisor: {$rut_emisor}\n";
        $message .= "• Tipo: Boleta Electrónica\n";
        $message .= "• Folio: N° {$folio}\n";
        $message .= "• Fecha: {$fecha_formateada}\n";
        $message .= "• Monto Total: {$order_total}\n";
        $message .= "• Orden: #{$order->get_order_number()}\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "Este documento tiene validez tributaria según normativa del SII.\n\n";
        $message .= "Si tiene alguna consulta, no dude en contactarnos.\n\n";
        $message .= "Saludos cordiales,\n";
        $message .= $razon_social . "\n";

        return apply_filters('simple_dte_email_message', $message, $order, $folio);
    }

    /**
     * Obtener headers del email
     *
     * Prioridad de configuración:
     * 1. Configuración de WooCommerce (si existe)
     * 2. Configuración de Simple DTE
     * 3. Configuración por defecto de WordPress
     */
    private static function get_headers() {
        // Intentar obtener configuración de WooCommerce primero
        $from_name = WC()->mailer()->get_from_name();
        $from_email = WC()->mailer()->get_from_address();

        // Si WooCommerce no tiene configuración, usar Simple DTE
        if (empty($from_name)) {
            $from_name = get_option('simple_dte_razon_social', get_bloginfo('name'));
        }

        if (empty($from_email)) {
            $from_email = get_option('admin_email');
        }

        // Permitir override con filtros
        $from_email = apply_filters('simple_dte_from_email', $from_email);
        $from_name = apply_filters('simple_dte_from_name', $from_name);

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            sprintf('From: %s <%s>', $from_name, $from_email)
        );

        return apply_filters('simple_dte_email_headers', $headers);
    }

    /**
     * Enviar email de prueba
     *
     * @param string $email_destino Email de destino
     * @return bool|WP_Error
     */
    public static function enviar_email_prueba($email_destino) {
        if (empty($email_destino) || !is_email($email_destino)) {
            return new WP_Error('invalid_email', __('Email inválido', 'simple-dte'));
        }

        $razon_social = get_option('simple_dte_razon_social', get_bloginfo('name'));
        $rut_emisor = get_option('simple_dte_rut_emisor', '');

        $subject = '✅ Test de Email - Simple DTE';

        $message = "Este es un email de prueba del sistema Simple DTE.\n\n";
        $message .= "Configuración actual:\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "• Emisor: {$razon_social}\n";
        $message .= "• RUT: {$rut_emisor}\n";
        $message .= "• Fecha: " . date_i18n('d/m/Y H:i:s') . "\n";
        $message .= "• Servidor: " . $_SERVER['SERVER_NAME'] . "\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "Si recibe este email, significa que el sistema de envío está funcionando correctamente.\n\n";
        $message .= "Las boletas electrónicas se enviarán automáticamente a los clientes después de cada compra.\n\n";
        $message .= "Saludos,\n";
        $message .= "Sistema Simple DTE\n";

        $headers = self::get_headers();

        $enviado = wp_mail($email_destino, $subject, $message, $headers);

        if ($enviado) {
            Simple_DTE_Logger::info('Email de prueba enviado', array(
                'email' => $email_destino
            ));
            return true;
        } else {
            Simple_DTE_Logger::error('Error al enviar email de prueba', array(
                'email' => $email_destino
            ));
            return new WP_Error('email_failed', __('No se pudo enviar el email de prueba', 'simple-dte'));
        }
    }

    /**
     * Configurar SMTP
     *
     * Prioridad de configuración:
     * 1. MailPoet (si está instalado y configurado)
     * 2. Simple DTE (si está habilitado)
     * 3. WooCommerce/WordPress por defecto (wp_mail nativo)
     */
    public static function configure_smtp() {
        // Si MailPoet está activo y configurado, él manejará el SMTP
        if (self::is_mailpoet_smtp_active()) {
            Simple_DTE_Logger::info('SMTP: Usando configuración de MailPoet');
            return;
        }

        // Si Simple DTE tiene SMTP habilitado, usarlo
        $smtp_enabled = get_option('simple_dte_smtp_enabled', false);
        if ($smtp_enabled) {
            add_action('phpmailer_init', array(__CLASS__, 'configure_phpmailer'), 10);
            Simple_DTE_Logger::info('SMTP: Usando configuración de Simple DTE');
            return;
        }

        // Si no hay SMTP configurado, usar wp_mail() nativo
        Simple_DTE_Logger::info('SMTP: Usando wp_mail() nativo de WordPress');
    }

    /**
     * Verificar si MailPoet está activo y configurado con SMTP
     *
     * @return bool
     */
    private static function is_mailpoet_smtp_active() {
        // Verificar si MailPoet está instalado
        if (!class_exists('\MailPoet\Config\Env')) {
            return false;
        }

        // Verificar si tiene configuración SMTP
        $mailpoet_settings = get_option('mailpoet_settings');

        if (!$mailpoet_settings || !is_array($mailpoet_settings)) {
            return false;
        }

        // MailPoet 3.x guarda la configuración en mailpoet_settings['mta']
        if (isset($mailpoet_settings['mta']['method']) && $mailpoet_settings['mta']['method'] === 'SMTP') {
            return !empty($mailpoet_settings['mta']['host']);
        }

        return false;
    }

    /**
     * Obtener información de configuración SMTP detectada
     *
     * @return array
     */
    public static function get_smtp_config_info() {
        $info = array(
            'source' => 'none',
            'configured' => false,
            'details' => array()
        );

        // Verificar MailPoet
        if (self::is_mailpoet_smtp_active()) {
            $mailpoet_settings = get_option('mailpoet_settings');
            $smtp = $mailpoet_settings['mta'];

            $info['source'] = 'mailpoet';
            $info['configured'] = true;
            $info['details'] = array(
                'host' => $smtp['host'] ?? '',
                'port' => $smtp['port'] ?? '',
                'secure' => $smtp['encryption'] ?? '',
                'username' => $smtp['login'] ?? ''
            );
            return $info;
        }

        // Verificar Simple DTE
        if (get_option('simple_dte_smtp_enabled', false)) {
            $info['source'] = 'simple_dte';
            $info['configured'] = true;
            $info['details'] = array(
                'host' => get_option('simple_dte_smtp_host', ''),
                'port' => get_option('simple_dte_smtp_port', ''),
                'secure' => get_option('simple_dte_smtp_secure', ''),
                'username' => get_option('simple_dte_smtp_username', '')
            );
            return $info;
        }

        // WooCommerce/WordPress nativo
        $info['source'] = 'wordpress';
        $info['configured'] = true;
        $info['details'] = array(
            'method' => 'wp_mail()',
            'from_name' => WC()->mailer()->get_from_name(),
            'from_email' => WC()->mailer()->get_from_address()
        );

        return $info;
    }

    /**
     * Configurar PHPMailer con SMTP de Simple DTE
     */
    public static function configure_phpmailer($phpmailer) {
        $smtp_host = get_option('simple_dte_smtp_host', '');
        $smtp_port = get_option('simple_dte_smtp_port', '587');
        $smtp_secure = get_option('simple_dte_smtp_secure', 'tls');
        $smtp_auth = get_option('simple_dte_smtp_auth', true);
        $smtp_username = get_option('simple_dte_smtp_username', '');
        $smtp_password = get_option('simple_dte_smtp_password', '');

        if (empty($smtp_host)) {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host = $smtp_host;
        $phpmailer->Port = $smtp_port;
        $phpmailer->SMTPSecure = $smtp_secure;

        if ($smtp_auth) {
            $phpmailer->SMTPAuth = true;
            $phpmailer->Username = $smtp_username;
            $phpmailer->Password = $smtp_password;
        }

        // Debug (solo si está habilitado)
        if (get_option('simple_dte_debug', false)) {
            $phpmailer->SMTPDebug = 2;
        }
    }
}
