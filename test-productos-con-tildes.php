<?php
/**
 * Test de productos con caracteres especiales en PDF
 * Verifica que tildes, eñes y símbolos se muestren correctamente
 */

require_once(__DIR__ . '/lib/generar-pdf-boleta.php');
require_once(__DIR__ . '/lib/VisualHelper.php');

$v = VisualHelper::getInstance();

echo "\n";
$v->titulo("TEST: Productos con Tildes y Caracteres Especiales");
echo "\n";

// Datos de prueba con MUCHOS caracteres especiales
$datos_test = [
    'Documento' => [
        'Encabezado' => [
            'IdentificacionDTE' => [
                'TipoDTE' => 39,
                'Folio' => 9999,
                'FechaEmision' => date('Y-m-d')
            ],
            'Emisor' => [
                'Rut' => '76063822-6',
                'RazonSocial' => 'EMPRESA ELECTRÓNICA SEÑORÍA LTDA.',
                'GiroBoleta' => 'Venta de artículos electrónicos, computación y tecnología',
                'DireccionOrigen' => 'Av. José María Cañas 123',
                'ComunaOrigen' => 'Ñuñoa'
            ],
            'Receptor' => [
                'Rut' => '11111111-1',
                'RazonSocial' => 'José María Pérez González',
                'Direccion' => 'Pasaje Año Nuevo N° 456',
                'Comuna' => 'Peñalolén'
            ],
            'Totales' => [
                'MontoNeto' => 100000,
                'IVA' => 19000,
                'MontoTotal' => 119000
            ]
        ],
        'Detalles' => [
            [
                'NmbItem' => 'Computación: PC Diseño Gráfico Año 2024',
                'Descripcion' => 'Incluye teclado español con ñ, ratón óptico y garantía',
                'Cantidad' => 1,
                'Precio' => 50000,
                'MontoItem' => 50000
            ],
            [
                'NmbItem' => 'Teléfono Móvil con Cámara 108MP',
                'Descripcion' => 'Pantalla AMOLED 6.7", batería 5000mAh, cargador rápido',
                'Cantidad' => 1,
                'Precio' => 30000,
                'MontoItem' => 30000
            ],
            [
                'NmbItem' => 'Café Orgánico Premium Montaña',
                'Descripcion' => 'Origen: región cafetalera, tostado artesanal',
                'Cantidad' => 2,
                'Precio' => 10000,
                'MontoItem' => 20000
            ]
        ]
    ]
];

// Crear XML básico de prueba
$xml_test = <<<XML
<?xml version="1.0" encoding="ISO-8859-1"?>
<DTE version="1.0">
    <Documento>
        <Encabezado>
            <IdDoc>
                <TipoDTE>39</TipoDTE>
                <Folio>9999</Folio>
                <FchEmis>2024-11-16</FchEmis>
            </IdDoc>
            <Emisor>
                <RUTEmisor>76063822-6</RUTEmisor>
            </Emisor>
            <Totales>
                <MntTotal>119000</MntTotal>
            </Totales>
        </Encabezado>
    </Documento>
</DTE>
XML;

$v->subtitulo("Productos de Prueba");

echo "\n";
$v->mensaje('info', 'Productos con caracteres especiales a probar:');
echo "\n";

foreach ($datos_test['Documento']['Detalles'] as $i => $item) {
    echo "  " . ($i + 1) . ". " . $item['NmbItem'] . "\n";
    if (!empty($item['Descripcion'])) {
        echo "     → " . $item['Descripcion'] . "\n";
    }
}

echo "\n";
$v->subtitulo("Caracteres Especiales Incluidos");
echo "\n";

$caracteres = [
    'Tildes' => ['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú'],
    'Eñes' => ['ñ', 'Ñ'],
    'Símbolos' => ['°', 'N°', '"', '%']
];

foreach ($caracteres as $tipo => $lista) {
    echo "  • $tipo: " . implode(', ', $lista) . "\n";
}

echo "\n";
$v->subtitulo("Generando PDF de Prueba");
echo "\n";

$pdf_dir = __DIR__ . '/pdfs';
if (!is_dir($pdf_dir)) {
    mkdir($pdf_dir, 0755, true);
}

$pdf_path = $pdf_dir . '/test_productos_tildes.pdf';

try {
    $v->cargando("Generando PDF con productos especiales", 1);

    generar_pdf_boleta($datos_test, $xml_test, $pdf_path);

    $v->mensaje('success', 'PDF generado exitosamente');
    $v->lista([
        ['texto' => 'Archivo', 'valor' => basename($pdf_path)],
        ['texto' => 'Ruta', 'valor' => $pdf_path],
        ['texto' => 'Tamaño', 'valor' => number_format(filesize($pdf_path) / 1024, 2) . ' KB']
    ]);

    echo "\n";
    $v->subtitulo("Verificación de Encoding");
    echo "\n";

    $texto_verificar = [
        'Empresa' => 'ELECTRÓNICA SEÑORÍA',
        'Comuna' => 'Ñuñoa',
        'Cliente' => 'José María Pérez',
        'Dirección' => 'Año Nuevo N°',
        'Comuna 2' => 'Peñalolén',
        'Producto 1' => 'Computación: PC Diseño Gráfico',
        'Descripción 1' => 'español con ñ',
        'Producto 2' => 'Teléfono Móvil con Cámara',
        'Descripción 2' => 'Pantalla AMOLED 6.7"',
        'Producto 3' => 'Café Orgánico Premium Montaña',
        'Descripción 3' => 'región cafetalera'
    ];

    echo "  ✅ Todos estos textos deberían verse CORRECTOS en el PDF:\n\n";

    foreach ($texto_verificar as $campo => $texto) {
        echo "     • $campo: \"$texto\"\n";
    }

    echo "\n";
    $v->mensaje('success', '¡Test completado! Abre el PDF y verifica que todos los caracteres se vean bien.');

    echo "\n";
    echo "╔═══════════════════════════════════════════════════════════════╗\n";
    echo "║ IMPORTANTE: Verifica en el PDF que se muestren correctamente: ║\n";
    echo "║                                                               ║\n";
    echo "║  ✓ Tildes: á, é, í, ó, ú                                     ║\n";
    echo "║  ✓ Eñes: ñ, Ñ (en Ñuñoa, Peñalolén, español)                 ║\n";
    echo "║  ✓ Símbolos: N° (con símbolo de grado)                       ║\n";
    echo "║  ✓ Comillas: 6.7\" (pulgadas)                                ║\n";
    echo "╚═══════════════════════════════════════════════════════════════╝\n";
    echo "\n";

} catch (Exception $e) {
    $v->mensaje('error', 'Error al generar PDF: ' . $e->getMessage());
    exit(1);
}
