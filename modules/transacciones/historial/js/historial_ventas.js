/**
 * historial_ventas.js — Historial de Ventas | SysInversiones CH Computer 2026
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

    // ── VER TICKET PDF ────────────────────────────────────
    $(document).on("click", ".btn-ver-ticket", function () {
        var id     = $(this).data("id");
        var tipo   = $(this).data("tipo") || "ticket";
        var numero = $(this).data("numero") || ("#" + id);

        // Si es nota → abrir en nueva pestaña con imprimir.php
        if (tipo !== "ticket") {
            window.open("../../Comprobantes/imprimir.php?tipo=venta&id=" + id, "_blank");
            return;
        }

        // Ticket 80mm → modal con iframe
        var urlPrev = "../../Comprobantes/comprobante_ticket.php?id_venta=" + id;
        var urlDesc = "../../Comprobantes/comprobante_ticket.php?id_venta=" + id + "&download=1";

        $("#ticketPdfNumero").text(numero);
        $("#btnDescargarTicket").attr("href", urlDesc);
        $("#ticketPdfFrame").attr("src", "").hide();
        $("#ticketPdfCargando").show();
        $("#modalTicketPDF").modal("show");

        $("#modalTicketPDF").one("shown.bs.modal", function () {
            $("#ticketPdfFrame").attr("src", urlPrev);
        });
    });

    // Limpiar iframe al cerrar modal PDF
    $("#modalTicketPDF").on("hidden.bs.modal", function () {
        $("#ticketPdfFrame").attr("src", "").hide();
        $("#ticketPdfCargando").show();
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
        $("#monto_pago").data("saldo", saldo).removeAttr("readonly");
        $("#pago_monto_hint").text("Saldo pendiente: S/. " + saldo.toFixed(2));
        $("#pago_cuotas_lista").html("");

        // Resetear método de pago
        $(".btn-metodo-pago-vta").css({ background: "#fff", color: "#555", borderColor: "#dee2e6" });
        $(".btn-metodo-pago-vta[data-metodo=efectivo]").css({ background: "#1a7a4a", color: "#fff", borderColor: "#1a7a4a" });
        $("#metodo_pago_abono").val("efectivo");
        $("#obs_pago").val("");

        // Cargar cuotas vía AJAX
        $("#pago_cuotas_loading").show();
        $.get("historial_ventas.php", { accion: "cuotas_ajax", id_venta: id }, function (data) {
            $("#pago_cuotas_loading").hide();
            if (!data.ok || !data.cuotas || data.cuotas.length === 0) {
                // Sin cuotas: monto libre
                $("#monto_pago").val(saldo.toFixed(2)).attr("max", saldo).removeAttr("readonly");
                $("#pago_monto_hint").text("Saldo pendiente: S/. " + saldo.toFixed(2));
                return;
            }
            renderCuotasPago(data.cuotas, saldo);
        }, "json").fail(function () {
            $("#pago_cuotas_loading").hide();
            $("#monto_pago").val(saldo.toFixed(2)).attr("max", saldo);
        });

        $("#modalPagarVenta").modal("show");
    });

    // ── Renderizar cuotas en el modal de pago ─────────────
    function renderCuotasPago(cuotas, saldo) {
        var activa = null;
        for (var i = 0; i < cuotas.length; i++) {
            if (cuotas[i].estado === "pendiente" || cuotas[i].estado === "vencido") {
                activa = cuotas[i];
                break;
            }
        }

        if (!activa) {
            $("#pago_cuotas_lista").html(
                '<div class="alert alert-success border-0 mb-2" style="border-radius:8px;font-size:.85rem;">' +
                '<i class="fas fa-check-circle mr-2"></i>Todas las cuotas están pagadas.</div>'
            );
            $("#monto_pago").val("0.00").attr("readonly", true);
            return;
        }

        // Fijar monto al de la cuota activa
        var montoCuota = parseFloat(activa.monto_cuota);
        $("#monto_pago").val(montoCuota.toFixed(2)).attr("max", saldo).attr("readonly", true);
        $("#pago_monto_hint").html(
            '<i class="fas fa-lock mr-1 text-warning"></i>' +
            'Monto fijado al valor de la cuota activa. Paga una cuota a la vez.'
        );

        // Construir lista visual
        var html = '<div style="margin-bottom:10px;">';
        html += '<div style="font-size:.78rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">' +
                '<i class="fas fa-calendar-alt mr-1"></i>Cronograma de cuotas</div>';

        cuotas.forEach(function (c) {
            var esPagada   = c.estado === "pagado";
            var esActiva   = c.id_cuota === activa.id_cuota;
            var esVencida  = c.estado === "vencido";
            var esBloqueada = !esPagada && !esActiva;

            var venc = "—";
            if (c.fecha_vencimiento) {
                var d = new Date(c.fecha_vencimiento + "T00:00:00");
                venc = d.toLocaleDateString("es-PE", { day: "2-digit", month: "2-digit", year: "numeric" });
            }

            var rowStyle, iconHtml, badgeHtml, opacityStyle = "";

            if (esPagada) {
                rowStyle  = "background:#f0fdf4;border:1px solid #bbf7d0;";
                iconHtml  = '<i class="fas fa-check-circle" style="color:#22c55e;font-size:1.1rem;"></i>';
                badgeHtml = '<span style="background:#dcfce7;color:#166534;font-size:.72rem;padding:2px 8px;border-radius:20px;font-weight:600;">PAGADA</span>';
            } else if (esActiva) {
                rowStyle  = "background:#eff6ff;border:2px solid #1a5276;box-shadow:0 2px 8px rgba(26,82,118,.15);";
                iconHtml  = '<i class="fas fa-arrow-right" style="color:#1a5276;font-size:1.1rem;"></i>';
                badgeHtml = '<span style="background:#dbeafe;color:#1a5276;font-size:.72rem;padding:2px 8px;border-radius:20px;font-weight:700;">' +
                            (esVencida ? "⚠ VENCIDA — PAGAR AHORA" : "← PAGAR AHORA") + "</span>";
            } else {
                rowStyle     = "background:#f8fafc;border:1px solid #e2e8f0;";
                opacityStyle = "opacity:.45;";
                iconHtml     = '<i class="fas fa-lock" style="color:#94a3b8;font-size:1rem;"></i>';
                badgeHtml    = '<span style="background:#f1f5f9;color:#94a3b8;font-size:.72rem;padding:2px 8px;border-radius:20px;">BLOQUEADA</span>';
            }

            html += '<div style="' + rowStyle + opacityStyle + 'border-radius:8px;padding:10px 14px;margin-bottom:6px;display:flex;align-items:center;gap:12px;">' +
                '<div style="flex-shrink:0;">' + iconHtml + '</div>' +
                '<div style="flex:1;min-width:0;">' +
                    '<div style="font-weight:700;font-size:.88rem;color:#1e293b;">Cuota ' + c.numero_cuota + ' de ' + cuotas.length +
                        ' <span style="margin-left:6px;">' + badgeHtml + '</span></div>' +
                    '<div style="font-size:.78rem;color:#64748b;margin-top:2px;"><i class="fas fa-calendar mr-1"></i>Vence: ' + venc + '</div>' +
                '</div>' +
                '<div style="font-weight:700;font-size:1rem;color:' + (esPagada ? "#22c55e" : esActiva ? "#1a5276" : "#94a3b8") + ';white-space:nowrap;">' +
                    'S/. ' + parseFloat(c.monto_cuota).toFixed(2) +
                '</div>' +
            '</div>';
        });

        html += "</div>";
        $("#pago_cuotas_lista").html(html);
    }

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

    // ── EXPORTAR HISTORIAL VENTAS ─────────────────────────
    $("#btnExportarHVta").on("click", function () {
        $("#modalExportarHVta").modal("show");
    });

    function hvtaGetParams() {
        return {
            estado:      $("#hvta_exp_estado").val()  || "all",
            tipo_pago:   $("#hvta_exp_pago").val()    || "all",
            fecha_desde: $("#hvta_exp_desde").val()   || "",
            fecha_hasta: $("#hvta_exp_hasta").val()   || "",
        };
    }

    function hvtaExportar(formato) {
        var p = hvtaGetParams();
        var url = "ajax_historial_ventas_export.php?exportar=" + formato
            + "&estado="      + encodeURIComponent(p.estado)
            + "&tipo_pago="   + encodeURIComponent(p.tipo_pago)
            + "&fecha_desde=" + encodeURIComponent(p.fecha_desde)
            + "&fecha_hasta=" + encodeURIComponent(p.fecha_hasta);
        window.location.href = url;
        $("#modalExportarHVta").modal("hide");
    }

    function hvtaExportarPDF() {
        var p = hvtaGetParams();
        var url = "historial_ventas_pdf.php?estado=" + encodeURIComponent(p.estado)
            + "&tipo_pago="   + encodeURIComponent(p.tipo_pago)
            + "&fecha_desde=" + encodeURIComponent(p.fecha_desde)
            + "&fecha_hasta=" + encodeURIComponent(p.fecha_hasta);
        window.open(url, "_blank");
        $("#modalExportarHVta").modal("hide");
    }

    $("#hvta_btn_csv").on("click",   function () { hvtaExportar("csv"); });
    $("#hvta_btn_excel").on("click", function () { hvtaExportar("excel"); });
    $("#hvta_btn_pdf").on("click",   function () { hvtaExportarPDF(); });

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
