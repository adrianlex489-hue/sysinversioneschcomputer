/* inventario.js — Módulo Stock General | SysInversiones CH Computer 2026 */

$(function () {

    // ══════════════════════════════════════════════════════════════════════════
    // DataTable
    // ══════════════════════════════════════════════════════════════════════════
    var tabla = null;

    if ($("#tablaInventario").length) {
        tabla = $("#tablaInventario").DataTable({
            language: {
                search:            "",
                searchPlaceholder: "Buscar...",
                lengthMenu:        "Mostrar _MENU_ registros",
                info:              "Mostrando _START_ a _END_ de _TOTAL_ registros",
                infoEmpty:         "Sin registros disponibles",
                infoFiltered:      "(filtrado de _MAX_ registros totales)",
                zeroRecords:       "No se encontraron resultados",
                emptyTable:        "No hay datos disponibles",
                paginate: {
                    previous: "&#8249;",
                    next:     "&#8250;"
                }
            },
            responsive:  true,
            autoWidth:   false,
            order:       [[2, "asc"]],
            pageLength:  25,
            columnDefs:  [
                { orderable: false, targets: [1, -1] },
                { width: "58px",  targets: 1 },
                { width: "130px", targets: 5 }   // columna proveedor
            ],
            dom: '<"d-flex justify-content-between align-items-center mb-2"lf>rtip'
        });
        // Ocultar el buscador nativo de DataTables (usamos el personalizado)
        $(".dataTables_filter").hide();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Buscador personalizado #buscarInventario
    // ══════════════════════════════════════════════════════════════════════════
    $("#buscarInventario").on("input", function () {
        aplicarFiltrosCombinados();
    });

    // ══════════════════════════════════════════════════════════════════════════
    // Filtros rápidos de stock
    // ══════════════════════════════════════════════════════════════════════════
    var filtroActivo = "todos";

    $(document).on("click", ".btn-filtro-inv", function () {
        $(".btn-filtro-inv").removeClass("active");
        $(this).addClass("active");
        filtroActivo = $(this).data("filtro");
        aplicarFiltrosCombinados();
    });

    // ══════════════════════════════════════════════════════════════════════════
    // Filtro por categoría — Modal profesional
    // ══════════════════════════════════════════════════════════════════════════
    var catSeleccionada = "";   // valor en minúsculas
    var catNombreLabel  = "Todas las categorías";

    // Abrir modal al pulsar el botón
    $("#btnFiltroCategoria").on("click", function () {
        actualizarContadorCatModal();
        $("#modalFiltroCategoria").modal("show");
        setTimeout(function () { $("#buscarCategoriaModal").focus(); }, 350);
    });

    // Marcar la opción activa al abrir el modal
    $("#modalFiltroCategoria").on("show.bs.modal", function () {
        $(".cat-modal-item").removeClass("active");
        $(".cat-modal-item[data-cat='" + catSeleccionada + "']").addClass("active");
        $("#buscarCategoriaModal").val("");
        $(".cat-modal-item").show();
        actualizarContadorCatModal();
    });

    // Seleccionar categoría al hacer clic en un ítem
    $(document).on("click", ".cat-modal-item", function () {
        $(".cat-modal-item").removeClass("active");
        $(this).addClass("active");
        catSeleccionada  = $(this).data("cat");
        catNombreLabel   = $(this).data("nombre");
        $("#filtroCategoriaInv").val(catSeleccionada);
        // Actualizar etiqueta del botón
        var label = catSeleccionada ? catNombreLabel : "Todas las categorías";
        $("#lblFiltroCategoria").text(label);
        // Resaltar botón si hay filtro activo
        if (catSeleccionada) {
            $("#btnFiltroCategoria").addClass("activo-cat");
        } else {
            $("#btnFiltroCategoria").removeClass("activo-cat");
        }
        $("#modalFiltroCategoria").modal("hide");
        aplicarFiltrosCombinados();
    });

    // Limpiar filtro desde el footer del modal
    $("#btnLimpiarCategoria").on("click", function () {
        catSeleccionada = "";
        catNombreLabel  = "Todas las categorías";
        $("#filtroCategoriaInv").val("");
        $("#lblFiltroCategoria").text("Todas las categorías");
        $("#btnFiltroCategoria").removeClass("activo-cat");
        $(".cat-modal-item").removeClass("active");
        $(".cat-modal-todas").addClass("active");
        $("#modalFiltroCategoria").modal("hide");
        aplicarFiltrosCombinados();
    });

    // Buscador dentro del modal
    $("#buscarCategoriaModal").on("input", function () {
        var q = $(this).val().toLowerCase().trim();
        $(".cat-modal-item").each(function () {
            var nombre = ($(this).data("nombre") || "").toLowerCase();
            $(this).toggle(!q || nombre.indexOf(q) > -1);
        });
        // Siempre mostrar "Todas"
        if (q) $(".cat-modal-todas").show();
        actualizarContadorCatModal();
    });

    function actualizarContadorCatModal() {
        var visible = $(".cat-modal-item:visible").length;
        $("#contadorCatModal").text(visible + " categoría" + (visible !== 1 ? "s" : ""));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Filtros combinados: stock + categoría + búsqueda de texto
    // ══════════════════════════════════════════════════════════════════════════
    function aplicarFiltrosCombinados() {
        var q         = ($("#buscarInventario").val() || "").toLowerCase().trim();
        var catFiltro = ($("#filtroCategoriaInv").val() || "").toLowerCase().trim();

        if (tabla) {
            tabla.search(q).draw();
            tabla.rows().every(function () {
                var $tr     = $(this.node());
                var okStock = (filtroActivo === "todos" || $tr.data("filtro") === filtroActivo);
                var okCat   = (!catFiltro || ($tr.data("categoria") || "").toLowerCase() === catFiltro);
                $tr.toggle(okStock && okCat);
            });
        } else {
            $(".fila-inv").each(function () {
                var $tr     = $(this);
                var texto   = ($tr.data("search") || "").toLowerCase();
                var okBusq  = (!q || texto.indexOf(q) > -1);
                var okStock = (filtroActivo === "todos" || $tr.data("filtro") === filtroActivo);
                var okCat   = (!catFiltro || ($tr.data("categoria") || "").toLowerCase() === catFiltro);
                $tr.toggle(okBusq && okStock && okCat);
            });
        }
        actualizarContador();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Contador de filas visibles
    // ══════════════════════════════════════════════════════════════════════════
    function actualizarContador() {
        var v = $(".fila-inv:visible").length;
        $("#contadorInventario").text(v + " producto" + (v !== 1 ? "s" : ""));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Handler: Ver detalle del producto (AJAX)
    // ══════════════════════════════════════════════════════════════════════════
    $(document).on("click", ".btn-ver-producto-inv", function () {
        var id = $(this).data("id");

        // Mostrar spinner mientras carga
        $("#modalVerProductoInv .modal-body").html(
            '<div class="text-center py-5">' +
            '<i class="fas fa-spinner fa-spin fa-2x text-muted"></i>' +
            '<p class="mt-3 text-muted" style="font-size:.88rem;">Cargando detalle del producto...</p>' +
            '</div>'
        );
        $("#modalVerProductoInv").modal("show");

        $.get("inventario.php", { accion: "detalle_ajax", id_producto: id })
            .done(function (html) {
                $("#modalVerProductoInv .modal-body").html(html);
                // Activar botón "Ver más" en historial si existe
                $(document).off("click.vermas").on("click.vermas", "#btnVerMasMovs", function () {
                    $("#invMovsExtra").slideDown(250);
                    $(this).hide();
                });
            })
            .fail(function () {
                $("#modalVerProductoInv .modal-body").html(
                    '<div class="alert alert-danger">' +
                    '<i class="fas fa-exclamation-circle mr-2"></i>' +
                    'Error al cargar el detalle. Intenta nuevamente.' +
                    '</div>'
                );
            });
    });

    // ══════════════════════════════════════════════════════════════════════════
    // Handler: Abrir modal ajuste de stock
    // ══════════════════════════════════════════════════════════════════════════
    $(document).on("click", ".btn-ajustar-stock", function () {
        var id     = $(this).data("id");
        var nombre = $(this).data("nombre");
        var stock  = parseInt($(this).data("stock")) || 0;

        // Poblar datos del modal
        $("#ajuste_id_producto").val(id);
        $("#ajuste_nombre_producto").text(nombre);
        $("#ajuste_stock_actual").text(stock + " uds");
        $("#ajuste_cantidad").val("");
        $("#ajuste_motivo").val("");
        $("#ajuste_tipo").val("entrada");

        // Reset botones tipo
        $(".btn-tipo-ajuste").removeClass("activo");
        $(".btn-tipo-ajuste.entrada").addClass("activo");

        // Ocultar preview
        $("#preview_nuevo_stock").hide();

        // Resetear botón submit
        $("#btnSubmitAjuste")
            .prop("disabled", false)
            .html('<i class="fas fa-save mr-1"></i>Guardar Ajuste');

        $("#modalAjustarStock").modal("show");
        $("#modalAjustarStock").one("shown.bs.modal", function () {
            $("#ajuste_cantidad").focus();
        });
    });

    // ══════════════════════════════════════════════════════════════════════════
    // Selector de tipo de ajuste con clase 'activo'
    // ══════════════════════════════════════════════════════════════════════════
    $(document).on("click", ".btn-tipo-ajuste", function () {
        $(".btn-tipo-ajuste").removeClass("activo");
        $(this).addClass("activo");
        $("#ajuste_tipo").val($(this).data("tipo"));
        actualizarPreviewAjuste();
    });

    // ══════════════════════════════════════════════════════════════════════════
    // Preview del nuevo stock en #preview_nuevo_stock
    // ══════════════════════════════════════════════════════════════════════════
    $("#ajuste_cantidad").on("input", actualizarPreviewAjuste);

    function actualizarPreviewAjuste() {
        var tipo     = $("#ajuste_tipo").val();
        var cantidad = parseInt($("#ajuste_cantidad").val()) || 0;
        var stockStr = $("#ajuste_stock_actual").text().replace(" uds", "").trim();
        var stock    = parseInt(stockStr) || 0;

        var nuevo;
        if (tipo === "entrada") {
            nuevo = stock + cantidad;
        } else if (tipo === "salida") {
            nuevo = Math.max(0, stock - cantidad);
        } else {
            // corrección directa
            nuevo = cantidad;
        }

        if (cantidad > 0) {
            var color = tipo === "entrada"    ? "#27ae60"
                      : tipo === "salida"     ? "#e74c3c"
                      : "#2980b9";
            var icono = tipo === "entrada"    ? "fa-arrow-up"
                      : tipo === "salida"     ? "fa-arrow-down"
                      : "fa-edit";
            $("#preview_nuevo_stock")
                .html(
                    'Nuevo stock estimado: ' +
                    '<strong style="color:' + color + ';font-size:1rem;">' +
                    '<i class="fas ' + icono + ' mr-1"></i>' + nuevo + ' uds</strong>'
                )
                .show();
        } else {
            $("#preview_nuevo_stock").hide();
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Submit #formAjustarStock con validación y confirmación SweetAlert2
    // ══════════════════════════════════════════════════════════════════════════
    $("#formAjustarStock").on("submit", function (e) {
        e.preventDefault();

        var tipo     = $("#ajuste_tipo").val();
        var cantidad = parseInt($("#ajuste_cantidad").val()) || 0;
        var motivo   = $("#ajuste_motivo").val().trim();
        var nombre   = $("#ajuste_nombre_producto").text();

        // Validaciones
        if (cantidad <= 0) {
            Swal.fire({
                icon: "warning",
                title: "Cantidad inválida",
                text: "Ingresa una cantidad mayor a 0.",
                confirmButtonColor: "#1a5276"
            });
            return;
        }
        if (!motivo) {
            Swal.fire({
                icon: "warning",
                title: "Motivo requerido",
                text: "Indica el motivo del ajuste antes de continuar.",
                confirmButtonColor: "#1a5276"
            });
            return;
        }

        var tipoLabel = tipo === "entrada"    ? "ENTRADA"
                      : tipo === "salida"     ? "SALIDA"
                      : "CORRECCIÓN";
        var tipoColor = tipo === "entrada"    ? "#27ae60"
                      : tipo === "salida"     ? "#e74c3c"
                      : "#2980b9";

        Swal.fire({
            icon: "question",
            title: "Confirmar ajuste de stock",
            html:
                '<div style="text-align:left;font-size:.9rem;">' +
                '<div class="mb-1"><i class="fas fa-laptop mr-1 text-muted"></i><strong>Producto:</strong> ' + nombre + '</div>' +
                '<div class="mb-1"><i class="fas fa-exchange-alt mr-1 text-muted"></i><strong>Tipo:</strong> ' +
                '<span style="color:' + tipoColor + ';font-weight:700;">' + tipoLabel + '</span></div>' +
                '<div class="mb-1"><i class="fas fa-sort-numeric-up mr-1 text-muted"></i><strong>Cantidad:</strong> ' + cantidad + ' uds</div>' +
                '<div><i class="fas fa-comment mr-1 text-muted"></i><strong>Motivo:</strong> ' + motivo + '</div>' +
                '</div>',
            showCancelButton:    true,
            confirmButtonColor:  "#1a5276",
            cancelButtonColor:   "#6c757d",
            confirmButtonText:   "<i class='fas fa-check mr-1'></i> Confirmar",
            cancelButtonText:    "<i class='fas fa-times mr-1'></i> Cancelar"
        }).then(function (result) {
            if (result.isConfirmed) {
                $("#btnSubmitAjuste")
                    .prop("disabled", true)
                    .html("<i class='fas fa-spinner fa-spin mr-1'></i> Guardando...");
                document.getElementById("formAjustarStock").submit();
            }
        });
    });

    // ══════════════════════════════════════════════════════════════════════════
    // Toggle tabla de productos inactivos
    // ══════════════════════════════════════════════════════════════════════════
    $("#btnToggleInactivos").on("click", function () {
        var $wrap = $("#tablaInactivosWrap");
        if ($wrap.is(":visible")) {
            $wrap.slideUp(200);
            $(this).html('<i class="fas fa-eye mr-1"></i>Mostrar');
        } else {
            $wrap.slideDown(200);
            $(this).html('<i class="fas fa-eye-slash mr-1"></i>Ocultar');
        }
    });

    // ══════════════════════════════════════════════════════════════════════════
    // EXPORTAR INVENTARIO
    // ══════════════════════════════════════════════════════════════════════════
    $("#btnExportarInv").on("click", function () {
        // Sincronizar filtro de stock activo
        var filtroActual = $(".btn-filtro-inv.active").data("filtro") || "todos";
        var mapFiltro = { "todos": "all", "ok": "ok", "bajo": "bajo", "agotado": "agotado", "exceso": "exceso" };
        $("#inv_exp_stock").val(mapFiltro[filtroActual] || "all");
        // Sincronizar categoría seleccionada en el filtro visual
        if (catSeleccionada) {
            var $opt = $("#inv_exp_categoria option").filter(function () {
                return $(this).text().trim().toLowerCase() === catSeleccionada.toLowerCase();
            });
            if ($opt.length) { $("#inv_exp_categoria").val($opt.val()); }
            else             { $("#inv_exp_categoria").val("all"); }
        } else {
            $("#inv_exp_categoria").val("all");
        }
        $("#modalExportarInv").modal("show");
    });

    function invGetParams() {
        return {
            stock:     $("#inv_exp_stock").val()     || "all",
            categoria: $("#inv_exp_categoria").val() || "all",
        };
    }

    function invExportar(formato) {
        var p = invGetParams();
        var url = "ajax_inventario_export.php?exportar=" + formato
            + "&stock="     + encodeURIComponent(p.stock)
            + "&categoria=" + encodeURIComponent(p.categoria);
        window.location.href = url;
        $("#modalExportarInv").modal("hide");
    }

    function invExportarPDF() {
        var p = invGetParams();
        var url = "inventario_pdf.php?stock=" + encodeURIComponent(p.stock)
            + "&categoria=" + encodeURIComponent(p.categoria);
        window.open(url, "_blank");
        $("#modalExportarInv").modal("hide");
    }

    $("#inv_btn_csv").on("click",   function () { invExportar("csv"); });
    $("#inv_btn_excel").on("click", function () { invExportar("excel"); });
    $("#inv_btn_pdf").on("click",   function () { invExportarPDF(); });

    // ══════════════════════════════════════════════════════════════════════════
    // Animación de barras de stock al cargar
    // ══════════════════════════════════════════════════════════════════════════
    $(".barra-stock-fill").each(function () {
        var targetWidth = $(this).css("width");
        $(this).css("width", "0").animate({ width: targetWidth }, { duration: 600, easing: "swing" });
    });

    // ══════════════════════════════════════════════════════════════════════════
    // Tooltips Bootstrap
    // ══════════════════════════════════════════════════════════════════════════
    $('[title]').tooltip({ trigger: "hover", placement: "top" });

    // ══════════════════════════════════════════════════════════════════════════
    // Inicialización
    // ══════════════════════════════════════════════════════════════════════════
    actualizarContador();

    // ── Thumbs del modal detalle ──────────────────────────────────────────────
    $(document).on("click", ".inv-gal-thumb", function () {
        var src = $(this).data("src");
        $("#invModalMainImg").attr("src", src);
        $(".inv-gal-thumb").removeClass("active").css("border-color", "#dee2e6");
        $(this).addClass("active").css("border-color", "#2980b9");
    });

    // ── LIGHTBOX — igual que productos ───────────────────────────────────────
    var _invLb = { imgs: [], idx: 0 };

    function invLbOpen(imgs, startIdx) {
        _invLb.imgs = Array.isArray(imgs) ? imgs.filter(Boolean) : [imgs];
        _invLb.idx  = startIdx || 0;
        invLbRender();
        $("#invImgLightbox").addClass("active");
        $("body").css("overflow", "hidden");
    }

    function invLbClose() {
        $("#invImgLightbox").removeClass("active");
        $("body").css("overflow", "");
        setTimeout(function () {
            $("#invLbMainImg").attr("src", "");
            $("#invLbThumbs").empty();
            $("#invLbCounter").text("");
        }, 220);
    }

    function invLbRender() {
        var url   = _invLb.imgs[_invLb.idx];
        var total = _invLb.imgs.length;

        var $img = $("#invLbMainImg");
        $img.css("opacity", 0).attr("src", url);
        $img.off("load").on("load", function () { $img.animate({ opacity: 1 }, 180); });
        if ($img[0].complete) $img.css("opacity", 1);

        $("#invLbCounter").text(total > 1 ? (_invLb.idx + 1) + " / " + total : "");
        $("#invLbPrev").toggleClass("hidden", total <= 1 || _invLb.idx === 0);
        $("#invLbNext").toggleClass("hidden", total <= 1 || _invLb.idx === total - 1);

        var $th = $("#invLbThumbs").empty();
        if (total > 1) {
            $.each(_invLb.imgs, function (i, u) {
                $th.append(
                    '<div class="inv-lb-thumb' + (i === _invLb.idx ? " active" : "") + '" data-index="' + i + '">' +
                    '<img src="' + u + '" alt=""></div>'
                );
            });
        }
    }

    // Abrir desde miniatura de tabla
    $(document).on("click", ".inv-lista-img-wrap", function (e) {
        e.stopPropagation();
        e.preventDefault();
        var $wrap = $(this);
        var src   = $wrap.find("img").attr("src");
        var raw   = $wrap.attr("data-imgs");
        var imgs  = [];

        if (raw) {
            try {
                // jQuery puede haber ya parseado el atributo; intentar ambas vías
                var tmp = $wrap.data("imgs");
                if (Array.isArray(tmp) && tmp.length) {
                    imgs = tmp.filter(Boolean);
                } else {
                    // Decodificar entidades y parsear manualmente
                    var decoded = $("<div>").html(raw).text();
                    var parsed  = JSON.parse(decoded);
                    imgs = Array.isArray(parsed) ? parsed.filter(Boolean) : [];
                }
            } catch (ex) {
                imgs = src ? [src] : [];
            }
        }
        if (!imgs.length && src) imgs = [src];
        var idx = $.inArray(src, imgs);
        invLbOpen(imgs, idx >= 0 ? idx : 0);
    });

    // Abrir desde imagen principal del modal detalle
    $(document).on("click", "#invModalMainImg", function (e) {
        e.stopPropagation();
        e.preventDefault();
        var $img = $(this);
        var src  = $img.attr("src");
        var imgs = [];

        try {
            var tmp = $img.data("imgs");
            if (Array.isArray(tmp) && tmp.length) {
                imgs = tmp.filter(Boolean);
            } else {
                var raw     = $img.attr("data-imgs");
                var decoded = $("<div>").html(raw).text();
                var parsed  = JSON.parse(decoded);
                imgs = Array.isArray(parsed) ? parsed.filter(Boolean) : [];
            }
        } catch (ex) {
            imgs = src ? [src] : [];
        }

        if (!imgs.length && src) imgs = [src];
        var idx = $.inArray(src, imgs);
        invLbOpen(imgs, idx >= 0 ? idx : 0);
    });

    // Prev / Next
    $("#invLbPrev").on("click", function (e) {
        e.stopPropagation();
        if (_invLb.idx > 0) { _invLb.idx--; invLbRender(); }
    });
    $("#invLbNext").on("click", function (e) {
        e.stopPropagation();
        if (_invLb.idx < _invLb.imgs.length - 1) { _invLb.idx++; invLbRender(); }
    });

    // Thumbnail del lightbox
    $(document).on("click", "#invLbThumbs .inv-lb-thumb", function (e) {
        e.stopPropagation();
        _invLb.idx = parseInt($(this).data("index")) || 0;
        invLbRender();
    });

    // Cerrar
    $("#invLbClose").on("click", invLbClose);
    $(document).on("click", "#invImgLightbox", function (e) {
        if ($(e.target).is("#invImgLightbox")) invLbClose();
    });
    $(document).on("keydown", function (e) {
        if (!$("#invImgLightbox").hasClass("active")) return;
        if (e.key === "Escape")     invLbClose();
        if (e.key === "ArrowLeft"  && _invLb.idx > 0)                        { _invLb.idx--; invLbRender(); }
        if (e.key === "ArrowRight" && _invLb.idx < _invLb.imgs.length - 1)   { _invLb.idx++; invLbRender(); }
    });

});
