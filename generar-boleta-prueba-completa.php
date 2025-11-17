<?php
/**
 * Generar Boleta de Prueba Completa (sin envío real al SII)
 *
 * Este script genera una boleta localmente con PDF completo
 */

require_once __DIR__ . '/lib/VisualHelper.php';

$v = VisualHelper::getInstance();
$v->limpiar();
$v->titulo("GENERACIÓN DE BOLETA DE PRUEBA COMPLETA", "═");

// ========================================
// CONFIGURACIÓN
// ========================================

echo "\n";
$v->subtitulo("1. Configuración");

define('RUT_EMISOR', '78274225-6');
define('RAZON_SOCIAL', 'AKIBARA SPA');
define('GIRO', 'SERVICIOS DE DESARROLLO DE SOFTWARE');
define('DIRECCION_EMISOR', 'Santiago, Chile');
define('COMUNA_EMISOR', 'Santiago');

$EMAIL_DESTINATARIO = 'ale.fvaras@gmail.com';

$v->lista([
    ['texto' => 'RUT Emisor', 'valor' => RUT_EMISOR],
    ['texto' => 'Razón Social', 'valor' => RAZON_SOCIAL],
    ['texto' => 'Email Destino', 'valor' => $EMAIL_DESTINATARIO],
]);

// ========================================
// DATOS DE LA BOLETA
// ========================================

echo "\n";
$v->subtitulo("2. Datos de la Boleta");

$folio = 1889;
$fecha = date('Y-m-d');
$hora = date('H:i:s');

$cliente = [
    'rut' => '66666666-6',
    'razon_social' => 'Alejandro Varas',
    'direccion' => 'Santiago, Chile',
    'comuna' => 'Santiago',
    'email' => $EMAIL_DESTINATARIO,
];

$items = [
    [
        'nombre' => 'Servicio de Desarrollo de Software',
        'descripcion' => 'Desarrollo de sistema de boletas electrónicas para WooCommerce',
        'cantidad' => 1,
        'precio' => 350000,
        'unidad' => 'un'
    ],
    [
        'nombre' => 'Consultoría Técnica',
        'descripcion' => 'Horas de consultoría en integración SII',
        'cantidad' => 4,
        'precio' => 85000,
        'unidad' => 'hr'
    ],
    [
        'nombre' => 'Soporte Mensual Premium',
        'descripcion' => 'Plan de soporte técnico mensual',
        'cantidad' => 1,
        'precio' => 120000,
        'unidad' => 'mes'
    ],
];

// Calcular totales
$subtotal = 0;
foreach ($items as $item) {
    $subtotal += $item['cantidad'] * $item['precio'];
}

$descuento = 0; // Sin descuento
$exento = 0;    // Sin exento
$neto = $subtotal - $descuento - $exento;
$iva = round($neto * 0.19);
$total = $neto + $iva;

$v->lista([
    ['texto' => 'Folio', 'valor' => $folio],
    ['texto' => 'Fecha', 'valor' => date('d/m/Y', strtotime($fecha))],
    ['texto' => 'Cliente', 'valor' => $cliente['razon_social']],
    ['texto' => 'Items', 'valor' => count($items)],
    ['texto' => 'Subtotal', 'valor' => '$' . number_format($subtotal, 0, ',', '.')],
    ['texto' => 'Neto', 'valor' => '$' . number_format($neto, 0, ',', '.')],
    ['texto' => 'IVA (19%)', 'valor' => '$' . number_format($iva, 0, ',', '.')],
    ['texto' => 'Total', 'valor' => '$' . number_format($total, 0, ',', '.')],
]);

// ========================================
// GENERAR PDF
// ========================================

echo "\n";
$v->subtitulo("3. Generando PDF");

require_once __DIR__ . '/lib/generar-pdf-boleta.php';

