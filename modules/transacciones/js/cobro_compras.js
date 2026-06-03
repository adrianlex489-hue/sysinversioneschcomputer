/**
 * cobro_compras.js — Pago de Compras a Crédito | SysInversiones 2026
 */
$(function () {

    // ── DataTables ────────────────────────────────────────────────────────────
    if ($('#tablaPendientesComp').length) {
        $('#tablaPendientesComp').DataTable({
            language: { url: 'https://cdn.datatables.net/plug-ins/1.10.25/i18n/Spanish.json' },
            order: [[5, 'asc']],
            pageLength: 25,
            columnDefs: [{ orderable: false, targets: [6] }]
        });
    }
    if ($('#tablaHistorialComp').length) {
        $('#tablaHistorialComp').DataTable({
            language: { url: 'https://cdn.datatables.net/plug-ins/1.10.25/i18n/Spanish.json' },
            order: [[6, 'desc']],
            pageLength: 25
        });
    }

    // ── Abrir modal PAGAR ─────────────────────────────────────────────────────
    $(document).on('click', '.btn-pagar-comp', function () {

        if (!window.CAJA_ABIERTA) {
            Swal.fire({
                icon: 'warning', title: 'Caja no aperturada',
                html: 'No puedes registrar pagos a proveedores sin una caja abierta.<br><br>' +
                      '<a href="../Caja/caja.php" class="btn btn-sm btn-warning font-weight-bold">' +
                      '<i class="fas fa-cash-register mr-1"></i>Ir al módulo de Caja</a>',
                confirmButtonColor: '#e67e22', confirmButtonText: 'Entendido'
            });
            return;
        }

        const id        = $(this).data('id');
        const comp      = $(this).data('comp');
        const proveedor = $(this).data('proveedor');
        const total     = parseFloat($(this).data('total')) || 0;
        const saldo     = parseFloat($(this).data('saldo')) || total;

        $('#pago-id-compra').val(id);
        $('#pago-modal-comp').text(comp);
        $('#pago-modal-proveedor').text(proveedor);
        $('#pago-modal-total').text('S/. ' + total.toFixed(2));
        $('#pago-modal-saldo').text('S/. ' + saldo.toFixed(2));
        $('#pago-monto').data('saldo', saldo).removeAttr('readonly');
        $('#comp-cuotas-lista').html('');

        $('input[name="metodo_pago"][value="efectivo"]').prop('checked', true);
        $('input[name="observacion"]').val('');

        // Cargar cuotas vía AJAX
        $('#comp-cuotas-loading').show();
        $.get('cobro_compras.php', { accion: 'cuotas_ajax', id_compra: id }, function (data) {
            $('#comp-cuotas-loading').hide();
            if (!data.ok || !data.cuotas || data.cuotas.length === 0) {
                $('#pago-monto').val(saldo.toFixed(2)).removeAttr('readonly');
                $('#pago-monto-hint').text('Saldo pendiente: S/. ' + saldo.toFixed(2));
                return;
            }
            renderCuotasComp(data.cuotas, saldo);
        }, 'json').fail(function () {
            $('#comp-cuotas-loading').hide();
            $('#pago-monto').val(saldo.toFixed(2));
            $('#pago-monto-hint').text('Saldo pendiente: S/. ' + saldo.toFixed(2));
        });

        $('#modalPagoComp').modal('show');
    });

    // ── Renderizar cuotas en el modal de pago ─────────────────────────────────
    function renderCuotasComp(cuotas, saldo) {
        const activa = cuotas.find(c => c.estado === 'pendiente' || c.estado === 'vencido');

        if (!activa) {
            $('#comp-cuotas-lista').html(
                '<div class="alert alert-success border-0 mb-2" style="border-radius:8px;font-size:.85rem;">' +
                '<i class="fas fa-check-circle mr-2"></i>Todas las cuotas están pagadas.</div>'
            );
            $('#pago-monto').val('0.00').attr('readonly', true);
            return;
        }

        const montoCuota = parseFloat(activa.monto_cuota);
        $('#pago-monto').val(montoCuota.toFixed(2)).attr('readonly', true);
        $('#pago-monto').data('saldo', saldo);
        $('#pago-monto-hint').html(
            '<i class="fas fa-lock mr-1 text-warning"></i>' +
            'Monto fijado al valor de la cuota activa. Paga una cuota a la vez.'
        );

        let html = '<div style="margin-bottom:10px;">';
        html += '<div style="font-size:.78rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">' +
                '<i class="fas fa-calendar-alt mr-1"></i>Cronograma de cuotas</div>';

        cuotas.forEach(function (c) {
            const esPagada   = c.estado === 'pagado';
            const esActiva   = c.id_cuota === activa.id_cuota;
            const esVencida  = c.estado === 'vencido';

            const venc = c.fecha_vencimiento
                ? new Date(c.fecha_vencimiento + 'T00:00:00').toLocaleDateString('es-PE', { day:'2-digit', month:'2-digit', year:'numeric' })
                : '—';

            let rowStyle, iconHtml, badgeHtml, opacityStyle = '';

            if (esPagada) {
                rowStyle  = 'background:#f0fdf4;border:1px solid #bbf7d0;';
                iconHtml  = '<i class="fas fa-check-circle" style="color:#22c55e;font-size:1.1rem;"></i>';
                badgeHtml = '<span style="background:#dcfce7;color:#166534;font-size:.72rem;padding:2px 8px;border-radius:20px;font-weight:600;">PAGADA</span>';
            } else if (esActiva) {
                rowStyle  = 'background:#f5f3ff;border:2px solid #7c3aed;box-shadow:0 2px 8px rgba(124,58,237,.15);';
                iconHtml  = '<i class="fas fa-arrow-right" style="color:#7c3aed;font-size:1.1rem;"></i>';
                badgeHtml = '<span style="background:#ede9fe;color:#5b21b6;font-size:.72rem;padding:2px 8px;border-radius:20px;font-weight:700;">' +
                            (esVencida ? '⚠ VENCIDA — PAGAR AHORA' : '← PAGAR AHORA') + '</span>';
            } else {
                rowStyle     = 'background:#f8fafc;border:1px solid #e2e8f0;';
                opacityStyle = 'opacity:.45;';
                iconHtml     = '<i class="fas fa-lock" style="color:#94a3b8;font-size:1rem;"></i>';
                badgeHtml    = '<span style="background:#f1f5f9;color:#94a3b8;font-size:.72rem;padding:2px 8px;border-radius:20px;">BLOQUEADA</span>';
            }

            html += `<div style="${rowStyle}${opacityStyle}border-radius:8px;padding:10px 14px;margin-bottom:6px;display:flex;align-items:center;gap:12px;">
                <div style="flex-shrink:0;">${iconHtml}</div>
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:700;font-size:.88rem;color:#1e293b;">
                        Cuota ${c.numero_cuota} de ${cuotas.length}
                        <span style="margin-left:6px;">${badgeHtml}</span>
                    </div>
                    <div style="font-size:.78rem;color:#64748b;margin-top:2px;">
                        <i class="fas fa-calendar mr-1"></i>Vence: ${venc}
                    </div>
                </div>
                <div style="font-weight:700;font-size:1rem;color:${esPagada ? '#22c55e' : esActiva ? '#7c3aed' : '#94a3b8'};white-space:nowrap;">
                    S/. ${parseFloat(c.monto_cuota).toFixed(2)}
                </div>
            </div>`;
        });

        html += '</div>';
        $('#comp-cuotas-lista').html(html);
    }

    // ── Validar y enviar pago ─────────────────────────────────────────────────
    $('#formPagoComp').on('submit', function (e) {
        e.preventDefault();

        const monto     = parseFloat($('#pago-monto').val()) || 0;
        const saldo     = parseFloat($('#pago-monto').data('saldo')) || 0;
        const comp      = $('#pago-modal-comp').text();
        const proveedor = $('#pago-modal-proveedor').text();
        const metodo    = $('input[name="metodo_pago"]:checked').val();

        if (monto <= 0) {
            Swal.fire({ icon: 'warning', title: 'Monto inválido', text: 'Ingresa un monto mayor a 0.', confirmButtonColor: '#7c3aed' });
            return;
        }
        if (monto > saldo + 0.01) {
            Swal.fire({
                icon: 'warning',
                title: 'Monto excede el saldo',
                text: 'El monto (S/. ' + monto.toFixed(2) + ') supera el saldo pendiente (S/. ' + saldo.toFixed(2) + ').',
                confirmButtonColor: '#7c3aed'
            });
            return;
        }

        Swal.fire({
            icon: 'question',
            title: '¿Confirmar pago?',
            html: `<b>${comp}</b><br>Proveedor: <b>${proveedor}</b><br>Monto: <b>S/. ${monto.toFixed(2)}</b><br>Método: <b>${metodo.charAt(0).toUpperCase() + metodo.slice(1)}</b>`,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-check mr-1"></i>Sí, registrar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#7c3aed',
            cancelButtonColor: '#6c757d'
        }).then(result => {
            if (result.isConfirmed) this.submit();
        });
    });

    // ── Ver detalle de compra ─────────────────────────────────────────────────
    $(document).on('click', '.btn-ver-detalle-comp', function () {
        const id = $(this).data('id');
        $('#det-comp-num').text('COMP-' + String(id).padStart(6, '0'));
        $('#detalle-comp-body').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i><p class="mt-2 text-muted">Cargando...</p></div>');
        $('#modalDetalleComp').modal('show');

        $.get('cobro_compras.php', { accion: 'detalle_ajax', id_compra: id }, function (data) {
            if (!data.ok) {
                $('#detalle-comp-body').html('<div class="alert alert-danger m-3">' + (data.msg || 'Error al cargar.') + '</div>');
                return;
            }
            renderDetalleComp(data);
        }, 'json').fail(function () {
            $('#detalle-comp-body').html('<div class="alert alert-danger m-3">Error de conexión.</div>');
        });
    });

    // ── Renderizar detalle ────────────────────────────────────────────────────
    function renderDetalleComp(data) {
        const c   = data.compra;
        const det = data.detalle  || [];
        const cuotas = data.cuotas || [];
        const pag = data.pagos    || [];

        const total  = parseFloat(c.total) || 0;
        const saldo  = parseFloat(c.saldo_pendiente) || 0;
        const pagado = total - saldo;
        const pct    = total > 0 ? Math.min(100, Math.round((pagado / total) * 100)) : 0;
        const pctColor = pct >= 100 ? '#22c55e' : pct > 0 ? '#f59e0b' : '#ef4444';

        const badgeEstado = c.estado === 'pagado'
            ? `<span class="det-comp-pill det-comp-pill-ok"><i class="fas fa-check-circle mr-1"></i>Pagado</span>`
            : `<span class="det-comp-pill det-comp-pill-warn"><i class="fas fa-clock mr-1"></i>Pendiente</span>`;

        let html = '';

        // Banda de estado
        html += `<div class="det-comp-status-bar">
            <div class="det-comp-status-item">
                <span class="det-comp-status-lbl"><i class="fas fa-wallet mr-1"></i>Estado</span>
                <span class="det-comp-status-val">${badgeEstado}</span>
            </div>
            <div class="det-comp-status-sep"></div>
            <div class="det-comp-status-item">
                <span class="det-comp-status-lbl"><i class="fas fa-tag mr-1"></i>Total</span>
                <span class="det-comp-status-val det-comp-money">S/. ${total.toFixed(2)}</span>
            </div>
            <div class="det-comp-status-sep"></div>
            <div class="det-comp-status-item">
                <span class="det-comp-status-lbl"><i class="fas fa-coins mr-1"></i>Pagado</span>
                <span class="det-comp-status-val" style="color:#22c55e;font-weight:700;">S/. ${pagado.toFixed(2)}</span>
            </div>
            <div class="det-comp-status-sep"></div>
            <div class="det-comp-status-item">
                <span class="det-comp-status-lbl"><i class="fas fa-exclamation-circle mr-1"></i>Saldo</span>
                <span class="det-comp-status-val" style="color:${saldo > 0 ? '#ef4444' : '#22c55e'};font-weight:700;">S/. ${saldo.toFixed(2)}</span>
            </div>
        </div>`;

        // Barra de progreso
        html += `<div class="det-comp-progress-wrap">
            <div class="det-comp-progress-bg">
                <div class="det-comp-progress-fill" style="width:${pct}%;background:${pctColor};"></div>
            </div>
            <span class="det-comp-progress-label" style="color:${pctColor};">${pct}% pagado</span>
        </div>`;

        // Tarjetas proveedor / comprobante
        html += `<div class="det-comp-card-row">
            <div class="det-comp-card det-comp-card-prov">
                <div class="det-comp-card-icon" style="background:linear-gradient(135deg,#4c1d95,#7c3aed);">
                    <i class="fas fa-truck"></i>
                </div>
                <div>
                    <div class="det-comp-card-label">Proveedor</div>
                    <div class="det-comp-card-name">${esc(c.proveedor_nombre || '—')}</div>
                    <div class="det-comp-card-meta">
                        ${c.proveedor_ruc ? `<span><i class="fas fa-id-card"></i> RUC: <strong>${esc(c.proveedor_ruc)}</strong></span>` : ''}
                        ${c.proveedor_telefono ? `<span><i class="fas fa-phone"></i> ${esc(c.proveedor_telefono)}</span>` : ''}
                        ${c.proveedor_email ? `<span><i class="fas fa-envelope"></i> ${esc(c.proveedor_email)}</span>` : ''}
                    </div>
                </div>
            </div>
            <div class="det-comp-card">
                <div class="det-comp-card-icon" style="background:linear-gradient(135deg,#0f766e,#14b8a6);">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div>
                    <div class="det-comp-card-label">Comprobante</div>
                    <div class="det-comp-card-name">${esc(c.tipo_comprobante ? c.tipo_comprobante.charAt(0).toUpperCase() + c.tipo_comprobante.slice(1) : '—')}</div>
                    <div class="det-comp-card-meta">
                        <span><i class="fas fa-hashtag"></i> ${esc(c.numero_comprobante || '—')}</span>
                        <span><i class="fas fa-calendar"></i> ${formatFecha(c.fecha)}</span>
                    </div>
                </div>
            </div>
        </div>`;

        // Productos
        if (det.length > 0) {
            let subtotal = det.reduce((a, d) => a + parseFloat(d.subtotal || 0), 0);
            html += `<div class="det-comp-section">
                <div class="det-comp-section-hdr det-comp-hdr-purple">
                    <i class="fas fa-boxes"></i> Productos comprados
                    <span class="det-comp-section-count">${det.length}</span>
                </div>
                <div class="det-comp-items-list">`;
            det.forEach((d, i) => {
                html += `<div class="det-comp-item-row ${i % 2 === 0 ? 'det-comp-item-even' : ''}">
                    <div class="det-comp-item-icon"><i class="fas fa-box"></i></div>
                    <div class="det-comp-item-info">
                        <div class="det-comp-item-name">${esc(d.nombre_producto || '—')}</div>
                        ${d.codigo ? `<div class="det-comp-item-sub"><i class="fas fa-barcode mr-1"></i>${esc(d.codigo)}</div>` : ''}
                    </div>
                    <div class="det-comp-item-qty">× ${d.cantidad}</div>
                    <div class="det-comp-item-price">S/. ${parseFloat(d.precio_compra || 0).toFixed(2)}</div>
                    <div class="det-comp-item-subtotal">S/. ${parseFloat(d.subtotal || 0).toFixed(2)}</div>
                </div>`;
            });
            html += `</div>
                <div class="det-comp-subtotal-row"><span>Subtotal productos</span><span>S/. ${subtotal.toFixed(2)}</span></div>
            </div>`;
        }

        // Total final
        html += `<div class="det-comp-total-final">
            <div class="det-comp-total-left">
                <div class="det-comp-total-icon"><i class="fas fa-receipt"></i></div>
                <div>
                    <div class="det-comp-total-label">TOTAL COMPRA</div>
                    ${saldo > 0 ? `<div class="det-comp-total-sub">Saldo pendiente: S/. ${saldo.toFixed(2)}</div>` : ''}
                </div>
            </div>
            <div class="det-comp-total-amount">S/. ${total.toFixed(2)}</div>
        </div>`;

        // Cuotas
        if (cuotas.length > 0) {
            html += `<div class="det-comp-section">
                <div class="det-comp-section-hdr det-comp-hdr-purple">
                    <i class="fas fa-calendar-alt"></i> Cuotas programadas
                    <span class="det-comp-section-count">${cuotas.length}</span>
                </div>`;
            cuotas.forEach(cu => {
                const venc = cu.fecha_vencimiento ? new Date(cu.fecha_vencimiento + 'T00:00:00') : null;
                const vencida = venc && venc < new Date() && cu.estado !== 'pagado';
                const badgeCuota = cu.estado === 'pagado'
                    ? `<span class="det-comp-pill det-comp-pill-ok" style="font-size:.7rem;">Pagada</span>`
                    : vencida
                        ? `<span class="det-comp-pill det-comp-pill-red" style="font-size:.7rem;">Vencida</span>`
                        : `<span class="det-comp-pill det-comp-pill-warn" style="font-size:.7rem;">Pendiente</span>`;
                html += `<div class="det-comp-cuota-row">
                    <span class="det-comp-cuota-num">Cuota ${cu.numero_cuota}</span>
                    <div class="det-comp-cuota-info">
                        <i class="fas fa-calendar mr-1"></i>
                        Vence: ${venc ? venc.toLocaleDateString('es-PE') : '—'}
                    </div>
                    ${badgeCuota}
                    <div class="det-comp-cuota-monto">S/. ${parseFloat(cu.monto_cuota).toFixed(2)}</div>
                </div>`;
            });
            html += `</div>`;
        }

        // Pagos registrados
        if (pag.length > 0) {
            const totalPagado = pag.reduce((a, p) => a + parseFloat(p.monto || 0), 0);
            const iconMap = { efectivo: 'fas fa-money-bill-wave', transferencia: 'fas fa-university', yape: 'fas fa-mobile-alt', plin: 'fas fa-mobile-alt', tarjeta: 'fas fa-credit-card' };
            html += `<div class="det-comp-section">
                <div class="det-comp-section-hdr det-comp-hdr-teal">
                    <i class="fas fa-money-bill-wave"></i> Pagos registrados
                    <span class="det-comp-section-count">${pag.length}</span>
                    <span class="det-comp-section-total">Total: S/. ${totalPagado.toFixed(2)}</span>
                </div>
                <div style="padding:12px 16px;">`;
            pag.forEach(p => {
                html += `<div class="det-comp-pago-row">
                    <div class="det-comp-pago-metodo cobrocomp-metodo-${esc(p.metodo_pago)}">
                        <i class="${iconMap[p.metodo_pago] || 'fas fa-money-bill'}"></i>
                        ${p.metodo_pago.charAt(0).toUpperCase() + p.metodo_pago.slice(1)}
                    </div>
                    <div class="det-comp-pago-info">
                        <span class="det-comp-pago-user"><i class="fas fa-user-tie mr-1"></i>${esc(p.nombre_completo || '—')}</span>
                        <span class="det-comp-pago-fecha">${formatFecha(p.fecha)}</span>
                    </div>
                    <div class="det-comp-pago-monto">S/. ${parseFloat(p.monto).toFixed(2)}</div>
                </div>`;
            });
            html += `</div></div>`;
        }

        $('#detalle-comp-body').html(html);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    function esc(str) {
        if (!str) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function formatFecha(str) {
        if (!str) return '—';
        const d = new Date(str.replace(' ', 'T'));
        if (isNaN(d)) return str;
        return d.toLocaleDateString('es-PE', { day:'2-digit', month:'2-digit', year:'numeric' })
             + ' ' + d.toLocaleTimeString('es-PE', { hour:'2-digit', minute:'2-digit' });
    }
});
