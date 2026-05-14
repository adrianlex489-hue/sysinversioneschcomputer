-- ============================================================
-- Tabla de permisos granulares por usuario | Botica 2026
-- Ejecutar en phpMyAdmin sobre la BD bdbotica
-- ============================================================

CREATE TABLE IF NOT EXISTS `permisos_usuario` (
  `id`          int(11)      NOT NULL AUTO_INCREMENT,
  `id_usuario`  int(11)      NOT NULL,
  `modulo`      varchar(60)  NOT NULL COMMENT 'Clave del módulo, ej: ventas, compras, caja',
  `permitido`   tinyint(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_usuario_modulo` (`id_usuario`, `modulo`),
  CONSTRAINT `fk_permiso_usuario` FOREIGN KEY (`id_usuario`)
    REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
