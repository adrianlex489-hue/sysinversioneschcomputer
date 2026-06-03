/**
 * catalogo_servicios.js — Catálogo de Servicios Técnicos
 * SysInversiones CH Computer 2026
 */

$(function () {

    // ── DataTable ─────────────────────────────────────────────────────────────
    if ($.fn.DataTable) {
        try {
            $('#tablaServicios').DataTable({
                language: { url: 'https://cdn.datatables.net/plug-ins/1.10.25/i18n/Spanish.json' },
                order: [[1, 'asc']],
                pageLength: 15,
                columnDefs: [{ orderable: false, targets: [3, 5, 6] }]
            });
        } catch (e) {
            console.warn('DataTable init error:', e);
        }
    }

    // ── VER DETALLE ───────────────────────────────────────────────────────────
    $(document).on('click', '.btn-ver-servicio', function () {
        var d = $(this).data();

        $('#ver_nombre').text(d.nombre || '—');

        var tipoLabel = d.tipo === 'catalogo'
            ? '<span class="srv-badge-tipo srv-tipo-catalogo"><i class="fas fa-book mr-1"></i>Catálogo</span>'
            : '<span class="srv-badge-tipo srv-tipo-personalizado"><i class="fas fa-wrench mr-1"></i>Personalizado</span>';
        $('#ver_badge_tipo').html(tipoLabel);

        var estadoHtml = parseInt(d.estado) === 1
            ? '<span class="badge badge-success">Activo</span>'
            : '<span class="badge badge-secondary">Inactivo</span>';
        $('#ver_badge_estado').html(estadoHtml);

        $('#ver_precio').text('S/. ' + parseFloat(d.precio || 0).toFixed(2));

        var tipos = (d.tiposEquipo || '').trim();
        if (tipos) {
            var chips = tipos.split(', ').map(function (t) {
                return '<span class="srv-badge-equipo">' + t + '</span>';
            }).join(' ');
            $('#ver_tipos').html(chips);
        } else {
            $('#ver_tipos').html('<span class="text-muted" style="font-size:.82rem;">Aplica a todos los equipos</span>');
        }

        var usado = parseInt(d.usado || 0);
        $('#ver_usado').text(usado + (usado === 1 ? ' vez' : ' veces'));
        $('#ver_tipo_texto').text(d.tipo === 'catalogo' ? 'Catálogo' : 'Personalizado');

        var desc = (d.descripcion || '').trim();
        if (desc) {
            $('#ver_descripcion').text(desc);
            $('#ver_desc_wrap').show();
        } else {
            $('#ver_desc_wrap').hide();
        }

        $('#modalVerServicio').modal('show');
    });

    // ── EDITAR ────────────────────────────────────────────────────────────────
    $(document).on('click', '.btn-editar-servicio', function () {
        var d = $(this).data();

        $('#edit_id_servicio').val(d.id);
        $('#edit_nombre').val(d.nombre || '');
        $('#edit_tipo').val(d.tipo || 'catalogo');
        $('#edit_precio').val(d.precio || '');
        $('#edit_descripcion').val(d.descripcion || '');
        $('#edit_estado').val(d.estado !== undefined ? d.estado : 1);

        var tiposActivos = (d.tiposEquipo || '').split(', ').map(function (t) { return t.trim(); });
        $('#tiposEditar .edit-tipo-check').each(function () {
            $(this).prop('checked', tiposActivos.indexOf($(this).val()) > -1);
        });

        $('#modalEditarServicio').modal('show');
    });

    // ── DESACTIVAR ────────────────────────────────────────────────────────────
    $(document).on('click', '.btn-desactivar-servicio', function () {
        var id     = $(this).data('id');
        var nombre = $(this).data('nombre');
        Swal.fire({
            icon: 'warning',
            title: '¿Desactivar servicio?',
            html: 'El servicio <strong>' + nombre + '</strong> no aparecerá en nuevas órdenes.',
            showCancelButton: true,
            confirmButtonColor: '#6b7280',
            cancelButtonColor: '#2563eb',
            confirmButtonText: 'Sí, desactivar',
            cancelButtonText: 'Cancelar'
        }).then(function (result) {
            if (result.isConfirmed) {
                var form = $('<form method="POST">');
                form.append('<input type="hidden" name="accion" value="desactivar">');
                form.append('<input type="hidden" name="id_servicio" value="' + id + '">');
                $('body').append(form);
                form.submit();
            }
        });
    });

    // ── REACTIVAR ─────────────────────────────────────────────────────────────
    $(document).on('click', '.btn-reactivar-servicio', function () {
        var id     = $(this).data('id');
        var nombre = $(this).data('nombre');
        Swal.fire({
            icon: 'question',
            title: '¿Reactivar servicio?',
            html: '<strong>' + nombre + '</strong> volverá a estar disponible en las órdenes.',
            showCancelButton: true,
            confirmButtonColor: '#16a34a',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Sí, reactivar',
            cancelButtonText: 'Cancelar'
        }).then(function (result) {
            if (result.isConfirmed) {
                var form = $('<form method="POST">');
                form.append('<input type="hidden" name="accion" value="reactivar">');
                form.append('<input type="hidden" name="id_servicio" value="' + id + '">');
                $('body').append(form);
                form.submit();
            }
        });
    });

    // ── ELIMINAR ──────────────────────────────────────────────────────────────
    $(document).on('click', '.btn-eliminar-servicio', function () {
        var id     = $(this).data('id');
        var nombre = $(this).data('nombre');
        var usado  = parseInt($(this).data('usado') || 0);

        if (usado > 0) {
            Swal.fire({
                icon: 'warning',
                title: 'No se puede eliminar',
                html: '<strong>' + nombre + '</strong> ya fue usado en <strong>' + usado + '</strong> orden(es). Solo puedes desactivarlo.',
                confirmButtonColor: '#2563eb'
            });
            return;
        }

        Swal.fire({
            icon: 'error',
            title: '¿Eliminar permanentemente?',
            html: 'El servicio <strong>' + nombre + '</strong> será eliminado y no se podrá recuperar.',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then(function (result) {
            if (result.isConfirmed) {
                var form = $('<form method="POST">');
                form.append('<input type="hidden" name="accion" value="eliminar">');
                form.append('<input type="hidden" name="id_servicio" value="' + id + '">');
                $('body').append(form);
                form.submit();
            }
        });
    });

    // ── LIMPIAR MODAL CREAR AL CERRAR ─────────────────────────────────────────
    $('#modalCrearServicio').on('hidden.bs.modal', function () {
        $('#formCrearServicio')[0].reset();
        $('#tiposCrear input[type="checkbox"]').prop('checked', false);
    });

    // ── VALIDACIÓN FORMULARIO CREAR ───────────────────────────────────────────
    $('#formCrearServicio').on('submit', function (e) {
        var nombre = $('input[name="nombre"]', this).val().trim();
        var precio = parseFloat($('input[name="precio_base"]', this).val());
        var tipo   = $('input[name="tipo"]:checked', this).val();
        if (!nombre) {
            e.preventDefault();
            Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'El nombre del servicio es obligatorio.', confirmButtonColor: '#2563eb' });
            return;
        }
        if (tipo !== 'personalizado' && (isNaN(precio) || precio <= 0)) {
            e.preventDefault();
            Swal.fire({ icon: 'warning', title: 'Precio inválido', text: 'Ingresa un precio base mayor a 0.', confirmButtonColor: '#2563eb' });
        }
    });

    // ── VALIDACIÓN FORMULARIO EDITAR ──────────────────────────────────────────
    $('#formEditarServicio').on('submit', function (e) {
        var nombre = $('#edit_nombre').val().trim();
        if (!nombre) {
            e.preventDefault();
            Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'El nombre del servicio es obligatorio.', confirmButtonColor: '#2563eb' });
        }
    });

    // ── FIX MODALES ANIDADOS BOOTSTRAP 4 ─────────────────────────────────────
    $(document).on('hidden.bs.modal', '.modal', function () {
        if ($('.modal.show').length) $('body').addClass('modal-open');
    });

    // ── EXPORTAR ─────────────────────────────────────────────────────────────
    $('#btnExportarSrv').on('click', function () {
        var tabActivo = $('#srvTab .nav-link.active').attr('href');
        if (tabActivo === '#tab-activos-srv')        $('#srv_exp_estado').val('activo');
        else if (tabActivo === '#tab-inactivos-srv') $('#srv_exp_estado').val('inactivo');
        else                                         $('#srv_exp_estado').val('all');
        $('#modalExportarSrv').modal('show');
    });

    function srvGetParams() {
        return {
            estado:      $('#srv_exp_estado').val() || 'all',
            tipo:        $('#srv_exp_tipo').val()   || 'all',
            tipo_equipo: $('#srv_exp_equipo').val() || 'all'
        };
    }

    function srvExportar(formato) {
        var p = srvGetParams();
        window.location.href = 'ajax_catalogo_servicios_export.php?exportar=' + formato
            + '&estado='      + encodeURIComponent(p.estado)
            + '&tipo='        + encodeURIComponent(p.tipo)
            + '&tipo_equipo=' + encodeURIComponent(p.tipo_equipo);
        $('#modalExportarSrv').modal('hide');
    }

    function srvExportarPDF() {
        var p = srvGetParams();
        window.open('catalogo_servicios_pdf.php?estado=' + encodeURIComponent(p.estado)
            + '&tipo='        + encodeURIComponent(p.tipo)
            + '&tipo_equipo=' + encodeURIComponent(p.tipo_equipo), '_blank');
        $('#modalExportarSrv').modal('hide');
    }

    $('#srv_btn_csv').on('click',   function () { srvExportar('csv'); });
    $('#srv_btn_excel').on('click', function () { srvExportar('excel'); });
    $('#srv_btn_pdf').on('click',   function () { srvExportarPDF(); });

});

