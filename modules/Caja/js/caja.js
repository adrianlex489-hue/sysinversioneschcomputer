/**
 * caja.js — Gestión de Caja | SysInversiones CH Computer 2026
 */
$(function () {

    const idCaja      = parseInt($('#idCajaActiva').val()) || 0;
    const fechaApert  = $('#fechaApertura').val() || '';
    const METODOS     = ['efectivo','yape','transferencia'];

    if (!idCaja) return; // pantalla de apertura, nada que hacer

    // ── Temporizador de duración ──────────────────────────────────────────────
    function actualizarDuracion() {
        if (!fechaApert) return;
        const ini  = new Date(fechaApert.replace(' ','T'));
        const diff = Math.floor((Date.now() - ini) / 1000);
        const h    = Math.floor(diff / 3600);
        const m    = Math.floor((diff % 3600) / 60);
        const s    = diff % 60;
        $('#cajaDuracion').text(
            (h > 0 ? h + 'h ' : '') + String(m).padStart(2,'0') + 'm ' + String(s).padStart(2,'0') + 's'
        );
    }
    actualizarDuracion();
    setInterval(actualizarDuracion, 1000);

    // ── Cargar resumen en tiempo real ─────────────────────────────────────────
    function cargarResumen() {
        $.get('ajax_caja.php', { accion: 'resumen', id_caja: idCaja }, function (data) {
            if (!data.ok) return;
            const pm = data.por_metodo || {};
            METODOS.forEach(m => {
                const v = pm[m] || { ingreso: 0, egreso: 0, neto: 0 };
                const neto = parseFloat(m === 'efectivo'
                    ? (v.neto + parseFloat($('#idCajaActiva').data('fondo') || 0))
                    : v.neto) || v.neto;
                $('#neto_' + m).text('S/. ' + v.neto.toFixed(2));
                $('#ing_'  + m).html('<i class="fas fa-arrow-up mr-1"></i>' + v.ingreso.toFixed(2));
                $('#eg_'   + m).html('<i class="fas fa-arrow-down mr-1"></i>' + v.egreso.toFixed(2));
            });
            $('#saldoActualVal').text('S/. ' + data.saldo_total.toFixed(2));
            $('#totalIngVal').text('S/. ' + data.total_ingresos.toFixed(2));
            $('#totalEgVal').text('S/. ' + data.total_egresos.toFixed(2));
        }, 'json');
    }

    // ── Cargar movimientos recientes ──────────────────────────────────────────
    function cargarMovsRecientes() {
        $.get('ajax_caja.php', { accion: 'movimientos', id_caja: idCaja, limit: 8 }, function (data) {
            if (!data.ok) return;
            const movs = data.movimientos || [];
            if (movs.length === 0) {
                $('#listaMovsRecientes').html('<div class="text-center py-5 text-muted" style="font-size:.83rem;"><i class="fas fa-inbox fa-2x mb-2 d-block" style="opacity:.3;"></i>Sin movimientos aún</div>');
                return;
            }
            const iconMap = { efectivo:'fa-money-bill-wave', yape:'fa-mobile-alt', transferencia:'fa-university' };
            let html = '';
            movs.forEach(m => {
                const signo = m.tipo === 'ingreso' ? '+' : '−';
                const fecha = new Date(m.fecha.replace(' ','T'));
                const hora  = fecha.toLocaleTimeString('es-PE', { hour:'2-digit', minute:'2-digit' });
                html += `<div class="cx-mov-item">
                    <div class="cx-mov-avatar ${m.tipo === 'ingreso' ? 'ing' : 'eg'}">
                        <i class="fas ${iconMap[m.metodo_pago] || 'fa-circle'}"></i>
                    </div>
                    <div class="cx-mov-desc">
                        <div class="cx-mov-text">${esc(m.descripcion)}</div>
                        <div class="cx-mov-meta">${hora} · ${esc(m.nombre_completo || '—')} · <span style="text-transform:capitalize;">${m.metodo_pago}</span></div>
                    </div>
                    <div class="cx-mov-monto ${m.tipo === 'ingreso' ? 'ing' : 'eg'}">${signo} S/. ${parseFloat(m.monto).toFixed(2)}</div>
                </div>`;
            });
            $('#listaMovsRecientes').html(html);
        }, 'json');
    }

    // ── Cargar tabla completa ─────────────────────────────────────────────────
    function cargarTabla(filtroTipo, filtroMetodo) {
        const params = { accion: 'movimientos', id_caja: idCaja, limit: 500 };
        if (filtroTipo)   params.tipo   = filtroTipo;
        if (filtroMetodo) params.metodo = filtroMetodo;

        $.get('ajax_caja.php', params, function (data) {
            if (!data.ok) return;
            const movs = data.movimientos || [];
            const refLabels = { venta:'Venta', compra:'Compra', servicio:'Servicio', manual:'Manual' };
            const refClass  = { venta:'cx-ref-venta', compra:'cx-ref-compra', servicio:'cx-ref-servicio', manual:'cx-ref-manual' };
            const iconMap   = { efectivo:'fa-money-bill-wave', yape:'fa-mobile-alt', transferencia:'fa-university' };

            if (movs.length === 0) {
                $('#tbodyMovimientos').html('<tr><td colspan="7" class="text-center py-4 text-muted"><i class="fas fa-inbox fa-2x mb-2 d-block" style="opacity:.3;"></i>Sin movimientos</td></tr>');
                return;
            }
            let html = '';
            movs.forEach(m => {
                const signo = m.tipo === 'ingreso' ? '+' : '−';
                const color = m.tipo === 'ingreso' ? '#059669' : '#dc2626';
                const fecha = new Date(m.fecha.replace(' ','T'));
                const fStr  = fecha.toLocaleDateString('es-PE',{day:'2-digit',month:'2-digit',year:'numeric'}) + ' ' + fecha.toLocaleTimeString('es-PE',{hour:'2-digit',minute:'2-digit'});
                const refCls = refClass[m.tipo_referencia] || 'cx-ref-manual';
                const refLbl = (refLabels[m.tipo_referencia] || m.tipo_referencia) + (m.id_referencia ? ' #'+m.id_referencia : '');
                html += `<tr>
                    <td><small class="text-muted">${fStr}</small></td>
                    <td><span class="cx-badge-${m.tipo==='ingreso'?'ing':'eg'}">${m.tipo.toUpperCase()}</span></td>
                    <td><div style="font-weight:600;font-size:.82rem;">${esc(m.descripcion)}</div>${m.observacion?`<small class="text-muted">${esc(m.observacion)}</small>`:''}</td>
                    <td><span class="${refCls}">${refLbl}</span></td>
                    <td><i class="fas ${iconMap[m.metodo_pago]||'fa-circle'} mr-1" style="color:#64748b;"></i><span style="font-size:.79rem;font-weight:600;text-transform:capitalize;">${m.metodo_pago}</span></td>
                    <td class="text-right" style="font-family:monospace;font-weight:800;color:${color};">${signo} S/. ${parseFloat(m.monto).toFixed(2)}</td>
                    <td><small>${esc(m.nombre_completo||'—')}</small></td>
                </tr>`;
            });
            $('#tbodyMovimientos').html(html);
        }, 'json');
    }

    // ── Inicializar ───────────────────────────────────────────────────────────
    cargarResumen();
    cargarMovsRecientes();
    cargarTabla('', '');

    // Actualizar cada 30 segundos
    setInterval(() => { cargarResumen(); cargarMovsRecientes(); }, 30000);

    // Botón actualizar
    $('#btnRefresh').on('click', function () {
        $(this).find('i').css('transform','rotate(360deg)');
        cargarResumen(); cargarMovsRecientes(); cargarTabla($('#filtroTipo').val(), $('#filtroMetodo').val());
        setTimeout(() => $(this).find('i').css('transform',''), 600);
    });

    // Filtros tabla
    $('#filtroTipo, #filtroMetodo').on('change', function () {
        cargarTabla($('#filtroTipo').val(), $('#filtroMetodo').val());
    });

    // ── Modal movimiento ──────────────────────────────────────────────────────
    $(document).on('click', '.cx-btn-tipo', function () {
        $('.cx-btn-tipo').removeClass('activo'); $(this).addClass('activo');
        $('#hiddenTipoMov').val($(this).data('tipo'));
    });
    $(document).on('click', '.cx-btn-metodo', function () {
        $('.cx-btn-metodo').removeClass('activo'); $(this).addClass('activo');
        $('#hiddenMetodoMov').val($(this).data('metodo'));
    });
    $('#modalMovManual').on('hidden.bs.modal', function () {
        $(this).find('form')[0].reset();
        $('.cx-btn-tipo, .cx-btn-metodo').removeClass('activo');
        $('.cx-btn-metodo[data-metodo="efectivo"]').addClass('activo');
        $('#hiddenTipoMov').val(''); $('#hiddenMetodoMov').val('efectivo');
    });

    // ── Helper ────────────────────────────────────────────────────────────────
    function esc(str) {
        if (!str) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
});
