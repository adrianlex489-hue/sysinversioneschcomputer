/**
 * reporte_cajas.js — Reporte Consolidado de Cajas
 * SysInversiones CH Computer 2026
 */
$(function () {

    // ── Modal selector de cajero ──────────────────────────────────────────────
    // Buscador dentro del modal
    $('#cajeroModalBuscar').on('input', function () {
        var q = $(this).val().toLowerCase().trim();
        $('#cajeroModalLista .rc-modal-cajero-item').each(function () {
            var nombre = ($(this).data('nombre') || '').toLowerCase();
            $(this).toggle(!q || nombre.indexOf(q) > -1);
        });
    });

    // Limpiar buscador al abrir el modal
    $('#modalSelCajero').on('shown.bs.modal', function () {
        $('#cajeroModalBuscar').val('').trigger('input').focus();
    });

    // Seleccionar cajero desde el modal
    $(document).on('click', '.rc-modal-cajero-item', function () {
        var id     = $(this).data('id');
        var nombre = $(this).data('nombre');
        var ini    = $(this).data('ini');
        var color  = $(this).data('color') || '';

        // Actualizar input oculto
        $('#cajeroValor').val(id);

        // Actualizar display del trigger
        $('#cajeroNombreDisplay').text(nombre);
        var $av = $('.rc-cajero-trigger .rc-cajero-avatar');
        if (ini) {
            $av.removeClass('rc-cajero-avatar-all').html(ini);
            $av.css('background', color || 'linear-gradient(135deg,#0c1a3a,#0ea5e9)');
        } else {
            $av.addClass('rc-cajero-avatar-all').html('<i class="fas fa-users"></i>').css('background', '');
        }

        // Marcar activo visualmente en el modal
        $('.rc-modal-cajero-item').removeClass('rc-modal-cajero-activo').find('.rc-modal-cajero-check').remove();
        $(this).addClass('rc-modal-cajero-activo')
               .append('<div class="rc-modal-cajero-check"><i class="fas fa-check-circle"></i></div>');

        // Cerrar modal y enviar form
        $('#modalSelCajero').modal('hide');
        $('#formFiltros').submit();
    });

    // ── Buscador en tabla ─────────────────────────────────────────────────────
    $('#rcBuscar').on('input', function () {
        var q = $(this).val().toLowerCase().trim();
        $('.rc-fila').each(function () {
            var txt = ($(this).data('search') || '').toLowerCase();
            $(this).toggle(!q || txt.indexOf(q) > -1);
        });
    });

    // ── Modal exportar ────────────────────────────────────────────────────────
    $('#btnExportarReporte').on('click', function () {
        $('#modalExportarRC').modal('show');
    });

    function getParams() {
        var form = document.getElementById('formFiltros');
        var data = new FormData(form);
        return {
            desde:      data.get('desde')      || '',
            hasta:      data.get('hasta')      || '',
            estado:     data.get('estado')     || 'all',
            id_usuario: data.get('id_usuario') || '0'
        };
    }

    function rcExportar(formato) {
        var p = getParams();
        var url = 'ajax_reporte_cajas_export.php?exportar=' + formato
            + '&desde='      + encodeURIComponent(p.desde)
            + '&hasta='      + encodeURIComponent(p.hasta)
            + '&estado='     + encodeURIComponent(p.estado)
            + '&id_usuario=' + encodeURIComponent(p.id_usuario);
        window.location.href = url;
        $('#modalExportarRC').modal('hide');
    }

    function rcExportarPDF() {
        var p = getParams();
        var url = 'reporte_cajas_pdf.php?desde=' + encodeURIComponent(p.desde)
            + '&hasta='      + encodeURIComponent(p.hasta)
            + '&estado='     + encodeURIComponent(p.estado)
            + '&id_usuario=' + encodeURIComponent(p.id_usuario);
        window.open(url, '_blank');
        $('#modalExportarRC').modal('hide');
    }

    $('#rc_btn_csv').on('click',   function () { rcExportar('csv'); });
    $('#rc_btn_excel').on('click', function () { rcExportar('excel'); });
    $('#rc_btn_pdf').on('click',   function () { rcExportarPDF(); });

    // ── Fix modales anidados ──────────────────────────────────────────────────
    $(document).on('hidden.bs.modal', '.modal', function () {
        if ($('.modal.show').length) $('body').addClass('modal-open');
    });

});
