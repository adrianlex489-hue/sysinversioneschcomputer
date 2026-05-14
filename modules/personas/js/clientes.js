/**
 * clientes.js — Módulo Gestión de Clientes | Botica 2026
 * Acciones: Ver (ojito), Editar (lápiz), Eliminar (tacho)
 *   - Tacho en activos   → desactivar (lógico) o eliminar permanente
 *   - Tacho en inactivos → eliminar permanente
 */

$(function () {

    // ── DataTables ────────────────────────────────────────────────────────────
    const dtOpts = {
        language: {
            search:           '<i class="fas fa-search"></i>',
            searchPlaceholder:'Buscar...',
            lengthMenu:       'Mostrar _MENU_ registros',
            info:             'Mostrando _START_ a _END_ de _TOTAL_',
            infoEmpty:        'Sin registros',
            zeroRecords:      'No se encontraron resultados',
            paginate:         { previous: '‹', next: '›' }
        },
        responsive: true,
        autoWidth:  false,
        order:      [[1, 'asc']],
        pageLength: 10,
    };

    if ($('#tablaClientesActivos').length)   $('#tablaClientesActivos').DataTable(dtOpts);
    if ($('#tablaClientesInactivos').length) $('#tablaClientesInactivos').DataTable(dtOpts);

    // ── Helper: enviar formulario POST oculto ─────────────────────────────────
    function submitAccion(accion, id) {
        const $f = $('<form method="POST" style="display:none;"></form>');
        $f.append(`<input type="hidden" name="accion"     value="${accion}">`);
        $f.append(`<input type="hidden" name="id_cliente" value="${id}">`);
        $('body').append($f);
        $f.submit();
    }

    // ── VER cliente ───────────────────────────────────────────────────────────
    $(document).on('click', '.btn-ver-cliente', function () {
        const d = $(this).data();
        const nombreCompleto = `${d.nombres} ${d.apellidoPaterno} ${d.apellidoMaterno || ''}`.trim();

        // Cabecera
        $('#ver_nombre_completo').text(nombreCompleto);
        $('#ver_badge_tipo').text(d.tipoDocumento || 'DNI');
        $('#ver_badge_dni').text(d.dni || '—');

        // Campos
        $('#ver_nombres').text(d.nombres || '—');
        $('#ver_apellidos').text(`${d.apellidoPaterno} ${d.apellidoMaterno || ''}`.trim() || '—');
        $('#ver_telefono').text(d.telefono || '—');
        $('#ver_email').text(d.email || '—');
        $('#ver_direccion').text(d.direccion || '—');
        $('#ver_fecha').text(d.fecha || '—');
        $('#ver_estado_cliente').html(parseInt(d.estado) === 1
            ? '<span style="background:#d4edda;color:#155724;padding:3px 10px;border-radius:20px;font-size:.8rem;font-weight:600;"><i class="fas fa-check-circle mr-1"></i>Activo</span>'
            : '<span style="background:#f8d7da;color:#721c24;padding:3px 10px;border-radius:20px;font-size:.8rem;font-weight:600;"><i class="fas fa-times-circle mr-1"></i>Inactivo</span>'
        );
        $('#modalVerCliente').modal('show');
    });

    // ── EDITAR cliente ────────────────────────────────────────────────────────
    $(document).on('click', '.btn-editar-cliente', function () {
        const d = $(this).data();
        $('#editar_id_cliente').val(d.id);
        $('#editar_tipo_documento').val(d.tipoDocumento || 'DNI');
        $('#editar_dni').val(d.dni);
        $('#editar_nombres').val(d.nombres);
        $('#editar_apellido_paterno').val(d.apellidoPaterno);
        $('#editar_apellido_materno').val(d.apellidoMaterno || '');
        $('#editar_telefono').val(d.telefono || '');
        $('#editar_email').val(d.email || '');
        $('#editar_direccion').val(d.direccion || '');
        $('#editar_estado_cliente').val(String(d.estado));
        $('#reniec_result_editar').removeClass('show');
        ajustarDni('editar');
        $('#modalEditarCliente').modal('show');
    });

    // ── ELIMINAR desde activos (tacho) → pregunta lógico o permanente ─────────
    $(document).on('click', '.btn-eliminar-cliente', function () {
        const id     = $(this).data('id');
        const nombre = $(this).data('nombre');

        Swal.fire({
            icon:  'warning',
            title: `Eliminar a ${nombre}`,
            html:  `<p class="mb-2">Elige cómo deseas eliminar este cliente:</p>
                    <div class="d-flex justify-content-center gap-3 mt-3">
                        <button id="swal-logico" class="swal2-confirm swal2-styled" style="background:#e67e22;">
                            <i class="fas fa-user-slash mr-1"></i> Desactivar
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
                        title:            '¿Desactivar cliente?',
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
    $(document).on('click', '.btn-reactivar-cliente', function () {
        const id     = $(this).data('id');
        const nombre = $(this).data('nombre');
        Swal.fire({
            icon:             'question',
            title:            '¿Reactivar cliente?',
            html:             `<b>${nombre}</b> volverá a la lista de clientes activos.`,
            showCancelButton: true,
            confirmButtonColor: '#1a7a4a',
            cancelButtonColor:  '#6c757d',
            confirmButtonText:  '<i class="fas fa-user-check mr-1"></i> Sí, reactivar',
            cancelButtonText:   'Cancelar',
        }).then(r => { if (r.isConfirmed) submitAccion('reactivar', id); });
    });

    // ── ELIMINAR PERMANENTE desde inactivos ───────────────────────────────────
    $(document).on('click', '.btn-eliminar-permanente', function () {
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

    // ── Consulta RENIEC ───────────────────────────────────────────────────────
    function consultarReniec(dniInputId, resultBoxId, prefijo) {
        const dni = $(`#${dniInputId}`).val().trim();

        if (!/^\d{8}$/.test(dni)) {
            Swal.fire({ icon: 'warning', title: 'DNI inválido', text: 'Ingrese exactamente 8 dígitos numéricos.', confirmButtonColor: '#1a7a4a' });
            return;
        }

        const $btn = $(`#btn_reniec_${prefijo}`);
        const textoOriginal = $btn.html();
        $btn.html('<span class="spinner-border spinner-border-sm"></span> Consultando...').prop('disabled', true);
        $(`#${resultBoxId}`).removeClass('show');

        $.ajax({
            url:      'api_consultar_dni.php',
            method:   'POST',
            data:     { dni },
            dataType: 'json',
        })
        .done(function (res) {
            if (res.success && res.datos) {
                const d = res.datos;
                $(`#${prefijo}_nombres`).val(d.nombres);
                $(`#${prefijo}_apellido_paterno`).val(d.apellido_paterno);
                $(`#${prefijo}_apellido_materno`).val(d.apellido_materno);
                $(`#${prefijo}_direccion`).val(d.direccion);
                $(`#${resultBoxId} .reniec-nombre`).text(
                    `${d.nombres} ${d.apellido_paterno} ${d.apellido_materno}`
                );
                $(`#${resultBoxId}`).addClass('show');
            } else {
                Swal.fire({ icon: 'error', title: 'No encontrado', text: res.error || 'DNI no encontrado en RENIEC.', confirmButtonColor: '#1a7a4a' });
            }
        })
        .fail(function () {
            Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo conectar con la API RENIEC.', confirmButtonColor: '#1a7a4a' });
        })
        .always(function () {
            $btn.html(textoOriginal).prop('disabled', false);
        });
    }

    $('#btn_reniec_crear').on('click', function () {
        consultarReniec('crear_dni', 'reniec_result_crear', 'crear');
    });
    $('#btn_reniec_editar').on('click', function () {
        consultarReniec('editar_dni', 'reniec_result_editar', 'editar');
    });

    // Enter en campo DNI dispara consulta
    $('#crear_dni').on('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); $('#btn_reniec_crear').trigger('click'); }
    });
    $('#editar_dni').on('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); $('#btn_reniec_editar').trigger('click'); }
    });

    // ── Ajustar maxlength y botón RENIEC según tipo documento ─────────────────
    function ajustarDni(prefijo) {
        const tipo = $(`#${prefijo}_tipo_documento`).val();
        const $input = $(`#${prefijo}_dni`);
        const $btnReniec = $(`#btn_reniec_${prefijo}`);
        if (tipo === 'DNI') {
            $input.attr('maxlength', 8).attr('placeholder', '12345678');
            $btnReniec.show();
        } else if (tipo === 'RUC') {
            $input.attr('maxlength', 11).attr('placeholder', '20123456789');
            $btnReniec.hide();
        } else {
            $input.attr('maxlength', 20).attr('placeholder', 'Número de documento');
            $btnReniec.hide();
        }
    }

    $('#crear_tipo_documento').on('change', function () { ajustarDni('crear'); });
    $('#editar_tipo_documento').on('change', function () { ajustarDni('editar'); });

    // ── Limpiar modal Crear al abrir ──────────────────────────────────────────
    $('#modalCrearCliente').on('show.bs.modal', function () {
        $(this).find('form')[0].reset();
        $('#reniec_result_crear').removeClass('show');
        ajustarDni('crear');
    });

});
