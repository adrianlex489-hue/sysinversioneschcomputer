/**
 * detalle_caja_reporte.js — Detalle de Caja (Reporte)
 * SysInversiones CH Computer 2026
 */
$(function () {

    // ── Gráfico de flujo por hora ─────────────────────────────────────────────
    if (window.DC && DC.chartLabels.length && typeof Chart !== 'undefined') {
        var ctx = document.getElementById('chartDetalleDia');
        if (ctx) {
            new Chart(ctx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: DC.chartLabels,
                    datasets: [
                        {
                            label: 'Ingresos',
                            data: DC.chartIng,
                            backgroundColor: 'rgba(16,185,129,.75)',
                            borderColor: '#059669',
                            borderWidth: 1,
                            borderRadius: 5
                        },
                        {
                            label: 'Egresos',
                            data: DC.chartEg,
                            backgroundColor: 'rgba(239,68,68,.65)',
                            borderColor: '#dc2626',
                            borderWidth: 1,
                            borderRadius: 5
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: function (c) {
                                    return c.dataset.label + ': S/. ' + parseFloat(c.raw).toFixed(2);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: function (v) { return 'S/. ' + v.toFixed(0); } }
                        }
                    }
                }
            });
        }
    }

    // ── Buscador en tabla ─────────────────────────────────────────────────────
    $('#dcBuscar').on('input', function () {
        var q = $(this).val().toLowerCase().trim();
        $('.rc-fila').each(function () {
            var txt = ($(this).data('search') || '').toLowerCase();
            $(this).toggle(!q || txt.indexOf(q) > -1);
        });
    });

    // ── Modal exportar ────────────────────────────────────────────────────────
    $('#btnExportarDetalle').on('click', function () {
        $('#modalExportarDetalle').modal('show');
    });

    function dcExportar(formato) {
        var url = 'ajax_detalle_caja_export.php?exportar=' + formato + '&id_caja=' + DC.id_caja;
        window.location.href = url;
        $('#modalExportarDetalle').modal('hide');
    }

    function dcExportarPDF() {
        window.open('detalle_caja_reporte_pdf.php?id_caja=' + DC.id_caja, '_blank');
        $('#modalExportarDetalle').modal('hide');
    }

    $('#dc_btn_csv').on('click',   function () { dcExportar('csv'); });
    $('#dc_btn_excel').on('click', function () { dcExportar('excel'); });
    $('#dc_btn_pdf').on('click',   function () { dcExportarPDF(); });

    // ── Modal detalle movimiento ──────────────────────────────────────────────
    $(document).on('click', '.btn-ver-mov', function () {
        var id  = $(this).data('id');
        var ref = $(this).data('ref');
        var rid = $(this).data('refid');
        $('#detalleMovBody').html(
            '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted mb-2 d-block" style="opacity:.4;"></i>' +
            '<span class="text-muted" style="font-size:.83rem;">Cargando...</span></div>'
        );
        $('#modalDetalleMovimiento').modal('show');
        $.get('ajax_detalle_movimiento.php', {
            id_movimiento:  id,
            tipo_referencia: ref,
            id_referencia:  rid
        })
        .done(function (html) { $('#detalleMovBody').html(html); })
        .fail(function ()     { $('#detalleMovBody').html('<p class="text-danger p-4">Error al cargar el detalle.</p>'); });
    });

    // ── Fix modales anidados ──────────────────────────────────────────────────
    $(document).on('hidden.bs.modal', '.modal', function () {
        if ($('.modal.show').length) $('body').addClass('modal-open');
    });

});
