<?php
// ============================================================
// modules/configuracion_empresa/empresa.php | SysInversiones CH Computer 2026
// Configuracion de datos de la empresa tecnologica
// ============================================================
$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';
require_once $ruta_base . 'conf/auditoria.php';

$swal = null;
if (isset($_SESSION['swal_emp'])) { $swal = $_SESSION['swal_emp']; unset($_SESSION['swal_emp']); }

function redirigirEmp(string $icon, string $title, string $text): void {
    $_SESSION['swal_emp'] = compact('icon', 'title', 'text');
    header('Location: empresa.php'); exit;
}

// ── POST: guardar datos ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $razon_social    = strtoupper(trim($_POST['razon_social']    ?? ''));
    $nombre_comercial= strtoupper(trim($_POST['nombre_comercial']?? '')) ?: null;
    $ruc             = trim($_POST['ruc']             ?? '');
    $direccion       = strtoupper(trim($_POST['direccion']       ?? ''));
    $distrito        = strtoupper(trim($_POST['distrito']        ?? '')) ?: null;
    $provincia       = strtoupper(trim($_POST['provincia']       ?? '')) ?: null;
    $departamento    = strtoupper(trim($_POST['departamento']    ?? '')) ?: null;
    $telefono        = trim($_POST['telefono']        ?? '') ?: null;
    $email           = trim($_POST['email']           ?? '') ?: null;
    $web             = trim($_POST['web']             ?? '') ?: null;
    $igv_porcentaje  = (float)($_POST['igv_porcentaje'] ?? 18);
    $serie_ticket    = strtoupper(trim($_POST['serie_ticket'] ?? 'T001'));
    $serie_nota      = strtoupper(trim($_POST['serie_nota']   ?? 'N001'));
    $pie_comprobante = trim($_POST['pie_comprobante'] ?? '') ?: 'Gracias por su preferencia';

    if (empty($razon_social))
        redirigirEmp('warning', 'Campo requerido', 'La razon social es obligatoria.');
    if (!preg_match('/^\d{11}$/', $ruc))
        redirigirEmp('warning', 'RUC invalido', 'El RUC debe tener exactamente 11 digitos numericos.');
    if (empty($direccion))
        redirigirEmp('warning', 'Campo requerido', 'La direccion es obligatoria.');

    // Manejo del logo
    $logo_path = $_POST['logo_actual'] ?? null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp']))
            redirigirEmp('warning', 'Formato invalido', 'El logo debe ser JPG, PNG o WEBP.');
        $dir_logo = $_SERVER['DOCUMENT_ROOT'] . '/sysinversioneschcomputer/Logo/';
        if (!is_dir($dir_logo)) mkdir($dir_logo, 0755, true);
        $nombre_logo = 'logo_empresa.' . $ext;
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $dir_logo . $nombre_logo))
            $logo_path = '/sysinversioneschcomputer/Logo/' . $nombre_logo;
    }

    try {
        $existe = $pdo->query("SELECT id_empresa FROM empresa LIMIT 1")->fetchColumn();

        // Leer datos anteriores para auditoría (solo en UPDATE)
        $emp_antes = [];
        if ($existe) {
            $emp_antes = $pdo->query("SELECT razon_social, ruc, igv_porcentaje, serie_ticket, serie_nota FROM empresa LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
        }

        if ($existe) {
            $pdo->prepare(
                "UPDATE empresa SET razon_social=?,nombre_comercial=?,ruc=?,
                 direccion=?,distrito=?,provincia=?,departamento=?,
                 telefono=?,email=?,web=?,logo=?,
                 igv_porcentaje=?,serie_ticket=?,serie_nota=?,pie_comprobante=?
                 WHERE id_empresa=?"
            )->execute([
                $razon_social,$nombre_comercial,$ruc,
                $direccion,$distrito,$provincia,$departamento,
                $telefono,$email,$web,$logo_path,
                $igv_porcentaje,$serie_ticket,$serie_nota,$pie_comprobante,
                $existe
            ]);

            // Auditoría: registrar campos críticos que cambiaron
            $campos_emp = [
                'razon_social'   => [$emp_antes['razon_social']   ?? null, $razon_social],
                'ruc'            => [$emp_antes['ruc']            ?? null, $ruc],
                'igv_porcentaje' => [$emp_antes['igv_porcentaje'] ?? null, $igv_porcentaje],
                'serie_ticket'   => [$emp_antes['serie_ticket']   ?? null, $serie_ticket],
                'serie_nota'     => [$emp_antes['serie_nota']     ?? null, $serie_nota],
            ];
            foreach ($campos_emp as $campo => [$antes, $nuevo]) {
                if ((string)$antes !== (string)$nuevo) {
                    registrarAuditoria($pdo, 'empresa', 'editar', 'empresa', (int)$existe,
                        "Configuración empresa — cambio en $campo",
                        $campo, $antes, $nuevo);
                }
            }
        } else {
            $pdo->prepare(
                "INSERT INTO empresa
                 (razon_social,nombre_comercial,ruc,direccion,distrito,provincia,
                  departamento,telefono,email,web,logo,igv_porcentaje,
                  serie_ticket,serie_nota,pie_comprobante)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
            )->execute([
                $razon_social,$nombre_comercial,$ruc,
                $direccion,$distrito,$provincia,$departamento,
                $telefono,$email,$web,$logo_path,
                $igv_porcentaje,$serie_ticket,$serie_nota,$pie_comprobante
            ]);
            $id_emp_nuevo = (int)$pdo->lastInsertId();
            registrarAuditoria($pdo, 'empresa', 'crear', 'empresa', $id_emp_nuevo,
                "Datos de empresa configurados por primera vez — RUC: $ruc");
        }
        redirigirEmp('success', 'Cambios guardados', 'Los datos de la empresa se actualizaron correctamente.');
    } catch (PDOException $e) {
        redirigirEmp('error', 'Error al guardar', $e->getMessage());
    }
}

