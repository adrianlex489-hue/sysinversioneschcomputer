/**
 * clientes.js — Módulo Gestión de Clientes | SysInversiones CH Computer
 * Personas Naturales (clientes_natural) y Empresas/Jurídicas (clientes_empresa)
 */

$(function () {

    // ── DataTables ────────────────────────────────────────────────────────────
    const dtOpts = {
        language: {
            search: '<i class="fas fa-search"></i>',
            searchPlaceholder: 'Buscar...',
            lengthMenu: 'Mostrar _MENU_',
            info: '_START_-_END_ de _TOTAL_',
            infoEmpty: 'Sin registros',
            zeroRecords: 'Sin resultados',
            paginate: { previous: '‹', next: '›' }
        },
        responsive: true,
        autoWidth: false,
        order: [[0, 'asc']],
        pageLength: 10
    };
    $('#tblNatActivos, #tblNatInactivos, #tblEmpActivos, #tblEmpInactivos').each(function () {
        $(this).DataTable(dtOpts);
    });

    // ── Tabs principales (Natural / Empresa) ──────────────────────────────────
    function activarTab(tipo) {
        if (tipo === 'natural') {
            $('#btnTabNatural').removeClass('inactivo');
            $('#btnTabEmpresa').removeClass('activo');
            $('#panelNatural').addClass('activo');
            $('#panelEmpresa').removeClass('activo');
        } else {
            $('#btnTabEmpresa').addClass('activo');
            $('#btnTabNatural').addClass('inactivo');
            $('#panelEmpresa').addClass('activo');
            $('#panelNatural').removeClass('activo');
        }
    }

    $('#btnTabNatural').on('click', function () { activarTab('natural'); });
    $('#btnTabEmpresa').on('click', function () { activarTab('empresa'); });

    // Botones cabecera abren el tab correcto
    $('#btnNuevoNatural').on('click', function () { activarTab('natural'); });
    $('#btnNuevoEmpresa').on('click', function () { activarTab('empresa'); });

    // ── Helper: submit POST oculto ────────────────────────────────────────────
    function submitAccion(accion, id, tabla) {
        const $f = $('<form method="POST" style="display:none;"></form>');
        $f.append(`<input type="hidden" name="accion"     value="${accion}">`);
        $f.append(`<input type="hidden" name="id_cliente" value="${id}">`);
        $f.append(`<input type="hidden" name="tabla"      value="${tabla}">`);
        $('body').append($f);
        $f.submit();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PERSONAS NATURALES — ACCIONES
    // ══════════════════════════════════════════════════════════════════════════

    // VER
    $(document).on('click', '.btn-ver-nat', function () {
        const d = $(this).data();
        $('#vn_nombre').text([d.nombres, d.ap, d.am].filter(Boolean).join(' '));
        $('#vn_doc').text(d.doc || '—');
        $('#vn_tel').text(d.tel || '—');
        $('#vn_email').text(d.email || '—');
        $('#vn_dir').text(d.dir || '—');
        $('#vn_fecha').text(d.fecha || '—');
        const esActivo = String(d.estado) === '1';
        $('#vn_estado').html(esActivo
            ? '<span style="background:#d1fae5;color:#065f46;padding:3px 10px;border-radius:20px;font-size:.78rem;font-weight:700;"><i class="fas fa-check-circle mr-1"></i>Activo</span>'
            : '<span style="background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:20px;font-size:.78rem;font-weight:700;"><i class="fas fa-times-circle mr-1"></i>Inactivo</span>');
        $('#modalVerNatural').modal('show');
    });

    // EDITAR
    $(document).on('click', '.btn-editar-nat', function () {
        const d = $(this).data();
        $('#en_id').val(d.id);
        $('#en_tipodoc').val(d.tipodoc || 'DNI');
        $('#en_doc').val(d.doc);
        $('#en_nombres').val(d.nombres);
        $('#en_ap').val(d.ap);
        $('#en_am').val(d.am);
        $('#en_tel').val(d.tel);
        $('#en_email').val(d.email);
        $('#en_dir').val(d.dir);
        $('#en_estado').val(String(d.estado));
        $('#modalEditarNatural').modal('show');
    });

    // DESACTIVAR / ELIMINAR
    $(document).on('click', '.btn-del-nat', function () {
        const id = $(this).data('id'), nombre = $(this).data('nombre');
        Swal.fire({
            icon: 'warning',
            title: `Eliminar a ${nombre}`,
            html: `<div class="d-flex justify-content-center gap-3 mt-3">
                     <button id="sw-desact" class="swal2-confirm swal2-styled" style="background:#e67e22;">
                       <i class="fas fa-ban mr-1"></i>Desactivar
                     </button>
                     <button id="sw-perm" class="swal2-confirm swal2-styled" style="background:#e74c3c;">
                       <i class="fas fa-trash-alt mr-1"></i>Eliminar permanente
                     </button>
                   </div>`,
            showConfirmButton: false,
            showCancelButton: true,
            cancelButtonText: 'Cancelar',
            cancelButtonColor: '#6c757d',
            didOpen: () => {
                document.getElementById('sw-desact').addEventListener('click', () => {
                    Swal.close();
                    Swal.fire({
                        icon: 'warning', title: '¿Desactivar?',
                        html: `<b>${nombre}</b> pasará a inactivos.`,
                        showCancelButton: true, confirmButtonColor: '#e67e22', cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Sí, desactivar', cancelButtonText: 'Cancelar'
                    }).then(r => { if (r.isConfirmed) submitAccion('desactivar', id, 'natural'); });
                });
                document.getElementById('sw-perm').addEventListener('click', () => {
                    Swal.close();
                    Swal.fire({
                        icon: 'error', title: '¿Eliminar permanentemente?',
                        html: `<b>${nombre}</b> será eliminado.<br><strong class="text-danger">No se puede deshacer.</strong>`,
                        showCancelButton: true, confirmButtonColor: '#e74c3c', cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar'
                    }).then(r => { if (r.isConfirmed) submitAccion('eliminar_permanente', id, 'natural'); });
                });
            }
        });
    });

    // REACTIVAR
    $(document).on('click', '.btn-react-nat', function () {
        const id = $(this).data('id'), nombre = $(this).data('nombre');
        Swal.fire({
            icon: 'question', title: '¿Reactivar?',
            html: `<b>${nombre}</b> volverá a activos.`,
            showCancelButton: true, confirmButtonColor: '#1a7a4a', cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, reactivar', cancelButtonText: 'Cancelar'
        }).then(r => { if (r.isConfirmed) submitAccion('reactivar', id, 'natural'); });
    });

    // ELIMINAR PERMANENTE (desde inactivos)
    $(document).on('click', '.btn-perm-nat', function () {
        const id = $(this).data('id'), nombre = $(this).data('nombre');
        Swal.fire({
            icon: 'error', title: '¿Eliminar permanentemente?',
            html: `<b>${nombre}</b> será eliminado.<br><strong class="text-danger">No se puede deshacer.</strong>`,
            showCancelButton: true, confirmButtonColor: '#e74c3c', cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar'
        }).then(r => { if (r.isConfirmed) submitAccion('eliminar_permanente', id, 'natural'); });
    });

    // ══════════════════════════════════════════════════════════════════════════
    // EMPRESAS / JURÍDICAS — ACCIONES
    // ══════════════════════════════════════════════════════════════════════════

    // VER
    $(document).on('click', '.btn-ver-emp', function () {
        const d = $(this).data();
        $('#ve_rs').text(d.rs || '—');
        $('#ve_ruc').text('RUC: ' + (d.ruc || '—'));
        $('#ve_tel').text(d.tel || '—');
        $('#ve_email').text(d.email || '—');
        $('#ve_dir').text(d.dir || '—');
        $('#ve_fecha').text(d.fecha || '—');
        const esActivo = String(d.estado) === '1';
        $('#ve_estado').html(esActivo
            ? '<span style="background:#d1fae5;color:#065f46;padding:3px 10px;border-radius:20px;font-size:.78rem;font-weight:700;"><i class="fas fa-check-circle mr-1"></i>Activo</span>'
            : '<span style="background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:20px;font-size:.78rem;font-weight:700;"><i class="fas fa-times-circle mr-1"></i>Inactivo</span>');
        $('#modalVerEmpresa').modal('show');
    });

    // EDITAR
    $(document).on('click', '.btn-editar-emp', function () {
        const d = $(this).data();
        $('#ee_id').val(d.id);
        $('#ee_ruc').val(d.ruc);
        $('#ee_rs').val(d.rs);
        $('#ee_tel').val(d.tel);
        $('#ee_email').val(d.email);
        $('#ee_dir').val(d.dir);
        $('#ee_estado').val(String(d.estado));
        $('#modalEditarEmpresa').modal('show');
    });

    // DESACTIVAR / ELIMINAR
    $(document).on('click', '.btn-del-emp', function () {
        const id = $(this).data('id'), nombre = $(this).data('nombre');
        Swal.fire({
            icon: 'warning',
            title: `Eliminar ${nombre}`,
            html: `<div class="d-flex justify-content-center gap-3 mt-3">
                     <button id="sw-desact-e" class="swal2-confirm swal2-styled" style="background:#e67e22;">
                       <i class="fas fa-ban mr-1"></i>Desactivar
                     </button>
                     <button id="sw-perm-e" class="swal2-confirm swal2-styled" style="background:#e74c3c;">
                       <i class="fas fa-trash-alt mr-1"></i>Eliminar permanente
                     </button>
                   </div>`,
            showConfirmButton: false,
            showCancelButton: true,
            cancelButtonText: 'Cancelar',
            cancelButtonColor: '#6c757d',
            didOpen: () => {
                document.getElementById('sw-desact-e').addEventListener('click', () => {
                    Swal.close();
                    Swal.fire({
                        icon: 'warning', title: '¿Desactivar?',
                        html: `<b>${nombre}</b> pasará a inactivos.`,
                        showCancelButton: true, confirmButtonColor: '#e67e22', cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Sí, desactivar', cancelButtonText: 'Cancelar'
                    }).then(r => { if (r.isConfirmed) submitAccion('desactivar', id, 'empresa'); });
                });
                document.getElementById('sw-perm-e').addEventListener('click', () => {
                    Swal.close();
                    Swal.fire({
                        icon: 'error', title: '¿Eliminar permanentemente?',
                        html: `<b>${nombre}</b> será eliminado.<br><strong class="text-danger">No se puede deshacer.</strong>`,
                        showCancelButton: true, confirmButtonColor: '#e74c3c', cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar'
                    }).then(r => { if (r.isConfirmed) submitAccion('eliminar_permanente', id, 'empresa'); });
                });
            }
        });
    });

    // REACTIVAR
    $(document).on('click', '.btn-react-emp', function () {
        const id = $(this).data('id'), nombre = $(this).data('nombre');
        Swal.fire({
            icon: 'question', title: '¿Reactivar?',
            html: `<b>${nombre}</b> volverá a activos.`,
            showCancelButton: true, confirmButtonColor: '#c05621', cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, reactivar', cancelButtonText: 'Cancelar'
        }).then(r => { if (r.isConfirmed) submitAccion('reactivar', id, 'empresa'); });
    });

    // ELIMINAR PERMANENTE (desde inactivos)
    $(document).on('click', '.btn-perm-emp', function () {
        const id = $(this).data('id'), nombre = $(this).data('nombre');
        Swal.fire({
            icon: 'error', title: '¿Eliminar permanentemente?',
            html: `<b>${nombre}</b> será eliminado.<br><strong class="text-danger">No se puede deshacer.</strong>`,
            showCancelButton: true, confirmButtonColor: '#e74c3c', cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar'
        }).then(r => { if (r.isConfirmed) submitAccion('eliminar_permanente', id, 'empresa'); });
    });

    // ══════════════════════════════════════════════════════════════════════════
    // CONSULTA RENIEC (modal crear persona natural)
    // ══════════════════════════════════════════════════════════════════════════
    $('#btn_reniec_cn').on('click', function () {
        const doc  = $('#cn_doc').val().trim();
        const tipo = $('#cn_tipodoc').val();
        if (tipo !== 'DNI' || !/^\d{8}$/.test(doc)) {
            Swal.fire({ icon: 'warning', title: 'DNI inválido', text: 'Ingresa exactamente 8 dígitos para consultar RENIEC.', confirmButtonColor: '#1a7a4a' });
            return;
        }
        const $btn = $(this), orig = $btn.html();
        $btn.html('<span class="spinner-border spinner-border-sm"></span>').prop('disabled', true);
        $('#reniec_result_cn').removeClass('show');
        $.ajax({ url: 'api_consultar_dni.php', method: 'POST', data: { dni: doc }, dataType: 'json' })
        .done(function (res) {
            if (res.success && res.datos) {
                const d = res.datos;
                $('#cn_nombres').val(d.nombres || '');
                $('#cn_ap').val(d.apellido_paterno || '');
                $('#cn_am').val(d.apellido_materno || '');
                if (d.direccion) $('#cn_dir').val(d.direccion);
                $('#rr_cn_nombre').text([d.nombres, d.apellido_paterno, d.apellido_materno].filter(Boolean).join(' '));
                $('#rr_cn_info').html(`DNI: <b>${doc}</b>`);
                $('#reniec_result_cn').addClass('show');
            } else {
                Swal.fire({ icon: 'error', title: 'No encontrado', text: res.error || 'DNI no encontrado en RENIEC.', confirmButtonColor: '#1a7a4a' });
            }
        })
        .fail(function () { Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar con la API.', confirmButtonColor: '#1a7a4a' }); })
        .always(function () { $btn.html(orig).prop('disabled', false); });
    });
    $('#cn_doc').on('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); $('#btn_reniec_cn').click(); } });

    // ══════════════════════════════════════════════════════════════════════════
    // CONSULTA SUNAT (modal crear empresa)
    // ══════════════════════════════════════════════════════════════════════════
    $('#btn_sunat_ce').on('click', function () {
        const ruc = $('#ce_ruc').val().trim();
        if (!/^\d{11}$/.test(ruc)) {
            Swal.fire({ icon: 'warning', title: 'RUC inválido', text: 'Ingresa exactamente 11 dígitos.', confirmButtonColor: '#c05621' });
            return;
        }
        const $btn = $(this), orig = $btn.html();
        $btn.html('<span class="spinner-border spinner-border-sm"></span>').prop('disabled', true);
        $('#sunat_result_ce').removeClass('show');
        $.ajax({ url: 'api_consultar_ruc.php', method: 'POST', data: { ruc }, dataType: 'json' })
        .done(function (res) {
            if (res.success && res.datos) {
                const d = res.datos;
                $('#ce_rs').val(d.razon_social || '');
                if (d.direccion) $('#ce_dir').val(d.direccion);
                $('#sr_ce_nombre').text(d.razon_social || '');
                $('#sr_ce_info').html(`RUC: <b>${ruc}</b>${d.estado_sunat ? ' | Estado: <b>' + d.estado_sunat + '</b>' : ''}`);
                $('#sunat_result_ce').addClass('show');
            } else {
                Swal.fire({ icon: 'error', title: 'No encontrado', text: res.error || 'RUC no encontrado en SUNAT.', confirmButtonColor: '#c05621' });
            }
        })
        .fail(function () { Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo conectar con la API.', confirmButtonColor: '#c05621' }); })
        .always(function () { $btn.html(orig).prop('disabled', false); });
    });
    $('#ce_ruc').on('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); $('#btn_sunat_ce').click(); } });

    // ══════════════════════════════════════════════════════════════════════════
    // EXPORTACIÓN — CSV / Excel / PDF
    // ══════════════════════════════════════════════════════════════════════════

    // Detecta qué tab está activo para exportar solo ese conjunto
    function getTabActivo() {
        return $('#panelEmpresa').hasClass('activo') ? 'empresa' : 'natural';
    }

    $('#btn-exportar-csv').on('click', function () {
        window.location.href = 'ajax_clientes_export.php?exportar=csv&tipo=' + getTabActivo();
    });

    $('#btn-exportar-excel').on('click', function () {
        window.location.href = 'ajax_clientes_export.php?exportar=excel&tipo=' + getTabActivo();
    });

    $('#btn-exportar-pdf').on('click', function () {
        window.open('clientes_pdf.php?tipo=' + getTabActivo(), '_blank');
    });

    // ══════════════════════════════════════════════════════════════════════════
    // IMPORTACIÓN DESDE EXCEL
    // ══════════════════════════════════════════════════════════════════════════

    let importTipo = 'natural'; // 'natural' | 'empresa'

    // Colores por tipo
    const importColors = {
        natural: { bg: 'linear-gradient(135deg,#1a5276,#2563eb)', btn: '#2563eb' },
        empresa: { bg: 'linear-gradient(135deg,#7b341e,#c05621)', btn: '#c05621' },
    };

    function abrirModalImportar(tipo) {
        importTipo = tipo;
        const label  = tipo === 'natural' ? 'Personas Naturales' : 'Personas Jurídicas';
        const colors = importColors[tipo];

        // 1. Primero reset de pasos/estado
        resetImportModal();

        // 2. Luego aplicar estilos del tipo (después del reset para no sobreescribir)
        $('#importModalHeader').css('background', colors.bg).css('color', '#fff');
        $('#importModalTitle').html(`<i class="fas fa-file-import mr-2"></i>Importar ${label}`);
        $('#importBtnProcesar').css('background', colors.btn).css('border-color', colors.btn).css('color', '#fff');
        $('#importLinkPlantilla').attr('href', `ajax_clientes_plantilla.php?tipo=${tipo}`);

        // 3. Abrir modal con pequeño delay para que el dropdown termine de cerrarse
        //    Desactivar enforceFocus de Bootstrap para que el input[type=file] funcione
        setTimeout(function () {
            // Parche Bootstrap 4: enforceFocus bloquea el diálogo nativo de archivos
            const orig = $.fn.modal.Constructor.prototype.enforceFocus;
            $.fn.modal.Constructor.prototype.enforceFocus = function () {};
            $('#modalImportarClientes').modal('show');
            $('#modalImportarClientes').one('hidden.bs.modal', function () {
                $.fn.modal.Constructor.prototype.enforceFocus = orig;
            });
        }, 150);
    }

    function resetImportModal() {
        $('#importStep1').show();
        $('#importStep2').hide();
        $('#importStep3').hide();
        $('#importFileInfo').hide();
        $('#importDropZone').show();
        $('#importBtnProcesar').hide();
        $('#importBtnRecargar').hide();
        $('#importBtnCerrar').show();
        $('#importDropZone').css('border-color', '#cbd5e1').css('background', '#f8fafc');
        $('#importDropIcon').css('color', '#94a3b8');
    }

    // Abrir modal desde botones del dropdown
    $('#btnImportarNatural').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        // Cerrar dropdown primero, luego abrir modal
        $(this).closest('.dropdown').removeClass('show');
        $(this).closest('.dropdown-menu').removeClass('show');
        abrirModalImportar('natural');
    });
    $('#btnImportarEmpresa').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).closest('.dropdown').removeClass('show');
        $(this).closest('.dropdown-menu').removeClass('show');
        abrirModalImportar('empresa');
    });

    // Click en zona de arrastre o botón seleccionar → abrir file input
    $(document).on('click', '#importDropZone', function (e) {
        if ($(e.target).is('#importBtnSeleccionar') || $(e.target).closest('#importBtnSeleccionar').length) return;
        document.getElementById('importFileInput').click();
    });

    $(document).on('click', '#importBtnSeleccionar', function (e) {
        e.preventDefault();
        e.stopPropagation();
        document.getElementById('importFileInput').click();
    });

    // Drag & Drop
    $('#importDropZone').on('dragover', function (e) {
        e.preventDefault();
        $(this).css('border-color', importColors[importTipo].btn).css('background', '#eff6ff');
        $('#importDropIcon').css('color', importColors[importTipo].btn);
    }).on('dragleave', function () {
        $(this).css('border-color', '#cbd5e1').css('background', '#f8fafc');
        $('#importDropIcon').css('color', '#94a3b8');
    }).on('drop', function (e) {
        e.preventDefault();
        $(this).css('border-color', '#cbd5e1').css('background', '#f8fafc');
        const files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) procesarArchivoSeleccionado(files[0]);
    });

    // Selección por input
    $('#importFileInput').on('change', function () {
        if (this.files.length > 0) procesarArchivoSeleccionado(this.files[0]);
    });

    function procesarArchivoSeleccionado(file) {
        const ext = file.name.split('.').pop().toLowerCase();
        if (!['xlsx', 'xls', 'csv'].includes(ext)) {
            Swal.fire({ icon: 'warning', title: 'Formato no válido', text: 'Solo se aceptan archivos .xlsx, .xls o .csv', confirmButtonColor: importColors[importTipo].btn });
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            Swal.fire({ icon: 'warning', title: 'Archivo muy grande', text: 'El archivo no debe superar 5 MB.', confirmButtonColor: importColors[importTipo].btn });
            return;
        }

        // Mostrar info del archivo
        $('#importFileName').text(file.name);
        $('#importFileSize').text(formatBytes(file.size));
        $('#importFileInfo').show();
        $('#importDropZone').hide();
        $('#importBtnProcesar').show();

        // Guardar referencia al archivo
        $('#importBtnProcesar').data('file', file);
    }

    // Quitar archivo seleccionado
    $('#importBtnQuitarArchivo').on('click', function () {
        $('#importFileInput').val('');
        $('#importFileInfo').hide();
        $('#importDropZone').show();
        $('#importBtnProcesar').hide();
    });

    // Procesar importación
    $('#importBtnProcesar').on('click', function () {
        const file = $(this).data('file');
        if (!file) return;

        const formData = new FormData();
        formData.append('archivo', file);
        formData.append('tipo', importTipo);

        // Paso 2: progreso
        $('#importStep1').hide();
        $('#importStep2').show();
        $('#importBtnProcesar').hide();
        $('#importBtnCerrar').hide();

        $.ajax({
            url: 'ajax_clientes_import.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
        })
        .done(function (res) {
            $('#importStep2').hide();
            $('#importStep3').show();
            $('#importBtnCerrar').show();

            if (!res.ok) {
                $('#importResultado').html(`
                    <div class="alert alert-danger border-0" style="border-radius:8px;">
                        <i class="fas fa-times-circle mr-2"></i><strong>Error:</strong> ${res.error || 'Error desconocido.'}
                    </div>
                `);
                return;
            }

            const colorBtn = importColors[importTipo].btn;
            const tipoLabel = importTipo === 'natural' ? 'Personas Naturales' : 'Personas Jurídicas';

            // Resumen
            let html = `
                <div class="row text-center mb-3">
                    <div class="col-4">
                        <div style="background:#f0fdf4;border-radius:10px;padding:16px 8px;border:1px solid #bbf7d0;">
                            <div style="font-size:1.8rem;font-weight:700;color:#166534;">${res.insertados}</div>
                            <div style="font-size:.8rem;color:#166534;">Importados</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div style="background:#fef9c3;border-radius:10px;padding:16px 8px;border:1px solid #fde047;">
                            <div style="font-size:1.8rem;font-weight:700;color:#854d0e;">${res.omitidos}</div>
                            <div style="font-size:.8rem;color:#854d0e;">Omitidos</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div style="background:#eff6ff;border-radius:10px;padding:16px 8px;border:1px solid #bfdbfe;">
                            <div style="font-size:1.8rem;font-weight:700;color:#1e40af;">${res.insertados + res.omitidos}</div>
                            <div style="font-size:.8rem;color:#1e40af;">Total leídos</div>
                        </div>
                    </div>
                </div>
            `;

            if (res.insertados > 0) {
                html += `<div class="alert alert-success border-0 mb-2" style="border-radius:8px;font-size:.88rem;">
                    <i class="fas fa-check-circle mr-2"></i>
                    <strong>${res.insertados}</strong> cliente(s) de tipo <strong>${tipoLabel}</strong> importados correctamente.
                </div>`;
            }

            if (res.errores && res.errores.length > 0) {
                const totalErr = res.total_errores || res.errores.length;
                html += `<div class="alert alert-warning border-0 mb-2" style="border-radius:8px;font-size:.85rem;">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <strong>${totalErr}</strong> fila(s) con advertencias:
                    <ul class="mb-0 mt-1 pl-3" style="max-height:120px;overflow-y:auto;">
                        ${res.errores.map(e => `<li>${e}</li>`).join('')}
                        ${totalErr > res.errores.length ? `<li class="text-muted">... y ${totalErr - res.errores.length} más.</li>` : ''}
                    </ul>
                </div>`;
            }

            if (res.insertados > 0) {
                $('#importBtnRecargar').show();
            }

            $('#importResultado').html(html);
        })
        .fail(function () {
            $('#importStep2').hide();
            $('#importStep3').show();
            $('#importBtnCerrar').show();
            $('#importResultado').html(`
                <div class="alert alert-danger border-0" style="border-radius:8px;">
                    <i class="fas fa-times-circle mr-2"></i>Error de conexión. Intenta nuevamente.
                </div>
            `);
        });
    });

    // Recargar página tras importación exitosa
    $('#importBtnRecargar').on('click', function () {
        const tab = importTipo === 'empresa' ? 'empresa' : 'natural';
        window.location.href = 'clientes.php?tab=' + tab;
    });

    // Reset al cerrar modal
    $('#modalImportarClientes').on('hidden.bs.modal', function () {
        resetImportModal();
        // Limpiar el input file (está fuera del modal)
        $('#importFileInput').val('');
    });

    function formatBytes(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    // Solo dígitos en campos numéricos
    $('#cn_doc, #ce_ruc, #ee_ruc, #en_doc').on('input', function () {
        this.value = this.value.replace(/[^0-9]/g, '');
    });

    // Limpiar modales al abrir
    $('#modalCrearNatural').on('show.bs.modal', function () {
        $(this).find('form')[0].reset();
        $('#reniec_result_cn').removeClass('show');
    });
    $('#modalCrearEmpresa').on('show.bs.modal', function () {
        $(this).find('form')[0].reset();
        $('#sunat_result_ce').removeClass('show');
    });

});
