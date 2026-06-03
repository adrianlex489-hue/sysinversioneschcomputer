// ============================================================
// usuarios.js — Módulo Gestión de Usuarios | SysInversiones 2026
// ============================================================

document.addEventListener('DOMContentLoaded', function() {
    // 1. Inicializar DataTables
    const dtOpts = {
        language: {
            search:            '<i class="fas fa-search"></i>',
            searchPlaceholder: 'Buscar usuario...',
            lengthMenu:        'Mostrar _MENU_ registros',
            info:              'Mostrando _START_ a _END_ de _TOTAL_',
            infoEmpty:         'Sin registros',
            zeroRecords:       'No se encontraron resultados',
            paginate:          { previous: '‹', next: '›' }
        },
        responsive: true,
        autoWidth:  false,
        order:      [[1, 'asc']],
        pageLength: 10,
        columnDefs: [
            { orderable: false, searchable: false, targets: [-1] }
        ]
    };

    if ($('#tablaUsuariosActivos').length)   $('#tablaUsuariosActivos').DataTable(dtOpts);
    if ($('#tablaUsuariosInactivos').length) $('#tablaUsuariosInactivos').DataTable(dtOpts);

    // 2. Ajustar columnas al cambiar pestaña
    $('a[data-toggle="pill"]').on('shown.bs.tab', function () {
        $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
    });

    // 3. Modal VER USUARIO
    $(document).on('click', '.btn-ver-usuario', function () {
        const d = $(this).data();

        $('#ver_nombre_completo').text(d.nombre || d.username || '—');
        $('#ver_username_badge').text(d.username);
        $('#ver_id_usuario').text(d.id);
        $('#ver_fecha').text(d.fecha || '—');

        // Rol con badge de color según id_rol
        const rolColors = { 1: 'admin', 2: 'asesor', 3: 'tecnico' };
        const rolClass  = rolColors[d.rol] || 'trabajador';
        $('#ver_rol').html(`<span class="badge-rol ${rolClass}">${d.nombre_rol || '—'}</span>`);

        // Estado siempre activo (botón solo aparece en tabla activos)
        $('#ver_estado_usuario').html(
            '<span style="background:#d4edda;color:#155724;padding:3px 10px;border-radius:20px;font-size:.8rem;font-weight:600;"><i class="fas fa-check-circle mr-1"></i>Activo</span>'
        );

        $('#modalVerUsuario').modal('show');
    });

    // 4. Modal EDITAR USUARIO
    $(document).on('click', '.btn-editar-usuario', function () {
        const d = $(this).data();
        $('#editar_id_usuario').val(d.id);
        $('#editar_username').val(d.username);
        $('#editar_nombre_completo').val(d.nombre || '');
        $('#editar_id_rol').val(d.rol);
        $('#editar_estado').val(d.estado);
        $('#editar_clave').val('');
        $('#modalEditarUsuario').modal('show');
    });

    // 5. Desactivar usuario
    $(document).on('click', '.btn-desactivar-usuario', function () {
        if ($(this).is(':disabled')) {
            Swal.fire({ icon: 'warning', title: 'Acción Restringida', text: 'No puedes desactivar tu propia cuenta.', confirmButtonColor: '#f39c12' });
            return;
        }
        const id       = $(this).data('id');
        const username = $(this).data('username');
        Swal.fire({
            title: '¿Desactivar usuario?',
            html:  `El usuario <b>@${username}</b> será movido a inactivos y no podrá acceder al sistema.`,
            icon:  'warning',
            showCancelButton:   true,
            confirmButtonColor: '#e74c3c',
            cancelButtonColor:  '#6c757d',
            confirmButtonText:  '<i class="fas fa-user-slash mr-1"></i>Sí, desactivar',
            cancelButtonText:   'Cancelar'
        }).then(r => { if (r.isConfirmed) enviarPost('desactivar', id); });
    });

    // 6. Reactivar usuario
    $(document).on('click', '.btn-reactivar-usuario', function () {
        const id       = $(this).data('id');
        const username = $(this).data('username');
        Swal.fire({
            title: '¿Reactivar usuario?',
            html:  `El usuario <b>@${username}</b> volverá a tener acceso al sistema.`,
            icon:  'question',
            showCancelButton:   true,
            confirmButtonColor: '#27ae60',
            cancelButtonColor:  '#6c757d',
            confirmButtonText:  '<i class="fas fa-check mr-1"></i>Sí, reactivar',
            cancelButtonText:   'Cancelar'
        }).then(r => { if (r.isConfirmed) enviarPost('reactivar', id); });
    });

    // 7. Eliminar permanente
    $(document).on('click', '.btn-eliminar-permanente', function () {
        if ($(this).is(':disabled')) {
            Swal.fire({ icon: 'warning', title: 'Acción Restringida', text: 'No puedes eliminar tu propia cuenta.', confirmButtonColor: '#f39c12' });
            return;
        }
        const id       = $(this).data('id');
        const username = $(this).data('username');
        Swal.fire({
            title: '¿ELIMINAR DEFINITIVAMENTE?',
            html:  `El usuario <b>@${username}</b> será eliminado de forma <b>permanente e irreversible</b>.`,
            icon:  'error',
            showCancelButton:   true,
            confirmButtonColor: '#c0392b',
            cancelButtonColor:  '#6c757d',
            confirmButtonText:  '<i class="fas fa-trash-alt mr-1"></i>Eliminar permanentemente',
            cancelButtonText:   'Cancelar'
        }).then(r => { if (r.isConfirmed) enviarPost('eliminar_permanente', id); });
    });

    // Helper: enviar formulario POST dinámico
    function enviarPost(accion, id) {
        const $f = $('<form method="POST" style="display:none;"></form>');
        $f.append(`<input type="hidden" name="accion"     value="${accion}">`);
        $f.append(`<input type="hidden" name="id_usuario" value="${id}">`);
        $('body').append($f);
        $f.submit();
    }

    // 8. Limpiar modal crear al abrir
    $('#modalCrearUsuario').on('show.bs.modal', function () {
        $(this).find('form')[0].reset();
    });
});
