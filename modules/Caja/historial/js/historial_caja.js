/**
 * historial_caja.js — Historial de Cajas | Botica 2026
 */

$(function () {

    // =====================================================
    // DATATABLE
    // =====================================================
    var tabla = $("#tablaHistorialCajas").DataTable({
        language: {
            search: "", searchPlaceholder: "Buscar caja...",
            lengthMenu: "Mostrar _MENU_ registros",
            info: "Mostrando _START_ a _END_ de _TOTAL_",
            infoEmpty: "Sin registros",
            zeroRecords: "No se encontraron resultados",
            paginate: { previous: "&#8249;", next: "&#8250;" }
        },
        responsive: true,
        autoWidth: false,
        order: [[0, "desc"]],
        pageLength: 15,
        columnDefs: [{ orderable: false, targets: [-1] }]
    });

    // =====================================================
    // FILTROS RÁPIDOS POR ESTADO
    // =====================================================
    $(document).on("click", ".btn-filtro-estado", function () {
        $(".btn-filtro-estado").each(function () {
            $(this).css({ background: "#fff", color: $(this).data("color") || "#555", borderColor: $(this).data("color") || "#dee2e6" });
        });
        var estado = $(this).data("estado");
        var color  = $(this).data("color") || "#1a7a4a";
        $(this).css({ background: color, color: "#fff", borderColor: color });

        if (estado === "todos") {
            tabla.column(8).search("").draw();
        } else {
            tabla.column(8).search(estado, false, false).draw();
        }
    });

    // =====================================================
    // FILTRO POR TURNO
    // =====================================================
    $(document).on("click", ".btn-filtro-turno", function () {
        $(".btn-filtro-turno").each(function () {
            $(this).css({ background: "#fff", color: "#555", borderColor: "#dee2e6" });
        });
        var turno = $(this).data("turno");
        var color = $(this).data("color") || "#1a7a4a";
        $(this).css({ background: color, color: "#fff", borderColor: color });

        if (turno === "todos") {
            tabla.column(2).search("").draw();
        } else {
            tabla.column(2).search(turno, false, false).draw();
        }
    });

    // =====================================================
    // VER DETALLE DE CAJA (AJAX)
    // =====================================================
    $(document).on("click", ".btn-ver-caja", function () {
        var id = $(this).data("id");
        $("#modalDetalleCaja .modal-body").html(
            '<div class="text-center py-5">' +
            '<i class="fas fa-spinner fa-spin fa-2x text-muted"></i>' +
            '<p class="mt-3 text-muted">Cargando detalle...</p>' +
            '</div>'
        );
        $("#modalDetalleCaja").modal("show");

        $.get("historial_caja.php", { accion: "detalle_ajax", id_caja: id }, function (html) {
            $("#modalDetalleCaja .modal-body").html(html);
        }).fail(function () {
            $("#modalDetalleCaja .modal-body").html(
                '<div class="alert alert-danger"><i class="fas fa-exclamation-circle mr-2"></i>Error al cargar el detalle.</div>'
            );
        });
    });

});
