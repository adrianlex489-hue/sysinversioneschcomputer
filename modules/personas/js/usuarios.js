// ============================================================
// usuarios.js — Módulo Gestión de Usuarios | Botica 2026
// ============================================================

document.addEventListener('DOMContentLoaded', function() {
    // 1. Inicializar DataTables
    const langES = { url: "//cdn.datatables.net/plug-ins/1.10.25/i18n/Spanish.json" };
    
    $('#tablaUsuariosActivos').DataTable({
        language: langES,
        order: [[1, "asc"]], // Ordenar por nombre por defecto
        responsive: true,
        autoWidth: false,
        columnDefs: [
            { orderable: false, targets: [6] },
            { searchable: false, targets: [6] }
        ]
    });

    $('#tablaUsuariosInactivos').DataTable({
        language: langES,
        order: [[1, "asc"]],
        responsive: true,
        autoWidth: false,
        columnDefs: [
            { orderable: false, targets: [6] },
            { searchable: false, targets: [6] }
        ]
    });

    // 2. Manejo de Pestañas (Evitar desalineación de columnas)
    $('a[data-toggle="pill"]').on('shown.bs.tab', function (e) {
        $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
    });

    // 3. Modal VER USUARIO
    $('.btn-ver-usuario').on('click', function() {
        const d = $(this).data();

        // Cabecera
        $('#ver_nombre_completo').text(d.nombre || d.username);
        $('#ver_username_badge').text(d.username);

        // Campos
        $('#ver_id_usuario').text(d.id);
        $('#ver_email').text(d.email || '—');
        $('#ver_fecha').text(d.fecha);

        // Rol con badge de color
        const rolClass = d.rol == 1 ? 'admin' : 'trabajador';
        $('#ver_rol').html(`<span class="badge-rol ${rolClass}">${d.nombre_rol}</span>`);

        // Estado — siempre activo en tabla de activos
        $('#ver_estado_usuario').html(
            '<span style="background:#d4edda;color:#155724;padding:3px 10px;border-radius:20px;font-size:.8rem;font-weight:600;"><i class="fas fa-check-circle mr-1"></i>Activo</span>'
        );

        $('#modalVerUsuario').modal('show');
    });

    // 4. Modal EDITAR USUARIO
    $('.btn-editar-usuario').on('click', function() {
        const d = $(this).data();
        $('#editar_id_usuario').val(d.id);
        $('#editar_username').val(d.username);
        $('#editar_nombre_completo').val(d.nombre);
        $('#editar_email').val(d.email);
        $('#editar_id_rol').val(d.rol);
        $('#editar_estado').val(d.estado);
        $('#editar_clave').val(''); // Limpiar contraseña por seguridad
        
        $('#modalEditarUsuario').modal('show');
    });

    // 5. Eliminar Lógico (Desactivar)
    $('.btn-desactivar-usuario').on('click', function() {
        const id = $(this).data('id');
        const username = $(this).data('username');
        
        if ($(this).is(':disabled')) {
            Swal.fire({
                icon: 'warning',
                title: 'Acción Restringida',
                text: 'No puedes desactivar tu propia cuenta activa.',
                confirmButtonColor: '#f39c12'
            });
            return;
        }

        Swal.fire({
            title: '¿Desactivar Usuario?',
            html: `El usuario <b>${username}</b> será movido a la lista de inactivos y no podrá acceder al sistema.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e74c3c',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-user-slash"></i> Sí, desactivar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                enviarFormularioTemporal('desactivar', id);
            }
        });
    });

    // 6. Reactivar
    $('.btn-reactivar-usuario').on('click', function() {
        const id = $(this).data('id');
        const username = $(this).data('username');

        Swal.fire({
            title: '¿Reactivar Usuario?',
            html: `El usuario <b>${username}</b> volverá a tener acceso al sistema.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#27ae60',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-check"></i> Sí, reactivar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                enviarFormularioTemporal('reactivar', id);
            }
        });
    });

    // 7. Eliminar Permanente
    $('.btn-eliminar-permanente').on('click', function() {
        const id = $(this).data('id');
        const username = $(this).data('username');
        
        if ($(this).is(':disabled')) {
            Swal.fire({
                icon: 'warning',
                title: 'Acción Restringida',
                text: 'No puedes eliminar tu propia cuenta.',
                confirmButtonColor: '#f39c12'
            });
            return;
        }

        Swal.fire({
            title: '¿ELIMINAR DEFINITIVAMENTE?',
            html: `Esta acción borrará al usuario <b>${username}</b> de la base de datos de forma <b>permanente e irreversible</b>.`,
            icon: 'error',
            showCancelButton: true,
            confirmButtonColor: '#c0392b',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-trash-alt"></i> ELIMINAR PERMANENTEMENTE',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                enviarFormularioTemporal('eliminar_permanente', id);
            }
        });
    });

    // Helper: Enviar formulario dinámico POST
    function enviarFormularioTemporal(accion, id) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'usuarios.php';
        form.style.display = 'none';

        const inputAccion = document.createElement('input');
        inputAccion.type = 'hidden';
        inputAccion.name = 'accion';
        inputAccion.value = accion;
        form.appendChild(inputAccion);

        const inputId = document.createElement('input');
        inputId.type = 'hidden';
        inputId.name = 'id_usuario';
        inputId.value = id;
        form.appendChild(inputId);

        document.body.appendChild(form);
        form.submit();
    }
    
    // Marcar menú activo
    const activateMenu = () => {
        $('.nav-sidebar .nav-link').removeClass('active');
        $('.nav-treeview .nav-link').removeClass('active');
        $('.nav-item.menu-open').removeClass('menu-open');
        const $link = $(`.nav-sidebar .nav-link[href*="usuarios.php"]`);
        if ($link.length) { 
            $link.addClass('active'); 
            $link.closest('.has-treeview').addClass('menu-open').find('.nav-link:first').addClass('active');
        }
    };
    activateMenu();
});
