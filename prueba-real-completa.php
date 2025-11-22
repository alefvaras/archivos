#!/usr/bin/env php
<?php
/**
 * PRUEBA REAL COMPLETA - SIN LIMITACIONES
 *
 * Genera una boleta electrónica REAL en el SII
 * y consulta su estado automáticamente.
 *
 * Uso: php prueba-real-completa.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "  PRUEBA REAL COMPLETA - SISTEMA DE FACTURACIÓN ELECTRÓNICA\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Cargar configuración
require_once __DIR__ . '/config/settings.php';

$config = ConfiguracionSistema::getInstance();

// Verificar ambiente
$ambiente = $config->get('general.ambiente');
echo "✓ Ambiente: {$ambiente}\n";
echo "✓ RUT Emisor: " . $config->get('emisor.rut') . "\n";
echo "✓ Razón Social: " . $config->get('emisor.razon_social') . "\n";
echo "✓ API URL: " . $config->get('api.base_url') . "\n\n";

if ($ambiente !== 'certificacion') {
    die("ERROR: Solo se permiten pruebas en ambiente de CERTIFICACIÓN\n");
}

echo "════════════════════════════════════════════════════════════════\n";
echo " PASO 1: GENERAR BOLETA ELECTRÓNICA REAL\n";
echo "════════════════════════════════════════════════════════════════\n\n";

// Datos de la boleta
$datosCliente = [
    'rut' => '66666666-6',  // RUT de prueba válido para certificación
    'razon_social' => 'Cliente de Prueba SII',
    'giro' => 'Servicios',
    'direccion' => 'Calle Falsa 123',
    'comuna' => 'Santiago',
    'email' => 'prueba@test.cl',
];

$items = [
    [
        'nombre' => 'Producto de Prueba 1',
        'descripcion' => 'Descripción detallada del producto',
        'cantidad' => 2,
        'precio' => 15000,
        'descuento' => 0,
    ],
    [
        'nombre' => 'Servicio de Prueba 2',
        'descripcion' => 'Servicio de consultoría',
        'cantidad' => 1,
        'precio' => 25000,
        'descuento' => 0,
    ],
];

// Calcular totales
$neto = 0;
foreach ($items as $item) {
    $subtotal = ($item['precio'] * $item['cantidad']) - $item['descuento'];
    $neto += $subtotal;
}

$iva = round($neto * 0.19);
$total = $neto + $iva;

echo "Items de la boleta:\n";
foreach ($items as $i => $item) {
    $subtotal = $item['precio'] * $item['cantidad'];
    echo sprintf("  %d. %s x%d = $%s\n",
        $i+1,
        $item['nombre'],
        $item['cantidad'],
        number_format($subtotal, 0, ',', '.')
    );
}
echo "\n";
echo "Neto:  $" . number_format($neto, 0, ',', '.') . "\n";
echo "IVA:   $" . number_format($iva, 0, ',', '.') . "\n";
echo "Total: $" . number_format($total, 0, ',', '.') . "\n\n";

// Preparar datos para SimpleAPI
$datosSimpleAPI = [
    'dte' => [
        'Encabezado' => [
            'IdDoc' => [
                'TipoDTE' => 39,  // Boleta electrónica
                'Folio' => null,  // SimpleAPI asigna automáticamente
                'FchEmis' => date('Y-m-d'),
            ],
            'Emisor' => [
                'RUTEmisor' => $config->get('emisor.rut'),
                'RznSoc' => $config->get('emisor.razon_social'),
                'GiroEmis' => $config->get('emisor.giro'),
                'Acteco' => 620200,
                'DirOrigen' => $config->get('emisor.direccion'),
                'CmnaOrigen' => $config->get('emisor.comuna'),
            ],
            'Receptor' => [
                'RUTRecep' => $datosCliente['rut'],
                'RznSocRecep' => $datosCliente['razon_social'],
                'GiroRecep' => $datosCliente['giro'],
                'DirRecep' => $datosCliente['direccion'],
                'CmnaRecep' => $datosCliente['comuna'],
                'CorreoRecep' => $datosCliente['email'],
            ],
            'Totales' => [
                'MntNeto' => $neto,
                'TasaIVA' => 19,
                'IVA' => $iva,
                'MntTotal' => $total,
            ],
        ],
        'Detalle' => [],
    ],
];

// Agregar items
foreach ($items as $i => $item) {
    $datosSimpleAPI['dte']['Detalle'][] = [
        'NroLinDet' => $i + 1,
        'NmbItem' => $item['nombre'],
        'DscItem' => $item['descripcion'],
        'QtyItem' => $item['cantidad'],
        'PrcItem' => $item['precio'],
        'MontoItem' => $item['precio'] * $item['cantidad'],
    ];
}

echo "Preparando envío a SimpleAPI...\n";

// Enviar a SimpleAPI
$apiKey = $config->get('api.api_key');
$apiUrl = $config->get('api.base_url');

$ch = curl_init($apiUrl . '/dte/document');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode($datosSimpleAPI),
    CURLOPT_TIMEOUT => 60,
    CURLOPT_SSL_VERIFYPEER => true,
]);

echo "Enviando a SimpleAPI...\n";
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    die("ERROR cURL: {$curlError}\n");
}

echo "Respuesta HTTP: {$httpCode}\n";

$resultado = json_decode($response, true);

if ($httpCode >= 200 && $httpCode < 300) {
    echo "\n✓✓✓ BOLETA GENERADA EXITOSAMENTE ✓✓✓\n\n";

    // Guardar información
    $trackId = $resultado['trackId'] ?? $resultado['track_id'] ?? null;
    $folio = $resultado['folio'] ?? null;
    $tipoDte = $resultado['tipo'] ?? 39;

    echo "════════════════════════════════════════════════════════════════\n";
    echo " INFORMACIÓN DE LA BOLETA\n";
    echo "════════════════════════════════════════════════════════════════\n";
    echo "Tipo DTE:  {$tipoDte}\n";
    echo "Folio:     {$folio}\n";
    echo "Track ID:  {$trackId}\n";
    echo "Monto:     $" . number_format($total, 0, ',', '.') . "\n";
    echo "════════════════════════════════════════════════════════════════\n\n";

    // Guardar respuesta completa
    $archivoResultado = __DIR__ . '/logs/resultado-prueba-' . date('Y-m-d_His') . '.json';
    file_put_contents($archivoResultado, json_encode($resultado, JSON_PRETTY_PRINT));
    echo "Resultado completo guardado en: {$archivoResultado}\n\n";

    // Esperar para consultar estado
    if ($trackId) {
        echo "════════════════════════════════════════════════════════════════\n";
        echo " PASO 2: CONSULTAR ESTADO EN EL SII\n";
        echo "════════════════════════════════════════════════════════════════\n\n";

        echo "Esperando 5 segundos para que el SII procese...\n";
        for ($i = 5; $i > 0; $i--) {
            echo "{$i}... ";
            sleep(1);
        }
        echo "\n\n";

        // Consultar estado
        $ch = curl_init($apiUrl . '/dte/status/' . $trackId);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        echo "Consultando estado con Track ID: {$trackId}\n";
        $responseEstado = curl_exec($ch);
        $httpCodeEstado = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        echo "Respuesta HTTP: {$httpCodeEstado}\n";

        $estado = json_decode($responseEstado, true);

        if ($httpCodeEstado >= 200 && $httpCodeEstado < 300) {
            echo "\n✓✓✓ ESTADO CONSULTADO EXITOSAMENTE ✓✓✓\n\n";

            echo "════════════════════════════════════════════════════════════════\n";
            echo " RESPUESTA DEL SII\n";
            echo "════════════════════════════════════════════════════════════════\n";
            echo json_encode($estado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            echo "════════════════════════════════════════════════════════════════\n\n";

            // Guardar estado
            $archivoEstado = __DIR__ . '/logs/estado-' . $trackId . '.json';
            file_put_contents($archivoEstado, json_encode($estado, JSON_PRETTY_PRINT));
            echo "Estado guardado en: {$archivoEstado}\n\n";

            // Interpretar estado
            $estadoSii = $estado['estado'] ?? $estado['status'] ?? 'DESCONOCIDO';
            $mensaje = $estado['mensaje'] ?? $estado['message'] ?? '';

            echo "Estado SII: {$estadoSii}\n";
            if ($mensaje) {
                echo "Mensaje: {$mensaje}\n";
            }

        } else {
            echo "\n⚠ Error al consultar estado\n";
            echo "Respuesta: {$responseEstado}\n\n";
        }
    }

} else {
    echo "\n✗✗✗ ERROR AL GENERAR BOLETA ✗✗✗\n\n";
    echo "Código HTTP: {$httpCode}\n";
    echo "Respuesta:\n";
    echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
}

echo "════════════════════════════════════════════════════════════════\n";
echo " PRUEBA COMPLETADA\n";
echo "════════════════════════════════════════════════════════════════\n\n";

echo "Archivos generados en: " . __DIR__ . "/logs/\n";
echo "Certificados en: " . __DIR__ . "/xmls/\n";
echo "PDFs en: " . __DIR__ . "/pdfs/\n\n";

exit(0);
