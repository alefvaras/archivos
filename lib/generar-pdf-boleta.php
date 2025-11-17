<?php
/**
 * Generador de PDF para Boletas Electrónicas
 * Formato profesional compatible con SII Chile
 * Inspirado en LibreDTE y mejores prácticas
 */

require_once(__DIR__ . '/fpdf.php');
require_once(__DIR__ . '/generar-timbre-pdf417.php');

class BoletaPDF extends FPDF {
    protected $datos_boleta;
    protected $dte_xml;
    protected $xml;

    // Ancho de ticket 80mm
    const ANCHO_TICKET = 80;
    const MARGEN_IZQUIERDO = 5;
    const MARGEN_DERECHO = 5;
    const ANCHO_UTIL = 70; // 80 - 5 - 5

    public function __construct($datos_boleta, $dte_xml) {
        // Usar tamaño A4 height (297mm) que es suficiente para cualquier boleta
        // El ancho es 80mm (ticket térmico estándar)
        parent::__construct('P', 'mm', array(self::ANCHO_TICKET, 297));

        $this->SetMargins(self::MARGEN_IZQUIERDO, 5, self::MARGEN_DERECHO);
        $this->SetAutoPageBreak(false);

        $this->datos_boleta = $datos_boleta;
        $this->dte_xml = $dte_xml;
        $this->xml = simplexml_load_string($dte_xml);
    }

    /**
     * Convertir texto UTF-8 a ISO-8859-1 para FPDF
     */
    private function utf8ToLatin1($text) {
        if (empty($text)) return '';
        return mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
    }

    /**
     * Generar boleta completa
     */
    public function generarBoleta() {
        $this->AddPage();

        // Generar todo el contenido
        $this->encabezadoEmisor();
        $this->tipoDTE();
        $this->datosReceptor();
        $this->detallesItems();
        $this->totales();
        $this->timbraje();
        $this->piePagina();
    }

    /**
     * Agregar logo de la empresa
     */
    private function agregarLogo($logo_url) {
        try {
            // Intentar obtener el archivo del logo
            $logo_path = $this->descargarImagen($logo_url);

            if (!$logo_path || !file_exists($logo_path)) {
                return; // Si no se puede obtener el logo, continuar sin él
            }

            // Obtener dimensiones de la imagen
            $image_info = @getimagesize($logo_path);
            if (!$image_info) {
                return;
            }

            list($width_px, $height_px) = $image_info;

            // Calcular dimensiones para que quede bien en el ticket
            // Ancho máximo: 50mm (dejamos margen a los lados)
            // Alto máximo: 20mm
            $max_width = 50;
            $max_height = 20;

            $ratio = min($max_width / ($width_px / 3.78), $max_height / ($height_px / 3.78));
            $logo_width = ($width_px / 3.78) * $ratio;
            $logo_height = ($height_px / 3.78) * $ratio;

            // Centrar el logo
            $x = (self::ANCHO_TICKET - $logo_width) / 2;
            $y = $this->GetY();

            // Insertar imagen
            $this->Image($logo_path, $x, $y, $logo_width, $logo_height);

            // Mover cursor después del logo
            $this->SetY($y + $logo_height + 2);

        } catch (Exception $e) {
            // Si hay error al cargar el logo, continuar sin él
            error_log('Error al cargar logo en PDF: ' . $e->getMessage());
        }
    }

    /**
     * Descargar imagen desde URL o usar ruta local
     */
    private function descargarImagen($url) {
        // Si es una ruta local absoluta
        if (file_exists($url)) {
            return $url;
        }

        // Si es URL de WordPress (uploads)
        $upload_dir = wp_upload_dir();
        if (strpos($url, $upload_dir['baseurl']) !== false) {
            $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);
            if (file_exists($file_path)) {
                return $file_path;
            }
        }

