/**
 * historial_caja.js — Historial de Cajas | SysInversiones CH Computer 2026
 */
$(function () {

    // ── DataTable ─────────────────────────────────────────────────────────────
    const tabla = $('#tablaHistCajas').DataTable({
        language: { url: 'https://cdn.datatables.net/plug-ins/1.10.25/i18n/Spanish.json' },
        order: [[0, 'desc']],
        pageLength: 25,
        responsive: true,
        columnDefs: [{ orderable: false, targets: [11] }],
        dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rtip'
    });

    // ── Filtros rápidos ───────────────────────────────────────────────────────
    $(document).on('click', '.cx-btn-filtro', function () {
        $('.cx-btn-filtro').removeClass('active');
        $(this).addClass('active');
        const filtro = $(this).data('filtro');
        const hoy    = new Date().toISOString().slice(0, 10);
        const semana = new Date(Date.now() - 7 * 86400000).toISOString().slice(0, 10);
        const mes    = hoy.slice(0, 7);

        $.fn.dataTable.ext.search.pop();
        if (filtro !== 'todos') {
            $.fn.dataTable.ext.search.push(function (settings, data, idx) {
                const row    = tabla.row(idx).node();
                const estado = $(row).data('estado') || '';
                const fecha  = $(row).data('fecha')  || '';
                if (filtro === 'abierta' || filtro === 'cerrada') return estado === filtro;
                if (filtro === 'hoy')    return fecha === hoy;
                if (filtro === 'semana') return fecha >= semana;
                if (filtro === 'mes')    return fecha.slice(0, 7) === mes;
                return true;
            });
        }
        tabla.draw();
    });

    // ── Ver detalle de caja ───────────────────────────────────────────────────
    $(document).on('click', '.cx-btn-ver', function () {
        const id = $(this).data('id');
        $('#detalleCajaBody').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted mb-2 d-block" style="opacity:.4;"></i><span class="text-muted" style="font-size:.83rem;">Cargando...</span></div>');
        $('#modalDetalleCaja').modal('show');

        $.get('ajax_caja.php', { accion: 'detalle_caja', id_caja: id }, function (data) {
            if (!data.ok) {
                $('#detalleCajaBody').html('<div class="alert alert-danger m-3">' + (data.msg || 'Error al cargar.') + '</div>');
                return;
            }
            renderDetalle(data);
        }, 'json').fail(function () {
            $('#detalleCajaBody').html('<div class="alert alert-danger m-3">Error de conexión.</div>');
        });
    });

    // ── Renderizar detalle ────────────────────────────────────────────────────
    function renderDetalle(data) {
        const c   = data.caja;
        const det = data.detalle_cierre || [];
        const movs = data.movimientos   || [];
        const refs = data.por_referencia || [];

        const totalIng = movs.filter(m => m.tipo === 'ingreso').reduce((a, m) => a + parseFloat(m.monto), 0);
        const totalEg  = movs.filter(m => m.tipo === 'egreso').reduce((a, m) => a + parseFloat(m.monto), 0);
        const neto     = totalIng - totalEg;

        const estadoBadge = c.estado === 'abierta'
            ? '<span class="cx-badge-abierta"><i class="fas fa-circle" style="font-size:.45rem;"></i>Abierta</span>'
            : '<span class="cx-badge-cerrada"><i class="fas fa-lock" style="font-size:.7rem;"></i>Cerrada</span>';

        let html = `
        <div style="display:flex;flex-wrap:wrap;background:#f8fafc;border-bottom:1px solid #e2e8f0;">
            <div style="flex:1;min-width:120px;padding:12px 18px;display:flex;flex-direction:column;gap:3px;">
                <span style="font-size:.65rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.6px;">Caja</span>
                <span style="font-size:.88rem;font-weight:700;color:#1e293b;">${esc(c.nombre || 'Caja #'+c.id_caja)}</span>
            </div>
            <div style="width:1px;background:#e2e8f0;margin:8px 0;"></div>
            <div style="flex:1;min-width:120px;padding:12px 18px;display:flex;flex-direction:column;gap:3px;">
                <span style="font-size:.65rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.6px;">Cajero</span>
                <span style="font-size:.88rem;font-weight:700;color:#1e293b;">${esc(c.nombre_completo)}</span>
            </div>
            <div style="width:1px;background:#e2e8f0;margin:8px 0;"></div>
            <div style="flex:1;min-width:120px;padding:12px 18px;display:flex;flex-direction:column;gap:3px;">
                <span style="font-size:.65rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.6px;">Estado</span>
                <span>${estadoBadge}</span>
            </div>
            <div style="width:1px;background:#e2e8f0;margin:8px 0;"></div>
            <div style="flex:1;min-width:120px;padding:12px 18px;display:flex;flex-direction:column;gap:3px;">
                <span style="font-size:.65rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.6px;">Fondo inicial</span>
                <span style="font-size:.9rem;font-weight:800;color:#0369a1;font-family:monospace;">S/. ${parseFloat(c.monto_inicial).toFixed(2)}</span>
            </div>
            <div style="width:1px;background:#e2e8f0;margin:8px 0;"></div>
            <div style="flex:1;min-width:120px;padding:12px 18px;display:flex;flex-direction:column;gap:3px;">
                <span style="font-size:.65rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.6px;">Neto del día</span>
                <span style="font-size:.9rem;font-weight:800;font-family:monospace;color:${neto>=0?'#059669':'#dc2626'};">${neto>=0?'+':''}S/. ${neto.toFixed(2)}</span>
            </div>
        </div>`;

        // Resumen por origen
        const refCfg = {
            venta:    { label:'Ventas',    color:'#0ea5e9', bg:'#e0f2fe', icon:'fas fa-shopping-cart' },
            servicio: { label:'Servicios', color:'#10b981', bg:'#dcfce7', icon:'fas fa-tools' },
            compra:   { label:'Compras',   color:'#ef4444', bg:'#fee2e2', icon:'fas fa-truck-loading' },
            manual:   { label:'Manuales',  color:'#f59e0b', bg:'#fef3c7', icon:'fas fa-hand-holding-usd' },
        };
        html += `<div style="padding:14px 18px;border-bottom:1px solid #f1f5f9;">
            <div style="font-size:.75rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;"><i class="fas fa-chart-pie mr-1"></i>Resumen por origen</div>
            <div style="display:flex;flex-wrap:wrap;gap:10px;">`;
        refs.forEach(r => {
            const cfg = refCfg[r.tipo_referencia] || { label:r.tipo_referencia, color:'#64748b', bg:'#f3f4f6', icon:'fas fa-circle' };
            const signo = r.tipo === 'ingreso' ? '+' : '−';
            const color = r.tipo === 'ingreso' ? '#059669' : '#dc2626';
            html += `<div style="background:${cfg.bg};border-radius:10px;padding:10px 14px;min-width:130px;flex:1;">
                <div style="display:flex;align-items:center;gap:7px;margin-bottom:5px;">
                    <i class="${cfg.icon}" style="color:${cfg.color};font-size:.8rem;"></i>
                    <span style="font-size:.75rem;font-weight:700;color:${cfg.color};">${cfg.label}</span>
                </div>
                <div style="font-size:.95rem;font-weight:800;font-family:monospace;color:${color};">${signo} S/. ${parseFloat(r.total).toFixed(2)}</div>
                <div style="font-size:.68rem;color:#94a3b8;margin-top:2px;">${r.cantidad} movimiento(s)</div>
            </div>`;
        });
        if (refs.length === 0) html += '<div style="color:#94a3b8;font-size:.83rem;">Sin movimientos</div>';
        html += `</div></div>`;

        // Detalle de cierre por método
        if (det.length > 0) {
            html += `<div style="padding:14px 18px;border-bottom:1px solid #f1f5f9;">
                <div style="font-size:.75rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;"><i class="fas fa-balance-scale mr-1"></i>Cuadre por canal</div>
                <div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;font-size:.82rem;">
                    <thead><tr style="background:#f8fafc;">
                        <th style="padding:7px 12px;text-align:left;font-weight:700;color:#64748b;border-bottom:2px solid #0ea5e9;">Canal</th>
                        <th style="padding:7px 12px;text-align:right;font-weight:700;color:#64748b;border-bottom:2px solid #0ea5e9;">Esperado</th>
                        <th style="padding:7px 12px;text-align:right;font-weight:700;color:#64748b;border-bottom:2px solid #0ea5e9;">Contado</th>
                        <th style="padding:7px 12px;text-align:right;font-weight:700;color:#64748b;border-bottom:2px solid #0ea5e9;">Diferencia</th>
                    </tr></thead><tbody>`;
            det.forEach(d => {
                const dif = parseFloat(d.diferencia);
                const difColor = Math.abs(dif) < 0.01 ? '#059669' : (dif > 0 ? '#059669' : '#dc2626');
                const difTxt   = Math.abs(dif) < 0.01 ? '✓ Exacto' : (dif > 0 ? '+S/. '+dif.toFixed(2) : '−S/. '+Math.abs(dif).toFixed(2));
                html += `<tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:8px 12px;font-weight:600;text-transform:capitalize;">${d.metodo_pago}</td>
                    <td style="padding:8px 12px;text-align:right;font-family:monospace;color:#0369a1;">S/. ${parseFloat(d.monto_esperado).toFixed(2)}</td>
                    <td style="padding:8px 12px;text-align:right;font-family:monospace;color:#1e293b;">S/. ${parseFloat(d.monto_contado).toFixed(2)}</td>
                    <td style="padding:8px 12px;text-align:right;font-family:monospace;font-weight:700;color:${difColor};">${difTxt}</td>
                </tr>`;
            });
            html += `</tbody></table></div></div>`;
        }

        // Últimos 10 movimientos
        if (movs.length > 0) {
            const iconMap = { efectivo:'fa-money-bill-wave', yape:'fa-mobile-alt', transferencia:'fa-university' };
            html += `<div style="padding:14px 18px;">
                <div style="font-size:.75rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;"><i class="fas fa-exchange-alt mr-1"></i>Últimos movimientos (${movs.length} total)</div>`;
            movs.slice(0, 10).forEach(m => {
                const signo = m.tipo === 'ingreso' ? '+' : '−';
                const color = m.tipo === 'ingreso' ? '#059669' : '#dc2626';
                const fecha = new Date(m.fecha.replace(' ','T'));
                html += `<div style="display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid #f8fafc;">
                    <div style="width:30px;height:30px;border-radius:8px;background:${m.tipo==='ingreso'?'#dcfce7':'#fee2e2'};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fas ${iconMap[m.metodo_pago]||'fa-circle'}" style="color:${color};font-size:.75rem;"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:.82rem;font-weight:600;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${esc(m.descripcion)}</div>
                        <div style="font-size:.7rem;color:#94a3b8;">${fecha.toLocaleDateString('es-PE')} ${fecha.toLocaleTimeString('es-PE',{hour:'2-digit',minute:'2-digit'})}</div>
                    </div>
                    <div style="font-size:.88rem;font-weight:800;font-family:monospace;color:${color};white-space:nowrap;">${signo} S/. ${parseFloat(m.monto).toFixed(2)}</div>
                </div>`;
            });
            html += `</div>`;
        }

        $('#detalleCajaBody').html(html);
    }

    function esc(str) {
        if (!str) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
});
