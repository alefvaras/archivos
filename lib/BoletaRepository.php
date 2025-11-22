<?php
/**
 * Repository para gestión de Boletas en base de datos
 * Maneja todas las operaciones CRUD para boletas, folios, y clientes
 */

require_once(__DIR__ . '/Database.php');

class BoletaRepository {
    private $db;

    public function __construct(Database $db = null) {
        $this->db = $db ?: Database::getInstance();
    }

    /**
     * Obtener o crear cliente por RUT
     */
    public function obtenerOCrearCliente($datos_cliente) {
        // Buscar cliente existente
        $cliente = $this->db->fetchOne(
            'SELECT * FROM clientes WHERE rut = ?',
            [$datos_cliente['rut']]
        );

        if ($cliente) {
            return $cliente['id'];
        }

        // Crear nuevo cliente
        $data = [
            'rut' => $datos_cliente['rut'],
            'razon_social' => $datos_cliente['razon_social'],
            'email' => $datos_cliente['email'] ?? null,
            'direccion' => $datos_cliente['direccion'] ?? null,
            'comuna' => $datos_cliente['comuna'] ?? null,
            'ciudad' => $datos_cliente['ciudad'] ?? null,
        ];

        return $this->db->insert('clientes', $data);
    }

    /**
     * Obtener próximo folio disponible
     */
    public function obtenerProximoFolio($tipo_dte) {
        try {
            // Buscar CAF activo con folios disponibles
            $caf = $this->db->fetchOne(
                'SELECT id, folio_desde, folio_hasta
                 FROM cafs
                 WHERE tipo_dte = ? AND activo = TRUE
                 ORDER BY folio_desde
                 LIMIT 1',
                [$tipo_dte]
            );

            if (!$caf) {
                throw new Exception("No hay CAFs disponibles para DTE tipo {$tipo_dte}");
            }

            // Obtener último folio usado de este CAF
            $ultimo_folio = $this->db->fetchOne(
                'SELECT MAX(folio) as ultimo
                 FROM folios_usados
                 WHERE tipo_dte = ? AND caf_id = ?',
                [$tipo_dte, $caf['id']]
            );

            $proximo_folio = $ultimo_folio && $ultimo_folio['ultimo']
                ? $ultimo_folio['ultimo'] + 1
                : $caf['folio_desde'];

            // Verificar que no exceda el rango
            if ($proximo_folio > $caf['folio_hasta']) {
                throw new Exception("Se agotaron los folios del CAF (Tipo {$tipo_dte})");
            }

            // Registrar folio como usado
            $this->db->insert('folios_usados', [
                'tipo_dte' => $tipo_dte,
                'folio' => $proximo_folio,
                'caf_id' => $caf['id']
            ]);

            return [
                'folio' => $proximo_folio,
                'caf_id' => $caf['id']
            ];

        } catch (Exception $e) {
            error_log("Error obteniendo próximo folio: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Guardar boleta en base de datos
     */
    public function guardarBoleta($datos_boleta, $xml_dte = null) {
        try {
            $this->db->beginTransaction();

            // Obtener o crear cliente
            $cliente_id = $this->obtenerOCrearCliente([
                'rut' => $datos_boleta['rut_receptor'],
                'razon_social' => $datos_boleta['razon_social_receptor'],
                'email' => $datos_boleta['email_receptor'] ?? null,
            ]);

            // Insertar boleta
            $boleta_data = [
                'tipo_dte' => $datos_boleta['tipo_dte'],
                'folio' => $datos_boleta['folio'],
                'fecha_emision' => $datos_boleta['fecha_emision'],
                'rut_emisor' => $datos_boleta['rut_emisor'],
                'razon_social_emisor' => $datos_boleta['razon_social_emisor'],
                'cliente_id' => $cliente_id,
                'rut_receptor' => $datos_boleta['rut_receptor'],
                'razon_social_receptor' => $datos_boleta['razon_social_receptor'],
                'email_receptor' => $datos_boleta['email_receptor'] ?? null,
                'monto_neto' => $datos_boleta['monto_neto'] ?? 0,
                'monto_iva' => $datos_boleta['monto_iva'] ?? 0,
                'monto_exento' => $datos_boleta['monto_exento'] ?? 0,
                'monto_total' => $datos_boleta['monto_total'],
                'xml_dte' => $xml_dte,
                'track_id' => $datos_boleta['track_id'] ?? null,
            ];

            $boleta_id = $this->db->insert('boletas', $boleta_data);

            // Insertar items si existen
            if (isset($datos_boleta['items']) && is_array($datos_boleta['items'])) {
                foreach ($datos_boleta['items'] as $index => $item) {
                    $this->db->insert('boleta_items', [
                        'boleta_id' => $boleta_id,
                        'linea' => $index + 1,
                        'nombre' => $item['nombre'],
                        'descripcion' => $item['descripcion'] ?? null,
                        'cantidad' => $item['cantidad'],
                        'unidad_medida' => $item['unidad_medida'] ?? 'un',
                        'precio_unitario' => $item['precio_unitario'],
                        'monto_item' => $item['monto_item'],
                        'indicador_exento' => $item['indicador_exento'] ?? 0,
                    ]);
                }
            }

            $this->db->commit();
            return $boleta_id;

        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error guardando boleta: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Actualizar estado SII de una boleta
     */
    public function actualizarEstadoSII($boleta_id, $track_id, $estado, $respuesta = null) {
        return $this->db->update(
            'boletas',
            [
                'track_id' => $track_id,
                'estado_sii' => $estado,
                'fecha_envio_sii' => date('Y-m-d H:i:s'),
                'respuesta_sii' => $respuesta ? json_encode($respuesta) : null,
            ],
            'id = ?',
            [$boleta_id]
        );
    }

    /**
     * Marcar email como enviado
     */
    public function marcarEmailEnviado($boleta_id) {
        return $this->db->update(
            'boletas',
            [
                'email_enviado' => true,
                'fecha_envio_email' => date('Y-m-d H:i:s'),
            ],
            'id = ?',
            [$boleta_id]
        );
    }

    /**
     * Marcar PDF como generado
     */
    public function marcarPDFGenerado($boleta_id, $pdf_path) {
        return $this->db->update(
            'boletas',
            [
                'pdf_generado' => true,
                'pdf_path' => $pdf_path,
            ],
            'id = ?',
            [$boleta_id]
        );
    }

    /**
     * Obtener boleta por ID
     */
    public function obtenerBoletaPorId($boleta_id) {
        return $this->db->fetchOne(
            'SELECT * FROM boletas WHERE id = ?',
            [$boleta_id]
        );
    }

    /**
     * Obtener boleta por folio
     */
    public function obtenerBoletaPorFolio($tipo_dte, $folio) {
        return $this->db->fetchOne(
            'SELECT * FROM boletas WHERE tipo_dte = ? AND folio = ?',
            [$tipo_dte, $folio]
        );
    }

    /**
     * Obtener items de una boleta
     */
    public function obtenerItemsBoleta($boleta_id) {
        return $this->db->fetchAll(
            'SELECT * FROM boleta_items WHERE boleta_id = ? ORDER BY linea',
            [$boleta_id]
        );
    }

    /**
     * Obtener folios disponibles por tipo DTE
     */
    public function obtenerFoliosDisponibles($tipo_dte) {
        return $this->db->fetchOne(
            'SELECT * FROM v_folios_disponibles WHERE tipo_dte = ?',
            [$tipo_dte]
        );
    }

    /**
     * Registrar CAF en la base de datos
     */
    public function registrarCAF($tipo_dte, $folio_desde, $folio_hasta, $xml_caf, $archivo_nombre) {
        return $this->db->insert('cafs', [
            'tipo_dte' => $tipo_dte,
            'folio_desde' => $folio_desde,
            'folio_hasta' => $folio_hasta,
            'fecha_autorizacion' => date('Y-m-d'),
            'archivo_caf' => $xml_caf,
            'archivo_nombre' => $archivo_nombre,
            'activo' => true,
        ]);
    }

    /**
     * Obtener CAF activo para un tipo de DTE
     */
    public function obtenerCAFActivo($tipo_dte) {
        return $this->db->fetchOne(
            'SELECT * FROM cafs WHERE tipo_dte = ? AND activo = TRUE ORDER BY folio_desde LIMIT 1',
            [$tipo_dte]
        );
    }

    /**
     * Registrar log
     */
    public function registrarLog($nivel, $operacion, $mensaje, $contexto = [], $boleta_id = null) {
        return $this->db->insert('logs', [
            'nivel' => $nivel,
            'operacion' => $operacion,
            'mensaje' => $mensaje,
            'contexto' => json_encode($contexto),
            'boleta_id' => $boleta_id,
            'tipo_dte' => $contexto['tipo_dte'] ?? null,
            'folio' => $contexto['folio'] ?? null,
        ]);
    }

    /**
     * Obtener estadísticas de boletas
     */
    public function obtenerEstadisticas($fecha_desde = null, $fecha_hasta = null) {
        $where = [];
        $params = [];

        if ($fecha_desde) {
            $where[] = 'fecha_emision >= ?';
            $params[] = $fecha_desde;
        }

        if ($fecha_hasta) {
            $where[] = 'fecha_emision <= ?';
            $params[] = $fecha_hasta;
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT
                    COUNT(*) as total_boletas,
                    SUM(monto_total) as monto_total,
                    SUM(CASE WHEN email_enviado = TRUE THEN 1 ELSE 0 END) as emails_enviados,
                    SUM(CASE WHEN pdf_generado = TRUE THEN 1 ELSE 0 END) as pdfs_generados,
                    COUNT(DISTINCT rut_receptor) as clientes_unicos
                FROM boletas
                {$whereClause}";

        return $this->db->fetchOne($sql, $params);
    }
}
