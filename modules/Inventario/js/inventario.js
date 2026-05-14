/**
 * inventario.js — Módulo Stock General | Botica 2026
 */

$(function () {

    // ── DataTable ─────────────────────────────────────────────────────────────
    var tabla = null;

    if ($("#tablaInventario").length) {
        tabla = $("#tablaInventario").DataTable({
            language: {
                search:            "",
                searchPlaceholder: "Buscar...",
                lengthMenu:        "Mostrar _MENU_ registros",
                info:              "Mostrando _START_ a _END_ de _TOTAL_",
                infoEmpty:         "Sin registros",
                zeroRecords:       "No se encontraron resultados",
                paginate:          { previous: "&#8249;", next: "&#8250;" }
            },
            responsive:  true,
            autoWidth:   false,
            order:       [[2, "asc"]],   // ordenar por nombre producto
            pageLength:  25,
            columnDefs:  [{ orderable: false, targets: [-1] }],
            dom: '<"d-flex justify-content-between align-items-center mb-2"lf>rtip'
        });
        $(".dataTables_filter").hide();
    }

    // ── Buscador personalizado ────────────────────────────────────────────────
    $("#buscarInventario").on("input", function () {
        var q = $(this).val().toLowerCase();
        if (tabla) {
            tabla.search(q).draw();
        } else {
            $(".fila-inv").each(function () {
                var texto = $(this).data("search") || "";
                $(this).toggle(texto.indexOf(q) > -1);
            });
        }
        actualizarContador();
    });

    // ── Filtros rápidos ───────────────────────────────────────────────────────
    var filtroActivo = "todos";

    $(document).on("click", ".btn-filtro-inv", function () {
        $(".btn-filtro-inv").removeClass("active");
        $(this).addClass("active");
        filtroActivo = $(this).data("filtro");
        aplicarFiltro();
    });

    function aplicarFiltro() {
        if (filtroActivo === "todos") {
            $(".fila-inv").show();
        } else {
            $(".fila-inv").each(function () {
                $(this).toggle($(this).data("filtro") === filtroActivo);
            });
        }
        actualizarContador();
    }

    function actualizarContador() {
        var v = $(".fila-inv:visible").length;
        $("#contadorInventario").text(v + " producto" + (v !== 1 ? "s" : ""));
    }

    // ── Ver detalle del producto (AJAX) ───────────────────────────────────────
    $(document).on("click", ".btn-ver-producto-inv", function () {
        var id = $(this).data("id");
        $("#modalVerProductoInv .modal-body").html(
            '<div class="text-center py-5">' +
            '<i class="fas fa-spinner fa-spin fa-2x text-muted"></i>' +
            '<p class="mt-3 text-muted">Cargando detalle...</p>' +
            '</div>'
        );
        $("#modalVerProductoInv").modal("show");
        $.get("inventario.php", { accion: "detalle_ajax", id_producto: id }, function (html) {
            $("#modalVerProductoInv .modal-body").html(html);
        }).fail(function () {
            $("#modalVerProductoInv .modal-body").html(
                '<div class="alert alert-danger"><i class="fas fa-exclamation-circle mr-2"></i>Error al cargar el detalle.</div>'
            );
        });
    });

    // ── Abrir modal ajuste de stock ───────────────────────────────────────────
    $(document).on("click", ".btn-ajustar-stock", function () {
        var id     = $(this).data("id");
        var nombre = $(this).data("nombre");
        var stock  = parseInt($(this).data("stock")) || 0;

        $("#ajuste_id_producto").val(id);
        $("#ajuste_nombre_producto").text(nombre);
        $("#ajuste_stock_actual").text(stock + " uds");
        $("#ajuste_cantidad").val("");
        $("#ajuste_motivo").val("");
        $("#ajuste_tipo").val("entrada");

        // Reset botones tipo
        $(".btn-tipo-ajuste").removeClass("activo");
        $(".btn-tipo-ajuste.entrada").addClass("activo");

        $("#modalAjustarStock").modal("show");
        $("#modalAjustarStock").one("shown.bs.modal", function () {
            $("#ajuste_cantidad").focus();
        });
    });

    // ── Selector tipo ajuste ──────────────────────────────────────────────────
    $(document).on("click", ".btn-tipo-ajuste", function () {
        $(".btn-tipo-ajuste").removeClass("activo");
        $(this).addClass("activo");
        $("#ajuste_tipo").val($(this).data("tipo"));
        actualizarPreviewAjuste();
    });

    // ── Preview del ajuste ────────────────────────────────────────────────────
    $("#ajuste_cantidad").on("input", actualizarPreviewAjuste);

    function actualizarPreviewAjuste() {
        var tipo     = $("#ajuste_tipo").val();
        var cantidad = parseInt($("#ajuste_cantidad").val()) || 0;
        var stockStr = $("#ajuste_stock_actual").text().replace(" uds", "");
        var stock    = parseInt(stockStr) || 0;
        var nuevo    = tipo === "entrada"    ? stock + cantidad
                     : tipo === "salida"     ? Math.max(0, stock - cantidad)
                     : cantidad;                // corrección directa

        if (cantidad > 0) {
            var color = tipo === "entrada" ? "#27ae60" : (tipo === "salida" ? "#e74c3c" : "#2980b9");
            $("#preview_nuevo_stock").html(
                '<span style="color:' + color + ';font-weight:700;font-size:1rem;">' +
                nuevo + ' uds</span>'
            ).show();
        } else {
            $("#preview_nuevo_stock").hide();
        }
    }

    // ── Submit ajuste ─────────────────────────────────────────────────────────
    $("#formAjustarStock").on("submit", function (e) {
        e.preventDefault();
        var tipo     = $("#ajuste_tipo").val();
        var cantidad = parseInt($("#ajuste_cantidad").val()) || 0;
        var motivo   = $("#ajuste_motivo").val().trim();
        var nombre   = $("#ajuste_nombre_producto").text();

        if (cantidad <= 0) {
            Swal.fire({ icon: "warning", title: "Cantidad inválida", text: "Ingresa una cantidad mayor a 0.", confirmButtonColor: "#1a5276" });
            return;
        }
        if (!motivo) {
            Swal.fire({ icon: "warning", title: "Motivo requerido", text: "Indica el motivo del ajuste.", confirmButtonColor: "#1a5276" });
            return;
        }

        var tipoLabel = tipo === "entrada" ? "ENTRADA" : (tipo === "salida" ? "SALIDA" : "CORRECCIÓN");
        var tipoColor = tipo === "entrada" ? "#27ae60" : (tipo === "salida" ? "#e74c3c" : "#2980b9");

        Swal.fire({
            icon: "question",
            title: "Confirmar ajuste",
            html: "Producto: <b>" + nombre + "</b><br>" +
                  "Tipo: <b style='color:" + tipoColor + ";'>" + tipoLabel + "</b><br>" +
                  "Cantidad: <b>" + cantidad + " uds</b>",
            showCancelButton: true,
            confirmButtonColor: "#1a5276",
            cancelButtonColor: "#6c757d",
            confirmButtonText: "<i class='fas fa-check mr-1'></i> Confirmar",
            cancelButtonText: "Cancelar"
        }).then(function (r) {
            if (r.isConfirmed) {
                $("#btnSubmitAjuste").prop("disabled", true)
                    .html("<i class='fas fa-spinner fa-spin mr-1'></i> Guardando...");
                document.getElementById("formAjustarStock").submit();
            }
        });
    });

    // ── Animación barras de stock ─────────────────────────────────────────────
    $(".barra-stock-fill").each(function () {
        var w = $(this).css("width");
        $(this).css("width", "0").animate({ width: w }, 500);
    });

    // ── Tooltips ─────────────────────────────────────────────────────────────
    $("[title]").tooltip({ trigger: "hover", placement: "top" });

    // ── Init ──────────────────────────────────────────────────────────────────
    actualizarContador();

});
