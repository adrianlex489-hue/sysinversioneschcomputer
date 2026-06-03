/**
 * resumen_caja.js — Gráficos del Resumen de Caja | SysInversiones 2026
 * Compatible con Chart.js v3.x (cargado en footer.php)
 * Los datos se inyectan en window.RC desde resumen_caja.php
 */
$(function () {

    if (typeof Chart === 'undefined') {
        console.error('[resumen_caja] Chart.js no está disponible.');
        return;
    }
    if (typeof window.RC === 'undefined') {
        console.error('[resumen_caja] window.RC no está definido.');
        return;
    }

    Chart.defaults.font.family = "'Inter','Segoe UI',sans-serif";
    Chart.defaults.color = '#64748b';

    // ── Gráfico de línea: flujo por hora ─────────────────────────────────────
    var ctxLinea = document.getElementById('chartLinea');
    if (ctxLinea) {
        if (!window.RC.hayDatosHora) {
            ctxLinea.style.display = 'none';
            var msg = document.createElement('div');
            msg.style.cssText = 'text-align:center;padding:40px 20px;color:#94a3b8;font-size:.85rem;';
            msg.innerHTML =
                '<i class="fas fa-chart-line" style="font-size:2rem;display:block;margin-bottom:10px;opacity:.3;"></i>' +
                'Sin movimientos registrados aún.<br>' +
                '<small>El gráfico se actualizará cuando haya actividad en caja.</small>';
            ctxLinea.parentNode.appendChild(msg);
        } else {
            new Chart(ctxLinea, {
                type: 'line',
                data: {
                    labels: window.RC.horasLabels,
                    datasets: [
                        {
                            label: 'Ingresos',
                            data: window.RC.horasIng,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16,185,129,.1)',
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: '#10b981',
                            pointRadius: 4
                        },
                        {
                            label: 'Egresos',
                            data: window.RC.horasEg,
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239,68,68,.08)',
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: '#ef4444',
                            pointRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'top', labels: { usePointStyle: true, padding: 16 } },
                        tooltip: {
                            callbacks: {
                                label: function (c) {
                                    return c.dataset.label + ': S/. ' + c.parsed.y.toFixed(2);
                                }
                            }
                        }
                    },
                    scales: {
                        x: { grid: { color: '#f1f5f9' } },
                        y: {
                            grid: { color: '#f1f5f9' },
                            beginAtZero: true,
                            ticks: { callback: function (v) { return 'S/. ' + v.toFixed(0); } }
                        }
                    }
                }
            });
        }
    }

    // ── Helper: dona + leyenda ────────────────────────────────────────────────
    function buildDona(canvasId, leyendaId, labels, data, colors) {
        var ctx = document.getElementById(canvasId);
        var leg = document.getElementById(leyendaId);
        if (!ctx || !leg) return;

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors,
                    borderWidth: 2,
                    borderColor: '#fff',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                cutout: '65%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return ' S/. ' + context.parsed.toFixed(2);
                            }
                        }
                    }
                }
            }
        });

        leg.innerHTML = '';
        labels.forEach(function (l, i) {
            leg.innerHTML +=
                '<div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;font-size:.78rem;">' +
                '<span style="display:flex;align-items:center;gap:7px;">' +
                '<span style="width:10px;height:10px;border-radius:50%;background:' + colors[i] + ';display:inline-block;flex-shrink:0;"></span>' +
                l +
                '</span>' +
                '<strong style="font-family:monospace;color:' + colors[i] + ';margin-left:8px;">S/. ' + data[i].toFixed(2) + '</strong>' +
                '</div>';
        });
    }

    buildDona('chartDona',   'donaLeyenda',   window.RC.donaLabels,   window.RC.donaData,   window.RC.donaColors);
    buildDona('chartDonaEg', 'donaEgLeyenda', window.RC.donaEgLabels, window.RC.donaEgData, window.RC.donaEgColors);

    // ── Gráfico de barras: por método de pago ─────────────────────────────────
    var ctxBarras = document.getElementById('chartBarras');
    if (ctxBarras) {
        new Chart(ctxBarras, {
            type: 'bar',
            data: {
                labels: window.RC.barLabels,
                datasets: [
                    {
                        label: 'Ingresos',
                        data: window.RC.barIng,
                        backgroundColor: window.RC.barColors.map(function (c) { return c + 'bb'; }),
                        borderColor: window.RC.barColors,
                        borderWidth: 1.5,
                        borderRadius: 5
                    },
                    {
                        label: 'Egresos',
                        data: window.RC.barEg,
                        backgroundColor: 'rgba(239,68,68,.3)',
                        borderColor: '#ef4444',
                        borderWidth: 1.5,
                        borderRadius: 5
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'top', labels: { usePointStyle: true, padding: 14 } },
                    tooltip: {
                        callbacks: {
                            label: function (c) {
                                return c.dataset.label + ': S/. ' + c.parsed.y.toFixed(2);
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        grid: { color: '#f1f5f9' },
                        beginAtZero: true,
                        ticks: { callback: function (v) { return 'S/. ' + v.toFixed(0); } }
                    }
                }
            }
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

});
