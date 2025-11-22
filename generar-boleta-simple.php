#!/usr/bin/env php
<?php
/**
 * GENERADOR SIMPLE DE BOLETAS ELECTRÓNICAS
 *
 * Script para generar boletas electrónicas de manera rápida
 * sin limitaciones técnicas.
 *
 * Uso:
 *   php generar-boleta-simple.php
 *   php generar-boleta-simple.php --monto=50000
 *   php generar-boleta-simple.php --rut-cliente=12345678-9 --monto=100000
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuración
$API_KEY = '9794-N370-6392-6913-8052';
$API_BASE = 'https://api.simpleapi.cl';
$CERT_PATH = __DIR__ . '/16694181-4.pfx';
$CAF_PATH = __DIR__ . '/FoliosSII78274225391889202511161321.xml';
$RUT_EMISOR = '78274225-6';
$CERT_PASSWORD = '5605';

// Parsear argumentos
$options = getopt('', ['monto::', 'rut-cliente::', 'descripcion::', 'cantidad::']);
$monto_neto = isset($options['monto']) ? (int)$options['monto'] : 100000;
$rut_cliente = isset($options['rut-cliente']) ? $options['rut-cliente'] : '66666666-6';
$descripcion = isset($options['descripcion']) ? $options['descripcion'] : 'Producto/Servicio de Prueba';
$cantidad = isset($options['cantidad']) ? (int)$options['cantidad'] : 1;

// Calcular totales
$iva = round($monto_neto * 0.19);
$total = $monto_neto + $iva;
$precio_unitario = round($monto_neto / $cantidad);

// Folio
$folios_usados_file = __DIR__ . '/folios_usados.txt';
$folio = file_exists($folios_usados_file) ? (int)trim(file_get_contents($folios_usados_file)) + 1 : 1889;

// Banner
echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  GENERADOR DE BOLETAS ELECTRÓNICAS - SIN LIMITACIONES       ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

echo "🏢 Emisor:     AKIBARA SPA (78274225-6)\n";
echo "👤 Cliente:    $rut_cliente\n";
echo "📝 Folio:      $folio\n";
echo "📅 Fecha:      " . date('Y-m-d') . "\n\n";

echo "──────────────────────────────────────────────────────────────\n";
echo "Items:\n";
echo sprintf("  %dx %s @ \$%s\n",
    $cantidad,
    $descripcion,
    number_format($precio_unitario, 0, ',', '.')
);
echo "──────────────────────────────────────────────────────────────\n";
echo sprintf("Neto:           \$%s\n", number_format($monto_neto, 0, ',', '.'));
echo sprintf("IVA (19%%):      \$%s\n", number_format($iva, 0, ',', '.'));
echo sprintf("TOTAL:          \$%s\n", number_format($total, 0, ',', '.'));
echo "──────────────────────────────────────────────────────────────\n\n";

// Documento
$documento = [
    'Documento' => [
        'Encabezado' => [
            'IdentificacionDTE' => [
                'TipoDTE' => 39,
                'Folio' => $folio,
                'FechaEmision' => date('Y-m-d')
            ],
            'Emisor' => [
                'Rut' => $RUT_EMISOR,
                'RazonSocial' => 'AKIBARA SPA',
                'GiroBoleta' => 'Servicios de Tecnología',
                'DireccionOrigen' => 'Av. Providencia 1234',
                'ComunaOrigen' => 'Providencia'
            ],
            'Receptor' => [
                'Rut' => $rut_cliente,
                'RazonSocial' => 'Cliente',
                'Direccion' => 'Santiago',
                'Comuna' => 'Santiago'
            ],
            'Totales' => [
                'MontoNeto' => $monto_neto,
                'IVA' => $iva,
                'MontoTotal' => $total
            ]
        ],
        'Detalles' => [
            [
                'NmbItem' => $descripcion,
                'Cantidad' => $cantidad,
                'Precio' => $precio_unitario,
                'MontoItem' => $monto_neto
            ]
        ]
    ],
    'Certificado' => [
        'Rut' => '16694181-4',
        'Password' => $CERT_PASSWORD
    ]
];

echo "⏳ Generando DTE...\n";

// Multipart
$boundary = '----FormBoundary' . uniqid();
$eol = "\r\n";

$body = '';
$body .= '--' . $boundary . $eol;
$body .= 'Content-Disposition: form-data; name="input"' . $eol;
$body .= 'Content-Type: text/plain' . $eol . $eol;
$body .= json_encode($documento, JSON_UNESCAPED_UNICODE) . $eol;

$body .= '--' . $boundary . $eol;
$body .= 'Content-Disposition: form-data; name="files"; filename="cert.pfx"' . $eol;
$body .= 'Content-Type: application/octet-stream' . $eol . $eol;
$body .= file_get_contents($CERT_PATH) . $eol;

$body .= '--' . $boundary . $eol;
$body .= 'Content-Disposition: form-data; name="files2"; filename="caf.xml"' . $eol;
$body .= 'Content-Type: application/octet-stream' . $eol . $eol;
$body .= file_get_contents($CAF_PATH) . $eol;

$body .= '--' . $boundary . '--' . $eol;

// Request
$ch = curl_init($API_BASE . '/api/v1/DTE/generar');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_HTTPHEADER => [
        'Authorization: ' . $API_KEY,
        'Content-Type: multipart/form-data; boundary=' . $boundary,
    ],
    CURLOPT_TIMEOUT => 60
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ Error: $error\n\n";
    exit(1);
}

if ($http_code !== 200) {
    echo "❌ Error HTTP $http_code\n";
    echo substr($response, 0, 500) . "\n\n";
    exit(1);
}

// Guardar
file_put_contents($folios_usados_file, $folio);

$xml_dir = __DIR__ . '/xmls';
@mkdir($xml_dir, 0755, true);
$xml_file = "$xml_dir/dte-$folio.xml";
file_put_contents($xml_file, $response);

echo "\n╔══════════════════════════════════════════════════════════════╗\n";
echo "║  ✅ BOLETA GENERADA EXITOSAMENTE                            ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

echo "📋 Detalles:\n";
echo "   Tipo:        Boleta Electrónica (39)\n";
echo "   Folio:       $folio\n";
echo "   Total:       \$" . number_format($total, 0, ',', '.') . "\n";
echo "   XML:         $xml_file\n";
echo "   Tamaño:      " . number_format(strlen($response)) . " bytes\n\n";

echo "📄 Vista previa del XML:\n";
echo str_repeat("─", 62) . "\n";
echo substr($response, 0, 600) . "...\n";
echo str_repeat("─", 62) . "\n\n";

echo "✨ Boleta lista para usar\n";
echo "💡 Siguiente folio disponible: " . ($folio + 1) . "\n\n";

exit(0);
