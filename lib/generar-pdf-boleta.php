<?php
/**
 * Generador de PDF para Boletas Electrónicas
 * Formato oficial SII Chile
 */

require_once(__DIR__ . '/fpdf.php');

class BoletaPDF extends FPDF {
    private $datos_boleta;
    private $dte_xml;

    public function __construct($datos_boleta, $dte_xml) {
        parent::__construct('P', 'mm', array(80, 200)); // Tamaño ticket 80mm ancho
        $this->datos_boleta = $datos_boleta;
        $this->dte_xml = $dte_xml;

        // Parse XML para obtener datos adicionales
        $this->xml = simplexml_load_string($dte_xml);
    }

    /**
     * Generar boleta completa
     */
    public function generarBoleta() {
        $this->AddPage();
        $this->SetAutoPageBreak(true, 10);

        // Encabezado con datos del emisor
        $this->encabezadoEmisor();

        // Tipo de documento y folio
        $this->tipoDTE();

        // Datos del receptor
        $this->datosReceptor();

        // Detalles de items
        $this->detallesItems();

        // Totales
        $this->totales();

        // Timbraje electrónico
        $this->timbraje();

        // Pie de página
        $this->piePagina();
    }

    /**
     * Encabezado con datos del emisor
     */
    private function encabezadoEmisor() {
        $emisor = $this->datos_boleta['Documento']['Encabezado']['Emisor'];

        // Nombre empresa
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 5, utf8_decode($emisor['RazonSocialBoleta'] ?? $emisor['RazonSocial']), 0, 1, 'C');

