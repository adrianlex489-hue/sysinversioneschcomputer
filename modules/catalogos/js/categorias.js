/**
 * categorias.js — Módulo Gestión de Categorías | SysInversiones CH Computer
 */

$(function () {

    // ── DataTable ─────────────────────────────────────────
    if ($('#tablaCategorias').length) {
        $('#tablaCategorias').DataTable({
            language: {
                search:       '<i class="fas fa-search"></i>',
                searchPlaceholder: 'Buscar categoría...',
                lengthMenu:   'Mostrar _MENU_ registros',
                info:         'Mostrando _START_ a _END_ de _TOTAL_',
                infoEmpty:    'Sin registros',
                zeroRecords:  'No se encontraron resultados',
                paginate:     { previous: '‹', next: '›' }
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
        $f.append('<input type="hidden" name="id_categoria" value="' + id + '">');
        $('body').append($f);
        $f.submit();
    }

    // ══════════════════════════════════════════════════════
    // ABRIR MODAL VER
    // ══════════════════════════════════════════════════════
    $(document).on('click', '.btn-ver-categoria', function () {
        var d = $(this).data();

        $('#ver_cat_nombre').text(d.nombre || '—');
        $('#ver_cat_descripcion').text(d.descripcion || 'Sin descripción');
        $('#ver_cat_productos').text(d.productos + ' productos');
        $('#ver_cat_fecha').text(d.fecha || '—');

        var esActiva = parseInt(d.estado) === 1;
        $('#ver_cat_badge_estado')
            .html(esActiva
                ? '<i class="fas fa-check-circle mr-1"></i>Activa'
                : '<i class="fas fa-times-circle mr-1"></i>Inactiva')
            .css(esActiva
                ? { background:'rgba(39,174,96,.25)', color:'#a9dfbf', border:'1px solid rgba(39,174,96,.4)' }
                : { background:'rgba(231,76,60,.25)',  color:'#f1948a', border:'1px solid rgba(231,76,60,.4)' });

        $('#modalVerCategoria').modal('show');
    });

    // ══════════════════════════════════════════════════════
    // ABRIR MODAL EDITAR
    // ══════════════════════════════════════════════════════
    $(document).on('click', '.btn-editar-categoria', function () {
        var d = $(this).data();
        $('#editar_id_categoria').val(d.id);
        $('#editar_nombre_categoria').val(d.nombre || '');
        $('#editar_descripcion').val(d.descripcion || '');
        $('#editar_estado').val(String(d.estado));
        $('#modalEditarCategoria').modal('show');
    });

    // ══════════════════════════════════════════════════════
    // DESACTIVAR / REACTIVAR
    // ══════════════════════════════════════════════════════
    $(document).on('click', '.btn-desactivar-categoria', function () {
        var id     = $(this).data('id');
        var nombre = $(this).data('nombre');
        Swal.fire({
            icon: 'warning',
            title: '¿Desactivar categoría?',
            html: '<b>' + nombre + '</b> pasará a la lista de inactivas.<br>Podrás reactivarla cuando quieras.',
            showCancelButton:    true,
            confirmButtonColor:  '#e67e22',
            cancelButtonColor:   '#6c757d',
            confirmButtonText:   '<i class="fas fa-trash-alt mr-1"></i> Sí, desactivar',
            cancelButtonText:    'Cancelar'
        }).then(function (r) {
            if (r.isConfirmed) submitAccion('desactivar', id);
        });
    });

    $(document).on('click', '.btn-reactivar-categoria', function () {
        var id     = $(this).data('id');
        var nombre = $(this).data('nombre');
        Swal.fire({
            icon: 'question',
            title: '¿Reactivar categoría?',
            html: '<b>' + nombre + '</b> volverá a la lista de categorías activas.',
            showCancelButton:    true,
            confirmButtonColor:  '#1a7a4a',
            cancelButtonColor:   '#6c757d',
            confirmButtonText:   '<i class="fas fa-check mr-1"></i> Sí, reactivar',
            cancelButtonText:    'Cancelar'
        }).then(function (r) {
            if (r.isConfirmed) submitAccion('reactivar', id);
        });
    });

    // ══════════════════════════════════════════════════════
    // ELIMINAR PERMANENTE
    // ══════════════════════════════════════════════════════
    $(document).on('click', '.btn-eliminar-categoria', function () {
        var id     = $(this).data('id');
        var nombre = $(this).data('nombre');
        Swal.fire({
            icon: 'error',
            title: '¿Eliminar permanentemente?',
            html: '<b>' + nombre + '</b> será eliminada de la base de datos.<br>' +
                  '<strong class="text-danger">Esta acción NO se puede deshacer.</strong>',
            showCancelButton:    true,
            confirmButtonColor:  '#e74c3c',
            cancelButtonColor:   '#6c757d',
            confirmButtonText:   '<i class="fas fa-trash-alt mr-1"></i> Sí, eliminar',
            cancelButtonText:    'Cancelar'
        }).then(function (r) {
            if (r.isConfirmed) submitAccion('eliminar', id);
        });
    });

    // ── Limpiar modal Crear al abrir ──────────────────────
    $('#modalCrearCategoria').on('show.bs.modal', function () {
        $(this).find('form')[0].reset();
    });

    // ══════════════════════════════════════════════════════
    // EXPORTACIÓN — CSV / Excel / PDF
    // ══════════════════════════════════════════════════════

    function getEstadoCat() {
        return $('#tab-inactivas-cat').hasClass('active') ? 'inactivo' : 'activo';
    }

    $('#btn-exportar-csv-cat').on('click', function () {
        window.location.href = 'ajax_categorias_export.php?exportar=csv&estado=' + getEstadoCat();
    });

    $('#btn-exportar-excel-cat').on('click', function () {
        window.location.href = 'ajax_categorias_export.php?exportar=excel&estado=' + getEstadoCat();
    });

    $('#btn-exportar-pdf-cat').on('click', function () {
        window.open('categorias_pdf.php?estado=' + getEstadoCat(), '_blank');
    });

});
