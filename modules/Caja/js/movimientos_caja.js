/**
 * movimientos_caja.js — Módulo Movimientos de Caja | SysInversiones 2026
 * Se carga DESPUÉS de jQuery y Bootstrap (vía footer.php $extra_js)
 */
$(function () {

    // ── DataTable ─────────────────────────────────────────────────────────────
    if ($('#tblMovsCaja').length) {
        $('#tblMovsCaja').DataTable({
            language: { url: 'https://cdn.datatables.net/plug-ins/1.10.25/i18n/Spanish.json' },
            order: [[0, 'desc']],
            pageLength: 25,
            responsive: true,
            dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rtip',
            columnDefs: [{ orderable: false, targets: [7] }]
        });
    }

    // ── Modal movimiento manual ───────────────────────────────────────────────
    $(document).on('click', '.cx-btn-tipo', function () {
        $('.cx-btn-tipo').removeClass('activo');
        $(this).addClass('activo');
        $('#hiddenTipoMov').val($(this).data('tipo'));
    });
    $(document).on('click', '.cx-btn-metodo', function () {
        $('.cx-btn-metodo').removeClass('activo');
        $(this).addClass('activo');
        $('#hiddenMetodoMov').val($(this).data('metodo'));
    });
    $('#modalMovManual').on('hidden.bs.modal', function () {
        $(this).find('form')[0].reset();
        $('.cx-btn-tipo, .cx-btn-metodo').removeClass('activo');
        $('.cx-btn-metodo[data-metodo="efectivo"]').addClass('activo');
        $('#hiddenTipoMov').val('');
        $('#hiddenMetodoMov').val('efectivo');
    });

    // ── Botón ojo: ver detalle del movimiento ─────────────────────────────────
    $(document).on('click', '.btn-detalle-mov', function (e) {
        e.preventDefault();
        e.stopPropagation();

        // closest() sube desde el <i> hijo hasta el <button> que tiene los data-*
        var $btn  = $(e.target).closest('.btn-detalle-mov');
        var id    = $btn.data('id');
        var ref   = $btn.data('ref');
        var refid = $btn.data('refid');
        var tipo  = $btn.data('tipo');

        if (!id) {
            console.warn('[movimientos_caja] btn-detalle-mov: id_movimiento no encontrado', $btn);
            return;
        }

        // Mostrar spinner en el body del modal
        $('#movDetalle-body').html(
            '<div class="text-center py-5">' +
            '<i class="fas fa-spinner fa-spin fa-2x" style="color:#0ea5e9;opacity:.6;"></i>' +
            '<p class="mt-3 text-muted" style="font-size:.88rem;">Cargando información...</p>' +
            '</div>'
        );

        // Abrir modal
        $('#modalDetalleMov').modal('show');

        // Petición AJAX
        $.ajax({
            url: '/sysinversioneschcomputer/modules/Caja/ajax_detalle_movimiento.php',
            method: 'GET',
            data: {
                id_movimiento:  id,
                tipo_referencia: ref,
                id_referencia:  refid,
                tipo:           tipo
            },
            success: function (html) {
                $('#movDetalle-body').html(html);
            },
            error: function (xhr) {
                $('#movDetalle-body').html(
                    '<div class="alert alert-danger m-3">' +
                    '<i class="fas fa-exclamation-circle mr-2"></i>' +
                    'Error HTTP ' + xhr.status + ' al cargar el detalle.' +
                    '</div>'
                );
                console.error('[movimientos_caja] AJAX error:', xhr.status, xhr.responseText);
            }
        });
    });

    // Limpiar modal al cerrar
    $('#modalDetalleMov').on('hidden.bs.modal', function () {
        $('#movDetalle-body').html(
            '<div class="text-center py-5">' +
            '<i class="fas fa-spinner fa-spin fa-2x" style="color:#0ea5e9;opacity:.6;"></i>' +
            '</div>'
        );
    });

});
