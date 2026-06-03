/**
 * cierre_caja.js — Cierre de Caja | SysInversiones CH Computer 2026
 */
$(function () {
    const idCaja  = parseInt($('#idCajaCierre').val()) || 0;
    const METODOS = ['efectivo','yape','transferencia'];
    let esperado  = {};

    if (!idCaja) return;

    // ── Cargar esperado desde el servidor ─────────────────────────────────────
    $.get('ajax_caja.php', { accion: 'esperado_cierre', id_caja: idCaja }, function (data) {
        if (!data.ok) return;
        esperado = data.esperado || {};
        const total = data.total_esperado || 0;

        METODOS.forEach(m => {
            const esp = esperado[m] || 0;
            $('#esp_' + m).text('S/. ' + esp.toFixed(2));
        });
        $('#totalEsperado').text('S/. ' + total.toFixed(2));
        $('#totalEsperadoBar').text('S/. ' + total.toFixed(2));
        calcularDiferencias();
    }, 'json');

    // ── Cargar resumen lateral ────────────────────────────────────────────────
    $.get('ajax_caja.php', { accion: 'resumen', id_caja: idCaja }, function (data) {
        if (!data.ok) return;
        const pm = data.por_metodo || {};
        let html = '';
        const origenCfg = {
            ventas:    { label:'Ventas',    color:'#0ea5e9' },
            servicios: { label:'Servicios', color:'#10b981' },
            compras:   { label:'Compras',   color:'#ef4444' },
            manuales:  { label:'Manuales',  color:'#f59e0b' },
        };

        html += `<div style="display:flex;flex-direction:column;gap:6px;">`;
        [
            { lbl:'Fondo inicial',  val:'S/. '+parseFloat(data.monto_inicial||0).toFixed(2), color:'#0369a1' },
            { lbl:'Total ingresos', val:'+S/. '+data.total_ingresos.toFixed(2),              color:'#059669' },
            { lbl:'Total egresos',  val:'−S/. '+data.total_egresos.toFixed(2),               color:'#dc2626' },
        ].forEach(r => {
            html += `<div class="cx-res-row"><span style="color:#64748b;font-size:.83rem;">${r.lbl}</span><span style="font-family:monospace;font-weight:700;color:${r.color};">${r.val}</span></div>`;
        });
        html += `</div>`;

        const neto = data.total_ingresos - data.total_egresos;
        html += `<div class="cx-res-total"><span><i class="fas fa-coins mr-2"></i>Saldo esperado</span><span style="font-family:monospace;">S/. ${data.saldo_total.toFixed(2)}</span></div>`;

        // Por método
        html += `<div style="margin-top:14px;">`;
        METODOS.forEach(m => {
            const v = pm[m] || { ingreso:0, egreso:0 };
            const neto_m = (m === 'efectivo' ? parseFloat(data.monto_inicial||0) : 0) + v.ingreso - v.egreso;
            if (neto_m === 0 && v.ingreso === 0) return;
            html += `<div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid #f1f5f9;font-size:.82rem;">
                <span style="color:#475569;text-transform:capitalize;">${m}</span>
                <span style="font-family:monospace;font-weight:700;color:${neto_m>=0?'#059669':'#dc2626'};">${neto_m>=0?'+':''}S/. ${neto_m.toFixed(2)}</span>
            </div>`;
        });
        html += `</div>`;

        $('#cierreResumenBody').html(html);
    }, 'json');

    // ── Calcular diferencias en tiempo real ───────────────────────────────────
    function calcularDiferencias() {
        let totalContado  = 0;
        let totalEsperado = 0;

        METODOS.forEach(m => {
            const cnt = parseFloat($('#cnt_' + m).val()) || 0;
            const esp = esperado[m] || 0;
            const dif = cnt - esp;
            totalContado  += cnt;
            totalEsperado += esp;

            const difEl = $('#dif_' + m);
            if (Math.abs(dif) < 0.01) {
                difEl.html('<span style="color:#059669;font-weight:700;">✓ Exacto</span>');
            } else if (dif > 0) {
                difEl.html('<span style="color:#059669;font-weight:700;">+S/. ' + dif.toFixed(2) + '</span>');
            } else {
                difEl.html('<span style="color:#dc2626;font-weight:700;">−S/. ' + Math.abs(dif).toFixed(2) + '</span>');
            }
        });

        $('#totalContado').text('S/. ' + totalContado.toFixed(2));
        const difTotal = totalContado - totalEsperado;
        const difBox   = $('#difGlobalBox');

        if (totalEsperado > 0) {
            difBox.show();
            if (Math.abs(difTotal) < 0.01) {
                difBox.attr('class','cx-dif-global ok mb-3').html('<i class="fas fa-check-circle fa-lg mr-2"></i><div><div style="font-size:.9rem;">Cuadre exacto</div><div style="font-size:.75rem;opacity:.8;">El conteo coincide con el sistema</div></div>');
                $('#totalDif').html('<span style="color:#059669;">✓ Exacto</span>');
            } else if (difTotal > 0) {
                difBox.attr('class','cx-dif-global pos mb-3').html('<i class="fas fa-arrow-circle-up fa-lg mr-2"></i><div><div style="font-size:.9rem;">Sobrante: S/. ' + difTotal.toFixed(2) + '</div><div style="font-size:.75rem;opacity:.8;">Hay más dinero del esperado</div></div>');
                $('#totalDif').html('<span style="color:#059669;">+S/. ' + difTotal.toFixed(2) + '</span>');
            } else {
                difBox.attr('class','cx-dif-global neg mb-3').html('<i class="fas fa-arrow-circle-down fa-lg mr-2"></i><div><div style="font-size:.9rem;">Faltante: S/. ' + Math.abs(difTotal).toFixed(2) + '</div><div style="font-size:.75rem;opacity:.8;">Hay menos dinero del esperado</div></div>');
                $('#totalDif').html('<span style="color:#dc2626;">−S/. ' + Math.abs(difTotal).toFixed(2) + '</span>');
            }
        }
    }

    $(document).on('input', '.cx-cnt-input', calcularDiferencias);

    // ── Confirmar cierre ──────────────────────────────────────────────────────
    $('#formCierre').on('submit', function (e) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: '¿Confirmar cierre de caja?',
            html: 'Esta acción es <strong>irreversible</strong>.<br>La caja quedará cerrada y no podrás registrar más movimientos.',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-lock mr-1"></i>Sí, cerrar caja',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#0ea5e9',
            cancelButtonColor: '#6c757d'
        }).then(result => {
            if (result.isConfirmed) this.submit();
        });
    });
});
