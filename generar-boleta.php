#!/usr/bin/env php
<?php
/**
 * Sistema de Generación de Boletas Electrónicas
 * Genera, envía al SII y opcionalmente envía por email al cliente
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Cargar generador de PDF
require_once(__DIR__ . '/lib/generar-pdf-boleta.php');

// ========================================
// CONFIGURACIÓN
// ========================================
define('API_KEY', '9794-N370-6392-6913-8052');
define('CERT_PATH', __DIR__ . '/16694181-4.pfx');
define('CERT_PASSWORD', '5605');
define('CAF_PATH', __DIR__ . '/FoliosSII78274225391889202511161321.xml');
define('RUT_EMISOR', '78274225-6');
define('RAZON_SOCIAL', 'AKIBARA SPA');
define('AMBIENTE', 'certificacion'); // certificacion o produccion

// URLs de Simple API
$API_BASE = 'https://api.simpleapi.cl';

// ========================================
// OPCIONES CONFIGURABLES
// ========================================
$CONFIG = [
    'envio_automatico_email' => false,  // true = enviar email automáticamente
    'consulta_automatica' => true,      // true = consultar estado automáticamente
    'espera_consulta_segundos' => 5,    // Segundos a esperar antes de consultar
    'guardar_xml' => true,               // Guardar XMLs generados
    'directorio_xml' => '/tmp',          // Directorio para guardar XMLs
    'email_remitente' => 'boletas@akibara.cl',  // Email remitente
    'adjuntar_pdf' => true,              // true = adjuntar PDF de la boleta
    'adjuntar_xml' => false,             // true = adjuntar XML de la boleta
];

// ========================================
// FUNCIONES AUXILIARES
// ========================================

/**
 * Leer y parsear archivo CAF
 */
function leer_caf($caf_path) {
    if (!file_exists($caf_path)) {
        throw new Exception("Archivo CAF no encontrado: $caf_path");
    }

    $caf_xml = file_get_contents($caf_path);
    $caf = simplexml_load_string($caf_xml);
    $da = $caf->CAF->DA;

    return [
        'xml' => $caf_xml,
        'folio_desde' => (int) ((string) $da->RNG->D),
        'folio_hasta' => (int) ((string) $da->RNG->H),
        'tipo_dte' => (int) ((string) $da->TD),
    ];
}

/**
 * Obtener siguiente folio disponible
 */
function obtener_siguiente_folio($caf_info) {
    // En producción, esto debería consultar una base de datos
    // Por ahora, leer de un archivo de control
    $control_file = __DIR__ . '/folios_usados.txt';

    if (!file_exists($control_file)) {
        file_put_contents($control_file, $caf_info['folio_desde']);
        return $caf_info['folio_desde'];
    }

    $ultimo_folio = (int) file_get_contents($control_file);
    $siguiente_folio = $ultimo_folio + 1;

    // Verificar si quedan folios disponibles
    if ($siguiente_folio > $caf_info['folio_hasta']) {
        throw new Exception("No hay más folios disponibles en el CAF actual. Por favor, genere un nuevo CAF en el portal del SII.");
    }

    // Advertir si quedan pocos folios (menos de 10)
    $folios_restantes = $caf_info['folio_hasta'] - $siguiente_folio + 1;
    if ($folios_restantes <= 10) {
        echo "  ⚠️  ADVERTENCIA: Quedan solo {$folios_restantes} folios disponibles\n";
        echo "     Por favor, genere un nuevo CAF en el portal del SII pronto\n\n";
    }

    file_put_contents($control_file, $siguiente_folio);
    return $siguiente_folio;
}

/**
 * Solicitar más folios automáticamente (preparación para futura integración)
 * NOTA: Actualmente el SII NO permite solicitar folios vía API
 * Los folios deben solicitarse manualmente en https://mipyme.sii.cl
 */
