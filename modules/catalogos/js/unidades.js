/**
 * unidades.js — Módulo Gestión de Unidades | Botica 2026
 */

$(function () {

    // ── DataTable ─────────────────────────────────────────
    if ($('#tablaUnidades').length) {
        $('#tablaUnidades').DataTable({
            language: {
                search:            '<i class="fas fa-search"></i>',
                searchPlaceholder: 'Buscar unidad...',
                lengthMenu:        'Mostrar _MENU_ registros',
                info:              'Mostrando _START_ a _END_ de _TOTAL_',
                infoEmpty:         'Sin registros',
                zeroRecords:       'No se encontraron resultados',
                paginate:          { previous: '‹', next: '›' }
            },
            responsive: true,
            autoWidth: false,
            order: [[1, 'asc']],
            pageLength: 15,
            columnDefs: [
                { orderable: false, targets: [0, -1] }
            ]
        });
    }

    // ── Helper: POST oculto ───────────────────────────────
    function submitAccion(accion, id) {
        var $f = $('<form method="POST" style="display:none;"></form>');
        $f.append('<input type="hidden" name="accion" value="' + accion + '">');
        $f.append('<input type="hidden" name="id_unidad" value="' + id + '">');
        $('body').append($f);
        $f.submit();
    }

    // ══════════════════════════════════════════════════════
    // VER UNIDAD
    // ══════════════════════════════════════════════════════
    $(document).on('click', '.btn-ver-unidad', function () {
        var d = $(this).data();

        $('#ver_uni_nombre').text(d.nombre || '—');
        $('#ver_uni_abrev').text(d.abrev || '—');
        $('#ver_uni_abrev_badge').text(d.abrev || '—');
        $('#ver_uni_productos').text(d.productos + ' productos');

        var esActiva = parseInt(d.estado) === 1;
        $('#ver_uni_badge_estado').html(
            esActiva
                ? '<i class="fas fa-check-circle mr-1"></i>Activa'
                : '<i class="fas fa-times-circle mr-1"></i>Inactiva'
        ).css({
            background: esActiva ? 'rgba(255,255,255,.2)' : 'rgba(231,76,60,.3)',
            color: '#fff'
        });

        $('#modalVerUnidad').modal('show');
    });

    // ══════════════════════════════════════════════════════
    // EDITAR UNIDAD
    // ══════════════════════════════════════════════════════
    $(document).on('click', '.btn-editar-unidad', function () {
        var d = $(this).data();
        $('#editar_id_unidad').val(d.id);
        $('#editar_nombre_unidad').val(d.nombre || '');
        $('#editar_abreviatura').val(d.abrev || '');
        $('#editar_estado').val(String(d.estado));
        $('#modalEditarUnidad').modal('show');
    });

    // ══════════════════════════════════════════════════════
    // DESACTIVAR
    // ══════════════════════════════════════════════════════
    $(document).on('click', '.btn-desactivar-unidad', function () {
        var id     = $(this).data('id');
        var nombre = $(this).data('nombre');
        Swal.fire({
            icon: 'warning',
            title: '¿Desactivar unidad?',
            html: '<b>' + nombre + '</b> pasará a la lista de inactivas.<br>Podrás reactivarla cuando quieras.',
            showCancelButton:   true,
            confirmButtonColor: '#e67e22',
            cancelButtonColor:  '#6c757d',
            confirmButtonText:  '<i class="fas fa-trash-alt mr-1"></i> Sí, desactivar',
            cancelButtonText:   'Cancelar'
        }).then(function (r) {
            if (r.isConfirmed) submitAccion('desactivar', id);
        });
    });

    // ══════════════════════════════════════════════════════
    // REACTIVAR
    // ══════════════════════════════════════════════════════
    $(document).on('click', '.btn-reactivar-unidad', function () {
        var id     = $(this).data('id');
        var nombre = $(this).data('nombre');
        Swal.fire({
            icon: 'question',
            title: '¿Reactivar unidad?',
            html: '<b>' + nombre + '</b> volverá a la lista de unidades activas.',
            showCancelButton:   true,
            confirmButtonColor: '#1a7a4a',
            cancelButtonColor:  '#6c757d',
            confirmButtonText:  '<i class="fas fa-check mr-1"></i> Sí, reactivar',
            cancelButtonText:   'Cancelar'
        }).then(function (r) {
            if (r.isConfirmed) submitAccion('reactivar', id);
        });
    });

    // ══════════════════════════════════════════════════════
    // ELIMINAR PERMANENTE
    // ══════════════════════════════════════════════════════
    $(document).on('click', '.btn-eliminar-unidad', function () {
        var id     = $(this).data('id');
        var nombre = $(this).data('nombre');
        Swal.fire({
            icon: 'error',
            title: '¿Eliminar permanentemente?',
            html: '<b>' + nombre + '</b> será eliminada de la base de datos.<br>' +
                  '<strong class="text-danger">Esta acción NO se puede deshacer.</strong>',
            showCancelButton:   true,
            confirmButtonColor: '#e74c3c',
            cancelButtonColor:  '#6c757d',
            confirmButtonText:  '<i class="fas fa-trash-alt mr-1"></i> Sí, eliminar',
            cancelButtonText:   'Cancelar'
        }).then(function (r) {
            if (r.isConfirmed) submitAccion('eliminar', id);
        });
    });

    // ── Limpiar modal Crear al abrir ──────────────────────
    $('#modalCrearUnidad').on('show.bs.modal', function () {
        $(this).find('form')[0].reset();
    });

});