        // Intentar descargar la imagen temporalmente
        try {
            $temp_file = tempnam(sys_get_temp_dir(), 'logo_') . '.jpg';
            $image_data = @file_get_contents($url);

            if ($image_data !== false) {
                file_put_contents($temp_file, $image_data);
                return $temp_file;
            }
        } catch (Exception $e) {
            error_log('Error descargando logo: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Encabezado con datos del emisor
     */
    private function encabezadoEmisor() {
        $emisor = $this->datos_boleta['Documento']['Encabezado']['Emisor'];

        // Logo de la empresa (si está configurado)
        $logo_url = get_option('simple_dte_logo_url');
        if (!empty($logo_url)) {
            $this->agregarLogo($logo_url);
        }

        // Nombre empresa
        $this->SetFont('Arial', 'B', 11);
        $razon_social = $emisor['RazonSocialBoleta'] ?? $emisor['RazonSocial'] ?? 'EMPRESA';
        $this->MultiCell(self::ANCHO_UTIL, 4, $this->utf8ToLatin1($razon_social), 0, 'C');

        // RUT
        $this->SetFont('Arial', '', 9);
        $this->Cell(self::ANCHO_UTIL, 4, 'RUT: ' . ($emisor['Rut'] ?? ''), 0, 1, 'C');

        // Giro
        if (isset($emisor['GiroBoleta']) && !empty($emisor['GiroBoleta'])) {
            $this->SetFont('Arial', '', 8);
            $this->MultiCell(self::ANCHO_UTIL, 3, $this->utf8ToLatin1($emisor['GiroBoleta']), 0, 'C');
        }

        // Dirección
        if (isset($emisor['DireccionOrigen']) && !empty($emisor['DireccionOrigen'])) {
            $this->SetFont('Arial', '', 7);
            $direccion = $emisor['DireccionOrigen'];
            if (isset($emisor['ComunaOrigen'])) {
                $direccion .= ', ' . $emisor['ComunaOrigen'];
            }
            $this->MultiCell(self::ANCHO_UTIL, 3, $this->utf8ToLatin1($direccion), 0, 'C');
        }

        $this->Ln(3);
    }

    /**
     * Tipo de DTE y folio
     */
    private function tipoDTE() {
        $idDoc = $this->datos_boleta['Documento']['Encabezado']['IdentificacionDTE'];

        // Recuadro con tipo y folio
        $this->SetLineWidth(0.3);
        $y_inicio = $this->GetY();
        $this->Rect(self::MARGEN_IZQUIERDO + 5, $y_inicio, self::ANCHO_UTIL - 10, 14);

        $this->SetY($y_inicio + 2);

        // Tipo de documento
        $tipo_doc = $idDoc['TipoDTE'];
        $nombre_doc = 'BOLETA ELECTRÓNICA';

        switch ($tipo_doc) {
            case 39:
                $nombre_doc = 'BOLETA ELECTRÓNICA';
                break;
            case 41:
                $nombre_doc = 'BOLETA EXENTA ELECTRÓNICA';
                break;
            case 61:
                $nombre_doc = 'NOTA DE CRÉDITO ELECTRÓNICA';
                break;
            case 56:
                $nombre_doc = 'NOTA DE DÉBITO ELECTRÓNICA';
                break;
        }

        $this->SetFont('Arial', 'B', 9);
        $this->Cell(self::ANCHO_UTIL, 4, $this->utf8ToLatin1($nombre_doc), 0, 1, 'C');

        // Folio
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(self::ANCHO_UTIL, 5, $this->utf8ToLatin1('N° ') . $idDoc['Folio'], 0, 1, 'C');

        // Fecha
        $this->SetFont('Arial', '', 8);
        $fecha = date('d/m/Y', strtotime($idDoc['FechaEmision']));
        $this->Cell(self::ANCHO_UTIL, 3, $fecha, 0, 1, 'C');

        $this->Ln(3);
    }

    /**
     * Datos del receptor/cliente
     */
    private function datosReceptor() {
        $receptor = $this->datos_boleta['Documento']['Encabezado']['Receptor'];

        $this->SetFont('Arial', 'B', 8);
        $this->Cell(self::ANCHO_UTIL, 4, 'DATOS DEL CLIENTE', 0, 1, 'L');

        $this->Ln(1);

        $this->SetFont('Arial', '', 8);

        // RUT
        $this->Cell(18, 3, 'RUT:', 0, 0, 'L');
        $this->Cell(0, 3, $receptor['Rut'] ?? '', 0, 1, 'L');

        // Razón Social
        $this->Cell(18, 3, $this->utf8ToLatin1('Señor(a):'), 0, 0, 'L');
        $this->MultiCell(self::ANCHO_UTIL - 18, 3, $this->utf8ToLatin1($receptor['RazonSocial'] ?? 'Cliente'), 0, 'L');

        // Dirección (si existe)
        if (isset($receptor['Direccion']) && !empty($receptor['Direccion'])) {
            $this->Cell(18, 3, $this->utf8ToLatin1('Dirección:'), 0, 0, 'L');
            $direccion = $receptor['Direccion'];
            if (isset($receptor['Comuna']) && !empty($receptor['Comuna'])) {
                $direccion .= ', ' . $receptor['Comuna'];
            }
            $this->MultiCell(self::ANCHO_UTIL - 18, 3, $this->utf8ToLatin1($direccion), 0, 'L');
        }

        $this->Ln(2);

        // Línea separadora
        $this->SetLineWidth(0.1);
        $this->Line(self::MARGEN_IZQUIERDO, $this->GetY(), self::ANCHO_TICKET - self::MARGEN_DERECHO, $this->GetY());
        $this->Ln(2);
    }

    /**
     * Detalles de items
     */
    private function detallesItems() {
        $this->SetFont('Arial', 'B', 7);

        // Encabezado de tabla
        $this->Cell(7, 3, 'Cant', 0, 0, 'C');
        $this->Cell(38, 3, $this->utf8ToLatin1('Descripción'), 0, 0, 'L');
        $this->Cell(12, 3, 'Precio', 0, 0, 'R');
        $this->Cell(13, 3, 'Total', 0, 1, 'R');

        // Línea
        $this->SetLineWidth(0.1);
        $this->Line(self::MARGEN_IZQUIERDO, $this->GetY(), self::ANCHO_TICKET - self::MARGEN_DERECHO, $this->GetY());
        $this->Ln(1);

        $this->SetFont('Arial', '', 7);

        // Items
        $detalles = $this->datos_boleta['Documento']['Detalles'];
        if (isset($detalles[0])) {
            // Múltiples items
            foreach ($detalles as $item) {
                $this->imprimirItem($item);
            }
        } else {
            // Un solo item
            $this->imprimirItem($detalles);
        }

        $this->Ln(2);
    }

    /**
     * Imprimir un item
     */
    private function imprimirItem($item) {
        $nombre = $item['Nombre'] ?? $item['NmbItem'] ?? 'Item';
        $cantidad = $item['Cantidad'] ?? 1;
        $precio = $item['Precio'] ?? 0;
        $monto = $item['MontoItem'] ?? 0;

        $y_inicio = $this->GetY();

        // Cantidad
        $this->Cell(7, 3, $cantidad, 0, 0, 'C');

        // Descripción (puede ser multilínea)
        $x_desc = $this->GetX();
        $y_desc = $this->GetY();

        $this->MultiCell(38, 3, $this->utf8ToLatin1($nombre), 0, 'L');

        $y_final = $this->GetY();
        $altura_desc = $y_final - $y_desc;

        // Volver para imprimir precio y total
        $this->SetXY($x_desc + 38, $y_desc);

        // Precio unitario
        $this->Cell(12, 3, '$' . number_format($precio, 0, ',', '.'), 0, 0, 'R');

        // Total
        $this->Cell(13, 3, '$' . number_format($monto, 0, ',', '.'), 0, 0, 'R');

        // Mover Y al final de la descripción si fue multilínea
        $this->SetY(max($y_desc + 3, $y_final));

        // Descripción adicional (si existe)
        if (isset($item['Descripcion']) && !empty($item['Descripcion'])) {
            $this->SetFont('Arial', 'I', 6);
            $this->Cell(7, 2, '', 0, 0);
            $this->MultiCell(63, 2, $this->utf8ToLatin1($item['Descripcion']), 0, 'L');
            $this->SetFont('Arial', '', 7);
        }
    }

    /**
     * Totales
     */
    private function totales() {
        $totales = $this->datos_boleta['Documento']['Encabezado']['Totales'];

        // Línea separadora
        $this->SetLineWidth(0.1);
        $this->Line(self::MARGEN_IZQUIERDO, $this->GetY(), self::ANCHO_TICKET - self::MARGEN_DERECHO, $this->GetY());
        $this->Ln(2);

        $this->SetFont('Arial', '', 8);

        // Monto neto (si existe)
        if (isset($totales['MontoNeto']) && $totales['MontoNeto'] > 0) {
            $this->Cell(50, 4, 'NETO:', 0, 0, 'R');
            $this->Cell(20, 4, '$' . number_format($totales['MontoNeto'], 0, ',', '.'), 0, 1, 'R');
        }

        // IVA (si existe)
        if (isset($totales['IVA']) && $totales['IVA'] > 0) {
            $this->Cell(50, 4, 'IVA (19%):', 0, 0, 'R');
            $this->Cell(20, 4, '$' . number_format($totales['IVA'], 0, ',', '.'), 0, 1, 'R');
        }

        // Monto exento (si existe)
        if (isset($totales['MontoExento']) && $totales['MontoExento'] > 0) {
            $this->Cell(50, 4, 'EXENTO:', 0, 0, 'R');
            $this->Cell(20, 4, '$' . number_format($totales['MontoExento'], 0, ',', '.'), 0, 1, 'R');
        }

        // Total
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(50, 5, 'TOTAL:', 0, 0, 'R');
        $this->Cell(20, 5, '$' . number_format($totales['MontoTotal'], 0, ',', '.'), 0, 1, 'R');

        $this->Ln(3);
    }

    /**
     * Timbraje electrónico con código PDF417
     */
    private function timbraje() {
        // Línea separadora
        $this->SetLineWidth(0.1);
        $this->Line(self::MARGEN_IZQUIERDO, $this->GetY(), self::ANCHO_TICKET - self::MARGEN_DERECHO, $this->GetY());
        $this->Ln(2);

        $this->SetFont('Arial', 'B', 7);
        $this->Cell(self::ANCHO_UTIL, 3, $this->utf8ToLatin1('TIMBRE ELECTRÓNICO SII'), 0, 1, 'C');
        $this->Ln(2);

        // Generar código PDF417 del timbre
        try {
            // Generar imagen PDF417
            $imagen_pdf417 = generar_timbre_pdf417($this->dte_xml, null, [
                'columns' => 10,
                'security_level' => 5,
                'scale' => 1,
                'ratio' => 2,
                'padding' => 1,
            ]);

            if ($imagen_pdf417) {
                // Guardar temporalmente
                $temp_file = sys_get_temp_dir() . '/timbre_' . uniqid() . '.png';
                file_put_contents($temp_file, $imagen_pdf417);

                // Obtener dimensiones
                $img_info = getimagesize($temp_file);
                $img_width_px = $img_info[0];
                $img_height_px = $img_info[1];

                // Convertir a mm (96 DPI)
                $img_width_mm = ($img_width_px * 25.4) / 96;
                $img_height_mm = ($img_height_px * 25.4) / 96;

                // Ajustar al ancho disponible (máximo 65mm)
                $max_width = 65;
                if ($img_width_mm > $max_width) {
                    $ratio = $max_width / $img_width_mm;
                    $img_width_mm = $max_width;
                    $img_height_mm *= $ratio;
                }

                // Centrar horizontalmente
                $x = (self::ANCHO_TICKET - $img_width_mm) / 2;

                // Agregar imagen
                $this->Image($temp_file, $x, $this->GetY(), $img_width_mm, $img_height_mm, 'PNG');
                $this->SetY($this->GetY() + $img_height_mm + 2);

                // Eliminar temporal
                @unlink($temp_file);
            } else {
                $this->mostrarInfoTimbreBasica();
            }
        } catch (Exception $e) {
            error_log("Error generando PDF417: " . $e->getMessage());
            $this->mostrarInfoTimbreBasica();
        }

        $this->Ln(2);
    }

    /**
     * Mostrar información básica del timbre (fallback)
     */
    private function mostrarInfoTimbreBasica() {
        $this->SetFont('Arial', '', 6);

        if ($this->xml && $this->xml->Documento) {
            $folio = (string) $this->xml->Documento->Encabezado->IdDoc->Folio;
            $this->Cell(self::ANCHO_UTIL, 3, 'Folio: ' . $folio, 0, 1, 'C');

            $fecha_emision = (string) $this->xml->Documento->Encabezado->IdDoc->FchEmis;
            $this->Cell(self::ANCHO_UTIL, 3, 'Fecha: ' . date('d/m/Y', strtotime($fecha_emision)), 0, 1, 'C');

            $rut_emisor = (string) $this->xml->Documento->Encabezado->Emisor->RUTEmisor;
            $this->Cell(self::ANCHO_UTIL, 3, 'RUT: ' . $rut_emisor, 0, 1, 'C');

            $total = (int) $this->xml->Documento->Encabezado->Totales->MntTotal;
            $this->Cell(self::ANCHO_UTIL, 3, 'Monto: $' . number_format($total, 0, ',', '.'), 0, 1, 'C');
        } else {
            $this->Cell(self::ANCHO_UTIL, 3, $this->utf8ToLatin1('Timbre Electrónico'), 0, 1, 'C');
        }
    }

    /**
     * Pie de página
     */
    private function piePagina() {
        // Línea separadora
        $this->SetLineWidth(0.1);
        $this->Line(self::MARGEN_IZQUIERDO, $this->GetY(), self::ANCHO_TICKET - self::MARGEN_DERECHO, $this->GetY());
        $this->Ln(2);

        // Leyenda SII
        $this->SetFont('Arial', '', 6);
        $this->MultiCell(self::ANCHO_UTIL, 2.5, $this->utf8ToLatin1('Documento Tributario Electrónico generado de acuerdo a lo establecido por el Servicio de Impuestos Internos (SII)'), 0, 'C');

        $this->Ln(1);

        // URL verificación
        $this->SetFont('Arial', '', 6);
        $this->Cell(self::ANCHO_UTIL, 2, 'www.sii.cl', 0, 1, 'C');

        $this->Ln(2);
    }
}

/**
 * Función principal para generar PDF de boleta con tamaño dinámico
 */
function generar_pdf_boleta($datos_boleta, $dte_xml, $output_path = null) {
    try {
        // PRIMERA PASADA: Calcular altura necesaria
        $pdf_temp = new BoletaPDF($datos_boleta, $dte_xml);
        $pdf_temp->generarBoleta();
        $altura_necesaria = $pdf_temp->GetY() + 10; // +10mm margen inferior

        // Limitar altura a valores razonables
        $altura_necesaria = max(100, min($altura_necesaria, 400));

        // SEGUNDA PASADA: Crear PDF final con altura exacta
        $pdf_final = new BoletaPDFFinal($datos_boleta, $dte_xml, $altura_necesaria);
        $pdf_final->generarBoleta();

        if ($output_path) {
            $pdf_final->Output('F', $output_path);
            return $output_path;
        } else {
            return $pdf_final->Output('S');
        }
    } catch (Exception $e) {
        error_log("Error generando PDF: " . $e->getMessage());
        return false;
    }
}

/**
 * Clase para PDF final con altura ajustada
 */
class BoletaPDFFinal extends BoletaPDF {
    private $altura_custom;

    public function __construct($datos_boleta, $dte_xml, $altura) {
        $this->altura_custom = $altura;

        // Crear con altura personalizada
        FPDF::__construct('P', 'mm', array(BoletaPDF::ANCHO_TICKET, $altura));

        $this->SetMargins(BoletaPDF::MARGEN_IZQUIERDO, 5, BoletaPDF::MARGEN_DERECHO);
        $this->SetAutoPageBreak(false);

        $this->datos_boleta = $datos_boleta;
        $this->dte_xml = $dte_xml;
        $this->xml = simplexml_load_string($dte_xml);
    }
}
