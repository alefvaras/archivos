<?php
/**
 * Generar Boleta de Prueba V2 - Datos Correctos
 */

require_once __DIR__ . '/lib/VisualHelper.php';

$v = VisualHelper::getInstance();
$v->limpiar();
$v->titulo("GENERACIÓN DE BOLETA DE PRUEBA V2 (DATOS CORRECTOS)", "═");

echo "\n";
$v->subtitulo("Configuración y Datos");

$folio = 1890;
$fecha = date('Y-m-d');
$EMAIL_DESTINATARIO = 'ale.fvaras@gmail.com';

// Items de la boleta
$items_venta = [
    [
        'nombre' => 'Servicio de Desarrollo de Software',
        'descripcion' => 'Desarrollo de sistema de boletas electrónicas',
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
foreach ($items_venta as $item) {
    $subtotal += $item['cantidad'] * $item['precio'];
}
$neto = $subtotal;
$iva = round($neto * 0.19);
$total = $neto + $iva;

$v->lista([
    ['texto' => 'Folio', 'valor' => $folio],
    ['texto' => 'Items', 'valor' => count($items_venta)],
    ['texto' => 'Neto', 'valor' => '$' . number_format($neto, 0, ',', '.')],
    ['texto' => 'IVA', 'valor' => '$' . number_format($iva, 0, ',', '.')],
    ['texto' => 'Total', 'valor' => '$' . number_format($total, 0, ',', '.')],
]);

// ========================================
// PREPARAR DATOS EN FORMATO CORRECTO
// ========================================

echo "\n";
$v->subtitulo("Preparando Datos para PDF");

// Estructura que espera el generador de PDF
// IMPORTANTE: El PDF generator busca 'Detalles' (con S) no 'Detalle'
$datos_boleta = [
    'Documento' => [
        'Encabezado' => [
            'IdentificacionDTE' => [
                'TipoDTE' => 39,
                'Folio' => $folio,
                'FechaEmision' => $fecha,
                'FechaVencimiento' => $fecha,
            ],
            'Emisor' => [
                'Rut' => '78274225-6',
                'RazonSocial' => 'AKIBARA SPA',
                'RazonSocialBoleta' => 'AKIBARA SPA',
                'GiroBoleta' => 'SERVICIOS DE DESARROLLO DE SOFTWARE',
                'DireccionOrigen' => 'Santiago',
                'ComunaOrigen' => 'Santiago',
                'ActividadEconomica' => '620200',
            ],
            'Receptor' => [
                'Rut' => '66666666-6',
                'RazonSocialReceptor' => 'Alejandro Varas',
                'DireccionReceptor' => 'Santiago, Chile',
                'ComunaReceptor' => 'Santiago',
            ],
            'Totales' => [
                'MntNeto' => $neto,
                'TasaIVA' => 19,
                'IVA' => $iva,
                'MontoTotal' => $total,  // IMPORTANTE: MontoTotal no MntTotal
            ],
        ],
        'Detalles' => [],  // IMPORTANTE: Detalles con S
    ],
];

// Agregar items al detalle
foreach ($items_venta as $index => $item) {
    $precio_unitario = $item['precio'];
    $monto_item = $item['cantidad'] * $precio_unitario;

    // IMPORTANTE: Las claves que busca el PDF generator son:
    // Nombre/NmbItem, Cantidad, Precio, MontoItem
    $datos_boleta['Documento']['Detalles'][] = [
        'NroLinDet' => $index + 1,
        'Nombre' => $item['nombre'],  // Usa 'Nombre' no 'NombreItem'
        'NmbItem' => $item['nombre'], // Alternativa
        'DescripcionItem' => $item['descripcion'] ?? '',
        'Cantidad' => $item['cantidad'],
        'UnidadMedida' => $item['unidad'] ?? 'un',
        'Precio' => $precio_unitario,  // Usa 'Precio' no 'PrecioUnitario'
        'MontoItem' => $monto_item,
    ];
}

echo "  ✓  Datos estructurados correctamente\n";
echo "  •  Emisor: AKIBARA SPA (78274225-6)\n";
echo "  •  Receptor: Alejandro Varas (66666666-6)\n";
echo "  •  Items: " . count($datos_boleta['Documento']['Detalles']) . "\n";

// ========================================
// GENERAR XML DE PRUEBA
// ========================================

echo "\n";
$v->subtitulo("Generando XML de Prueba");

$xml_dte = '<?xml version="1.0" encoding="ISO-8859-1"?>
<DTE version="1.0" xmlns="http://www.sii.cl/SiiDte">
  <Documento ID="DTE-39-' . $folio . '">
    <Encabezado>
      <IdDoc>
        <TipoDTE>39</TipoDTE>
        <Folio>' . $folio . '</Folio>
        <FchEmis>' . $fecha . '</FchEmis>
      </IdDoc>
      <Emisor>
        <RUTEmisor>78274225-6</RUTEmisor>
        <RznSocBoleta>AKIBARA SPA</RznSocBoleta>
        <GiroBoleta>SERVICIOS DE DESARROLLO DE SOFTWARE</GiroBoleta>
        <DirOrigen>Santiago</DirOrigen>
        <CmnaOrigen>Santiago</CmnaOrigen>
      </Emisor>
      <Receptor>
        <RUTRecep>66666666-6</RUTRecep>
        <RznSocRecep>Alejandro Varas</RznSocRecep>
        <DirRecep>Santiago, Chile</DirRecep>
        <CmnaRecep>Santiago</CmnaRecep>
      </Receptor>
      <Totales>
        <MntNeto>' . $neto . '</MntNeto>
        <TasaIVA>19</TasaIVA>
        <IVA>' . $iva . '</IVA>
        <MntTotal>' . $total . '</MntTotal>
      </Totales>
    </Encabezado>
    <Detalle>';

foreach ($items_venta as $index => $item) {
    $monto_linea = $item['cantidad'] * $item['precio'];
    $xml_dte .= '
      <Linea>
        <NroLinDet>' . ($index + 1) . '</NroLinDet>
        <NmbItem>' . htmlspecialchars($item['nombre']) . '</NmbItem>
        <DscItem>' . htmlspecialchars($item['descripcion'] ?? '') . '</DscItem>
        <QtyItem>' . $item['cantidad'] . '</QtyItem>
        <UnmdItem>' . ($item['unidad'] ?? 'un') . '</UnmdItem>
        <PrcItem>' . $item['precio'] . '</PrcItem>
        <MontoItem>' . $monto_linea . '</MontoItem>
      </Linea>';
}

$xml_dte .= '
    </Detalle>
    <TED version="1.0">
      <DD>
        <RE>78274225-6</RE>
        <TD>39</TD>
        <F>' . $folio . '</F>
        <FE>' . $fecha . '</FE>
        <RR>66666666-6</RR>
        <RSR>Alejandro Varas</RSR>
        <MNT>' . $total . '</MNT>
        <IT1>SERVICIOS DE DESARROLLO</IT1>
        <CAF version="1.0">
          <DA>
            <RE>78274225-6</RE>
            <RS>AKIBARA SPA</RS>
            <TD>39</TD>
            <RNG><D>' . $folio . '</D><H>' . ($folio + 99) . '</H></RNG>
            <FA>' . $fecha . '</FA>
          </DA>
          <FRMA algoritmo="SHA1withRSA">SIMULADO_CAF_PRUEBA</FRMA>
        </CAF>
        <TSTED>' . date('Y-m-d\TH:i:s') . '</TSTED>
      </DD>
      <FRMT algoritmo="SHA1withRSA">SIMULADO_FIRMA_PRUEBA_' . md5($folio . $total) . '</FRMT>
    </TED>
  </Documento>
</DTE>';

// Guardar XML
$xml_dir = __DIR__ . '/xmls';
if (!is_dir($xml_dir)) {
    mkdir($xml_dir, 0755, true);
}

$xml_filename = 'boleta_' . $folio . '_' . date('YmdHis') . '.xml';
$xml_path = $xml_dir . '/' . $xml_filename;
file_put_contents($xml_path, $xml_dte);

echo "  ✓  XML generado\n";
echo "  •  Ruta: {$xml_path}\n";
echo "  •  Tamaño: " . number_format(filesize($xml_path) / 1024, 2) . " KB\n";

// ========================================
// GENERAR PDF
// ========================================

echo "\n";
$v->subtitulo("Generando PDF");

require_once __DIR__ . '/lib/generar-pdf-boleta.php';

$pdf_dir = __DIR__ . '/pdfs';
if (!is_dir($pdf_dir)) {
    mkdir($pdf_dir, 0755, true);
}

$pdf_filename = 'boleta_' . $folio . '_' . date('YmdHis') . '.pdf';
$pdf_path = $pdf_dir . '/' . $pdf_filename;

try {
    generar_pdf_boleta($datos_boleta, $xml_dte, $pdf_path);

    if (file_exists($pdf_path)) {
        $pdf_size = filesize($pdf_path);
        echo "  ✓  PDF generado correctamente\n";
        echo "  •  Ruta: {$pdf_path}\n";
        echo "  •  Tamaño: " . number_format($pdf_size / 1024, 2) . " KB\n";
    } else {
        throw new Exception('PDF no se generó');
    }
} catch (Exception $e) {
    echo "  ✗  Error: " . $e->getMessage() . "\n";
    exit(1);
}

// ========================================
// PREPARAR EMAIL HTML
// ========================================

echo "\n";
$v->subtitulo("Generando Preview de Email");

$email_html = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Boleta Electrónica N° {$folio}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; }
        .header { background: linear-gradient(135deg, #0066cc 0%, #0052a3 100%); color: white; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 28px; }
        .content { padding: 30px; }
        .boleta-card { background: #f8f9fa; border: 2px solid #0066cc; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e0e0e0; }
        .info-label { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th { background: #0066cc; color: white; padding: 10px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #ddd; }
        .total-row { background: #0066cc; color: white; font-weight: bold; font-size: 18px; }
        .footer { background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666; border-top: 2px solid #0066cc; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>📄 Boleta Electrónica</h1>
            <p>Documento Tributario Electrónico</p>
        </div>
        <div class='content'>
            <p style='font-size: 16px;'>Estimado/a <strong>Alejandro Varas</strong>,</p>
            <p>Adjunto encontrará su Boleta Electrónica emitida por <strong>AKIBARA SPA</strong>.</p>

            <div class='boleta-card'>
                <h3 style='color: #0066cc; margin-top: 0;'>📋 Información del Documento</h3>
                <div class='info-row'>
                    <span class='info-label'>Tipo:</span>
                    <span>Boleta Electrónica (Tipo 39)</span>
                </div>
                <div class='info-row'>
                    <span class='info-label'>Folio:</span>
                    <span>#{$folio}</span>
                </div>
                <div class='info-row'>
                    <span class='info-label'>Fecha:</span>
                    <span>" . date('d/m/Y', strtotime($fecha)) . "</span>
                </div>
                <div class='info-row' style='border-bottom: none;'>
                    <span class='info-label'>Total:</span>
                    <span style='font-size: 20px; color: #0066cc; font-weight: bold;'>\$" . number_format($total, 0, ',', '.') . "</span>
                </div>
            </div>

            <h3>Detalle de Servicios</h3>
            <table>
                <tr>
                    <th>Cant.</th>
                    <th>Descripción</th>
                    <th style='text-align: right;'>Precio</th>
                    <th style='text-align: right;'>Total</th>
                </tr>";

foreach ($items_venta as $item) {
    $total_linea = $item['cantidad'] * $item['precio'];
    $email_html .= "
                <tr>
                    <td>{$item['cantidad']} {$item['unidad']}</td>
                    <td><strong>{$item['nombre']}</strong><br><small style='color: #666;'>{$item['descripcion']}</small></td>
                    <td style='text-align: right;'>\$" . number_format($item['precio'], 0, ',', '.') . "</td>
                    <td style='text-align: right;'>\$" . number_format($total_linea, 0, ',', '.') . "</td>
                </tr>";
}

$email_html .= "
                <tr style='background: #f5f5f5;'>
                    <td colspan='3' style='text-align: right; font-weight: bold;'>NETO:</td>
                    <td style='text-align: right; font-weight: bold;'>\$" . number_format($neto, 0, ',', '.') . "</td>
                </tr>
                <tr style='background: #f5f5f5;'>
                    <td colspan='3' style='text-align: right;'>IVA (19%):</td>
                    <td style='text-align: right;'>\$" . number_format($iva, 0, ',', '.') . "</td>
                </tr>
                <tr class='total-row'>
                    <td colspan='3' style='text-align: right; padding: 15px;'>TOTAL:</td>
                    <td style='text-align: right; padding: 15px;'>\$" . number_format($total, 0, ',', '.') . "</td>
                </tr>
            </table>

            <p style='margin-top: 30px; color: #666;'>
                Saludos cordiales,<br>
                <strong style='color: #0066cc;'>Equipo AKIBARA SPA</strong>
            </p>
        </div>
        <div class='footer'>
            <strong>AKIBARA SPA</strong><br>
            RUT: 78274225-6<br>
            Santiago, Chile<br>
            <a href='mailto:{$EMAIL_DESTINATARIO}'>{$EMAIL_DESTINATARIO}</a>
        </div>
    </div>
</body>
</html>";

$email_preview_path = __DIR__ . '/email_boleta_' . date('YmdHis') . '.html';
file_put_contents($email_preview_path, $email_html);

echo "  ✓  Email HTML generado\n";
echo "  •  Preview: {$email_preview_path}\n";

// ========================================
// RESUMEN
// ========================================

echo "\n";
$v->titulo("ARCHIVOS GENERADOS", "─");
echo "\n";

echo "📄 PDF: {$pdf_path}\n";
echo "📋 XML: {$xml_path}\n";
echo "📧 Email Preview: {$email_preview_path}\n\n";

echo "📤 Para enviar por email:\n";
echo "   php enviar-email-boleta.php '{$pdf_path}' '{$xml_path}' '{$EMAIL_DESTINATARIO}'\n\n";

$v->mensaje('success', '¡Boleta generada exitosamente con datos correctos!');

echo "\n";
$v->separador();
echo "\n";
