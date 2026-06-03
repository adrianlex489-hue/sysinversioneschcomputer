/**
 * compras.js — Módulo Registro de Compras | SysInversiones 2026
 * Contado y crédito (max 4 cuotas) — sin lotes
 * Comprobantes: Ticket de Compra / Nota de Compra
 */
$(function () {

    // ── Estado de la compra en curso ─────────────────────────────────────────
    var compra = {
        id_proveedor:    null,
        nombre_proveedor: '',
        items:           [],
        aplica_igv:      true,
        metodo_pago:     'efectivo',
        tipo_pago:       'contado',
        num_cuotas:      1
    };

    // Fix Bootstrap 4 — modales secuenciales
    // Cuando se cierra un modal y hay otro abierto, mantener modal-open en body
    $(document).on('hidden.bs.modal', '.modal', function () {
        if ($('.modal.show').length) {
            $('body').addClass('modal-open');
        }
    });

    // ── Numeración automática de comprobantes ────────────────────────────────
    var prefijos = { ticket: 'T001', nota: 'N001' };

    function cargarSiguienteNumero(tipo) {
        $.get('compras.php', { accion: 'siguiente_numero', tipo: tipo }, function (data) {
            try {
                var res = typeof data === 'string' ? JSON.parse(data) : data;
                var num = res.numero || (prefijos[tipo] + '-00000001');
                var partes = num.split('-');
                var serie  = partes.slice(0, -1).join('-');
                $('#prefijo_comprobante').text(serie);
                $('#numero_comprobante').val(num);
            } catch (e) {
                $('#numero_comprobante').val((prefijos[tipo] || 'TC001') + '-00000001');
            }
        });
    }

    $('#tipo_comprobante').on('change', function () { cargarSiguienteNumero($(this).val()); });
    cargarSiguienteNumero($('#tipo_comprobante').val());

    // ── Tipo de pago ─────────────────────────────────────────────────────────
    $(document).on('click', '.btn-tipo-pago', function () {
        $('.btn-tipo-pago').removeClass('activo');
        $(this).addClass('activo');
        compra.tipo_pago = $(this).data('tipo');
        $('#hidden_tipo_pago').val(compra.tipo_pago);
        if (compra.tipo_pago === 'credito') {
            $('#panelCredito').slideDown(200);
            $('#bloqueMetodoPago').slideUp(200);
            $('#resumenCuota').show();
            actualizarPreviewCuotas();
        } else {
            $('#panelCredito').slideUp(200);
            $('#bloqueMetodoPago').slideDown(200);
            $('#resumenCuota').hide();
        }
    });
    $('.btn-tipo-pago[data-tipo=contado]').addClass('activo');

    // ── Selector de cuotas ───────────────────────────────────────────────────
    $(document).on('click', '.btn-cuota', function () {
        $('.btn-cuota').removeClass('activo');
        $(this).addClass('activo');
        compra.num_cuotas = parseInt($(this).data('cuotas'));
        $('#hidden_num_cuotas').val(compra.num_cuotas);
        actualizarPreviewCuotas();
    });
    $('.btn-cuota[data-cuotas=1]').addClass('activo');

    // ── Preview cuotas ───────────────────────────────────────────────────────
    function actualizarPreviewCuotas() {
        var total     = parseFloat($('#hidden_total').val()) || 0;
        var fechaStr  = $('#fecha_primera_cuota').val();
        var frecuencia = parseInt($('#frecuencia_dias').val()) || 30;
        var n         = compra.num_cuotas;

        if (!fechaStr || total <= 0) {
            $('#previewCuotas').hide();
            $('#resumenCuotaTexto').text('Completa el total y la fecha de la primera cuota.');
            return;
        }

        var montoCuota = Math.round((total / n) * 100) / 100;
        var diferencia = Math.round((total - montoCuota * n) * 100) / 100;
        var fecha      = new Date(fechaStr + 'T00:00:00');
        var meses      = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

        var html = '<div style="background:#fff3e0;border-radius:8px;padding:12px;border:1px solid #ffe0b2;">';
        html += '<div style="font-size:.8rem;font-weight:700;color:#e67e22;margin-bottom:8px;"><i class="fas fa-calendar-alt mr-1"></i>CRONOGRAMA DE CUOTAS</div>';
        html += '<table style="width:100%;font-size:.82rem;"><thead><tr style="color:#999;">';
        html += '<th style="padding:3px 6px;">Cuota</th><th style="padding:3px 6px;text-align:right;">Monto</th><th style="padding:3px 6px;text-align:center;">Vencimiento</th>';
        html += '</tr></thead><tbody>';
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
        $('#previewCuotas').html(html).show();
        $('#resumenCuotaTexto').text(n + ' cuota(s) de S/. ' + montoCuota.toFixed(2) + ' c/u');
    }

    $('#fecha_primera_cuota, #frecuencia_dias').on('change', actualizarPreviewCuotas);

    // ── Alerta caja no aperturada ────────────────────────────────────────────
    function alertSinCajaComp() {
        Swal.fire({
            background: '#111827',
            padding: 0,
            width: 400,
            showConfirmButton: false,
            allowOutsideClick: true,
            customClass: { popup: 'swal-caja-popup' },
            didOpen: function () {
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
                    '<div class="swal-caja-badge"><i class="fas fa-lock" style="margin-right:5px;"></i>compras</div>' +
                '</div>' +
                '<div class="swal-caja-body">' +
                    '<div class="swal-caja-ibox">' +
                        '<div class="swal-caja-iico"><i class="fas fa-exclamation-triangle"></i></div>' +
                        '<div>' +
                            '<div class="swal-caja-itit">SIN CAJA ABIERTA</div>' +
                            '<p class="swal-caja-itxt">No puedes registrar compras sin aperturar una caja. Ve al módulo de Caja e inicia una sesión para continuar.</p>' +
                        '</div>' +
                    '</div>' +
                    '<div class="swal-caja-dbox">' +
                        '<div class="swal-caja-dico"><i class="fas fa-store"></i></div>' +
                        '<p class="swal-caja-dtxt">Módulo: <strong>Compras</strong> — Acción requerida: <strong>Aperturar Caja</strong></p>' +
                    '</div>' +
                    '<a href="../Caja/caja.php" class="swal-caja-btn">' +
                        '<i class="fas fa-arrow-left" style="margin-right:8px;"></i>Ir al módulo de Caja' +
                    '</a>' +
                '</div>'
        });
    }

    // Overlay sin caja: captura cualquier clic sobre el formulario deshabilitado
    $('#overlaySinCajaComp').on('click', function () { alertSinCajaComp(); });

    // ── Selector proveedor ───────────────────────────────────────────────────
    $('#btnSeleccionarProveedor, #campoProveedor').on('click', function () {
        if (typeof hayCajaComp !== 'undefined' && !hayCajaComp) { alertSinCajaComp(); return; }
        $('#buscarProveedor').val('');
        $('#listaProveedores .item-selector').show();
        $('#contProveedores').text($('#listaProveedores .item-selector').length);
        $('#sinResultadosProv').hide();
        $('#modalSelectorProveedor').modal('show');
        $('#modalSelectorProveedor').one('shown.bs.modal', function () { $('#buscarProveedor').focus(); });
    });

    $('#buscarProveedor').on('input', function () {
        var q = $.trim($(this).val()).toLowerCase();
        var vis = 0;
        $('#listaProveedores .item-selector').each(function () {
            var nombre = (this.getAttribute('data-nombre') || '').toLowerCase();
            var ruc    = (this.getAttribute('data-ruc')    || '').toLowerCase();
            var match  = !q || nombre.indexOf(q) > -1 || ruc.indexOf(q) > -1;
            this.style.display = match ? '' : 'none';
            if (match) vis++;
        });
        $('#contProveedores').text(vis);
        $('#sinResultadosProv').toggle(vis === 0);
    });

    $(document).on('click', '#listaProveedores .item-selector', function () {
        compra.id_proveedor    = $(this).data('id');
        compra.nombre_proveedor = $(this).data('nombre');
        $('#id_proveedor_hidden').val(compra.id_proveedor);
        $('#campoProveedor').addClass('seleccionado')
            .find('.selected-text').text(compra.nombre_proveedor).show()
            .end().find('.placeholder-text').hide();
        $('#modalSelectorProveedor').modal('hide');
        Swal.fire({ icon: 'success', title: 'Proveedor seleccionado', html: '<b>' + compra.nombre_proveedor + '</b>',
            toast: true, position: 'top-end', timer: 2000, timerProgressBar: true,
            showConfirmButton: false, background: '#eafaf1', color: '#1a7a4a', iconColor: '#27ae60' });
    });

    $('#btnLimpiarProveedor').on('click', function (e) {
        e.stopPropagation();
        compra.id_proveedor = null; compra.nombre_proveedor = '';
        $('#id_proveedor_hidden').val('');
        $('#campoProveedor').removeClass('seleccionado')
            .find('.selected-text').text('').hide()
            .end().find('.placeholder-text').show();
    });

    // ── Búsqueda RUC ─────────────────────────────────────────────────────────
    function seleccionarProveedorEnModal(id, nombre, guardado) {
        compra.id_proveedor     = id;
        compra.nombre_proveedor = nombre;
        $('#id_proveedor_hidden').val(id);
        $('#campoProveedor').addClass('seleccionado')
            .find('.selected-text').text(nombre).show()
            .end().find('.placeholder-text').hide();
        $('#modalNuevoProveedorRuc').modal('hide');
        $('#modalSelectorProveedor').modal('hide');
        Swal.fire({ icon: 'success',
            title: guardado ? 'Proveedor guardado y seleccionado' : 'Proveedor seleccionado',
            html: '<b>' + nombre + '</b>',
            toast: true, position: 'top-end', timer: 3000, timerProgressBar: true,
            showConfirmButton: false, background: '#eafaf1', color: '#1a7a4a', iconColor: '#27ae60' });
    }

    $('#btnNuevoProveedorRuc').on('click', function () {
        $('#inputRucModal').val('');
        $('#rucModalEstado').hide().empty();
        $('#rucModalResultado').hide();
        $('#modalNuevoProveedorRuc').modal('show');
        $('#modalNuevoProveedorRuc').one('shown.bs.modal', function () { $('#inputRucModal').focus(); });
    });

    function ejecutarBusquedaRuc() {
        var ruc = $.trim($('#inputRucModal').val());
        if (!/^\d{11}$/.test(ruc)) {
            $('#rucModalEstado').html('<div class="alert alert-warning py-2 mb-3" style="border-radius:8px;font-size:.85rem;"><i class="fas fa-exclamation-triangle mr-2"></i>El RUC debe tener exactamente 11 dígitos.</div>').show();
            return;
        }
        var $btn = $('#btnBuscarRucModal');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Consultando...');
        $('#rucModalEstado').html('<div class="text-center py-3"><i class="fas fa-spinner fa-spin fa-2x mb-2 d-block" style="color:#1a5276;"></i><small class="text-muted">Consultando SUNAT...</small></div>').show();
        $('#rucModalResultado').hide();

        $.post('ajax_buscar_ruc.php', { ruc: ruc, guardar: 0 }, function (res) {
            $btn.prop('disabled', false).html('<i class="fas fa-search mr-1"></i>Buscar');
            $('#rucModalEstado').hide().empty();
            if (!res.success) {
                $('#rucModalEstado').html('<div class="alert alert-danger py-2 mb-3" style="border-radius:8px;font-size:.85rem;"><i class="fas fa-times-circle mr-2"></i>' + res.error + '</div>').show();
                return;
            }
            var d = res.datos; var yaExiste = res.ya_existe;
            $('#rucRazonSocial').text(d.razon_social);
            $('#rucNumero').text(d.ruc);
            if (d.direccion) { $('#rucDireccion').text(d.direccion); $('#rucDireccionRow').show(); } else { $('#rucDireccionRow').hide(); }
            if (yaExiste) {
                $('#rucBadge').html('<span style="background:#d4edda;color:#155724;padding:2px 10px;border-radius:20px;font-size:.72rem;font-weight:700;"><i class="fas fa-check-circle mr-1"></i>Ya registrado</span>');
                $('#rucOpciones').html('<button type="button" class="btn btn-block font-weight-bold" id="btnRucUsar" style="background:#1a5276;color:#fff;border-radius:8px;padding:10px;"><i class="fas fa-check mr-2"></i>Seleccionar este proveedor</button>');
                $('#btnRucUsar').on('click', function () { seleccionarProveedorEnModal(d.id_proveedor, d.razon_social, false); });
            } else {
                $('#rucBadge').html('<span style="background:#e3f2fd;color:#1a5276;padding:2px 10px;border-radius:20px;font-size:.72rem;font-weight:700;"><i class="fas fa-building mr-1"></i>Nuevo proveedor</span>');
                $('#rucOpciones').html(
                    '<div class="row"><div class="col-6 pr-1">' +
                    '<button type="button" class="btn btn-outline-primary btn-block font-weight-bold" id="btnRucUsarSin" style="border-radius:8px;padding:10px;font-size:.85rem;"><i class="fas fa-bolt mr-1"></i>Usar sin guardar</button></div>' +
                    '<div class="col-6 pl-1">' +
                    '<button type="button" class="btn btn-block font-weight-bold" id="btnRucGuardarUsar" style="background:#1a5276;color:#fff;border-radius:8px;padding:10px;font-size:.85rem;"><i class="fas fa-save mr-1"></i>Guardar y usar</button></div></div>'
                );
                $('#btnRucUsarSin').on('click', function () { seleccionarProveedorEnModal(0, d.razon_social, false); });
                $('#btnRucGuardarUsar').on('click', function () {
                    var $b = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Guardando...');
                    $.post('ajax_buscar_ruc.php', { ruc: d.ruc, guardar: 1 }, function (res2) {
                        if (res2.success) {
                            seleccionarProveedorEnModal(res2.id_proveedor || 0, d.razon_social, true);
                            if (res2.id_proveedor) {
                                var nuevoItem = '<div class="item-selector" data-id="' + res2.id_proveedor + '" data-nombre="' + d.razon_social + '" data-ruc="' + d.ruc + '">' +
                                    '<div class="d-flex align-items-center gap-3"><div style="width:38px;height:38px;background:#e3f2fd;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-industry" style="color:#1a5276;font-size:.85rem;"></i></div>' +
                                    '<div><div class="item-nombre">' + d.razon_social + '</div><div class="item-sub"><i class="fas fa-id-card mr-1"></i>RUC: ' + d.ruc + '</div></div></div></div>';
                                $('#listaProveedores').prepend(nuevoItem);
                                $('#contProveedores').text(parseInt($('#contProveedores').text()) + 1);
                            }
                        } else {
                            $b.prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Guardar y usar');
                            Swal.fire({ icon: 'error', title: 'Error al guardar', text: res2.error, confirmButtonColor: '#1a5276' });
                        }
                    }, 'json').fail(function () { $b.prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Guardar y usar'); });
                });
            }
            $('#rucModalResultado').show();
        }, 'json').fail(function () {
            $btn.prop('disabled', false).html('<i class="fas fa-search mr-1"></i>Buscar');
            $('#rucModalEstado').html('<div class="alert alert-danger py-2 mb-3" style="border-radius:8px;font-size:.85rem;"><i class="fas fa-wifi mr-2"></i>Error de conexión. Intenta nuevamente.</div>').show();
        });
    }

    $('#btnBuscarRucModal').on('click', ejecutarBusquedaRuc);
    $('#inputRucModal').on('keypress', function (e) { if (e.which === 13) ejecutarBusquedaRuc(); });
    $('#inputRucModal').on('input', function () { this.value = this.value.replace(/[^0-9]/g, ''); });
    $('#modalNuevoProveedorRuc').on('hidden.bs.modal', function () {
        $('#inputRucModal').val(''); $('#rucModalEstado').hide().empty(); $('#rucModalResultado').hide();
    });

    // ── Selector producto ────────────────────────────────────────────────────
    $('#btnAgregarProducto').on('click', function () {
        if (typeof hayCajaComp !== 'undefined' && !hayCajaComp) { alertSinCajaComp(); return; }
        $('#buscarProducto').val('');
        $('#listaProductos .item-selector').show();
        $('#contProductos').text($('#listaProductos .item-selector').length);
        $('#sinResultadosProd').hide();
        $('#modalSelectorProducto').modal('show');
        $('#modalSelectorProducto').one('shown.bs.modal', function () { $('#buscarProducto').focus(); });
    });

    $('#buscarProducto').on('input', function () {
        var q = $.trim($(this).val()).toLowerCase();
        var vis = 0;
        $('#listaProductos .item-selector').each(function () {
            var nombre = (this.getAttribute('data-nombre') || '').toLowerCase();
            var codigo = (this.getAttribute('data-codigo') || '').toLowerCase();
            var cat    = (this.getAttribute('data-cat')    || '').toLowerCase();
            var match  = !q || nombre.indexOf(q) > -1 || codigo.indexOf(q) > -1 || cat.indexOf(q) > -1;
            this.style.display = match ? '' : 'none';
            if (match) vis++;
        });
        $('#contProductos').text(vis);
        $('#sinResultadosProd').toggle(vis === 0);
    });

    $(document).on('click', '#listaProductos .item-selector', function () {
        var d = $(this).data();
        if (compra.items.some(function (it) { return it.id_producto === d.id; })) {
            Swal.fire({ icon: 'info', title: 'Ya agregado', text: 'Este producto ya está en la lista.', timer: 2000, showConfirmButton: false });
            return;
        }
        // Guardar datos del producto seleccionado en variables locales
        var prodId     = d.id;
        var prodNombre = d.nombre || '';
        var prodCodigo = d.codigo || '';
        var prodPrecio = parseFloat(d.precio) || 0;

        // Poblar el modal de detalle ANTES de cerrar el selector
        $('#modal_item_id_producto').val(prodId);
        $('#modal_item_nombre').text(prodNombre);
        $('#modal_item_codigo').text(prodCodigo);
        $('#modal_item_precio').val(prodPrecio.toFixed(2));
        $('#modal_item_cantidad').val(1);
        $('#modal_item_descuento').val('0.00');
        // Descuento: solo admin puede editar
        if (typeof esAdminComp !== 'undefined' && esAdminComp) {
            $('#modal_item_descuento').prop('disabled', false).css('background','');
            $('#btnDesbloquearDescComp').hide();
        } else {
            $('#modal_item_descuento').prop('disabled', true).css('background','#f8f9fa');
            $('#btnDesbloquearDescComp').show();
        }
        calcularSubtotalItem();

        // Cerrar selector y abrir detalle con setTimeout
        $('#modalSelectorProducto').modal('hide');
        setTimeout(function () {
            $('#modalDetalleItem').modal('show');
            setTimeout(function () {
                $('#modal_item_cantidad').focus().select();
            }, 400);
        }, 400);
    });

    // ── Cálculo subtotal item ────────────────────────────────────────────────
    function calcularSubtotalItem() {
        var precio    = parseFloat($('#modal_item_precio').val())    || 0;
        var cantidad  = parseInt($('#modal_item_cantidad').val())    || 0;
        var descuento = parseFloat($('#modal_item_descuento').val()) || 0;
        var maxDesc   = Math.max(0, precio * cantidad);
        if (descuento < 0) { descuento = 0; $('#modal_item_descuento').val('0.00'); }
        var subtotal = Math.max(0, maxDesc - Math.min(descuento, maxDesc));
        $('#modal_item_subtotal').text('S/. ' + subtotal.toFixed(2));
        return { precio: precio, cantidad: cantidad, descuento: descuento, subtotal: subtotal };
    }
    $('#modal_item_cantidad, #modal_item_descuento').on('input', function () { calcularSubtotalItem(); });

    // ── Botón desbloquear descuento (no-admin) — Compras ─────────────────────
    $(document).on('click', '#btnDesbloquearDescComp', function () {
        pedirClaveAdminComp(function () {
            $('#modal_item_descuento').prop('disabled', false).css('background','').focus();
            $('#btnDesbloquearDescComp').hide();
        });
    });

    // Validar descuento al salir del campo
    $('#modal_item_descuento').on('change blur', function () {
        var $campo   = $(this);
        var precio   = parseFloat($('#modal_item_precio').val())   || 0;
        var cantidad = parseInt($('#modal_item_cantidad').val())   || 0;
        var maxDesc  = precio * cantidad;
        var val      = parseFloat($campo.val()) || 0;
        if (val < 0) { $campo.val('0.00'); calcularSubtotalItem(); return; }
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
                if (result.isDenied) { $campo.val(maximo); calcularSubtotalItem(); }
                else if (result.isDismissed && result.dismiss === Swal.DismissReason.cancel) { $campo.val('0.00'); calcularSubtotalItem(); }
                else { $campo.val('').focus(); }
            });
        }
    });

    // ── Confirmar item ───────────────────────────────────────────────────────
    $(document).on('click', '#btnConfirmarItem', function () {
        var id       = parseInt($('#modal_item_id_producto').val());
        var nombre   = $('#modal_item_nombre').text();
        var codigo   = $('#modal_item_codigo').text();
        var calc     = calcularSubtotalItem();
        var precio   = calc.precio;
        var cantidad = calc.cantidad;
        var desc     = calc.descuento;
        var subtotal = calc.subtotal;

        console.log('[ComprasJS] btnConfirmarItem click — id:', id, 'nombre:', nombre, 'precio:', precio, 'cantidad:', cantidad, 'desc:', desc);

        if (!id || isNaN(id)) { Swal.fire({ icon: 'warning', title: 'Error interno', text: 'No se pudo identificar el producto. Vuelve a seleccionarlo.', confirmButtonColor: '#1a5276' }); return; }
        if (cantidad <= 0) { Swal.fire({ icon: 'warning', title: 'Cantidad inválida', text: 'Ingresa una cantidad mayor a 0.', confirmButtonColor: '#1a5276' }); return; }
        if (precio <= 0)   { Swal.fire({ icon: 'warning', title: 'Precio inválido',   text: 'El precio de compra debe ser mayor a 0.', confirmButtonColor: '#1a5276' }); return; }
        if (desc > 0 && desc >= precio * cantidad) { Swal.fire({ icon: 'warning', title: 'Descuento inválido', text: 'El descuento no puede ser igual o mayor al total del item.', confirmButtonColor: '#1a5276' }); return; }

        compra.items.push({ id_producto: id, nombre: nombre, codigo: codigo, precio_compra: precio, cantidad: cantidad, descuento: desc, subtotal: subtotal });
        renderTablaDetalle();
        actualizarTotales();
        $('#modalDetalleItem').modal('hide');
        Swal.fire({
            icon: 'success',
            title: 'Producto agregado',
            html: '<b>' + nombre + '</b>',
            toast: true, position: 'top-end',
            timer: 2000, timerProgressBar: true,
            showConfirmButton: false,
            background: '#eafaf1', color: '#1a7a4a', iconColor: '#27ae60'
        });
        console.log('[ComprasJS] Item agregado. Total items:', compra.items.length);
    });

    // ── Tabla detalle ────────────────────────────────────────────────────────
    function renderTablaDetalle() {
        var $tbody = $('#tablaDetalle tbody');
        $tbody.empty();
        if (compra.items.length === 0) {
            $tbody.html('<tr class="fila-vacia"><td colspan="7"><i class="fas fa-box-open"></i>Sin productos agregados.<br><small>Haz clic en "Agregar Producto" para comenzar.</small></td></tr>');
            return;
        }
        $.each(compra.items, function (i, item) {
            var $tr = $('<tr></tr>').html(
                '<td class="text-center"><div class="num-fila-comp">' + (i + 1) + '</div></td>' +
                '<td><div style="font-weight:700;font-size:.88rem;">' + item.nombre + '</div>' +
                '<div style="font-size:.75rem;color:#999;">' + (item.codigo || '') + '</div></td>' +
                '<td class="text-center"><input type="number" class="form-control form-control-sm text-center input-cantidad" value="' + item.cantidad + '" min="1" style="width:70px;margin:auto;" data-idx="' + i + '" disabled readonly title="La cantidad no es editable desde aquí"></td>' +
                '<td class="text-right"><input type="number" step="0.01" class="form-control form-control-sm text-right input-precio" value="' + item.precio_compra.toFixed(2) + '" min="0.01" style="width:90px;margin-left:auto;" data-idx="' + i + '" disabled readonly title="El precio no es editable desde aquí"></td>' +
                '<td class="text-right"><input type="number" step="0.01" class="form-control form-control-sm text-right input-descuento" value="' + item.descuento.toFixed(2) + '" min="0" style="width:80px;margin-left:auto;" data-idx="' + i + '" disabled readonly title="El descuento no es editable desde aquí"></td>' +
                '<td class="text-right font-weight-bold text-success subtotal-item">S/. ' + item.subtotal.toFixed(2) + '</td>' +
                '<td class="text-center"><button type="button" class="btn-quitar-item btn-quitar" data-idx="' + i + '" title="Quitar"><i class="fas fa-times"></i></button></td>'
            );
            $tbody.append($tr);
        });
        // Inputs ocultos para el form
        $('#inputsOcultos').empty();
        $.each(compra.items, function (i, item) {
            $('#inputsOcultos').append(
                '<input type="hidden" name="items[' + i + '][id_producto]" value="' + item.id_producto + '">' +
                '<input type="hidden" name="items[' + i + '][cantidad]" value="' + item.cantidad + '">' +
                '<input type="hidden" name="items[' + i + '][precio_compra]" value="' + item.precio_compra + '">' +
                '<input type="hidden" name="items[' + i + '][descuento]" value="' + item.descuento + '">' +
                '<input type="hidden" name="items[' + i + '][subtotal]" value="' + item.subtotal + '">'
            );
        });
    }

    $(document).on('input', '.input-descuento', function () {
        var idx  = parseInt($(this).data('idx'));
        var item = compra.items[idx];
        if (!item) return;
        item.descuento = parseFloat($(this).val()) || 0;
        item.subtotal  = Math.max(0, (item.precio_compra * item.cantidad) - item.descuento);
        $(this).closest('tr').find('.subtotal-item').text('S/. ' + item.subtotal.toFixed(2));
        $('input[name="items[' + idx + '][descuento]"]').val(item.descuento);
        $('input[name="items[' + idx + '][subtotal]"]').val(item.subtotal);
        actualizarTotales();
    });

    $(document).on('click', '.btn-quitar', function () {
        compra.items.splice(parseInt($(this).data('idx')), 1);
        renderTablaDetalle();
        actualizarTotales();
    });

    // ── Totales ──────────────────────────────────────────────────────────────
    function actualizarTotales() {
        var subtotal   = compra.items.reduce(function (s, it) { return s + it.subtotal; }, 0);
        var descGlobal = parseFloat($('#descuento_global').val()) || 0;
        var base       = Math.max(0, subtotal - descGlobal);
        var igv        = compra.aplica_igv ? base * 0.18 : 0;
        var total      = base + igv;
        $('#resumen_subtotal').text('S/. ' + subtotal.toFixed(2));
        $('#resumen_descuento').text('- S/. ' + descGlobal.toFixed(2));
        $('#resumen_igv').text(compra.aplica_igv ? 'S/. ' + igv.toFixed(2) : 'No aplica');
        $('#resumen_total').text('S/. ' + total.toFixed(2));
        $('#hidden_subtotal').val(subtotal.toFixed(2));
        $('#hidden_igv').val(igv.toFixed(2));
        $('#hidden_total').val(total.toFixed(2));
        $('#contadorItems').text(compra.items.length + ' producto' + (compra.items.length !== 1 ? 's' : ''));
        if (compra.tipo_pago === 'credito') actualizarPreviewCuotas();
    }

    $('#descuento_global').on('input', actualizarTotales);
    $('#aplica_igv').on('change', function () {
        compra.aplica_igv = $(this).is(':checked');
        actualizarTotales();
    });

    // ── Método de pago ───────────────────────────────────────────────────────
    $(document).on('click', '.btn-metodo', function () {
        $('.btn-metodo').removeClass('activo');
        $(this).addClass('activo');
        compra.metodo_pago = $(this).data('metodo');
        $('#hidden_metodo_pago').val(compra.metodo_pago);
    });
    $('.btn-metodo[data-metodo=efectivo]').addClass('activo');

    // ── Submit ───────────────────────────────────────────────────────────────
    $('#formNuevaCompra').on('submit', function (e) {
        e.preventDefault();
        if (!compra.id_proveedor) { Swal.fire({ icon: 'warning', title: 'Proveedor requerido', text: 'Selecciona un proveedor.', confirmButtonColor: '#1a5276' }); return; }
        if (compra.items.length === 0) { Swal.fire({ icon: 'warning', title: 'Sin productos', text: 'Agrega al menos un producto.', confirmButtonColor: '#1a5276' }); return; }
        var total = parseFloat($('#hidden_total').val()) || 0;
        if (total <= 0) { Swal.fire({ icon: 'warning', title: 'Total inválido', text: 'El total debe ser mayor a 0.', confirmButtonColor: '#1a5276' }); return; }
        if (compra.tipo_pago === 'credito' && !$('#fecha_primera_cuota').val()) {
            Swal.fire({ icon: 'warning', title: 'Fecha requerida', text: 'Indica la fecha de la primera cuota.', confirmButtonColor: '#1a5276' }); return;
        }
        var tipoPagoLabel = compra.tipo_pago === 'contado' ? 'CONTADO' : 'CRÉDITO (' + compra.num_cuotas + ' cuota(s))';
        var tipoCompLabel = { ticket: 'Ticket de Compra', nota: 'Nota de Compra' };
        var tipoComp = tipoCompLabel[$('#tipo_comprobante').val()] || $('#tipo_comprobante').val();
        Swal.fire({
            icon: 'question', title: '¿Registrar compra?',
            html: '<b>' + compra.items.length + ' producto(s)</b> &mdash; Total: <b>S/. ' + total.toFixed(2) + '</b><br>' +
                  'Proveedor: <b>' + compra.nombre_proveedor + '</b><br>' +
                  'Comprobante: <b>' + tipoComp + '</b> &mdash; Pago: <b>' + tipoPagoLabel + '</b>',
            showCancelButton: true, confirmButtonColor: '#1a5276', cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-save mr-1"></i> Sí, registrar',
            cancelButtonText: 'Cancelar'
        }).then(function (r) {
            if (r.isConfirmed) {
                $('#btnSubmitCompra').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...');
                document.getElementById('formNuevaCompra').submit();
            }
        });
    });

    // ── Ver detalle compra ───────────────────────────────────────────────────
    $(document).on('click', '.btn-ver-compra', function () {
        var id = $(this).data('id');
        $('#cuerpoDetalleCompra').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i><p class="mt-2 text-muted">Cargando...</p></div>');
        $('#modalVerCompra').modal('show');
        $.get('compras.php', { accion: 'detalle_ajax', id_compra: id }, function (html) {
            $('#cuerpoDetalleCompra').html(html);
        }).fail(function () { $('#cuerpoDetalleCompra').html('<div class="alert alert-danger">Error al cargar el detalle.</div>'); });
    });

    // ── Registrar pago (crédito) ─────────────────────────────────────────────
    $(document).on('click', '.btn-pagar-compra', function () {
        var id       = $(this).data('id');
        var saldo    = parseFloat($(this).data('saldo')) || 0;
        var proveedor = $(this).data('proveedor') || '';
        $('#pago_id_compra').val(id);
        $('#pago_saldo_display').text('S/. ' + saldo.toFixed(2));
        $('#pago_proveedor_label').text(proveedor);
        $('#monto_pago').val(saldo.toFixed(2)).attr('max', saldo);
        $('.btn-metodo-pago').css({ background: '#fff', color: '#555', borderColor: '#dee2e6' });
        $('.btn-metodo-pago[data-metodo=efectivo]').css({ background: '#1a7a4a', color: '#fff', borderColor: '#1a7a4a' });
        $('#metodo_pago_abono').val('efectivo');
        $('#modalPagarCompra').modal('show');
    });

    $(document).on('click', '.btn-metodo-pago', function () {
        $('.btn-metodo-pago').css({ background: '#fff', color: '#555', borderColor: '#dee2e6' });
        $(this).css({ background: '#1a7a4a', color: '#fff', borderColor: '#1a7a4a' });
        $('#metodo_pago_abono').val($(this).data('metodo'));
    });

    // ── Anular compra ────────────────────────────────────────────────────────
    $(document).on('click', '.btn-anular-compra', function () {
        var id  = $(this).data('id');
        var num = $(this).data('numero') || ('#' + id);
        Swal.fire({
            icon: 'error', title: '¿Anular compra?',
            html: 'La compra <b>' + num + '</b> será anulada.<br><strong class="text-danger">El stock de los productos será revertido.</strong>',
            showCancelButton: true, confirmButtonColor: '#e74c3c', cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-ban mr-1"></i> Sí, anular',
            cancelButtonText: 'Cancelar'
        }).then(function (r) {
            if (r.isConfirmed) {
                var $f = $('<form method="POST" style="display:none;"></form>');
                $f.append('<input type="hidden" name="accion" value="anular">');
                $f.append('<input type="hidden" name="id_compra" value="' + id + '">');
                $('body').append($f); $f.submit();
            }
        });
    });

    // ── Limpiar formulario ───────────────────────────────────────────────────
    $('#btnLimpiarForm').on('click', function () {
        Swal.fire({
            icon: 'question', title: '¿Limpiar formulario?',
            text: 'Se perderán todos los datos ingresados.',
            showCancelButton: true, confirmButtonColor: '#e67e22', cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, limpiar', cancelButtonText: 'Cancelar'
        }).then(function (r) {
            if (!r.isConfirmed) return;
            compra.items = []; compra.id_proveedor = null; compra.nombre_proveedor = '';
            compra.tipo_pago = 'contado'; compra.num_cuotas = 1;
            $('#formNuevaCompra')[0].reset();
            $('#campoProveedor').removeClass('seleccionado')
                .find('.selected-text').text('').hide()
                .end().find('.placeholder-text').show();
            $('#id_proveedor_hidden').val('');
            $('.btn-tipo-pago').removeClass('activo');
            $('.btn-tipo-pago[data-tipo=contado]').addClass('activo');
            $('#hidden_tipo_pago').val('contado');
            $('#panelCredito').hide(); $('#bloqueMetodoPago').show(); $('#resumenCuota').hide();
            $('.btn-metodo').removeClass('activo');
            $('.btn-metodo[data-metodo=efectivo]').addClass('activo');
            $('#hidden_metodo_pago').val('efectivo');
            $('.btn-cuota').removeClass('activo');
            $('.btn-cuota[data-cuotas=1]').addClass('activo');
            $('#hidden_num_cuotas').val(1);
            $('#previewCuotas').hide();
            renderTablaDetalle();
            actualizarTotales();
            cargarSiguienteNumero($('#tipo_comprobante').val());
        });
    });

    // ── Init ─────────────────────────────────────────────────────────────────
    renderTablaDetalle();
    actualizarTotales();

    // ══════════════════════════════════════════════════════════════════════════
    // VINCULACIÓN DE COTIZACIONES DE SERVICIO
    // ══════════════════════════════════════════════════════════════════════════

    // Mapa: id_producto → id_cotizacion (para enviar al guardar)
    var cotizacionesVinculadas = {};

    // ── Botón "Agregar a compra" desde la alerta de repuestos pendientes ──────
    $(document).on('click', '.btn-vincular-cotizacion', function () {
        var idCot      = $(this).data('id-cot');
        var idProducto = $(this).data('id-producto');
        var nombre     = $(this).data('nombre');
        var precio     = parseFloat($(this).data('precio')) || 0;
        var cantidad   = parseInt($(this).data('cantidad')) || 1;
        var orden      = $(this).data('orden');

        // Si el producto ya está en la lista, solo avisar
        if (idProducto && compra.items.some(function (it) { return it.id_producto === idProducto; })) {
            Swal.fire({
                icon: 'info', title: 'Ya en la lista',
                html: '<b>' + nombre + '</b> ya fue agregado a esta compra.',
                confirmButtonColor: '#1a5276'
            });
            return;
        }

        Swal.fire({
            icon: 'question',
            title: 'Agregar repuesto a la compra',
            html:
                '<div style="text-align:left;font-size:.88rem;">' +
                '<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 14px;margin-bottom:10px;">' +
                '<div style="font-weight:700;color:#92400e;margin-bottom:4px;">' + nombre + '</div>' +
                '<div style="color:#64748b;"><i class="fas fa-hashtag mr-1"></i>Orden: <strong>' + orden + '</strong></div>' +
                '<div style="color:#64748b;"><i class="fas fa-sort-numeric-up mr-1"></i>Cantidad: <strong>' + cantidad + '</strong></div>' +
                '<div style="color:#64748b;"><i class="fas fa-tag mr-1"></i>Precio referencial: <strong>S/. ' + precio.toFixed(2) + '</strong></div>' +
                '</div>' +
                '<p style="color:#475569;margin:0;font-size:.82rem;">Se pre-cargará en el formulario de compra. Puedes ajustar el precio antes de guardar.</p>' +
                '</div>',
            showCancelButton: true,
            confirmButtonColor: '#d97706',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="fas fa-plus mr-1"></i>Sí, agregar',
            cancelButtonText: 'Cancelar'
        }).then(function (r) {
            if (!r.isConfirmed) return;

            // Agregar a la lista de items
            var nuevoItem = {
                id_producto:   idProducto || 0,
                nombre:        nombre,
                codigo:        '',
                precio_compra: precio,
                cantidad:      cantidad,
                descuento:     0,
                subtotal:      precio * cantidad
            };
            compra.items.push(nuevoItem);

            // Registrar la vinculación para enviarla al guardar
            if (idProducto) {
                cotizacionesVinculadas[idProducto] = idCot;
            }

            renderTablaDetalle();
            actualizarTotales();

            // Scroll al formulario
            $('html, body').animate({ scrollTop: $('#seccionNuevaCompra').offset().top - 80 }, 400);

            Swal.fire({
                icon: 'success',
                title: 'Repuesto agregado',
                html: '<b>' + nombre + '</b> fue agregado al formulario de compra.<br>' +
                      '<small class="text-muted">Verifica el precio y guarda la compra para marcar la cotización como comprada.</small>',
                confirmButtonColor: '#d97706',
                timer: 4000,
                timerProgressBar: true
            });
        });
    });

    // ── Al renderizar tabla, agregar inputs ocultos de cotización ─────────────
    // Sobreescribir renderTablaDetalle para incluir id_cotizacion
    var _renderTablaDetalle = renderTablaDetalle;
    renderTablaDetalle = function () {
        _renderTablaDetalle();
        // Agregar inputs ocultos de cotización vinculada
        $.each(compra.items, function (i, item) {
            var idCot = cotizacionesVinculadas[item.id_producto];
            if (idCot) {
                $('#inputsOcultos').append(
                    '<input type="hidden" name="items[' + i + '][id_cotizacion]" value="' + idCot + '">'
                );
            }
        });
    };

    // ── Al limpiar formulario, limpiar también las vinculaciones ─────────────
    var _limpiarOriginal = $('#btnLimpiarForm').data('events');
    $('#btnLimpiarForm').on('compra:limpiar', function () {
        cotizacionesVinculadas = {};
    });

    // Hook: cuando se limpia el form, limpiar vinculaciones
    var originalLimpiar = $('#btnLimpiarForm').off('click').on('click', function () {
        Swal.fire({
            icon: 'question', title: '¿Limpiar formulario?',
            text: 'Se perderán todos los datos ingresados.',
            showCancelButton: true, confirmButtonColor: '#e67e22', cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, limpiar', cancelButtonText: 'Cancelar'
        }).then(function (r) {
            if (!r.isConfirmed) return;
            compra.items = []; compra.id_proveedor = null; compra.nombre_proveedor = '';
            compra.tipo_pago = 'contado'; compra.num_cuotas = 1;
            cotizacionesVinculadas = {};
            $('#formNuevaCompra')[0].reset();
            $('#campoProveedor').removeClass('seleccionado')
                .find('.selected-text').text('').hide()
                .end().find('.placeholder-text').show();
            $('#id_proveedor_hidden').val('');
            $('.btn-tipo-pago').removeClass('activo');
            $('.btn-tipo-pago[data-tipo=contado]').addClass('activo');
            $('#hidden_tipo_pago').val('contado');
            $('#panelCredito').hide(); $('#bloqueMetodoPago').show(); $('#resumenCuota').hide();
            $('.btn-metodo').removeClass('activo');
            $('.btn-metodo[data-metodo=efectivo]').addClass('activo');
            $('#hidden_metodo_pago').val('efectivo');
            $('.btn-cuota').removeClass('activo');
            $('.btn-cuota[data-cuotas=1]').addClass('activo');
            $('#hidden_num_cuotas').val(1);
            $('#previewCuotas').hide();
            renderTablaDetalle();
            actualizarTotales();
            cargarSiguienteNumero($('#tipo_comprobante').val());
        });
    });

    // ── Helper: pedir contraseña de administrador — Compras ───────────────────
    var _adminAuthCallbackComp = null;

    function pedirClaveAdminComp(onSuccess) {
        _adminAuthCallbackComp = onSuccess;
        // Restaurar contenido original si fue reemplazado por el estado de éxito
        if (!$('#adminAuthPassComp').length) {
            $('#modalAdminAuthComp').modal('hide');
            location.reload();
            return;
        }
        $('#adminAuthPassComp').val('');
        $('#adminAuthErrComp').hide();
        $('#adminAuthConfirmComp').prop('disabled', false).html('<i class="fas fa-unlock mr-1"></i>Verificar acceso');
        $('#modalAdminAuthComp').modal('show');
        $('#modalAdminAuthComp').one('shown.bs.modal', function () {
            $('#adminAuthPassComp').focus();
        });
    }

    // Ojo mostrar/ocultar contraseña
    $('#adminAuthEyeComp').on('click', function () {
        var $inp = $('#adminAuthPassComp');
        var isPass = $inp.attr('type') === 'password';
        $inp.attr('type', isPass ? 'text' : 'password');
        $(this).find('i').toggleClass('fa-eye fa-eye-slash');
    });

    // Cancelar
    $('#adminAuthCancelComp').on('click', function () {
        $('#modalAdminAuthComp').modal('hide');
        _adminAuthCallbackComp = null;
    });

    // Enter en el campo
    $('#adminAuthPassComp').on('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); $('#adminAuthConfirmComp').trigger('click'); }
    });

    // Confirmar
    $('#adminAuthConfirmComp').on('click', function () {
        var clave = $('#adminAuthPassComp').val().trim();
        if (!clave) {
            $('#adminAuthErrTxtComp').text('Ingresa la contraseña del administrador.');
            $('#adminAuthErrComp').show();
            $('#adminAuthPassComp').focus();
            return;
        }
        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Verificando...');
        $('#adminAuthErrComp').hide();

        $.ajax({
            url: '/sysinversioneschcomputer/conf/ajax_verificar_admin.php',
            method: 'POST',
            data: { clave: clave },
            dataType: 'json',
            success: function (data) {
                if (data.ok) {
                    // ── Mostrar estado de éxito dentro del mismo modal ────────
                    $('#modalAdminAuthComp .modal-content').html(
                        '<div style="background:linear-gradient(160deg,#001a0a 0%,#003d1a 55%,#001a0a 100%);padding:40px 24px 36px;text-align:center;position:relative;overflow:hidden;border-radius:18px;">' +
                          '<div style="position:absolute;inset:0;background-image:linear-gradient(rgba(39,174,96,.07) 1px,transparent 1px),linear-gradient(90deg,rgba(39,174,96,.07) 1px,transparent 1px);background-size:26px 26px;pointer-events:none;"></div>' +
                          '<div style="width:80px;height:80px;border-radius:50%;border:2px solid rgba(39,174,96,.5);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;background:rgba(39,174,96,.12);position:relative;">' +
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
                    setTimeout(function () {
                        $('#modalAdminAuthComp').modal('hide');
                        if (typeof _adminAuthCallbackComp === 'function') {
                            _adminAuthCallbackComp();
                            _adminAuthCallbackComp = null;
                        }
                    }, 1500);
                } else {
                    $('#adminAuthErrTxtComp').text(data.msg || 'Contraseña incorrecta. Intenta nuevamente.');
                    $('#adminAuthErrComp').show();
                    $('#adminAuthPassComp').val('').focus();
                    $btn.prop('disabled', false).html('<i class="fas fa-unlock mr-1"></i>Verificar acceso');
                }
            },
            error: function () {
                $('#adminAuthErrTxtComp').text('Error de conexión. Intenta nuevamente.');
                $('#adminAuthErrComp').show();
                $btn.prop('disabled', false).html('<i class="fas fa-unlock mr-1"></i>Verificar acceso');
            }
        });
    });

});
