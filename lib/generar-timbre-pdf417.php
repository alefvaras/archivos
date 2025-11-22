<?php
/**
 * Generador de Timbre PDF417 para Boletas Electrónicas Chile
 * Genera el código de barras PDF417 del Timbre Electrónico (TED)
 * según especificaciones del SII
 */

require_once(__DIR__ . '/pdf417-simple-autoload.php');

use Le\PDF417\PDF417;
use Le\PDF417\Renderer\GdImageRenderer;

/**
 * Extrae el TED (Timbre Electrónico DTE) del XML
 *
 * @param string $dte_xml XML del DTE generado
 * @return string|false TED como string XML o false si no se encuentra
 */
function extraer_ted_xml($dte_xml) {
    try {
        // Parsear XML
        $xml = simplexml_load_string($dte_xml);

        if (!$xml) {
            error_log("Error: No se pudo parsear el XML del DTE");
            return false;
        }

        // Buscar el nodo TED
        $ted = null;

        // Intentar diferentes estructuras posibles
        if (isset($xml->Documento->TED)) {
            $ted = $xml->Documento->TED;
        } elseif (isset($xml->TED)) {
            $ted = $xml->TED;
        }

        if (!$ted) {
            error_log("Error: No se encontró el nodo TED en el XML");
            return false;
        }

        // Convertir el nodo TED a string XML
        $ted_string = $ted->asXML();

        return $ted_string;

    } catch (Exception $e) {
        error_log("Error al extraer TED: " . $e->getMessage());
        return false;
    }
}

/**
 * Genera imagen PNG del timbre PDF417
 *
 * @param string $dte_xml XML del DTE que contiene el TED
 * @param string|null $output_path Ruta donde guardar la imagen (null = retornar datos)
 * @param array $options Opciones de renderizado
 * @return string|bool Datos de la imagen PNG o false si hay error
 */
function generar_timbre_pdf417($dte_xml, $output_path = null, $options = []) {
    try {
        // Extraer TED del XML
        $ted_string = extraer_ted_xml($dte_xml);

        if (!$ted_string) {
            error_log("Error: No se pudo extraer el TED del XML");
            return false;
        }

        // Configuración por defecto según especificaciones SII
        $default_options = [
            'columns' => 15,           // Columnas del barcode
            'security_level' => 5,     // Nivel de corrección de errores (SII requiere nivel 5)
            'scale' => 2,              // Escala del barcode (pixels por módulo)
            'ratio' => 3,              // Ratio altura/ancho
            'padding' => 5,            // Padding alrededor del barcode
        ];

        $options = array_merge($default_options, $options);

        // Crear encoder PDF417
        $pdf417 = new PDF417();
        $pdf417->setColumns($options['columns']);
        $pdf417->setSecurityLevel($options['security_level']);

        // Codificar el TED
        $barcodeData = $pdf417->encode($ted_string);

        // Crear renderer GD (sin dependencias externas)
        $renderer = new GdImageRenderer([
            'format' => 'png',
            'scale' => $options['scale'],
            'ratio' => $options['ratio'],
            'padding' => $options['padding'],
            'color' => [0, 0, 0],        // Negro
            'bgColor' => [255, 255, 255], // Blanco
        ]);

        // Validar opciones
        $errors = $renderer->validateOptions();
        if (!empty($errors)) {
            error_log("Errores en opciones del renderer: " . implode(', ', $errors));
            return false;
        }

        // Generar imagen
        $imageData = $renderer->render($barcodeData);

        // Guardar o retornar
        if ($output_path) {
            $result = file_put_contents($output_path, $imageData);
            return $result !== false;
        } else {
            return $imageData;
        }

    } catch (Exception $e) {
        error_log("Error al generar timbre PDF417: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtiene información del TED para debugging
 *
 * @param string $dte_xml XML del DTE
 * @return array|false Array con información del TED o false
 */
function obtener_info_ted($dte_xml) {
    try {
        $xml = simplexml_load_string($dte_xml);

        if (!$xml || !isset($xml->Documento->TED)) {
            return false;
        }

        $ted = $xml->Documento->TED;
        $dd = $ted->DD;

        return [
            'rut_emisor' => (string) $dd->RE,
            'tipo_dte' => (int) $dd->TD,
            'folio' => (int) $dd->F,
            'fecha_emision' => (string) $dd->FE,
            'rut_receptor' => (string) $dd->RR,
            'razon_social_receptor' => (string) $dd->RSR,
            'monto_total' => (int) $dd->MNT,
            'item1' => (string) $dd->IT1,
            'timestamp' => (string) $dd->TSTED,
        ];

    } catch (Exception $e) {
        error_log("Error al obtener info TED: " . $e->getMessage());
        return false;
    }
}
