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

});
