-- ============================================================
-- Tabla de Auditoría | SysInversiones CH Computer
-- Ejecutar en phpMyAdmin sobre la BD: bdinversioneschcomputer
-- ============================================================

CREATE TABLE IF NOT EXISTS `auditoria` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `id_usuario`  INT(11)      NOT NULL                  COMMENT 'Usuario que realizó la acción',
  `modulo`      VARCHAR(60)  NOT NULL                  COMMENT 'productos, ventas, compras, caja, usuarios, servicios, empresa, inventario',
  `accion`      VARCHAR(40)  NOT NULL                  COMMENT 'crear, editar, eliminar, ajuste, anular, apertura, cierre, permisos, cambio_rol',
  `tabla`       VARCHAR(60)  DEFAULT NULL              COMMENT 'Tabla de BD afectada',
  `id_registro` INT(11)      DEFAULT NULL              COMMENT 'ID del registro afectado',
  `campo`       VARCHAR(80)  DEFAULT NULL              COMMENT 'Campo específico modificado',
  `valor_antes` TEXT         DEFAULT NULL              COMMENT 'Valor anterior (puede ser JSON para múltiples campos)',
  `valor_nuevo` TEXT         DEFAULT NULL              COMMENT 'Valor nuevo (puede ser JSON para múltiples campos)',
  `descripcion` VARCHAR(255) DEFAULT NULL              COMMENT 'Descripción legible del evento',
  `ip`          VARCHAR(45)  DEFAULT NULL              COMMENT 'IP del cliente',
  `fecha`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_usuario`  (`id_usuario`),
  INDEX `idx_modulo`   (`modulo`),
  INDEX `idx_accion`   (`accion`),
  INDEX `idx_fecha`    (`fecha`),
  INDEX `idx_tabla_id` (`tabla`, `id_registro`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci
  COMMENT='Registro de auditoría de acciones del sistema';
