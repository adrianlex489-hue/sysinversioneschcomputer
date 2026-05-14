<?php
// ============================================================
// modules/Comprobantes/empresa.php | Botica 2026
// Configuración de datos de la empresa
// ============================================================
$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';

if (!isset($pdo) || !($pdo instanceof PDO)) die('Error: Conexión BD no disponible.');
if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 1);
verificar_acceso([ROL_ADMINISTRADOR]);
verificarPermiso($pdo, 'empresa');

$swal = null;
if (isset($_SESSION['swal_emp'])) { $swal = $_SESSION['swal_emp']; unset($_SESSION['swal_emp']); }

function redirigirEmp(string $icon, string $title, string $text): void {
    $_SESSION['swal_emp'] = compact('icon', 'title', 'text');
    header('Location: empresa.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $razon_social     = strtoupper(trim($_POST['razon_social']     ?? ''));
    $nombre_comercial = strtoupper(trim($_POST['nombre_comercial'] ?? '')) ?: null;
    $ruc              = trim($_POST['ruc']              ?? '');
    $direccion        = strtoupper(trim($_POST['direccion']        ?? ''));
    $distrito         = strtoupper(trim($_POST['distrito']         ?? '')) ?: null;
    $provincia        = strtoupper(trim($_POST['provincia']        ?? '')) ?: null;
    $departamento     = strtoupper(trim($_POST['departamento']     ?? '')) ?: null;
    $telefono         = trim($_POST['telefono']         ?? '') ?: null;
    $email            = trim($_POST['email']            ?? '') ?: null;
    $web              = trim($_POST['web']              ?? '') ?: null;
    $igv_porcentaje   = (float)($_POST['igv_porcentaje'] ?? 18);
    $serie_ticket     = strtoupper(trim($_POST['serie_ticket'] ?? 'T001'));
    $serie_nota       = strtoupper(trim($_POST['serie_nota']   ?? 'N001'));
    $pie_comprobante  = trim($_POST['pie_comprobante']  ?? '') ?: null;

    if (empty($razon_social))
        redirigirEmp('warning', 'Campo requerido', 'La razón social es obligatoria.');
    if (!preg_match('/^\d{11}$/', $ruc))
        redirigirEmp('warning', 'RUC inválido', 'El RUC debe tener exactamente 11 dígitos numéricos.');
    if (empty($direccion))
        redirigirEmp('warning', 'Campo requerido', 'La dirección es obligatoria.');

    $logo_path = $_POST['logo_actual'] ?? null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp']))
            redirigirEmp('warning', 'Formato inválido', 'El logo debe ser JPG, PNG o WEBP.');
        $dir_logo = $_SERVER['DOCUMENT_ROOT'] . '/botica-2026/Logo/';
        if (!is_dir($dir_logo)) mkdir($dir_logo, 0755, true);
        if (!empty($logo_path)) {
            $ruta_ant = $_SERVER['DOCUMENT_ROOT'] . $logo_path;
            if (file_exists($ruta_ant)) unlink($ruta_ant);
        }
        $nombre_logo = 'logo_empresa.' . $ext;
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $dir_logo . $nombre_logo))
            $logo_path = '/botica-2026/Logo/' . $nombre_logo;
    }

    try {
        $existe = $pdo->query("SELECT id_empresa FROM empresa LIMIT 1")->fetchColumn();
        if ($existe) {
            $pdo->prepare("UPDATE empresa SET razon_social=?,nombre_comercial=?,ruc=?,direccion=?,distrito=?,provincia=?,departamento=?,telefono=?,email=?,web=?,logo=?,igv_porcentaje=?,serie_ticket=?,serie_nota=?,pie_comprobante=? WHERE id_empresa=?")
                ->execute([$razon_social,$nombre_comercial,$ruc,$direccion,$distrito,$provincia,$departamento,$telefono,$email,$web,$logo_path,$igv_porcentaje,$serie_ticket,$serie_nota,$pie_comprobante,$existe]);
        } else {
            $pdo->prepare("INSERT INTO empresa (razon_social,nombre_comercial,ruc,direccion,distrito,provincia,departamento,telefono,email,web,logo,igv_porcentaje,serie_ticket,serie_nota,pie_comprobante) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$razon_social,$nombre_comercial,$ruc,$direccion,$distrito,$provincia,$departamento,$telefono,$email,$web,$logo_path,$igv_porcentaje,$serie_ticket,$serie_nota,$pie_comprobante]);
        }
        redirigirEmp('success', '¡Cambios guardados!', 'Los datos de la empresa se actualizaron correctamente.');
    } catch (PDOException $e) {
        redirigirEmp('error', 'Error al guardar', $e->getMessage());
    }
}