function verificar_y_solicitar_folios($caf_info, $cantidad = 50) {
    $folios_restantes = $caf_info['folio_hasta'] - $caf_info['folio_desde'] + 1;

    // Buscar si hay otros archivos CAF disponibles
    $caf_files = glob(__DIR__ . '/FoliosSII*.xml');

    echo "  📋 Archivos CAF disponibles:\n";
    foreach ($caf_files as $idx => $file) {
        $temp_caf = simplexml_load_string(file_get_contents($file));
        $temp_desde = (int) ((string) $temp_caf->CAF->DA->RNG->D);
        $temp_hasta = (int) ((string) $temp_caf->CAF->DA->RNG->H);
        $temp_disponibles = $temp_hasta - $temp_desde + 1;

        $es_actual = (basename($file) === basename(CAF_PATH)) ? ' (ACTUAL)' : '';
        echo "     " . ($idx + 1) . ". " . basename($file) . " - Folios: {$temp_desde}-{$temp_hasta} ({$temp_disponibles} disponibles){$es_actual}\n";
    }

    if ($folios_restantes < 10) {
        echo "\n  ⚠️  ACCIÓN REQUERIDA:\n";
        echo "     Quedan menos de 10 folios en el CAF actual\n";
        echo "     Por favor, siga estos pasos:\n";
        echo "     1. Ingrese a https://mipyme.sii.cl\n";
        echo "     2. Vaya a 'Folios' → 'Generar Folios'\n";
        echo "     3. Solicite {$cantidad} folios para DTE tipo 39 (Boleta Electrónica)\n";
        echo "     4. Descargue el archivo CAF y guárdelo en: " . __DIR__ . "\n";
        echo "     5. Actualice CAF_PATH en el script\n\n";

        return false;
    }

    return true;
}

/**
 * Generar DTE vía Simple API
 */
function generar_dte($documento, $api_base, $caf_xml) {
    $boundary = '----WebKitFormBoundary' . md5(time());
    $eol = "\r\n";

    // Construir multipart/form-data
    $body = '';

    // Parte 1: input (JSON)
    $body .= '--' . $boundary . $eol;
    $body .= 'Content-Disposition: form-data; name="input"' . $eol . $eol;
    $body .= json_encode($documento) . $eol;

    // Parte 2: certificado
    $body .= '--' . $boundary . $eol;
    $body .= 'Content-Disposition: form-data; name="files"; filename="' . basename(CERT_PATH) . '"' . $eol;
    $body .= 'Content-Type: application/x-pkcs12' . $eol . $eol;
    $body .= file_get_contents(CERT_PATH) . $eol;

    // Parte 3: CAF
    $body .= '--' . $boundary . $eol;
    $body .= 'Content-Disposition: form-data; name="files2"; filename="' . basename(CAF_PATH) . '"' . $eol;
    $body .= 'Content-Type: text/xml' . $eol . $eol;
    $body .= $caf_xml . $eol;

    // Parte 4: password
    $body .= '--' . $boundary . $eol;
    $body .= 'Content-Disposition: form-data; name="password"' . $eol . $eol;
    $body .= CERT_PASSWORD . $eol;

    $body .= '--' . $boundary . '--' . $eol;

    // Hacer request
    $ch = curl_init($api_base . '/api/v1/dte/generar');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . API_KEY,
            'Content-Type: multipart/form-data; boundary=' . $boundary,
            'Content-Length: ' . strlen($body)
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
        CURLOPT_TIMEOUT => 60,
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        throw new Exception("Error CURL: $curl_error");
    }

    if ($http_code != 200) {
        throw new Exception("Error HTTP $http_code: $response");
    }

    if (strpos($response, '<?xml') === false) {
        throw new Exception("La respuesta no es XML válido");
    }

    return $response;
}

/**
 * Generar sobre de envío firmado
 */
