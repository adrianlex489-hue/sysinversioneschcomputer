/**
 * caja.js — Módulo Gestión de Caja | Botica 2026
 * Apertura, cierre, movimientos manuales e historial
 */

$(function () {

    // =====================================================
    // DATATABLES
    // =====================================================
    var dtLang = {
        search: "", searchPlaceholder: "Buscar...",
        lengthMenu: "Mostrar _MENU_ registros",
        info: "Mostrando _START_ a _END_ de _TOTAL_",
        infoEmpty: "Sin registros", zeroRecords: "No se encontraron resultados",
        paginate: { previous: "&#8249;", next: "&#8250;" }
    };

    if ($("#tablaMovimientos").length) {
        $("#tablaMovimientos").DataTable({
            language: dtLang, responsive: true, autoWidth: false,
            order: [[0, "desc"]], pageLength: 15,
            columnDefs: [{ orderable: false, targets: [-1] }]
        });
    }
    if ($("#tablaHistorialCajas").length) {
        $("#tablaHistorialCajas").DataTable({
            language: dtLang, responsive: true, autoWidth: false,
            order: [[0, "desc"]], pageLength: 10,
            columnDefs: [{ orderable: false, targets: [-1] }]
        });
    }

    // =====================================================
    // SELECTOR DE TURNO (apertura)
    // =====================================================
    $(document).on("click", ".btn-turno", function () {
        $(".btn-turno").removeClass("activo");
        $(this).addClass("activo");
        $("#hidden_turno").val($(this).data("turno"));
    });

    // =====================================================
    // SELECTOR MÉTODO PAGO (movimiento manual)
    // =====================================================
    $(document).on("click", ".btn-metodo-caja", function () {
        $(".btn-metodo-caja").removeClass("activo");
        $(this).addClass("activo");
        $("#hidden_metodo_mov").val($(this).data("metodo"));
    });

    // =====================================================
    // TIPO MOVIMIENTO (ingreso/egreso)
    // =====================================================
    $(document).on("click", ".btn-tipo-mov", function () {
        $(".btn-tipo-mov").removeClass("activo");
        $(this).addClass("activo");
        var tipo = $(this).data("tipo");
        $("#hidden_tipo_mov").val(tipo);
        // Cambiar color del botón confirmar
        if (tipo === "ingreso") {
            $("#btnConfirmarMovimiento").css({ background: "linear-gradient(135deg,#1a7a4a,#27ae60)", borderColor: "#1a7a4a" });
        } else {
            $("#btnConfirmarMovimiento").css({ background: "linear-gradient(135deg,#922b21,#e74c3c)", borderColor: "#e74c3c" });
        }
    });

    // =====================================================
    // CIERRE DE CAJA — calcular diferencia en tiempo real
    // =====================================================
    $("#monto_final_cierre").on("input", function () {
        var montoEsperado = parseFloat($("#monto_esperado_cierre").val()) || 0;
        var montoFinal    = parseFloat($(this).val()) || 0;
        var diferencia    = montoFinal - montoEsperado;

        var $divDif = $("#diferencia_cierre");
        var $txtDif = $("#texto_diferencia");

        $divDif.show();
        if (diferencia > 0) {
            $txtDif.html('<i class="fas fa-arrow-up mr-1"></i>Sobrante: <strong class="diferencia-positiva">S/. ' + diferencia.toFixed(2) + '</strong>');
        } else if (diferencia < 0) {
            $txtDif.html('<i class="fas fa-arrow-down mr-1"></i>Faltante: <strong class="diferencia-negativa">S/. ' + Math.abs(diferencia).toFixed(2) + '</strong>');
        } else {
            $txtDif.html('<i class="fas fa-check-circle mr-1"></i><strong class="diferencia-cero">Cuadre exacto</strong>');
        }
    });

    // =====================================================
    // SUBMIT APERTURA
    // =====================================================
    $("#formAperturaCaja").on("submit", function (e) {
        e.preventDefault();
        var turno  = $("#hidden_turno").val();
        var monto  = parseFloat($("#monto_inicial_apertura").val()) || 0;
        if (!turno) {
            Swal.fire({ icon: "warning", title: "Turno requerido", text: "Selecciona el turno de trabajo.", confirmButtonColor: "#1a7a4a" });
            return;
        }
        var turnoLabel = { "mañana": "MAÑANA ☀️", "tarde": "TARDE 🌤️", "noche": "NOCHE 🌙" }[turno] || turno.toUpperCase();
        Swal.fire({
            icon: "question",
            title: "¿Aperturar caja?",
            html: "Turno: <strong>" + turnoLabel + "</strong><br>Monto inicial: <strong>S/. " + monto.toFixed(2) + "</strong>",
            showCancelButton: true,
            confirmButtonColor: "#1a7a4a",
            cancelButtonColor: "#6c757d",
            confirmButtonText: '<i class="fas fa-lock-open mr-1"></i> Sí, aperturar',
            cancelButtonText: "Cancelar"
        }).then(function (r) {
            if (r.isConfirmed) {
                $("#formAperturaCaja")[0].submit();
            }
        });
    });

    // =====================================================
    // SUBMIT MOVIMIENTO MANUAL
    // =====================================================
    $("#formMovimientoManual").on("submit", function (e) {
        e.preventDefault();
        var tipo        = $("#hidden_tipo_mov").val();
        var descripcion = $("#descripcion_mov").val().trim();
        var monto       = parseFloat($("#monto_mov").val()) || 0;
        var metodo      = $("#hidden_metodo_mov").val();

        if (!tipo)        { Swal.fire({ icon: "warning", title: "Tipo requerido",        text: "Selecciona ingreso o egreso.",          confirmButtonColor: "#1a7a4a" }); return; }
        if (!descripcion) { Swal.fire({ icon: "warning", title: "Descripción requerida", text: "Ingresa una descripción del movimiento.", confirmButtonColor: "#1a7a4a" }); return; }
        if (monto <= 0)   { Swal.fire({ icon: "warning", title: "Monto inválido",        text: "El monto debe ser mayor a 0.",           confirmButtonColor: "#1a7a4a" }); return; }
        if (!metodo)      { Swal.fire({ icon: "warning", title: "Método requerido",      text: "Selecciona el método de pago.",          confirmButtonColor: "#1a7a4a" }); return; }

        var tipoLabel  = tipo === "ingreso" ? "INGRESO 💰" : "EGRESO 💸";
        var colorBtn   = tipo === "ingreso" ? "#1a7a4a" : "#e74c3c";
        Swal.fire({
            icon: "question",
            title: "¿Registrar movimiento?",
            html: "Tipo: <strong>" + tipoLabel + "</strong><br>Monto: <strong>S/. " + monto.toFixed(2) + "</strong><br>Descripción: <em>" + descripcion + "</em>",
            showCancelButton: true,
            confirmButtonColor: colorBtn,
            cancelButtonColor: "#6c757d",
            confirmButtonText: '<i class="fas fa-save mr-1"></i> Sí, registrar',
            cancelButtonText: "Cancelar"
        }).then(function (r) {
            if (r.isConfirmed) {
                $("#formMovimientoManual")[0].submit();
            }
        });
    });

    // =====================================================
    // CIERRE DE CAJA
    // =====================================================
    $("#btnAbrirCierre").on("click", function () {
        $("#modalCierreCaja").modal("show");
    });

    $("#formCierreCaja").on("submit", function (e) {
        e.preventDefault();
        var montoFinal = parseFloat($("#monto_final_cierre").val());
        if (isNaN(montoFinal) || montoFinal < 0) {
            Swal.fire({ icon: "warning", title: "Monto inválido", text: "Ingresa el monto contado en caja (puede ser 0).", confirmButtonColor: "#1a7a4a" });
            return;
        }
        var montoEsperado = parseFloat($("#monto_esperado_cierre").val()) || 0;
        var diferencia    = montoFinal - montoEsperado;
        var difTexto      = diferencia === 0
            ? "Cuadre exacto ✅"
            : (diferencia > 0 ? "Sobrante de S/. " + diferencia.toFixed(2) : "Faltante de S/. " + Math.abs(diferencia).toFixed(2));

        Swal.fire({
            icon: diferencia === 0 ? "success" : "warning",
            title: "¿Cerrar caja?",
            html: "Monto esperado: <strong>S/. " + montoEsperado.toFixed(2) + "</strong><br>" +
                  "Monto contado: <strong>S/. " + montoFinal.toFixed(2) + "</strong><br>" +
                  "<span style='color:" + (diferencia === 0 ? "#1a7a4a" : (diferencia > 0 ? "#27ae60" : "#e74c3c")) + ";font-weight:700;'>" + difTexto + "</span>",
            showCancelButton: true,
            confirmButtonColor: "#e74c3c",
            cancelButtonColor: "#6c757d",
            confirmButtonText: '<i class="fas fa-lock mr-1"></i> Sí, cerrar caja',
            cancelButtonText: "Cancelar"
        }).then(function (r) {
            if (r.isConfirmed) {
                $("#formCierreCaja")[0].submit();
            }
        });
    });

    // =====================================================
    // VER DETALLE CAJA (historial)
    // =====================================================
    $(document).on("click", ".btn-ver-caja", function () {
        var id = $(this).data("id");
        $("#modalDetalleCaja .modal-body").html(
            '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i><p class="mt-2 text-muted">Cargando...</p></div>'
        );
        $("#modalDetalleCaja").modal("show");
        $.get("caja.php", { accion: "detalle_ajax", id_caja: id }, function (html) {
            $("#modalDetalleCaja .modal-body").html(html);
        }).fail(function () {
            $("#modalDetalleCaja .modal-body").html('<div class="alert alert-danger">Error al cargar el detalle.</div>');
        });
    });

    // =====================================================
    // LIMPIAR FORM MOVIMIENTO al cerrar modal
    // =====================================================
    $("#modalMovimientoManual").on("hidden.bs.modal", function () {
        $("#descripcion_mov").val("");
        $("#monto_mov").val("");
        $("#obs_mov").val("");
        $(".btn-tipo-mov").removeClass("activo");
        $(".btn-metodo-caja").removeClass("activo");
        $(".btn-metodo-caja[data-metodo='efectivo']").addClass("activo");
        $("#hidden_tipo_mov").val("");
        $("#hidden_metodo_mov").val("efectivo");
        $("#btnConfirmarMovimiento").css({ background: "linear-gradient(135deg,#1a7a4a,#27ae60)", borderColor: "#1a7a4a" });
    });

});
