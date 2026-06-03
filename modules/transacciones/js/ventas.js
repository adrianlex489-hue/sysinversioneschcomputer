/**
 * ventas.js — Módulo Registro de Ventas | SysInversiones 2026
 * Contado y crédito (max 4 cuotas) — sin lotes
 */
$(function () {

    var venta = {
        id_cliente:    null,
        tipo_cliente:  'natural',
        nombre_cliente: '',
        items:         [],
        aplica_igv:    true,
        metodo_pago:   'efectivo',
        tipo_pago:     'contado',
        num_cuotas:    1
    };

    // Fix modales anidados Bootstrap 4
    $(document).on('hidden.bs.modal', '.modal', function () {
        if ($('.modal.show').length) $('body').addClass('modal-open');
    });

    // ── Numeración automática ────────────────────────────────────────────────
    var prefijos = { ticket:'T001', nota:'N001' };

    function cargarSiguienteNumero(tipo) {
        $.get('ventas.php', { accion: 'siguiente_numero', tipo: tipo }, function (data) {
            try {
                var res = typeof data === 'string' ? JSON.parse(data) : data;
                var num = res.numero || (prefijos[tipo] + '-00000001');
                var partes = num.split('-');
                var serie  = partes.slice(0, -1).join('-');
                $('#prefijo_comprobante_vta').text(serie);
                $('#numero_comprobante_vta').val(num);
            } catch (e) {
                $('#numero_comprobante_vta').val((prefijos[tipo] || 'T001') + '-00000001');
            }
        });
    }

    $('#tipo_comprobante_vta').on('change', function () { cargarSiguienteNumero($(this).val()); });
    cargarSiguienteNumero($('#tipo_comprobante_vta').val());

    // ── Tipo de pago ─────────────────────────────────────────────────────────
    $(document).on('click', '.btn-tipo-pago-vta', function () {
        $('.btn-tipo-pago-vta').removeClass('activo');
        $(this).addClass('activo');
        venta.tipo_pago = $(this).data('tipo');
        $('#hidden_tipo_pago_vta').val(venta.tipo_pago);
        if (venta.tipo_pago === 'credito') {
            $('#panelCreditoVta').slideDown(200);
            $('#bloqueMetodoPagoVta').slideUp(200);
            $('#resumenCuotaVta').show();
            actualizarPreviewCuotas();
        } else {
            $('#panelCreditoVta').slideUp(200);
            $('#bloqueMetodoPagoVta').slideDown(200);
            $('#resumenCuotaVta').hide();
        }
    });

    // ── Método de pago ───────────────────────────────────────────────────────
    $(document).on('click', '.btn-metodo-vta', function () {
        $('.btn-metodo-vta').removeClass('activo');
        $(this).addClass('activo');
        venta.metodo_pago = $(this).data('metodo');
        $('#hidden_metodo_pago_vta').val(venta.metodo_pago);
    });

    // ── Selector de cuotas ───────────────────────────────────────────────────
    $(document).on('click', '.btn-cuota-vta', function () {
        $('.btn-cuota-vta').removeClass('activo');
        $(this).addClass('activo');
        venta.num_cuotas = parseInt($(this).data('cuotas'));
        $('#hidden_num_cuotas_vta').val(venta.num_cuotas);
        actualizarPreviewCuotas();
    });

    // ── Preview cuotas ───────────────────────────────────────────────────────
    function actualizarPreviewCuotas() {
        var total      = parseFloat($('#hidden_total_vta').val()) || 0;
        var fechaStr   = $('#fecha_primera_cuota_vta').val();
        var frecuencia = parseInt($('#frecuencia_dias_vta').val()) || 30;
        var n          = venta.num_cuotas;

        if (!fechaStr || total <= 0) {
            $('#previewCuotasVta').hide();
            $('#resumenCuotaTextoVta').text('Completa el total y la fecha de la primera cuota.');
            return;
        }

        var montoCuota = Math.round((total / n) * 100) / 100;
        var diferencia = Math.round((total - montoCuota * n) * 100) / 100;
        var fecha      = new Date(fechaStr + 'T00:00:00');
        var meses      = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

        var html = '<div style="background:#fff3e0;border-radius:8px;padding:12px;border:1px solid #ffe0b2;">';
        html += '<div style="font-size:.8rem;font-weight:700;color:#e67e22;margin-bottom:8px;"><i class="fas fa-calendar-alt mr-1"></i>CRONOGRAMA DE CUOTAS</div>';
        html += '<table style="width:100%;font-size:.82rem;"><thead><tr style="color:#999;"><th style="padding:3px 6px;">Cuota</th><th style="padding:3px 6px;text-align:right;">Monto</th><th style="padding:3px 6px;text-align:center;">Vencimiento</th></tr></thead><tbody>';
        for (var i = 1; i <= n; i++) {
            var montoEsta = (i === n) ? Math.round((montoCuota + diferencia) * 100) / 100 : montoCuota;
            html += '<tr style="border-top:1px solid #ffe0b2;">';
            html += '<td style="padding:4px 6px;font-weight:700;color:#e67e22;">' + i + ' / ' + n + '</td>';
            html += '<td style="padding:4px 6px;text-align:right;font-weight:700;">S/. ' + montoEsta.toFixed(2) + '</td>';
            html += '<td style="padding:4px 6px;text-align:center;">' + fecha.getDate() + ' ' + meses[fecha.getMonth()] + ' ' + fecha.getFullYear() + '</td>';
            html += '</tr>';
            fecha.setDate(fecha.getDate() + frecuencia);
        }
        html += '</tbody></table></div>';
        $('#previewCuotasVta').html(html).show();
        $('#resumenCuotaTextoVta').text(n + ' cuota(s) de S/. ' + montoCuota.toFixed(2) + ' c/u');
    }

    $('#fecha_primera_cuota_vta, #frecuencia_dias_vta').on('change', actualizarPreviewCuotas);

    // ── Alerta caja no aperturada ────────────────────────────────────────────
    function alertSinCajaVta() {
        Swal.fire({
            background: '#111827',
            padding: 0,
            width: 400,
            showConfirmButton: false,
            allowOutsideClick: true,
            customClass: { popup: 'swal-caja-popup' },
            didOpen: function () {
                // Inyectar estilos si no existen aún
                if (!document.getElementById('swal-caja-styles')) {
                    var s = document.createElement('style');
                    s.id = 'swal-caja-styles';
                    s.textContent = [
                        '.swal-caja-popup{padding:0!important;border-radius:18px!important;overflow:hidden!important;box-shadow:0 25px 60px rgba(0,0,0,.6)!important;}',
                        '.swal-caja-popup .swal2-html-container{margin:0!important;padding:0!important;overflow:hidden!important;}',
                        '.swal2-container{backdrop-filter:blur(3px)!important;}',
                        '.swal-caja-header{position:relative;background:linear-gradient(160deg,#1a0a00 0%,#3d1a00 60%,#1a0a00 100%);padding:36px 28px 28px;text-align:center;overflow:hidden;}',
                        '.swal-caja-grid{position:absolute;inset:0;background-image:linear-gradient(rgba(230,126,34,.09) 1px,transparent 1px),linear-gradient(90deg,rgba(230,126,34,.09) 1px,transparent 1px);background-size:28px 28px;pointer-events:none;}',
                        '.swal-caja-ring{width:80px;height:80px;border-radius:50%;border:2px solid rgba(230,126,34,.55);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;animation:cajaPulse 2s ease-in-out infinite;}',
                        '.swal-caja-inner{width:56px;height:56px;border-radius:50%;background:rgba(230,126,34,.15);border:1.5px solid rgba(230,126,34,.4);display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#e67e22;}',
                        '@keyframes cajaPulse{0%,100%{box-shadow:0 0 0 0 rgba(230,126,34,.35);}50%{box-shadow:0 0 0 12px rgba(230,126,34,0);}}',
                        '.swal-caja-sup{font-size:.68rem;font-weight:700;letter-spacing:2.5px;color:#e67e22;text-transform:uppercase;margin-bottom:6px;}',
                        '.swal-caja-ttl{font-size:1.55rem;font-weight:800;color:#fff;margin-bottom:12px;letter-spacing:-.3px;}',
                        '.swal-caja-badge{display:inline-block;background:rgba(230,126,34,.18);border:1px solid rgba(230,126,34,.35);color:#f0a050;font-size:.78rem;font-weight:600;padding:4px 14px;border-radius:20px;}',
                        '.swal-caja-body{background:#111827;padding:20px 20px 24px;display:flex;flex-direction:column;gap:12px;}',
                        '.swal-caja-ibox{display:flex;align-items:flex-start;gap:12px;background:rgba(230,126,34,.08);border:1px solid rgba(230,126,34,.3);border-left:4px solid #e67e22;border-radius:10px;padding:14px;}',
                        '.swal-caja-iico{width:36px;height:36px;min-width:36px;border-radius:8px;background:rgba(230,126,34,.2);display:flex;align-items:center;justify-content:center;color:#e67e22;font-size:1rem;}',
                        '.swal-caja-itit{font-size:.7rem;font-weight:700;letter-spacing:1.5px;color:#e67e22;text-transform:uppercase;margin-bottom:4px;}',
                        '.swal-caja-itxt{font-size:.84rem;color:#ccc;line-height:1.5;margin:0;}',
                        '.swal-caja-dbox{display:flex;align-items:center;gap:12px;background:rgba(26,82,118,.2);border:1px solid rgba(26,82,118,.4);border-radius:10px;padding:12px 14px;}',
                        '.swal-caja-dico{width:36px;height:36px;min-width:36px;border-radius:8px;background:rgba(26,82,118,.4);display:flex;align-items:center;justify-content:center;color:#7fb3d3;font-size:1rem;}',
                        '.swal-caja-dtxt{font-size:.82rem;color:#aaa;line-height:1.4;margin:0;}',
                        '.swal-caja-dtxt strong{color:#e0e0e0;}',
                        '.swal-caja-btn{display:block;text-align:center;background:linear-gradient(135deg,#1a5276,#2980b9);color:#fff!important;font-weight:700;font-size:.95rem;padding:13px;border-radius:10px;text-decoration:none!important;margin-top:4px;transition:opacity .2s;}',
                        '.swal-caja-btn:hover{opacity:.88;}'
                    ].join('');
                    document.head.appendChild(s);
                }
            },
            html:
                '<div class="swal-caja-header">' +
                    '<div class="swal-caja-grid"></div>' +
                    '<div class="swal-caja-ring">' +
                        '<div class="swal-caja-inner"><i class="fas fa-cash-register"></i></div>' +
                    '</div>' +
                    '<div class="swal-caja-sup">OPERACIÓN BLOQUEADA</div>' +
                    '<div class="swal-caja-ttl">Caja no Aperturada</div>' +
                    '<div class="swal-caja-badge"><i class="fas fa-lock" style="margin-right:5px;"></i>ventas</div>' +
                '</div>' +
                '<div class="swal-caja-body">' +
                    '<div class="swal-caja-ibox">' +
                        '<div class="swal-caja-iico"><i class="fas fa-exclamation-triangle"></i></div>' +
                        '<div>' +
                            '<div class="swal-caja-itit">SIN CAJA ABIERTA</div>' +
                            '<p class="swal-caja-itxt">No puedes registrar ventas sin aperturar una caja. Ve al módulo de Caja e inicia una sesión para continuar.</p>' +
                        '</div>' +
                    '</div>' +
                    '<div class="swal-caja-dbox">' +
                        '<div class="swal-caja-dico"><i class="fas fa-store"></i></div>' +
                        '<p class="swal-caja-dtxt">Módulo: <strong>Ventas</strong> — Acción requerida: <strong>Aperturar Caja</strong></p>' +
                    '</div>' +
                    '<a href="../Caja/caja.php" class="swal-caja-btn">' +
                        '<i class="fas fa-arrow-left" style="margin-right:8px;"></i>Ir al módulo de Caja' +
                    '</a>' +
                '</div>'
        });
    }

    // Overlay sin caja: captura cualquier clic sobre el formulario deshabilitado
    $('#overlaySinCajaVta').on('click', function () { alertSinCajaVta(); });

    // ── Selector cliente ─────────────────────────────────────────────────────
    $('#btnSeleccionarClienteVta, #campoClienteVta').on('click', function () {
        if (typeof hayCajaVta !== 'undefined' && !hayCajaVta) { alertSinCajaVta(); return; }
        $('#buscarClienteVta').val('');
        $('#listaClientesVta .item-selector-vta').show();
        $('#contClientesVta').text($('#listaClientesVta .item-selector-vta').length);
        $('#sinResultadosCliVta').hide();
        $('#modalSelectorClienteVta').modal('show');
        $('#modalSelectorClienteVta').one('shown.bs.modal', function () { $('#buscarClienteVta').focus(); });
    });

    $('#buscarClienteVta').on('input', function () {
        var q = $.trim($(this).val()).toLowerCase();
        var vis = 0;
        $('#listaClientesVta .item-selector-vta').each(function () {
            var nombre = (this.getAttribute('data-nombre') || '').toLowerCase();
            var doc    = (this.getAttribute('data-doc')    || '').toLowerCase();
            var match  = !q || nombre.indexOf(q) > -1 || doc.indexOf(q) > -1;
            this.style.display = match ? '' : 'none';
            if (match) vis++;
        });
        $('#contClientesVta').text(vis);
        $('#sinResultadosCliVta').toggle(vis === 0);
    });

    $(document).on('click', '#listaClientesVta .item-selector-vta', function () {
        venta.id_cliente    = $(this).data('id');
        venta.tipo_cliente  = $(this).data('tipo-cliente') || 'natural';
        venta.nombre_cliente = $(this).data('nombre');
        $('#id_cliente_hidden').val(venta.id_cliente);
        $('#tipo_cliente_hidden').val(venta.tipo_cliente);
        $('#campoClienteVta').addClass('seleccionado')
            .find('.selected-text').text(venta.nombre_cliente).show()
            .end().find('.placeholder-text').hide();
        $('#modalSelectorClienteVta').modal('hide');
        Swal.fire({ icon: 'success', title: 'Cliente seleccionado', html: '<b>' + venta.nombre_cliente + '</b>',
            toast: true, position: 'top-end', timer: 2000, timerProgressBar: true,
            showConfirmButton: false, background: '#eafaf1', color: '#1a7a4a', iconColor: '#27ae60' });
    });

    $('#btnLimpiarClienteVta').on('click', function (e) {
        e.stopPropagation();
        venta.id_cliente = null; venta.nombre_cliente = ''; venta.tipo_cliente = 'natural';
        $('#id_cliente_hidden').val('');
        $('#tipo_cliente_hidden').val('natural');
        $('#campoClienteVta').removeClass('seleccionado')
            .find('.selected-text').text('').hide()
            .end().find('.placeholder-text').show();
    });

    // ── Búsqueda DNI ─────────────────────────────────────────────────────────
    function seleccionarClienteEnModal(id, nombre, guardado, tipoCliente) {
        venta.id_cliente    = id;
        venta.tipo_cliente  = tipoCliente || 'natural';
        venta.nombre_cliente = nombre;
        $('#id_cliente_hidden').val(id);
        $('#tipo_cliente_hidden').val(venta.tipo_cliente);
        $('#campoClienteVta').addClass('seleccionado')
            .find('.selected-text').text(nombre).show()
            .end().find('.placeholder-text').hide();
        $('#modalNuevoClienteDniVta').modal('hide');
        $('#modalSelectorClienteVta').modal('hide');
        Swal.fire({ icon: 'success',
            title: guardado ? 'Cliente guardado y seleccionado' : 'Cliente seleccionado',
            html: '<b>' + nombre + '</b>',
            toast: true, position: 'top-end', timer: 3000, timerProgressBar: true,
            showConfirmButton: false, background: '#eafaf1', color: '#1a7a4a', iconColor: '#27ae60' });
    }

    $('#btnNuevoClienteDniVta').on('click', function () {
        $('#inputDniModalVta').val('');
        $('#dniModalEstadoVta').hide().empty();
        $('#dniModalResultadoVta').hide();
        $('#modalNuevoClienteDniVta').modal('show');
        $('#modalNuevoClienteDniVta').one('shown.bs.modal', function () { $('#inputDniModalVta').focus(); });
    });

    function ejecutarBusquedaDni() {
        var dni = $.trim($('#inputDniModalVta').val());
        if (!/^\d{8}$/.test(dni)) {
            $('#dniModalEstadoVta').html('<div class="alert alert-warning py-2 mb-3" style="border-radius:8px;font-size:.85rem;"><i class="fas fa-exclamation-triangle mr-2"></i>El DNI debe tener exactamente 8 dígitos.</div>').show();
            return;
        }
        var $btn = $('#btnBuscarDniModalVta');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Consultando...');
        $('#dniModalEstadoVta').html('<div class="text-center py-3"><i class="fas fa-spinner fa-spin fa-2x mb-2 d-block" style="color:#1a5276;"></i><small class="text-muted">Consultando RENIEC...</small></div>').show();
        $('#dniModalResultadoVta').hide();

        $.post('ajax_buscar_dni.php', { dni: dni, guardar: 0 }, function (res) {
            $btn.prop('disabled', false).html('<i class="fas fa-search mr-1"></i>Buscar');
            $('#dniModalEstadoVta').hide().empty();
            if (!res.success) {
                $('#dniModalEstadoVta').html('<div class="alert alert-danger py-2 mb-3" style="border-radius:8px;font-size:.85rem;"><i class="fas fa-times-circle mr-2"></i>' + res.error + '</div>').show();
                return;
            }
            var d = res.datos; var yaExiste = res.ya_existe;
            $('#dniNombreCompletoVta').text(d.nombre_completo);
            $('#dniNumeroVta').text(d.dni);
            if (d.direccion) { $('#dniDireccionVta').text(d.direccion); $('#dniDireccionRowVta').show(); } else { $('#dniDireccionRowVta').hide(); }

            if (yaExiste) {
                $('#dniBadgeVta').html('<span style="background:#d4edda;color:#155724;padding:2px 10px;border-radius:20px;font-size:.72rem;font-weight:700;"><i class="fas fa-check-circle mr-1"></i>Ya registrado</span>');
                $('#dniOpcionesVta').html('<button type="button" class="btn btn-block font-weight-bold" id="btnDniUsarVta" style="background:#1a5276;color:#fff;border-radius:8px;padding:10px;"><i class="fas fa-check mr-2"></i>Seleccionar este cliente</button>');
                $('#btnDniUsarVta').on('click', function () { seleccionarClienteEnModal(d.id_cliente, d.nombre_completo, false, res.tipo_cliente || 'natural'); });
            } else {
                $('#dniBadgeVta').html('<span style="background:#fff3cd;color:#856404;padding:2px 10px;border-radius:20px;font-size:.72rem;font-weight:700;"><i class="fas fa-user-plus mr-1"></i>Nuevo cliente</span>');
                $('#dniOpcionesVta').html(
                    '<div class="row"><div class="col-6 pr-1">' +
                    '<button type="button" class="btn btn-outline-primary btn-block font-weight-bold" id="btnDniUsarSinVta" style="border-radius:8px;padding:10px;font-size:.85rem;"><i class="fas fa-bolt mr-1"></i>Usar sin guardar</button></div>' +
                    '<div class="col-6 pl-1">' +
                    '<button type="button" class="btn btn-block font-weight-bold" id="btnDniGuardarUsarVta" style="background:#1a5276;color:#fff;border-radius:8px;padding:10px;font-size:.85rem;"><i class="fas fa-save mr-1"></i>Guardar y usar</button></div></div>'
                );
                $('#btnDniUsarSinVta').on('click', function () { seleccionarClienteEnModal(0, d.nombre_completo, false, res.tipo_cliente || 'natural'); });
                $('#btnDniGuardarUsarVta').on('click', function () {
                    var $b = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Guardando...');
                    $.post('ajax_buscar_dni.php', { dni: d.dni, guardar: 1 }, function (res2) {
                        if (res2.success) {
                            seleccionarClienteEnModal(res2.id_cliente || 0, d.nombre_completo, true, res2.tipo_cliente || 'natural');
                            if (res2.id_cliente) {
                                var nuevoItem = '<div class="item-selector-vta" data-id="' + res2.id_cliente + '" data-tipo-cliente="' + (res2.tipo_cliente || 'natural') + '" data-nombre="' + d.nombre_completo + '" data-doc="' + d.dni + '">' +
                                    '<div class="d-flex align-items-center" style="gap:12px;"><div style="width:38px;height:38px;background:#e3f2fd;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-user" style="color:#1a5276;font-size:.85rem;"></i></div>' +
                                    '<div><div class="item-nombre">' + d.nombre_completo + '</div><div class="item-sub"><i class="fas fa-id-card mr-1"></i>DNI: ' + d.dni + '</div></div></div></div>';
                                $('#listaClientesVta').prepend(nuevoItem);
                                $('#contClientesVta').text(parseInt($('#contClientesVta').text()) + 1);
                            }
                        } else {
                            $b.prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Guardar y usar');
                            Swal.fire({ icon: 'error', title: 'Error al guardar', text: res2.error, confirmButtonColor: '#1a5276' });
                        }
                    }, 'json').fail(function () { $b.prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Guardar y usar'); });
                });
            }
            $('#dniModalResultadoVta').show();
        }, 'json').fail(function () {
            $btn.prop('disabled', false).html('<i class="fas fa-search mr-1"></i>Buscar');
            $('#dniModalEstadoVta').html('<div class="alert alert-danger py-2 mb-3" style="border-radius:8px;font-size:.85rem;"><i class="fas fa-wifi mr-2"></i>Error de conexión.</div>').show();
        });
    }

    $('#btnBuscarDniModalVta').on('click', ejecutarBusquedaDni);
    $('#inputDniModalVta').on('keypress', function (e) { if (e.which === 13) ejecutarBusquedaDni(); });
    $('#inputDniModalVta').on('input', function () { this.value = this.value.replace(/[^0-9]/g, ''); });
    $('#modalNuevoClienteDniVta').on('hidden.bs.modal', function () {
        $('#inputDniModalVta').val(''); $('#dniModalEstadoVta').hide().empty(); $('#dniModalResultadoVta').hide();
    });

    // ── Selector producto ────────────────────────────────────────────────────
    $('#btnAgregarProductoVta').on('click', function () {
        if (typeof hayCajaVta !== 'undefined' && !hayCajaVta) { alertSinCajaVta(); return; }
        $('#buscarProductoVta').val('');
        $('#listaProductosVta .item-selector-vta').show();
        $('#contProductosVta').text($('#listaProductosVta .item-selector-vta').length);
        $('#sinResultadosProdVta').hide();
        $('#modalSelectorProductoVta').modal('show');
        $('#modalSelectorProductoVta').one('shown.bs.modal', function () { $('#buscarProductoVta').focus(); });
    });

    $('#buscarProductoVta').on('input', function () {
        var q = $.trim($(this).val()).toLowerCase();
        var vis = 0;
        $('#listaProductosVta .item-selector-vta').each(function () {
            var nombre = (this.getAttribute('data-nombre') || '').toLowerCase();
            var codigo = (this.getAttribute('data-codigo') || '').toLowerCase();
            var cat    = (this.getAttribute('data-cat')    || '').toLowerCase();
            var match  = !q || nombre.indexOf(q) > -1 || codigo.indexOf(q) > -1 || cat.indexOf(q) > -1;
            this.style.display = match ? '' : 'none';
            if (match) vis++;
        });
        $('#contProductosVta').text(vis);
        $('#sinResultadosProdVta').toggle(vis === 0);
    });

    $(document).on('click', '#listaProductosVta .item-selector-vta', function () {
        var d = $(this).data();
        if (venta.items.some(function (it) { return it.id_producto === d.id; })) {
            Swal.fire({ icon: 'info', title: 'Ya agregado', text: 'Este producto ya está en la lista.', timer: 2000, showConfirmButton: false });
            return;
        }
        if (parseInt(d.stock) <= 0) {
            Swal.fire({ icon: 'warning', title: 'Sin stock', text: 'Este producto no tiene stock disponible.', confirmButtonColor: '#1a5276' });
            return;
        }
        $('#modal_item_id_producto_vta').val(d.id);
        $('#modal_item_nombre_vta').text(d.nombre || '');
        $('#modal_item_codigo_vta').text(d.codigo || '');
        $('#modal_item_precio_vta').val(parseFloat(d.precio || 0).toFixed(2));
        $('#modal_item_cantidad_vta').val(1).attr('max', parseInt(d.stock) || 1);
        $('#modal_item_descuento_vta').val('0.00');
        // Descuento: solo admin puede editar
        if (typeof esAdminVta !== 'undefined' && esAdminVta) {
            $('#modal_item_descuento_vta').prop('disabled', false).css('background','');
            $('#btnDesbloquearDescVta').hide();
        } else {
            $('#modal_item_descuento_vta').prop('disabled', true).css('background','#f8f9fa');
            $('#btnDesbloquearDescVta').show();
        }
        $('#modal_item_stock_disp_vta').text(d.stock + ' uds disponibles');
        calcularSubtotalItemVta();
        $('#modalSelectorProductoVta').modal('hide');
        setTimeout(function () {
            $('#modalDetalleItemVta').modal('show');
            $('#modalDetalleItemVta').one('shown.bs.modal', function () { $('#modal_item_cantidad_vta').focus().select(); });
        }, 350);
    });

    // ── Cálculo subtotal item ────────────────────────────────────────────────
    function calcularSubtotalItemVta() {
        var precio    = parseFloat($('#modal_item_precio_vta').val())    || 0;
        var cantidad  = parseInt($('#modal_item_cantidad_vta').val())    || 0;
        var descuento = parseFloat($('#modal_item_descuento_vta').val()) || 0;
        var maxDesc   = Math.max(0, precio * cantidad);
        if (descuento < 0) { descuento = 0; $('#modal_item_descuento_vta').val('0.00'); }
        var subtotal = Math.max(0, maxDesc - Math.min(descuento, maxDesc));
        $('#modal_item_subtotal_vta').text('S/. ' + subtotal.toFixed(2));
        return { precio: precio, cantidad: cantidad, descuento: descuento, subtotal: subtotal };
    }
    $('#modal_item_cantidad_vta, #modal_item_descuento_vta').on('input', function () { calcularSubtotalItemVta(); });

    // Validar cantidad en tiempo real — no superar stock
    $('#modal_item_cantidad_vta').on('input change', function () {
        var max = parseInt($(this).attr('max')) || 0;
        var val = parseInt($(this).val()) || 0;
        if (max > 0 && val > max) {
            $(this).val(max);
            calcularSubtotalItemVta();
            Swal.fire({
                icon: 'warning',
                title: 'Stock insuficiente',
                html: 'La cantidad máxima disponible es <strong style="color:#e74c3c;">' + max + ' uds</strong>.',
                toast: true, position: 'top-end',
                timer: 2500, timerProgressBar: true,
                showConfirmButton: false,
                background: '#fff8f0', color: '#856404', iconColor: '#e67e22'
            });
        }
    });

    // ── Botón desbloquear descuento (no-admin) — Ventas ──────────────────────
    $(document).on('click', '#btnDesbloquearDescVta', function () {
        pedirClaveAdmin(function () {
            $('#modal_item_descuento_vta').prop('disabled', false).css('background','').focus();
            $('#btnDesbloquearDescVta').hide();
        });
    });

    $('#modal_item_descuento_vta').on('change blur', function () {
        var $campo   = $(this);
        var precio   = parseFloat($('#modal_item_precio_vta').val())   || 0;
        var cantidad = parseInt($('#modal_item_cantidad_vta').val())   || 0;
        var maxDesc  = precio * cantidad;
        var val      = parseFloat($campo.val()) || 0;
        if (val < 0) { $campo.val('0.00'); calcularSubtotalItemVta(); return; }
        if (maxDesc > 0 && val >= maxDesc) {
            var maximo = (maxDesc - 0.01).toFixed(2);
            Swal.fire({
                icon: 'warning', title: 'Descuento inválido',
                html: 'El descuento <strong style="color:#e74c3c;">S/. ' + val.toFixed(2) + '</strong> es igual o mayor al subtotal.<br>' +
                      '<small>Máximo permitido: <strong>S/. ' + maximo + '</strong></small>',
                showCancelButton: true, showDenyButton: true,
                confirmButtonColor: '#1a5276', denyButtonColor: '#e67e22', cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-edit mr-1"></i> Corregir',
                denyButtonText: '<i class="fas fa-check mr-1"></i> Ajustar al máximo',
                cancelButtonText: '<i class="fas fa-times mr-1"></i> Cancelar'
            }).then(function (result) {
                if (result.isDenied) { $campo.val(maximo); calcularSubtotalItemVta(); }
                else if (result.isDismissed && result.dismiss === Swal.DismissReason.cancel) { $campo.val('0.00'); calcularSubtotalItemVta(); }
                else { $campo.val('').focus(); }
            });
        }
    });

    // ── Confirmar item ───────────────────────────────────────────────────────
    $(document).on('click', '#btnConfirmarItemVta', function () {
        var id       = parseInt($('#modal_item_id_producto_vta').val());
        var nombre   = $('#modal_item_nombre_vta').text();
        var codigo   = $('#modal_item_codigo_vta').text();
        var calc     = calcularSubtotalItemVta();
        var stockMax = parseInt($('#modal_item_cantidad_vta').attr('max')) || 0;

        if (!id || isNaN(id)) { Swal.fire({ icon: 'warning', title: 'Error', text: 'No se pudo identificar el producto.', confirmButtonColor: '#1a5276' }); return; }
        if (calc.cantidad <= 0) { Swal.fire({ icon: 'warning', title: 'Cantidad inválida', text: 'Ingresa una cantidad mayor a 0.', confirmButtonColor: '#1a5276' }); return; }
        if (calc.precio <= 0)   { Swal.fire({ icon: 'warning', title: 'Precio inválido', text: 'El precio de venta debe ser mayor a 0.', confirmButtonColor: '#1a5276' }); return; }

        // Validación de stock
        if (stockMax > 0 && calc.cantidad > stockMax) {
            Swal.fire({
                icon: 'warning',
                title: 'Stock insuficiente',
                html:
                    'Solo hay <strong style="color:#e74c3c;">' + stockMax + ' unidades</strong> disponibles de:<br>' +
                    '<span style="font-size:.9rem;color:#555;">' + nombre + '</span><br><br>' +
                    'Ingresaste <strong>' + calc.cantidad + '</strong> uds. Ajusta la cantidad.',
                confirmButtonColor: '#1a5276',
                confirmButtonText: '<i class="fas fa-edit mr-1"></i> Corregir cantidad'
            }).then(function () {
                $('#modal_item_cantidad_vta').val(stockMax).focus().select();
                calcularSubtotalItemVta();
            });
            return;
        }

        venta.items.push({ id_producto: id, nombre: nombre, codigo: codigo, precio_unitario: calc.precio, cantidad: calc.cantidad, descuento: calc.descuento, subtotal: calc.subtotal });
        renderTablaDetalleVta();
        actualizarTotalesVta();
        $('#modalDetalleItemVta').modal('hide');
        Swal.fire({
            icon: 'success',
            title: 'Producto agregado',
            html: '<b>' + nombre + '</b>',
            toast: true, position: 'top-end',
            timer: 2000, timerProgressBar: true,
            showConfirmButton: false,
            background: '#eafaf1', color: '#1a7a4a', iconColor: '#27ae60'
        });
    });

    // ── Tabla detalle ────────────────────────────────────────────────────────
    function renderTablaDetalleVta() {
        var $tbody = $('#tablaDetalleVta tbody');
        $tbody.empty();
        if (venta.items.length === 0) {
            $tbody.html('<tr class="fila-vacia-vta"><td colspan="7"><i class="fas fa-shopping-cart"></i>Sin productos agregados.<br><small>Haz clic en "Agregar Producto" para comenzar.</small></td></tr>');
            return;
        }
        $.each(venta.items, function (i, item) {
            var $tr = $('<tr></tr>').html(
                '<td class="text-center"><div class="num-fila-vta">' + (i + 1) + '</div></td>' +
                '<td><div style="font-weight:700;font-size:.88rem;">' + item.nombre + '</div>' +
                '<div style="font-size:.75rem;color:#999;">' + (item.codigo || '') + '</div></td>' +
                '<td class="text-center"><input type="number" class="form-control form-control-sm text-center input-cant-vta" value="' + item.cantidad + '" min="1" style="width:70px;margin:auto;" data-idx="' + i + '" disabled readonly title="La cantidad no es editable desde aquí"></td>' +
                '<td class="text-right"><input type="number" step="0.01" class="form-control form-control-sm text-right input-precio-vta" value="' + item.precio_unitario.toFixed(2) + '" min="0.01" style="width:90px;margin-left:auto;" data-idx="' + i + '" disabled readonly title="El precio no es editable desde aquí"></td>' +
                '<td class="text-right"><input type="number" step="0.01" class="form-control form-control-sm text-right input-desc-vta" value="' + item.descuento.toFixed(2) + '" min="0" style="width:80px;margin-left:auto;" data-idx="' + i + '" disabled readonly title="El descuento no es editable desde aquí"></td>' +
                '<td class="text-right font-weight-bold text-success subtotal-item-vta">S/. ' + item.subtotal.toFixed(2) + '</td>' +
                '<td class="text-center"><button type="button" class="btn-quitar-item-vta btn-quitar-vta" data-idx="' + i + '" title="Quitar"><i class="fas fa-times"></i></button></td>'
            );
            $tbody.append($tr);
        });
        $('#inputsOcultosVta').empty();
        $.each(venta.items, function (i, item) {
            $('#inputsOcultosVta').append(
                '<input type="hidden" name="items[' + i + '][id_producto]" value="' + item.id_producto + '">' +
                '<input type="hidden" name="items[' + i + '][cantidad]" value="' + item.cantidad + '">' +
                '<input type="hidden" name="items[' + i + '][precio_unitario]" value="' + item.precio_unitario + '">' +
                '<input type="hidden" name="items[' + i + '][descuento]" value="' + item.descuento + '">' +
                '<input type="hidden" name="items[' + i + '][subtotal]" value="' + item.subtotal + '">'
            );
        });
    }

    $(document).on('input', '.input-desc-vta', function () {
        var idx  = parseInt($(this).data('idx'));
        var item = venta.items[idx];
        if (!item) return;
        item.descuento = parseFloat($(this).val()) || 0;
        item.subtotal  = Math.max(0, (item.precio_unitario * item.cantidad) - item.descuento);
        $(this).closest('tr').find('.subtotal-item-vta').text('S/. ' + item.subtotal.toFixed(2));
        $('input[name="items[' + idx + '][descuento]"]').val(item.descuento);
        $('input[name="items[' + idx + '][subtotal]"]').val(item.subtotal);
        actualizarTotalesVta();
    });

    $(document).on('click', '.btn-quitar-vta', function () {
        venta.items.splice(parseInt($(this).data('idx')), 1);
        renderTablaDetalleVta();
        actualizarTotalesVta();
    });

    // ── Totales ──────────────────────────────────────────────────────────────
    function actualizarTotalesVta() {
        var subtotal   = venta.items.reduce(function (s, it) { return s + it.subtotal; }, 0);
        var descGlobal = parseFloat($('#descuento_global_vta').val()) || 0;
        var base       = Math.max(0, subtotal - descGlobal);
        var igv        = venta.aplica_igv ? Math.round(base * 0.18 * 100) / 100 : 0;
        var total      = Math.round((base + igv) * 100) / 100;

        $('#resumen_subtotal_vta').text('S/. ' + subtotal.toFixed(2));
        $('#resumen_igv_vta').text('S/. ' + igv.toFixed(2));
        $('#resumen_total_vta').text('S/. ' + total.toFixed(2));

        if (descGlobal > 0) {
            $('#resumen_descuento_vta').text('- S/. ' + descGlobal.toFixed(2));
            $('#filaDescGlobalVta').show();
        } else {
            $('#filaDescGlobalVta').hide();
        }

        $('#hidden_subtotal_vta').val(subtotal.toFixed(2));
        $('#hidden_igv_vta').val(igv.toFixed(2));
        $('#hidden_total_vta').val(total.toFixed(2));

        if (venta.tipo_pago === 'credito') actualizarPreviewCuotas();
    }

    $('#descuento_global_vta').on('input', actualizarTotalesVta);

    $('#switchIgvVta').on('change', function () {
        venta.aplica_igv = $(this).is(':checked');
        $('#filaIgvVta').toggle(venta.aplica_igv);
        actualizarTotalesVta();
    });

    // ── Limpiar formulario ───────────────────────────────────────────────────
    $('#btnLimpiarFormVta').on('click', function () {
        Swal.fire({
            icon: 'question', title: '¿Limpiar formulario?',
            text: 'Se borrarán todos los datos ingresados.',
            showCancelButton: true,
            confirmButtonColor: '#e74c3c', cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-eraser mr-1"></i> Sí, limpiar',
            cancelButtonText: 'Cancelar'
        }).then(function (r) {
            if (!r.isConfirmed) return;
            venta.id_cliente = null; venta.nombre_cliente = ''; venta.items = [];
            venta.tipo_pago = 'contado'; venta.metodo_pago = 'efectivo'; venta.num_cuotas = 1;
            venta.tipo_cliente = 'natural';
            $('#id_cliente_hidden').val('');
            $('#tipo_cliente_hidden').val('natural');
            $('#campoClienteVta').removeClass('seleccionado').find('.selected-text').text('').hide().end().find('.placeholder-text').show();
            $('#descuento_global_vta').val('0.00');
            $('#hidden_tipo_pago_vta').val('contado');
            $('#hidden_metodo_pago_vta').val('efectivo');
            $('#hidden_num_cuotas_vta').val('1');
            $('.btn-tipo-pago-vta').removeClass('activo'); $('.btn-tipo-pago-vta[data-tipo=contado]').addClass('activo');
            $('.btn-metodo-vta').removeClass('activo'); $('.btn-metodo-vta[data-metodo=efectivo]').addClass('activo');
            $('.btn-cuota-vta').removeClass('activo'); $('.btn-cuota-vta[data-cuotas=1]').addClass('activo');
            $('#panelCreditoVta').hide(); $('#bloqueMetodoPagoVta').show(); $('#resumenCuotaVta').hide();
            renderTablaDetalleVta();
            actualizarTotalesVta();
            cargarSiguienteNumero($('#tipo_comprobante_vta').val());
        });
    });

    // ── Submit con confirmación ──────────────────────────────────────────────
    $('#formNuevaVenta').on('submit', function (e) {
        e.preventDefault();
        if (!venta.id_cliente) {
            Swal.fire({ icon: 'warning', title: 'Cliente requerido', text: 'Selecciona un cliente antes de continuar.', confirmButtonColor: '#1a5276' }); return;
        }
        if (venta.items.length === 0) {
            Swal.fire({ icon: 'warning', title: 'Sin productos', text: 'Agrega al menos un producto.', confirmButtonColor: '#1a5276' }); return;
        }
        var total = parseFloat($('#hidden_total_vta').val()) || 0;
        if (total <= 0) {
            Swal.fire({ icon: 'warning', title: 'Total inválido', text: 'El total debe ser mayor a 0.', confirmButtonColor: '#1a5276' }); return;
        }
        var tipoPago = $('#hidden_tipo_pago_vta').val();
        var html = 'Se registrará la venta por <strong>S/. ' + total.toFixed(2) + '</strong><br>' +
                   '<small style="color:#6c757d;">Tipo de pago: ' + tipoPago.toUpperCase() + '</small>';
        Swal.fire({
            icon: 'question', title: '¿Registrar venta?', html: html,
            showCancelButton: true,
            confirmButtonColor: '#1a5276', cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-save mr-1"></i> Sí, registrar',
            cancelButtonText: 'Cancelar'
        }).then(function (r) {
            if (r.isConfirmed) {
                var $btn = $('#btnRegistrarVenta');
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Registrando...');
                document.getElementById('formNuevaVenta').submit();
            }
        });
    });

    // ── Modal Ver Venta ──────────────────────────────────────────────────────
    $(document).on('click', '.btn-ver-venta', function () {
        var id = $(this).data('id');
        $('#cuerpoModalVerVenta').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x" style="color:#1a5276;"></i></div>');
        $('#footerModalVerVenta').empty();
        $('#modalVerVenta').modal('show');
        $.get('ventas.php', { accion: 'detalle_ajax', id_venta: id }, function (html) {
            $('#cuerpoModalVerVenta').html(html);
            // Detectar tipo de comprobante desde el data-attribute inyectado en el HTML del detalle
            var tipo = $('#cuerpoModalVerVenta').find('[data-tipo-comprobante]').data('tipo-comprobante') || 'ticket';
            var urlComprobante = '/sysinversioneschcomputer/modules/Comprobantes/imprimir.php?tipo=venta&id=' + id;
            var labelBtn = tipo === 'ticket' ? 'Imprimir Ticket'
                         : tipo === 'nota'   ? 'Imprimir Nota de Venta'
                         : 'Imprimir Comprobante';
            $('#footerModalVerVenta').html(
                '<a href="' + urlComprobante + '" target="_blank" class="btn btn-sm font-weight-bold" style="background:#1a5276;color:#fff;border-radius:8px;"><i class="fas fa-print mr-1"></i>' + labelBtn + '</a>' +
                '<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cerrar</button>'
            );
        });
    });

    // ── Modal Pagar Venta ────────────────────────────────────────────────────
    $(document).on('click', '.btn-pagar-venta', function () {
        var id     = $(this).data('id');
        var info   = $(this).data('info') || 'Venta #' + id;
        var saldo  = $(this).data('saldo') || '0.00';
        $('#pago_id_venta').val(id);
        $('#pago_info_venta').text(info);
        $('#pago_saldo_venta').text('S/. ' + parseFloat(saldo).toFixed(2));
        $('#pago_monto_venta').val('').attr('max', parseFloat(saldo));
        $('.btn-metodo-pago-vta').removeClass('activo'); $('.btn-metodo-pago-vta[data-metodo=efectivo]').addClass('activo');
        $('#pago_metodo_venta').val('efectivo');
        $('#modalPagarVenta').modal('show');
        $('#modalPagarVenta').one('shown.bs.modal', function () { $('#pago_monto_venta').focus(); });
    });

    $(document).on('click', '.btn-metodo-pago-vta', function () {
        $('.btn-metodo-pago-vta').removeClass('activo');
        $(this).addClass('activo');
        $('#pago_metodo_venta').val($(this).data('metodo'));
    });

    // ── Anular venta ─────────────────────────────────────────────────────────
    $(document).on('click', '.btn-anular-venta', function () {
        var id = $(this).data('id');
        Swal.fire({
            icon: 'warning', title: '¿Anular venta #' + id + '?',
            html: 'Esta acción revertirá el stock de los productos.<br><small style="color:#e74c3c;">No se puede deshacer.</small>',
            showCancelButton: true,
            confirmButtonColor: '#e74c3c', cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-ban mr-1"></i> Sí, anular',
            cancelButtonText: 'Cancelar'
        }).then(function (r) {
            if (r.isConfirmed) {
                var $form = $('<form method="POST"><input type="hidden" name="accion" value="anular"><input type="hidden" name="id_venta" value="' + id + '"></form>');
                $('body').append($form); $form.submit();
            }
        });
    });

    // Inicializar
    actualizarTotalesVta();

    // ── Helper: pedir contraseña de administrador ─────────────────────────────
    var _adminAuthCallbackVta  = null;
    var _adminAuthOriginalHtml = null;  // guarda el HTML original del modal-content

    function pedirClaveAdmin(onSuccess) {
        _adminAuthCallbackVta = onSuccess;

        // Restaurar HTML original si fue reemplazado por el estado de éxito
        if (_adminAuthOriginalHtml && !$('#adminAuthPassVta').length) {
            $('#modalAdminAuthVta .modal-content').html(_adminAuthOriginalHtml);
            _adminAuthOriginalHtml = null;
            // Re-enlazar eventos tras restaurar
            _bindAdminAuthEventsVta();
        }

        $('#adminAuthPassVta').val('');
        $('#adminAuthErrVta').hide();
        $('#adminAuthConfirmVta').prop('disabled', false).html('<i class="fas fa-unlock mr-1"></i>Verificar acceso');
        $('#modalAdminAuthVta').modal('show');
        $('#modalAdminAuthVta').one('shown.bs.modal', function () {
            $('#adminAuthPassVta').focus();
        });
    }

    function _bindAdminAuthEventsVta() {
        // Ojo
        $('#adminAuthEyeVta').off('click').on('click', function () {
            var $inp = $('#adminAuthPassVta');
            $inp.attr('type', $inp.attr('type') === 'password' ? 'text' : 'password');
            $(this).find('i').toggleClass('fa-eye fa-eye-slash');
        });
        // Cancelar
        $('#adminAuthCancelVta').off('click').on('click', function () {
            $('#modalAdminAuthVta').modal('hide');
            _adminAuthCallbackVta = null;
        });
        // Enter
        $('#adminAuthPassVta').off('keydown').on('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); $('#adminAuthConfirmVta').trigger('click'); }
        });
        // Confirmar
        $('#adminAuthConfirmVta').off('click').on('click', function () {
            var clave = $('#adminAuthPassVta').val().trim();
            if (!clave) {
                $('#adminAuthErrTxtVta').text('Ingresa la contraseña del administrador.');
                $('#adminAuthErrVta').show();
                $('#adminAuthPassVta').focus();
                return;
            }
            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Verificando...');
            $('#adminAuthErrVta').hide();

            $.ajax({
                url: '/sysinversioneschcomputer/conf/ajax_verificar_admin.php',
                method: 'POST',
                data: { clave: clave },
                dataType: 'json',
                success: function (data) {
                    if (data.ok) {
                        // Guardar HTML original antes de reemplazar
                        _adminAuthOriginalHtml = $('#modalAdminAuthVta .modal-content').html();

                        // Mostrar estado de éxito
                        $('#modalAdminAuthVta .modal-content').html(
                            '<div style="background:linear-gradient(160deg,#001a0a 0%,#003d1a 55%,#001a0a 100%);padding:40px 24px 36px;text-align:center;position:relative;overflow:hidden;border-radius:18px;">' +
                              '<div style="position:absolute;inset:0;background-image:linear-gradient(rgba(39,174,96,.07) 1px,transparent 1px),linear-gradient(90deg,rgba(39,174,96,.07) 1px,transparent 1px);background-size:26px 26px;pointer-events:none;"></div>' +
                              '<div style="width:80px;height:80px;border-radius:50%;border:2px solid rgba(39,174,96,.5);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;background:rgba(39,174,96,.12);">' +
                                '<div style="width:56px;height:56px;border-radius:50%;background:rgba(39,174,96,.18);border:1.5px solid rgba(39,174,96,.4);display:flex;align-items:center;justify-content:center;">' +
                                  '<i class="fas fa-unlock" style="font-size:1.5rem;color:#2ecc71;"></i>' +
                                '</div>' +
                              '</div>' +
                              '<div style="font-size:.65rem;letter-spacing:3px;text-transform:uppercase;color:rgba(255,255,255,.4);margin-bottom:6px;">Acceso concedido</div>' +
                              '<div style="font-size:1.3rem;font-weight:800;color:#fff;letter-spacing:-.2px;margin-bottom:10px;">¡Autorización exitosa!</div>' +
                              '<div style="display:inline-flex;align-items:center;gap:7px;background:rgba(39,174,96,.18);border:1px solid rgba(39,174,96,.4);border-radius:20px;padding:5px 16px;">' +
                                '<i class="fas fa-check-circle" style="color:#2ecc71;font-size:.8rem;"></i>' +
                                '<span style="font-size:.78rem;color:#2ecc71;font-weight:600;">Descuento desbloqueado</span>' +
                              '</div>' +
                              '<div style="margin-top:18px;font-size:.82rem;color:rgba(255,255,255,.5);">Cerrando automáticamente...</div>' +
                            '</div>'
                        );

                        var cb = _adminAuthCallbackVta;
                        _adminAuthCallbackVta = null;

                        setTimeout(function () {
                            // Cerrar SOLO el modal de auth sin afectar el modal de detalle
                            $('#modalAdminAuthVta').modal('hide');
                            // Ejecutar callback después de que el modal termine de cerrarse
                            $('#modalAdminAuthVta').one('hidden.bs.modal', function () {
                                if (typeof cb === 'function') { cb(); }
                                // Restaurar HTML original para la próxima vez
                                if (_adminAuthOriginalHtml) {
                                    $('#modalAdminAuthVta .modal-content').html(_adminAuthOriginalHtml);
                                    _adminAuthOriginalHtml = null;
                                    _bindAdminAuthEventsVta();
                                }
                            });
                        }, 1500);

                    } else {
                        $('#adminAuthErrTxtVta').text(data.msg || 'Contraseña incorrecta. Intenta nuevamente.');
                        $('#adminAuthErrVta').show();
                        $('#adminAuthPassVta').val('').focus();
                        $btn.prop('disabled', false).html('<i class="fas fa-unlock mr-1"></i>Verificar acceso');
                    }
                },
                error: function () {
                    $('#adminAuthErrTxtVta').text('Error de conexión. Intenta nuevamente.');
                    $('#adminAuthErrVta').show();
                    $btn.prop('disabled', false).html('<i class="fas fa-unlock mr-1"></i>Verificar acceso');
                }
            });
        });
    }

    // Enlazar eventos inicialmente
    _bindAdminAuthEventsVta();

});