function generar_sobre($dte_xml, $api_base) {
    $boundary = '----WebKitFormBoundary' . md5(time() . 'generar');
    $eol = "\r\n";
    $body = '';

    $sobre_config = [
        'Certificado' => [
            'Rut' => '16694181-4',
            'Password' => CERT_PASSWORD
        ],
        'Caratula' => [
            'RutEmisor' => RUT_EMISOR,
            'RutReceptor' => '60803000-K',
            'FechaResolucion' => date('Y-m-d'),
            'NumeroResolucion' => 0
        ]
    ];

    $body .= '--' . $boundary . $eol;
    $body .= 'Content-Disposition: form-data; name="input"' . $eol . $eol;
    $body .= json_encode($sobre_config) . $eol;

    // Certificado
    $body .= '--' . $boundary . $eol;
    $body .= 'Content-Disposition: form-data; name="files"; filename="' . basename(CERT_PATH) . '"' . $eol;
    $body .= 'Content-Type: application/x-pkcs12' . $eol . $eol;
    $body .= file_get_contents(CERT_PATH) . $eol;

    // DTE XML
    $body .= '--' . $boundary . $eol;
    $body .= 'Content-Disposition: form-data; name="files"; filename="boleta.xml"' . $eol;
    $body .= 'Content-Type: text/xml' . $eol . $eol;
    $body .= $dte_xml . $eol;

    $body .= '--' . $boundary . '--' . $eol;

    $ch = curl_init($api_base . '/api/v1/envio/generar');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . API_KEY,
            'Content-Type: multipart/form-data; boundary=' . $boundary,
            'Content-Length: ' . strlen($body)
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
        CURLOPT_TIMEOUT => 60
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code != 200) {
        throw new Exception("Error al generar sobre: HTTP $http_code");
    }

    return $response;
}

/**
 * Enviar sobre al SII
 */
function enviar_sii($sobre_xml, $api_base) {
    $boundary = '----WebKitFormBoundary' . md5(time() . 'send');
    $eol = "\r\n";
    $body = '';

    $envio_config = [
        'Certificado' => [
            'Rut' => '16694181-4',
            'Password' => CERT_PASSWORD
        ],
        'Ambiente' => 0,  // 0 = Certificación, 1 = Producción
        'Tipo' => 2       // 2 = EnvioBoleta
    ];

    $body .= '--' . $boundary . $eol;
    $body .= 'Content-Disposition: form-data; name="input"' . $eol . $eol;
    $body .= json_encode($envio_config) . $eol;

    // Certificado
    $body .= '--' . $boundary . $eol;
    $body .= 'Content-Disposition: form-data; name="files"; filename="' . basename(CERT_PATH) . '"' . $eol;
    $body .= 'Content-Type: application/x-pkcs12' . $eol . $eol;
    $body .= file_get_contents(CERT_PATH) . $eol;

    // Sobre XML
    $body .= '--' . $boundary . $eol;
    $body .= 'Content-Disposition: form-data; name="files"; filename="sobre.xml"' . $eol;
    $body .= 'Content-Type: text/xml' . $eol . $eol;
    $body .= $sobre_xml . $eol;

    $body .= '--' . $boundary . '--' . $eol;

    $ch = curl_init($api_base . '/api/v1/envio/enviar');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . API_KEY,
            'Content-Type: multipart/form-data; boundary=' . $boundary,
            'Content-Length: ' . strlen($body)
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
        CURLOPT_TIMEOUT => 90
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code != 200) {
        throw new Exception("Error al enviar al SII: HTTP $http_code - $response");
    }

    $result = json_decode($response, true);

    if (!$result) {
        throw new Exception("Respuesta inválida del SII");
    }

    // Extraer Track ID
    $track_id = null;
    if (isset($result['trackId'])) {
        $track_id = $result['trackId'];
    } elseif (isset($result['data']['track_id'])) {
        $track_id = $result['data']['track_id'];
    }

    return [
        'track_id' => $track_id,
        'respuesta' => $result
    ];
}

/**
 * Consultar estado del envío
 */
