/**
 * historial_ventas.js — Historial de Ventas | Botica 2026
 */

$(function () {

    // ── Fix modales anidados ──────────────────────────────
    $(document).on("show.bs.modal", ".modal", function () {
        var zIdx = 1040 + 20 * ($(".modal.show").length + 1);
        $(this).css("z-index", zIdx);
        setTimeout(function () { $(".modal-backdrop").last().css("z-index", zIdx - 1); }, 0);
    });
    $(document).on("hidden.bs.modal", ".modal", function () {
        if ($(".modal.show").length) $("body").addClass("modal-open");
    });

    // ── DataTable ─────────────────────────────────────────
    var tabla = null;
    if ($("#tablaHistorialVentas").length) {
        tabla = $("#tablaHistorialVentas").DataTable({
            language: {
                search: "<i class='fas fa-search'></i>",
                searchPlaceholder: "Buscar venta...",
                lengthMenu: "Mostrar _MENU_ registros",
                info: "Mostrando _START_ a _END_ de _TOTAL_",
                infoEmpty: "Sin registros",
                zeroRecords: "No se encontraron resultados",
                paginate: { previous: "&#8249;", next: "&#8250;" }
            },
            responsive: true,
            autoWidth: false,
            order: [[0, "desc"]],
            pageLength: 20,
            columnDefs: [{ orderable: false, targets: [-1] }]
        });
    }

    // ── Filtro rapido por estado ──────────────────────────
    $(document).on("click", ".btn-filtro-estado", function () {
        $(".btn-filtro-estado").removeClass("active");
        $(this).addClass("active");
        var estado = $(this).data("estado");
        if (tabla) {
            if (estado === "todos") {
                tabla.column(7).search("").draw();
            } else {
                tabla.column(7).search(estado, true, false).draw();
            }
        }
    });

    // ── VER DETALLE VENTA ─────────────────────────────────
    $(document).on("click", ".btn-ver-venta", function () {
        var id = $(this).data("id");
        $("#modalVerVenta .modal-body").html(
            '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i><p class="mt-3 text-muted">Cargando detalle...</p></div>'
        );
        $("#modalVerVenta").modal("show");
        $.get("historial_ventas.php", { accion: "detalle_ajax", id_venta: id }, function (html) {
            $("#modalVerVenta .modal-body").html(html);
        }).fail(function () {
            $("#modalVerVenta .modal-body").html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle mr-2"></i>Error al cargar el detalle.</div>');
        });
    });

    // ── REGISTRAR PAGO ────────────────────────────────────
    $(document).on("click", ".btn-pagar-venta", function () {
        var id      = $(this).data("id");
        var saldo   = parseFloat($(this).data("saldo")) || 0;
        var cliente = $(this).data("cliente") || "";
        var numero  = $(this).data("numero") || ("#" + id);

        $("#pago_id_venta").val(id);
        $("#pago_saldo_display").text("S/. " + saldo.toFixed(2));
        $("#pago_cliente_label").text(cliente + " — " + numero);
        $("#monto_pago").val(saldo.toFixed(2)).attr("max", saldo);

        $(".btn-metodo-pago-vta").css({ background: "#fff", color: "#555", borderColor: "#dee2e6" });
        $(".btn-metodo-pago-vta[data-metodo=efectivo]").css({ background: "#1a7a4a", color: "#fff", borderColor: "#1a7a4a" });
        $("#metodo_pago_abono").val("efectivo");
        $("#obs_pago").val("");

        $("#modalPagarVenta").modal("show");
        setTimeout(function () { $("#monto_pago").focus().select(); }, 400);
    });

    $(document).on("click", ".btn-metodo-pago-vta", function () {
        $(".btn-metodo-pago-vta").css({ background: "#fff", color: "#555", borderColor: "#dee2e6" });
        $(this).css({ background: "#1a7a4a", color: "#fff", borderColor: "#1a7a4a" });
        $("#metodo_pago_abono").val($(this).data("metodo"));
    });

    // Validar monto antes de enviar pago
    $("#formRegistrarPagoVta").on("submit", function (e) {
        e.preventDefault();
        var monto = parseFloat($("#monto_pago").val()) || 0;
        var max   = parseFloat($("#monto_pago").attr("max")) || 0;
        if (monto <= 0) {
            Swal.fire({ icon: "warning", title: "Monto invalido", text: "El monto debe ser mayor a 0.", confirmButtonColor: "#1a7a4a" });
            return;
        }
        if (monto > max + 0.01) {
            Swal.fire({ icon: "warning", title: "Monto excede saldo", text: "El monto no puede superar el saldo pendiente de S/. " + max.toFixed(2), confirmButtonColor: "#1a7a4a" });
            return;
        }
        Swal.fire({
            icon: "question",
            title: "Registrar pago?",
            html: "Monto: <b>S/. " + monto.toFixed(2) + "</b><br>Metodo: <b>" + $("#metodo_pago_abono").val().toUpperCase() + "</b>",
            showCancelButton: true,
            confirmButtonColor: "#1a7a4a",
            cancelButtonColor: "#6c757d",
            confirmButtonText: "<i class='fas fa-check mr-1'></i> Si, registrar",
            cancelButtonText: "Cancelar"
        }).then(function (r) {
            if (r.isConfirmed) document.getElementById("formRegistrarPagoVta").submit();
        });
    });

    // ── ANULAR VENTA ──────────────────────────────────────
    $(document).on("click", ".btn-anular-venta", function () {
        var id  = $(this).data("id");
        var num = $(this).data("numero") || ("#" + id);
        Swal.fire({
            icon: "error",
            title: "Anular venta?",
            html: "La venta <b>" + num + "</b> sera anulada.<br><strong class='text-danger'>El stock de los productos sera revertido.</strong>",
            showCancelButton: true,
            confirmButtonColor: "#e74c3c",
            cancelButtonColor: "#6c757d",
            confirmButtonText: "<i class='fas fa-ban mr-1'></i> Si, anular",
            cancelButtonText: "Cancelar"
        }).then(function (r) {
            if (r.isConfirmed) {
                var $f = $("<form method='POST' style='display:none;'></form>");
                $f.append("<input type='hidden' name='accion' value='anular'>");
                $f.append("<input type='hidden' name='id_venta' value='" + id + "'>");
                $("body").append($f);
                $f.submit();
            }
        });
    });

});
