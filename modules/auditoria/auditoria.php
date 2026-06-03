<?php
// ============================================================
// modules/auditoria/auditoria.php | SysInversiones CH Computer
// Módulo de Auditoría — solo accesible por Administrador
// ============================================================
$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';

if (!isset($pdo) || !($pdo instanceof PDO)) die('Error: Conexión BD no disponible.');
if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 1);
verificar_acceso([ROL_ADMINISTRADOR]);

// ── Cargar lista de usuarios para el filtro ───────────────────────────────────
$usuarios_lista = [];
try {
    $usuarios_lista = $pdo->query("SELECT id_usuario, nombre_completo, username FROM usuarios WHERE estado = 1 ORDER BY nombre_completo")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ── Verificar si la tabla auditoria existe ────────────────────────────────────
$tabla_existe = false;
try {
    $tabla_existe = (bool)$pdo->query("SHOW TABLES LIKE 'auditoria'")->fetchColumn();
} catch (PDOException $e) {}

$extra_css = '<link rel="stylesheet" href="/sysinversioneschcomputer/modules/auditoria/css/auditoria.css?v=' . time() . '">';
$extra_js  = '<script src="/sysinversioneschcomputer/modules/auditoria/js/auditoria.js?v=' . time() . '"></script>';

include $ruta_base . 'includes/header.php';
include $ruta_base . 'includes/sidebar.php';
?>

<div class="content-wrapper">

    <!-- ── CABECERA ── -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="page-header-audit d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
                <div>
                    <h4><i class="fas fa-shield-alt mr-2"></i>Auditoría del Sistema</h4>
                    <small>
                        <i class="fas fa-map-marker-alt mr-1"></i>
                        SysInversiones CH Computer &rsaquo; Seguridad &rsaquo; Auditoría
                    </small>
                </div>
                <div class="d-flex align-items-center" style="gap:8px;">
                    <div class="audit-export-group">
                        <button id="btn-exportar-csv" class="audit-export-btn audit-export-csv" title="Exportar a CSV">
                            <i class="fas fa-file-csv"></i>
                            <span>CSV</span>
                        </button>
                        <button id="btn-exportar-excel" class="audit-export-btn audit-export-excel" title="Exportar a Excel">
                            <i class="fas fa-file-excel"></i>
                            <span>Excel</span>
                        </button>
                        <button id="btn-exportar-pdf" class="audit-export-btn audit-export-pdf" title="Exportar a PDF">
                            <i class="fas fa-file-pdf"></i>
                            <span>PDF</span>
                        </button>
                    </div>
                    <span class="audit-results-badge" id="audit-total-badge">Cargando...</span>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">

            <?php if (!$tabla_existe): ?>
            <!-- ── ALERTA: tabla no existe ── -->
            <div class="alert" style="background:linear-gradient(135deg,#fef3c7,#fffbeb);border:1.5px solid #fde68a;border-left:5px solid #f59e0b;border-radius:10px;padding:18px 22px;">
                <div style="display:flex;align-items:flex-start;gap:14px;">
                    <i class="fas fa-exclamation-triangle" style="font-size:1.5rem;color:#f59e0b;flex-shrink:0;margin-top:2px;"></i>
                    <div>
                        <div style="font-weight:700;color:#92400e;font-size:.95rem;margin-bottom:6px;">Tabla de auditoría no encontrada</div>
                        <p style="color:#78350f;font-size:.85rem;margin:0 0 10px;">
                            La tabla <code>auditoria</code> no existe en la base de datos. Ejecuta el siguiente SQL en phpMyAdmin para crearla:
                        </p>
                        <pre style="background:#fff;border:1px solid #fde68a;border-radius:6px;padding:12px;font-size:.78rem;color:#334155;overflow-x:auto;margin:0;">CREATE TABLE `auditoria` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `id_usuario`  INT NOT NULL,
  `modulo`      VARCHAR(60)  NOT NULL COMMENT 'productos, ventas, caja, usuarios...',
  `accion`      VARCHAR(40)  NOT NULL COMMENT 'crear, editar, eliminar, ajuste, anular...',
  `tabla`       VARCHAR(60)  DEFAULT NULL,
  `id_registro` INT          DEFAULT NULL,
  `campo`       VARCHAR(80)  DEFAULT NULL,
  `valor_antes` TEXT         DEFAULT NULL,
  `valor_nuevo` TEXT         DEFAULT NULL,
  `descripcion` VARCHAR(255) DEFAULT NULL,
  `ip`          VARCHAR(45)  DEFAULT NULL,
  `fecha`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_usuario  (`id_usuario`),
  INDEX idx_modulo   (`modulo`),
  INDEX idx_accion   (`accion`),
  INDEX idx_fecha    (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish_ci;</pre>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── STATS ── -->
            <div class="row mb-4">
                <div class="col-6 col-md-3 mb-2">
                    <div class="audit-stat" style="background:linear-gradient(135deg,#1e3a8a,#2563eb);">
                        <i class="fas fa-calendar-day"></i>
                        <div>
                            <div class="stat-value" id="stat-hoy">—</div>
                            <div class="stat-label">Eventos hoy</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <div class="audit-stat" style="background:linear-gradient(135deg,#5b21b6,#7c3aed);">
                        <i class="fas fa-calendar-week"></i>
                        <div>
                            <div class="stat-value" id="stat-semana">—</div>
                            <div class="stat-label">Últimos 7 días</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <div class="audit-stat" style="background:linear-gradient(135deg,#0f766e,#14b8a6);">
                        <i class="fas fa-database"></i>
                        <div>
                            <div class="stat-value" id="stat-total">—</div>
                            <div class="stat-label">Total registros</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <div class="audit-stat" style="background:linear-gradient(135deg,#92400e,#f59e0b);">
                        <i class="fas fa-users"></i>
                        <div>
                            <div class="stat-value" id="stat-usuarios">—</div>
                            <div class="stat-label">Usuarios con actividad</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── FILTROS ── -->
            <div class="audit-filtros-card">
                <div class="audit-filtros-title">
                    <i class="fas fa-filter" style="color:#2563eb;"></i>
                    Filtros de búsqueda
                </div>
                <div class="audit-filtros-grid">

                    <!-- Módulo (modal picker) -->
                    <div class="form-group">
                        <label class="audit-form-label"><i class="fas fa-layer-group mr-1"></i>Módulo</label>
                        <input type="hidden" id="filtro_modulo" value="">
                        <button type="button" class="audit-picker-btn" id="btn-picker-modulo">
                            <span class="audit-picker-icon"><i class="fas fa-layer-group"></i></span>
                            <span class="audit-picker-text" id="txt-modulo">Todos los módulos</span>
                            <i class="fas fa-chevron-down audit-picker-arrow"></i>
                        </button>
                    </div>

                    <!-- Acción (modal picker) -->
                    <div class="form-group">
                        <label class="audit-form-label"><i class="fas fa-bolt mr-1"></i>Acción</label>
                        <input type="hidden" id="filtro_accion" value="">
                        <button type="button" class="audit-picker-btn" id="btn-picker-accion">
                            <span class="audit-picker-icon"><i class="fas fa-bolt"></i></span>
                            <span class="audit-picker-text" id="txt-accion">Todas las acciones</span>
                            <i class="fas fa-chevron-down audit-picker-arrow"></i>
                        </button>
                    </div>

                    <!-- Usuario (modal picker) -->
                    <div class="form-group">
                        <label class="audit-form-label"><i class="fas fa-user mr-1"></i>Usuario</label>
                        <input type="hidden" id="filtro_usuario" value="">
                        <button type="button" class="audit-picker-btn" id="btn-picker-usuario">
                            <span class="audit-picker-icon"><i class="fas fa-user"></i></span>
                            <span class="audit-picker-text" id="txt-usuario">Todos los usuarios</span>
                            <i class="fas fa-chevron-down audit-picker-arrow"></i>
                        </button>
                    </div>

                    <!-- Fecha desde -->
                    <div class="form-group">
                        <label class="audit-form-label"><i class="fas fa-calendar-alt mr-1"></i>Desde</label>
                        <input type="date" id="filtro_fecha_desde" class="audit-input">
                    </div>

                    <!-- Fecha hasta -->
                    <div class="form-group">
                        <label class="audit-form-label"><i class="fas fa-calendar-alt mr-1"></i>Hasta</label>
                        <input type="date" id="filtro_fecha_hasta" class="audit-input">
                    </div>

                    <!-- Búsqueda libre -->
                    <div class="form-group">
                        <label class="audit-form-label"><i class="fas fa-search mr-1"></i>Buscar</label>
                        <input type="text" id="filtro_buscar" class="audit-input" placeholder="Descripción, campo, valor...">
                    </div>

                    <!-- Botones -->
                    <div class="form-group d-flex flex-column" style="gap:6px;">
                        <label class="audit-form-label">&nbsp;</label>
                        <button id="btn-filtrar" class="audit-btn-filtrar">
                            <i class="fas fa-search"></i>Buscar
                        </button>
                    </div>
                    <div class="form-group d-flex flex-column" style="gap:6px;">
                        <label class="audit-form-label">&nbsp;</label>
                        <button id="btn-limpiar" class="audit-btn-limpiar">
                            <i class="fas fa-times"></i>Limpiar
                        </button>
                    </div>

                </div>
            </div>

            <!-- ── TABLA PRINCIPAL ── -->
            <div class="audit-card" id="audit-card">
                <div class="audit-card-header">
                    <h6><i class="fas fa-list mr-2"></i>Registro de Eventos</h6>
                    <small style="opacity:.75;font-size:.78rem;">
                        <i class="fas fa-info-circle mr-1"></i>
                        Mostrando los últimos 30 días por defecto
                    </small>
                </div>

                <div class="table-responsive">
                    <table class="table table-audit table-bordered table-hover table-sm mb-0">
                        <thead>
                            <tr>
                                <th style="width:130px;"><i class="fas fa-clock mr-1"></i>Fecha / Hora</th>
                                <th style="width:140px;"><i class="fas fa-user mr-1"></i>Usuario</th>
                                <th style="width:110px;"><i class="fas fa-layer-group mr-1"></i>Módulo</th>
                                <th style="width:100px;"><i class="fas fa-bolt mr-1"></i>Acción</th>
                                <th style="width:100px;"><i class="fas fa-columns mr-1"></i>Campo</th>
                                <th style="width:200px;"><i class="fas fa-exchange-alt mr-1"></i>Antes → Después</th>
                                <th><i class="fas fa-comment-alt mr-1"></i>Descripción / IP</th>
                                <th style="width:60px;" class="text-center"><i class="fas fa-eye mr-1"></i></th>
                            </tr>
                        </thead>
                        <tbody id="audit-tbody">
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fas fa-spinner fa-spin fa-2x d-block mb-2" style="opacity:.3;"></i>
                                    Cargando registros...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div class="audit-pagination" id="audit-pagination" style="display:none;">
                    <span class="audit-pagination-info" id="audit-pag-info"></span>
                    <div class="audit-pagination-btns" id="audit-pagination-btns"></div>
                </div>

            </div><!-- /audit-card -->

        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: DETALLE DEL EVENTO
     ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalDetalleAudit" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document" style="max-width:700px;">
        <div class="modal-content adm-modal-content">

            <!-- Header -->
            <div class="adm-modal-header">
                <div class="adm-modal-header-bg"></div>
                <div class="adm-modal-header-inner">
                    <div class="adm-modal-icon-wrap">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="adm-modal-title-wrap">
                        <h5 class="adm-modal-title">Detalle del Evento</h5>
                        <span class="adm-modal-subtitle">Registro completo de auditoría del sistema</span>
                    </div>
                    <button type="button" class="adm-modal-close" data-dismiss="modal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <!-- Body -->
            <div class="modal-body adm-modal-body" id="modal-detalle-body">
                <div class="adm-loading">
                    <div class="adm-loading-spinner"></div>
                    <span>Cargando información...</span>
                </div>
            </div>

            <!-- Footer -->
            <div class="adm-modal-footer">
                <button type="button" class="adm-modal-btn-close" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i>Cerrar
                </button>
            </div>

        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: SELECTOR DE MÓDULO
     ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalPickerModulo" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered audit-picker-dialog" role="document">
        <div class="modal-content audit-picker-content">
            <!-- Header -->
            <div class="audit-picker-header" style="background:linear-gradient(135deg,#0f172a 0%,#1e3a8a 60%,#2563eb 100%);">
                <div class="audit-picker-header-icon" style="background:linear-gradient(135deg,#1e40af,#3b82f6);"><i class="fas fa-layer-group"></i></div>
                <div>
                    <h5>Filtrar por Módulo</h5>
                    <small>Selecciona el módulo del sistema a consultar</small>
                </div>
                <button type="button" class="audit-picker-close" data-dismiss="modal"><i class="fas fa-times"></i></button>
            </div>
            <!-- Body -->
            <div class="audit-picker-body">
                <div class="audit-picker-options" id="modulo-options">
                    <div class="audit-picker-option audit-picker-option--all active" data-value="" data-label="Todos los módulos">
                        <span class="audit-picker-opt-icon" style="background:#f1f5f9;color:#475569;"><i class="fas fa-layer-group"></i></span>
                        <span class="audit-picker-opt-label">Todos los módulos</span>
                        <i class="fas fa-check audit-picker-check"></i>
                    </div>
                    <div class="audit-picker-option" data-value="productos" data-label="Productos">
                        <span class="audit-picker-opt-icon" style="background:#dbeafe;color:#1e40af;"><i class="fas fa-laptop"></i></span>
                        <span class="audit-picker-opt-label">Productos</span>
                        <i class="fas fa-check audit-picker-check"></i>
                    </div>
                    <div class="audit-picker-option" data-value="inventario" data-label="Inventario">
                        <span class="audit-picker-opt-icon" style="background:#dcfce7;color:#166534;"><i class="fas fa-boxes"></i></span>
                        <span class="audit-picker-opt-label">Inventario</span>
                        <i class="fas fa-check audit-picker-check"></i>
                    </div>
                    <div class="audit-picker-option" data-value="ventas" data-label="Ventas">
                        <span class="audit-picker-opt-icon" style="background:#e0f2fe;color:#0c4a6e;"><i class="fas fa-shopping-cart"></i></span>
                        <span class="audit-picker-opt-label">Ventas</span>
                        <i class="fas fa-check audit-picker-check"></i>
                    </div>
                    <div class="audit-picker-option" data-value="compras" data-label="Compras">
                        <span class="audit-picker-opt-icon" style="background:#fef3c7;color:#92400e;"><i class="fas fa-truck-loading"></i></span>
                        <span class="audit-picker-opt-label">Compras</span>
                        <i class="fas fa-check audit-picker-check"></i>
                    </div>
                    <div class="audit-picker-option" data-value="caja" data-label="Caja">
                        <span class="audit-picker-opt-icon" style="background:#f0fdf4;color:#14532d;"><i class="fas fa-cash-register"></i></span>
                        <span class="audit-picker-opt-label">Caja</span>
                        <i class="fas fa-check audit-picker-check"></i>
                    </div>
                    <div class="audit-picker-option" data-value="usuarios" data-label="Usuarios">
                        <span class="audit-picker-opt-icon" style="background:#fde8e8;color:#991b1b;"><i class="fas fa-users-cog"></i></span>
                        <span class="audit-picker-opt-label">Usuarios</span>
                        <i class="fas fa-check audit-picker-check"></i>
                    </div>
                    <div class="audit-picker-option" data-value="servicios" data-label="Servicios">
                        <span class="audit-picker-opt-icon" style="background:#ede9fe;color:#5b21b6;"><i class="fas fa-tools"></i></span>
                        <span class="audit-picker-opt-label">Servicios</span>
                        <i class="fas fa-check audit-picker-check"></i>
                    </div>
                    <div class="audit-picker-option" data-value="empresa" data-label="Empresa">
                        <span class="audit-picker-opt-icon" style="background:#f1f5f9;color:#334155;"><i class="fas fa-building"></i></span>
                        <span class="audit-picker-opt-label">Empresa</span>
                        <i class="fas fa-check audit-picker-check"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: SELECTOR DE ACCIÓN
     ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalPickerAccion" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered audit-picker-dialog" role="document">
        <div class="modal-content audit-picker-content">
            <!-- Header -->
            <div class="audit-picker-header">
                <div class="audit-picker-header-icon"><i class="fas fa-bolt"></i></div>
                <div>
                    <h5>Filtrar por Acción</h5>
                    <small>Selecciona el tipo de acción a consultar</small>
                </div>
                <button type="button" class="audit-picker-close" data-dismiss="modal"><i class="fas fa-times"></i></button>
            </div>
            <!-- Body -->
            <div class="audit-picker-body">
                <div class="audit-picker-options" id="accion-options">
                    <div class="audit-picker-option audit-picker-option--all active" data-value="" data-label="Todas las acciones">
                        <span class="audit-picker-opt-icon" style="background:#f1f5f9;color:#475569;"><i class="fas fa-layer-group"></i></span>
                        <span class="audit-picker-opt-label">Todas las acciones</span>
                        <i class="fas fa-check audit-picker-check"></i>
                    </div>
                    <div class="audit-picker-option" data-value="crear" data-label="Crear">
                        <span class="audit-picker-opt-icon" style="background:#dcfce7;color:#166534;"><i class="fas fa-plus-circle"></i></span>
                        <span class="audit-picker-opt-label">Crear</span>
                        <i class="fas fa-check audit-picker-check"></i>
                    </div>
                    <div class="audit-picker-option" data-value="editar" data-label="Editar">
                        <span class="audit-picker-opt-icon" style="background:#fef3c7;color:#92400e;"><i class="fas fa-edit"></i></span>
                        <span class="audit-picker-opt-label">Editar</span>
                        <i class="fas fa-check audit-picker-check"></i>
                    </div>
                    <div class="audit-picker-option" data-value="eliminar" data-label="Eliminar">
                        <span class="audit-picker-opt-icon" style="background:#fee2e2;color:#991b1b;"><i class="fas fa-trash-alt"></i></span>
                        <span class="audit-picker-opt-label">Eliminar</span>
                        <i class="fas fa-check audit-picker-check"></i>
                    </div>
                    <div class="audit-picker-option" data-value="ajuste" data-label="Ajuste stock">
                        <span class="audit-picker-opt-icon" style="background:#ede9fe;color:#5b21b6;"><i class="fas fa-sliders-h"></i></span>
                        <span class="audit-picker-opt-label">Ajuste stock</span>
                        <i class="fas fa-check audit-picker-check"></i>
                    </div>
                    <div class="audit-picker-option" data-value="anular" data-label="Anular">
                        <span class="audit-picker-opt-icon" style="background:#fee2e2;color:#991b1b;"><i class="fas fa-ban"></i></span>
                        <span class="audit-picker-opt-label">Anular</span>
                        <i class="fas fa-check audit-picker-check"></i>
                    </div>
                    <div class="audit-picker-option" data-value="apertura" data-label="Apertura caja">
                        <span class="audit-picker-opt-icon" style="background:#dcfce7;color:#166534;"><i class="fas fa-lock-open"></i></span>
                        <span class="audit-picker-opt-label">Apertura caja</span>
                        <i class="fas fa-check audit-picker-check"></i>
                    </div>
                    <div class="audit-picker-option" data-value="cierre" data-label="Cierre caja">
                        <span class="audit-picker-opt-icon" style="background:#fef3c7;color:#92400e;"><i class="fas fa-lock"></i></span>
                        <span class="audit-picker-opt-label">Cierre caja</span>
                        <i class="fas fa-check audit-picker-check"></i>
                    </div>
                    <div class="audit-picker-option" data-value="permisos" data-label="Cambio permisos">
                        <span class="audit-picker-opt-icon" style="background:#dbeafe;color:#1e40af;"><i class="fas fa-shield-alt"></i></span>
                        <span class="audit-picker-opt-label">Cambio permisos</span>
                        <i class="fas fa-check audit-picker-check"></i>
                    </div>
                    <div class="audit-picker-option" data-value="cambio_rol" data-label="Cambio de rol">
                        <span class="audit-picker-opt-icon" style="background:#e0f2fe;color:#0c4a6e;"><i class="fas fa-user-tag"></i></span>
                        <span class="audit-picker-opt-label">Cambio de rol</span>
                        <i class="fas fa-check audit-picker-check"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: SELECTOR DE USUARIO
     ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalPickerUsuario" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered audit-picker-dialog" role="document">
        <div class="modal-content audit-picker-content">
            <!-- Header -->
            <div class="audit-picker-header">
                <div class="audit-picker-header-icon" style="background:linear-gradient(135deg,#0f766e,#14b8a6);"><i class="fas fa-users"></i></div>
                <div>
                    <h5>Filtrar por Usuario</h5>
                    <small>Selecciona el usuario a consultar</small>
                </div>
                <button type="button" class="audit-picker-close" data-dismiss="modal"><i class="fas fa-times"></i></button>
            </div>
            <!-- Buscador -->
            <div class="audit-picker-search-wrap">
                <i class="fas fa-search audit-picker-search-icon"></i>
                <input type="text" id="usuario-search" class="audit-picker-search" placeholder="Buscar usuario por nombre o usuario...">
            </div>
            <!-- Body -->
            <div class="audit-picker-body">
                <div class="audit-picker-options" id="usuario-options">
                    <div class="audit-picker-option audit-picker-option--all active" data-value="" data-label="Todos los usuarios">
                        <span class="audit-picker-opt-icon" style="background:#f1f5f9;color:#475569;"><i class="fas fa-users"></i></span>
                        <span class="audit-picker-opt-label">Todos los usuarios</span>
                        <i class="fas fa-check audit-picker-check"></i>
                    </div>
                    <?php foreach ($usuarios_lista as $u): ?>
                    <div class="audit-picker-option"
                         data-value="<?= htmlspecialchars($u['nombre_completo']) ?>"
                         data-label="<?= htmlspecialchars($u['nombre_completo']) ?>"
                         data-search="<?= strtolower(htmlspecialchars($u['nombre_completo'] . ' ' . $u['username'])) ?>">
                        <span class="audit-picker-opt-icon" style="background:#dbeafe;color:#1e40af;">
                            <i class="fas fa-user"></i>
                        </span>
                        <span class="audit-picker-opt-label">
                            <?= htmlspecialchars($u['nombre_completo']) ?>
                            <small>@<?= htmlspecialchars($u['username']) ?></small>
                        </span>
                        <i class="fas fa-check audit-picker-check"></i>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="audit-picker-empty" id="usuario-empty" style="display:none;">
                    <i class="fas fa-user-slash"></i>
                    <p>No se encontraron usuarios</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include $ruta_base . 'includes/footer.php'; ?>