function consultar_estado($track_id, $api_base) {
    $boundary = '----WebKitFormBoundary' . md5(time() . 'consulta');
    $eol = "\r\n";
    $body = '';

    $consulta = [
        'Certificado' => [
            'Rut' => '16694181-4',
            'Password' => CERT_PASSWORD
        ],
        'RutEmpresa' => RUT_EMISOR,
        'TrackId' => (int)$track_id,
        'Ambiente' => 0,
        'ServidorBoletaREST' => true
    ];

    $body .= '--' . $boundary . $eol;
    $body .= 'Content-Disposition: form-data; name="input"' . $eol . $eol;
    $body .= json_encode($consulta) . $eol;

    // Certificado
    $body .= '--' . $boundary . $eol;
    $body .= 'Content-Disposition: form-data; name="files"; filename="' . basename(CERT_PATH) . '"' . $eol;
    $body .= 'Content-Type: application/x-pkcs12' . $eol . $eol;
    $body .= file_get_contents(CERT_PATH) . $eol;

    $body .= '--' . $boundary . '--' . $eol;

    $ch = curl_init($api_base . '/api/v1/consulta/envio');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . API_KEY,
            'Content-Type: multipart/form-data; boundary=' . $boundary,
            'Content-Length: ' . strlen($body)
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code != 200) {
        return null;
    }

    return json_decode($response, true);
}

/**
 * Enviar boleta por email usando MailPoet
 */
