#!/usr/bin/env php
<?php
/**
 * Sistema de Generación de Boletas Electrónicas
 * Genera, envía al SII y opcionalmente envía por email al cliente
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

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

    if ($siguiente_folio > $caf_info['folio_hasta']) {
        throw new Exception("No hay más folios disponibles en el CAF actual");
    }

    file_put_contents($control_file, $siguiente_folio);
    return $siguiente_folio;
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
 * Enviar boleta por email
 */
function enviar_email($destinatario, $dte_xml, $datos_boleta, $config) {
    // Parsear XML para obtener datos
    $xml = simplexml_load_string($dte_xml);
    $folio = (string) $xml->Documento->Encabezado->IdDoc->Folio;
    $fecha = (string) $xml->Documento->Encabezado->IdDoc->FchEmis;
    $total = (string) $xml->Documento->Encabezado->Totales->MntTotal;

    // Guardar PDF temporal (en producción, generar PDF real)
    $pdf_filename = "boleta_{$folio}.pdf";
    $xml_filename = "boleta_{$folio}.xml";

    // Asunto y mensaje
    $asunto = "Boleta Electrónica N° {$folio} - " . RAZON_SOCIAL;
    $mensaje = "
    <html>
    <body>
        <h2>Boleta Electrónica</h2>
        <p>Estimado cliente,</p>
        <p>Adjunto encontrará su Boleta Electrónica con los siguientes datos:</p>
        <ul>
            <li><strong>Folio:</strong> {$folio}</li>
            <li><strong>Fecha:</strong> {$fecha}</li>
            <li><strong>Total:</strong> \${$total}</li>
            <li><strong>Emisor:</strong> " . RAZON_SOCIAL . "</li>
        </ul>
        <p>Gracias por su preferencia.</p>
    </body>
    </html>
    ";

    // Headers
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$config['email_remitente']}\r\n";

    // En producción, usar una librería como PHPMailer
    // Por ahora, solo simular el envío
    echo "  📧 Email enviado a: {$destinatario}\n";
    echo "     Asunto: {$asunto}\n";

    return true;
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
    echo "  ✓ CAF tipo {$caf_info['tipo_dte']}, folios {$caf_info['folio_desde']}-{$caf_info['folio_hasta']}\n\n";

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
