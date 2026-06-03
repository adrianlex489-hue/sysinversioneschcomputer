/**
 * proveedores.js — Módulo Gestión de Proveedores | Botica 2026
 * Acciones: Ver (ojito), Editar (lápiz), Eliminar (tacho)
 * Consulta RUC via API SUNAT (miapi.cloud)
 */

$(function () {

    // ── DataTables ────────────────────────────────────────────────────────────
    const dtOpts = {
        language: {
            search:            '<i class="fas fa-search"></i>',
            searchPlaceholder: 'Buscar proveedor...',
            lengthMenu:        'Mostrar _MENU_ registros',
            info:              'Mostrando _START_ a _END_ de _TOTAL_',
            infoEmpty:         'Sin registros',
            zeroRecords:       'No se encontraron resultados',
            paginate:          { previous: '‹', next: '›' }
        },
        responsive: true,
        autoWidth:  false,
        order:      [[0, 'asc']],
        pageLength: 10,
    };

    if ($('#tablaProveedoresActivos').length)   $('#tablaProveedoresActivos').DataTable(dtOpts);
    if ($('#tablaProveedoresInactivos').length) $('#tablaProveedoresInactivos').DataTable(dtOpts);

    // ── Helper: enviar formulario POST oculto ─────────────────────────────────
    function submitAccion(accion, id) {
        const $f = $('<form method="POST" style="display:none;"></form>');
        $f.append(`<input type="hidden" name="accion"       value="${accion}">`);
        $f.append(`<input type="hidden" name="id_proveedor" value="${id}">`);
        $('body').append($f);
        $f.submit();
    }

    // ── VER proveedor ─────────────────────────────────────────────────────────
    $(document).on('click', '.btn-ver-proveedor', function () {
        const d = $(this).data();

        // Cabecera
        $('#ver_razon_social').text(d.razonSocial || '—');
        $('#ver_ruc').text(d.ruc || '—');

        // Campos
        $('#ver_telefono').text(d.telefono  || '—');
        $('#ver_email').text(d.email        || '—');
        $('#ver_direccion').text(d.direccion || '—');
        $('#ver_contacto').text(d.contacto  || '—');
        $('#ver_fecha').text(d.fecha        || '—');
        $('#ver_estado').html(d.estado == 1
            ? '<span style="background:#d4edda;color:#155724;padding:3px 10px;border-radius:20px;font-size:.8rem;font-weight:600;"><i class="fas fa-check-circle mr-1"></i>Activo</span>'
            : '<span style="background:#f8d7da;color:#721c24;padding:3px 10px;border-radius:20px;font-size:.8rem;font-weight:600;"><i class="fas fa-times-circle mr-1"></i>Inactivo</span>'
        );
        $('#modalVerProveedor').modal('show');
    });

    // ── EDITAR proveedor ──────────────────────────────────────────────────────
    $(document).on('click', '.btn-editar-proveedor', function () {
        const d = $(this).data();
        $('#editar_id_proveedor').val(d.id);
        $('#editar_ruc').val(d.ruc);
        $('#editar_razon_social').val(d.razonSocial  || '');
        $('#editar_telefono').val(d.telefono          || '');
        $('#editar_email').val(d.email                || '');
        $('#editar_direccion').val(d.direccion        || '');
        $('#editar_contacto').val(d.contacto          || '');
        $('#editar_estado').val(String(d.estado));
        $('#sunat_result_editar').removeClass('show');
        $('#modalEditarProveedor').modal('show');
    });

    // ── ELIMINAR desde activos → lógico o permanente ──────────────────────────
    $(document).on('click', '.btn-eliminar-proveedor', function () {
        const id     = $(this).data('id');
        const nombre = $(this).data('nombre');

        Swal.fire({
            icon:  'warning',
            title: `Eliminar a ${nombre}`,
            html:  `<p class="mb-2">Elige cómo deseas eliminar este proveedor:</p>
                    <div class="d-flex justify-content-center gap-3 mt-3">
                        <button id="swal-logico" class="swal2-confirm swal2-styled" style="background:#e67e22;">
                            <i class="fas fa-ban mr-1"></i> Desactivar
                        </button>
                        <button id="swal-permanente" class="swal2-confirm swal2-styled" style="background:#e74c3c;">
                            <i class="fas fa-trash-alt mr-1"></i> Eliminar permanente
                        </button>
                    </div>`,
            showConfirmButton: false,
            showCancelButton:  true,
            cancelButtonText:  '<i class="fas fa-times mr-1"></i> Cancelar',
            cancelButtonColor: '#6c757d',
            didOpen: () => {
                document.getElementById('swal-logico').addEventListener('click', () => {
                    Swal.close();
                    Swal.fire({
                        icon:             'warning',
                        title:            '¿Desactivar proveedor?',
                        html:             `<b>${nombre}</b> pasará a la lista de inactivos.<br>Podrás reactivarlo cuando quieras.`,
                        showCancelButton: true,
                        confirmButtonColor: '#e67e22',
                        cancelButtonColor:  '#6c757d',
                        confirmButtonText:  'Sí, desactivar',
                        cancelButtonText:   'Cancelar',
                    }).then(r => { if (r.isConfirmed) submitAccion('desactivar', id); });
                });

                document.getElementById('swal-permanente').addEventListener('click', () => {
                    Swal.close();
                    Swal.fire({
                        icon:             'error',
                        title:            '¿Eliminar permanentemente?',
                        html:             `<b>${nombre}</b> será eliminado de la base de datos.<br><strong class="text-danger">Esta acción NO se puede deshacer.</strong>`,
                        showCancelButton: true,
                        confirmButtonColor: '#e74c3c',
                        cancelButtonColor:  '#6c757d',
                        confirmButtonText:  'Sí, eliminar',
                        cancelButtonText:   'Cancelar',
                    }).then(r => { if (r.isConfirmed) submitAccion('eliminar_permanente', id); });
                });
            }
        });
    });

    // ── REACTIVAR desde inactivos ─────────────────────────────────────────────
    $(document).on('click', '.btn-reactivar-proveedor', function () {
        const id     = $(this).data('id');
        const nombre = $(this).data('nombre');
        Swal.fire({
            icon:             'question',
            title:            '¿Reactivar proveedor?',
            html:             `<b>${nombre}</b> volverá a la lista de proveedores activos.`,
            showCancelButton: true,
            confirmButtonColor: '#1a5276',
            cancelButtonColor:  '#6c757d',
            confirmButtonText:  '<i class="fas fa-check mr-1"></i> Sí, reactivar',
            cancelButtonText:   'Cancelar',
        }).then(r => { if (r.isConfirmed) submitAccion('reactivar', id); });
    });

    // ── ELIMINAR PERMANENTE desde inactivos ───────────────────────────────────
    $(document).on('click', '.btn-eliminar-permanente-prov', function () {
        const id     = $(this).data('id');
        const nombre = $(this).data('nombre');
        Swal.fire({
            icon:             'error',
            title:            '¿Eliminar permanentemente?',
            html:             `<b>${nombre}</b> será eliminado de la base de datos.<br><strong class="text-danger">Esta acción NO se puede deshacer.</strong>`,
            showCancelButton: true,
            confirmButtonColor: '#e74c3c',
            cancelButtonColor:  '#6c757d',
            confirmButtonText:  '<i class="fas fa-trash-alt mr-1"></i> Sí, eliminar',
            cancelButtonText:   'Cancelar',
        }).then(r => { if (r.isConfirmed) submitAccion('eliminar_permanente', id); });
    });

    // ── Consulta SUNAT/RUC ────────────────────────────────────────────────────
    function consultarRuc(rucInputId, resultBoxId, prefijo) {
        const ruc = $(`#${rucInputId}`).val().trim();

        if (!/^\d{11}$/.test(ruc)) {
            Swal.fire({ icon: 'warning', title: 'RUC inválido', text: 'Ingrese exactamente 11 dígitos numéricos.', confirmButtonColor: '#1a5276' });
            return;
        }

        const $btn = $(`#btn_sunat_${prefijo}`);
        const textoOriginal = $btn.html();
        $btn.html('<span class="spinner-border spinner-border-sm"></span> Consultando...').prop('disabled', true);
        $(`#${resultBoxId}`).removeClass('show');

        $.ajax({
            url:      'api_consultar_ruc.php',
            method:   'POST',
            data:     { ruc },
            dataType: 'json',
        })
        .done(function (res) {
            if (res.success && res.datos) {
                const d = res.datos;
                $(`#${prefijo}_razon_social`).val(d.razon_social);
                $(`#${prefijo}_direccion`).val(d.direccion);

                // Mostrar resultado visual
                $(`#${resultBoxId} .sunat-razon`).text(d.razon_social);
                $(`#${resultBoxId} .sunat-estado`).html(
                    `Estado SUNAT: <b>${d.estado_sunat}</b> | Condición: <b>${d.condicion}</b>`
                );
                $(`#${resultBoxId}`).addClass('show');
            } else {
                Swal.fire({ icon: 'error', title: 'No encontrado', text: res.error || 'RUC no encontrado en SUNAT.', confirmButtonColor: '#1a5276' });
            }
        })
        .fail(function () {
            Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo conectar con la API SUNAT.', confirmButtonColor: '#1a5276' });
        })
        .always(function () {
            $btn.html(textoOriginal).prop('disabled', false);
        });
    }

    $('#btn_sunat_crear').on('click',  function () { consultarRuc('crear_ruc',  'sunat_result_crear',  'crear');  });
    $('#btn_sunat_editar').on('click', function () { consultarRuc('editar_ruc', 'sunat_result_editar', 'editar'); });

    // Enter en campo RUC dispara consulta
    $('#crear_ruc').on('keydown',  function (e) { if (e.key === 'Enter') { e.preventDefault(); $('#btn_sunat_crear').trigger('click');  } });
    $('#editar_ruc').on('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); $('#btn_sunat_editar').trigger('click'); } });

    // ── Limpiar modal Crear al abrir ──────────────────────────────────────────
    $('#modalCrearProveedor').on('show.bs.modal', function () {
        $(this).find('form')[0].reset();
        $('#sunat_result_crear').removeClass('show');
    });

    // ══════════════════════════════════════════════════════════════════════════
    // IMPORTACIÓN DESDE EXCEL / CSV
    // ══════════════════════════════════════════════════════════════════════════

    function resetProvImport() {
        $('#provImportStep1').show();
        $('#provImportStep2').hide();
        $('#provImportStep3').hide();
        $('#provImportFileInfo').hide();
        $('#provImportDropZone').show();
        $('#provImportBtnProcesar').hide();
        $('#provImportBtnRecargar').hide();
        $('#provImportBtnCerrar').show();
        $('#provImportDropZone').css('border-color', '#cbd5e1').css('background', '#f8fafc');
        $('#provImportDropIcon').css('color', '#94a3b8');
    }

    // Abrir modal
    $('#btnImportarProveedores').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).closest('.dropdown').removeClass('show');
        $(this).closest('.dropdown-menu').removeClass('show');
        resetProvImport();
        setTimeout(function () {
            const orig = $.fn.modal.Constructor.prototype.enforceFocus;
            $.fn.modal.Constructor.prototype.enforceFocus = function () {};
            $('#modalImportarProveedores').modal('show');
            $('#modalImportarProveedores').one('hidden.bs.modal', function () {
                $.fn.modal.Constructor.prototype.enforceFocus = orig;
            });
        }, 150);
    });

    // Click zona / botón seleccionar
    $(document).on('click', '#provImportDropZone', function (e) {
        if ($(e.target).is('#provImportBtnSeleccionar') || $(e.target).closest('#provImportBtnSeleccionar').length) return;
        document.getElementById('provImportFileInput').click();
    });
    $(document).on('click', '#provImportBtnSeleccionar', function (e) {
        e.preventDefault();
        e.stopPropagation();
        document.getElementById('provImportFileInput').click();
    });

    // Drag & Drop
    $('#provImportDropZone').on('dragover', function (e) {
        e.preventDefault();
        $(this).css('border-color', '#1a5276').css('background', '#eff6ff');
        $('#provImportDropIcon').css('color', '#1a5276');
    }).on('dragleave', function () {
        $(this).css('border-color', '#cbd5e1').css('background', '#f8fafc');
        $('#provImportDropIcon').css('color', '#94a3b8');
    }).on('drop', function (e) {
        e.preventDefault();
        $(this).css('border-color', '#cbd5e1').css('background', '#f8fafc');
        const files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) provProcesarArchivo(files[0]);
    });

    // Selección por input
    $('#provImportFileInput').on('change', function () {
        if (this.files.length > 0) provProcesarArchivo(this.files[0]);
    });

    function provProcesarArchivo(file) {
        const ext = file.name.split('.').pop().toLowerCase();
        if (!['xlsx', 'xls', 'csv'].includes(ext)) {
            Swal.fire({ icon: 'warning', title: 'Formato no válido', text: 'Solo se aceptan .xlsx, .xls o .csv', confirmButtonColor: '#1a5276' });
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            Swal.fire({ icon: 'warning', title: 'Archivo muy grande', text: 'El archivo no debe superar 5 MB.', confirmButtonColor: '#1a5276' });
            return;
        }
        $('#provImportFileName').text(file.name);
        $('#provImportFileSize').text(provFormatBytes(file.size));
        $('#provImportFileInfo').show();
        $('#provImportDropZone').hide();
        $('#provImportBtnProcesar').show();
        $('#provImportBtnProcesar').data('file', file);
    }

    // Quitar archivo
    $(document).on('click', '#provImportBtnQuitar', function () {
        $('#provImportFileInput').val('');
        $('#provImportFileInfo').hide();
        $('#provImportDropZone').show();
        $('#provImportBtnProcesar').hide();
    });

    // Procesar importación
    $(document).on('click', '#provImportBtnProcesar', function () {
        const file = $(this).data('file');
        if (!file) return;
        const formData = new FormData();
        formData.append('archivo', file);

        $('#provImportStep1').hide();
        $('#provImportStep2').show();
        $('#provImportBtnProcesar').hide();
        $('#provImportBtnCerrar').hide();

        $.ajax({
            url: 'ajax_proveedores_import.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
        })
        .done(function (res) {
            $('#provImportStep2').hide();
            $('#provImportStep3').show();
            $('#provImportBtnCerrar').show();

            if (!res.ok) {
                $('#provImportResultado').html(`
                    <div class="alert alert-danger border-0" style="border-radius:8px;">
                        <i class="fas fa-times-circle mr-2"></i><strong>Error:</strong> ${res.error || 'Error desconocido.'}
                    </div>`);
                return;
            }

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
                </div>`;

            if (res.insertados > 0) {
                html += `<div class="alert alert-success border-0 mb-2" style="border-radius:8px;font-size:.88rem;">
                    <i class="fas fa-check-circle mr-2"></i>
                    <strong>${res.insertados}</strong> proveedor(es) importados correctamente.
                </div>`;
                $('#provImportBtnRecargar').show();
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

            $('#provImportResultado').html(html);
        })
        .fail(function () {
            $('#provImportStep2').hide();
            $('#provImportStep3').show();
            $('#provImportBtnCerrar').show();
            $('#provImportResultado').html(`
                <div class="alert alert-danger border-0" style="border-radius:8px;">
                    <i class="fas fa-times-circle mr-2"></i>Error de conexión. Intenta nuevamente.
                </div>`);
        });
    });

    // Recargar tras importación exitosa
    $('#provImportBtnRecargar').on('click', function () {
        window.location.href = 'proveedores.php';
    });

    // Reset al cerrar
    $('#modalImportarProveedores').on('hidden.bs.modal', function () {
        resetProvImport();
        $('#provImportFileInput').val('');
    });

    function provFormatBytes(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    // ══════════════════════════════════════════════════════════════════════════
    // EXPORTACIÓN — CSV / Excel / PDF
    // ══════════════════════════════════════════════════════════════════════════

    // Detecta qué tab está activo para pasar el filtro de estado
    function getEstadoActivo() {
        return $('#tab-inactivos-prov').hasClass('active') ? 'inactivo' : 'activo';
    }

    $('#btn-exportar-csv-prov').on('click', function () {
        window.location.href = 'ajax_proveedores_export.php?exportar=csv&estado=' + getEstadoActivo();
    });

    $('#btn-exportar-excel-prov').on('click', function () {
        window.location.href = 'ajax_proveedores_export.php?exportar=excel&estado=' + getEstadoActivo();
    });

    $('#btn-exportar-pdf-prov').on('click', function () {
        window.open('proveedores_pdf.php?estado=' + getEstadoActivo(), '_blank');
    });

});
