/**
 * historial_compras.js — Historial de Compras | SysInversiones 2026
 */
$(function () {

    // Fix modales Bootstrap 4
    $(document).on('hidden.bs.modal', '.modal', function () {
        if ($('.modal.show').length) $('body').addClass('modal-open');
    });

    // DataTable
    var tabla = null;
    if ($('#tablaHistorial').length) {
        tabla = $('#tablaHistorial').DataTable({
            language: {
                search: '',
                searchPlaceholder: 'Buscar compra...',
                lengthMenu: 'Mostrar _MENU_ registros',
                info: 'Mostrando _START_ a _END_ de _TOTAL_',
                infoEmpty: 'Sin registros',
                zeroRecords: 'No se encontraron resultados',
                paginate: { previous: '&#8249;', next: '&#8250;' }
            },
            responsive: true,
            autoWidth: false,
            order: [[0, 'desc']],
            pageLength: 20,
            columnDefs: [{ orderable: false, targets: [-1] }]
        });
    }

    // Filtro rapido por estado
    $(document).on('click', '.btn-filtro-estado', function () {
        var colores = { todos:'#1a5276', pagado:'#27ae60', pendiente:'#e67e22', anulado:'#e74c3c' };
        $('.btn-filtro-estado').each(function () {
            var c = colores[$(this).data('estado')] || '#1a5276';
            $(this).css({ background: '#fff', color: c, borderColor: c });
        });
        var estado = $(this).data('estado');
        var c = colores[estado] || '#1a5276';
        $(this).css({ background: c, color: '#fff', borderColor: c });

        if (tabla) {
            tabla.column(7).search(estado === 'todos' ? '' : estado, true, false).draw();
        }
    });
    // Estilo inicial boton Todos
    var cTodos = '#1a5276';
    $('.btn-filtro-estado[data-estado=todos]').css({ background: cTodos, color: '#fff', borderColor: cTodos });

    // Ver detalle compra
    $(document).on('click', '.btn-ver-compra', function () {
        var id = $(this).data('id');
        $('#modalVerCompra .modal-body').html(
            '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i><p class="mt-3 text-muted">Cargando detalle...</p></div>'
        );
        $('#modalVerCompra').modal('show');
        $.get('historial_compras.php', { accion: 'detalle_ajax', id_compra: id }, function (html) {
            $('#modalVerCompra .modal-body').html(html);
        }).fail(function () {
            $('#modalVerCompra .modal-body').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle mr-2"></i>Error al cargar el detalle.</div>');
        });
    });

    // Ver ticket PDF en modal
    $(document).on('click', '.btn-ver-ticket', function () {
        var id      = $(this).data('id');
        var tipo    = $(this).data('tipo')    || 'ticket';
        var entidad = $(this).data('entidad') || 'compra';
        var numero  = $(this).data('numero')  || ('#' + id);

        // Si es nota → abrir en nueva pestaña con imprimir.php
        if (tipo !== 'ticket') {
            window.open('../../Comprobantes/imprimir.php?tipo=' + entidad + '&id=' + id, '_blank');
            return;
        }

        // Ticket 80mm → modal con iframe
        var urlPrev = '../../Comprobantes/comprobante_ticket_compra.php?id_compra=' + id;
        var urlDesc = '../../Comprobantes/comprobante_ticket_compra.php?id_compra=' + id + '&download=1';

        $('#ticketPdfNumero').text(numero);
        $('#btnDescargarTicket').attr('href', urlDesc);
        $('#ticketPdfFrame').attr('src', '').hide();
        $('#ticketPdfCargando').show();
        $('#modalTicketPDF').modal('show');

        $('#modalTicketPDF').one('shown.bs.modal', function () {
            $('#ticketPdfFrame').attr('src', urlPrev);
        });
    });

    // Limpiar iframe al cerrar modal PDF
    $('#modalTicketPDF').on('hidden.bs.modal', function () {
        $('#ticketPdfFrame').attr('src', '').hide();
        $('#ticketPdfCargando').show();
    });

    // Registrar pago
    $(document).on('click', '.btn-pagar-compra', function () {
        var id        = $(this).data('id');
        var saldo     = parseFloat($(this).data('saldo')) || 0;
        var proveedor = $(this).data('proveedor') || '';
        var numero    = $(this).data('numero') || ('#' + id);

        $('#pago_id_compra').val(id);
        $('#pago_saldo_display').text('S/. ' + saldo.toFixed(2));
        $('#pago_proveedor_label').text(proveedor + ' — ' + numero);
        $('#monto_pago').data('saldo', saldo).removeAttr('readonly');
        $('#hcomp_monto_hint').text('Saldo pendiente: S/. ' + saldo.toFixed(2));
        $('#hcomp_cuotas_lista').html('');

        $('.btn-metodo-pago').css({ background: '#fff', color: '#555', borderColor: '#dee2e6' });
        $('.btn-metodo-pago[data-metodo=efectivo]').css({ background: '#1a7a4a', color: '#fff', borderColor: '#1a7a4a' });
        $('#metodo_pago_abono').val('efectivo');
        $('#obs_pago').val('');

        // Cargar cuotas vía AJAX
        $('#hcomp_cuotas_loading').show();
        $.get('historial_compras.php', { accion: 'cuotas_ajax', id_compra: id }, function (data) {
            $('#hcomp_cuotas_loading').hide();
            if (!data.ok || !data.cuotas || data.cuotas.length === 0) {
                $('#monto_pago').val(saldo.toFixed(2)).attr('max', saldo).removeAttr('readonly');
                $('#hcomp_monto_hint').text('Saldo pendiente: S/. ' + saldo.toFixed(2));
                return;
            }
            renderCuotasHComp(data.cuotas, saldo);
        }, 'json').fail(function () {
            $('#hcomp_cuotas_loading').hide();
            $('#monto_pago').val(saldo.toFixed(2)).attr('max', saldo);
        });

        $('#modalPagarCompra').modal('show');
    });

    // ── Renderizar cuotas en el modal de pago ─────────────────────────────────
    function renderCuotasHComp(cuotas, saldo) {
        var activa = null;
        for (var i = 0; i < cuotas.length; i++) {
            if (cuotas[i].estado === 'pendiente' || cuotas[i].estado === 'vencido') {
                activa = cuotas[i]; break;
            }
        }

        if (!activa) {
            $('#hcomp_cuotas_lista').html(
                '<div class="alert alert-success border-0 mb-2" style="border-radius:8px;font-size:.85rem;">' +
                '<i class="fas fa-check-circle mr-2"></i>Todas las cuotas están pagadas.</div>'
            );
            $('#monto_pago').val('0.00').attr('readonly', true);
            return;
        }

        var montoCuota = parseFloat(activa.monto_cuota);
        $('#monto_pago').val(montoCuota.toFixed(2)).attr('max', saldo).attr('readonly', true);
        $('#hcomp_monto_hint').html(
            '<i class="fas fa-lock mr-1 text-warning"></i>' +
            'Monto fijado al valor de la cuota activa. Paga una cuota a la vez.'
        );

        var html = '<div style="margin-bottom:10px;">';
        html += '<div style="font-size:.78rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">' +
                '<i class="fas fa-calendar-alt mr-1"></i>Cronograma de cuotas</div>';

        cuotas.forEach(function (c) {
            var esPagada  = c.estado === 'pagado';
            var esActiva  = c.id_cuota === activa.id_cuota;
            var esVencida = c.estado === 'vencido';

            var venc = '—';
            if (c.fecha_vencimiento) {
                var d = new Date(c.fecha_vencimiento + 'T00:00:00');
                venc = d.toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric' });
            }

            var rowStyle, iconHtml, badgeHtml, opacityStyle = '';

            if (esPagada) {
                rowStyle  = 'background:#f0fdf4;border:1px solid #bbf7d0;';
                iconHtml  = '<i class="fas fa-check-circle" style="color:#22c55e;font-size:1.1rem;"></i>';
                badgeHtml = '<span style="background:#dcfce7;color:#166534;font-size:.72rem;padding:2px 8px;border-radius:20px;font-weight:600;">PAGADA</span>';
            } else if (esActiva) {
                rowStyle  = 'background:#f0fdf4;border:2px solid #1a7a4a;box-shadow:0 2px 8px rgba(26,122,74,.15);';
                iconHtml  = '<i class="fas fa-arrow-right" style="color:#1a7a4a;font-size:1.1rem;"></i>';
                badgeHtml = '<span style="background:#dcfce7;color:#166534;font-size:.72rem;padding:2px 8px;border-radius:20px;font-weight:700;">' +
                            (esVencida ? '⚠ VENCIDA — PAGAR AHORA' : '← PAGAR AHORA') + '</span>';
            } else {
                rowStyle     = 'background:#f8fafc;border:1px solid #e2e8f0;';
                opacityStyle = 'opacity:.45;';
                iconHtml     = '<i class="fas fa-lock" style="color:#94a3b8;font-size:1rem;"></i>';
                badgeHtml    = '<span style="background:#f1f5f9;color:#94a3b8;font-size:.72rem;padding:2px 8px;border-radius:20px;">BLOQUEADA</span>';
            }

            html += '<div style="' + rowStyle + opacityStyle + 'border-radius:8px;padding:10px 14px;margin-bottom:6px;display:flex;align-items:center;gap:12px;">' +
                '<div style="flex-shrink:0;">' + iconHtml + '</div>' +
                '<div style="flex:1;min-width:0;">' +
                    '<div style="font-weight:700;font-size:.88rem;color:#1e293b;">Cuota ' + c.numero_cuota + ' de ' + cuotas.length +
                        ' <span style="margin-left:6px;">' + badgeHtml + '</span></div>' +
                    '<div style="font-size:.78rem;color:#64748b;margin-top:2px;"><i class="fas fa-calendar mr-1"></i>Vence: ' + venc + '</div>' +
                '</div>' +
                '<div style="font-weight:700;font-size:1rem;color:' + (esPagada ? '#22c55e' : esActiva ? '#1a7a4a' : '#94a3b8') + ';white-space:nowrap;">' +
                    'S/. ' + parseFloat(c.monto_cuota).toFixed(2) +
                '</div>' +
            '</div>';
        });

        html += '</div>';
        $('#hcomp_cuotas_lista').html(html);
    }

    $(document).on('click', '.btn-metodo-pago', function () {
        $('.btn-metodo-pago').css({ background: '#fff', color: '#555', borderColor: '#dee2e6' });
        $(this).css({ background: '#1a7a4a', color: '#fff', borderColor: '#1a7a4a' });
        $('#metodo_pago_abono').val($(this).data('metodo'));
    });

    // Validar y confirmar pago
    $('#formRegistrarPago').on('submit', function (e) {
        e.preventDefault();
        var monto = parseFloat($('#monto_pago').val()) || 0;
        var max   = parseFloat($('#monto_pago').attr('max')) || 0;
        if (monto <= 0) {
            Swal.fire({ icon: 'warning', title: 'Monto invalido', text: 'El monto debe ser mayor a 0.', confirmButtonColor: '#1a5276' });
            return;
        }
        if (monto > max + 0.01) {
            Swal.fire({ icon: 'warning', title: 'Monto excede saldo', text: 'El monto no puede superar el saldo pendiente de S/. ' + max.toFixed(2), confirmButtonColor: '#1a5276' });
            return;
        }
        Swal.fire({
            icon: 'question', title: 'Registrar pago?',
            html: 'Monto: <b>S/. ' + monto.toFixed(2) + '</b><br>Metodo: <b>' + $('#metodo_pago_abono').val().toUpperCase() + '</b>',
            showCancelButton: true,
            confirmButtonColor: '#1a7a4a', cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-check mr-1"></i> Si, registrar',
            cancelButtonText: 'Cancelar'
        }).then(function (r) {
            if (r.isConfirmed) document.getElementById('formRegistrarPago').submit();
        });
    });

    // ── EXPORTAR HISTORIAL COMPRAS ────────────────────────
    $('#btnExportarHCmp').on('click', function () {
        $('#modalExportarHCmp').modal('show');
    });

    function hcmpGetParams() {
        return {
            estado:      $('#hcmp_exp_estado').val() || 'all',
            tipo_pago:   $('#hcmp_exp_pago').val()   || 'all',
            fecha_desde: $('#hcmp_exp_desde').val()  || '',
            fecha_hasta: $('#hcmp_exp_hasta').val()  || '',
        };
    }

    function hcmpExportar(formato) {
        var p = hcmpGetParams();
        var url = 'ajax_historial_compras_export.php?exportar=' + formato
            + '&estado='      + encodeURIComponent(p.estado)
            + '&tipo_pago='   + encodeURIComponent(p.tipo_pago)
            + '&fecha_desde=' + encodeURIComponent(p.fecha_desde)
            + '&fecha_hasta=' + encodeURIComponent(p.fecha_hasta);
        window.location.href = url;
        $('#modalExportarHCmp').modal('hide');
    }

    function hcmpExportarPDF() {
        var p = hcmpGetParams();
        var url = 'historial_compras_pdf.php?estado=' + encodeURIComponent(p.estado)
            + '&tipo_pago='   + encodeURIComponent(p.tipo_pago)
            + '&fecha_desde=' + encodeURIComponent(p.fecha_desde)
            + '&fecha_hasta=' + encodeURIComponent(p.fecha_hasta);
        window.open(url, '_blank');
        $('#modalExportarHCmp').modal('hide');
    }

    $('#hcmp_btn_csv').on('click',   function () { hcmpExportar('csv'); });
    $('#hcmp_btn_excel').on('click', function () { hcmpExportar('excel'); });
    $('#hcmp_btn_pdf').on('click',   function () { hcmpExportarPDF(); });

    // Anular compra
    $(document).on('click', '.btn-anular-compra', function () {
        var id  = $(this).data('id');
        var num = $(this).data('numero') || ('#' + id);
        Swal.fire({
            icon: 'error', title: 'Anular compra?',
            html: 'La compra <b>' + num + '</b> sera anulada.<br><strong class="text-danger">El stock de los productos sera revertido.</strong>',
            showCancelButton: true,
            confirmButtonColor: '#e74c3c', cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-ban mr-1"></i> Si, anular',
            cancelButtonText: 'Cancelar'
        }).then(function (r) {
            if (r.isConfirmed) {
                var $f = $('<form method="POST" style="display:none;"></form>');
                $f.append('<input type="hidden" name="accion" value="anular">');
                $f.append('<input type="hidden" name="id_compra" value="' + id + '">');
                $('body').append($f);
                $f.submit();
            }
        });
    });

});
