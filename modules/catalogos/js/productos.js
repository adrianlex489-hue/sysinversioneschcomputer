/**
 * productos.js — Módulo Gestión de Productos | Botica 2026
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

    // ══════════════════════════════════════════════════════
    // CARRUSEL AUTOMÁTICO EN VISTA GRID (TARJETAS)
    // ══════════════════════════════════════════════════════
    function iniciarCarruselGrid() {
        $('.producto-card[data-imgs]').each(function() {
            var $card = $(this);
            var imgs = parsearImgs($card.attr('data-imgs'));
            
            if (imgs.length > 1) {
                var $img = $card.find('.card-img-top');
                var currentIndex = 0;
                
                // Guardar el intervalo en el elemento
                var interval = setInterval(function() {
                    currentIndex = (currentIndex + 1) % imgs.length;
                    $img.fadeOut(200, function() {
                        $img.attr('src', imgs[currentIndex]).fadeIn(200);
                    });
                }, 4000); // Cambiar cada 4 segundos
                
                $card.data('carrusel-interval', interval);
            }
        });
    }

    // Detener carruseles al cambiar de vista
    function detenerCarruselGrid() {
        $('.producto-card[data-imgs]').each(function() {
            var interval = $(this).data('carrusel-interval');
            if (interval) {
                clearInterval(interval);
                $(this).removeData('carrusel-interval');
            }
        });
    }

    // ── DataTable ─────────────────────────────────────────
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

    // ── Toggle Vista Grid / Lista ─────────────────────────
    $('#btnVistaGrid').on('click', function () {
        $('#vistaGrid').show(); $('#vistaLista').hide();
        $(this).addClass('active'); $('#btnVistaLista').removeClass('active');
        localStorage.setItem('prod_vista', 'grid');
        iniciarCarruselGrid();
    });
    $('#btnVistaLista').on('click', function () {
        $('#vistaLista').show(); $('#vistaGrid').hide();
        $(this).addClass('active'); $('#btnVistaGrid').removeClass('active');
        localStorage.setItem('prod_vista', 'lista');
        detenerCarruselGrid();
    });
    if (localStorage.getItem('prod_vista') === 'lista') $('#btnVistaLista').trigger('click');
    else { $('#btnVistaGrid').trigger('click'); }

    // ── Búsqueda en Grid ──────────────────────────────────
    $('#buscarGrid').on('keyup', function () {
        var q = $(this).val().toLowerCase();
        $('.producto-grid-item').each(function () {
            $(this).toggle(($(this).data('search') || '').toLowerCase().indexOf(q) > -1);
        });
    });

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
        var labels = { categoria: 'Categoría', unidad: 'Unidad', proveedor: 'Proveedor' };
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
    // SELECTORES: Categoría / Unidad / Proveedor
    // ══════════════════════════════════════════════════════
    var _tipo = '', _prefijo = '';

    window.abrirModalSelector = function (tipo, prefijo) {
        _tipo    = tipo;
        _prefijo = prefijo;
        if (tipo === 'categoria') {
            $('#buscarCategoria').val('').trigger('input');
            $('#modalSelectorCategoria').modal('show');
            $('#modalSelectorCategoria').one('shown.bs.modal', function () { $('#buscarCategoria').focus(); });
        } else if (tipo === 'unidad') {
            $('#buscarUnidad').val('').trigger('input');
            $('#modalSelectorUnidad').modal('show');
            $('#modalSelectorUnidad').one('shown.bs.modal', function () { $('#buscarUnidad').focus(); });
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

    $('#buscarUnidad').on('input', function () {
        var q = $(this).val().toLowerCase(), vis = 0;
        $('#listaUnidades .item-unidad').each(function () {
            var m = ($(this).data('nombre') || '').toLowerCase().indexOf(q) > -1;
            $(this).toggle(m); if (m) vis++;
        });
        $('#contUnidades').text(vis);
        $('#sinResultadosUni').toggle(vis === 0);
    });
    $('#btnLimpiarBusqUni').on('click', function () { $('#buscarUnidad').val('').trigger('input').focus(); });

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
    $(document).on('click', '.item-unidad', function () {
        var id = $(this).data('id'), nombre = $(this).data('nombre');
        $('#' + _prefijo + '_id_unidad').val(id);
        marcarVerde($('#' + _prefijo + '_nombre_unidad'), nombre);
        $('#modalSelectorUnidad').modal('hide');
        toastExito('unidad', nombre);
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
        // Leer imagenes con .attr() para evitar que jQuery auto-parsee y rompa entidades HTML
        var imgs = parsearImgs($btn.attr('data-imagenes'));

        $('#ver_nombre_producto').text(d.nombre || '—');
        $('#ver_codigo_badge').text(d.codigo || '—');
        $('#ver_badge_receta').toggle(parseInt(d.receta) === 1);
        $('#ver_laboratorio').text(d.laboratorio || '—');
        $('#ver_presentacion').text(d.presentacion || '—');
        $('#ver_concentracion').text(d.concentracion || '—');
        $('#ver_categoria').text(d.categoria || '—');
        $('#ver_unidad').text(d.unidad || '—');
        $('#ver_proveedor').text(d.proveedor || '—');
        $('#ver_registro_sanitario').text(d.registro || '—');
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
            // Carrusel automático cada 4 segundos si hay más de 1 imagen
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

    // Click manual en miniatura — detiene autoplay
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
        // Usar .attr() para leer el JSON crudo sin que jQuery lo interprete mal
        var imgs = parsearImgs($btn.attr('data-imagenes'));

        $('#editar_id_producto').val(d.id);
        $('#editar_codigo').val(d.codigo || '');
        $('#editar_codigo_barra').val(d.codigoBarra || '');
        $('#editar_nombre_producto').val(d.nombre || '');
        $('#editar_laboratorio').val(d.laboratorio || '');
        $('#editar_presentacion').val(d.presentacion || '');
        $('#editar_concentracion').val(d.concentracion || '');
        $('#editar_descripcion').val(d.descripcion || '');

        // Categoría
        $('#editar_id_categoria').val(d.idCategoria || '');
        if (d.idCategoria && d.categoria) {
            marcarVerde($('#editar_nombre_categoria'), d.categoria);
        } else {
            resetCampo($('#editar_nombre_categoria'));
        }

        // Unidad
        $('#editar_id_unidad').val(d.idUnidad || '');
        if (d.idUnidad && d.unidad) {
            marcarVerde($('#editar_nombre_unidad'), d.unidad);
        } else {
            resetCampo($('#editar_nombre_unidad'));
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
        $('#editar_requiere_receta').val(d.receta || 0);
        $('#editar_registro_sanitario').val(d.registro || '');
        $('#editar_estado').val(String(d.estado));

        // Imágenes actuales — usar imgs ya parseado con .attr()
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
        var disponibles = 4 - actuales - enPreview;
        $.each(files, function (i, file) {
            if (disponibles <= 0) {
                Swal.fire({ icon: 'warning', title: 'Límite alcanzado', text: 'Máximo 4 imágenes por producto.', confirmButtonColor: '#1a7a4a' });
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

    function renderImagenesActuales(imgs) {
        var $cont = $('#editar_imagenes_actuales');
        $cont.empty();
        if (imgs.length === 0) {
            $cont.append('<div style="text-align:center;padding:20px;color:#999;"><i class="fas fa-image" style="font-size:2rem;opacity:.3;display:block;margin-bottom:8px;"></i><small>No hay imágenes guardadas</small></div>');
            return;
        }
        $.each(imgs, function (i, url) {
            var $item = $('<div class="preview-item' + (i===0?' principal':'') + '" data-url="' + url + '">' +
                '<img src="' + url + '" alt="">' +
                '<button type="button" class="btn-remove-img btn-eliminar-img-actual" data-url="' + url + '"><i class="fas fa-times"></i></button>' +
                (i===0 ? '<div class="badge-principal">Principal</div>' : '') +
            '</div>');
            $cont.append($item);
        });
    }

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
        ['categoria', 'unidad', 'proveedor'].forEach(function (t) {
            $('#crear_id_' + t).val('');
            resetCampo($('#crear_nombre_' + t));
        });
    });
});
