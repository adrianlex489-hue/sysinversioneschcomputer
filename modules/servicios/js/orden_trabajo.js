/**
 * orden_trabajo.js — Página completa de trabajo de orden
 * SysInversiones CH Computer 2026
 */
$(function () {

    var ID_ORDEN = $('input[name="id_orden"]').val();

    // ══════════════════════════════════════════════════════════════════════════
    // SERVICIOS — estado en memoria
    // ══════════════════════════════════════════════════════════════════════════
    // Leer servicios ya renderizados en el HTML (cargados desde PHP)
    var servicios = [];
    $('.ot-srv-item').each(function () {
        servicios.push({
            id:       parseInt($(this).data('id')),
            nombre:   $(this).find('.ot-srv-nombre').text().trim(),
            precio:   parseFloat($(this).find('.h-precio').val()),
            cantidad: parseInt($(this).find('.h-cant').val())
        });
    });
    reindexarItems();
    actualizarTotales();

    // ── Reindexar todos los items del DOM ────────────────────────────────────
    function reindexarItems() {
        $('#otServiciosLista .ot-srv-item').each(function (i) {
            $(this).attr('data-idx', i);
            $(this).find('[data-idx]').attr('data-idx', i);
            $(this).find('.ot-cant-dec, .ot-cant-inc, .ot-srv-del, .ot-btn-editar-precio').attr('data-idx', i);
        });
    }

    // ── Renderizar un item nuevo ──────────────────────────────────────────────
    function renderItem(srv, idx) {
        var sub = (srv.precio * srv.cantidad).toFixed(2);
        return (
            '<div class="ot-srv-item" data-id="' + srv.id + '" data-idx="' + idx + '">' +
            '  <div class="ot-srv-item-header">' +
            '    <span class="ot-srv-nombre">' + $('<span>').text(srv.nombre).html() + '</span>' +
            '    <button type="button" class="ot-srv-del" data-idx="' + idx + '" title="Quitar"><i class="fas fa-times"></i></button>' +
            '  </div>' +
            '  <div class="ot-srv-item-footer">' +
            '    <div class="ot-srv-controls">' +
            '      <button type="button" class="ot-qty-btn ot-cant-dec" data-idx="' + idx + '"><i class="fas fa-minus"></i></button>' +
            '      <span class="ot-qty-display" id="otQty_' + idx + '">' + srv.cantidad + '</span>' +
            '      <button type="button" class="ot-qty-btn ot-cant-inc" data-idx="' + idx + '"><i class="fas fa-plus"></i></button>' +
            '      <button type="button" class="ot-precio-chip ot-btn-editar-precio" data-idx="' + idx + '">' +
            '        <i class="fas fa-tag"></i> S/. <span class="ot-precio-lbl" id="otPrecioLbl_' + idx + '">' + srv.precio.toFixed(2) + '</span>' +
            '        <i class="fas fa-pencil-alt" style="font-size:.6rem;opacity:.6;"></i>' +
            '      </button>' +
            '    </div>' +
            '    <span class="ot-srv-sub">S/. <span class="ot-sub-val" id="otSub_' + idx + '">' + sub + '</span></span>' +
            '  </div>' +
            '  <input type="hidden" name="servicios_ids[]" value="' + srv.id + '">' +
            '  <input type="hidden" name="servicios_precios[]" class="h-precio" value="' + srv.precio.toFixed(2) + '">' +
            '  <input type="hidden" name="servicios_cantidades[]" class="h-cant" value="' + srv.cantidad + '">' +
            '</div>'
        );
    }

    function actualizarItemDOM(idx) {
        var srv = servicios[idx];
        var sub = (srv.precio * srv.cantidad).toFixed(2);
        $('#otQty_' + idx).text(srv.cantidad);
        $('#otPrecioLbl_' + idx).text(srv.precio.toFixed(2));
        $('#otSub_' + idx).text(sub);
        var $item = $('#otServiciosLista .ot-srv-item[data-idx="' + idx + '"]');
        $item.find('.h-precio').val(srv.precio.toFixed(2));
        $item.find('.h-cant').val(srv.cantidad);
        actualizarTotales();
    }

    function actualizarTotales() {
        var total = servicios.reduce(function (acc, s) { return acc + s.precio * s.cantidad; }, 0);
        $('#otTotalVal, #otTotalResumen').text('S/. ' + total.toFixed(2));

        // Resumen lateral
        var $res = $('#otResumenServicios').empty();
        if (servicios.length === 0) {
            $res.html('<span class="text-muted" style="font-style:italic;">Sin servicios</span>');
        } else {
            servicios.forEach(function (s) {
                var sub = s.precio * s.cantidad;
                $res.append(
                    '<div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #f1f5f9;">' +
                    '<span style="flex:1;color:#1e293b;">' + $('<span>').text(s.nombre).html() +
                    (s.cantidad > 1 ? ' <small class="text-muted">x' + s.cantidad + '</small>' : '') + '</span>' +
                    '<span style="font-weight:700;color:#065f46;white-space:nowrap;">S/. ' + sub.toFixed(2) + '</span>' +
                    '</div>'
                );
            });
        }

        // Mostrar/ocultar vacío
        if (servicios.length === 0) {
            $('#otSinServicios').show();
        } else {
            $('#otSinServicios').hide();
        }
    }

    // ── Botones +/- cantidad ──────────────────────────────────────────────────
    $(document).on('click', '.ot-cant-dec', function () {
        var idx = parseInt($(this).data('idx'));
        if (servicios[idx] && servicios[idx].cantidad > 1) {
            servicios[idx].cantidad--;
            actualizarItemDOM(idx);
        }
    });
    $(document).on('click', '.ot-cant-inc', function () {
        var idx = parseInt($(this).data('idx'));
        if (servicios[idx] && servicios[idx].cantidad < 99) {
            servicios[idx].cantidad++;
            actualizarItemDOM(idx);
        }
    });

    // ── Quitar servicio ───────────────────────────────────────────────────────
    $(document).on('click', '.ot-srv-del', function () {
        var idx    = parseInt($(this).data('idx'));
        var nombre = servicios[idx] ? servicios[idx].nombre : '';
        Swal.fire({
            icon: 'warning', title: '¿Quitar servicio?',
            html: '<b>' + $('<span>').text(nombre).html() + '</b> será removido.',
            showCancelButton: true,
            confirmButtonColor: '#dc2626', cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="fas fa-times mr-1"></i>Sí, quitar',
            cancelButtonText: 'Cancelar', reverseButtons: true
        }).then(function (r) {
            if (!r.isConfirmed) return;
            servicios.splice(idx, 1);
            // Re-renderizar lista completa
            var $lista = $('#otServiciosLista');
            $lista.empty();
            if (servicios.length === 0) {
                $lista.append('<div class="ot-empty-srv" id="otSinServicios"><i class="fas fa-clipboard-list fa-2x mb-2 d-block" style="opacity:.25;"></i><p class="mb-0 fw-bold">Sin servicios agregados</p><small>Haz clic en "Agregar del Catálogo"</small></div>');
            } else {
                servicios.forEach(function (s, i) { $lista.append(renderItem(s, i)); });
            }
            actualizarTotales();
        });
    });

    // ══════════════════════════════════════════════════════════════════════════
    // CATÁLOGO MODAL
    // ══════════════════════════════════════════════════════════════════════════
    $('#btnAbrirCatalogo').on('click', function () {
        $('#catBuscar').val('');
        $('#catFiltroTipo').val('');
        filtrarCatalogo();
        $('#modalCatalogo').modal('show');
    });

    function filtrarCatalogo() {
        var q    = $('#catBuscar').val().toLowerCase().trim();
        var tipo = $('#catFiltroTipo').val();
        var vis  = 0;
        $('.cat-item').each(function () {
            var nombre  = $(this).data('nombre').toLowerCase();
            var tipoSrv = $(this).data('tipo');
            var show    = (!q || nombre.indexOf(q) > -1) && (!tipo || tipoSrv === tipo);
            $(this).toggle(show);
            if (show) vis++;
        });
        $('#catContador').text(vis);
        $('#catSinResultados').toggle(vis === 0);
    }
    $('#catBuscar').on('input', filtrarCatalogo);
    $('#catFiltroTipo').on('change', filtrarCatalogo);

    $(document).on('click', '.cat-btn-agregar', function () {
        var $item  = $(this).closest('.cat-item');
        var id     = parseInt($item.data('id'));
        var nombre = $item.data('nombre');
        var precio = parseFloat($item.data('precio'));

        if (servicios.find(function (s) { return s.id === id; })) {
            Swal.fire({ icon: 'info', title: 'Ya agregado', text: '"' + nombre + '" ya está en la lista.',
                toast: true, position: 'top-end', timer: 2000, showConfirmButton: false, timerProgressBar: true });
            return;
        }

        var idx = servicios.length;
        servicios.push({ id: id, nombre: nombre, precio: precio, cantidad: 1 });

        var $lista = $('#otServiciosLista');
        $lista.find('#otSinServicios').remove();
        $lista.append(renderItem(servicios[idx], idx));
        actualizarTotales();

        // Feedback en botón
        var $btn = $(this);
        $btn.html('<i class="fas fa-check"></i>').css('background', '#10b981');
        setTimeout(function () { $btn.html('<i class="fas fa-plus"></i>').css('background', '#059669'); }, 1200);

        Swal.fire({ icon: 'success', title: 'Agregado', html: '<b>' + $('<span>').text(nombre).html() + '</b> fue agregado.',
            toast: true, position: 'top-end', timer: 1800, showConfirmButton: false, timerProgressBar: true,
            background: '#f0fdf4', color: '#065f46', iconColor: '#059669' });
    });

    // ══════════════════════════════════════════════════════════════════════════
    // MODAL EDITAR PRECIO / CANTIDAD
    // ══════════════════════════════════════════════════════════════════════════
    $(document).on('click', '.ot-btn-editar-precio', function () {
        var idx = parseInt($(this).data('idx'));
        var srv = servicios[idx];
        $('#editSrvTarget').val(idx);
        $('#editSrvNombre').text(srv.nombre);
        $('#editPrecioVal').val(srv.precio.toFixed(2));
        $('#editCantVal').val(srv.cantidad);
        actualizarEditSubtotal();
        $('#modalEditarSrv').modal('show');
    });

    function actualizarEditSubtotal() {
        var p = parseFloat($('#editPrecioVal').val()) || 0;
        var c = parseInt($('#editCantVal').val()) || 1;
        $('#editSubtotal').text('S/. ' + (p * c).toFixed(2));
    }
    $('#editPrecioVal, #editCantVal').on('input', actualizarEditSubtotal);

    $('#editPrecioDec').on('click', function () {
        var v = Math.max(0, (parseFloat($('#editPrecioVal').val()) || 0) - 0.5);
        $('#editPrecioVal').val(v.toFixed(2)); actualizarEditSubtotal();
    });
    $('#editPrecioInc').on('click', function () {
        var v = (parseFloat($('#editPrecioVal').val()) || 0) + 0.5;
        $('#editPrecioVal').val(v.toFixed(2)); actualizarEditSubtotal();
    });
    $('#editCantDec').on('click', function () {
        var v = Math.max(1, (parseInt($('#editCantVal').val()) || 1) - 1);
        $('#editCantVal').val(v); actualizarEditSubtotal();
    });
    $('#editCantInc').on('click', function () {
        var v = Math.min(99, (parseInt($('#editCantVal').val()) || 1) + 1);
        $('#editCantVal').val(v); actualizarEditSubtotal();
    });

    $('#btnAplicarEdicion').on('click', function () {
        var idx    = parseInt($('#editSrvTarget').val());
        var precio = parseFloat($('#editPrecioVal').val()) || 0;
        var cant   = Math.max(1, parseInt($('#editCantVal').val()) || 1);
        servicios[idx].precio   = precio;
        servicios[idx].cantidad = cant;
        actualizarItemDOM(idx);
        $('#modalEditarSrv').modal('hide');
        Swal.fire({ icon: 'success', title: 'Actualizado', html: 'Precio y cantidad actualizados.',
            toast: true, position: 'top-end', timer: 1500, showConfirmButton: false, timerProgressBar: true,
            background: '#f0fdf4', color: '#065f46', iconColor: '#059669' });
    });

    // ══════════════════════════════════════════════════════════════════════════
    // FOTOS DEL TALLER
    // ══════════════════════════════════════════════════════════════════════════
    var fotasTaller   = [];
    var lbIndex       = 0;

    // Cargar fotos existentes al iniciar
    $.get('ajax_fotos_taller.php', { accion: 'listar', id_orden: ID_ORDEN }, function (res) {
        if (res.success) { fotasTaller = res.fotos || []; renderGaleria(); }
    }, 'json');

    function renderGaleria() {
        var $gal = $('#otFotoGaleria').empty();
        if (fotasTaller.length === 0) {
            $('#otFotoVacia').show();
            $('#otFotoBadge').hide();
            return;
        }
        $('#otFotoVacia').hide();
        $('#otFotoBadge').text(fotasTaller.length).show();

        fotasTaller.forEach(function (ruta, idx) {
            var web = '/sysinversioneschcomputer/' + ruta;
            $gal.append(
                '<div class="ot-foto-thumb" data-idx="' + idx + '">' +
                '  <img src="' + web + '" alt="Foto ' + (idx+1) + '" loading="lazy">' +
                '  <span class="ot-foto-num">' + (idx+1) + '</span>' +
                '  <div class="ot-foto-thumb-overlay">' +
                '    <button type="button" class="ot-foto-thumb-btn ot-ftb-ver" data-idx="' + idx + '" title="Ver"><i class="fas fa-expand"></i></button>' +
                '    <button type="button" class="ot-foto-thumb-btn ot-ftb-del" data-idx="' + idx + '" data-ruta="' + ruta + '" title="Eliminar"><i class="fas fa-trash-alt"></i></button>' +
                '  </div>' +
                '</div>'
            );
        });
    }

    function subirFoto(file) {
        if (file.size > 5 * 1024 * 1024) {
            Swal.fire({ icon: 'warning', title: 'Archivo muy grande', text: '"' + file.name + '" supera 5 MB.',
                toast: true, position: 'top-end', timer: 3000, showConfirmButton: false }); return;
        }
        var allowed = ['image/jpeg','image/png','image/webp','image/gif'];
        if (!allowed.includes(file.type)) {
            Swal.fire({ icon: 'warning', title: 'Tipo no permitido', text: 'Solo JPG, PNG, WEBP o GIF.',
                toast: true, position: 'top-end', timer: 3000, showConfirmButton: false }); return;
        }

        var $skel = $('<div class="ot-foto-skeleton"></div>');
        $('#otFotoGaleria').append($skel);
        $('#otFotoVacia').hide();

        $('#otFotoProgress').show();
        $('#otFotoProgressLabel').text('Subiendo ' + file.name + '...');
        $('#otFotoProgressBar').css('width', '0%');
        $('#otFotoProgressPct').text('0%');

        var fd = new FormData();
        fd.append('accion', 'subir');
        fd.append('id_orden', ID_ORDEN);
        fd.append('foto', file);

        $.ajax({
            url: 'ajax_fotos_taller.php', method: 'POST',
            data: fd, processData: false, contentType: false,
            xhr: function () {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function (e) {
                    if (e.lengthComputable) {
                        var pct = Math.round((e.loaded / e.total) * 100);
                        $('#otFotoProgressBar').css('width', pct + '%');
                        $('#otFotoProgressPct').text(pct + '%');
                    }
                }, false);
                return xhr;
            },
            success: function (res) {
                $skel.remove(); $('#otFotoProgress').hide();
                if (res.success) {
                    fotasTaller.push(res.ruta); renderGaleria();
                    Swal.fire({ icon: 'success', title: 'Foto subida', html: 'Guardada correctamente.',
                        toast: true, position: 'top-end', timer: 1800, showConfirmButton: false, timerProgressBar: true,
                        background: '#f0fdf4', color: '#065f46', iconColor: '#059669' });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.error || 'No se pudo subir.', confirmButtonColor: '#059669' });
                    if (fotasTaller.length === 0) $('#otFotoVacia').show();
                }
            },
            error: function () {
                $skel.remove(); $('#otFotoProgress').hide();
                Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo conectar.', confirmButtonColor: '#059669' });
            }
        });
    }

    $('#btnSeleccionarFotos').on('click', function () { $('#otFotoInput').val('').trigger('click'); });
    $('#otFotoInput').on('change', function () {
        Array.from(this.files).forEach(subirFoto);
    });

    // Drag & Drop sobre la dropzone
    var $dz = $('#otDropzone');
    $dz.on('dragover dragenter', function (e) { e.preventDefault(); e.stopPropagation(); $(this).addClass('drag-over'); });
    $dz.on('dragleave drop', function (e) { e.preventDefault(); e.stopPropagation(); $(this).removeClass('drag-over'); });
    $dz.on('drop', function (e) {
        Array.from(e.originalEvent.dataTransfer.files).forEach(subirFoto);
    });
    $dz.on('click', function () { $('#otFotoInput').val('').trigger('click'); });

    // Ver foto
    $(document).on('click', '.ot-ftb-ver', function (e) {
        e.stopPropagation();
        lbIndex = parseInt($(this).data('idx'));
        abrirLb(lbIndex);
    });
    $(document).on('click', '.ot-foto-thumb', function (e) {
        if (!$(e.target).closest('.ot-foto-thumb-btn').length) {
            lbIndex = parseInt($(this).data('idx'));
            abrirLb(lbIndex);
        }
    });

    // Eliminar foto
    $(document).on('click', '.ot-ftb-del', function (e) {
        e.stopPropagation();
        var idx  = parseInt($(this).data('idx'));
        var ruta = $(this).data('ruta');
        Swal.fire({
            icon: 'warning', title: '¿Eliminar esta foto?',
            html: 'La foto será eliminada permanentemente.',
            showCancelButton: true, confirmButtonColor: '#dc2626', cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="fas fa-trash-alt mr-1"></i>Sí, eliminar',
            cancelButtonText: 'Cancelar', reverseButtons: true
        }).then(function (r) {
            if (!r.isConfirmed) return;
            $.post('ajax_fotos_taller.php', { accion: 'eliminar', id_orden: ID_ORDEN, ruta: ruta }, function (res) {
                if (res.success) {
                    fotasTaller.splice(idx, 1); renderGaleria();
                    Swal.fire({ icon: 'success', title: 'Eliminada',
                        toast: true, position: 'top-end', timer: 1500, showConfirmButton: false, timerProgressBar: true });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.error, confirmButtonColor: '#059669' });
                }
            }, 'json');
        });
    });

    // ══════════════════════════════════════════════════════════════════════════
    // LIGHTBOX
    // ══════════════════════════════════════════════════════════════════════════
    window.abrirLightbox = function (src) {
        $('#otLbImg').attr('src', src);
        $('#otLbCaption').text('');
        $('#otLbPrev, #otLbNext').hide();
        $('#otLightbox').css('display', 'flex');
    };

    function abrirLb(idx) {
        if (fotasTaller.length === 0) return;
        idx = Math.max(0, Math.min(idx, fotasTaller.length - 1));
        lbIndex = idx;
        $('#otLbImg').attr('src', '/sysinversioneschcomputer/' + fotasTaller[idx]);
        $('#otLbCaption').text('Foto ' + (idx+1) + ' de ' + fotasTaller.length);
        $('#otLbPrev, #otLbNext').toggle(fotasTaller.length > 1);
        $('#otLightbox').css('display', 'flex');
    }

    $('#otLbClose').on('click', function () { $('#otLightbox').hide(); });
    $('#otLightbox').on('click', function (e) { if ($(e.target).is('#otLightbox')) $(this).hide(); });
    $('#otLbPrev').on('click', function (e) { e.stopPropagation(); abrirLb((lbIndex - 1 + fotasTaller.length) % fotasTaller.length); });
    $('#otLbNext').on('click', function (e) { e.stopPropagation(); abrirLb((lbIndex + 1) % fotasTaller.length); });
    $(document).on('keydown', function (e) {
        if ($('#otLightbox').is(':visible')) {
            if (e.key === 'ArrowLeft')  abrirLb((lbIndex - 1 + fotasTaller.length) % fotasTaller.length);
            if (e.key === 'ArrowRight') abrirLb((lbIndex + 1) % fotasTaller.length);
            if (e.key === 'Escape')     $('#otLightbox').hide();
        }
    });

    // ══════════════════════════════════════════════════════════════════════════
    // GUARDAR — validación y confirmación
    // ══════════════════════════════════════════════════════════════════════════
    $('#formOT').on('submit', function (e) {
        var estado = $('#otNuevoEstado').val();
        if (estado === 'listo') {
            e.preventDefault();
            Swal.fire({
                icon: 'question', title: '¿Marcar como Listo?',
                html: 'La orden quedará lista para entrega al cliente.<br><small class="text-muted">Recepción será notificada.</small>',
                showCancelButton: true, confirmButtonColor: '#059669', cancelButtonColor: '#6b7280',
                confirmButtonText: 'Sí, marcar como listo', cancelButtonText: 'Cancelar'
            }).then(function (r) {
                if (r.isConfirmed) { $('#formOT').off('submit').submit(); }
            });
        }
    });

    // Fix modales anidados Bootstrap 4
    $(document).on('hidden.bs.modal', '.modal', function () {
        if ($('.modal.show').length) $('body').addClass('modal-open');
    });

    // ══════════════════════════════════════════════════════════════════════════
    // COTIZACIONES DE REPUESTOS
    // ══════════════════════════════════════════════════════════════════════════

    var cotTipoActual = 'libre';

    var estadoCotLabel = {
        cotizado:         { label: 'Cotizado',              cls: 'cot-estado-cotizado',         icon: 'fa-clock' },
        aprobado:         { label: 'Aprobado',              cls: 'cot-estado-aprobado',         icon: 'fa-check-circle' },
        rechazado:        { label: 'Rechazado',             cls: 'cot-estado-rechazado',        icon: 'fa-times-circle' },
        comprado:         { label: 'Comprado',              cls: 'cot-estado-comprado',         icon: 'fa-shopping-cart' },
        pendiente_compra: { label: 'Pendiente de compra',   cls: 'cot-estado-pendiente-compra', icon: 'fa-exclamation-circle' },
        completado:       { label: 'Cotización completada', cls: 'cot-estado-completado',       icon: 'fa-check-double' }
    };

    // ── Cargar cotizaciones al abrir la página ────────────────────────────────
    function cargarCotizaciones() {
        $.get('ajax_cotizaciones.php', { accion: 'listar', id_orden: ID_ORDEN }, function (res) {
            if (res.success) renderCotizaciones(res.items, res.total);
        }, 'json');
    }
    cargarCotizaciones();

    // ── Renderizar lista ──────────────────────────────────────────────────────
    function renderCotizaciones(items, total) {
        var $lista = $('#cotLista').empty();

        if (!items || items.length === 0) {
            $('#cotVacia').show();
            $('#cotTotalBox').hide();
            $('#cotBadge').hide();
            return;
        }

        $('#cotVacia').hide();
        $('#cotBadge').text(items.length).show();

        items.forEach(function (c) {
            var est   = estadoCotLabel[c.estado] || estadoCotLabel['cotizado'];
            var nombre = c.nombre_producto ? c.nombre_producto : c.descripcion;
            var codigo = c.codigo_producto ? ' <small style="color:#94a3b8;">(' + c.codigo_producto + ')</small>' : '';

            var acciones = '';
            if (c.estado === 'cotizado') {
                acciones =
                    '<button type="button" class="cot-btn-accion cot-btn-aprobar" data-id="' + c.id + '" title="Marcar como aprobado por el cliente">' +
                    '<i class="fas fa-check"></i> Aprobar</button>' +
                    '<button type="button" class="cot-btn-accion cot-btn-rechazar" data-id="' + c.id + '" title="Cliente rechazó">' +
                    '<i class="fas fa-times"></i> Rechazar</button>' +
                    '<button type="button" class="cot-btn-del" data-id="' + c.id + '" title="Eliminar"><i class="fas fa-trash-alt"></i></button>';
            } else if (c.estado === 'aprobado') {
                acciones =
                    '<span style="font-size:.72rem;color:#059669;font-weight:600;"><i class="fas fa-info-circle mr-1"></i>Pendiente de compra</span>' +
                    '<button type="button" class="cot-btn-accion cot-btn-cotizar" data-id="' + c.id + '" title="Volver a cotizado">' +
                    '<i class="fas fa-undo"></i> Revertir</button>';
            } else if (c.estado === 'rechazado') {
                acciones =
                    '<button type="button" class="cot-btn-accion cot-btn-cotizar" data-id="' + c.id + '" title="Volver a cotizado">' +
                    '<i class="fas fa-undo"></i> Reactivar</button>' +
                    '<button type="button" class="cot-btn-del" data-id="' + c.id + '" title="Eliminar"><i class="fas fa-trash-alt"></i></button>';
            } else if (c.estado === 'comprado') {
                acciones = '<span style="font-size:.72rem;color:#2563eb;font-weight:600;"><i class="fas fa-check-double mr-1"></i>Repuesto adquirido</span>';
            } else if (c.estado === 'pendiente_compra') {
                acciones =
                    '<span style="font-size:.72rem;color:#d97706;font-weight:700;"><i class="fas fa-exclamation-circle mr-1"></i>Sin stock — pendiente de compra</span>' +
                    '<button type="button" class="cot-btn-del" data-id="' + c.id + '" title="Eliminar"><i class="fas fa-trash-alt"></i></button>';
            } else if (c.estado === 'completado') {
                acciones = '<span style="font-size:.72rem;color:#059669;font-weight:700;"><i class="fas fa-check-double mr-1"></i>Cotización realizada con éxito</span>';
            }

            var notaHtml = c.nota ? '<div style="font-size:.74rem;color:#64748b;margin-top:4px;"><i class="fas fa-sticky-note mr-1"></i>' + $('<span>').text(c.nota).html() + '</div>' : '';

            $lista.append(
                '<div class="cot-item" data-id="' + c.id + '" data-estado="' + c.estado + '">' +
                '  <div class="cot-item-header">' +
                '    <div class="cot-item-nombre">' + $('<span>').text(nombre).html() + codigo + '</div>' +
                '    <span class="cot-estado-badge ' + est.cls + '"><i class="fas ' + est.icon + ' mr-1"></i>' + est.label + '</span>' +
                '  </div>' +
                '  <div class="cot-item-detalle">' +
                '    <span><i class="fas fa-sort-numeric-up mr-1"></i>Cant: <strong>' + c.cantidad + '</strong></span>' +
                '    <span><i class="fas fa-tag mr-1"></i>P.Unit: <strong>S/. ' + parseFloat(c.precio_unitario).toFixed(2) + '</strong></span>' +
                '    <span class="cot-subtotal">Subtotal: <strong>S/. ' + parseFloat(c.subtotal).toFixed(2) + '</strong></span>' +
                '    <span style="color:#94a3b8;"><i class="fas fa-user mr-1"></i>' + $('<span>').text(c.usuario_nombre || '—').html() + '</span>' +
                '  </div>' +
                notaHtml +
                '  <div class="cot-item-acciones">' + acciones + '</div>' +
                '</div>'
            );
        });

        // Total (aprobados + comprados + pendiente_compra + completado)
        var totalAprobados = items
            .filter(function (c) { return c.estado === 'aprobado' || c.estado === 'comprado' || c.estado === 'pendiente_compra' || c.estado === 'completado'; })
            .reduce(function (s, c) { return s + parseFloat(c.subtotal); }, 0);

        if (totalAprobados > 0) {
            $('#cotTotalVal').text('S/. ' + totalAprobados.toFixed(2));
            $('#cotTotalBox').show();
        } else {
            $('#cotTotalBox').hide();
        }
    }

    // ── Abrir modal nueva cotización ──────────────────────────────────────────
    $('#btnNuevaCotizacion').on('click', function () {
        // Reset form
        cotTipoActual = 'libre';
        $('#cotTipoLibre').css({ background: 'linear-gradient(135deg,#92400e,#d97706)', color: '#fff' });
        $('#cotTipoCatalogo').css({ background: '#f8fafc', color: '#475569' });
        $('#cotBloqueLibre').show();
        $('#cotBloqueProducto').hide();
        $('#cotDescripcion').val('');
        $('#cotIdProducto').val('');
        $('#cotProductoTexto').text('Seleccionar producto...');
        $('#btnLimpiarCotProducto').hide();
        $('#cotCampoProducto').css({ color: '#94a3b8' });
        $('#cotCantidad').val(1);
        $('#cotPrecio').val('');
        $('#cotNota').val('');
        $('#cotSubtotalPreview').text('S/. 0.00');
        $('#modalNuevaCotizacion').modal('show');
    });

    // ── Toggle tipo libre / catálogo ──────────────────────────────────────────
    $('#cotTipoLibre').on('click', function () {
        cotTipoActual = 'libre';
        $(this).css({ background: 'linear-gradient(135deg,#92400e,#d97706)', color: '#fff' });
        $('#cotTipoCatalogo').css({ background: '#f8fafc', color: '#475569' });
        $('#cotBloqueLibre').show();
        $('#cotBloqueProducto').hide();
    });
    $('#cotTipoCatalogo').on('click', function () {
        cotTipoActual = 'catalogo';
        $(this).css({ background: 'linear-gradient(135deg,#92400e,#d97706)', color: '#fff' });
        $('#cotTipoLibre').css({ background: '#f8fafc', color: '#475569' });
        $('#cotBloqueLibre').hide();
        $('#cotBloqueProducto').show();
    });

    // ── Preview subtotal en tiempo real ──────────────────────────────────────
    $('#cotCantidad, #cotPrecio').on('input', function () {
        var cant  = parseInt($('#cotCantidad').val()) || 0;
        var precio = parseFloat($('#cotPrecio').val()) || 0;
        $('#cotSubtotalPreview').text('S/. ' + (cant * precio).toFixed(2));
    });

    // ── Selector de producto para cotización ──────────────────────────────────
    $('#cotCampoProducto').on('click', function () {
        $('#cotBuscarProducto').val('');
        $('.cot-prod-item').show();
        $('#cotSinProd').hide();
        $('#modalCotProducto').modal('show');
    });

    $('#cotBuscarProducto').on('input', function () {
        var q = $(this).val().toLowerCase().trim();
        var vis = 0;
        $('.cot-prod-item').each(function () {
            var nombre = $(this).data('nombre').toLowerCase();
            var codigo = ($(this).data('codigo') || '').toLowerCase();
            var show   = !q || nombre.indexOf(q) > -1 || codigo.indexOf(q) > -1;
            $(this).toggle(show);
            if (show) vis++;
        });
        $('#cotSinProd').toggle(vis === 0);
    });

    $(document).on('click', '.cot-prod-item', function () {
        var id     = $(this).data('id');
        var nombre = $(this).data('nombre');
        var precio = parseFloat($(this).data('precio')) || 0;

        $('#cotIdProducto').val(id);
        $('#cotProductoTexto').text(nombre);
        $('#cotCampoProducto').css({ color: '#1e293b' });
        $('#btnLimpiarCotProducto').show();
        if (precio > 0) $('#cotPrecio').val(precio.toFixed(2));
        $('#cotCantidad').trigger('input'); // actualizar preview
        $('#modalCotProducto').modal('hide');
    });

    $('#btnLimpiarCotProducto').on('click', function () {
        $('#cotIdProducto').val('');
        $('#cotProductoTexto').text('Seleccionar producto...');
        $('#cotCampoProducto').css({ color: '#94a3b8' });
        $(this).hide();
    });

    // ── Guardar cotización ────────────────────────────────────────────────────
    $('#btnGuardarCotizacion').on('click', function () {
        var descripcion = $('#cotDescripcion').val().trim();
        var id_producto = $('#cotIdProducto').val();
        var cantidad    = parseInt($('#cotCantidad').val()) || 0;
        var precio      = parseFloat($('#cotPrecio').val()) || 0;
        var nota        = $('#cotNota').val().trim();

        if (cotTipoActual === 'libre' && !descripcion) {
            Swal.fire({ icon: 'warning', title: 'Descripción requerida', text: 'Escribe el nombre del repuesto.', confirmButtonColor: '#d97706' }); return;
        }
        if (cotTipoActual === 'catalogo' && !id_producto) {
            Swal.fire({ icon: 'warning', title: 'Producto requerido', text: 'Selecciona un producto del inventario.', confirmButtonColor: '#d97706' }); return;
        }
        if (cantidad < 1) {
            Swal.fire({ icon: 'warning', title: 'Cantidad inválida', text: 'La cantidad debe ser al menos 1.', confirmButtonColor: '#d97706' }); return;
        }
        if (precio <= 0) {
            Swal.fire({ icon: 'warning', title: 'Precio requerido', text: 'Ingresa el precio unitario del repuesto.', confirmButtonColor: '#d97706' }); return;
        }

        var $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Guardando...');

        $.post('ajax_cotizaciones.php', {
            accion:          'crear',
            id_orden:        ID_ORDEN,
            id_producto:     id_producto || 0,
            descripcion:     cotTipoActual === 'libre' ? descripcion : '',
            cantidad:        cantidad,
            precio_unitario: precio,
            nota:            nota
        }, function (res) {
            $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Guardar Cotización');
            if (res.success) {
                renderCotizaciones(res.items, res.total);
                $('#modalNuevaCotizacion').modal('hide');
                var esPendiente = res.estado_inicial === 'pendiente_compra';
                Swal.fire({
                    icon: esPendiente ? 'warning' : 'success',
                    title: esPendiente ? 'Sin stock disponible' : 'Cotización registrada',
                    html: esPendiente
                        ? 'El producto <b>no tiene stock suficiente</b>. La cotización quedó como <b>Pendiente de compra</b>. Deberás adquirirlo para completarla.'
                        : 'El repuesto fue agregado y el <b>stock fue descontado</b> del inventario.',
                    toast: !esPendiente,
                    position: esPendiente ? 'center' : 'top-end',
                    timer: esPendiente ? undefined : 2500,
                    showConfirmButton: esPendiente,
                    confirmButtonColor: '#d97706',
                    timerProgressBar: !esPendiente,
                    background: esPendiente ? '#fffbeb' : '#fffbeb',
                    color: '#92400e', iconColor: '#d97706'
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.error, confirmButtonColor: '#d97706' });
            }
        }, 'json').fail(function () {
            $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Guardar Cotización');
            Swal.fire({ icon: 'error', title: 'Error de conexión', confirmButtonColor: '#d97706' });
        });
    });

    // ── Cambiar estado (aprobar / rechazar / revertir) ────────────────────────
    $(document).on('click', '.cot-btn-aprobar', function () {
        var id = $(this).data('id');
        Swal.fire({
            icon: 'question', title: '¿El cliente aprobó este repuesto?',
            html: 'Se marcará como <strong>Aprobado</strong> y quedará pendiente de compra.',
            showCancelButton: true, confirmButtonColor: '#059669', cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="fas fa-check mr-1"></i>Sí, aprobado', cancelButtonText: 'Cancelar'
        }).then(function (r) {
            if (!r.isConfirmed) return;

            // Verificar si la cotización tiene producto del inventario (stock ya descontado)
            // Si tiene id_producto → el stock ya fue reservado → pasar a completado directamente
            var $card = $('.cot-item[data-id="' + id + '"]');
            var tieneProducto = $card.find('.cot-item-nombre small').length > 0; // tiene código de producto

            // Consultar al servidor para saber si tiene id_producto
            $.post('ajax_cotizaciones.php', {
                accion: 'aprobar_inteligente',
                id_orden: ID_ORDEN,
                id_cotizacion: id
            }, function (res) {
                if (res.success) {
                    renderCotizaciones(res.items, res.total);
                    var msg = res.estado_final === 'completado'
                        ? 'El repuesto ya estaba en inventario. <b>Cotización completada.</b>'
                        : 'Repuesto aprobado. Pendiente de compra al proveedor.';
                    Swal.fire({
                        icon: 'success', title: 'Estado actualizado',
                        html: msg,
                        toast: true, position: 'top-end', timer: 3000,
                        showConfirmButton: false, timerProgressBar: true
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.error, confirmButtonColor: '#d97706' });
                }
            }, 'json');
        });
    });

    $(document).on('click', '.cot-btn-rechazar', function () {
        var id = $(this).data('id');
        Swal.fire({
            icon: 'warning', title: '¿El cliente rechazó este repuesto?',
            html: 'Se marcará como <strong>Rechazado</strong>.',
            showCancelButton: true, confirmButtonColor: '#dc2626', cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="fas fa-times mr-1"></i>Sí, rechazado', cancelButtonText: 'Cancelar'
        }).then(function (r) {
            if (r.isConfirmed) cambiarEstadoCot(id, 'rechazado');
        });
    });

    $(document).on('click', '.cot-btn-cotizar', function () {
        cambiarEstadoCot($(this).data('id'), 'cotizado');
    });

    function cambiarEstadoCot(id, estado) {
        $.post('ajax_cotizaciones.php', {
            accion: 'cambiar_estado', id_orden: ID_ORDEN,
            id_cotizacion: id, estado: estado
        }, function (res) {
            if (res.success) {
                renderCotizaciones(res.items, res.total);
                Swal.fire({
                    icon: 'success', title: 'Estado actualizado',
                    toast: true, position: 'top-end', timer: 1500,
                    showConfirmButton: false, timerProgressBar: true
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.error, confirmButtonColor: '#d97706' });
            }
        }, 'json');
    }

    // ── Eliminar cotización ───────────────────────────────────────────────────
    $(document).on('click', '.cot-btn-del', function () {
        var id = $(this).data('id');
        Swal.fire({
            icon: 'warning', title: '¿Eliminar esta cotización?',
            html: 'Esta acción no se puede deshacer.',
            showCancelButton: true, confirmButtonColor: '#dc2626', cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="fas fa-trash-alt mr-1"></i>Sí, eliminar',
            cancelButtonText: 'Cancelar', reverseButtons: true
        }).then(function (r) {
            if (!r.isConfirmed) return;
            $.post('ajax_cotizaciones.php', {
                accion: 'eliminar', id_orden: ID_ORDEN, id_cotizacion: id
            }, function (res) {
                if (res.success) {
                    renderCotizaciones(res.items, res.total);
                    Swal.fire({ icon: 'success', title: 'Eliminada', toast: true, position: 'top-end', timer: 1500, showConfirmButton: false });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.error, confirmButtonColor: '#d97706' });
                }
            }, 'json');
        });
    });

    // Fix modales anidados (cotización + producto)
    $(document).on('hidden.bs.modal', '#modalCotProducto', function () {
        if ($('#modalNuevaCotizacion').hasClass('show')) $('body').addClass('modal-open');
    });

});