// ── MODAL FILTRO POR TIPO DE EQUIPO ──────────────────────────────────────────
var iconosTipo = {
    'Laptop':     'fas fa-laptop',
    'PC':         'fas fa-desktop',
    'All-in-One': 'fas fa-tv',
    'Tablet':     'fas fa-tablet-alt',
    'Impresora':  'fas fa-print',
    'Monitor':    'fas fa-desktop',
    'Otro':       'fas fa-tools'
};

$(document).on('click', '.filtro-equipo-chip', function () {
    var tipo = $(this).data('tipo');
    $('.filtro-equipo-chip').removeClass('activo');
    $(this).addClass('activo');
    $('.filtro-tipo-panel').hide();
    $('.filtro-tipo-panel[data-panel="' + tipo + '"]').show();
    $('#filtroTipoActivo').text(tipo);
    $('#filtroIconoActivo').attr('class', (iconosTipo[tipo] || 'fas fa-tools') + ' mr-2');
    var total = $('.filtro-tipo-panel[data-panel="' + tipo + '"] .filtro-srv-card').length;
    $('#filtroTotalActivo').text(total + (total === 1 ? ' servicio' : ' servicios'));
});

$('#modalFiltroEquipo').on('shown.bs.modal', function () {
    var primerChip = $('.filtro-equipo-chip.activo').first();
    if (primerChip.length) {
        var tipo  = primerChip.data('tipo');
        var total = $('.filtro-tipo-panel[data-panel="' + tipo + '"] .filtro-srv-card').length;
        $('#filtroTotalActivo').text(total + (total === 1 ? ' servicio' : ' servicios'));
        $('#filtroIconoActivo').attr('class', (iconosTipo[tipo] || 'fas fa-tools') + ' mr-2');
    }
});
