/**
 * ventas.js - Modulo Registro de Ventas | Botica 2026
 * Cuotas max 4, numeracion automatica de comprobantes
 * Descuenta stock de lotes (FEFO: primero en vencer, primero en salir)
 */

$(function () {

    // ── Helper: alerta + sonido cuando caja está cerrada ─────────────────────
    var cajaVtaCerrada = !!document.getElementById('alertaCajaVta');

    function alertaCajaCerradaVta() {
        try {
            new Audio('/botica-2026/public/assets/sonidos/alerta.mp3').play().catch(function(){});
        } catch(e) {}
        Swal.fire({
            icon: 'warning',
            title: 'Caja cerrada',
            text: 'No puedes realizar esta acción sin una caja abierta. Apertura tu caja primero.',
            confirmButtonColor: '#e67e22',
            confirmButtonText: '<i class="fas fa-lock-open mr-1"></i> Ir a Caja',
            showCancelButton: true,
            cancelButtonText: 'Cancelar',
            cancelButtonColor: '#6c757d'
        }).then(function(r) {
            if (r.isConfirmed) {
                window.location.href = '/botica-2026/modules/Caja/caja.php';
            }
        });
    }

    var venta = {
        id_cliente: null,
        nombre_cliente: "",
        items: [],
        aplica_igv: false,
        metodo_pago: "efectivo",
        tipo_pago: "contado",
        num_cuotas: 1
    };

    // DataTable historial
    if ($("#tablaHistorialVentas").length) {
        $("#tablaHistorialVentas").DataTable({
            language: { search: "", searchPlaceholder: "Buscar venta...", lengthMenu: "Mostrar _MENU_ registros", info: "Mostrando _START_ a _END_ de _TOTAL_", infoEmpty: "Sin registros", zeroRecords: "No se encontraron resultados", paginate: { previous: "&#8249;", next: "&#8250;" } },
            responsive: true, autoWidth: false, order: [[0, "desc"]], pageLength: 15,
            columnDefs: [{ orderable: false, targets: [-1] }]
        });
    }

    // Fix modales anidados
    $(document).on("show.bs.modal", ".modal", function () {
        var zIdx = 1040 + 20 * ($(".modal.show").length + 1);
        $(this).css("z-index", zIdx);
        setTimeout(function () { $(".modal-backdrop").last().css("z-index", zIdx - 1); }, 0);
    });
    $(document).on("hidden.bs.modal", ".modal", function () {
        if ($(".modal.show").length) $("body").addClass("modal-open");
    });

    // =====================================================
    // NUMERACION AUTOMATICA DE COMPROBANTES
    // =====================================================
    var prefijos = { ticket: "T001", nota: "N001", boleta: "B001", factura: "F001" };

    function cargarSiguienteNumero(tipo) {
        $.get("ventas.php", { accion: "siguiente_numero", tipo: tipo }, function (data) {
            try {
                var res = typeof data === "string" ? JSON.parse(data) : data;
                var num = res.numero || (prefijos[tipo] + "-00000001");
                var partes = num.split("-");
                var serie = partes.slice(0, -1).join("-");
                $("#prefijo_comprobante_vta").text(serie);
                $("#numero_comprobante_vta").val(num);
            } catch (e) {
                $("#numero_comprobante_vta").val((prefijos[tipo] || "B001") + "-00000001");
            }
        });
    }

    $("#tipo_comprobante_vta").on("change", function () {
        cargarSiguienteNumero($(this).val());
    });
    cargarSiguienteNumero($("#tipo_comprobante_vta").val());

    // =====================================================
    // TIPO DE PAGO (Contado / Credito)
    // =====================================================
    $(document).on("click", ".btn-tipo-pago-vta", function () {
        $(".btn-tipo-pago-vta").css({ background: "#fff", color: "#555", borderColor: "#dee2e6" });
        $(this).css({ background: "#1a7a4a", color: "#fff", borderColor: "#1a7a4a" });
        venta.tipo_pago = $(this).data("tipo");
        $("#hidden_tipo_pago_vta").val(venta.tipo_pago);
        if (venta.tipo_pago === "credito") {
            $("#panelCreditoVta").slideDown(200);
            $("#bloqueMetodoPagoVta").slideUp(200);
            $("#resumenCuotaVta").show();
            actualizarPreviewCuotas();
        } else {
            $("#panelCreditoVta").slideUp(200);
            $("#bloqueMetodoPagoVta").slideDown(200);
            $("#resumenCuotaVta").hide();
        }
    });
    $(".btn-tipo-pago-vta[data-tipo=contado]").css({ background: "#1a7a4a", color: "#fff", borderColor: "#1a7a4a" });

    // =====================================================
    // SELECTOR DE CUOTAS (1-4)
    // =====================================================
    $(document).on("click", ".btn-cuota-vta", function () {
        $(".btn-cuota-vta").css({ background: "#fff", color: "#555", borderColor: "#dee2e6" });
        $(this).css({ background: "#e67e22", color: "#fff", borderColor: "#e67e22" });
        venta.num_cuotas = parseInt($(this).data("cuotas"));
        $("#hidden_num_cuotas_vta").val(venta.num_cuotas);
        actualizarPreviewCuotas();
    });
    $(".btn-cuota-vta[data-cuotas=1]").css({ background: "#e67e22", color: "#fff", borderColor: "#e67e22" });
    $(".btn-cuota-vta").css({ border: "2px solid #dee2e6", borderRadius: "8px", padding: "8px 16px", fontSize: ".9rem", fontWeight: "700", cursor: "pointer", transition: "all .2s", background: "#fff", color: "#555" });

    // =====================================================
    // PREVIEW DE CUOTAS
    // =====================================================
    function actualizarPreviewCuotas() {
        var total = parseFloat($("#hidden_total_vta").val()) || 0;
        var fechaStr = $("#fecha_primera_cuota_vta").val();
        var frecuencia = parseInt($("#frecuencia_dias_vta").val()) || 30;
        var n = venta.num_cuotas;

        if (!fechaStr || total <= 0) {
            $("#previewCuotasVta").hide();
            $("#resumenCuotaTextoVta").text("Completa el total y la fecha de la primera cuota.");
            return;
        }

        var montoCuota = Math.round((total / n) * 100) / 100;
        var diferencia = Math.round((total - montoCuota * n) * 100) / 100;
        var fecha = new Date(fechaStr + "T00:00:00");
        var meses = ["Ene","Feb","Mar","Abr","May","Jun","Jul","Ago","Sep","Oct","Nov","Dic"];

        var html = "<div style=\"background:#fff3e0;border-radius:8px;padding:12px;border:1px solid #ffe0b2;\">";
        html += "<div style=\"font-size:.8rem;font-weight:700;color:#e67e22;margin-bottom:8px;\"><i class=\"fas fa-calendar-alt mr-1\"></i>CRONOGRAMA DE CUOTAS</div>";
        html += "<table style=\"width:100%;font-size:.82rem;\"><thead><tr style=\"color:#999;\"><th style=\"padding:3px 6px;\">Cuota</th><th style=\"padding:3px 6px;text-align:right;\">Monto</th><th style=\"padding:3px 6px;text-align:center;\">Vencimiento</th></tr></thead><tbody>";

        for (var i = 1; i <= n; i++) {
            var montoEsta = (i === n) ? Math.round((montoCuota + diferencia) * 100) / 100 : montoCuota;
            html += "<tr style=\"border-top:1px solid #ffe0b2;\">";
            html += "<td style=\"padding:4px 6px;font-weight:700;color:#e67e22;\">" + i + " / " + n + "</td>";
            html += "<td style=\"padding:4px 6px;text-align:right;font-weight:700;\">S/. " + montoEsta.toFixed(2) + "</td>";
            html += "<td style=\"padding:4px 6px;text-align:center;\">" + fecha.getDate() + " " + meses[fecha.getMonth()] + " " + fecha.getFullYear() + "</td>";
            html += "</tr>";
            fecha.setDate(fecha.getDate() + frecuencia);
        }
        html += "</tbody></table></div>";
        $("#previewCuotasVta").html(html).show();
        $("#resumenCuotaTextoVta").text(n + " cuota(s) de S/. " + montoCuota.toFixed(2) + " c/u");
    }

    $("#fecha_primera_cuota_vta, #frecuencia_dias_vta").on("change", actualizarPreviewCuotas);

    // =====================================================
    // SELECTOR CLIENTE
    // =====================================================
    $("#btnSeleccionarCliente, #campoCliente").on("click", function () {
        if (cajaVtaCerrada) { alertaCajaCerradaVta(); return; }
        $("#buscarCliente").val("");
        $("#listaClientes .item-selector-vta").show();
        $("#contClientes").text($("#listaClientes .item-selector-vta").length);
        $("#sinResultadosCli").hide();
        $("#modalSelectorCliente").modal("show");
        $("#modalSelectorCliente").one("shown.bs.modal", function () { $("#buscarCliente").focus(); });
    });
    $("#buscarCliente").on("input", function () {
        var q = $.trim($(this).val()).toLowerCase();
        var vis = 0;
        $("#listaClientes .item-selector-vta").each(function () {
            var nombre = (this.getAttribute("data-nombre") || "").toLowerCase();
            var dni    = (this.getAttribute("data-dni")    || "").toLowerCase();
            var match  = !q || nombre.indexOf(q) > -1 || dni.indexOf(q) > -1;
            if (match) { $(this).show(); vis++; } else { $(this).hide(); }
        });
        $("#contClientes").text(vis);
        $("#sinResultadosCli").toggle(vis === 0);
    });
    $(document).on("click", "#listaClientes .item-selector-vta", function () {
        venta.id_cliente    = this.getAttribute("data-id");
        venta.nombre_cliente = this.getAttribute("data-nombre");
        $("#id_cliente_hidden").val(venta.id_cliente);
        $("#campoCliente").addClass("seleccionado").find(".selected-text").text(venta.nombre_cliente).show().end().find(".placeholder-text").hide();
        $("#modalSelectorCliente").modal("hide");
        Swal.fire({ icon: "success", title: "Cliente seleccionado", html: "<b>" + venta.nombre_cliente + "</b>", toast: true, position: "top-end", timer: 2000, timerProgressBar: true, showConfirmButton: false, background: "#eafaf1", color: "#1a7a4a", iconColor: "#27ae60" });
    });
    $("#btnLimpiarCliente").on("click", function (e) {
        e.stopPropagation(); venta.id_cliente = null; venta.nombre_cliente = "";
        $("#id_cliente_hidden").val("");
        $("#campoCliente").removeClass("seleccionado").find(".selected-text").text("").hide().end().find(".placeholder-text").show();
    });

    // =====================================================
    // BÚSQUEDA DNI — MODAL DEDICADO
    // =====================================================
    function seleccionarClienteEnModal(id, nombre, guardado) {
        venta.id_cliente     = id;
        venta.nombre_cliente = nombre;
        $("#id_cliente_hidden").val(id);
        $("#campoCliente").addClass("seleccionado")
            .find(".selected-text").text(nombre).show()
            .end().find(".placeholder-text").hide();
        $("#modalNuevoClienteDni").modal("hide");
        $("#modalSelectorCliente").modal("hide");

        var titulo  = guardado ? "Cliente guardado y seleccionado" : "Cliente seleccionado";
        var mensaje = guardado
            ? "<b>" + nombre + "</b> fue registrado en el sistema y seleccionado para esta venta."
            : "<b>" + nombre + "</b> seleccionado para esta venta.";

        Swal.fire({ icon: "success", title: titulo, html: mensaje,
            toast: true, position: "top-end", timer: 3500, timerProgressBar: true,
            showConfirmButton: false, background: "#eafaf1", color: "#1a7a4a", iconColor: "#27ae60" });
    }

    // Abrir modal DNI
    $("#btnNuevoClienteDni").on("click", function () {
        $("#inputDniModal").val("");
        $("#dniModalEstado").hide().empty();
        $("#dniModalResultado").hide();
        $("#modalNuevoClienteDni").modal("show");
        $("#modalNuevoClienteDni").one("shown.bs.modal", function () {
            $("#inputDniModal").focus();
        });
    });

    // Buscar DNI
    function ejecutarBusquedaDni() {
        var dni = $.trim($("#inputDniModal").val());
        if (!/^\d{8}$/.test(dni)) {
            $("#dniModalEstado").html(
                '<div class="alert alert-warning py-2 mb-3" style="border-radius:8px;font-size:.85rem;">' +
                '<i class="fas fa-exclamation-triangle mr-2"></i>El DNI debe tener exactamente 8 dígitos.</div>'
            ).show();
            return;
        }
        var $btn = $("#btnBuscarDniModal");
        $btn.prop("disabled", true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Consultando RENIEC...');
        $("#dniModalEstado").html(
            '<div class="text-center py-3"><i class="fas fa-spinner fa-spin fa-2x text-success mb-2 d-block"></i>' +
            '<small class="text-muted">Consultando base de datos RENIEC...</small></div>'
        ).show();
        $("#dniModalResultado").hide();

        $.post("ajax_buscar_dni.php", { dni: dni, guardar: 0 }, function (res) {
            $btn.prop("disabled", false).html('<i class="fas fa-search mr-1"></i>Buscar');
            $("#dniModalEstado").hide().empty();

            if (!res.success) {
                $("#dniModalEstado").html(
                    '<div class="alert alert-danger py-2 mb-3" style="border-radius:8px;font-size:.85rem;">' +
                    '<i class="fas fa-times-circle mr-2"></i>' + res.error + '</div>'
                ).show();
                return;
            }

            var d = res.datos;
            var yaExiste = res.ya_existe;

            // Llenar datos
            $("#dniNombreCompleto").text(d.nombre_completo);
            $("#dniNumero").text(d.dni);
            if (d.direccion) {
                $("#dniDireccion").text(d.direccion);
                $("#dniDireccionRow").show();
            } else {
                $("#dniDireccionRow").hide();
            }

            if (yaExiste) {
                $("#dniBadge").html('<span style="background:#d4edda;color:#155724;padding:2px 10px;border-radius:20px;font-size:.72rem;font-weight:700;"><i class="fas fa-check-circle mr-1"></i>Ya registrado</span>');
                $("#dniOpciones").html(
                    '<button type="button" class="btn btn-success btn-block font-weight-bold" id="btnDniUsar" style="border-radius:8px;padding:10px;">' +
                    '<i class="fas fa-check mr-2"></i>Seleccionar este cliente</button>'
                );
                $("#btnDniUsar").on("click", function () {
                    seleccionarClienteEnModal(d.id_cliente, d.nombre_completo, false);
                });
            } else {
                $("#dniBadge").html('<span style="background:#fff3cd;color:#856404;padding:2px 10px;border-radius:20px;font-size:.72rem;font-weight:700;"><i class="fas fa-user-plus mr-1"></i>Nuevo cliente</span>');
                $("#dniOpciones").html(
                    '<div class="row" style="gap:0;">' +
                    '<div class="col-6 pr-1">' +
                    '<button type="button" class="btn btn-outline-success btn-block font-weight-bold" id="btnDniUsarSin" style="border-radius:8px;padding:10px;font-size:.85rem;">' +
                    '<i class="fas fa-bolt mr-1"></i>Usar sin guardar' +
                    '<div style="font-size:.72rem;font-weight:400;opacity:.8;margin-top:2px;">Solo para esta venta</div></button></div>' +
                    '<div class="col-6 pl-1">' +
                    '<button type="button" class="btn btn-success btn-block font-weight-bold" id="btnDniGuardarUsar" style="border-radius:8px;padding:10px;font-size:.85rem;">' +
                    '<i class="fas fa-save mr-1"></i>Guardar y usar' +
                    '<div style="font-size:.72rem;font-weight:400;opacity:.8;margin-top:2px;">Registrar en sistema</div></button></div>' +
                    '</div>'
                );
                $("#btnDniUsarSin").on("click", function () {
                    seleccionarClienteEnModal(0, d.nombre_completo, false);
                });
                $("#btnDniGuardarUsar").on("click", function () {
                    var $b = $(this).prop("disabled", true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Guardando...');
                    $.post("ajax_buscar_dni.php", { dni: d.dni, guardar: 1 }, function (res2) {
                        if (res2.success) {
                            seleccionarClienteEnModal(res2.id_cliente || 0, d.nombre_completo, true);
                            if (res2.id_cliente) {
                                var nuevoItem = '<div class="item-selector-vta" data-id="' + res2.id_cliente +
                                    '" data-nombre="' + d.nombre_completo + '" data-dni="' + d.dni + '">' +
                                    '<div class="item-nombre">' + d.nombre_completo + '</div>' +
                                    '<div class="item-sub"><i class="fas fa-id-card mr-1"></i>DNI: ' + d.dni + '</div></div>';
                                $("#listaClientes").prepend(nuevoItem);
                                $("#contClientes").text(parseInt($("#contClientes").text()) + 1);
                            }
                        } else {
                            $b.prop("disabled", false).html('<i class="fas fa-save mr-1"></i>Guardar y usar');
                            Swal.fire({ icon: "error", title: "Error al guardar", text: res2.error, confirmButtonColor: "#1a7a4a" });
                        }
                    }, "json").fail(function () {
                        $b.prop("disabled", false).html('<i class="fas fa-save mr-1"></i>Guardar y usar');
                    });
                });
            }
            $("#dniModalResultado").show();
        }, "json").fail(function () {
            $btn.prop("disabled", false).html('<i class="fas fa-search mr-1"></i>Buscar');
            $("#dniModalEstado").html(
                '<div class="alert alert-danger py-2 mb-3" style="border-radius:8px;font-size:.85rem;">' +
                '<i class="fas fa-wifi mr-2"></i>Error de conexión. Intenta nuevamente.</div>'
            ).show();
        });
    }

    $("#btnBuscarDniModal").on("click", ejecutarBusquedaDni);
    $("#inputDniModal").on("keypress", function (e) {
        if (e.which === 13) ejecutarBusquedaDni();
    });
    // Solo números en el input DNI
    $("#inputDniModal").on("input", function () {
        this.value = this.value.replace(/[^0-9]/g, "");
    });

    // Limpiar al cerrar
    $("#modalNuevoClienteDni").on("hidden.bs.modal", function () {
        $("#inputDniModal").val("");
        $("#dniModalEstado").hide().empty();
        $("#dniModalResultado").hide();
    });

    // =====================================================
    // SELECTOR PRODUCTO
    // =====================================================
    $("#btnAgregarProducto").on("click", function () {
        if (cajaVtaCerrada) { alertaCajaCerradaVta(); return; }
        $("#buscarProductoVta").val("");
        $("#listaProductosVta .item-selector-vta").show();
        $("#contProductosVta").text($("#listaProductosVta .item-selector-vta").length);
        $("#sinResultadosProdVta").hide();
        $("#modalSelectorProductoVta").modal("show");
        $("#modalSelectorProductoVta").one("shown.bs.modal", function () { $("#buscarProductoVta").focus(); });
    });
    $("#buscarProductoVta").on("input", function () {
        var q = $.trim($(this).val()).toLowerCase();
        var vis = 0;
        $("#listaProductosVta .item-selector-vta").each(function () {
            var nombre = (this.getAttribute("data-nombre") || "").toLowerCase();
            var codigo = (this.getAttribute("data-codigo") || "").toLowerCase();
            var lab    = (this.getAttribute("data-lab")    || "").toLowerCase();
            var match  = !q || nombre.indexOf(q) > -1 || codigo.indexOf(q) > -1 || lab.indexOf(q) > -1;
            if (match) { $(this).show(); vis++; } else { $(this).hide(); }
        });
        $("#contProductosVta").text(vis);
        $("#sinResultadosProdVta").toggle(vis === 0);
    });
    $(document).on("click", "#listaProductosVta .item-selector-vta", function () {
        var d = $(this).data();
        if (venta.items.some(function (it) { return it.id_producto === d.id; })) {
            Swal.fire({ icon: "info", title: "Ya agregado", text: "Este producto ya esta en la lista.", timer: 2000, showConfirmButton: false }); return;
        }
        if (parseInt(d.stock) <= 0) {
            Swal.fire({ icon: "warning", title: "Sin stock", text: "Este producto no tiene stock disponible.", confirmButtonColor: "#1a7a4a" }); return;
        }
        $("#modal_item_id_producto_vta").val(d.id);
        $("#modal_item_nombre_vta").text(d.nombre);
        $("#modal_item_codigo_vta").text(d.codigo || "");
        $("#modal_item_stock_max_vta").val(parseInt(d.stock) || 0);
        $("#modal_item_precio_vta").val(parseFloat(d.precio || 0).toFixed(2));
        $("#modal_item_cantidad_vta").val(1).attr("max", parseInt(d.stock) || 1);
        $("#modal_item_descuento_vta").val("0.00");
        $("#modal_item_stock_disp_vta").text(d.stock + " uds disponibles");
        calcularSubtotalItemVta();
        $("#modalSelectorProductoVta").modal("hide");
        setTimeout(function () {
            $("#modalDetalleItemVta").modal("show");
            $("#modalDetalleItemVta").one("shown.bs.modal", function () { $("#modal_item_cantidad_vta").focus().select(); });
        }, 300);
    });

    function calcularSubtotalItemVta() {
        var precio    = parseFloat($("#modal_item_precio_vta").val()) || 0;
        var cantidad  = parseInt($("#modal_item_cantidad_vta").val()) || 0;
        var descuento = parseFloat($("#modal_item_descuento_vta").val()) || 0;
        var maxDesc   = Math.max(0, precio * cantidad);
        if (descuento < 0) { descuento = 0; $("#modal_item_descuento_vta").val("0.00"); }
        if (descuento > 0 && maxDesc > 0 && descuento >= maxDesc * 0.5) {
            $("#modal_item_descuento_vta").css({ "border-color": "#e67e22", "box-shadow": "0 0 0 .15rem rgba(230,126,34,.2)" });
        } else {
            $("#modal_item_descuento_vta").css({ "border-color": "", "box-shadow": "" });
        }
        var subtotal = Math.max(0, maxDesc - Math.min(descuento, maxDesc));
        $("#modal_item_subtotal_vta").text("S/. " + subtotal.toFixed(2));
        return subtotal;
    }
    $("#modal_item_precio_vta, #modal_item_cantidad_vta, #modal_item_descuento_vta").on("input", calcularSubtotalItemVta);

    // Alerta cuando el descuento supera el subtotal - con opcion de ajustar
    $("#modal_item_descuento_vta").on("change blur", function () {
        var $campo   = $(this);
        var precio   = parseFloat($("#modal_item_precio_vta").val()) || 0;
        var cantidad = parseInt($("#modal_item_cantidad_vta").val()) || 0;
        var maxDesc  = precio * cantidad;
        var val      = parseFloat($campo.val()) || 0;
        if (val < 0) { $campo.val("0.00"); calcularSubtotalItemVta(); return; }
        if (maxDesc > 0 && val >= maxDesc) {
            var maximo = (maxDesc - 0.01).toFixed(2);
            Swal.fire({
                icon: "warning",
                title: "Descuento invalido",
                html: "<div style='text-align:left;font-size:.9rem;'>" +
                      "El descuento ingresado <strong style='color:#e74c3c;'>S/. " + val.toFixed(2) + "</strong> " +
                      "es igual o mayor al subtotal del item.<br><br>" +
                      "<div style='background:#f8f9fa;border-radius:8px;padding:10px 14px;font-size:.85rem;'>" +
                      "<div><i class='fas fa-calculator mr-2 text-muted'></i>Subtotal bruto: <strong>S/. " + maxDesc.toFixed(2) + "</strong></div>" +
                      "<div style='margin-top:4px;'><i class='fas fa-tag mr-2 text-muted'></i>Descuento maximo: <strong style='color:#1a7a4a;'>S/. " + maximo + "</strong></div>" +
                      "</div></div>",
                showCancelButton: true,
                showDenyButton: true,
                confirmButtonColor: "#1a7a4a",
                denyButtonColor: "#e67e22",
                cancelButtonColor: "#6c757d",
                confirmButtonText: "<i class='fas fa-edit mr-1'></i> Corregir manualmente",
                denyButtonText: "<i class='fas fa-check mr-1'></i> Ajustar al maximo",
                cancelButtonText: "<i class='fas fa-times mr-1'></i> Cancelar",
                reverseButtons: false
            }).then(function (result) {
                if (result.isDenied) {
                    $campo.val(maximo);
                    calcularSubtotalItemVta();
                } else if (result.isDismissed && result.dismiss === Swal.DismissReason.cancel) {
                    $campo.val("0.00");
                    calcularSubtotalItemVta();
                } else {
                    $campo.val("").focus();
                }
            });
        }
    });

    // EDITAR PRECIO DE VENTA - modal profesional
    $("#btnEditarPrecioVta").on("click", function () {
        var idProd       = parseInt($("#modal_item_id_producto_vta").val());
        var nombreProd   = $("#modal_item_nombre_vta").text();
        var precioActual = parseFloat($("#modal_item_precio_vta").val()) || 0;

        // Cerrar el modal de Bootstrap para que SweetAlert reciba el foco correctamente
        $("#modalDetalleItemVta").modal("hide");

        setTimeout(function () {
            Swal.fire({
                title: '<i class="fas fa-tag mr-2" style="color:#27ae60;"></i>Actualizar Precio de Venta',
                html: '<div style="background:#f0fff4;border-radius:10px;padding:12px 16px;margin-bottom:16px;text-align:left;">' +
                        '<div style="font-size:.78rem;color:#999;text-transform:uppercase;font-weight:600;letter-spacing:.4px;margin-bottom:4px;"><i class="fas fa-pills mr-1"></i>Producto</div>' +
                        '<div style="font-weight:700;color:#2d3436;font-size:.92rem;">' + nombreProd + '</div>' +
                      '</div>' +
                      '<div style="text-align:left;margin-bottom:6px;">' +
                        '<label style="font-size:.8rem;font-weight:600;color:#495057;text-transform:uppercase;letter-spacing:.4px;">Precio actual</label>' +
                        '<div style="font-size:1.3rem;font-weight:700;color:#1a7a4a;margin-bottom:12px;">S/. ' + precioActual.toFixed(2) + '</div>' +
                        '<label style="font-size:.8rem;font-weight:600;color:#495057;text-transform:uppercase;letter-spacing:.4px;">Nuevo precio de venta <span style="color:#e74c3c;">*</span></label>' +
                      '</div>' +
                      '<div style="display:flex;align-items:center;gap:8px;">' +
                        '<span style="background:#d4edda;color:#1a7a4a;padding:8px 12px;border-radius:6px 0 0 6px;font-weight:700;border:1.5px solid #c8e6c9;border-right:none;">S/.</span>' +
                        '<input id="swal_precio_vta" type="number" step="0.01" min="0.01" ' +
                        'style="flex:1;padding:10px 12px;border:1.5px solid #dee2e6;border-radius:0 6px 6px 0;font-size:1rem;font-weight:600;color:#1a7a4a;outline:none;" ' +
                        'placeholder="0.00" value="' + precioActual.toFixed(2) + '">' +
                      '</div>' +
                      '<div style="font-size:.75rem;color:#999;margin-top:6px;text-align:left;"><i class="fas fa-info-circle mr-1"></i>Actualiza el precio de venta en la ficha del producto.</div>',
                showCancelButton: true,
                confirmButtonColor: "#1a7a4a",
                cancelButtonColor: "#6c757d",
                confirmButtonText: '<i class="fas fa-save mr-1"></i> Guardar precio',
                cancelButtonText: '<i class="fas fa-times mr-1"></i> Cancelar',
                focusConfirm: false,
                allowOutsideClick: false,
                didOpen: function () {
                    var inp = document.getElementById("swal_precio_vta");
                    inp.focus(); inp.select();
                    inp.addEventListener("focus", function () { this.style.borderColor = "#27ae60"; });
                    inp.addEventListener("blur",  function () { this.style.borderColor = "#dee2e6"; });
                },
                preConfirm: function () {
                    var v = parseFloat(document.getElementById("swal_precio_vta").value);
                    if (!v || v <= 0) { Swal.showValidationMessage("Ingresa un precio valido mayor a 0."); return false; }
                    return v;
                }
            }).then(function (r) {
                // Reabrir el modal de Bootstrap en cualquier caso
                setTimeout(function () { $("#modalDetalleItemVta").modal("show"); }, 200);

                if (!r.isConfirmed) return;
                var nuevoPrecio = r.value;
                var $btn = $("#btnEditarPrecioVta");
                $btn.prop("disabled", true).html('<i class="fas fa-spinner fa-spin"></i>');
                $.post("ventas.php", { accion: "actualizar_precio_venta", id_producto: idProd, precio_venta: nuevoPrecio }, function (res) {
                    $btn.prop("disabled", false).html('<i class="fas fa-pencil-alt"></i>');
                    try {
                        var data = typeof res === "string" ? JSON.parse(res) : res;
                        if (data.ok) {
                            $("#modal_item_precio_vta").val(nuevoPrecio.toFixed(2));
                            calcularSubtotalItemVta();
                            Swal.fire({
                                icon: "success", title: "Precio actualizado",
                                html: "Precio de venta de <b>" + nombreProd + "</b><br>actualizado a <b style='color:#1a7a4a;'>S/. " + nuevoPrecio.toFixed(2) + "</b>",
                                timer: 3000, timerProgressBar: true, showConfirmButton: false, toast: true, position: "top-end"
                            });
                        } else {
                            Swal.fire({ icon: "error", title: "Error al actualizar", text: data.msg || "No se pudo actualizar.", confirmButtonColor: "#1a7a4a" });
                        }
                    } catch(e) {
                        Swal.fire({ icon: "error", title: "Error", text: "Respuesta inesperada.", confirmButtonColor: "#1a7a4a" });
                    }
                }).fail(function () {
                    $btn.prop("disabled", false).html('<i class="fas fa-pencil-alt"></i>');
                    Swal.fire({ icon: "error", title: "Error de conexion", text: "No se pudo conectar con el servidor.", confirmButtonColor: "#1a7a4a" });
                });
            });
        }, 400); // Esperar que el modal de Bootstrap termine de cerrarse
    });
    $("#btnConfirmarItemVta").on("click", function () {
        var id = parseInt($("#modal_item_id_producto_vta").val());
        var nombre = $("#modal_item_nombre_vta").text();
        var codigo = $("#modal_item_codigo_vta").text();
        var precio = parseFloat($("#modal_item_precio_vta").val()) || 0;
        var cantidad = parseInt($("#modal_item_cantidad_vta").val()) || 0;
        var desc = parseFloat($("#modal_item_descuento_vta").val()) || 0;
        var stockMax = parseInt($("#modal_item_stock_max_vta").val()) || 0;

        if (cantidad <= 0) { Swal.fire({ icon: "warning", title: "Cantidad invalida", text: "Ingresa una cantidad mayor a 0.", confirmButtonColor: "#1a7a4a" }); return; }
        if (precio <= 0)   { Swal.fire({ icon: "warning", title: "Precio invalido", text: "Ingresa un precio mayor a 0.", confirmButtonColor: "#1a7a4a" }); return; }
        if (desc >= precio * cantidad) { Swal.fire({ icon: "warning", title: "Descuento invalido", text: "El descuento (S/. " + desc.toFixed(2) + ") no puede ser igual o mayor al total del item (S/. " + (precio * cantidad).toFixed(2) + ").", confirmButtonColor: "#1a7a4a" }); return; }
        if (cantidad > stockMax) { Swal.fire({ icon: "warning", title: "Stock insuficiente", text: "Solo hay " + stockMax + " unidades disponibles.", confirmButtonColor: "#1a7a4a" }); return; }

        var subtotal = calcularSubtotalItemVta();
        venta.items.push({ id_producto: id, nombre: nombre, codigo: codigo, precio_unitario: precio, cantidad: cantidad, descuento: desc, subtotal: subtotal, stock_max: stockMax });
        renderTablaDetalleVta(); actualizarTotalesVta();
        $("#modalDetalleItemVta").modal("hide");
    });

    // =====================================================
    // TABLA DETALLE
    // =====================================================
    function renderTablaDetalleVta() {
        var $tbody = $("#tablaDetalleVta tbody");
        $tbody.empty();
        if (venta.items.length === 0) {
            $tbody.html("<tr class=\"fila-vacia-vta\"><td colspan=\"7\"><i class=\"fas fa-shopping-cart\"></i>Sin productos agregados.<br><small>Haz clic en \"Agregar Producto\" para comenzar.</small></td></tr>");
            return;
        }
        $.each(venta.items, function (i, item) {
            var $tr = $("<tr></tr>").html(
                "<td class=\"text-center\"><div class=\"num-fila-vta\">" + (i+1) + "</div></td>" +
                "<td><div style=\"font-weight:700;font-size:.88rem;\">" + item.nombre + "</div><div style=\"font-size:.75rem;color:#999;\">" + (item.codigo||"") + "</div></td>" +
                "<td class=\"text-center\"><input type=\"number\" class=\"form-control form-control-sm text-center input-cantidad-vta\" value=\"" + item.cantidad + "\" min=\"1\" max=\"" + item.stock_max + "\" style=\"width:70px;margin:auto;\" data-idx=\"" + i + "\"></td>" +
                "<td class=\"text-right\"><input type=\"number\" step=\"0.01\" class=\"form-control form-control-sm text-right input-precio-vta\" value=\"" + item.precio_unitario.toFixed(2) + "\" min=\"0.01\" style=\"width:90px;margin-left:auto;\" data-idx=\"" + i + "\"></td>" +
                "<td class=\"text-right\"><input type=\"number\" step=\"0.01\" class=\"form-control form-control-sm text-right input-descuento-vta\" value=\"" + item.descuento.toFixed(2) + "\" min=\"0\" style=\"width:80px;margin-left:auto;\" data-idx=\"" + i + "\"></td>" +
                "<td class=\"text-right font-weight-bold text-success subtotal-item-vta\">S/. " + item.subtotal.toFixed(2) + "</td>" +
                "<td class=\"text-center\"><button type=\"button\" class=\"btn-quitar-vta\" data-idx=\"" + i + "\" title=\"Quitar\"><i class=\"fas fa-times\"></i></button></td>"
            );
            $tbody.append($tr);
        });
        $("#inputsOcultosVta").empty();
        $.each(venta.items, function (i, item) {
            $("#inputsOcultosVta").append(
                "<input type=\"hidden\" name=\"items[" + i + "][id_producto]\" value=\"" + item.id_producto + "\">" +
                "<input type=\"hidden\" name=\"items[" + i + "][cantidad]\" value=\"" + item.cantidad + "\">" +
                "<input type=\"hidden\" name=\"items[" + i + "][precio_unitario]\" value=\"" + item.precio_unitario + "\">" +
                "<input type=\"hidden\" name=\"items[" + i + "][descuento]\" value=\"" + item.descuento + "\">" +
                "<input type=\"hidden\" name=\"items[" + i + "][subtotal]\" value=\"" + item.subtotal + "\">"
            );
        });
    }

    $(document).on("input", ".input-cantidad-vta, .input-precio-vta, .input-descuento-vta", function () {
        var idx = parseInt($(this).data("idx")); var item = venta.items[idx]; if (!item) return;
        if ($(this).hasClass("input-cantidad-vta")) {
            var cant = parseInt($(this).val()) || 1;
            if (cant > item.stock_max) { cant = item.stock_max; $(this).val(cant); }
            item.cantidad = cant;
        }
        if ($(this).hasClass("input-precio-vta"))    item.precio_unitario = parseFloat($(this).val()) || 0;
        if ($(this).hasClass("input-descuento-vta")) item.descuento       = parseFloat($(this).val()) || 0;
        item.subtotal = Math.max(0, (item.precio_unitario * item.cantidad) - item.descuento);
        $(this).closest("tr").find(".subtotal-item-vta").text("S/. " + item.subtotal.toFixed(2));
        $("input[name=\"items[" + idx + "][cantidad]\"]").val(item.cantidad);
        $("input[name=\"items[" + idx + "][precio_unitario]\"]").val(item.precio_unitario);
        $("input[name=\"items[" + idx + "][descuento]\"]").val(item.descuento);
        $("input[name=\"items[" + idx + "][subtotal]\"]").val(item.subtotal);
        actualizarTotalesVta();
    });
    $(document).on("click", ".btn-quitar-vta", function () {
        venta.items.splice(parseInt($(this).data("idx")), 1);
        renderTablaDetalleVta(); actualizarTotalesVta();
    });

    // =====================================================
    // TOTALES
    // =====================================================
    function actualizarTotalesVta() {
        var subtotal = venta.items.reduce(function (s, it) { return s + it.subtotal; }, 0);
        var descGlobal = parseFloat($("#descuento_global_vta").val()) || 0;
        var base = Math.max(0, subtotal - descGlobal);
        var igv = venta.aplica_igv ? base * 0.18 : 0;
        var total = base + igv;
        $("#resumen_subtotal_vta").text("S/. " + subtotal.toFixed(2));
        $("#resumen_descuento_vta").text("- S/. " + descGlobal.toFixed(2));
        $("#resumen_igv_vta").text(venta.aplica_igv ? "S/. " + igv.toFixed(2) : "No aplica");
        $("#resumen_total_vta").text("S/. " + total.toFixed(2));
        $("#hidden_subtotal_vta").val(subtotal.toFixed(2));
        $("#hidden_igv_vta").val(igv.toFixed(2));
        $("#hidden_total_vta").val(total.toFixed(2));
        $("#contadorItemsVta").text(venta.items.length + " producto" + (venta.items.length !== 1 ? "s" : ""));
        if (venta.tipo_pago === "credito") actualizarPreviewCuotas();
    }
    $("#descuento_global_vta").on("input", actualizarTotalesVta);
    $("#aplica_igv_vta").on("change", function () {
        venta.aplica_igv = $(this).is(":checked");
        actualizarTotalesVta();
    });

    // =====================================================
    // METODO PAGO
    // =====================================================
    $(document).on("click", ".btn-metodo-vta", function () {
        $(".btn-metodo-vta").removeClass("activo");
        $(this).addClass("activo");
        venta.metodo_pago = $(this).data("metodo");
        $("#hidden_metodo_pago_vta").val(venta.metodo_pago);
    });
    $(".btn-metodo-vta[data-metodo=efectivo]").addClass("activo");

    // =====================================================
    // SUBMIT
    // =====================================================
    $("#formNuevaVenta").on("submit", function (e) {
        e.preventDefault();
        if (!venta.id_cliente) { Swal.fire({ icon: "warning", title: "Cliente requerido", text: "Selecciona un cliente.", confirmButtonColor: "#1a7a4a" }); return; }
        if (venta.items.length === 0) { Swal.fire({ icon: "warning", title: "Sin productos", text: "Agrega al menos un producto.", confirmButtonColor: "#1a7a4a" }); return; }
        var total = parseFloat($("#hidden_total_vta").val()) || 0;
        if (total <= 0) { Swal.fire({ icon: "warning", title: "Total invalido", text: "El total debe ser mayor a 0.", confirmButtonColor: "#1a7a4a" }); return; }
        if (venta.tipo_pago === "credito" && !$("#fecha_primera_cuota_vta").val()) {
            Swal.fire({ icon: "warning", title: "Fecha requerida", text: "Indica la fecha de la primera cuota.", confirmButtonColor: "#1a7a4a" }); return;
        }
        var tipoPagoLabel = venta.tipo_pago === "contado" ? "CONTADO" : "CREDITO (" + venta.num_cuotas + " cuota(s))";
        Swal.fire({
            icon: "question", title: "Registrar venta?",
            html: "<b>" + venta.items.length + " producto(s)</b> &mdash; Total: <b>S/. " + total.toFixed(2) + "</b><br>Cliente: <b>" + venta.nombre_cliente + "</b><br>Pago: <b>" + tipoPagoLabel + "</b>",
            showCancelButton: true, confirmButtonColor: "#1a7a4a", cancelButtonColor: "#6c757d",
            confirmButtonText: "<i class=\"fas fa-save mr-1\"></i> Si, registrar", cancelButtonText: "Cancelar"
        }).then(function (r) {
            if (r.isConfirmed) {
                $("#btnSubmitVenta").prop("disabled", true).html("<i class=\"fas fa-spinner fa-spin mr-1\"></i> Guardando...");
                document.getElementById("formNuevaVenta").submit();
            }
        });
    });

    // =====================================================
    // VER DETALLE VENTA (AJAX)
    // =====================================================
    $(document).on("click", ".btn-ver-venta", function () {
        var id = $(this).data("id");
        $("#modalVerVenta .modal-body").html("<div class=\"text-center py-4\"><i class=\"fas fa-spinner fa-spin fa-2x text-muted\"></i><p class=\"mt-2 text-muted\">Cargando...</p></div>");
        $("#modalVerVenta").modal("show");
        $.get("ventas.php", { accion: "detalle_ajax", id_venta: id }, function (html) {
            $("#modalVerVenta .modal-body").html(html);
        }).fail(function () { $("#modalVerVenta .modal-body").html("<div class=\"alert alert-danger\">Error al cargar el detalle.</div>"); });
    });

    // =====================================================
    // REGISTRAR PAGO (credito)
    // =====================================================
    $(document).on("click", ".btn-pagar-venta", function () {
        var id       = $(this).data("id");
        var saldo    = parseFloat($(this).data("saldo")) || 0;
        var cliente  = $(this).data("cliente") || "";
        $("#pago_id_venta").val(id);
        $("#pago_saldo_display_vta").text("S/. " + saldo.toFixed(2));
        $("#pago_cliente_label").text(cliente);
        $("#monto_pago_vta").val(saldo.toFixed(2)).attr("max", saldo);
        $(".btn-metodo-pago-vta").css({ background: "#fff", color: "#555", borderColor: "#dee2e6" });
        $(".btn-metodo-pago-vta[data-metodo=efectivo]").css({ background: "#1a7a4a", color: "#fff", borderColor: "#1a7a4a" });
        $("#metodo_pago_abono_vta").val("efectivo");
        $("#modalPagarVenta").modal("show");
    });
    $(document).on("click", ".btn-metodo-pago-vta", function () {
        $(".btn-metodo-pago-vta").css({ background: "#fff", color: "#555", borderColor: "#dee2e6" });
        $(this).css({ background: "#1a7a4a", color: "#fff", borderColor: "#1a7a4a" });
        $("#metodo_pago_abono_vta").val($(this).data("metodo"));
    });

    // =====================================================
    // ANULAR VENTA
    // =====================================================
    $(document).on("click", ".btn-anular-venta", function () {
        var id  = $(this).data("id");
        var num = $(this).data("numero") || ("#" + id);
        Swal.fire({
            icon: "error", title: "Anular venta?",
            html: "La venta <b>" + num + "</b> sera anulada.<br><strong class=\"text-danger\">El stock de los productos sera revertido.</strong>",
            showCancelButton: true, confirmButtonColor: "#e74c3c", cancelButtonColor: "#6c757d",
            confirmButtonText: "<i class=\"fas fa-ban mr-1\"></i> Si, anular", cancelButtonText: "Cancelar"
        }).then(function (r) {
            if (r.isConfirmed) {
                var $f = $("<form method=\"POST\" style=\"display:none;\"></form>");
                $f.append("<input type=\"hidden\" name=\"accion\" value=\"anular\">");
                $f.append("<input type=\"hidden\" name=\"id_venta\" value=\"" + id + "\">");
                $("body").append($f); $f.submit();
            }
        });
    });

    // =====================================================
    // LIMPIAR FORMULARIO
    // =====================================================
    $("#btnLimpiarFormVta").on("click", function () {
        Swal.fire({ icon: "question", title: "Limpiar formulario?", text: "Se perderan todos los datos ingresados.", showCancelButton: true, confirmButtonColor: "#e67e22", cancelButtonColor: "#6c757d", confirmButtonText: "Si, limpiar", cancelButtonText: "Cancelar" })
        .then(function (r) {
            if (r.isConfirmed) {
                venta.items = []; venta.id_cliente = null; venta.nombre_cliente = ""; venta.tipo_pago = "contado"; venta.num_cuotas = 1;
                $("#formNuevaVenta")[0].reset();
                $("#campoCliente").removeClass("seleccionado").find(".selected-text").text("").hide().end().find(".placeholder-text").show();
                $("#id_cliente_hidden").val("");
                $(".btn-tipo-pago-vta").css({ background: "#fff", color: "#555", borderColor: "#dee2e6" });
                $(".btn-tipo-pago-vta[data-tipo=contado]").css({ background: "#1a7a4a", color: "#fff", borderColor: "#1a7a4a" });
                $("#hidden_tipo_pago_vta").val("contado");
                $("#panelCreditoVta").hide(); $("#bloqueMetodoPagoVta").show(); $("#resumenCuotaVta").hide();
                $(".btn-metodo-vta").removeClass("activo"); $(".btn-metodo-vta[data-metodo=efectivo]").addClass("activo"); $("#hidden_metodo_pago_vta").val("efectivo");
                $(".btn-cuota-vta").css({ background: "#fff", color: "#555", borderColor: "#dee2e6" }); $(".btn-cuota-vta[data-cuotas=1]").css({ background: "#e67e22", color: "#fff", borderColor: "#e67e22" });
                $("#hidden_num_cuotas_vta").val(1); $("#previewCuotasVta").hide();
                renderTablaDetalleVta(); actualizarTotalesVta();
                cargarSiguienteNumero($("#tipo_comprobante_vta").val());
            }
        });
    });

    // Init
    renderTablaDetalleVta();
    actualizarTotalesVta();

});