// ── Cargar datos actuales ─────────────────────────────────────────────────────
$empresa  = [];
$tabla_ok = true;
try {
    $empresa = $pdo->query("SELECT * FROM empresa LIMIT 1")->fetch() ?: [];
} catch (PDOException $e) {
    $tabla_ok = false;
}

$extra_css = '<link rel="stylesheet" href="/sysinversioneschcomputer/modules/configuracion_empresa/css/empresa.css">';
include $ruta_base . 'includes/header.php';
include $ruta_base . 'includes/sidebar.php';
?>
<div class="content-wrapper">
<div class="content-header"><div class="container-fluid">
<div class="page-header-emp">
    <div>
        <h4><i class="fas fa-building mr-2"></i>Mi Empresa</h4>
        <small><i class="fas fa-map-marker-alt mr-1"></i>SysInversiones &rsaquo; Configuracion &rsaquo; Mi Empresa</small>
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
<script>document.addEventListener('DOMContentLoaded',function(){Swal.fire({
    icon:'<?= $swal['icon'] ?>',
    title:'<?= addslashes($swal['title']) ?>',
    text:'<?= addslashes($swal['text']) ?>',
    confirmButtonColor:'#1a5276',
    timer:<?= in_array($swal['icon'],['success','info'])?4000:0 ?>,
    timerProgressBar:<?= in_array($swal['icon'],['success','info'])?'true':'false' ?>,
    showConfirmButton:<?= in_array($swal['icon'],['success','info'])?'false':'true' ?>
});});</script>
<?php endif; ?>

<?php if (!$tabla_ok): ?>
<div class="emp-alert-error">
    <i class="fas fa-database mr-2"></i>
    <strong>Tabla empresa no encontrada.</strong>
    Ejecuta el SQL de creacion de la tabla en phpMyAdmin primero.
</div>
<?php else: ?>

<form method="POST" enctype="multipart/form-data" id="formEmpresa" action="empresa.php">
<input type="hidden" name="logo_actual" value="<?= htmlspecialchars($empresa['logo'] ?? '') ?>">

<div class="emp-layout">

<!-- ═══════════════════════════════════════════
     PANEL IZQUIERDO — Formulario con tabs