$empresa = [];
$tabla_ok = true;
try {
    $empresa = $pdo->query("SELECT * FROM empresa LIMIT 1")->fetch() ?: [];
} catch (PDOException $e) {
    $tabla_ok = false;
}

include $ruta_base . 'includes/header.php';
include $ruta_base . 'includes/sidebar.php';
?>
<link rel="stylesheet" href="css/empresa.css">
<div class="content-wrapper">

<!-- CABECERA -->
<div class="content-header"><div class="container-fluid">
<div class="page-header-emp">
    <div>
        <h4><i class="fas fa-building mr-2"></i>Mi Empresa</h4>
        <small><i class="fas fa-map-marker-alt mr-1"></i>Botica 2026 &rsaquo; Mi Empresa</small>
    </div>
    <?php if (!empty($empresa)): ?>
    <span class="badge-emp-activo"><i class="fas fa-check-circle mr-1"></i>Datos configurados</span>
    <?php else: ?>
    <span class="badge-emp-pendiente"><i class="fas fa-exclamation-circle mr-1"></i>Pendiente de configurar</span>
    <?php endif; ?>
</div>
</div></div>

<div class="content"><div class="container-fluid">

<?php if ($swal): ?>
<script>document.addEventListener('DOMContentLoaded',function(){Swal.fire({icon:'<?= $swal['icon'] ?>',title:'<?= addslashes($swal['title']) ?>',text:'<?= addslashes($swal['text']) ?>',confirmButtonColor:'#1a7a4a',timer:<?= in_array($swal['icon'],['success','info'])?4000:0 ?>,timerProgressBar:<?= in_array($swal['icon'],['success','info'])?'true':'false' ?>,showConfirmButton:<?= in_array($swal['icon'],['success','info'])?'false':'true' ?>,});});</script>
<?php endif; ?>

<?php if (!$tabla_ok): ?>
<div class="emp-alert-error"><i class="fas fa-database mr-2"></i><strong>Tabla no encontrada.</strong> Ejecuta <code>empresa.sql</code> en phpMyAdmin primero.</div>
<?php else: ?>

<form method="POST" enctype="multipart/form-data" id="formEmpresa">
<input type="hidden" name="logo_actual" value="<?= htmlspecialchars($empresa['logo'] ?? '') ?>">

