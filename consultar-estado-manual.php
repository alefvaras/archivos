#!/usr/bin/env php
<?php
/**
 * Script para consultar manualmente el estado de un envío al SII
 * Uso: php consultar-estado-manual.php <track_id>
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar argumentos
if ($argc < 2) {
    echo "\n";
    echo "═══════════════════════════════════════════════════════════\n";
    echo "  CONSULTA MANUAL DE ESTADO DE ENVÍO AL SII\n";
    echo "═══════════════════════════════════════════════════════════\n\n";
    echo "Uso: php consultar-estado-manual.php <track_id>\n\n";
    echo "Ejemplo:\n";
    echo "  php consultar-estado-manual.php 123456789\n\n";
    exit(1);
}

$track_id = $argv[1];

// Configuración
$API_KEY = '9794-N370-6392-6913-8052';
$API_BASE = 'https://api.simpleapi.cl';
$RUT_EMISOR = '78274225-6';

echo "\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "  CONSULTA DE ESTADO DE ENVÍO AL SII\n";
echo "═══════════════════════════════════════════════════════════\n\n";
echo "Track ID: $track_id\n";
echo "RUT Emisor: $RUT_EMISOR\n";
echo "Ambiente: Certificación\n\n";

// Construir URL
$url = $API_BASE . '/api/v1/dte/estado/' . $track_id;

echo "🔍 Consultando estado...\n";
echo "URL: $url\n\n";

// Hacer petición
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: ' . $API_KEY,
    ],
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    echo "❌ Error al consultar estado (HTTP $http_code)\n";
    echo "Respuesta: " . substr($response, 0, 500) . "\n\n";
    exit(1);
}

echo "✅ Consulta exitosa (HTTP $http_code)\n\n";

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  RESULTADO DE LA CONSULTA                                  ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// Intentar decodificar como JSON
$data = json_decode($response, true);

if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
    // Respuesta JSON
    foreach ($data as $key => $value) {
        if (is_array($value) || is_object($value)) {
            echo str_pad($key, 25) . ": " . json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo str_pad($key, 25) . ": " . $value . "\n";
        }
    }
} else {
    // Respuesta de texto/XML
    echo $response . "\n";
}

echo "\n═══════════════════════════════════════════════════════════\n\n";