═══════════════════════════════════════════ -->
<div class="emp-form-panel">

    <!-- TABS -->
    <div class="emp-tabs">
        <div class="emp-tab active" data-tab="identificacion"><i class="fas fa-id-card mr-1"></i>Identificacion</div>
        <div class="emp-tab" data-tab="ubicacion"><i class="fas fa-map-marker-alt mr-1"></i>Ubicacion</div>
        <div class="emp-tab" data-tab="contacto"><i class="fas fa-globe mr-1"></i>Contacto</div>
        <div class="emp-tab" data-tab="comprobantes"><i class="fas fa-file-invoice mr-1"></i>Comprobantes</div>
        <div class="emp-tab" data-tab="logo"><i class="fas fa-image mr-1"></i>Logo</div>
    </div>

    <!-- TAB: IDENTIFICACION -->
    <div class="emp-tab-content active" id="tab-identificacion">
        <div class="emp-field">
            <label class="emp-label"><i class="fas fa-building mr-1"></i>Razon Social <span class="req">*</span></label>
            <div class="emp-input-wrap">
                <i class="emp-input-icon fas fa-building"></i>
                <input type="text" class="emp-input" name="razon_social" maxlength="200"
                    style="text-transform:uppercase;"
                    value="<?= htmlspecialchars($empresa['razon_social'] ?? '') ?>"
                    placeholder="Ej: INVERSIONES CH COMPUTER SRL">
            </div>
        </div>
        <div class="emp-field">
            <label class="emp-label"><i class="fas fa-store mr-1"></i>Nombre Comercial</label>
            <div class="emp-input-wrap">
                <i class="emp-input-icon fas fa-store"></i>
                <input type="text" class="emp-input" name="nombre_comercial" maxlength="200"
                    style="text-transform:uppercase;"
                    value="<?= htmlspecialchars($empresa['nombre_comercial'] ?? '') ?>"
                    placeholder="Ej: SYSINVERSIONES CH COMPUTER">
            </div>
            <span class="emp-input-hint">Nombre que aparece en comprobantes</span>
        </div>
        <div class="emp-field-row cols-2">
            <div class="emp-field">
                <label class="emp-label"><i class="fas fa-hashtag mr-1"></i>RUC <span class="req">*</span></label>
                <div class="emp-input-wrap">
                    <i class="emp-input-icon fas fa-hashtag"></i>
                    <input type="text" class="emp-input" name="ruc" maxlength="11"
                        value="<?= htmlspecialchars($empresa['ruc'] ?? '') ?>"
                        placeholder="20479894699">
                </div>
                <span class="emp-input-hint">Exactamente 11 digitos</span>
            </div>
            <div class="emp-field">
                <label class="emp-label"><i class="fas fa-percentage mr-1"></i>IGV (%)</label>
                <div class="emp-input-wrap">
                    <i class="emp-input-icon fas fa-percentage"></i>
                    <input type="number" class="emp-input" step="0.01" min="0" max="100" name="igv_porcentaje"
                        value="<?= htmlspecialchars($empresa['igv_porcentaje'] ?? '18.00') ?>">
                </div>
            </div>
        </div>
        <div class="emp-field">
            <label class="emp-label"><i class="fas fa-phone mr-1"></i>Telefono</label>
            <div class="emp-input-wrap">
                <i class="emp-input-icon fas fa-phone"></i>
                <input type="text" class="emp-input" name="telefono" maxlength="30"
                    value="<?= htmlspecialchars($empresa['telefono'] ?? '') ?>"
                    placeholder="939683782">
            </div>
        </div>
    </div>

    <!-- TAB: UBICACION -->
    <div class="emp-tab-content" id="tab-ubicacion">
        <div class="emp-field">
            <label class="emp-label"><i class="fas fa-map-marker-alt mr-1"></i>Direccion <span class="req">*</span></label>
            <div class="emp-input-wrap">
                <i class="emp-input-icon fas fa-map-marker-alt"></i>
                <input type="text" class="emp-input" name="direccion" maxlength="250"
                    style="text-transform:uppercase;"
                    value="<?= htmlspecialchars($empresa['direccion'] ?? '') ?>"
                    placeholder="Ej: CAL. JOSE FRANCISCO CABRERA NRO. 274">
            </div>
        </div>
        <div class="emp-field-row cols-3">
            <div class="emp-field">
                <label class="emp-label">Distrito</label>
                <div class="emp-input-wrap">
                    <i class="emp-input-icon fas fa-map-pin"></i>
                    <input type="text" class="emp-input" name="distrito" maxlength="100"
                        style="text-transform:uppercase;"
                        value="<?= htmlspecialchars($empresa['distrito'] ?? '') ?>"
                        placeholder="CHICLAYO">
                </div>
            </div>
            <div class="emp-field">
                <label class="emp-label">Provincia</label>
                <div class="emp-input-wrap">
                    <i class="emp-input-icon fas fa-map"></i>
                    <input type="text" class="emp-input" name="provincia" maxlength="100"
                        style="text-transform:uppercase;"
                        value="<?= htmlspecialchars($empresa['provincia'] ?? '') ?>"
                        placeholder="CHICLAYO">
                </div>
            </div>
            <div class="emp-field">
                <label class="emp-label">Departamento</label>
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
                <label class="emp-label"><i class="fas fa-envelope mr-1"></i>Email</label>
                <div class="emp-input-wrap">
                    <i class="emp-input-icon fas fa-envelope"></i>
                    <input type="email" class="emp-input" name="email" maxlength="100"
                        value="<?= htmlspecialchars($empresa['email'] ?? '') ?>"
                        placeholder="inversiones123@gmail.com">
                </div>
            </div>
            <div class="emp-field">
                <label class="emp-label"><i class="fas fa-globe mr-1"></i>Sitio Web</label>
                <div class="emp-input-wrap">
                    <i class="emp-input-icon fas fa-globe"></i>
                    <input type="text" class="emp-input" name="web" maxlength="150"
                        value="<?= htmlspecialchars($empresa['web'] ?? '') ?>"
                        placeholder="www.sysinversiones.com">
                </div>
            </div>
        </div>
        <div class="emp-field">
            <label class="emp-label"><i class="fas fa-comment-alt mr-1"></i>Pie de Comprobante</label>
            <div class="emp-input-wrap">
                <i class="emp-input-icon fas fa-comment-alt"></i>
                <input type="text" class="emp-input" name="pie_comprobante" maxlength="300"
                    value="<?= htmlspecialchars($empresa['pie_comprobante'] ?? 'Gracias por su preferencia') ?>"
                    placeholder="Gracias por su preferencia">
            </div>
            <span class="emp-input-hint">Aparece al final de cada comprobante impreso.</span>
        </div>
    </div>

    <!-- TAB: COMPROBANTES -->
    <div class="emp-tab-content" id="tab-comprobantes">
        <p style="font-size:.83rem;color:#6c757d;margin-bottom:20px;">
            <i class="fas fa-info-circle mr-1" style="color:#1a5276;"></i>
            La serie se combina con el correlativo automatico.
            Ejemplo: <strong style="color:#1a5276;">T001</strong>-00000001
        </p>
        <div class="emp-field-row cols-2">
            <div class="emp-serie-card">
                <div class="emp-serie-title"><i class="fas fa-receipt mr-1"></i>Serie Ticket de Compra</div>
                <input type="text" class="emp-serie-input" name="serie_ticket" id="inp_serie_ticket"
                    maxlength="4"
                    value="<?= htmlspecialchars($empresa['serie_ticket'] ?? 'T001') ?>">
                <div class="emp-serie-preview">
                    Proximo: <strong id="prev_ticket_num"><?= htmlspecialchars($empresa['serie_ticket'] ?? 'T001') ?>-00000001</strong>
                </div>
            </div>
            <div class="emp-serie-card">
                <div class="emp-serie-title"><i class="fas fa-file-alt mr-1"></i>Serie Nota de Compra</div>
                <input type="text" class="emp-serie-input" name="serie_nota" id="inp_serie_nota"
                    maxlength="4"
                    value="<?= htmlspecialchars($empresa['serie_nota'] ?? 'N001') ?>">
                <div class="emp-serie-preview">
                    Proxima: <strong id="prev_nota_num"><?= htmlspecialchars($empresa['serie_nota'] ?? 'N001') ?>-00000001</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB: LOGO -->
    <div class="emp-tab-content" id="tab-logo">
        <p style="font-size:.83rem;color:#6c757d;margin-bottom:16px;">
            <i class="fas fa-info-circle mr-1" style="color:#1a5276;"></i>
            El logo aparece en la cabecera de todos los comprobantes PDF.
            Recomendado: <strong>300x100 px</strong>, fondo blanco o transparente.
        </p>
        <label for="inputLogo" class="emp-logo-dropzone" id="logoDropzone">
            <?php if (!empty($empresa['logo'])): ?>
            <img src="<?= htmlspecialchars($empresa['logo']) ?>" id="logoPreviewImg" alt="Logo actual" style="max-height:100px;max-width:200px;object-fit:contain;">
            <p style="font-size:.78rem;color:#adb5bd;margin:8px 0 0;">Logo actual — haz clic para cambiar</p>
            <?php else: ?>
            <div id="logoPlaceholder" style="text-align:center;padding:20px;">
                <i class="fas fa-cloud-upload-alt" style="font-size:2.5rem;color:#adb5bd;display:block;margin-bottom:8px;"></i>
                <p style="color:#6c757d;margin:0;">Haz clic o arrastra tu logo aqui</p>
                <small style="color:#adb5bd;">JPG, PNG o WEBP</small>
            </div>
            <img src="" id="logoPreviewImg" style="display:none;max-height:100px;max-width:200px;object-fit:contain;" alt="Preview">
            <?php endif; ?>
        </label>
        <input type="file" id="inputLogo" name="logo" accept=".jpg,.jpeg,.png,.webp" style="display:none;">
        <div id="logoFilename" style="text-align:center;margin-top:8px;display:none;font-size:.83rem;color:#1a5276;">
            <i class="fas fa-paperclip mr-1"></i><span id="logoFilenameText"></span>
        </div>
    </div>

    <!-- BOTON GUARDAR -->
    <div class="emp-footer">
        <span class="emp-footer-hint"><span class="req">*</span> Campos obligatorios</span>
        <button type="submit" class="btn-emp-save" id="btnGuardar">
            <i class="fas fa-save mr-1"></i>Guardar Cambios
        </button>
    </div>