<div class="emp-layout">

    <!-- ══════════════════════════════════════════
         PANEL IZQUIERDO — Formulario con tabs
    ══════════════════════════════════════════ -->
    <div class="emp-form-panel">

        <!-- TABS -->
        <div class="emp-tabs" id="empTabs">
            <div class="emp-tab active" data-tab="identificacion">
                <i class="fas fa-id-card"></i> Identificación
            </div>
            <div class="emp-tab" data-tab="ubicacion">
                <i class="fas fa-map-marker-alt"></i> Ubicación
            </div>
            <div class="emp-tab" data-tab="contacto">
                <i class="fas fa-globe"></i> Contacto
            </div>
            <div class="emp-tab" data-tab="comprobantes">
                <i class="fas fa-file-invoice"></i> Comprobantes
            </div>
            <div class="emp-tab" data-tab="logo">
                <i class="fas fa-image"></i> Logo
            </div>
        </div>

        <!-- TAB: IDENTIFICACIÓN -->
        <div class="emp-tab-content active" id="tab-identificacion">
            <div class="emp-field-row cols-1-2">
                <div class="emp-field">
                    <label class="emp-label"><i class="fas fa-building"></i> Razón Social <span class="req">*</span></label>
                    <div class="emp-input-wrap">
                        <i class="emp-input-icon fas fa-building"></i>
                        <input type="text" class="emp-input" name="razon_social" maxlength="200" required
                            style="text-transform:uppercase;"
                            value="<?= htmlspecialchars($empresa['razon_social'] ?? '') ?>"
                            placeholder="Ej: BOTICA SALUD EXPRESS S.A.C.">
                    </div>
                </div>
                <div class="emp-field">
                    <label class="emp-label"><i class="fas fa-id-card"></i> RUC <span class="req">*</span></label>
                    <div class="emp-input-wrap">
                        <i class="emp-input-icon fas fa-hashtag"></i>
                        <input type="text" class="emp-input" name="ruc" maxlength="11" pattern="\d{11}" required
                            value="<?= htmlspecialchars($empresa['ruc'] ?? '') ?>"
                            placeholder="20000000001">
                    </div>
                    <span class="emp-input-hint">Exactamente 11 dígitos</span>
                </div>
            </div>
            <div class="emp-field-row cols-2">
                <div class="emp-field">
                    <label class="emp-label"><i class="fas fa-store"></i> Nombre Comercial</label>
                    <div class="emp-input-wrap">
                        <i class="emp-input-icon fas fa-store"></i>
                        <input type="text" class="emp-input" name="nombre_comercial" maxlength="200"
                            style="text-transform:uppercase;"
                            value="<?= htmlspecialchars($empresa['nombre_comercial'] ?? '') ?>"
                            placeholder="Ej: BOTICA SALUD EXPRESS">
                    </div>
                    <span class="emp-input-hint">Nombre que aparece en comprobantes</span>
                </div>
                <div class="emp-field-row cols-2" style="margin-bottom:0;">
                    <div class="emp-field">
                        <label class="emp-label"><i class="fas fa-percentage"></i> IGV (%)</label>
                        <div class="emp-input-wrap emp-input-suffix">
                            <i class="emp-input-icon fas fa-percentage"></i>
                            <input type="number" step="0.01" min="0" max="100" name="igv_porcentaje"
                                value="<?= htmlspecialchars($empresa['igv_porcentaje'] ?? '18.00') ?>">
                            <span class="suffix-label">%</span>
                        </div>
                    </div>
                    <div class="emp-field">
                        <label class="emp-label"><i class="fas fa-phone"></i> Teléfono</label>
                        <div class="emp-input-wrap">
                            <i class="emp-input-icon fas fa-phone"></i>
                            <input type="text" class="emp-input" name="telefono" maxlength="30"
                                value="<?= htmlspecialchars($empresa['telefono'] ?? '') ?>"
                                placeholder="074-000000">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB: UBICACIÓN -->
        <div class="emp-tab-content" id="tab-ubicacion">
            <div class="emp-field" style="margin-bottom:20px;">
                <label class="emp-label"><i class="fas fa-map-marker-alt"></i> Dirección <span class="req">*</span></label>
                <div class="emp-input-wrap">
                    <i class="emp-input-icon fas fa-map-marker-alt"></i>
                    <input type="text" class="emp-input" name="direccion" maxlength="250" required
                        style="text-transform:uppercase;"
                        value="<?= htmlspecialchars($empresa['direccion'] ?? '') ?>"
                        placeholder="Ej: AV. PRINCIPAL NRO. 123">
                </div>
            </div>
            <div class="emp-field-row cols-3">
                <div class="emp-field">
                    <label class="emp-label"><i class="fas fa-map-pin"></i> Distrito</label>
                    <div class="emp-input-wrap">
                        <i class="emp-input-icon fas fa-map-pin"></i>
                        <input type="text" class="emp-input" name="distrito" maxlength="100"
                            style="text-transform:uppercase;"
                            value="<?= htmlspecialchars($empresa['distrito'] ?? '') ?>"
                            placeholder="CHICLAYO">
                    </div>
                </div>
                <div class="emp-field">
                    <label class="emp-label"><i class="fas fa-map"></i> Provincia</label>
                    <div class="emp-input-wrap">
                        <i class="emp-input-icon fas fa-map"></i>
                        <input type="text" class="emp-input" name="provincia" maxlength="100"
                            style="text-transform:uppercase;"
                            value="<?= htmlspecialchars($empresa['provincia'] ?? '') ?>"
                            placeholder="CHICLAYO">
                    </div>
                </div>
                <div class="emp-field">
                    <label class="emp-label"><i class="fas fa-globe-americas"></i> Departamento</label>
                    <div class="emp-input-wrap">
                        <i class="emp-input-icon fas fa-globe-americas"></i>
                        <input type="text" class="emp-input" name="departamento" maxlength="100"
                            style="text-transform:uppercase;"
                            value="<?= htmlspecialchars($empresa['departamento'] ?? '') ?>"
                            placeholder="LAMBAYEQUE">
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB: CONTACTO -->
        <div class="emp-tab-content" id="tab-contacto">
            <div class="emp-field-row cols-2">
                <div class="emp-field">
                    <label class="emp-label"><i class="fas fa-envelope"></i> Email</label>
                    <div class="emp-input-wrap">
                        <i class="emp-input-icon fas fa-envelope"></i>
                        <input type="email" class="emp-input" name="email" maxlength="100"
                            value="<?= htmlspecialchars($empresa['email'] ?? '') ?>"
                            placeholder="contacto@botica.com">
                    </div>
                </div>
                <div class="emp-field">
                    <label class="emp-label"><i class="fas fa-globe"></i> Sitio Web</label>
                    <div class="emp-input-wrap">
                        <i class="emp-input-icon fas fa-globe"></i>
                        <input type="text" class="emp-input" name="web" maxlength="150"
                            value="<?= htmlspecialchars($empresa['web'] ?? '') ?>"
                            placeholder="www.botica.com">
                    </div>
                </div>
            </div>
            <div class="emp-field">
                <label class="emp-label"><i class="fas fa-comment-alt"></i> Pie de Comprobante</label>
                <div class="emp-input-wrap">
                    <i class="emp-input-icon fas fa-comment-alt"></i>
                    <input type="text" class="emp-input" name="pie_comprobante" maxlength="300"
                        value="<?= htmlspecialchars($empresa['pie_comprobante'] ?? 'Gracias por su preferencia') ?>"
                        placeholder="Ej: Gracias por su preferencia">
                </div>
                <span class="emp-input-hint">Aparece al final de cada comprobante impreso. Máx. 300 caracteres.</span>
            </div>
        </div>

        <!-- TAB: COMPROBANTES -->
        <div class="emp-tab-content" id="tab-comprobantes">
            <p style="font-size:.83rem;color:#6c757d;margin-bottom:20px;">
                <i class="fas fa-info-circle mr-1 text-success"></i>
                La serie se combina con el correlativo automático. Ejemplo: <strong style="color:#1a7a4a;">T001</strong>-00000001
            </p>
            <div class="emp-field-row cols-2">
                <div class="emp-serie-card">
                    <div class="emp-serie-title"><i class="fas fa-receipt"></i> Serie Ticket</div>
                    <input type="text" class="emp-serie-input" name="serie_ticket" id="inp_serie_ticket"
                        maxlength="4"
                        value="<?= htmlspecialchars($empresa['serie_ticket'] ?? 'T001') ?>">
                    <div class="emp-serie-preview">
                        Próximo: <strong id="prev_ticket_num"><?= htmlspecialchars($empresa['serie_ticket'] ?? 'T001') ?>-00000001</strong>
                    </div>
                </div>
                <div class="emp-serie-card">
                    <div class="emp-serie-title"><i class="fas fa-file-alt"></i> Serie Nota de Venta</div>
                    <input type="text" class="emp-serie-input" name="serie_nota" id="inp_serie_nota"
                        maxlength="4"
                        value="<?= htmlspecialchars($empresa['serie_nota'] ?? 'N001') ?>">
                    <div class="emp-serie-preview">
                        Próxima: <strong id="prev_nota_num"><?= htmlspecialchars($empresa['serie_nota'] ?? 'N001') ?>-00000001</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB: LOGO -->
        <div class="emp-tab-content" id="tab-logo">
            <p style="font-size:.83rem;color:#6c757d;margin-bottom:16px;">
                <i class="fas fa-info-circle mr-1 text-success"></i>
                El logo aparece en la cabecera de todos los comprobantes PDF. Recomendado: <strong>300×100 px</strong>, fondo transparente o blanco.
            </p>
            <label for="inputLogo" class="emp-logo-dropzone" id="logoDropzone">
                <?php if (!empty($empresa['logo'])): ?>
                <img src="<?= htmlspecialchars($empresa['logo']) ?>" id="logoPreviewImg" alt="Logo actual">
                <p style="font-size:.78rem;color:#adb5bd;margin:4px 0 0;">Logo actual — haz clic para cambiar</p>
                <?php else: ?>
                <div class="logo-placeholder" id="logoPlaceholder">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p>Haz clic o arrastra tu logo aquí</p>
                    <small style="color:#adb5bd;">JPG, PNG o WEBP</small>
                </div>
                <img src="" id="logoPreviewImg" style="display:none;" alt="Preview">
                <?php endif; ?>
            </label>
            <input type="file" id="inputLogo" name="logo" accept=".jpg,.jpeg,.png,.webp" style="display:none;">
            <div id="logoFilename" style="text-align:center;margin-top:8px;display:none;">
                <span class="emp-logo-filename" id="logoFilenameText"></span>
            </div>
        </div>

        <!-- FOOTER GUARDAR -->
        <div class="emp-footer">
            <span class="emp-footer-hint"><span class="req">*</span> Campos obligatorios</span>
            <button type="submit" class="btn-emp-save" id="btnGuardar">
                <i class="fas fa-save"></i> Guardar Cambios
            </button>
        </div>
    </div>

    <!-- ══════════════════════════════════════════
         PANEL DERECHO — Vista previa + checklist
    ══════════════════════════════════════════ -->
    <div class="emp-right-panel">

        <!-- Vista previa comprobante -->
        <div class="emp-side-card">
            <div class="emp-side-card-header">
                <i class="fas fa-eye"></i> Vista Previa en Comprobante
            </div>
            <div class="emp-side-card-body" style="padding:16px;">
                <div class="emp-preview-doc">
                    <div class="emp-preview-doc-header">
                        <?php if (!empty($empresa['logo'])): ?>
                        <img src="<?= htmlspecialchars($empresa['logo']) ?>" id="prevLogoImg" alt="Logo">
                        <?php else: ?>
                        <div id="prevLogoImg" style="height:20px;"></div>
                        <?php endif; ?>
                        <div class="emp-preview-doc-razon" id="prevRazon">
                            <?= htmlspecialchars($empresa['razon_social'] ?? 'NOMBRE DE LA EMPRESA') ?>
                        </div>
                        <div class="emp-preview-doc-dato" id="prevRuc">
                            RUC: <?= htmlspecialchars($empresa['ruc'] ?? '00000000000') ?>
                        </div>
                        <div class="emp-preview-doc-dato" id="prevDir">
                            <?= htmlspecialchars(($empresa['direccion'] ?? '') . (!empty($empresa['distrito']) ? ' - '.$empresa['distrito'] : '')) ?>
                        </div>
                        <div class="emp-preview-doc-dato" id="prevTel">
                            <?= !empty($empresa['telefono']) ? 'Tel: '.htmlspecialchars($empresa['telefono']) : '' ?>
                        </div>
                    </div>
                    <div class="emp-preview-doc-tipo">
                        <div class="emp-preview-doc-tipo-label">TICKET / NOTA DE VENTA</div>
                        <div class="emp-preview-doc-tipo-num" id="prevSerie">
                            N° <?= htmlspecialchars($empresa['serie_ticket'] ?? 'T001') ?>-00000001
                        </div>
                    </div>
                    <div class="emp-preview-doc-pie" id="prevPie">
                        <?= htmlspecialchars($empresa['pie_comprobante'] ?? 'Gracias por su preferencia') ?>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /emp-right-panel -->