        // RUT
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 4, 'RUT: ' . $emisor['Rut'], 0, 1, 'C');

        // Giro
        if (isset($emisor['GiroBoleta'])) {
            $this->Cell(0, 4, utf8_decode($emisor['GiroBoleta']), 0, 1, 'C');
        }

        // Dirección
        if (isset($emisor['DireccionOrigen'])) {
            $this->SetFont('Arial', '', 8);
            $direccion = $emisor['DireccionOrigen'];
            if (isset($emisor['ComunaOrigen'])) {
                $direccion .= ', ' . $emisor['ComunaOrigen'];
            }
            $this->MultiCell(0, 3, utf8_decode($direccion), 0, 'C');
        }

        $this->Ln(2);
    }

    /**
     * Tipo de DTE y folio
     */
    private function tipoDTE() {
        $idDoc = $this->datos_boleta['Documento']['Encabezado']['IdentificacionDTE'];

        // Recuadro con tipo y folio
        $this->SetLineWidth(0.5);
        $this->Rect(10, $this->GetY(), 60, 15);

        $y_inicio = $this->GetY();

        // Tipo de documento
        $tipo_doc = $idDoc['TipoDTE'];
        $nombre_doc = 'BOLETA ELECTRONICA';

        switch ($tipo_doc) {
            case 39:
                $nombre_doc = 'BOLETA ELECTRONICA';
                break;
            case 41:
                $nombre_doc = 'BOLETA EXENTA ELECTRONICA';
                break;
            case 61:
                $nombre_doc = 'NOTA DE CREDITO ELECTRONICA';
                break;
            case 56:
                $nombre_doc = 'NOTA DE DEBITO ELECTRONICA';
                break;
        }

        $this->SetY($y_inicio + 2);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 4, utf8_decode($nombre_doc), 0, 1, 'C');

        // Folio
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 5, 'N° ' . $idDoc['Folio'], 0, 1, 'C');

        // Fecha
        $this->SetFont('Arial', '', 8);
        $fecha = date('d/m/Y', strtotime($idDoc['FechaEmision']));
        $this->Cell(0, 3, $fecha, 0, 1, 'C');

        $this->Ln(3);
    }

    /**
     * Datos del receptor/cliente
     */
    private function datosReceptor() {
        $receptor = $this->datos_boleta['Documento']['Encabezado']['Receptor'];

        $this->SetFont('Arial', 'B', 9);
        $this->Cell(0, 4, 'DATOS DEL CLIENTE', 0, 1, 'L');

        $this->SetFont('Arial', '', 8);

        // RUT
        $this->Cell(15, 3, 'RUT:', 0, 0, 'L');
        $this->Cell(0, 3, $receptor['Rut'], 0, 1, 'L');

        // Razón Social
        $this->Cell(15, 3, utf8_decode('Señor(a):'), 0, 0, 'L');
        $this->MultiCell(0, 3, utf8_decode($receptor['RazonSocial']), 0, 'L');

        // Dirección (si existe)
        if (isset($receptor['Direccion'])) {
            $this->Cell(15, 3, utf8_decode('Dirección:'), 0, 0, 'L');
            $direccion = $receptor['Direccion'];
            if (isset($receptor['Comuna'])) {
                $direccion .= ', ' . $receptor['Comuna'];
            }
            $this->MultiCell(0, 3, utf8_decode($direccion), 0, 'L');
        }

        $this->Ln(2);

        // Línea separadora
        $this->Line(5, $this->GetY(), 75, $this->GetY());
        $this->Ln(2);
    }

    /**
     * Detalles de items
     */
    private function detallesItems() {
        $this->SetFont('Arial', 'B', 8);

        // Encabezado de tabla
        $this->Cell(8, 4, 'Cant', 0, 0, 'C');
        $this->Cell(42, 4, utf8_decode('Descripción'), 0, 0, 'L');
        $this->Cell(15, 4, 'Precio', 0, 0, 'R');
        $this->Cell(15, 4, 'Total', 0, 1, 'R');

        // Línea
        $this->Line(5, $this->GetY(), 75, $this->GetY());
        $this->Ln(1);

        $this->SetFont('Arial', '', 8);

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
        $nombre = isset($item['Nombre']) ? $item['Nombre'] : $item['NmbItem'];
        $cantidad = $item['Cantidad'];
        $precio = $item['Precio'];
        $monto = $item['MontoItem'];

        // Cantidad
        $this->Cell(8, 4, $cantidad, 0, 0, 'C');

        // Descripción (puede ser multilínea)
        $x_actual = $this->GetX();
        $y_actual = $this->GetY();

        $this->MultiCell(42, 4, utf8_decode($nombre), 0, 'L');

        $y_despues = $this->GetY();
        $this->SetXY($x_actual + 42, $y_actual);

        // Precio unitario
        $this->Cell(15, 4, '$' . number_format($precio, 0, ',', '.'), 0, 0, 'R');

        // Total
        $this->Cell(15, 4, '$' . number_format($monto, 0, ',', '.'), 0, 1, 'R');

        // Ajustar Y si la descripción ocupó más líneas
        if ($y_despues > $this->GetY()) {
            $this->SetY($y_despues);
        }

        // Descripción adicional (si existe)
        if (isset($item['Descripcion']) && !empty($item['Descripcion'])) {
            $this->SetFont('Arial', 'I', 7);
            $this->Cell(8, 3, '', 0, 0);
            $this->MultiCell(62, 3, utf8_decode($item['Descripcion']), 0, 'L');
            $this->SetFont('Arial', '', 8);
        }
    }

    /**
     * Totales
     */
    private function totales() {
        $totales = $this->datos_boleta['Documento']['Encabezado']['Totales'];

        // Línea separadora
        $this->Line(5, $this->GetY(), 75, $this->GetY());
        $this->Ln(2);

        $this->SetFont('Arial', '', 9);

        // Monto neto (si existe)
        if (isset($totales['MontoNeto'])) {
            $this->Cell(50, 4, 'NETO:', 0, 0, 'R');
            $this->Cell(20, 4, '$' . number_format($totales['MontoNeto'], 0, ',', '.'), 0, 1, 'R');
        }

        // IVA (si existe)
        if (isset($totales['IVA'])) {
            $this->Cell(50, 4, 'IVA (19%):', 0, 0, 'R');
            $this->Cell(20, 4, '$' . number_format($totales['IVA'], 0, ',', '.'), 0, 1, 'R');
        }

        // Monto exento (si existe)
        if (isset($totales['MontoExento'])) {
            $this->Cell(50, 4, 'EXENTO:', 0, 0, 'R');
            $this->Cell(20, 4, '$' . number_format($totales['MontoExento'], 0, ',', '.'), 0, 1, 'R');
        }

        // Total
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(50, 6, 'TOTAL:', 0, 0, 'R');
        $this->Cell(20, 6, '$' . number_format($totales['MontoTotal'], 0, ',', '.'), 0, 1, 'R');

        $this->Ln(2);
    }

    /**
     * Timbraje electrónico
     */
    private function timbraje() {
        // Línea separadora
        $this->Line(5, $this->GetY(), 75, $this->GetY());
        $this->Ln(2);

        $this->SetFont('Arial', '', 7);

        // Timbre electrónico SII
        $this->Cell(0, 3, 'TIMBRE ELECTRONICO SII', 0, 1, 'C');

        // Folio
        $folio = (string) $this->xml->Documento->Encabezado->IdDoc->Folio;
        $this->Cell(0, 3, 'Folio: ' . $folio, 0, 1, 'C');

        // Fecha de emisión
        $fecha_emision = (string) $this->xml->Documento->Encabezado->IdDoc->FchEmis;
        $this->Cell(0, 3, 'Fecha: ' . date('d/m/Y', strtotime($fecha_emision)), 0, 1, 'C');

        // RUT emisor
        $rut_emisor = (string) $this->xml->Documento->Encabezado->Emisor->RUTEmisor;
        $this->Cell(0, 3, 'RUT: ' . $rut_emisor, 0, 1, 'C');

        // Total
        $total = (string) $this->xml->Documento->Encabezado->Totales->MntTotal;
        $this->Cell(0, 3, 'Monto: $' . number_format($total, 0, ',', '.'), 0, 1, 'C');

        $this->Ln(2);
    }

    /**
     * Pie de página
     */
    private function piePagina() {
        // Leyenda SII
        $this->SetFont('Arial', 'I', 6);
        $this->MultiCell(0, 3, utf8_decode('Documento Tributario Electrónico generado de acuerdo a lo establecido por el Servicio de Impuestos Internos (SII)'), 0, 'C');

        $this->Ln(1);

        // URL verificación (si corresponde)
        $this->SetFont('Arial', '', 6);
        $this->Cell(0, 3, 'www.sii.cl', 0, 1, 'C');
    }
}

/**
 * Función principal para generar PDF de boleta
 */
function generar_pdf_boleta($datos_boleta, $dte_xml, $output_path = null) {
    try {
        $pdf = new BoletaPDF($datos_boleta, $dte_xml);
        $pdf->generarBoleta();

        if ($output_path) {
            $pdf->Output('F', $output_path);
            return $output_path;
        } else {
            // Generar en memoria
            return $pdf->Output('S');
        }
    } catch (Exception $e) {
        error_log("Error generando PDF: " . $e->getMessage());
        return false;
    }
}