</div><!-- /emp-form-panel -->

<!-- ═══════════════════════════════════════════
     PANEL DERECHO — Vista previa
═══════════════════════════════════════════ -->
<div class="emp-right-panel">
    <div class="emp-side-card">
        <div class="emp-side-card-header">
            <i class="fas fa-eye mr-1"></i>Vista Previa en Comprobante
        </div>
        <div class="emp-side-card-body">
            <div class="emp-preview-doc">
                <div class="emp-preview-doc-header">
                    <?php if (!empty($empresa['logo'])): ?>
                    <img src="<?= htmlspecialchars($empresa['logo']) ?>" id="prevLogoImg"
                         alt="Logo" style="max-height:40px;max-width:120px;object-fit:contain;margin-bottom:6px;">
                    <?php else: ?>
                    <div id="prevLogoImg" style="height:10px;"></div>
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
                    <div class="emp-preview-doc-tipo-label">TICKET / NOTA DE COMPRA</div>
                    <div class="emp-preview-doc-tipo-num" id="prevSerie">
                        N&deg; <?= htmlspecialchars($empresa['serie_ticket'] ?? 'T001') ?>-00000001
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
</div><!-- /content-wrapper -->

<?php include $ruta_base . 'includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {

    // TABS
    document.querySelectorAll('.emp-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.emp-tab').forEach(function(t){ t.classList.remove('active'); });
            document.querySelectorAll('.emp-tab-content').forEach(function(c){ c.classList.remove('active'); });
            this.classList.add('active');
            var target = document.getElementById('tab-' + this.dataset.tab);
            if (target) target.classList.add('active');
        });
    });

    // VISTA PREVIA EN TIEMPO REAL
    function bindPreview(name, id, fn) {
        var el = document.querySelector('[name=' + name + ']');
        var pr = document.getElementById(id);
        if (!el || !pr) return;
        el.addEventListener('input', function () { pr.textContent = fn ? fn(this.value) : this.value; });
    }
    bindPreview('razon_social',    'prevRazon', function(v){ return v || 'NOMBRE DE LA EMPRESA'; });
    bindPreview('ruc',             'prevRuc',   function(v){ return 'RUC: ' + (v || '00000000000'); });
    bindPreview('pie_comprobante', 'prevPie',   function(v){ return v || 'Gracias por su preferencia'; });
    bindPreview('telefono',        'prevTel',   function(v){ return v ? 'Tel: ' + v : ''; });

    function actualizarDir() {
        var dir = (document.querySelector('[name=direccion]') ? document.querySelector('[name=direccion]').value : '').trim();
        var dis = (document.querySelector('[name=distrito]')  ? document.querySelector('[name=distrito]').value  : '').trim();
        var el  = document.getElementById('prevDir');
        if (el) el.textContent = dir + (dis ? ' - ' + dis : '');
    }
    var elDir = document.querySelector('[name=direccion]');
    var elDis = document.querySelector('[name=distrito]');
    if (elDir) elDir.addEventListener('input', actualizarDir);
    if (elDis) elDis.addEventListener('input', actualizarDir);

    var elTicket = document.getElementById('inp_serie_ticket');
    if (elTicket) {
        elTicket.addEventListener('input', function () {
            var v = this.value.toUpperCase() || 'T001';
            var el1 = document.getElementById('prev_ticket_num');
            var el2 = document.getElementById('prevSerie');
            if (el1) el1.textContent = v + '-00000001';
            if (el2) el2.textContent = 'N\u00B0 ' + v + '-00000001';
        });
    }
    var elNota = document.getElementById('inp_serie_nota');
    if (elNota) {
        elNota.addEventListener('input', function () {
            var v = this.value.toUpperCase() || 'N001';
            var el = document.getElementById('prev_nota_num');
            if (el) el.textContent = v + '-00000001';
        });
    }

    // LOGO PREVIEW
    var inputLogo  = document.getElementById('inputLogo');
    var dropzone   = document.getElementById('logoDropzone');
    var previewImg = document.getElementById('logoPreviewImg');
    var placeholder= document.getElementById('logoPlaceholder');
    var filenameBox= document.getElementById('logoFilename');
    var filenameEl = document.getElementById('logoFilenameText');
    var prevLogo   = document.getElementById('prevLogoImg');

    if (inputLogo) {
        inputLogo.addEventListener('change', function () {
            var file = this.files[0];
            if (!file) return;
            var reader = new FileReader();
            reader.onload = function (e) {
                if (previewImg) { previewImg.src = e.target.result; previewImg.style.display = 'block'; }
                if (placeholder) placeholder.style.display = 'none';
                if (prevLogo && prevLogo.tagName === 'IMG') prevLogo.src = e.target.result;
            };
            reader.readAsDataURL(file);
            if (filenameEl) filenameEl.textContent = file.name;
            if (filenameBox) filenameBox.style.display = 'block';
        });
    }
    if (dropzone) {
        dropzone.addEventListener('dragover', function(e){ e.preventDefault(); this.style.borderColor='#1a5276'; });
        dropzone.addEventListener('dragleave', function(){ this.style.borderColor=''; });
        dropzone.addEventListener('drop', function(e){
            e.preventDefault(); this.style.borderColor='';
            var file = e.dataTransfer.files[0];
            if (file && inputLogo) {
                var dt = new DataTransfer(); dt.items.add(file); inputLogo.files = dt.files;
                inputLogo.dispatchEvent(new Event('change'));
            }
        });
    }

    // SUBMIT — validación JS + confirmación SweetAlert
    var form = document.getElementById('formEmpresa');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            // Validar razon social
            var razonSocial = document.querySelector('[name=razon_social]');
            if (!razonSocial || razonSocial.value.trim() === '') {
                Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'La razón social es obligatoria.', confirmButtonColor: '#1a5276' });
                // Ir al tab identificacion
                document.querySelector('[data-tab="identificacion"]').click();
                return;
            }

            // Validar RUC
            var ruc = document.querySelector('[name=ruc]') ? document.querySelector('[name=ruc]').value : '';
            if (!/^\d{11}$/.test(ruc)) {
                Swal.fire({ icon: 'warning', title: 'RUC inválido', text: 'El RUC debe tener exactamente 11 dígitos.', confirmButtonColor: '#1a5276' });
                document.querySelector('[data-tab="identificacion"]').click();
                return;
            }

            Swal.fire({
                icon: 'question',
                title: '¿Guardar cambios?',
                html: 'Se actualizarán los datos de la empresa.<br><small style="color:#6c757d;">Esta información aparecerá en todos los comprobantes.</small>',
                showCancelButton: true,
                confirmButtonColor: '#1a5276',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-save mr-1"></i> Sí, guardar',
                cancelButtonText: 'Cancelar'
            }).then(function (r) {
                if (r.isConfirmed) {
                    var btn = document.getElementById('btnGuardar');
                    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Guardando...'; }
                    form.removeEventListener('submit', arguments.callee);
                    form.submit();
                }
            });
        });
    }

});
</script>