$datos_pdf = [
    'folio' => $folio,
    'fecha' => date('d/m/Y', strtotime($fecha)),
    'hora' => $hora,
    'cliente' => [
        'rut' => $cliente['rut'],
        'razon_social' => $cliente['razon_social'],
        'direccion' => $cliente['direccion'],
        'comuna' => $cliente['comuna'],
    ],
    'items' => $items,
    'subtotal' => $subtotal,
    'descuento' => $descuento,
    'exento' => $exento,
    'neto' => $neto,
    'iva' => $iva,
    'total' => $total,
    'emisor' => [
        'rut' => RUT_EMISOR,
        'razon_social' => RAZON_SOCIAL,
        'giro' => GIRO,
        'direccion' => DIRECCION_EMISOR,
        'comuna' => COMUNA_EMISOR,
    ],
    'tipo_dte' => 39,
];

// Crear directorio si no existe
$pdf_dir = __DIR__ . '/pdfs';
if (!is_dir($pdf_dir)) {
    mkdir($pdf_dir, 0755, true);
    echo "  ✓  Directorio PDFs creado\n";
}

$pdf_filename = 'boleta_prueba_' . $folio . '_' . date('YmdHis') . '.pdf';
$pdf_path = $pdf_dir . '/' . $pdf_filename;

// Generar PDF (sin timbre por ahora, ya que no tenemos XML del SII)
try {
    // Crear XML de prueba simple para el timbre
    $xml_prueba = '<?xml version="1.0" encoding="ISO-8859-1"?>
<DTE version="1.0">
  <Documento ID="DTE-39-' . $folio . '">
    <TED version="1.0">
      <DD>
        <RE>' . RUT_EMISOR . '</RE>
        <TD>39</TD>
        <F>' . $folio . '</F>
        <FE>' . $fecha . '</FE>
        <RR>' . $cliente['rut'] . '</RR>
        <RSR>' . substr($cliente['razon_social'], 0, 40) . '</RSR>
        <MNT>' . $total . '</MNT>
        <IT1>SERVICIOS DE DESARROLLO</IT1>
        <CAF version="1.0">
          <DA>
            <RE>' . RUT_EMISOR . '</RE>
            <TD>39</TD>
          </DA>
        </CAF>
        <TSTED>' . date('Y-m-d\TH:i:s') . '</TSTED>
      </DD>
      <FRMT algoritmo="SHA1withRSA">SIMULADO_PRUEBA_LOCAL</FRMT>
    </TED>
  </Documento>
</DTE>';

    generar_pdf_boleta($datos_pdf, $xml_prueba, $pdf_path);

    if (file_exists($pdf_path)) {
        $pdf_size = filesize($pdf_path);
        echo "  ✓  PDF generado correctamente\n";
        echo "  •  Ruta: {$pdf_path}\n";
        echo "  •  Tamaño: " . number_format($pdf_size / 1024, 2) . " KB\n";
    } else {
        throw new Exception('PDF no se generó correctamente');
    }
} catch (Exception $e) {
    echo "  ✗  Error al generar PDF: " . $e->getMessage() . "\n";
    exit(1);
}

// ========================================
// GENERAR XML DE LA BOLETA
// ========================================

echo "\n";
$v->subtitulo("4. Generando XML");

$xml_dir = __DIR__ . '/xmls';
if (!is_dir($xml_dir)) {
    mkdir($xml_dir, 0755, true);
}

$xml_filename = 'boleta_prueba_' . $folio . '_' . date('YmdHis') . '.xml';
$xml_path = $xml_dir . '/' . $xml_filename;

file_put_contents($xml_path, $xml_prueba);
echo "  ✓  XML generado\n";
echo "  •  Ruta: {$xml_path}\n";

// ========================================
// PREPARAR EMAIL
// ========================================

echo "\n";
$v->subtitulo("5. Preparando Email");

