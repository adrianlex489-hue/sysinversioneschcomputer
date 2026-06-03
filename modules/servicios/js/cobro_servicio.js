/**
 * cobro_servicio.js — Módulo Cobro de Servicios | SysInversiones 2026
 */
$(function () {

    // ── DataTables ────────────────────────────────────────────────────────────
    if ($('#tablaPendientes').length) {
        $('#tablaPendientes').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.10.25/i18n/Spanish.json'
            },
            order: [[6, 'asc']],
            pageLength: 25,
            columnDefs: [{ orderable: false, targets: [7] }]
        });
    }

    if ($('#tablaHistorial').length) {
        $('#tablaHistorial').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.10.25/i18n/Spanish.json'
            },
            order: [[6, 'desc']],
            pageLength: 25
        });
    }

    // ── Abrir modal COBRAR ────────────────────────────────────────────────────
    $(document).on('click', '.btn-cobrar', function () {

        // Bloqueo: caja no aperturada
        if (!window.CAJA_ABIERTA) {
            Swal.fire({
                icon: 'warning',
                title: 'Caja no aperturada',
                html: 'No puedes registrar cobros de servicio sin una caja abierta.<br><br>' +
                      '<a href="../Caja/caja.php" class="btn btn-sm btn-warning font-weight-bold">' +
                      '<i class="fas fa-cash-register mr-1"></i>Ir al módulo de Caja</a>',
                confirmButtonColor: '#e67e22',
                confirmButtonText: 'Entendido'
            });
            return;
        }

        const id      = $(this).data('id');
        const orden   = $(this).data('orden');
        const cliente = $(this).data('cliente');

        // Consultar el total real desde el servidor antes de abrir el modal
        $.get('cobro_servicio.php', { accion: 'detalle_ajax', id_orden: id }, function (data) {
            const costo = data.total_real !== undefined ? parseFloat(data.total_real) : (parseFloat(data.orden?.costo_total) || 0);
            const saldo = data.saldo_real !== undefined ? parseFloat(data.saldo_real) : costo;

            $('#cobro-id-orden').val(id);
            $('#cobro-modal-orden').text(orden);
            $('#cobro-modal-cliente').text(cliente);
            $('#cobro-modal-costo').text('S/. ' + costo.toFixed(2));
            $('#cobro-modal-saldo').text('S/. ' + saldo.toFixed(2));

            $('#cobro-monto').val(saldo.toFixed(2));
            $('#cobro-monto-hint').text('Saldo pendiente: S/. ' + saldo.toFixed(2));
            $('#cobro-monto').data('saldo', saldo);

            $('input[name="metodo_pago"][value="efectivo"]').prop('checked', true);
            $('input[name="observacion"]').val('');
            actualizarVuelto();

            $('#modalCobro').modal('show');
        }, 'json').fail(function () {
            // Fallback: usar los datos del botón si falla la consulta
            const costo = parseFloat($(`.btn-cobrar[data-id="${id}"]`).data('costo')) || 0;
            const saldo = parseFloat($(`.btn-cobrar[data-id="${id}"]`).data('saldo')) || costo;

            $('#cobro-id-orden').val(id);
            $('#cobro-modal-orden').text(orden);
            $('#cobro-modal-cliente').text(cliente);
            $('#cobro-modal-costo').text('S/. ' + costo.toFixed(2));
            $('#cobro-modal-saldo').text('S/. ' + saldo.toFixed(2));
            $('#cobro-monto').val(saldo.toFixed(2));
            $('#cobro-monto-hint').text('Saldo pendiente: S/. ' + saldo.toFixed(2));
            $('#cobro-monto').data('saldo', saldo);
            $('input[name="metodo_pago"][value="efectivo"]').prop('checked', true);
            $('input[name="observacion"]').val('');
            actualizarVuelto();
            $('#modalCobro').modal('show');
        });
    });

    // ── Cálculo de vuelto ─────────────────────────────────────────────────────
    function actualizarVuelto() {
        const metodo = $('input[name="metodo_pago"]:checked').val();
        const monto  = parseFloat($('#cobro-monto').val()) || 0;
        const saldo  = parseFloat($('#cobro-monto').data('saldo')) || 0;

        if (metodo === 'efectivo' && monto > 0) {
            const vuelto = monto - saldo;
            if (vuelto > 0) {
                $('#cobro-vuelto-val').text('S/. ' + vuelto.toFixed(2));
                $('#cobro-vuelto-box').show();
            } else {
                $('#cobro-vuelto-box').hide();
            }
        } else {
            $('#cobro-vuelto-box').hide();
        }
    }

    $(document).on('change', 'input[name="metodo_pago"]', actualizarVuelto);
    $(document).on('input', '#cobro-monto', actualizarVuelto);

    // ── Validar y enviar cobro ────────────────────────────────────────────────
    $('#formCobro').on('submit', function (e) {
        e.preventDefault();

        const monto = parseFloat($('#cobro-monto').val()) || 0;
        const saldo = parseFloat($('#cobro-monto').data('saldo')) || 0;

        if (monto <= 0) {
            Swal.fire({ icon: 'warning', title: 'Monto inválido', text: 'Ingresa un monto mayor a 0.', confirmButtonColor: '#0f766e' });
            return;
        }
        if (monto > saldo + 0.01) {
            Swal.fire({
                icon: 'warning',
                title: 'Monto excede el saldo',
                text: 'El monto ingresado (S/. ' + monto.toFixed(2) + ') supera el saldo pendiente (S/. ' + saldo.toFixed(2) + ').',
                confirmButtonColor: '#0f766e'
            });
            return;
        }

        const metodo = $('input[name="metodo_pago"]:checked').val();
        const orden  = $('#cobro-modal-orden').text();

        Swal.fire({
            icon: 'question',
            title: '¿Confirmar cobro?',
            html: `<b>${orden}</b><br>Monto: <b>S/. ${monto.toFixed(2)}</b><br>Método: <b>${metodo.charAt(0).toUpperCase() + metodo.slice(1)}</b>`,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-check mr-1"></i>Sí, cobrar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#0f766e',
            cancelButtonColor: '#6c757d'
        }).then(result => {
            if (result.isConfirmed) {
                this.submit();
            }
        });
    });

    // ── Botón "Sin cobro" ─────────────────────────────────────────────────────
    $('#btnSinCobro').on('click', function () {
        const id    = $('#cobro-id-orden').val();
        const orden = $('#cobro-modal-orden').text();

        Swal.fire({
            icon: 'question',
            title: '¿Marcar como sin cobro?',
            html: `La orden <b>${orden}</b> se marcará como garantía o servicio gratuito.<br>No se registrará ningún pago.`,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-gift mr-1"></i>Sí, sin cobro',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#6c757d',
            cancelButtonColor: '#0f766e'
        }).then(result => {
            if (result.isConfirmed) {
                // Enviar formulario de sin_cobro
                const form = $('<form method="POST"><input type="hidden" name="accion" value="marcar_sin_cobro"><input type="hidden" name="id_orden" value="' + id + '"></form>');
                $('body').append(form);
                form.submit();
            }
        });
    });

    // ── Ver detalle de orden ──────────────────────────────────────────────────
    $(document).on('click', '.btn-ver-detalle', function () {
        const id = $(this).data('id');
        $('#det-num-orden').text('ORD-' + String(id).padStart(6, '0'));
        $('#detalle-body').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i><p class="mt-2 text-muted">Cargando...</p></div>');
        $('#modalDetalle').modal('show');

        $.get('cobro_servicio.php', { accion: 'detalle_ajax', id_orden: id }, function (data) {
            if (!data.ok) {
                $('#detalle-body').html('<div class="alert alert-danger m-3">' + (data.msg || 'Error al cargar.') + '</div>');
                return;
            }
            // Actualizar data-saldo y data-costo del botón cobrar con el total real
            const totalReal = data.total_real !== undefined ? parseFloat(data.total_real) : 0;
            const saldoReal = data.saldo_real !== undefined ? parseFloat(data.saldo_real) : totalReal;
            $(`.btn-cobrar[data-id="${id}"]`)
                .data('costo', totalReal)
                .data('saldo', saldoReal);
            renderDetalle(data);
        }, 'json').fail(function () {
            $('#detalle-body').html('<div class="alert alert-danger m-3">Error de conexión.</div>');
        });
    });

    // ── Renderizar detalle ────────────────────────────────────────────────────
    function renderDetalle(data) {
        const o   = data.orden;
        const srv = data.servicios || [];
        const rep = data.repuestos || [];
        const pag = data.pagos     || [];

        const estadoPago = o.estado_pago || 'pendiente';

        // Usar total_real calculado en el servidor (servicios + cotizaciones no rechazadas)
        const costo = data.total_real  !== undefined ? parseFloat(data.total_real)  : (parseFloat(o.costo_total) || 0);
        const saldo = data.saldo_real  !== undefined ? parseFloat(data.saldo_real)  : (parseFloat(o.saldo_pendiente) || costo);

        // ── Badges ──
        const badgePago = {
            pendiente: `<span class="det-pill det-pill-warn"><i class="fas fa-clock mr-1"></i>Pendiente</span>`,
            pagado:    `<span class="det-pill det-pill-ok"><i class="fas fa-check-circle mr-1"></i>Pagado</span>`,
            sin_cobro: `<span class="det-pill det-pill-gray"><i class="fas fa-gift mr-1"></i>Sin cobro</span>`
        }[estadoPago] || '';

        const badgeEstado = {
            recibido:    `<span class="det-pill det-pill-blue">Recibido</span>`,
            diagnostico: `<span class="det-pill det-pill-amber">Diagnóstico</span>`,
            en_proceso:  `<span class="det-pill det-pill-orange">En Proceso</span>`,
            listo:       `<span class="det-pill det-pill-ok">Listo</span>`,
            entregado:   `<span class="det-pill det-pill-gray">Entregado</span>`,
            cancelado:   `<span class="det-pill det-pill-red">Cancelado</span>`
        }[o.estado] || `<span class="det-pill det-pill-gray">${esc(o.estado)}</span>`;

        // ── Porcentaje pagado ──
        const pct = costo > 0 ? Math.min(100, Math.round(((costo - saldo) / costo) * 100)) : 0;
        const pctColor = pct >= 100 ? '#22c55e' : pct > 0 ? '#f59e0b' : '#ef4444';

        let html = '';

        // ══ BANDA DE ESTADO ══
        html += `
        <div class="det-status-bar">
            <div class="det-status-item">
                <span class="det-status-lbl"><i class="fas fa-clipboard-check mr-1"></i>Estado orden</span>
                <span class="det-status-val">${badgeEstado}</span>
            </div>
            <div class="det-status-sep"></div>
            <div class="det-status-item">
                <span class="det-status-lbl"><i class="fas fa-wallet mr-1"></i>Estado pago</span>
                <span class="det-status-val">${badgePago}</span>
            </div>
            <div class="det-status-sep"></div>
            <div class="det-status-item">
                <span class="det-status-lbl"><i class="fas fa-tag mr-1"></i>Total</span>
                <span class="det-status-val det-status-money">${costo > 0 ? 'S/. ' + costo.toFixed(2) : '—'}</span>
            </div>
            <div class="det-status-sep"></div>
            <div class="det-status-item">
                <span class="det-status-lbl"><i class="fas fa-exclamation-circle mr-1"></i>Saldo</span>
                <span class="det-status-val" style="color:${saldo > 0 ? '#ef4444' : '#22c55e'};font-weight:700;">
                    ${saldo > 0 ? 'S/. ' + saldo.toFixed(2) : 'S/. 0.00'}
                </span>
            </div>
        </div>`;

        // ── Barra de progreso de pago ──
        if (costo > 0) {
            html += `
            <div class="det-progress-wrap">
                <div class="det-progress-bar-bg">
                    <div class="det-progress-bar-fill" style="width:${pct}%;background:${pctColor};"></div>
                </div>
                <span class="det-progress-label" style="color:${pctColor};">${pct}% cobrado</span>
            </div>`;
        }

        // ══ TARJETAS CLIENTE / EQUIPO ══
        html += `<div class="det-cards-row">`;

        // Tarjeta cliente
        html += `
        <div class="det-card det-card-client">
            <div class="det-card-icon" style="background:linear-gradient(135deg,#1e40af,#3b82f6);">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="det-card-body">
                <div class="det-card-label">Cliente</div>
                <div class="det-card-name">${esc(o.cliente_nombre)}</div>
                <div class="det-card-meta">
                    <span><i class="fas fa-id-card"></i> ${esc(o.tipo_documento)}: <strong>${esc(o.documento_identidad)}</strong></span>
                    ${o.telefono ? `<span><i class="fas fa-phone"></i> ${esc(o.telefono)}</span>` : ''}
                </div>
            </div>
        </div>`;

        // Tarjeta equipo
        html += `
        <div class="det-card det-card-device">
            <div class="det-card-icon" style="background:linear-gradient(135deg,#5b21b6,#8b5cf6);">
                <i class="fas fa-laptop"></i>
            </div>
            <div class="det-card-body">
                <div class="det-card-label">Equipo</div>
                <div class="det-card-name">${esc(o.equipo_tipo)} <span style="color:#5b21b6;">${esc(o.marca)}</span></div>
                <div class="det-card-meta">
                    <span><i class="fas fa-microchip"></i> ${esc(o.modelo || '—')}</span>
                    ${o.numero_serie ? `<span><i class="fas fa-barcode"></i> S/N: <strong>${esc(o.numero_serie)}</strong></span>` : ''}
                </div>
            </div>
        </div>`;

        html += `</div>`; // /det-cards-row

        // ══ SERVICIOS ══
        if (srv.length > 0) {
            let subtotalSrv = srv.reduce((a, s) => a + parseFloat(s.subtotal || 0), 0);
            html += `
            <div class="det-section">
                <div class="det-section-hdr det-section-hdr-green">
                    <i class="fas fa-tools"></i> Servicios realizados
                    <span class="det-section-count">${srv.length}</span>
                </div>
                <div class="det-items-list">`;
            srv.forEach((s, i) => {
                html += `
                <div class="det-item-row ${i % 2 === 0 ? 'det-item-even' : ''}">
                    <div class="det-item-icon"><i class="fas fa-wrench"></i></div>
                    <div class="det-item-info">
                        <div class="det-item-name">${esc(s.nombre_servicio)}</div>
                        ${s.descripcion && s.descripcion !== s.nombre_servicio ? `<div class="det-item-sub">${esc(s.descripcion)}</div>` : ''}
                    </div>
                    <div class="det-item-qty">× ${s.cantidad || 1}</div>
                    <div class="det-item-price">S/. ${parseFloat(s.precio || 0).toFixed(2)}</div>
                    <div class="det-item-subtotal">S/. ${parseFloat(s.subtotal || 0).toFixed(2)}</div>
                </div>`;
            });
            html += `
                </div>
                <div class="det-subtotal-row">
                    <span>Subtotal servicios</span>
                    <span>S/. ${subtotalSrv.toFixed(2)}</span>
                </div>
            </div>`;
        }

        // ══ COTIZACIONES (todos los estados) ══
        if (rep.length > 0) {
            // Solo sumar las que no están rechazadas para el subtotal
            let subtotalRep = rep.reduce((a, r) => {
                return r.estado_cot !== 'rechazado' ? a + parseFloat(r.subtotal || 0) : a;
            }, 0);

            const estadoCotBadge = {
                cotizado:        `<span style="background:#dbeafe;color:#1d4ed8;padding:2px 8px;border-radius:20px;font-size:.72rem;font-weight:600;">Cotizado</span>`,
                aprobado:        `<span style="background:#dcfce7;color:#166534;padding:2px 8px;border-radius:20px;font-size:.72rem;font-weight:600;">Aprobado</span>`,
                rechazado:       `<span style="background:#fee2e2;color:#991b1b;padding:2px 8px;border-radius:20px;font-size:.72rem;font-weight:600;">Rechazado</span>`,
                comprado:        `<span style="background:#e0f2fe;color:#0369a1;padding:2px 8px;border-radius:20px;font-size:.72rem;font-weight:600;">Comprado</span>`,
                pendiente_compra:`<span style="background:#fef9c3;color:#854d0e;padding:2px 8px;border-radius:20px;font-size:.72rem;font-weight:600;">Pend. compra</span>`,
                completado:      `<span style="background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:20px;font-size:.72rem;font-weight:600;">Completado</span>`
            };

            html += `
            <div class="det-section">
                <div class="det-section-hdr det-section-hdr-purple">
                    <i class="fas fa-cogs"></i> Cotizaciones de repuestos
                    <span class="det-section-count">${rep.length}</span>
                </div>
                <div class="det-items-list">`;
            rep.forEach((r, i) => {
                const nombre    = r.nombre_producto || r.nombre_producto_libre || 'Repuesto';
                const badge     = estadoCotBadge[r.estado_cot] || `<span style="background:#f1f5f9;color:#475569;padding:2px 8px;border-radius:20px;font-size:.72rem;">${esc(r.estado_cot)}</span>`;
                const rechazado = r.estado_cot === 'rechazado';
                html += `
                <div class="det-item-row ${i % 2 === 0 ? 'det-item-even' : ''}" style="${rechazado ? 'opacity:.55;' : ''}">
                    <div class="det-item-icon" style="background:#ede9fe;color:#5b21b6;"><i class="fas fa-microchip"></i></div>
                    <div class="det-item-info">
                        <div class="det-item-name" style="${rechazado ? 'text-decoration:line-through;' : ''}">${esc(nombre)}</div>
                        <div class="det-item-sub d-flex align-items-center gap-1" style="gap:6px;">
                            ${badge}
                            ${r.codigo ? `<span><i class="fas fa-barcode mr-1"></i>${esc(r.codigo)}</span>` : ''}
                            ${r.nota ? `<span class="text-muted"><i class="fas fa-comment-alt mr-1"></i>${esc(r.nota)}</span>` : ''}
                        </div>
                    </div>
                    <div class="det-item-qty">× ${r.cantidad}</div>
                    <div class="det-item-price">S/. ${parseFloat(r.precio_unitario || 0).toFixed(2)}</div>
                    <div class="det-item-subtotal" style="${rechazado ? 'text-decoration:line-through;color:#9ca3af;' : ''}">S/. ${parseFloat(r.subtotal || 0).toFixed(2)}</div>
                </div>`;
            });
            html += `
                </div>
                <div class="det-subtotal-row">
                    <span>Subtotal cotizaciones <small class="text-muted">(excl. rechazadas)</small></span>
                    <span>S/. ${subtotalRep.toFixed(2)}</span>
                </div>
            </div>`;
        }

        // ══ TOTAL FINAL ══
        if (costo > 0) {
            const totalSrv = data.total_servicios !== undefined ? parseFloat(data.total_servicios) : 0;
            const totalCot = data.total_cotizaciones !== undefined ? parseFloat(data.total_cotizaciones) : 0;
            html += `
            <div class="det-total-final">
                <div class="det-total-left">
                    <div class="det-total-icon"><i class="fas fa-receipt"></i></div>
                    <div>
                        <div class="det-total-label">TOTAL A COBRAR</div>
                        ${totalSrv > 0 && totalCot > 0 ? `<div class="det-total-sub">Servicios: S/. ${totalSrv.toFixed(2)} + Repuestos: S/. ${totalCot.toFixed(2)}</div>` : ''}
                        ${saldo > 0 && saldo < costo ? `<div class="det-total-sub" style="color:#ef4444;">Saldo pendiente: S/. ${saldo.toFixed(2)}</div>` : ''}
                    </div>
                </div>
                <div class="det-total-amount">S/. ${costo.toFixed(2)}</div>
            </div>`;
        }

        // ══ PAGOS REGISTRADOS ══
        if (pag.length > 0) {
            const totalPagado = pag.reduce((a, p) => a + parseFloat(p.monto || 0), 0);
            const metodoIconMap = {
                efectivo: 'fas fa-money-bill-wave', yape: 'fas fa-mobile-alt',
                transferencia: 'fas fa-university'
            };
            html += `
            <div class="det-section">
                <div class="det-section-hdr det-section-hdr-teal">
                    <i class="fas fa-money-bill-wave"></i> Pagos registrados
                    <span class="det-section-count">${pag.length}</span>
                    <span class="det-section-total-pag">Total: S/. ${totalPagado.toFixed(2)}</span>
                </div>
                <div style="padding:12px 16px;">`;
            pag.forEach(p => {
                const icon = metodoIconMap[p.metodo_pago] || 'fas fa-money-bill';
                html += `
                <div class="det-pago-row">
                    <div class="det-pago-metodo cobro-metodo-${esc(p.metodo_pago)}">
                        <i class="${icon}"></i> ${p.metodo_pago.charAt(0).toUpperCase() + p.metodo_pago.slice(1)}
                    </div>
                    <div class="det-pago-info">
                        <span class="det-pago-user"><i class="fas fa-user-tie mr-1"></i>${esc(p.nombre_completo || '—')}</span>
                        <span class="det-pago-fecha">${formatFecha(p.fecha)}</span>
                    </div>
                    <div class="det-pago-monto">S/. ${parseFloat(p.monto).toFixed(2)}</div>
                </div>`;
            });
            html += `</div></div>`;
        }

        $('#detalle-body').html(html);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    function esc(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatFecha(str) {
        if (!str) return '—';
        const d = new Date(str.replace(' ', 'T'));
        if (isNaN(d)) return str;
        return d.toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric' })
            + ' ' + d.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' });
    }

});
