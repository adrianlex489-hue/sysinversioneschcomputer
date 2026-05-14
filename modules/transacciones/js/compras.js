/**
 * compras.js - Modulo Registro de Compras | Botica 2026
 * Cuotas max 4, numeracion automatica de comprobantes
 */

$(function () {

    // ── Helper: alerta + sonido cuando caja está cerrada ─────────────────────
    var cajaCompCerrada = !!document.getElementById('alertaCajaComp');

    function alertaCajaCerradaComp() {
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

    var compra = {
        id_proveedor: null,
        nombre_proveedor: "",
        items: [],
        aplica_igv: true,
        metodo_pago: "efectivo",
        tipo_pago: "contado",
        num_cuotas: 1
    };

    // DataTable historial
    if ($("#tablaHistorialCompras").length) {
        $("#tablaHistorialCompras").DataTable({
            language: { search: "", searchPlaceholder: "Buscar compra...", lengthMenu: "Mostrar _MENU_ registros", info: "Mostrando _START_ a _END_ de _TOTAL_", infoEmpty: "Sin registros", zeroRecords: "No se encontraron resultados", paginate: { previous: "&#8249;", next: "&#8250;" } },
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
    var prefijos = { ticket: "T001", nota: "N001", factura: "F001", boleta: "B001" };
    var etiquetas = { factura: "Factura", boleta: "Boleta", ticket: "Ticket", nota: "Nota de Venta" };

    function cargarSiguienteNumero(tipo) {
        $.get("compras.php", { accion: "siguiente_numero", tipo: tipo }, function (data) {
            try {
                var res = typeof data === "string" ? JSON.parse(data) : data;
                var num = res.numero || (prefijos[tipo] + "-00000001");
                var partes = num.split("-");
                var serie = partes.slice(0, -1).join("-");
                var secuencia = partes[partes.length - 1];
                $("#prefijo_comprobante").text(serie);
                $("#numero_comprobante").val(num);
            } catch (e) {
                $("#numero_comprobante").val(prefijos[tipo] + "-00000001");
            }
        });
    }

    // Cargar numero al cambiar tipo de comprobante
    $("#tipo_comprobante").on("change", function () {
        cargarSiguienteNumero($(this).val());
    });
    // Cargar al inicio
    cargarSiguienteNumero($("#tipo_comprobante").val());

    // =====================================================
    // TIPO DE PAGO (Contado / Credito)
    // =====================================================
    $(document).on("click", ".btn-tipo-pago", function () {
        $(".btn-tipo-pago").css({ background: "#fff", color: "#555", borderColor: "#dee2e6" });
        $(this).css({ background: "#1a5276", color: "#fff", borderColor: "#1a5276" });
        compra.tipo_pago = $(this).data("tipo");
        $("#hidden_tipo_pago").val(compra.tipo_pago);
        if (compra.tipo_pago === "credito") {
            $("#panelCredito").slideDown(200);
            $("#bloqueMetodoPago").slideUp(200);
            $("#resumenCuota").show();
            actualizarPreviewCuotas();
        } else {
            $("#panelCredito").slideUp(200);
            $("#bloqueMetodoPago").slideDown(200);
            $("#resumenCuota").hide();
        }
    });
    // Activar contado por defecto
    $(".btn-tipo-pago[data-tipo=contado]").css({ background: "#1a5276", color: "#fff", borderColor: "#1a5276" });

    // =====================================================
    // SELECTOR DE CUOTAS (1-4)
    // =====================================================
    $(document).on("click", ".btn-cuota", function () {
        $(".btn-cuota").css({ background: "#fff", color: "#555", borderColor: "#dee2e6" });
        $(this).css({ background: "#e67e22", color: "#fff", borderColor: "#e67e22" });
        compra.num_cuotas = parseInt($(this).data("cuotas"));
        $("#hidden_num_cuotas").val(compra.num_cuotas);
        actualizarPreviewCuotas();
    });
    // Activar 1 cuota por defecto
    $(".btn-cuota[data-cuotas=1]").css({ background: "#e67e22", color: "#fff", borderColor: "#e67e22" });

    // Estilo de los botones de cuota
    $(".btn-cuota").css({ border: "2px solid #dee2e6", borderRadius: "8px", padding: "8px 16px", fontSize: ".9rem", fontWeight: "700", cursor: "pointer", transition: "all .2s", background: "#fff", color: "#555" });

    // =====================================================
    // PREVIEW DE CUOTAS
    // =====================================================
    function actualizarPreviewCuotas() {
        var total = parseFloat($("#hidden_total").val()) || 0;
        var fechaStr = $("#fecha_primera_cuota").val();
        var frecuencia = parseInt($("#frecuencia_dias").val()) || 30;
        var n = compra.num_cuotas;

        if (!fechaStr || total <= 0) {
            $("#previewCuotas").hide();
            $("#resumenCuotaTexto").text("Completa el total y la fecha de la primera cuota.");
            return;
        }

        var montoCuota = Math.round((total / n) * 100) / 100;
        var diferencia = Math.round((total - montoCuota * n) * 100) / 100;
        var fecha = new Date(fechaStr + "T00:00:00");
        var meses = ["Ene","Feb","Mar","Abr","May","Jun","Jul","Ago","Sep","Oct","Nov","Dic"];

        var html = "<div style=\"background:#fff3e0;border-radius:8px;padding:12px;border:1px solid #ffe0b2;\">";
        html += "<div style=\"font-size:.8rem;font-weight:700;color:#e67e22;margin-bottom:8px;\"><i class=\"fas fa-calendar-alt mr-1\"></i>CRONOGRAMA DE CUOTAS</div>";
        html += "<table style=\"width:100%;font-size:.82rem;\">";
        html += "<thead><tr style=\"color:#999;\"><th style=\"padding:3px 6px;\">Cuota</th><th style=\"padding:3px 6px;text-align:right;\">Monto</th><th style=\"padding:3px 6px;text-align:center;\">Vencimiento</th></tr></thead><tbody>";

        for (var i = 1; i <= n; i++) {
            var montoEsta = (i === n) ? Math.round((montoCuota + diferencia) * 100) / 100 : montoCuota;
            var d = fecha.getDate();
            var m = meses[fecha.getMonth()];
            var y = fecha.getFullYear();
            html += "<tr style=\"border-top:1px solid #ffe0b2;\">";
            html += "<td style=\"padding:4px 6px;font-weight:700;color:#e67e22;\">" + i + " / " + n + "</td>";
            html += "<td style=\"padding:4px 6px;text-align:right;font-weight:700;\">S/. " + montoEsta.toFixed(2) + "</td>";
            html += "<td style=\"padding:4px 6px;text-align:center;\">" + d + " " + m + " " + y + "</td>";
            html += "</tr>";
            fecha.setDate(fecha.getDate() + frecuencia);
        }
        html += "</tbody></table></div>";
        $("#previewCuotas").html(html).show();
        $("#resumenCuotaTexto").text(n + " cuota(s) de S/. " + montoCuota.toFixed(2) + " c/u");
    }

    $("#fecha_primera_cuota, #frecuencia_dias").on("change", actualizarPreviewCuotas);

    // =====================================================
    // SELECTOR PROVEEDOR
    // =====================================================
    $("#btnSeleccionarProveedor, #campoProveedor").on("click", function () {
        if (cajaCompCerrada) { alertaCajaCerradaComp(); return; }
        $("#buscarProveedor").val("");
        $("#listaProveedores .item-selector").each(function () {
            this.style.display = "";
        });
        var total = $("#listaProveedores .item-selector").length;
        $("#contProveedores").text(total);
        $("#sinResultadosProv").hide();
        $("#modalSelectorProveedor").modal("show");
        $("#modalSelectorProveedor").one("shown.bs.modal", function () {
            $("#buscarProveedor").focus();
        });
    });
    $("#buscarProveedor").on("input", function () {
        var q = $.trim($(this).val()).toLowerCase();
        var vis = 0;
        $("#listaProveedores .item-selector").each(function () {
            var nombre = (this.getAttribute("data-nombre") || "").toLowerCase();
            var ruc    = (this.getAttribute("data-ruc")    || "").toLowerCase();
            var match  = !q || nombre.indexOf(q) > -1 || ruc.indexOf(q) > -1;
            this.style.display = match ? "" : "none";
            if (match) vis++;
        });
        $("#contProveedores").text(vis);
        $("#sinResultadosProv").toggle(vis === 0);
    });
    $(document).on("click", "#listaProveedores .item-selector", function () {
        compra.id_proveedor = $(this).data("id"); compra.nombre_proveedor = $(this).data("nombre");
        $("#id_proveedor_hidden").val(compra.id_proveedor);
        $("#campoProveedor").addClass("seleccionado").find(".selected-text").text(compra.nombre_proveedor).show().end().find(".placeholder-text").hide();
        $("#modalSelectorProveedor").modal("hide");
        Swal.fire({ icon: "success", title: "Proveedor seleccionado", html: "<b>" + compra.nombre_proveedor + "</b>", toast: true, position: "top-end", timer: 2000, timerProgressBar: true, showConfirmButton: false, background: "#eafaf1", color: "#1a7a4a", iconColor: "#27ae60" });
    });
    $("#btnLimpiarProveedor").on("click", function (e) {
        e.stopPropagation(); compra.id_proveedor = null; compra.nombre_proveedor = "";
        $("#id_proveedor_hidden").val("");
        $("#campoProveedor").removeClass("seleccionado").find(".selected-text").text("").hide().end().find(".placeholder-text").show();
    });

    // =====================================================
    // BÚSQUEDA RUC — MODAL DEDICADO
    // =====================================================
    function seleccionarProveedorEnModal(id, nombre, guardado) {
        compra.id_proveedor     = id;
        compra.nombre_proveedor = nombre;
        $("#id_proveedor_hidden").val(id);
        $("#campoProveedor").addClass("seleccionado")
            .find(".selected-text").text(nombre).show()
            .end().find(".placeholder-text").hide();
        $("#modalNuevoProveedorRuc").modal("hide");
        $("#modalSelectorProveedor").modal("hide");

        var titulo  = guardado ? "Proveedor guardado y seleccionado" : "Proveedor seleccionado";
        var mensaje = guardado
            ? "<b>" + nombre + "</b> fue registrado en el sistema y seleccionado para esta compra."
            : "<b>" + nombre + "</b> seleccionado para esta compra.";

        Swal.fire({ icon: "success", title: titulo, html: mensaje,
            toast: true, position: "top-end", timer: 3500, timerProgressBar: true,
            showConfirmButton: false, background: "#eafaf1", color: "#1a7a4a", iconColor: "#27ae60" });
    }

    // Abrir modal RUC
    $("#btnNuevoProveedorRuc").on("click", function () {
        $("#inputRucModal").val("");
        $("#rucModalEstado").hide().empty();
        $("#rucModalResultado").hide();
        $("#modalNuevoProveedorRuc").modal("show");
        $("#modalNuevoProveedorRuc").one("shown.bs.modal", function () {
            $("#inputRucModal").focus();
        });
    });

    // Buscar RUC
    function ejecutarBusquedaRuc() {
        var ruc = $.trim($("#inputRucModal").val());
        if (!/^\d{11}$/.test(ruc)) {
            $("#rucModalEstado").html(
                '<div class="alert alert-warning py-2 mb-3" style="border-radius:8px;font-size:.85rem;">' +
                '<i class="fas fa-exclamation-triangle mr-2"></i>El RUC debe tener exactamente 11 dígitos.</div>'
            ).show();
            return;
        }
        var $btn = $("#btnBuscarRucModal");
        $btn.prop("disabled", true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Consultando SUNAT...');
        $("#rucModalEstado").html(
            '<div class="text-center py-3"><i class="fas fa-spinner fa-spin fa-2x mb-2 d-block" style="color:#1a5276;"></i>' +
            '<small class="text-muted">Consultando base de datos SUNAT...</small></div>'
        ).show();
        $("#rucModalResultado").hide();

        $.post("ajax_buscar_ruc.php", { ruc: ruc, guardar: 0 }, function (res) {
            $btn.prop("disabled", false).html('<i class="fas fa-search mr-1"></i>Buscar');
            $("#rucModalEstado").hide().empty();

            if (!res.success) {
                $("#rucModalEstado").html(
                    '<div class="alert alert-danger py-2 mb-3" style="border-radius:8px;font-size:.85rem;">' +
                    '<i class="fas fa-times-circle mr-2"></i>' + res.error + '</div>'
                ).show();
                return;
            }

            var d = res.datos;
            var yaExiste = res.ya_existe;

            $("#rucRazonSocial").text(d.razon_social);
            $("#rucNumero").text(d.ruc);
            if (d.direccion) {
                $("#rucDireccion").text(d.direccion);
                $("#rucDireccionRow").show();
            } else {
                $("#rucDireccionRow").hide();
            }

            if (yaExiste) {
                $("#rucBadge").html('<span style="background:#d4edda;color:#155724;padding:2px 10px;border-radius:20px;font-size:.72rem;font-weight:700;"><i class="fas fa-check-circle mr-1"></i>Ya registrado</span>');
                $("#rucOpciones").html(
                    '<button type="button" class="btn btn-block font-weight-bold" id="btnRucUsar" style="background:#1a5276;color:#fff;border-radius:8px;padding:10px;">' +
                    '<i class="fas fa-check mr-2"></i>Seleccionar este proveedor</button>'
                );
                $("#btnRucUsar").on("click", function () {
                    seleccionarProveedorEnModal(d.id_proveedor, d.razon_social, false);
                });
            } else {
                $("#rucBadge").html('<span style="background:#e3f2fd;color:#1a5276;padding:2px 10px;border-radius:20px;font-size:.72rem;font-weight:700;"><i class="fas fa-building mr-1"></i>Nuevo proveedor</span>');
                $("#rucOpciones").html(
                    '<div class="row" style="gap:0;">' +
                    '<div class="col-6 pr-1">' +
                    '<button type="button" class="btn btn-outline-primary btn-block font-weight-bold" id="btnRucUsarSin" style="border-radius:8px;padding:10px;font-size:.85rem;border-color:#1a5276;color:#1a5276;">' +
                    '<i class="fas fa-bolt mr-1"></i>Usar sin guardar' +
                    '<div style="font-size:.72rem;font-weight:400;opacity:.8;margin-top:2px;">Solo para esta compra</div></button></div>' +
                    '<div class="col-6 pl-1">' +
                    '<button type="button" class="btn btn-block font-weight-bold" id="btnRucGuardarUsar" style="background:#1a5276;color:#fff;border-radius:8px;padding:10px;font-size:.85rem;">' +
                    '<i class="fas fa-save mr-1"></i>Guardar y usar' +
                    '<div style="font-size:.72rem;font-weight:400;opacity:.8;margin-top:2px;">Registrar en sistema</div></button></div>' +
                    '</div>'
                );
                $("#btnRucUsarSin").on("click", function () {
                    seleccionarProveedorEnModal(0, d.razon_social, false);
                });
                $("#btnRucGuardarUsar").on("click", function () {
                    var $b = $(this).prop("disabled", true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Guardando...');
                    $.post("ajax_buscar_ruc.php", { ruc: d.ruc, guardar: 1 }, function (res2) {
                        if (res2.success) {
                            seleccionarProveedorEnModal(res2.id_proveedor || 0, d.razon_social, true);
                            if (res2.id_proveedor) {
                                var nuevoItem = '<div class="item-selector" data-id="' + res2.id_proveedor +
                                    '" data-nombre="' + d.razon_social + '" data-ruc="' + d.ruc + '">' +
                                    '<div class="d-flex align-items-center gap-3">' +
                                    '<div style="width:38px;height:38px;background:#e3f2fd;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-industry" style="color:#1a5276;font-size:.85rem;"></i></div>' +
                                    '<div><div class="item-nombre">' + d.razon_social + '</div>' +
                                    '<div class="item-sub"><i class="fas fa-id-card mr-1"></i>RUC: ' + d.ruc + '</div></div></div></div>';
                                $("#listaProveedores").prepend(nuevoItem);
                                $("#contProveedores").text(parseInt($("#contProveedores").text()) + 1);
                            }
                        } else {
                            $b.prop("disabled", false).html('<i class="fas fa-save mr-1"></i>Guardar y usar');
                            Swal.fire({ icon: "error", title: "Error al guardar", text: res2.error, confirmButtonColor: "#1a5276" });
                        }
                    }, "json").fail(function () {
                        $b.prop("disabled", false).html('<i class="fas fa-save mr-1"></i>Guardar y usar');
                    });
                });
            }
            $("#rucModalResultado").show();
        }, "json").fail(function () {
            $btn.prop("disabled", false).html('<i class="fas fa-search mr-1"></i>Buscar');
            $("#rucModalEstado").html(
                '<div class="alert alert-danger py-2 mb-3" style="border-radius:8px;font-size:.85rem;">' +
                '<i class="fas fa-wifi mr-2"></i>Error de conexión. Intenta nuevamente.</div>'
            ).show();
        });
    }

    $("#btnBuscarRucModal").on("click", ejecutarBusquedaRuc);
    $("#inputRucModal").on("keypress", function (e) {
        if (e.which === 13) ejecutarBusquedaRuc();
    });
    $("#inputRucModal").on("input", function () {
        this.value = this.value.replace(/[^0-9]/g, "");
    });

    $("#modalNuevoProveedorRuc").on("hidden.bs.modal", function () {
        $("#inputRucModal").val("");
        $("#rucModalEstado").hide().empty();
        $("#rucModalResultado").hide();
    });

    // =====================================================
    // SELECTOR PRODUCTO
    // =====================================================
    $("#btnAgregarProducto").on("click", function () {
        if (cajaCompCerrada) { alertaCajaCerradaComp(); return; }
        $("#buscarProducto").val("");
        $("#listaProductos .item-selector").show();
        $("#contProductos").text($("#listaProductos .item-selector").length);
        $("#sinResultadosProd").hide();
        $("#modalSelectorProducto").modal("show");
        $("#modalSelectorProducto").one("shown.bs.modal", function () {
            $("#buscarProducto").focus();
        });
    });
    $("#buscarProducto").on("input", function () {
        var q = $.trim($(this).val()).toLowerCase();
        var vis = 0;
        $("#listaProductos .item-selector").each(function () {
            var nombre = (this.getAttribute("data-nombre") || "").toLowerCase();
            var codigo = (this.getAttribute("data-codigo") || "").toLowerCase();
            var lab    = (this.getAttribute("data-lab")    || "").toLowerCase();
            var match  = !q || nombre.indexOf(q) > -1 || codigo.indexOf(q) > -1 || lab.indexOf(q) > -1;
            this.style.display = match ? "" : "none";
            if (match) vis++;
        });
        $("#contProductos").text(vis);
        $("#sinResultadosProd").toggle(vis === 0);
    });
    $(document).on("click", "#listaProductos .item-selector", function () {
        var d = $(this).data();
        if (compra.items.some(function (it) { return it.id_producto === d.id; })) {
            Swal.fire({ icon: "info", title: "Ya agregado", text: "Este producto ya esta en la lista.", timer: 2000, showConfirmButton: false }); return;
        }
        $("#modal_item_id_producto").val(d.id);
        $("#modal_item_nombre").text(d.nombre);
        $("#modal_item_codigo").text(d.codigo || "");
        $("#modal_item_precio").val(parseFloat(d.precio || 0).toFixed(2));
        $("#modal_item_cantidad").val(1);
        $("#modal_item_descuento").val("0.00");
        $("#modal_item_lote").val(""); $("#modal_item_vencimiento").val("");
        calcularSubtotalItem();
        $("#modalSelectorProducto").modal("hide");
        setTimeout(function () {
            $("#modalDetalleItem").modal("show");
            $("#modalDetalleItem").one("shown.bs.modal", function () { $("#modal_item_cantidad").focus().select(); });
        }, 300);
    });


    function calcularSubtotalItem() {
        var precio    = parseFloat($("#modal_item_precio").val()) || 0;
        var cantidad  = parseInt($("#modal_item_cantidad").val()) || 0;
        var descuento = parseFloat($("#modal_item_descuento").val()) || 0;
        var maxDesc   = Math.max(0, precio * cantidad);
        if (descuento < 0) { descuento = 0; $("#modal_item_descuento").val("0.00"); }
        if (descuento > 0 && maxDesc > 0 && descuento >= maxDesc * 0.5) {
            $("#modal_item_descuento").css({ "border-color": "#e67e22", "box-shadow": "0 0 0 .15rem rgba(230,126,34,.2)" });
        } else {
            $("#modal_item_descuento").css({ "border-color": "", "box-shadow": "" });
        }
        var subtotal = Math.max(0, maxDesc - Math.min(descuento, maxDesc));
        $("#modal_item_subtotal").text("S/. " + subtotal.toFixed(2));
        return subtotal;
    }
    $("#modal_item_precio, #modal_item_cantidad, #modal_item_descuento").on("input", calcularSubtotalItem);

    // Alerta cuando el descuento supera el subtotal - con 3 opciones
    $("#modal_item_descuento").on("change blur", function () {
        var $campo   = $(this);
        var precio   = parseFloat($("#modal_item_precio").val()) || 0;
        var cantidad = parseInt($("#modal_item_cantidad").val()) || 0;
        var maxDesc  = precio * cantidad;
        var val      = parseFloat($campo.val()) || 0;
        if (val < 0) { $campo.val("0.00"); calcularSubtotalItem(); return; }
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
                confirmButtonColor: "#1a5276",
                denyButtonColor: "#e67e22",
                cancelButtonColor: "#6c757d",
                confirmButtonText: "<i class='fas fa-edit mr-1'></i> Corregir manualmente",
                denyButtonText: "<i class='fas fa-check mr-1'></i> Ajustar al maximo",
                cancelButtonText: "<i class='fas fa-times mr-1'></i> Cancelar",
                reverseButtons: false
            }).then(function (result) {
                if (result.isDenied) {
                    $campo.val(maximo);
                    calcularSubtotalItem();
                } else if (result.isDismissed && result.dismiss === Swal.DismissReason.cancel) {
                    $campo.val("0.00");
                    calcularSubtotalItem();
                } else {
                    $campo.val("").focus();
                }
            });
        }
    });

    // EDITAR PRECIO DE COMPRA - modal profesional
    $("#btnEditarPrecioComp").on("click", function () {
        var idProd       = parseInt($("#modal_item_id_producto").val());
        var nombreProd   = $("#modal_item_nombre").text();
        var precioActual = parseFloat($("#modal_item_precio").val()) || 0;
        $("#modalDetalleItem").modal("hide");
        setTimeout(function () {
            Swal.fire({
                title: "<i class='fas fa-tag mr-2' style='color:#e67e22;'></i>Actualizar Precio de Compra",
                html: "<div style='background:#f8f9fa;border-radius:10px;padding:12px 16px;margin-bottom:16px;text-align:left;'>" +
                        "<div style='font-size:.78rem;color:#999;text-transform:uppercase;font-weight:600;letter-spacing:.4px;margin-bottom:4px;'><i class='fas fa-pills mr-1'></i>Producto</div>" +
                        "<div style='font-weight:700;color:#2d3436;font-size:.92rem;'>" + nombreProd + "</div>" +
                      "</div>" +
                      "<div style='text-align:left;margin-bottom:6px;'>" +
                        "<label style='font-size:.8rem;font-weight:600;color:#495057;text-transform:uppercase;letter-spacing:.4px;'>Precio actual</label>" +
                        "<div style='font-size:1.3rem;font-weight:700;color:#1a7a4a;margin-bottom:12px;'>S/. " + precioActual.toFixed(2) + "</div>" +
                        "<label style='font-size:.8rem;font-weight:600;color:#495057;text-transform:uppercase;letter-spacing:.4px;'>Nuevo precio de compra <span style='color:#e74c3c;'>*</span></label>" +
                      "</div>" +
                      "<div style='display:flex;align-items:center;gap:8px;'>" +
                        "<span style='background:#e3f2fd;color:#1a5276;padding:8px 12px;border-radius:6px 0 0 6px;font-weight:700;border:1.5px solid #bbdefb;border-right:none;'>S/.</span>" +
                        "<input id='swal_precio_comp' type='number' step='0.01' min='0.01' style='flex:1;padding:10px 12px;border:1.5px solid #dee2e6;border-radius:0 6px 6px 0;font-size:1rem;font-weight:600;color:#1a5276;outline:none;' placeholder='0.00' value='" + precioActual.toFixed(2) + "'>" +
                      "</div>" +
                      "<div style='font-size:.75rem;color:#999;margin-top:6px;text-align:left;'><i class='fas fa-info-circle mr-1'></i>Actualiza el precio de compra en la ficha del producto.</div>",
                showCancelButton: true,
                confirmButtonColor: "#1a5276",
                cancelButtonColor: "#6c757d",
                confirmButtonText: "<i class='fas fa-save mr-1'></i> Guardar precio",
                cancelButtonText: "<i class='fas fa-times mr-1'></i> Cancelar",
                focusConfirm: false,
                allowOutsideClick: false,
                didOpen: function () {
                    var inp = document.getElementById("swal_precio_comp");
                    inp.focus(); inp.select();
                    inp.addEventListener("focus", function () { this.style.borderColor = "#1a5276"; });
                    inp.addEventListener("blur",  function () { this.style.borderColor = "#dee2e6"; });
                },
                preConfirm: function () {
                    var v = parseFloat(document.getElementById("swal_precio_comp").value);
                    if (!v || v <= 0) { Swal.showValidationMessage("Ingresa un precio valido mayor a 0."); return false; }
                    return v;
                }
            }).then(function (r) {
                setTimeout(function () { $("#modalDetalleItem").modal("show"); }, 200);
                if (!r.isConfirmed) return;
                var nuevoPrecio = r.value;
                var $btn = $("#btnEditarPrecioComp");
                $btn.prop("disabled", true).html("<i class='fas fa-spinner fa-spin'></i>");
                $.post("compras.php", { accion: "actualizar_precio_compra", id_producto: idProd, precio_compra: nuevoPrecio }, function (res) {
                    $btn.prop("disabled", false).html("<i class='fas fa-pencil-alt'></i>");
                    try {
                        var data = typeof res === "string" ? JSON.parse(res) : res;
                        if (data.ok) {
                            $("#modal_item_precio").val(nuevoPrecio.toFixed(2));
                            calcularSubtotalItem();
                            Swal.fire({ icon: "success", title: "Precio actualizado",
                                html: "Precio de compra de <b>" + nombreProd + "</b><br>actualizado a <b style='color:#1a7a4a;'>S/. " + nuevoPrecio.toFixed(2) + "</b>",
                                timer: 3000, timerProgressBar: true, showConfirmButton: false, toast: true, position: "top-end" });
                        } else {
                            Swal.fire({ icon: "error", title: "Error al actualizar", text: data.msg || "No se pudo actualizar.", confirmButtonColor: "#1a5276" });
                        }
                    } catch(e) {
                        Swal.fire({ icon: "error", title: "Error", text: "Respuesta inesperada.", confirmButtonColor: "#1a5276" });
                    }
                }).fail(function () {
                    $btn.prop("disabled", false).html("<i class='fas fa-pencil-alt'></i>");
                    Swal.fire({ icon: "error", title: "Error de conexion", text: "No se pudo conectar.", confirmButtonColor: "#1a5276" });
                });
            });
        }, 400);
    });

    $("#btnConfirmarItem").on("click", function () {
        var id = parseInt($("#modal_item_id_producto").val());
        var nombre = $("#modal_item_nombre").text();
        var codigo = $("#modal_item_codigo").text();
        var precio = parseFloat($("#modal_item_precio").val()) || 0;
        var cantidad = parseInt($("#modal_item_cantidad").val()) || 0;
        var desc = parseFloat($("#modal_item_descuento").val()) || 0;
        var lote = $("#modal_item_lote").val().trim().toUpperCase();
        var vence = $("#modal_item_vencimiento").val();
        if (cantidad <= 0) { Swal.fire({ icon: "warning", title: "Cantidad invalida", text: "Ingresa una cantidad mayor a 0.", confirmButtonColor: "#1a5276" }); return; }
        if (precio <= 0)   { Swal.fire({ icon: "warning", title: "Precio invalido", text: "Ingresa un precio mayor a 0.", confirmButtonColor: "#1a5276" }); return; }
        if (desc >= precio * cantidad) { Swal.fire({ icon: "warning", title: "Descuento invalido", text: "El descuento (S/. " + desc.toFixed(2) + ") no puede ser igual o mayor al total del item (S/. " + (precio * cantidad).toFixed(2) + ").", confirmButtonColor: "#1a5276" }); return; }
        if (!lote)         { Swal.fire({ icon: "warning", title: "Codigo de lote requerido", text: "Ingresa el codigo de lote del fabricante.", confirmButtonColor: "#1a5276" }); return; }
        if (!vence)        { Swal.fire({ icon: "warning", title: "Fecha de vencimiento requerida", text: "Ingresa la fecha de vencimiento del lote.", confirmButtonColor: "#1a5276" }); return; }
        var subtotal = calcularSubtotalItem();
        compra.items.push({ id_producto: id, nombre: nombre, codigo: codigo, precio_compra: precio, cantidad: cantidad, descuento: desc, subtotal: subtotal, codigo_lote: lote, fecha_vencimiento: vence });
        renderTablaDetalle(); actualizarTotales();
        $("#modalDetalleItem").modal("hide");
    });

    // =====================================================
    // TABLA DETALLE
    // =====================================================
    function renderTablaDetalle() {
        var $tbody = $("#tablaDetalle tbody");
        $tbody.empty();
        if (compra.items.length === 0) {
            $tbody.html("<tr class=\"fila-vacia\"><td colspan=\"8\"><i class=\"fas fa-box-open\"></i>Sin productos agregados.<br><small>Haz clic en Agregar Producto para comenzar.</small></td></tr>");
            return;
        }
        $.each(compra.items, function (i, item) {
            var hoy = new Date(); var vence = new Date(item.fecha_vencimiento);
            var dias = Math.ceil((vence - hoy) / (1000 * 60 * 60 * 24));
            var av = dias < 0 ? "<span class=\"alerta-vencido ml-1\"><i class=\"fas fa-exclamation-triangle\"></i> VENCIDO</span>" : (dias <= 90 ? "<span class=\"alerta-vence-pronto ml-1\"><i class=\"fas fa-clock\"></i> " + dias + "d</span>" : "");
            var fmtV = item.fecha_vencimiento ? item.fecha_vencimiento.split("-").reverse().join("/") : "---";
            var $tr = $("<tr></tr>").html(
                "<td class=\"text-center\"><div class=\"num-fila-comp\">" + (i+1) + "</div></td>" +
                "<td><div style=\"font-weight:700;font-size:.88rem;\">" + item.nombre + "</div><div style=\"font-size:.75rem;color:#999;\">" + (item.codigo||"") + "</div></td>" +
                "<td class=\"text-center\"><code style=\"font-size:.8rem;background:#e0f7fa;color:#117a8b;padding:2px 6px;border-radius:4px;\">" + item.codigo_lote + "</code><div style=\"font-size:.72rem;color:#999;margin-top:2px;\">" + fmtV + av + "</div></td>" +
                "<td class=\"text-center\"><input type=\"number\" class=\"form-control form-control-sm text-center input-cantidad\" value=\"" + item.cantidad + "\" min=\"1\" style=\"width:70px;margin:auto;\" data-idx=\"" + i + "\"></td>" +
                "<td class=\"text-right\"><input type=\"number\" step=\"0.01\" class=\"form-control form-control-sm text-right input-precio\" value=\"" + item.precio_compra.toFixed(2) + "\" min=\"0.01\" style=\"width:90px;margin-left:auto;\" data-idx=\"" + i + "\"></td>" +
                "<td class=\"text-right\"><input type=\"number\" step=\"0.01\" class=\"form-control form-control-sm text-right input-descuento\" value=\"" + item.descuento.toFixed(2) + "\" min=\"0\" style=\"width:80px;margin-left:auto;\" data-idx=\"" + i + "\"></td>" +
                "<td class=\"text-right font-weight-bold text-success subtotal-item\">S/. " + item.subtotal.toFixed(2) + "</td>" +
                "<td class=\"text-center\"><button type=\"button\" class=\"btn-quitar-item btn-quitar\" data-idx=\"" + i + "\" title=\"Quitar\"><i class=\"fas fa-times\"></i></button></td>"
            );
            $tbody.append($tr);
        });
        $("#inputsOcultos").empty();
        $.each(compra.items, function (i, item) {
            $("#inputsOcultos").append(
                "<input type=\"hidden\" name=\"items[" + i + "][id_producto]\" value=\"" + item.id_producto + "\">" +
                "<input type=\"hidden\" name=\"items[" + i + "][cantidad]\" value=\"" + item.cantidad + "\">" +
                "<input type=\"hidden\" name=\"items[" + i + "][precio_compra]\" value=\"" + item.precio_compra + "\">" +
                "<input type=\"hidden\" name=\"items[" + i + "][descuento]\" value=\"" + item.descuento + "\">" +
                "<input type=\"hidden\" name=\"items[" + i + "][subtotal]\" value=\"" + item.subtotal + "\">" +
                "<input type=\"hidden\" name=\"items[" + i + "][codigo_lote]\" value=\"" + item.codigo_lote + "\">" +
                "<input type=\"hidden\" name=\"items[" + i + "][fecha_vencimiento]\" value=\"" + item.fecha_vencimiento + "\">"
            );
        });
    }

    $(document).on("input", ".input-cantidad, .input-precio, .input-descuento", function () {
        var idx = parseInt($(this).data("idx")); var item = compra.items[idx]; if (!item) return;
        if ($(this).hasClass("input-cantidad"))  item.cantidad      = parseInt($(this).val())   || 1;
        if ($(this).hasClass("input-precio"))    item.precio_compra = parseFloat($(this).val()) || 0;
        if ($(this).hasClass("input-descuento")) item.descuento     = parseFloat($(this).val()) || 0;
        item.subtotal = Math.max(0, (item.precio_compra * item.cantidad) - item.descuento);
        $(this).closest("tr").find(".subtotal-item").text("S/. " + item.subtotal.toFixed(2));
        $("input[name=\"items[" + idx + "][cantidad]\"]").val(item.cantidad);
        $("input[name=\"items[" + idx + "][precio_compra]\"]").val(item.precio_compra);
        $("input[name=\"items[" + idx + "][descuento]\"]").val(item.descuento);
        $("input[name=\"items[" + idx + "][subtotal]\"]").val(item.subtotal);
        actualizarTotales();
    });
    $(document).on("click", ".btn-quitar", function () {
        compra.items.splice(parseInt($(this).data("idx")), 1);
        renderTablaDetalle(); actualizarTotales();
    });

    // =====================================================
    // TOTALES
    // =====================================================
    function actualizarTotales() {
        var subtotal = compra.items.reduce(function (s, it) { return s + it.subtotal; }, 0);
        var descGlobal = parseFloat($("#descuento_global").val()) || 0;
        var base = Math.max(0, subtotal - descGlobal);
        var igv = compra.aplica_igv ? base * 0.18 : 0;
        var total = base + igv;
        $("#resumen_subtotal").text("S/. " + subtotal.toFixed(2));
        $("#resumen_descuento").text("- S/. " + descGlobal.toFixed(2));
        $("#resumen_igv").text(compra.aplica_igv ? "S/. " + igv.toFixed(2) : "No aplica");
        $("#resumen_total").text("S/. " + total.toFixed(2));
        $("#hidden_subtotal").val(subtotal.toFixed(2));
        $("#hidden_igv").val(igv.toFixed(2));
        $("#hidden_total").val(total.toFixed(2));
        $("#contadorItems").text(compra.items.length + " producto" + (compra.items.length !== 1 ? "s" : ""));
        if (compra.tipo_pago === "credito") actualizarPreviewCuotas();
    }
    $("#descuento_global").on("input", actualizarTotales);
    $("#aplica_igv").on("change", function () {
        compra.aplica_igv = $(this).is(":checked");
        actualizarTotales();
    });

    // =====================================================
    // METODO PAGO
    // =====================================================
    $(document).on("click", ".btn-metodo", function () {
        $(".btn-metodo").removeClass("activo");
        $(this).addClass("activo");
        compra.metodo_pago = $(this).data("metodo");
        $("#hidden_metodo_pago").val(compra.metodo_pago);
    });
    $(".btn-metodo[data-metodo=efectivo]").addClass("activo");

    // =====================================================
    // SUBMIT
    // =====================================================
    $("#formNuevaCompra").on("submit", function (e) {
        e.preventDefault();
        if (!compra.id_proveedor) { Swal.fire({ icon: "warning", title: "Proveedor requerido", text: "Selecciona un proveedor.", confirmButtonColor: "#1a5276" }); return; }
        if (compra.items.length === 0) { Swal.fire({ icon: "warning", title: "Sin productos", text: "Agrega al menos un producto.", confirmButtonColor: "#1a5276" }); return; }
        var total = parseFloat($("#hidden_total").val()) || 0;
        if (total <= 0) { Swal.fire({ icon: "warning", title: "Total invalido", text: "El total debe ser mayor a 0.", confirmButtonColor: "#1a5276" }); return; }
        if (compra.tipo_pago === "credito" && !$("#fecha_primera_cuota").val()) {
            Swal.fire({ icon: "warning", title: "Fecha requerida", text: "Indica la fecha de la primera cuota.", confirmButtonColor: "#1a5276" }); return;
        }
        var tipoPagoLabel = compra.tipo_pago === "contado" ? "CONTADO" : "CREDITO (" + compra.num_cuotas + " cuota(s))";
        Swal.fire({
            icon: "question", title: "Registrar compra?",
            html: "<b>" + compra.items.length + " producto(s)</b> &mdash; Total: <b>S/. " + total.toFixed(2) + "</b><br>Proveedor: <b>" + compra.nombre_proveedor + "</b><br>Pago: <b>" + tipoPagoLabel + "</b>",
            showCancelButton: true, confirmButtonColor: "#1a5276", cancelButtonColor: "#6c757d",
            confirmButtonText: "<i class=\"fas fa-save mr-1\"></i> Si, registrar", cancelButtonText: "Cancelar"
        }).then(function (r) {
            if (r.isConfirmed) {
                $("#btnSubmitCompra").prop("disabled", true).html("<i class=\"fas fa-spinner fa-spin mr-1\"></i> Guardando...");
                document.getElementById("formNuevaCompra").submit();
            }
        });
    });

    // =====================================================
    // VER DETALLE COMPRA
    // =====================================================
    $(document).on("click", ".btn-ver-compra", function () {
        var id = $(this).data("id");
        $("#modalVerCompra .modal-body").html("<div class=\"text-center py-4\"><i class=\"fas fa-spinner fa-spin fa-2x text-muted\"></i><p class=\"mt-2 text-muted\">Cargando...</p></div>");
        $("#modalVerCompra").modal("show");
        $.get("compras.php", { accion: "detalle_ajax", id_compra: id }, function (html) {
            $("#modalVerCompra .modal-body").html(html);
        }).fail(function () { $("#modalVerCompra .modal-body").html("<div class=\"alert alert-danger\">Error al cargar el detalle.</div>"); });
    });

    // =====================================================
    // REGISTRAR PAGO (credito)
    // =====================================================
    $(document).on("click", ".btn-pagar-compra", function () {
        var id = $(this).data("id");
        var saldo = parseFloat($(this).data("saldo")) || 0;
        var proveedor = $(this).data("proveedor") || "";
        $("#pago_id_compra").val(id);
        $("#pago_saldo_display").text("S/. " + saldo.toFixed(2));
        $("#pago_proveedor_label").text(proveedor);
        $("#monto_pago").val(saldo.toFixed(2)).attr("max", saldo);
        $(".btn-metodo-pago").css({ background: "#fff", color: "#555", borderColor: "#dee2e6" });
        $(".btn-metodo-pago[data-metodo=efectivo]").css({ background: "#1a7a4a", color: "#fff", borderColor: "#1a7a4a" });
        $("#metodo_pago_abono").val("efectivo");
        $("#modalPagarCompra").modal("show");
    });
    $(document).on("click", ".btn-metodo-pago", function () {
        $(".btn-metodo-pago").css({ background: "#fff", color: "#555", borderColor: "#dee2e6" });
        $(this).css({ background: "#1a7a4a", color: "#fff", borderColor: "#1a7a4a" });
        $("#metodo_pago_abono").val($(this).data("metodo"));
    });

    // =====================================================
    // ANULAR COMPRA
    // =====================================================
    $(document).on("click", ".btn-anular-compra", function () {
        var id = $(this).data("id"); var num = $(this).data("numero") || ("#" + id);
        Swal.fire({
            icon: "error", title: "Anular compra?",
            html: "La compra <b>" + num + "</b> sera anulada.<br><strong class=\"text-danger\">El stock de los lotes sera revertido.</strong>",
            showCancelButton: true, confirmButtonColor: "#e74c3c", cancelButtonColor: "#6c757d",
            confirmButtonText: "<i class=\"fas fa-ban mr-1\"></i> Si, anular", cancelButtonText: "Cancelar"
        }).then(function (r) {
            if (r.isConfirmed) {
                var $f = $("<form method=\"POST\" style=\"display:none;\"></form>");
                $f.append("<input type=\"hidden\" name=\"accion\" value=\"anular\">");
                $f.append("<input type=\"hidden\" name=\"id_compra\" value=\"" + id + "\">");
                $("body").append($f); $f.submit();
            }
        });
    });

    // =====================================================
    // LIMPIAR FORMULARIO
    // =====================================================
    $("#btnLimpiarForm").on("click", function () {
        Swal.fire({ icon: "question", title: "Limpiar formulario?", text: "Se perderan todos los datos ingresados.", showCancelButton: true, confirmButtonColor: "#e67e22", cancelButtonColor: "#6c757d", confirmButtonText: "Si, limpiar", cancelButtonText: "Cancelar" })
        .then(function (r) {
            if (r.isConfirmed) {
                compra.items = []; compra.id_proveedor = null; compra.nombre_proveedor = ""; compra.tipo_pago = "contado"; compra.num_cuotas = 1;
                $("#formNuevaCompra")[0].reset();
                $("#campoProveedor").removeClass("seleccionado").find(".selected-text").text("").hide().end().find(".placeholder-text").show();
                $("#id_proveedor_hidden").val("");
                $(".btn-tipo-pago").css({ background: "#fff", color: "#555", borderColor: "#dee2e6" });
                $(".btn-tipo-pago[data-tipo=contado]").css({ background: "#1a5276", color: "#fff", borderColor: "#1a5276" });
                $("#hidden_tipo_pago").val("contado");
                $("#panelCredito").hide(); $("#bloqueMetodoPago").show(); $("#resumenCuota").hide();
                $(".btn-metodo").removeClass("activo"); $(".btn-metodo[data-metodo=efectivo]").addClass("activo"); $("#hidden_metodo_pago").val("efectivo");
                $(".btn-cuota").css({ background: "#fff", color: "#555", borderColor: "#dee2e6" }); $(".btn-cuota[data-cuotas=1]").css({ background: "#e67e22", color: "#fff", borderColor: "#e67e22" });
                $("#hidden_num_cuotas").val(1); $("#previewCuotas").hide();
                renderTablaDetalle(); actualizarTotales();
                cargarSiguienteNumero($("#tipo_comprobante").val());
            }
        });
    });

    // Init
    renderTablaDetalle();
    actualizarTotales();

});