</div><!-- /emp-layout -->
</form>

<?php endif; ?>
</div></div>
</div>

<?php include $ruta_base . 'includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── TABS ─────────────────────────────────────────────────────────────────
    document.querySelectorAll('.emp-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.emp-tab').forEach(function(t){ t.classList.remove('active'); });
            document.querySelectorAll('.emp-tab-content').forEach(function(c){ c.classList.remove('active'); });
            this.classList.add('active');
            var target = document.getElementById('tab-' + this.dataset.tab);
            if (target) target.classList.add('active');
        });
    });

    // ── VISTA PREVIA EN TIEMPO REAL ───────────────────────────────────────────
    function bindPreview(selector, previewId, transform) {
        var el = document.querySelector(selector);
        var pr = document.getElementById(previewId);
        if (!el || !pr) return;
        el.addEventListener('input', function () {
            pr.textContent = transform ? transform(this.value) : (this.value || '');
        });
    }

    bindPreview('[name=razon_social]',    'prevRazon', function(v){ return v || 'NOMBRE DE LA EMPRESA'; });
    bindPreview('[name=ruc]',             'prevRuc',   function(v){ return 'RUC: ' + (v || '00000000000'); });
    bindPreview('[name=pie_comprobante]', 'prevPie',   function(v){ return v || 'Gracias por su preferencia'; });
    bindPreview('[name=telefono]',        'prevTel',   function(v){ return v ? 'Tel: ' + v : ''; });

    // Dirección + distrito
    function actualizarDir() {
        var dir = (document.querySelector('[name=direccion]')?.value || '').trim();
        var dis = (document.querySelector('[name=distrito]')?.value  || '').trim();
        var el  = document.getElementById('prevDir');
        if (el) el.textContent = dir + (dis ? ' - ' + dis : '');
    }
    document.querySelector('[name=direccion]')?.addEventListener('input', actualizarDir);
    document.querySelector('[name=distrito]')?.addEventListener('input', actualizarDir);

    // Series
    document.getElementById('inp_serie_ticket')?.addEventListener('input', function () {
        var v = this.value.toUpperCase() || 'T001';
        var el = document.getElementById('prev_ticket_num');
        if (el) el.textContent = v + '-00000001';
        var ps = document.getElementById('prevSerie');
        if (ps) ps.textContent = 'N° ' + v + '-00000001';
    });
    document.getElementById('inp_serie_nota')?.addEventListener('input', function () {
        var v = this.value.toUpperCase() || 'N001';
        var el = document.getElementById('prev_nota_num');
        if (el) el.textContent = v + '-00000001';
    });

    // ── LOGO PREVIEW ─────────────────────────────────────────────────────────
    var inputLogo   = document.getElementById('inputLogo');
    var dropzone    = document.getElementById('logoDropzone');
    var previewImg  = document.getElementById('logoPreviewImg');
    var placeholder = document.getElementById('logoPlaceholder');
    var filenameBox = document.getElementById('logoFilename');
    var filenameEl  = document.getElementById('logoFilenameText');
    var prevLogoImg = document.getElementById('prevLogoImg');

    if (inputLogo) {
        inputLogo.addEventListener('change', function () {
            var file = this.files[0];
            if (!file) return;
            var reader = new FileReader();
            reader.onload = function (e) {
                if (previewImg) { previewImg.src = e.target.result; previewImg.style.display = 'block'; }
                if (placeholder) placeholder.style.display = 'none';
                if (prevLogoImg && prevLogoImg.tagName === 'IMG') prevLogoImg.src = e.target.result;
            };
            reader.readAsDataURL(file);
            if (filenameEl) filenameEl.textContent = '📎 ' + file.name;
            if (filenameBox) filenameBox.style.display = 'block';
        });
    }

    // Drag & drop
    if (dropzone) {
        dropzone.addEventListener('dragover', function(e){ e.preventDefault(); this.classList.add('dragover'); });
        dropzone.addEventListener('dragleave', function(){ this.classList.remove('dragover'); });
        dropzone.addEventListener('drop', function(e){
            e.preventDefault(); this.classList.remove('dragover');
            var file = e.dataTransfer.files[0];
            if (file && inputLogo) {
                var dt = new DataTransfer(); dt.items.add(file); inputLogo.files = dt.files;
                inputLogo.dispatchEvent(new Event('change'));
            }
        });
    }

    // ── SUBMIT CON CONFIRMACIÓN ───────────────────────────────────────────────
    var form = document.getElementById('formEmpresa');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var ruc = document.querySelector('[name=ruc]')?.value || '';
            if (!/^\d{11}$/.test(ruc)) {
                Swal.fire({ icon: 'warning', title: 'RUC inválido', text: 'El RUC debe tener exactamente 11 dígitos.', confirmButtonColor: '#1a7a4a' });
                return;
            }
            Swal.fire({
                icon: 'question',
                title: '¿Guardar cambios?',
                html: 'Se actualizarán los datos de la empresa.<br><small style="color:#6c757d;">Esta información aparecerá en todos los comprobantes.</small>',
                showCancelButton: true,
                confirmButtonColor: '#1a7a4a',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-save mr-1"></i> Sí, guardar',
                cancelButtonText: '<i class="fas fa-times mr-1"></i> Cancelar'
            }).then(function (r) {
                if (r.isConfirmed) {
                    var btn = document.getElementById('btnGuardar');
                    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...'; }
                    form.submit();
                }
            });
        });
    }

});
</script>

