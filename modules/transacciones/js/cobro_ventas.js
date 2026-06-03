/**
 * cobro_ventas.js — Cobro de Ventas a Crédito | SysInversiones 2026
 */
$(function () {

    // ── DataTables ────────────────────────────────────────────────────────────
    if ($('#tablaPendientesVta').length) {
        $('#tablaPendientesVta').DataTable({
            language: { url: 'https://cdn.datatables.net/plug-ins/1.10.25/i18n/Spanish.json' },
            order: [[5, 'asc']],
            pageLength: 25,
            columnDefs: [{ orderable: false, targets: [7] }]
        });
    }
    if ($('#tablaHistorialVta').length) {
        $('#tablaHistorialVta').DataTable({
            language: { url: 'https://cdn.datatables.net/plug-ins/1.10.25/i18n/Spanish.json' },
            order: [[6, 'desc']],
            pageLength: 25
        });
    }

    // ── Abrir modal ABONAR ────────────────────────────────────────────────────
    $(document).on('click', '.btn-abonar', function () {

        // Bloqueo: caja no aperturada
        if (!window.CAJA_ABIERTA) {
            Swal.fire({
                icon: 'warning',
                title: 'Caja no aperturada',
                html: 'No puedes registrar abonos sin una caja abierta.<br><br>' +
                      '<a href="../Caja/caja.php" class="btn btn-sm btn-warning font-weight-bold">' +
                      '<i class="fas fa-cash-register mr-1"></i>Ir al módulo de Caja</a>',
                confirmButtonColor: '#e67e22',
                confirmButtonText: 'Entendido'
            });
            return;
        }

        const id      = $(this).data('id');
        const venta   = $(this).data('venta');
        const cliente = $(this).data('cliente');
        const total   = parseFloat($(this).data('total')) || 0;
        const saldo   = parseFloat($(this).data('saldo')) || total;

        $('#abono-id-venta').val(id);
        $('#abono-modal-venta').text(venta);
        $('#abono-modal-cliente').text(cliente);
        $('#abono-modal-total').text('S/. ' + total.toFixed(2));
        $('#abono-modal-saldo').text('S/. ' + saldo.toFixed(2));
        $('#abono-monto').data('saldo', saldo);

        $('input[name="metodo_pago"][value="efectivo"]').prop('checked', true);
        $('input[name="observacion"]').val('');
        $('#abono-cuotas-lista').html('');

        // Cargar cuotas vía AJAX
        $('#abono-cuotas-loading').show();
        $.get('cobro_ventas.php', { accion: 'cuotas_ajax', id_venta: id }, function (data) {
            $('#abono-cuotas-loading').hide();
            if (!data.ok || !data.cuotas || data.cuotas.length === 0) {
                // Sin cuotas: comportamiento libre (pago libre del saldo)
                $('#abono-monto').val(saldo.toFixed(2)).removeAttr('readonly');
                $('#abono-monto-hint').text('Saldo pendiente: S/. ' + saldo.toFixed(2));
                actualizarVueltoVta();
                return;
            }

            renderCuotasAbono(data.cuotas, saldo);
        }, 'json').fail(function () {
            $('#abono-cuotas-loading').hide();
            $('#abono-monto').val(saldo.toFixed(2));
            $('#abono-monto-hint').text('Saldo pendiente: S/. ' + saldo.toFixed(2));
            actualizarVueltoVta();
        });

        $('#modalAbono').modal('show');
    });

    // ── Renderizar cuotas en el modal de abono ────────────────────────────────
    function renderCuotasAbono(cuotas, saldo) {
        // Encontrar la primera cuota pendiente (la que se debe pagar ahora)
        const activa = cuotas.find(c => c.estado === 'pendiente' || c.estado === 'vencido');

        if (!activa) {
            // Todas pagadas
            $('#abono-cuotas-lista').html(
                '<div class="alert alert-success border-0 mb-2" style="border-radius:8px;font-size:.85rem;">' +
                '<i class="fas fa-check-circle mr-2"></i>Todas las cuotas están pagadas.</div>'
            );
            $('#abono-monto').val('0.00').attr('readonly', true);
            return;
        }

        // Pre-rellenar monto con la cuota activa (bloqueado)
        const montoCuota = parseFloat(activa.monto_cuota);
        $('#abono-monto').val(montoCuota.toFixed(2)).attr('readonly', true);
        $('#abono-monto').data('saldo', saldo);
        $('#abono-monto-hint').html(
            '<i class="fas fa-lock mr-1 text-warning"></i>' +
            'Monto fijado al valor de la cuota activa. Paga una cuota a la vez.'
        );
        actualizarVueltoVta();

        // Construir lista de cuotas
        let html = '<div style="margin-bottom:12px;">';
        html += '<div style="font-size:.78rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">' +
                '<i class="fas fa-calendar-alt mr-1"></i>Cronograma de cuotas</div>';

        cuotas.forEach(function (c) {
            const esPagada  = c.estado === 'pagado';
            const esActiva  = c.id_cuota === activa.id_cuota;
            const esVencida = c.estado === 'vencido';
            const esBloqueada = !esPagada && !esActiva;

            const venc = c.fecha_vencimiento
                ? new Date(c.fecha_vencimiento + 'T00:00:00').toLocaleDateString('es-PE', { day:'2-digit', month:'2-digit', year:'numeric' })
                : '—';

            let rowStyle, iconHtml, badgeHtml, opacityStyle = '';

            if (esPagada) {
                rowStyle = 'background:#f0fdf4;border:1px solid #bbf7d0;';
                iconHtml = '<i class="fas fa-check-circle" style="color:#22c55e;font-size:1.1rem;"></i>';
                badgeHtml = '<span style="background:#dcfce7;color:#166534;font-size:.72rem;padding:2px 8px;border-radius:20px;font-weight:600;">PAGADA</span>';
            } else if (esActiva) {
                rowStyle = 'background:#eff6ff;border:2px solid #3b82f6;box-shadow:0 2px 8px rgba(59,130,246,.15);';
                iconHtml = '<i class="fas fa-arrow-right" style="color:#3b82f6;font-size:1.1rem;"></i>';
                badgeHtml = '<span style="background:#dbeafe;color:#1d4ed8;font-size:.72rem;padding:2px 8px;border-radius:20px;font-weight:700;">' +
                            (esVencida ? '⚠ VENCIDA — PAGAR AHORA' : '← PAGAR AHORA') + '</span>';
            } else {
                rowStyle = 'background:#f8fafc;border:1px solid #e2e8f0;';
                opacityStyle = 'opacity:.45;';
                iconHtml = '<i class="fas fa-lock" style="color:#94a3b8;font-size:1rem;"></i>';
                badgeHtml = '<span style="background:#f1f5f9;color:#94a3b8;font-size:.72rem;padding:2px 8px;border-radius:20px;">BLOQUEADA</span>';
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
                <div style="font-weight:700;font-size:1rem;color:${esPagada ? '#22c55e' : esActiva ? '#1d4ed8' : '#94a3b8'};white-space:nowrap;">
                    S/. ${parseFloat(c.monto_cuota).toFixed(2)}
                </div>
            </div>`;
        });

        html += '</div>';
        $('#abono-cuotas-lista').html(html);
    }

    // ── Cálculo de vuelto ─────────────────────────────────────────────────────
    function actualizarVueltoVta() {
        const metodo = $('input[name="metodo_pago"]:checked').val();
        const monto  = parseFloat($('#abono-monto').val()) || 0;
        const saldo  = parseFloat($('#abono-monto').data('saldo')) || 0;

        if (metodo === 'efectivo' && monto > saldo + 0.009) {
            $('#abono-vuelto-val').text('S/. ' + (monto - saldo).toFixed(2));
            $('#abono-vuelto-box').show();
        } else {
            $('#abono-vuelto-box').hide();
        }
    }

    $(document).on('change', 'input[name="metodo_pago"]', actualizarVueltoVta);
    $(document).on('input', '#abono-monto', actualizarVueltoVta);

    // ── Validar y enviar abono ────────────────────────────────────────────────
    $('#formAbono').on('submit', function (e) {
        e.preventDefault();

        const monto = parseFloat($('#abono-monto').val()) || 0;
        const saldo = parseFloat($('#abono-monto').data('saldo')) || 0;
        const venta = $('#abono-modal-venta').text();
        const metodo = $('input[name="metodo_pago"]:checked').val();

        if (monto <= 0) {
            Swal.fire({ icon: 'warning', title: 'Monto inválido', text: 'Ingresa un monto mayor a 0.', confirmButtonColor: '#1d4ed8' });
            return;
        }
        if (monto > saldo + 0.01) {
            Swal.fire({
                icon: 'warning',
                title: 'Monto excede el saldo',
                text: 'El monto (S/. ' + monto.toFixed(2) + ') supera el saldo pendiente (S/. ' + saldo.toFixed(2) + ').',
                confirmButtonColor: '#1d4ed8'
            });
            return;
        }

        Swal.fire({
            icon: 'question',
            title: '¿Confirmar abono?',
            html: `<b>${venta}</b><br>Monto: <b>S/. ${monto.toFixed(2)}</b><br>Método: <b>${metodo.charAt(0).toUpperCase() + metodo.slice(1)}</b>`,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-check mr-1"></i>Sí, registrar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#1d4ed8',
            cancelButtonColor: '#6c757d'
        }).then(result => {
            if (result.isConfirmed) this.submit();
        });
    });

    // ── Ver detalle de venta ──────────────────────────────────────────────────
    $(document).on('click', '.btn-ver-detalle-vta', function () {
        const id = $(this).data('id');
        $('#det-vta-num').text('VTA-' + String(id).padStart(6, '0'));
        $('#detalle-vta-body').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i><p class="mt-2 text-muted">Cargando...</p></div>');
        $('#modalDetalleVta').modal('show');

        $.get('cobro_ventas.php', { accion: 'detalle_ajax', id_venta: id }, function (data) {
            if (!data.ok) {
                $('#detalle-vta-body').html('<div class="alert alert-danger m-3">' + (data.msg || 'Error al cargar.') + '</div>');
                return;
            }
            renderDetalleVta(data);
        }, 'json').fail(function () {
            $('#detalle-vta-body').html('<div class="alert alert-danger m-3">Error de conexión.</div>');
        });
    });

    // ── Renderizar detalle ────────────────────────────────────────────────────
    function renderDetalleVta(data) {
        const v   = data.venta;
        const det = data.detalle  || [];
        const cuotas = data.cuotas || [];
        const pag = data.pagos    || [];

        const total = parseFloat(v.total) || 0;
        const saldo = parseFloat(v.saldo_pendiente) || 0;
        const pagado = total - saldo;
        const pct = total > 0 ? Math.min(100, Math.round((pagado / total) * 100)) : 0;
        const pctColor = pct >= 100 ? '#22c55e' : pct > 0 ? '#f59e0b' : '#ef4444';

        const badgeEstado = v.estado === 'pagado'
            ? `<span class="det-vta-pill det-vta-pill-ok"><i class="fas fa-check-circle mr-1"></i>Pagado</span>`
            : `<span class="det-vta-pill det-vta-pill-warn"><i class="fas fa-clock mr-1"></i>Pendiente</span>`;

        let html = '';

        // Banda de estado
        html += `<div class="det-vta-status-bar">
            <div class="det-vta-status-item">
                <span class="det-vta-status-lbl"><i class="fas fa-wallet mr-1"></i>Estado</span>
                <span class="det-vta-status-val">${badgeEstado}</span>
            </div>
            <div class="det-vta-status-sep"></div>
            <div class="det-vta-status-item">
                <span class="det-vta-status-lbl"><i class="fas fa-tag mr-1"></i>Total</span>
                <span class="det-vta-status-val det-vta-money">S/. ${total.toFixed(2)}</span>
            </div>
            <div class="det-vta-status-sep"></div>
            <div class="det-vta-status-item">
                <span class="det-vta-status-lbl"><i class="fas fa-coins mr-1"></i>Pagado</span>
                <span class="det-vta-status-val" style="color:#22c55e;font-weight:700;">S/. ${pagado.toFixed(2)}</span>
            </div>
            <div class="det-vta-status-sep"></div>
            <div class="det-vta-status-item">
                <span class="det-vta-status-lbl"><i class="fas fa-exclamation-circle mr-1"></i>Saldo</span>
                <span class="det-vta-status-val" style="color:${saldo > 0 ? '#ef4444' : '#22c55e'};font-weight:700;">S/. ${saldo.toFixed(2)}</span>
            </div>
        </div>`;

        // Barra de progreso
        html += `<div class="det-vta-progress-wrap">
            <div class="det-vta-progress-bg">
                <div class="det-vta-progress-fill" style="width:${pct}%;background:${pctColor};"></div>
            </div>
            <span class="det-vta-progress-label" style="color:${pctColor};">${pct}% cobrado</span>
        </div>`;

        // Tarjeta cliente
        html += `<div class="det-vta-card-row">
            <div class="det-vta-card det-vta-card-client">
                <div class="det-vta-card-icon" style="background:linear-gradient(135deg,#1e40af,#3b82f6);">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div>
                    <div class="det-vta-card-label">Cliente</div>
                    <div class="det-vta-card-name">${esc(v.cliente_nombre)}</div>
                    <div class="det-vta-card-meta">
                        <span><i class="fas fa-id-card"></i> ${esc(v.tipo_documento)}: <strong>${esc(v.documento_identidad)}</strong></span>
                        ${v.telefono ? `<span><i class="fas fa-phone"></i> ${esc(v.telefono)}</span>` : ''}
                    </div>
                </div>
            </div>
            <div class="det-vta-card">
                <div class="det-vta-card-icon" style="background:linear-gradient(135deg,#0f766e,#14b8a6);">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div>
                    <div class="det-vta-card-label">Comprobante</div>
                    <div class="det-vta-card-name">${esc(v.tipo_comprobante ? v.tipo_comprobante.charAt(0).toUpperCase() + v.tipo_comprobante.slice(1) : '—')}</div>
                    <div class="det-vta-card-meta">
                        <span><i class="fas fa-hashtag"></i> ${esc(v.numero_comprobante || '—')}</span>
                        <span><i class="fas fa-calendar"></i> ${formatFecha(v.fecha)}</span>
                    </div>
                </div>
            </div>
        </div>`;

        // Productos
        if (det.length > 0) {
            let subtotal = det.reduce((a, d) => a + parseFloat(d.subtotal || 0), 0);
            html += `<div class="det-vta-section">
                <div class="det-vta-section-hdr det-vta-hdr-blue">
                    <i class="fas fa-box"></i> Productos vendidos
                    <span class="det-vta-section-count">${det.length}</span>
                </div>
                <div class="det-vta-items-list">`;
            det.forEach((d, i) => {
                html += `<div class="det-vta-item-row ${i % 2 === 0 ? 'det-vta-item-even' : ''}">
                    <div class="det-vta-item-icon"><i class="fas fa-box-open"></i></div>
                    <div class="det-vta-item-info">
                        <div class="det-vta-item-name">${esc(d.nombre_producto || '—')}</div>
                        ${d.codigo ? `<div class="det-vta-item-sub"><i class="fas fa-barcode mr-1"></i>${esc(d.codigo)}</div>` : ''}
                    </div>
                    <div class="det-vta-item-qty">× ${d.cantidad}</div>
                    <div class="det-vta-item-price">S/. ${parseFloat(d.precio_unitario || 0).toFixed(2)}</div>
                    <div class="det-vta-item-subtotal">S/. ${parseFloat(d.subtotal || 0).toFixed(2)}</div>
                </div>`;
            });
            html += `</div>
                <div class="det-vta-subtotal-row"><span>Subtotal productos</span><span>S/. ${subtotal.toFixed(2)}</span></div>
            </div>`;
        }

        // Total final
        html += `<div class="det-vta-total-final">
            <div class="det-vta-total-left">
                <div class="det-vta-total-icon"><i class="fas fa-receipt"></i></div>
                <div>
                    <div class="det-vta-total-label">TOTAL VENTA</div>
                    ${saldo > 0 ? `<div class="det-vta-total-sub">Saldo pendiente: S/. ${saldo.toFixed(2)}</div>` : ''}
                </div>
            </div>
            <div class="det-vta-total-amount">S/. ${total.toFixed(2)}</div>
        </div>`;

        // Cuotas
        if (cuotas.length > 0) {
            html += `<div class="det-vta-section">
                <div class="det-vta-section-hdr det-vta-hdr-blue">
                    <i class="fas fa-calendar-alt"></i> Cuotas programadas
                    <span class="det-vta-section-count">${cuotas.length}</span>
                </div>`;
            cuotas.forEach(c => {
                const venc = c.fecha_vencimiento ? new Date(c.fecha_vencimiento + 'T00:00:00') : null;
                const vencida = venc && venc < new Date() && c.estado !== 'pagado';
                const badgeCuota = c.estado === 'pagado'
                    ? `<span class="det-vta-pill det-vta-pill-ok" style="font-size:.7rem;">Pagada</span>`
                    : vencida
                        ? `<span class="det-vta-pill det-vta-pill-red" style="font-size:.7rem;">Vencida</span>`
                        : `<span class="det-vta-pill det-vta-pill-warn" style="font-size:.7rem;">Pendiente</span>`;
                html += `<div class="det-vta-cuota-row">
                    <span class="det-vta-cuota-num">Cuota ${c.numero_cuota}</span>
                    <div class="det-vta-cuota-info">
                        <i class="fas fa-calendar mr-1"></i>
                        Vence: ${venc ? venc.toLocaleDateString('es-PE') : '—'}
                    </div>
                    ${badgeCuota}
                    <div class="det-vta-cuota-monto">S/. ${parseFloat(c.monto_cuota).toFixed(2)}</div>
                </div>`;
            });
            html += `</div>`;
        }

        // Pagos registrados
        if (pag.length > 0) {
            const totalPagado = pag.reduce((a, p) => a + parseFloat(p.monto || 0), 0);
            const iconMap = { efectivo: 'fas fa-money-bill-wave', yape: 'fas fa-mobile-alt', plin: 'fas fa-mobile-alt', transferencia: 'fas fa-university', tarjeta: 'fas fa-credit-card' };
            html += `<div class="det-vta-section">
                <div class="det-vta-section-hdr det-vta-hdr-teal">
                    <i class="fas fa-money-bill-wave"></i> Abonos registrados
                    <span class="det-vta-section-count">${pag.length}</span>
                    <span class="det-vta-section-total">Total: S/. ${totalPagado.toFixed(2)}</span>
                </div>
                <div style="padding:12px 16px;">`;
            pag.forEach(p => {
                html += `<div class="det-vta-pago-row">
                    <div class="det-vta-pago-metodo cobrovta-metodo-${esc(p.metodo_pago)}">
                        <i class="${iconMap[p.metodo_pago] || 'fas fa-money-bill'}"></i>
                        ${p.metodo_pago.charAt(0).toUpperCase() + p.metodo_pago.slice(1)}
                    </div>
                    <div class="det-vta-pago-info">
                        <span class="det-vta-pago-user"><i class="fas fa-user-tie mr-1"></i>${esc(p.nombre_completo || '—')}</span>
                        <span class="det-vta-pago-fecha">${formatFecha(p.fecha)}</span>
                    </div>
                    <div class="det-vta-pago-monto">S/. ${parseFloat(p.monto).toFixed(2)}</div>
                </div>`;
            });
            html += `</div></div>`;
        }

        $('#detalle-vta-body').html(html);
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