$email_asunto = "Boleta Electrónica N° {$folio} - " . RAZON_SOCIAL;
$email_mensaje = "
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .header { background: #0066cc; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; }
        .boleta-info { background: #f5f5f5; padding: 15px; margin: 20px 0; border-left: 4px solid #0066cc; }
        .footer { background: #f5f5f5; padding: 15px; text-align: center; font-size: 12px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th { background: #0066cc; color: white; padding: 10px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class='header'>
        <h1>Boleta Electrónica</h1>
        <p>Documento Tributario Electrónico</p>
    </div>

    <div class='content'>
        <p>Estimado/a <strong>{$cliente['razon_social']}</strong>,</p>

        <p>Adjunto encontrará su Boleta Electrónica generada por <strong>" . RAZON_SOCIAL . "</strong>.</p>

        <div class='boleta-info'>
            <h3>Información del Documento</h3>
            <table>
                <tr>
                    <td><strong>Tipo:</strong></td>
                    <td>Boleta Electrónica (Tipo 39)</td>
                </tr>
                <tr>
                    <td><strong>Folio:</strong></td>
                    <td>{$folio}</td>
                </tr>
                <tr>
                    <td><strong>Fecha:</strong></td>
                    <td>" . date('d/m/Y', strtotime($fecha)) . "</td>
                </tr>
                <tr>
                    <td><strong>Monto Total:</strong></td>
                    <td>\$" . number_format($total, 0, ',', '.') . "</td>
                </tr>
            </table>
        </div>

        <h3>Detalle de Items</h3>
        <table>
            <tr>
                <th>Cantidad</th>
                <th>Descripción</th>
                <th>Precio Unit.</th>
                <th>Total</th>
            </tr>";

foreach ($items as $item) {
    $total_linea = $item['cantidad'] * $item['precio'];
    $email_mensaje .= "
            <tr>
                <td>{$item['cantidad']} {$item['unidad']}</td>
                <td><strong>{$item['nombre']}</strong><br><small>{$item['descripcion']}</small></td>
                <td>\$" . number_format($item['precio'], 0, ',', '.') . "</td>
                <td>\$" . number_format($total_linea, 0, ',', '.') . "</td>
            </tr>";
}

$email_mensaje .= "
            <tr style='background: #f5f5f5; font-weight: bold;'>
                <td colspan='3' style='text-align: right;'>NETO:</td>
                <td>\$" . number_format($neto, 0, ',', '.') . "</td>
            </tr>
            <tr style='background: #f5f5f5;'>
                <td colspan='3' style='text-align: right;'>IVA (19%):</td>
                <td>\$" . number_format($iva, 0, ',', '.') . "</td>
            </tr>
            <tr style='background: #0066cc; color: white; font-weight: bold; font-size: 16px;'>
                <td colspan='3' style='text-align: right;'>TOTAL:</td>
                <td>\$" . number_format($total, 0, ',', '.') . "</td>
            </tr>
        </table>

        <p><strong>Nota:</strong> Este documento ha sido generado electrónicamente y tiene validez tributaria.</p>

        <p>Adjuntos:</p>
        <ul>
            <li>📄 Boleta Electrónica en formato PDF</li>
            <li>📋 XML del documento (respaldo)</li>
        </ul>
    </div>

    <div class='footer'>
        <p><strong>" . RAZON_SOCIAL . "</strong><br>
        RUT: " . RUT_EMISOR . "<br>
        " . DIRECCION_EMISOR . "</p>
        <p style='margin-top: 15px;'><small>Este correo fue generado automáticamente. Por favor no responder.</small></p>
    </div>
</body>
</html>
";

// Guardar HTML del email para revisión
$email_html_path = __DIR__ . '/email_preview_' . date('YmdHis') . '.html';
file_put_contents($email_html_path, $email_mensaje);

echo "  ✓  Email preparado\n";
echo "  •  Para: {$EMAIL_DESTINATARIO}\n";
echo "  •  Asunto: {$email_asunto}\n";
echo "  •  Adjuntos: PDF + XML\n";
echo "  •  Preview HTML guardado en: {$email_html_path}\n";

// ========================================
// RESUMEN FINAL
// ========================================

echo "\n";
$v->titulo("RESUMEN DE ARCHIVOS GENERADOS", "─");

echo "\n";
echo "📄 PDF de la Boleta:\n";
echo "   {$pdf_path}\n\n";

echo "📋 XML del Documento:\n";
echo "   {$xml_path}\n\n";

echo "📧 Preview del Email:\n";
echo "   {$email_html_path}\n\n";

echo "💾 Para enviar el email real, ejecutar:\n";
echo "   php enviar-email-boleta.php '{$pdf_path}' '{$xml_path}' '{$EMAIL_DESTINATARIO}'\n\n";

$v->mensaje('success', '¡Boleta de prueba generada exitosamente!');

echo "\n";
$v->separador();
echo "\n";
