/**
 * historial_compras.js — Historial de Compras | Botica 2026
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
    if ($("#tablaHistorial").length) {
        tabla = $("#tablaHistorial").DataTable({
            language: {
                search: "<i class='fas fa-search'></i>",
                searchPlaceholder: "Buscar compra...",
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

    // ── Filtro rápido por estado ──────────────────────────
    $(document).on("click", ".btn-filtro-estado", function () {
        $(".btn-filtro-estado").removeClass("active");
        $(this).addClass("active");
        var estado = $(this).data("estado");
        if (tabla) {
            if (estado === "todos") {
                tabla.column(6).search("").draw();
            } else {
                tabla.column(6).search(estado, true, false).draw();
            }
        }
    });

    // ── VER DETALLE COMPRA ────────────────────────────────
    $(document).on("click", ".btn-ver-compra", function () {
        var id = $(this).data("id");
        $("#modalVerCompra .modal-body").html(
            '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i><p class="mt-3 text-muted">Cargando detalle...</p></div>'
        );
        $("#modalVerCompra").modal("show");
        $.get("historial_compras.php", { accion: "detalle_ajax", id_compra: id }, function (html) {
            $("#modalVerCompra .modal-body").html(html);
        }).fail(function () {
            $("#modalVerCompra .modal-body").html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle mr-2"></i>Error al cargar el detalle.</div>');
        });
    });

    // ── REGISTRAR PAGO ────────────────────────────────────
    $(document).on("click", ".btn-pagar-compra", function () {
        var id       = $(this).data("id");
        var saldo    = parseFloat($(this).data("saldo")) || 0;
        var proveedor = $(this).data("proveedor") || "";
        var numero   = $(this).data("numero") || ("#" + id);

        $("#pago_id_compra").val(id);
        $("#pago_saldo_display").text("S/. " + saldo.toFixed(2));
        $("#pago_proveedor_label").text(proveedor + " — " + numero);
        $("#monto_pago").val(saldo.toFixed(2)).attr("max", saldo);

        // Reset método pago
        $(".btn-metodo-pago").css({ background: "#fff", color: "#555", borderColor: "#dee2e6" });
        $(".btn-metodo-pago[data-metodo=efectivo]").css({ background: "#1a7a4a", color: "#fff", borderColor: "#1a7a4a" });
        $("#metodo_pago_abono").val("efectivo");
        $("#obs_pago").val("");

        $("#modalPagarCompra").modal("show");
        setTimeout(function () { $("#monto_pago").focus().select(); }, 400);
    });

    $(document).on("click", ".btn-metodo-pago", function () {
        $(".btn-metodo-pago").css({ background: "#fff", color: "#555", borderColor: "#dee2e6" });
        $(this).css({ background: "#1a7a4a", color: "#fff", borderColor: "#1a7a4a" });
        $("#metodo_pago_abono").val($(this).data("metodo"));
    });

    // Validar monto antes de enviar pago
    $("#formRegistrarPago").on("submit", function (e) {
        e.preventDefault();
        var monto = parseFloat($("#monto_pago").val()) || 0;
        var max   = parseFloat($("#monto_pago").attr("max")) || 0;
        if (monto <= 0) {
            Swal.fire({ icon: "warning", title: "Monto inválido", text: "El monto debe ser mayor a 0.", confirmButtonColor: "#1a5276" });
            return;
        }
        if (monto > max + 0.01) {
            Swal.fire({ icon: "warning", title: "Monto excede saldo", text: "El monto no puede superar el saldo pendiente de S/. " + max.toFixed(2), confirmButtonColor: "#1a5276" });
            return;
        }
        Swal.fire({
            icon: "question",
            title: "¿Registrar pago?",
            html: "Monto: <b>S/. " + monto.toFixed(2) + "</b><br>Método: <b>" + $("#metodo_pago_abono").val().toUpperCase() + "</b>",
            showCancelButton: true,
            confirmButtonColor: "#1a7a4a",
            cancelButtonColor: "#6c757d",
            confirmButtonText: "<i class='fas fa-check mr-1'></i> Sí, registrar",
            cancelButtonText: "Cancelar"
        }).then(function (r) {
            if (r.isConfirmed) document.getElementById("formRegistrarPago").submit();
        });
    });

    // ── ANULAR COMPRA ─────────────────────────────────────
    $(document).on("click", ".btn-anular-compra", function () {
        var id  = $(this).data("id");
        var num = $(this).data("numero") || ("#" + id);
        Swal.fire({
            icon: "error",
            title: "¿Anular compra?",
            html: "La compra <b>" + num + "</b> será anulada.<br><strong class='text-danger'>El stock de los lotes será revertido.</strong>",
            showCancelButton: true,
            confirmButtonColor: "#e74c3c",
            cancelButtonColor: "#6c757d",
            confirmButtonText: "<i class='fas fa-ban mr-1'></i> Sí, anular",
            cancelButtonText: "Cancelar"
        }).then(function (r) {
            if (r.isConfirmed) {
                var $f = $("<form method='POST' style='display:none;'></form>");
                $f.append("<input type='hidden' name='accion' value='anular'>");
                $f.append("<input type='hidden' name='id_compra' value='" + id + "'>");
                $("body").append($f);
                $f.submit();
            }
        });
    });

});