function enviar_email($destinatario, $dte_xml, $datos_boleta, $config) {
    // Parsear XML para obtener datos
    $xml = simplexml_load_string($dte_xml);
    $folio = (string) $xml->Documento->Encabezado->IdDoc->Folio;
    $fecha = (string) $xml->Documento->Encabezado->IdDoc->FchEmis;
    $total = (string) $xml->Documento->Encabezado->Totales->MntTotal;

    // Obtener nombre del cliente
    $nombre_cliente = isset($datos_boleta['Documento']['Encabezado']['Receptor']['RazonSocial'])
        ? $datos_boleta['Documento']['Encabezado']['Receptor']['RazonSocial']
        : 'Cliente';

    // Preparar adjuntos según configuración
    $attachments = [];

    // Adjuntar XML si está configurado
    if (!empty($config['adjuntar_xml'])) {
        $xml_filename = "boleta_{$folio}.xml";
        $xml_path = sys_get_temp_dir() . '/' . $xml_filename;
        file_put_contents($xml_path, $dte_xml);
        $attachments[] = $xml_path;
    }

    // Adjuntar PDF si está configurado
    if (!empty($config['adjuntar_pdf'])) {
        $pdf_filename = "boleta_{$folio}.pdf";
        $pdf_path = sys_get_temp_dir() . '/' . $pdf_filename;

        // Generar PDF
        generar_pdf_boleta($datos_boleta, $dte_xml, $pdf_path);
        $attachments[] = $pdf_path;
    }

    // Asunto
    $asunto = "Boleta Electrónica N° {$folio} - " . RAZON_SOCIAL;

    // Mensaje HTML
    $mensaje = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; }
            .content { background-color: #f9f9f9; padding: 20px; margin-top: 20px; }
            .details { background-color: white; padding: 15px; margin: 10px 0; border-left: 4px solid #4CAF50; }
            .footer { text-align: center; margin-top: 20px; color: #777; font-size: 12px; }
            table { width: 100%; border-collapse: collapse; }
            td { padding: 8px 0; }
            .label { font-weight: bold; color: #555; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>Boleta Electrónica</h2>
                <p>Folio N° ' . $folio . '</p>
            </div>

            <div class="content">
                <p>Estimado/a <strong>' . htmlspecialchars($nombre_cliente) . '</strong>,</p>
                <p>Adjunto encontrará su Boleta Electrónica con los siguientes datos:</p>

                <div class="details">
                    <table>
                        <tr>
                            <td class="label">Folio:</td>
                            <td>' . $folio . '</td>
                        </tr>
                        <tr>
                            <td class="label">Fecha de Emisión:</td>
                            <td>' . date('d/m/Y', strtotime($fecha)) . '</td>
                        </tr>
                        <tr>
                            <td class="label">Total:</td>
                            <td><strong>$' . number_format($total, 0, ',', '.') . '</strong></td>
                        </tr>
                        <tr>
                            <td class="label">Emisor:</td>
                            <td>' . RAZON_SOCIAL . '</td>
                        </tr>
                        <tr>
                            <td class="label">RUT Emisor:</td>
                            <td>' . RUT_EMISOR . '</td>
                        </tr>
                    </table>
                </div>

                <p>Este documento tributario electrónico ha sido generado y timbrado por el Servicio de Impuestos Internos (SII).</p>
                <p>Gracias por su preferencia.</p>
            </div>

            <div class="footer">
                <p>' . RAZON_SOCIAL . ' - ' . RUT_EMISOR . '</p>
                <p>Este es un email automático, por favor no responder.</p>
            </div>
        </div>
    </body>
    </html>
    ';

    // Intentar enviar con MailPoet si está disponible
    if (function_exists('mailpoet_send_transactional_email')) {
        // MailPoet 3.x transactional email
        try {
            $result = mailpoet_send_transactional_email([
                'to' => $destinatario,
                'subject' => $asunto,
                'body' => $mensaje,
                'from_name' => RAZON_SOCIAL,
                'from_email' => $config['email_remitente'],
                'attachments' => $attachments
            ]);

            echo "  📧 Email enviado vía MailPoet a: {$destinatario}\n";
            echo "     Asunto: {$asunto}\n";
            if (!empty($config['adjuntar_pdf'])) {
                echo "     Adjunto: PDF\n";
            }
            if (!empty($config['adjuntar_xml'])) {
                echo "     Adjunto: XML\n";
            }

            // Limpiar archivos temporales
            foreach ($attachments as $file) {
                @unlink($file);
            }
            return true;

        } catch (Exception $e) {
            echo "  ⚠️  Error MailPoet: " . $e->getMessage() . "\n";
            echo "  🔄 Intentando con wp_mail()...\n";
        }
    }

    // Fallback: usar wp_mail() si está disponible (WordPress)
    if (function_exists('wp_mail')) {
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . RAZON_SOCIAL . ' <' . $config['email_remitente'] . '>'
        ];

        $result = wp_mail($destinatario, $asunto, $mensaje, $headers, $attachments);

        if ($result) {
            echo "  📧 Email enviado vía wp_mail() a: {$destinatario}\n";
            echo "     Asunto: {$asunto}\n";
            if (!empty($config['adjuntar_pdf'])) {
                echo "     Adjunto: PDF\n";
            }
            if (!empty($config['adjuntar_xml'])) {
                echo "     Adjunto: XML\n";
            }
        } else {
            echo "  ❌ Error al enviar email vía wp_mail()\n";
        }

        // Limpiar archivos temporales
        foreach ($attachments as $file) {
            @unlink($file);
        }
        return $result;
    }

    // Fallback final: mail() de PHP (no recomendado para producción)
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . RAZON_SOCIAL . " <{$config['email_remitente']}>\r\n";

    $result = mail($destinatario, $asunto, $mensaje, $headers);

    if ($result) {
        echo "  📧 Email enviado vía mail() a: {$destinatario}\n";
        echo "     Asunto: {$asunto}\n";
        echo "     ⚠️  Nota: Adjuntos no soportados (usar MailPoet o wp_mail para adjuntos)\n";
    } else {
        echo "  ❌ Error al enviar email\n";
    }

    // Limpiar archivos temporales
    foreach ($attachments as $file) {
        @unlink($file);
    }
    return $result;
}

// ========================================
// FUNCIÓN PRINCIPAL
// ========================================

/**
 * Generar boleta electrónica completa
 */
function generar_boleta($datos_cliente, $items, $config, $api_base) {
    global $CONFIG;

    echo "\n=== GENERANDO BOLETA ELECTRÓNICA ===\n\n";

    // 1. Leer CAF
    echo "📋 Paso 1: Leyendo CAF...\n";
    $caf_info = leer_caf(CAF_PATH);
    echo "  ✓ CAF tipo {$caf_info['tipo_dte']}, folios {$caf_info['folio_desde']}-{$caf_info['folio_hasta']}\n";

    // Verificar folios disponibles
    verificar_y_solicitar_folios($caf_info, 50);
    echo "\n";

    // 2. Obtener folio
    echo "📝 Paso 2: Obteniendo folio...\n";
    $folio = obtener_siguiente_folio($caf_info);
    echo "  ✓ Folio asignado: {$folio}\n\n";

    // 3. Calcular totales
    echo "💰 Paso 3: Calculando totales...\n";
    $total_con_iva = 0;
    foreach ($items as $item) {
        $total_con_iva += $item['precio'] * $item['cantidad'];
    }
    $neto = round($total_con_iva / 1.19);
    $iva = $total_con_iva - $neto;
    echo "  Neto: \${$neto}\n";
    echo "  IVA: \${$iva}\n";
    echo "  Total: \${$total_con_iva}\n\n";

    // 4. Construir documento
    echo "📄 Paso 4: Construyendo documento...\n";
    $detalles = [];
    foreach ($items as $idx => $item) {
        $detalles[] = [
            'IndicadorExento' => 0,
            'Nombre' => $item['nombre'],
            'Descripcion' => $item['descripcion'] ?? '',
            'Cantidad' => $item['cantidad'],
            'UnidadMedida' => $item['unidad'] ?? 'un',
            'Precio' => $item['precio'],
            'Descuento' => $item['descuento'] ?? 0,
            'Recargo' => $item['recargo'] ?? 0,
            'MontoItem' => $item['precio'] * $item['cantidad']
        ];
    }

    $documento = [
        'Documento' => [
            'Encabezado' => [
                'IdentificacionDTE' => [
                    'TipoDTE' => 39,
                    'Folio' => $folio,
                    'FechaEmision' => date('Y-m-d'),
                    'IndicadorServicio' => 3
                ],
                'Emisor' => [
                    'Rut' => RUT_EMISOR,
                    'RazonSocialBoleta' => RAZON_SOCIAL,
                    'GiroBoleta' => 'Comercio minorista de coleccionables',
                    'DireccionOrigen' => 'BARTOLO SOTO 3700 DP 1402 PISO 14',
                    'ComunaOrigen' => 'San Miguel'
                ],
                'Receptor' => [
                    'Rut' => $datos_cliente['rut'],
                    'RazonSocial' => $datos_cliente['razon_social'],
                    'Direccion' => $datos_cliente['direccion'] ?? 'Santiago',
                    'Comuna' => $datos_cliente['comuna'] ?? 'Santiago'
                ],
                'Totales' => [
                    'MontoNeto' => $neto,
                    'IVA' => $iva,
                    'MontoTotal' => $total_con_iva
                ]
            ],
            'Detalles' => $detalles
        ],
        'Certificado' => [
            'Rut' => '16694181-4',
            'Password' => CERT_PASSWORD
        ]
    ];

    echo "  ✓ Documento construido con " . count($detalles) . " items\n\n";

    // 5. Generar DTE
    echo "🔄 Paso 5: Generando DTE firmado...\n";
    $dte_xml = generar_dte($documento, $api_base, $caf_info['xml']);
    echo "  ✓ DTE generado (" . strlen($dte_xml) . " bytes)\n\n";

    // 6. Guardar XML si está configurado
    if ($CONFIG['guardar_xml']) {
        $xml_path = $CONFIG['directorio_xml'] . "/boleta_{$folio}.xml";
        file_put_contents($xml_path, $dte_xml);
        echo "  💾 XML guardado en: {$xml_path}\n\n";
    }

    // 7. Generar sobre
    echo "📦 Paso 6: Generando sobre de envío...\n";
    $sobre_xml = generar_sobre($dte_xml, $api_base);
    echo "  ✓ Sobre generado\n\n";

    // 8. Enviar al SII
    echo "📤 Paso 7: Enviando al SII...\n";
    $resultado_sii = enviar_sii($sobre_xml, $api_base);
    echo "  ✓ Enviado al SII\n";
    echo "  Track ID: {$resultado_sii['track_id']}\n\n";

    // 9. Consultar estado si está configurado
    if ($CONFIG['consulta_automatica']) {
        echo "🔍 Paso 8: Consultando estado...\n";
        echo "  ⏳ Esperando {$CONFIG['espera_consulta_segundos']} segundos...\n";
        sleep($CONFIG['espera_consulta_segundos']);

        $estado = consultar_estado($resultado_sii['track_id'], $api_base);

        if ($estado) {
            echo "  ✓ Estado: {$estado['estado']}\n";

            if (isset($estado['estadistica']) && count($estado['estadistica']) > 0) {
                $stats = $estado['estadistica'][0];
                echo "  Aceptados: {$stats['aceptados']}, ";
                echo "Rechazados: {$stats['rechazados']}, ";
                echo "Reparos: {$stats['reparos']}\n";
            }

            if (isset($estado['detalles']) && count($estado['detalles']) > 0) {
                foreach ($estado['detalles'] as $detalle) {
                    if (isset($detalle['errores']) && count($detalle['errores']) > 0) {
                        echo "  ⚠️  Errores:\n";
                        foreach ($detalle['errores'] as $error) {
                            echo "     - [{$error['codigo']}] {$error['descripcion']}\n";
                        }
                    }
                }
            }
        } else {
            echo "  ℹ️  Estado aún no disponible\n";
        }
        echo "\n";
    }

    // 10. Enviar email si está configurado
    if ($CONFIG['envio_automatico_email'] && !empty($datos_cliente['email'])) {
        echo "📧 Paso 9: Enviando email al cliente...\n";
        enviar_email($datos_cliente['email'], $dte_xml, $documento, $CONFIG);
        echo "\n";
    }

    echo "=== BOLETA GENERADA EXITOSAMENTE ===\n";
    echo "Folio: {$folio}\n";
    echo "Total: \${$total_con_iva}\n";
    echo "Track ID: {$resultado_sii['track_id']}\n\n";

    return [
        'folio' => $folio,
        'total' => $total_con_iva,
        'track_id' => $resultado_sii['track_id'],
        'dte_xml' => $dte_xml,
        'estado' => $estado ?? null
    ];
}

// ========================================
// EJEMPLO DE USO
// ========================================

if (php_sapi_name() === 'cli') {
    echo "=== SISTEMA DE BOLETAS ELECTRÓNICAS ===\n";
    echo "Ambiente: " . AMBIENTE . "\n";
    echo "Emisor: " . RAZON_SOCIAL . " (" . RUT_EMISOR . ")\n\n";

    echo "Configuración:\n";
    echo "  Envío automático email: " . ($CONFIG['envio_automatico_email'] ? 'SÍ' : 'NO') . "\n";
    echo "  Consulta automática: " . ($CONFIG['consulta_automatica'] ? 'SÍ' : 'NO') . "\n\n";

    // Ejemplo de datos
    $datos_cliente = [
        'rut' => '66666666-6',
        'razon_social' => 'Cliente Final',
        'direccion' => 'Santiago Centro',
        'comuna' => 'Santiago',
        'email' => 'cliente@ejemplo.cl'
    ];

    $items = [
        [
            'nombre' => 'Cambio de aceite',
            'descripcion' => 'Servicio de cambio de aceite sintético',
            'cantidad' => 1,
            'precio' => 19900,
            'unidad' => 'un'
        ],
        [
            'nombre' => 'Alineación y balanceo',
            'descripcion' => 'Servicio completo',
            'cantidad' => 1,
            'precio' => 9900,
            'unidad' => 'un'
        ]
    ];

    try {
        $resultado = generar_boleta($datos_cliente, $items, $CONFIG, $API_BASE);

        echo "✅ Proceso completado exitosamente\n";

    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
