-- ============================================================
-- SCHEMA: Sistema de Boletas Electrónicas
-- Base de datos para gestión de DTEs, folios, y clientes
-- Compatible con MySQL 5.7+ y PostgreSQL 12+
-- ============================================================

-- Tabla de Clientes
CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rut VARCHAR(12) NOT NULL UNIQUE,
    razon_social VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    direccion VARCHAR(255),
    comuna VARCHAR(100),
    ciudad VARCHAR(100),
    telefono VARCHAR(20),
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_rut (rut),
    INDEX idx_email (email),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de CAFs (Código de Autorización de Folios)
CREATE TABLE IF NOT EXISTS cafs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_dte INT NOT NULL,                      -- 39=Boleta, 61=NC, 56=ND, etc.
    folio_desde INT NOT NULL,
    folio_hasta INT NOT NULL,
    fecha_autorizacion DATE NOT NULL,
    archivo_caf TEXT NOT NULL,                   -- Contenido del XML del CAF
    archivo_nombre VARCHAR(255),
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_tipo_dte (tipo_dte),
    INDEX idx_activo (activo),
    UNIQUE KEY unique_rango (tipo_dte, folio_desde, folio_hasta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de Folios Usados
CREATE TABLE IF NOT EXISTS folios_usados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_dte INT NOT NULL,                      -- 39=Boleta, 61=NC, 56=ND, etc.
    folio INT NOT NULL,
    caf_id INT,
    usado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_folio (tipo_dte, folio),
    FOREIGN KEY (caf_id) REFERENCES cafs(id) ON DELETE SET NULL,
    INDEX idx_tipo_dte (tipo_dte),
    INDEX idx_folio (folio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de Boletas/DTEs
CREATE TABLE IF NOT EXISTS boletas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_dte INT NOT NULL,                      -- 39=Boleta, 61=NC, 56=ND, etc.
    folio INT NOT NULL,
    fecha_emision DATE NOT NULL,

    -- Datos del emisor
    rut_emisor VARCHAR(12) NOT NULL,
    razon_social_emisor VARCHAR(255) NOT NULL,

    -- Datos del receptor
    cliente_id INT,
    rut_receptor VARCHAR(12) NOT NULL,
    razon_social_receptor VARCHAR(255) NOT NULL,
    email_receptor VARCHAR(255),

    -- Montos
    monto_neto DECIMAL(15,2) DEFAULT 0,
    monto_iva DECIMAL(15,2) DEFAULT 0,
    monto_exento DECIMAL(15,2) DEFAULT 0,
    monto_total DECIMAL(15,2) NOT NULL,

    -- Archivos generados
    xml_dte MEDIUMTEXT,                          -- XML del DTE firmado
    pdf_generado BOOLEAN DEFAULT FALSE,
    pdf_path VARCHAR(500),

    -- Envío al SII
    track_id VARCHAR(50),                        -- Track ID del SII
    estado_sii VARCHAR(20),                      -- REC, EPR, RCH, RPR
    fecha_envio_sii TIMESTAMP,
    respuesta_sii TEXT,                          -- JSON con respuesta completa

    -- Email
    email_enviado BOOLEAN DEFAULT FALSE,
    fecha_envio_email TIMESTAMP,

    -- Auditoría
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_dte (tipo_dte, folio),
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
    INDEX idx_tipo_dte (tipo_dte),
    INDEX idx_folio (folio),
    INDEX idx_fecha_emision (fecha_emision),
    INDEX idx_rut_receptor (rut_receptor),
    INDEX idx_track_id (track_id),
    INDEX idx_estado_sii (estado_sii)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de Items/Detalles de Boletas
CREATE TABLE IF NOT EXISTS boleta_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    boleta_id INT NOT NULL,
    linea INT NOT NULL,                          -- Número de línea en el DTE

    nombre VARCHAR(255) NOT NULL,
    descripcion TEXT,
    cantidad DECIMAL(10,2) NOT NULL,
    unidad_medida VARCHAR(10) DEFAULT 'un',
    precio_unitario DECIMAL(15,2) NOT NULL,
    monto_item DECIMAL(15,2) NOT NULL,
    indicador_exento INT DEFAULT 0,              -- 0=Afecto, 1=Exento

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (boleta_id) REFERENCES boletas(id) ON DELETE CASCADE,
    INDEX idx_boleta_id (boleta_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de Logs del Sistema
CREATE TABLE IF NOT EXISTS logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nivel VARCHAR(20) NOT NULL,                  -- INFO, WARNING, ERROR, DEBUG
    operacion VARCHAR(50) NOT NULL,              -- generar, enviar, consultar, email
    mensaje TEXT NOT NULL,
    contexto JSON,                                -- Datos adicionales en formato JSON

    -- Relacionar con boleta si aplica
    boleta_id INT,
    tipo_dte INT,
    folio INT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (boleta_id) REFERENCES boletas(id) ON DELETE SET NULL,
    INDEX idx_nivel (nivel),
    INDEX idx_operacion (operacion),
    INDEX idx_created_at (created_at),
    INDEX idx_boleta_id (boleta_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- VISTAS ÚTILES
-- ============================================================

-- Vista de folios disponibles por CAF
CREATE OR REPLACE VIEW v_folios_disponibles AS
SELECT
    c.id AS caf_id,
    c.tipo_dte,
    c.folio_desde,
    c.folio_hasta,
    (c.folio_hasta - c.folio_desde + 1) AS total_folios,
    COUNT(f.folio) AS folios_usados,
    (c.folio_hasta - c.folio_desde + 1 - COUNT(f.folio)) AS folios_disponibles,
    c.fecha_autorizacion,
    c.activo
FROM cafs c
LEFT JOIN folios_usados f ON c.id = f.caf_id AND c.tipo_dte = f.tipo_dte
WHERE c.activo = TRUE
GROUP BY c.id, c.tipo_dte, c.folio_desde, c.folio_hasta, c.fecha_autorizacion, c.activo;

-- Vista de resumen de boletas por estado
CREATE OR REPLACE VIEW v_resumen_boletas AS
SELECT
    DATE(fecha_emision) AS fecha,
    tipo_dte,
    estado_sii,
    COUNT(*) AS cantidad,
    SUM(monto_total) AS monto_total,
    SUM(CASE WHEN email_enviado = TRUE THEN 1 ELSE 0 END) AS emails_enviados
FROM boletas
GROUP BY DATE(fecha_emision), tipo_dte, estado_sii;

-- Vista de clientes con estadísticas
CREATE OR REPLACE VIEW v_clientes_estadisticas AS
SELECT
    c.id,
    c.rut,
    c.razon_social,
    c.email,
    COUNT(b.id) AS total_boletas,
    SUM(b.monto_total) AS monto_total_facturado,
    MAX(b.fecha_emision) AS ultima_compra,
    c.activo
FROM clientes c
LEFT JOIN boletas b ON c.id = b.cliente_id
GROUP BY c.id, c.rut, c.razon_social, c.email, c.activo;

-- ============================================================
-- DATOS INICIALES (OPCIONAL)
-- ============================================================

-- Cliente genérico para ventas sin datos específicos
INSERT IGNORE INTO clientes (rut, razon_social, email, activo)
VALUES ('66666666-6', 'Cliente Final', NULL, TRUE);

-- ============================================================
-- PROCEDIMIENTOS ALMACENADOS
-- ============================================================

DELIMITER $$

-- Obtener próximo folio disponible
CREATE PROCEDURE IF NOT EXISTS sp_obtener_proximo_folio(
    IN p_tipo_dte INT,
    OUT p_folio INT,
    OUT p_caf_id INT
)
BEGIN
    DECLARE v_folio_siguiente INT;
    DECLARE v_caf_id INT;
    DECLARE v_folio_hasta INT;

    -- Obtener el CAF activo con folios disponibles
    SELECT id, folio_hasta INTO v_caf_id, v_folio_hasta
    FROM cafs
    WHERE tipo_dte = p_tipo_dte AND activo = TRUE
    ORDER BY folio_desde
    LIMIT 1;

    IF v_caf_id IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No hay CAFs disponibles para este tipo de DTE';
    END IF;

    -- Obtener el último folio usado de este CAF
    SELECT COALESCE(MAX(folio), (SELECT folio_desde - 1 FROM cafs WHERE id = v_caf_id))
    INTO v_folio_siguiente
    FROM folios_usados
    WHERE tipo_dte = p_tipo_dte AND caf_id = v_caf_id;

    -- Siguiente folio
    SET p_folio = v_folio_siguiente + 1;
    SET p_caf_id = v_caf_id;

    -- Verificar que no se exceda el rango
    IF p_folio > v_folio_hasta THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Se agotaron los folios del CAF actual';
    END IF;

    -- Registrar folio como usado
    INSERT INTO folios_usados (tipo_dte, folio, caf_id, usado_en)
    VALUES (p_tipo_dte, p_folio, p_caf_id, NOW());
END$$

DELIMITER ;

-- ============================================================
-- FIN DEL SCHEMA
-- ============================================================
