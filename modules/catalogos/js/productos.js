/**
 * productos.js — Módulo Gestión de Productos | SysInversiones CH Computer
 */

$(function () {

    // ══════════════════════════════════════════════════════
    // FIX: Modales anidados Bootstrap 4
    // ══════════════════════════════════════════════════════
    $(document).on('show.bs.modal', '.modal', function () {
        var zIdx = 1040 + 20 * ($('.modal.show').length + 1);
        $(this).css('z-index', zIdx);
        setTimeout(function () {
            $('.modal-backdrop').last().css('z-index', zIdx - 1);
        }, 0);
    });
    $(document).on('hidden.bs.modal', '.modal', function () {
        if ($('.modal.show').length > 0) {
            $('body').addClass('modal-open');
        }
    });

    // ── Zoom desde tarjeta grid ───────────────────────────
    $(document).on('click', '.btn-grid-zoom', function (e) {
        e.stopPropagation();
        var $card = $(this).closest('.producto-card, .producto-card-inactivo');
        var imgs  = parsearImgs($card.attr('data-imgs'));
        if (!imgs.length) return;
        lbOpen(imgs, 0);
    });

    // ── DataTable activos ─────────────────────────────────
    if ($('#tablaProductos').length) {
        $('#tablaProductos').DataTable({
            language: {
                search: '<i class="fas fa-search"></i>',
                searchPlaceholder: 'Buscar producto...',
                lengthMenu: 'Mostrar _MENU_ registros',
                info: 'Mostrando _START_ a _END_ de _TOTAL_',
                infoEmpty: 'Sin registros',
                zeroRecords: 'No se encontraron resultados',
                paginate: { previous: '‹', next: '›' }
            },
            responsive: true, autoWidth: false,
            order: [[1, 'asc']], pageLength: 15,
            columnDefs: [{ orderable: false, targets: [-1] }]
        });
    }

    // ── DataTable inactivos ───────────────────────────────
    if ($('#tablaProductosInactivos').length) {
        $('#tablaProductosInactivos').DataTable({
            language: {
                search: '<i class="fas fa-search"></i>',
                searchPlaceholder: 'Buscar producto...',
                lengthMenu: 'Mostrar _MENU_ registros',
                info: 'Mostrando _START_ a _END_ de _TOTAL_',
                infoEmpty: 'Sin registros',
                zeroRecords: 'No se encontraron resultados',
                paginate: { previous: '‹', next: '›' }
            },
            responsive: true, autoWidth: false,
            order: [[1, 'asc']], pageLength: 15,
            columnDefs: [{ orderable: false, targets: [-1] }]
        });
    }

    // ── Toggle Vista Grid / Lista ─────────────────────────
    // Aplica a ambos tabs (activos e inactivos) simultáneamente
    function aplicarVista(vista) {
        if (vista === 'lista') {
            $('#vistaGrid, #vistaGridInactivos').hide();
            $('#vistaLista, #vistaListaInactivos').show();
            $('#btnVistaLista').addClass('active');
            $('#btnVistaGrid').removeClass('active');
        } else {
            $('#vistaLista, #vistaListaInactivos').hide();
            $('#vistaGrid, #vistaGridInactivos').show();
            $('#btnVistaGrid').addClass('active');
            $('#btnVistaLista').removeClass('active');
        }
        localStorage.setItem('prod_vista', vista);
    }

    $('#btnVistaGrid').on('click', function () { aplicarVista('grid'); });
    $('#btnVistaLista').on('click', function () { aplicarVista('lista'); });

    // Restaurar vista guardada
    var vistaGuardada = localStorage.getItem('prod_vista') || 'grid';
    aplicarVista(vistaGuardada);

    // ── Búsqueda en Grid (activos e inactivos) ────────────
    $('#buscarGrid').on('keyup', function () {
        var q = $(this).val().toLowerCase();
        $('.producto-grid-item, .producto-grid-item-inactivo').each(function () {
            $(this).toggle(($(this).data('search') || '').toLowerCase().indexOf(q) > -1);
        });
    });

    // ══════════════════════════════════════════════════════
    // FILTROS AVANZADOS
    // ══════════════════════════════════════════════════════
    var _filtros = { texto: '', categoria: '', stock: '', precioMin: '', precioMax: '' };

    // Abrir modal filtros
    $('#btnAbrirFiltros').on('click', function () {
        // Sincronizar campos con filtros actuales
        $('#filtro_texto').val(_filtros.texto);
        $('#filtro_categoria').val(_filtros.categoria);
        $('input[name="filtro_stock"][value="' + _filtros.stock + '"]').prop('checked', true);
        actualizarChips();
        $('#filtro_precio_min').val(_filtros.precioMin);
        $('#filtro_precio_max').val(_filtros.precioMax);
        $('#modalFiltrosProductos').modal('show');
    });

    // Chips de stock — estilo visual al seleccionar
    function actualizarChips() {
        $('.filtro-stock-chip').each(function () {
            var $input = $(this).find('input');
            if ($input.is(':checked')) {
                $(this).addClass('chip-activo');
            } else {
                $(this).removeClass('chip-activo');
            }
        });
    }
    $(document).on('change', 'input[name="filtro_stock"]', actualizarChips);
    $(document).on('click', '.filtro-stock-chip', function () {
        $(this).find('input').prop('checked', true).trigger('change');
    });

    // Aplicar filtros
    $('#btnAplicarFiltros').on('click', function () {
        _filtros.texto      = $('#filtro_texto').val().trim().toLowerCase();
        _filtros.categoria  = $('#filtro_categoria').val();
        _filtros.stock      = $('input[name="filtro_stock"]:checked').val();
        _filtros.precioMin  = parseFloat($('#filtro_precio_min').val()) || '';
        _filtros.precioMax  = parseFloat($('#filtro_precio_max').val()) || '';

        // Sincronizar campo de búsqueda rápida
        if (_filtros.texto) $('#buscarGrid').val(_filtros.texto);

        aplicarFiltros();
        actualizarBadgeFiltros();
    });

    // Limpiar filtros
    $('#btnLimpiarFiltros').on('click', function () {
        _filtros = { texto: '', categoria: '', stock: '', precioMin: '', precioMax: '' };
        $('#filtro_texto').val('');
        $('#filtro_categoria').val('');
        $('input[name="filtro_stock"][value=""]').prop('checked', true);
        actualizarChips();
        $('#filtro_precio_min').val('');
        $('#filtro_precio_max').val('');
        $('#buscarGrid').val('');
        aplicarFiltros();
        actualizarBadgeFiltros();
    });

    // También filtrar al escribir en el campo rápido
    $('#buscarGrid').on('keyup', function () {
        _filtros.texto = $(this).val().trim().toLowerCase();
        aplicarFiltros();
        actualizarBadgeFiltros();
    });

    function aplicarFiltros() {
        var total = 0;
        $('.producto-grid-item, .producto-grid-item-inactivo').each(function () {
            var $item = $(this);
            var $card = $item.find('.producto-card, .producto-card-inactivo');
            var visible = true;

            // Texto libre (nombre, marca, categoría)
            if (_filtros.texto) {
                var search = ($item.data('search') || '').toLowerCase();
                if (search.indexOf(_filtros.texto) === -1) visible = false;
            }

            // Categoría
            if (visible && _filtros.categoria) {
                var catId = $item.data('categoria-id') || '';
                if (String(catId) !== String(_filtros.categoria)) visible = false;
            }

            // Stock
            if (visible && _filtros.stock) {
                var stock = parseInt($item.data('stock') || 0);
                var smin  = parseInt($item.data('stock-min') || 0);
                if (_filtros.stock === 'agotado' && stock > 0)          visible = false;
                if (_filtros.stock === 'bajo'    && !(stock > 0 && stock <= smin)) visible = false;
                if (_filtros.stock === 'ok'      && !(stock > smin))    visible = false;
            }

            // Precio
            if (visible && _filtros.precioMin !== '') {
                var precio = parseFloat($item.data('precio') || 0);
                if (precio < _filtros.precioMin) visible = false;
            }
            if (visible && _filtros.precioMax !== '') {
                var precio2 = parseFloat($item.data('precio') || 0);
                if (precio2 > _filtros.precioMax) visible = false;
            }

            $item.toggle(visible);
            if (visible) total++;
        });

        // Actualizar contador de resultados
        $('#filtros-resultado').text(total > 0 ? total + ' resultado(s)' : 'Sin resultados').toggle(
            !!(  _filtros.texto || _filtros.categoria || _filtros.stock ||
                 _filtros.precioMin !== '' || _filtros.precioMax !== '')
        );
    }

    function actualizarBadgeFiltros() {
        var count = 0;
        if (_filtros.texto)            count++;
        if (_filtros.categoria)        count++;
        if (_filtros.stock)            count++;
        if (_filtros.precioMin !== '') count++;
        if (_filtros.precioMax !== '') count++;

        var $badge = $('#filtros-activos-badge');
        var $bar   = $('#filtros-activos-bar');

        if (count > 0) {
            $badge.text(count).show();
            $('#btnAbrirFiltros').css({ 'background': '#e67e22', 'border-color': '#e67e22' });
            $bar.css('display', 'flex');
        } else {
            $badge.hide();
            $('#btnAbrirFiltros').css({ 'background': '', 'border-color': '' });
            $bar.hide();
        }
    }

    // Botón quitar filtros desde la barra
    $(document).on('click', '#btnQuitarFiltros', function () {
        $('#btnLimpiarFiltros').trigger('click');
    });

    // ── Zoom desde vista lista (clic en miniatura) ────────
    $(document).on('click', '.lista-img-wrap.has-imgs', function () {
        var imgs = parsearImgs($(this).attr('data-imgs'));
        if (imgs.length) lbOpen(imgs, 0);
    });

    // ── Copiar código al portapapeles ─────────────────────
    $(document).on('click', '.codigo-copiable', function () {
        var $el     = $(this);
        var codigo  = $el.data('codigo');
        if (!codigo) return;

        // Usar Clipboard API si está disponible
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(codigo).then(function () {
                mostrarCopiado($el, codigo);
            });
        } else {
            // Fallback para HTTP
            var $tmp = $('<input>').val(codigo).appendTo('body').select();
            document.execCommand('copy');
            $tmp.remove();
            mostrarCopiado($el, codigo);
        }
    });

    function mostrarCopiado($el, codigo) {
        $el.addClass('copiado');
        $el.find('.codigo-copy-icon').removeClass('fa-copy').addClass('fa-check');
        // Toast discreto
        Swal.fire({
            icon: 'success',
            title: 'Código copiado',
            html: '<code style="font-size:1rem;color:#1a5276;">' + codigo + '</code>',
            toast: true, position: 'bottom-end',
            timer: 1800, timerProgressBar: true,
            showConfirmButton: false,
            background: '#eafaf1', color: '#1a7a4a', iconColor: '#27ae60'
        });
        setTimeout(function () {
            $el.removeClass('copiado');
            $el.find('.codigo-copy-icon').removeClass('fa-check').addClass('fa-copy');
        }, 2000);
    }

    // ── Helper: POST oculto ───────────────────────────────
    function submitAccion(accion, id) {
        var $f = $('<form method="POST" style="display:none;"></form>');
        $f.append('<input type="hidden" name="accion" value="' + accion + '">');
        $f.append('<input type="hidden" name="id_producto" value="' + id + '">');
        $('body').append($f);
        $f.submit();
    }

    // ── Helper: parsear imágenes sin romper ───────────────
    function parsearImgs(raw) {
        if (!raw) return [];
        // Si ya es array, devolverlo
        if (Array.isArray(raw)) return raw;
        // Si es string, intentar parsear JSON
        if (typeof raw === 'string') {
            // Decodificar entidades HTML si las hay
            var decoded = raw
                .replace(/&quot;/g, '"')
                .replace(/&#039;/g, "'")
                .replace(/&amp;/g, '&')
                .replace(/&lt;/g, '<')
                .replace(/&gt;/g, '>');
            try {
                var parsed = JSON.parse(decoded);
                return Array.isArray(parsed) ? parsed : [];
            } catch(e) {
                // Si falla, intentar con el string original
                try {
                    var parsed2 = JSON.parse(raw);
                    return Array.isArray(parsed2) ? parsed2 : [];
                } catch(e2) {
                    return [];
                }
            }
        }
        // Si jQuery ya lo parseó como objeto/array
        if (typeof raw === 'object') {
            return Array.isArray(raw) ? raw : Object.values(raw);
        }
        return [];
    }

    // ── Helper: marcar campo verde permanente ─────────────
    function marcarVerde($input, valor) {
        $input.val(valor).css({
            'border-color': '#27ae60',
            'background'  : '#eafaf1',
            'color'       : '#1a7a4a',
            'font-weight' : '700'
        });
    }

    // ── Helper: resetear campo selector ──────────────────
    function resetCampo($input) {
        $input.val('').css({ 'border-color': '', background: '#fff', color: '', 'font-weight': '' });
    }

    // ── Helper: toast éxito selector ─────────────────────
    function toastExito(tipo, nombre) {
        var labels = { categoria: 'Categoría', proveedor: 'Proveedor' };
        Swal.fire({
            icon: 'success',
            title: '¡' + (labels[tipo] || tipo) + ' seleccionada!',
            html: '<b>' + nombre + '</b> fue agregada correctamente.',
            toast: true, position: 'top-end',
            timer: 2500, timerProgressBar: true,
            showConfirmButton: false,
            background: '#eafaf1', color: '#1a7a4a', iconColor: '#27ae60'
        });
    }

    // ══════════════════════════════════════════════════════
    // SELECTORES: Categoría / Proveedor
    // ══════════════════════════════════════════════════════
    var _prefijo = '';

    window.abrirModalSelector = function (tipo, prefijo) {
        _prefijo = prefijo;
        if (tipo === 'categoria') {
            $('#buscarCategoria').val('').trigger('input');
            $('#modalSelectorCategoria').modal('show');
            $('#modalSelectorCategoria').one('shown.bs.modal', function () { $('#buscarCategoria').focus(); });
        } else if (tipo === 'proveedor') {
            $('#buscarProveedor').val('').trigger('input');
            $('#modalSelectorProveedor').modal('show');
            $('#modalSelectorProveedor').one('shown.bs.modal', function () { $('#buscarProveedor').focus(); });
        }
    };

    window.limpiarSelector = function (tipo, prefijo) {
        $('#' + prefijo + '_id_' + tipo).val('');
        resetCampo($('#' + prefijo + '_nombre_' + tipo));
    };

    // Filtros búsqueda
    $('#buscarCategoria').on('input', function () {
        var q = $(this).val().toLowerCase(), vis = 0;
        $('#listaCategorias .item-categoria').each(function () {
            var m = ($(this).data('nombre') || '').toLowerCase().indexOf(q) > -1
                 || ($(this).data('descripcion') || '').toLowerCase().indexOf(q) > -1;
            $(this).toggle(m); if (m) vis++;
        });
        $('#contCategorias').text(vis);
        $('#sinResultadosCat').toggle(vis === 0);
    });
    $('#btnLimpiarBusqCat').on('click', function () { $('#buscarCategoria').val('').trigger('input').focus(); });

    $('#buscarProveedor').on('input', function () {
        var q = $(this).val().toLowerCase(), vis = 0;
        $('#listaProveedores .item-proveedor').each(function () {
            var m = ($(this).data('nombre')    || '').toLowerCase().indexOf(q) > -1
                 || ($(this).data('telefono')  || '').toLowerCase().indexOf(q) > -1
                 || ($(this).data('direccion') || '').toLowerCase().indexOf(q) > -1;
            $(this).toggle(m); if (m) vis++;
        });
        $('#contProveedores').text(vis);
        $('#sinResultadosProv').toggle(vis === 0);
    });
    $('#btnLimpiarBusqProv').on('click', function () { $('#buscarProveedor').val('').trigger('input').focus(); });

    // Clicks de selección
    $(document).on('click', '.item-categoria', function () {
        var id = $(this).data('id'), nombre = $(this).data('nombre');
        $('#' + _prefijo + '_id_categoria').val(id);
        marcarVerde($('#' + _prefijo + '_nombre_categoria'), nombre);
        $('#modalSelectorCategoria').modal('hide');
        toastExito('categoria', nombre);
    });
    $(document).on('click', '.item-proveedor', function () {
        var id = $(this).data('id'), nombre = $(this).data('nombre');
        $('#' + _prefijo + '_id_proveedor').val(id);
        marcarVerde($('#' + _prefijo + '_nombre_proveedor'), nombre);
        $('#modalSelectorProveedor').modal('hide');
        toastExito('proveedor', nombre);
    });

    // ══════════════════════════════════════════════════════
    // VER PRODUCTO
    // ══════════════════════════════════════════════════════
    $(document).on('click', '.btn-ver-producto', function () {
        var $btn = $(this);
        var d = $btn.data();
        var imgs = parsearImgs($btn.attr('data-imagenes'));

        $('#ver_nombre_producto').text(d.nombre || '—');
        $('#ver_codigo_badge').text(d.codigo || '—');
        $('#ver_marca').text(d.marca || '—');
        $('#ver_modelo').text(d.modelo || '—');
        $('#ver_categoria').text(d.categoria || '—');
        $('#ver_proveedor').text(d.proveedor || '—');
        $('#ver_descripcion').text(d.descripcion || '—');
        $('#ver_precio_venta').text('S/. ' + parseFloat(d.precioVenta || 0).toFixed(2));
        $('#ver_precio_compra').text('S/. ' + parseFloat(d.precioCompra || 0).toFixed(2));

        var stock = parseInt(d.stock || 0), smin = parseInt(d.stockMin || 0);
        var cls = stock <= 0 ? 'stock-critico' : (stock <= smin ? 'stock-bajo' : 'stock-ok');
        var txt = stock <= 0 ? 'Agotado'       : (stock <= smin ? 'Stock bajo' : 'Normal');
        $('#ver_stock').html('<span class="stock-badge ' + cls + '">' + stock + ' uds — ' + txt + '</span>');
        $('#ver_stock_minimo').text(smin + ' uds');
        $('#ver_stock_maximo').text((d.stockMax || 0) + ' uds');
        $('#ver_estado_prod').html(parseInt(d.estado) === 1
            ? '<span style="background:#d4edda;color:#155724;padding:3px 10px;border-radius:20px;font-size:.8rem;font-weight:600;"><i class="fas fa-check-circle mr-1"></i>Activo</span>'
            : '<span style="background:#f8d7da;color:#721c24;padding:3px 10px;border-radius:20px;font-size:.8rem;font-weight:600;"><i class="fas fa-times-circle mr-1"></i>Inactivo</span>'
        );

        // Galería
        var $gallery = $('#ver_gallery'), $mainImg = $('#ver_main_img');
        $gallery.empty();
        if (window._verAutoplay) { clearInterval(window._verAutoplay); window._verAutoplay = null; }

        if (imgs.length > 0) {
            $mainImg.attr('src', imgs[0]).show();
            $('#ver_no_img').hide();
            $.each(imgs, function (i, url) {
                $gallery.append(
                    '<div class="thumb' + (i===0?' active':'') + '" data-src="' + url + '">' +
                    '<img src="' + url + '" alt=""></div>'
                );
            });
            if (imgs.length > 1) {
                var vi = 0;
                window._verAutoplay = setInterval(function () {
                    vi = (vi + 1) % imgs.length;
                    $mainImg.fadeOut(250, function () {
                        $(this).attr('src', imgs[vi]).fadeIn(250);
                    });
                    $gallery.find('.thumb').removeClass('active').eq(vi).addClass('active');
                }, 4000);
            }
        } else {
            $mainImg.hide();
            $('#ver_no_img').show();
        }

        $('#modalVerProducto').modal('show');
    });

    $('#modalVerProducto').on('hidden.bs.modal', function () {
        if (window._verAutoplay) { clearInterval(window._verAutoplay); window._verAutoplay = null; }
    });

    $(document).on('click', '#ver_gallery .thumb', function () {
        if (window._verAutoplay) { clearInterval(window._verAutoplay); window._verAutoplay = null; }
        $('#ver_gallery .thumb').removeClass('active');
        $(this).addClass('active');
        var src = $(this).data('src');
        $('#ver_main_img').fadeOut(200, function() { $(this).attr('src', src).fadeIn(200); });
    });

    // ══════════════════════════════════════════════════════
    // EDITAR PRODUCTO
    // ══════════════════════════════════════════════════════
    $(document).on('click', '.btn-editar-producto', function () {
        var $btn = $(this);
        var d = $btn.data();
        var imgs = parsearImgs($btn.attr('data-imagenes'));

        $('#editar_id_producto').val(d.id);
        $('#editar_codigo').val(d.codigo || '');
        $('#editar_nombre_producto').val(d.nombre || '');
        $('#editar_marca').val(d.marca || '');
        $('#editar_modelo').val(d.modelo || '');
        $('#editar_descripcion').val(d.descripcion || '');

        // Categoría
        $('#editar_id_categoria').val(d.idCategoria || '');
        if (d.idCategoria && d.categoria) {
            marcarVerde($('#editar_nombre_categoria'), d.categoria);
        } else {
            resetCampo($('#editar_nombre_categoria'));
        }

        // Proveedor
        $('#editar_id_proveedor').val(d.idProveedor || '');
        if (d.idProveedor && d.proveedor) {
            marcarVerde($('#editar_nombre_proveedor'), d.proveedor);
        } else {
            resetCampo($('#editar_nombre_proveedor'));
        }

        $('#editar_stock_minimo').val(d.stockMin || 0);
        $('#editar_stock_maximo').val(d.stockMax || 0);
        $('#editar_precio_compra').val(parseFloat(d.precioCompra || 0).toFixed(2));
        $('#editar_precio_venta').val(parseFloat(d.precioVenta || 0).toFixed(2));
        $('#editar_estado').val(String(d.estado));

        renderImagenesActuales(imgs);
        $('#editar_preview_nuevas').empty();
        $('#editar_imagenes').val('');
        $('#formEditarProducto input[name="eliminar_imagen[]"]').remove();

        $('#modalEditarProducto').modal('show');
    });

    // ══════════════════════════════════════════════════════
    // ACCIONES: TACHO (btn-desactivar-producto)
    // Igual que proveedores: dos opciones → desactivar lógico
    // o eliminar permanente de la BD
    // ══════════════════════════════════════════════════════
    $(document).on('click', '.btn-desactivar-producto', function () {
        var id     = $(this).data('id');
        var nombre = $(this).data('nombre');

        Swal.fire({
            icon : 'warning',
            title: 'Eliminar a ' + nombre,
            html : '<p class="mb-2">Elige cómo deseas eliminar este producto:</p>' +
                   '<div class="d-flex justify-content-center mt-3" style="gap:12px;">' +
                       '<button id="swal-logico" class="swal2-confirm swal2-styled" style="background:#e67e22;">' +
                           '<i class="fas fa-ban mr-1"></i> Desactivar' +
                       '</button>' +
                       '<button id="swal-permanente" class="swal2-confirm swal2-styled" style="background:#e74c3c;">' +
                           '<i class="fas fa-trash-alt mr-1"></i> Eliminar permanente' +
                       '</button>' +
                   '</div>',
            showConfirmButton: false,
            showCancelButton : true,
            cancelButtonText : '<i class="fas fa-times mr-1"></i> Cancelar',
            cancelButtonColor: '#6c757d',
            didOpen: function () {
                document.getElementById('swal-logico').addEventListener('click', function () {
                    Swal.close();
                    Swal.fire({
                        icon : 'warning',
                        title: '¿Desactivar producto?',
                        html : '<b>' + nombre + '</b> pasará a la lista de inactivos.<br>Podrás reactivarlo cuando quieras.',
                        showCancelButton   : true,
                        confirmButtonColor : '#e67e22',
                        cancelButtonColor  : '#6c757d',
                        confirmButtonText  : 'Sí, desactivar',
                        cancelButtonText   : 'Cancelar'
                    }).then(function (r) { if (r.isConfirmed) submitAccion('desactivar', id); });
                });

                document.getElementById('swal-permanente').addEventListener('click', function () {
                    Swal.close();
                    Swal.fire({
                        icon : 'error',
                        title: '¿Eliminar permanentemente?',
                        html : '<b>' + nombre + '</b> será eliminado de la base de datos.<br><strong class="text-danger">Esta acción NO se puede deshacer.</strong>',
                        showCancelButton   : true,
                        confirmButtonColor : '#e74c3c',
                        cancelButtonColor  : '#6c757d',
                        confirmButtonText  : '<i class="fas fa-trash-alt mr-1"></i> Sí, eliminar',
                        cancelButtonText   : 'Cancelar'
                    }).then(function (r) { if (r.isConfirmed) submitAccion('eliminar_permanente', id); });
                });
            }
        });
    });

    // ── REACTIVAR (inactivos) ─────────────────────────────
    $(document).on('click', '.btn-reactivar-producto', function () {
        var id     = $(this).data('id');
        var nombre = $(this).data('nombre');
        Swal.fire({
            icon : 'question',
            title: '¿Reactivar producto?',
            html : '<b>' + nombre + '</b> volverá a la lista de productos activos.',
            showCancelButton   : true,
            confirmButtonColor : '#1a7a4a',
            cancelButtonColor  : '#6c757d',
            confirmButtonText  : '<i class="fas fa-check mr-1"></i> Sí, reactivar',
            cancelButtonText   : 'Cancelar'
        }).then(function (r) { if (r.isConfirmed) submitAccion('reactivar', id); });
    });

    // ── ELIMINAR PERMANENTE desde inactivos ───────────────
    $(document).on('click', '.btn-eliminar-producto', function () {
        var id     = $(this).data('id');
        var nombre = $(this).data('nombre');
        Swal.fire({
            icon : 'error',
            title: '¿Eliminar permanentemente?',
            html : '<b>' + nombre + '</b> será eliminado de la base de datos.<br><strong class="text-danger">Esta acción NO se puede deshacer.</strong>',
            showCancelButton   : true,
            confirmButtonColor : '#e74c3c',
            cancelButtonColor  : '#6c757d',
            confirmButtonText  : '<i class="fas fa-trash-alt mr-1"></i> Sí, eliminar',
            cancelButtonText   : 'Cancelar'
        }).then(function (r) { if (r.isConfirmed) submitAccion('eliminar_permanente', id); });
    });

    // ══════════════════════════════════════════════════════
    // UPLOAD IMÁGENES
    // ══════════════════════════════════════════════════════
    function setupUpload(prefijo) {
        var $area  = $('#' + prefijo + '_upload_area');
        var $input = $('#' + prefijo + '_imagenes');
        $area.on('click', function () { $input.trigger('click'); });
        $area.on('dragover', function (e) { e.preventDefault(); $area.css('border-color', '#2980b9'); });
        $area.on('dragleave', function () { $area.css('border-color', '#aed6f1'); });
        $area.on('drop', function (e) {
            e.preventDefault(); $area.css('border-color', '#aed6f1');
            procesarArchivos(e.originalEvent.dataTransfer.files, prefijo);
        });
        $input.on('change', function () { procesarArchivos(this.files, prefijo); });
    }
    setupUpload('crear');
    setupUpload('editar');

    function procesarArchivos(files, prefijo) {
        var $preview    = $('#' + prefijo + '_preview_nuevas');
        var actuales    = $('#' + prefijo + '_imagenes_actuales .preview-item').length;
        var enPreview   = $preview.find('.preview-item').length;
        var disponibles = 7 - actuales - enPreview;
        $.each(files, function (i, file) {
            if (disponibles <= 0) {
                Swal.fire({ icon: 'warning', title: 'Límite alcanzado', text: 'Máximo 7 imágenes por producto.', confirmButtonColor: '#1a7a4a' });
                return false;
            }
            if (!file.type.startsWith('image/')) return;
            if (file.size > 2 * 1024 * 1024) {
                Swal.fire({ icon: 'warning', title: 'Imagen muy grande', text: file.name + ' supera 2 MB.', confirmButtonColor: '#1a7a4a' });
                return;
            }
            var reader = new FileReader();
            reader.onload = function (e) {
                var $item = $('<div class="preview-item"><img src="' + e.target.result + '" alt=""><button type="button" class="btn-remove-img"><i class="fas fa-times"></i></button></div>');
                $item.find('.btn-remove-img').on('click', function () { $item.remove(); });
                $preview.append($item);
            };
            reader.readAsDataURL(file);
            disponibles--;
        });
    }

    // ══════════════════════════════════════════════════════
    // RENDER IMÁGENES ACTUALES — con orden, zoom y drag
    // ══════════════════════════════════════════════════════
    function renderImagenesActuales(imgs) {
        var $cont = $('#editar_imagenes_actuales');
        $cont.empty();
        if (imgs.length === 0) {
            $cont.append(
                '<div style="text-align:center;padding:20px;color:#999;">' +
                '<i class="fas fa-image" style="font-size:2rem;opacity:.3;display:block;margin-bottom:8px;"></i>' +
                '<small>No hay imágenes guardadas</small></div>'
            );
            return;
        }
        $.each(imgs, function (i, url) {
            var esPrincipal = (i === 0);
            var $item = $(
                '<div class="preview-item' + (esPrincipal ? ' principal' : '') + '" ' +
                    'draggable="true" data-url="' + url + '" data-index="' + i + '">' +
                    '<img src="' + url + '" alt="Imagen ' + (i+1) + '">' +
                    '<span class="orden-badge">' + (i+1) + '</span>' +
                    '<button type="button" class="btn-zoom-img" title="Ver en grande" data-url="' + url + '">' +
                        '<i class="fas fa-search-plus"></i>' +
                    '</button>' +
                    '<button type="button" class="btn-remove-img btn-eliminar-img-actual" data-url="' + url + '">' +
                        '<i class="fas fa-times"></i>' +
                    '</button>' +
                    (esPrincipal ? '<div class="badge-principal">Principal</div>' : '') +
                '</div>'
            );
            $cont.append($item);
        });
        initSortable($cont);
    }

    // ── Actualizar badges de orden y clase principal ──────
    function actualizarOrdenBadges($cont) {
        $cont.find('.preview-item').each(function (i) {
            $(this).attr('data-index', i);
            $(this).find('.orden-badge').text(i + 1);
            if (i === 0) {
                $(this).addClass('principal');
                if ($(this).find('.badge-principal').length === 0) {
                    $(this).append('<div class="badge-principal">Principal</div>');
                }
            } else {
                $(this).removeClass('principal');
                $(this).find('.badge-principal').remove();
            }
        });
    }

    // ══════════════════════════════════════════════════════
    // DRAG & DROP — reordenar imágenes actuales
    // ══════════════════════════════════════════════════════
    function initSortable($cont) {
        var $items  = $cont.find('.preview-item[draggable]');
        var dragSrc = null;

        $items.off('dragstart dragend dragenter drop');
        $cont.off('dragover dragleave');

        $items.on('dragstart', function (e) {
            dragSrc = this;
            $(this).addClass('dragging');
            e.originalEvent.dataTransfer.effectAllowed = 'move';
            e.originalEvent.dataTransfer.setData('text/plain', '');
        });

        $items.on('dragend', function () {
            $(this).removeClass('dragging');
            $cont.find('.preview-item').removeClass('drag-target');
            $cont.removeClass('drag-over');
            dragSrc = null;
        });

        $cont.on('dragover', function (e) {
            e.preventDefault();
            e.originalEvent.dataTransfer.dropEffect = 'move';
            $(this).addClass('drag-over');
        });

        $cont.on('dragleave', function (e) {
            if (!$.contains(this, e.relatedTarget)) {
                $(this).removeClass('drag-over');
            }
        });

        $items.on('dragenter', function (e) {
            e.preventDefault();
            if (this !== dragSrc) {
                $cont.find('.preview-item').removeClass('drag-target');
                $(this).addClass('drag-target');
            }
        });

        $items.on('drop', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $cont.removeClass('drag-over');
            if (dragSrc && this !== dragSrc) {
                var $src    = $(dragSrc);
                var $target = $(this);
                var srcIdx  = $src.index();
                var tgtIdx  = $target.index();
                if (srcIdx < tgtIdx) {
                    $src.insertAfter($target);
                } else {
                    $src.insertBefore($target);
                }
                actualizarOrdenBadges($cont);
                initSortable($cont);
            }
        });
    }

    // ══════════════════════════════════════════════════════
    // LIGHTBOX — zoom de imágenes (solo modal Editar)
    // ══════════════════════════════════════════════════════
    var _lb = { imgs: [], index: 0 };

    function lbOpen(imgs, startIndex) {
        _lb.imgs  = imgs;
        _lb.index = startIndex || 0;
        lbRender();
        $('#imgLightbox').addClass('active');
        $('body').css('overflow', 'hidden');
    }

    function lbClose() {
        $('#imgLightbox').removeClass('active');
        $('body').css('overflow', '');
    }

    function lbRender() {
        var url   = _lb.imgs[_lb.index];
        var total = _lb.imgs.length;

        var $img = $('#lbMainImg');
        $img.css('opacity', 0).attr('src', url);
        $img.off('load').on('load', function () { $img.animate({ opacity: 1 }, 180); });
        if ($img[0].complete) $img.css('opacity', 1);

        $('#lbCounter').text(total > 1 ? (_lb.index + 1) + ' / ' + total : '');
        $('#lbPrev').toggleClass('hidden', total <= 1 || _lb.index === 0);
        $('#lbNext').toggleClass('hidden', total <= 1 || _lb.index === total - 1);

        var $thumbs = $('#lbThumbs').empty();
        if (total > 1) {
            $.each(_lb.imgs, function (i, u) {
                $thumbs.append(
                    '<div class="lb-thumb' + (i === _lb.index ? ' active' : '') + '" data-index="' + i + '">' +
                    '<img src="' + u + '" alt=""></div>'
                );
            });
        }
    }

    // Abrir lightbox al hacer clic en btn-zoom-img
    $(document).on('click', '#editar_imagenes_actuales .btn-zoom-img', function (e) {
        e.stopPropagation();
        var clickedUrl = $(this).data('url');
        var imgs = [];
        $('#editar_imagenes_actuales .preview-item').each(function () {
            imgs.push($(this).data('url'));
        });
        var idx = imgs.indexOf(clickedUrl);
        lbOpen(imgs, idx < 0 ? 0 : idx);
    });

    // Controles lightbox
    $('#lbClose').on('click', lbClose);
    $('#imgLightbox').on('click', function (e) {
        if ($(e.target).is('#imgLightbox')) lbClose();
    });
    $('#lbPrev').on('click', function () {
        if (_lb.index > 0) { _lb.index--; lbRender(); }
    });
    $('#lbNext').on('click', function () {
        if (_lb.index < _lb.imgs.length - 1) { _lb.index++; lbRender(); }
    });
    $(document).on('click', '#lbThumbs .lb-thumb', function () {
        _lb.index = parseInt($(this).data('index'));
        lbRender();
    });
    $(document).on('keydown.lightbox', function (e) {
        if (!$('#imgLightbox').hasClass('active')) return;
        if (e.key === 'Escape')     lbClose();
        if (e.key === 'ArrowLeft')  { if (_lb.index > 0) { _lb.index--; lbRender(); } }
        if (e.key === 'ArrowRight') { if (_lb.index < _lb.imgs.length - 1) { _lb.index++; lbRender(); } }
    });

    $(document).on('click', '.btn-eliminar-img-actual', function () {
        var url   = $(this).data('url');
        var $item = $(this).closest('.preview-item');
        Swal.fire({
            icon: 'warning', title: '¿Eliminar imagen?', text: 'Esta imagen se eliminará al guardar.',
            showCancelButton: true, confirmButtonColor: '#e74c3c', cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar'
        }).then(function (r) {
            if (r.isConfirmed) {
                $('<input type="hidden" name="eliminar_imagen[]">').val(url).appendTo('#formEditarProducto');
                $item.remove();
            }
        });
    });

    // ── Limpiar modal Crear al abrir ──────────────────────
    $('#modalCrearProducto').on('show.bs.modal', function () {
        $(this).find('form')[0].reset();
        $('#crear_preview_nuevas').empty();
        ['categoria', 'proveedor'].forEach(function (t) {
            $('#crear_id_' + t).val('');
            resetCampo($('#crear_nombre_' + t));
        });
    });

    // ── Al guardar editar: serializar orden de imágenes ───
    $('#formEditarProducto').on('submit', function () {
        // Eliminar campos de orden previos
        $(this).find('input[name="imagenes_orden[]"]').remove();
        // Agregar el orden actual de las imágenes actuales (las que no se eliminaron)
        $('#editar_imagenes_actuales .preview-item').each(function () {
            var url = $(this).data('url');
            if (url) {
                $('<input type="hidden" name="imagenes_orden[]">').val(url).appendTo('#formEditarProducto');
            }
        });
    });
});


    // ══════════════════════════════════════════════════════════════════════════
    // EXPORTACIÓN — CSV / Excel / PDF
    // ══════════════════════════════════════════════════════════════════════════

    // Detecta qué tab está activo
    function getEstadoProd() {
        return $('#tab-inactivos-prod').hasClass('active') ? 'inactivo' : 'activo';
    }

    $('#btn-exportar-csv-prod').on('click', function () {
        window.location.href = 'ajax_productos_export.php?exportar=csv&estado=' + getEstadoProd();
    });

    $('#btn-exportar-excel-prod').on('click', function () {
        window.location.href = 'ajax_productos_export.php?exportar=excel&estado=' + getEstadoProd();
    });

    $('#btn-exportar-pdf-prod').on('click', function () {
        window.open('productos_pdf.php?estado=' + getEstadoProd(), '_blank');
    });
