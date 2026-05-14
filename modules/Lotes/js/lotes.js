/**
 * lotes.js — Módulo Lotes / Vencimientos | Botica 2026
 */

$(function () {

    // ── DataTable ─────────────────────────────────────────────────────────────
    var tabla = null;

    if ($("#tablaLotes").length) {
        tabla = $("#tablaLotes").DataTable({
            language: {
                search:           "",
                searchPlaceholder: "Buscar...",
                lengthMenu:       "Mostrar _MENU_ registros",
                info:             "Mostrando _START_ a _END_ de _TOTAL_",
                infoEmpty:        "Sin registros",
                zeroRecords:      "No se encontraron resultados",
                paginate:         { previous: "&#8249;", next: "&#8250;" }
            },
            responsive:  true,
            autoWidth:   false,
            order:       [[3, "asc"]],   // ordenar por fecha vencimiento
            pageLength:  25,
            columnDefs:  [{ orderable: false, targets: [-1] }],
            dom: '<"d-flex justify-content-between align-items-center mb-2"lf>rtip'
        });

        // Ocultar el buscador nativo de DataTable (usamos el propio)
        $(".dataTables_filter").hide();
    }

    // ── Buscador personalizado ────────────────────────────────────────────────
    $("#buscarLote").on("input", function () {
        var q = $(this).val().toLowerCase();
        if (tabla) {
            tabla.search(q).draw();
        } else {
            $(".fila-lote").each(function () {
                var texto = $(this).data("search") || "";
                $(this).toggle(texto.indexOf(q) > -1);
            });
        }
        actualizarContador();
    });

    // ── Filtros rápidos ───────────────────────────────────────────────────────
    var filtroActivo = "todos";

    $(document).on("click", ".btn-filtro-lote", function () {
        $(".btn-filtro-lote").removeClass("active");
        $(this).addClass("active");
        filtroActivo = $(this).data("filtro");
        aplicarFiltro();
    });

    function aplicarFiltro() {
        if (filtroActivo === "todos") {
            $(".fila-lote").show();
        } else {
            $(".fila-lote").each(function () {
                var f = $(this).data("filtro") || "";
                $(this).toggle(f === filtroActivo);
            });
        }
        actualizarContador();
    }

    function actualizarContador() {
        var visibles = $(".fila-lote:visible").length;
        $("#contadorLotes").text(visibles + " lote" + (visibles !== 1 ? "s" : ""));
    }

    // ── Ver detalle de lote (AJAX) ────────────────────────────────────────────
    $(document).on("click", ".btn-ver-lote", function () {
        var id = $(this).data("id");
        $("#modalVerLote .modal-body").html(
            '<div class="text-center py-5">' +
            '<i class="fas fa-spinner fa-spin fa-2x text-muted"></i>' +
            '<p class="mt-3 text-muted">Cargando detalle...</p>' +
            '</div>'
        );
        $("#modalVerLote").modal("show");
        $.get("lotes.php", { accion: "detalle_ajax", id_lote: id }, function (html) {
            $("#modalVerLote .modal-body").html(html);
        }).fail(function () {
            $("#modalVerLote .modal-body").html(
                '<div class="alert alert-danger"><i class="fas fa-exclamation-circle mr-2"></i>Error al cargar el detalle del lote.</div>'
            );
        });
    });

    // ── Animación de barras al cargar ─────────────────────────────────────────
    $(".barra-fill").each(function () {
        var w = $(this).css("width");
        $(this).css("width", "0").animate({ width: w }, 600);
    });

    // ── Tooltip en botones ────────────────────────────────────────────────────
    $('[title]').tooltip({ trigger: "hover", placement: "top" });

    // ── Inicializar contador ──────────────────────────────────────────────────
    actualizarContador();

});
