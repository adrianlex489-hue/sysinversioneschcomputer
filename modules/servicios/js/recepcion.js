/**
 * recepcion.js — Módulo Recepción de Equipos | SysInversiones CH Computer
 * Gestión de órdenes de servicio técnico
 */

$(function () {

    // ── DataTables ────────────────────────────────────────────────────────────
    $('#tablaActivas').DataTable({
        language: { url: 'https://cdn.datatables.net/plug-ins/1.10.25/i18n/Spanish.json' },
        order: [[6, 'desc']],
        pageLength: 15,
        responsive: true
    });
    $('#tablaHistorial').DataTable({
        language: { url: 'https://cdn.datatables.net/plug-ins/1.10.25/i18n/Spanish.json' },
        order: [[5, 'desc']],
        pageLength: 15,
        responsive: true
    });

    // ══════════════════════════════════════════════════════════════════════════
    // SELECTOR DE CLIENTE
    // ══════════════════════════════════════════════════════════════════════════

    // Abrir modal selector
    $('#campoClienteRec').on('click', function () {
        $('#buscarClienteRec').val('');
        $('#listaClientesRec .rec-item-selector').show();
        $('#contClientesRec').text($('#listaClientesRec .rec-item-selector').length);
        $('#sinResultadosCliRec').hide();
        $('#modalSelectorClienteRec').modal('show');
        $('#modalSelectorClienteRec').one('shown.bs.modal', function () {
            $('#buscarClienteRec').focus();
        });
    });

    // Filtrar lista
    $('#buscarClienteRec').on('input', function () {
        const q = $.trim($(this).val()).toLowerCase();
        let vis = 0;
        $('#listaClientesRec .rec-item-selector').each(function () {
            const nombre = (this.getAttribute('data-nombre') || '').toLowerCase();
            const doc    = (this.getAttribute('data-doc')    || '').toLowerCase();
            const match  = !q || nombre.indexOf(q) > -1 || doc.indexOf(q) > -1;
            this.style.display = match ? '' : 'none';
            if (match) vis++;
        });
        $('#contClientesRec').text(vis);
        $('#sinResultadosCliRec').toggle(vis === 0);
    });

    // Seleccionar desde la lista
    $(document).on('click', '#listaClientesRec .rec-item-selector', function () {
        const id         = $(this).data('id');
        const nombre     = $(this).data('nombre');
        const tipoCliente = $(this).data('tipo-cliente') || 'natural';
        const esEmpresa  = tipoCliente === 'empresa';
        seleccionarClienteRec(id, nombre, esEmpresa, tipoCliente);
        Swal.fire({
            icon: 'success', title: 'Cliente seleccionado', html: '<b>' + nombre + '</b>',
            toast: true, position: 'top-end', timer: 2000, timerProgressBar: true,
            showConfirmButton: false, background: '#eafaf1', color: '#1a7a4a', iconColor: '#27ae60'
        });
    });

    // Limpiar cliente
    $('#btnLimpiarClienteRec').on('click', function (e) {
        e.stopPropagation();
        $('#hiddenIdCliente').val('');
        $('#hiddenTipoCliente').val('natural');
        $('#campoClienteRec').removeClass('rec-seleccionado')
            .find('.rec-selected').text('').hide()
            .end().find('.rec-placeholder').show();
        $(this).hide();
        $('#resumenCliente').text('—');
    });

    // Función central: seleccionar cliente
    function seleccionarClienteRec(id, nombre, esEmpresa, tipoCliente) {
        esEmpresa   = esEmpresa   || false;
        tipoCliente = tipoCliente || (esEmpresa ? 'empresa' : 'natural');
        $('#hiddenIdCliente').val(id || 0);
        $('#hiddenTipoCliente').val(tipoCliente);
        const icono = esEmpresa
            ? '<i class="fas fa-building mr-2 text-primary"></i>'
            : '<i class="fas fa-user-check mr-2"></i>';
        $('#campoClienteRec').addClass('rec-seleccionado')
            .find('.rec-selected').html(icono + nombre).show()
            .end().find('.rec-placeholder').hide();
        $('#btnLimpiarClienteRec').show();
        $('#resumenCliente').text(nombre);
        $('#modalSelectorClienteRec').modal('hide');
        $('#modalDniRec').modal('hide');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // MODAL DNI / RUC
    // ══════════════════════════════════════════════════════════════════════════

    // Abrir modal DNI desde el selector
    $('#btnAbrirDniRec').on('click', function () {
        $('#modalSelectorClienteRec').modal('hide');
        setTimeout(function () {
            resetModalDni();
            $('#modalDniRec').modal('show');
            $('#modalDniRec').one('shown.bs.modal', function () { $('#inputDniRec').focus(); });
        }, 350);
    });

    // Limpiar modal DNI al cerrar
    $('#modalDniRec').on('hidden.bs.modal', function () { resetModalDni(); });

    function resetModalDni() {
        $('#inputDniRec').val('');
        $('#dniRecEstado').hide().empty();
        $('#dniRecResultado').hide();
        $('#dniRecSunatRow').remove();
    }

    // Tabs DNI / RUC
    var modoDocRec = 'dni';

    $(document).on('click', '#tabDniRec, #tabRucRec', function () {
        modoDocRec = $(this).data('modo');
        $('#tabDniRec').toggleClass('activo', modoDocRec === 'dni');
        $('#tabRucRec').toggleClass('activo', modoDocRec === 'ruc');
        resetModalDni();
        if (modoDocRec === 'ruc') {
            $('#inputDniRec').attr('placeholder', 'Ingresa 11 dígitos RUC...');
            $('#dniRecApiLabel').html('<i class="fas fa-search mr-1"></i>Consultar SUNAT');
            $('#dniRecHint').text('Ingresa los 11 dígitos del RUC');
        } else {
            $('#inputDniRec').attr('placeholder', 'Ingresa 8 dígitos DNI...');
            $('#dniRecApiLabel').html('<i class="fas fa-search mr-1"></i>Consultar RENIEC');
            $('#dniRecHint').text('Ingresa los 8 dígitos del DNI');
        }
        $('#inputDniRec').focus();
    });

    // Solo dígitos
    $('#inputDniRec').on('input', function () { this.value = this.value.replace(/[^0-9]/g, ''); });
    $('#inputDniRec').on('keypress', function (e) { if (e.which === 13) ejecutarBusquedaDniRec(); });
    $('#btnConsultarDniRec').on('click', ejecutarBusquedaDniRec);

    function ejecutarBusquedaDniRec() {
        const doc   = $.trim($('#inputDniRec').val());
        const esRuc = doc.length === 11;
        const esDni = doc.length === 8;

        if (!/^\d+$/.test(doc) || (!esDni && !esRuc)) {
            $('#dniRecEstado').html(
                '<div class="alert alert-warning py-2 mb-0" style="border-radius:8px;font-size:.85rem;">' +
                '<i class="fas fa-exclamation-triangle mr-2"></i>Ingresa un DNI (8 dígitos) o RUC (11 dígitos) válido.</div>'
            ).show();
            return;
        }

        const $btn     = $('#btnConsultarDniRec');
        const labelAPI = esRuc ? 'SUNAT' : 'RENIEC';
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Consultando...');
        $('#dniRecEstado').html(
            '<div class="text-center py-3"><i class="fas fa-spinner fa-spin fa-2x mb-2 d-block" style="color:#1e40af;"></i>' +
            '<small class="text-muted">Consultando ' + labelAPI + '...</small></div>'
        ).show();
        $('#dniRecResultado').hide();
        $('#dniRecSunatRow').remove();

        $.post('ajax_buscar_cliente.php', { documento: doc, guardar: 0 }, function (res) {
            $btn.prop('disabled', false).html('<i class="fas fa-search mr-1"></i>Buscar');
            $('#dniRecEstado').hide().empty();

            if (!res.success) {
                $('#dniRecEstado').html(
                    '<div class="alert alert-danger py-2 mb-0" style="border-radius:8px;font-size:.85rem;">' +
                    '<i class="fas fa-times-circle mr-2"></i>' + res.error + '</div>'
                ).show();
                return;
            }

            const d         = res.datos;
            const yaExiste  = res.ya_existe;
            const esEmpresa = (res.tipo === 'juridica');

            $('#dniRecNombre').html(
                (esEmpresa ? '<i class="fas fa-building mr-1 text-primary"></i>' : '<i class="fas fa-user mr-1 text-muted"></i>') +
                d.nombre_completo
            );
            $('#dniRecNumero').text((esEmpresa ? 'RUC: ' : 'DNI: ') + (d.documento || d.dni || d.ruc || ''));

            if (d.direccion) { $('#dniRecDir').text(d.direccion); $('#dniRecDirRow').show(); }
            else             { $('#dniRecDirRow').hide(); }

            if (esEmpresa && d.estado_sunat) {
                const activo = d.estado_sunat.toLowerCase().includes('activo');
                $('#dniRecDirRow').after(
                    '<div class="rec-resultado-dato" id="dniRecSunatRow">' +
                    '<i class="fas fa-circle mr-1" style="color:' + (activo ? '#16a34a' : '#dc2626') + ';font-size:.6rem;"></i>' +
                    'SUNAT: <strong>' + d.estado_sunat + '</strong></div>'
                );
            }

            if (yaExiste) {
                $('#dniRecBadge').html('<span style="background:#d4edda;color:#155724;padding:2px 10px;border-radius:20px;font-size:.72rem;font-weight:700;"><i class="fas fa-check-circle mr-1"></i>Ya registrado</span>');
                $('#dniRecOpciones').html(
                    '<button type="button" class="btn btn-block font-weight-bold btn-rec-primary" id="btnDniUsarRec" style="border-radius:8px;padding:10px;">' +
                    '<i class="fas fa-check mr-2"></i>Seleccionar este cliente</button>'
                );
                $('#btnDniUsarRec').on('click', function () {
                    seleccionarClienteRec(d.id_cliente, d.nombre_completo, esEmpresa, res.tipo_cliente || (esEmpresa ? 'empresa' : 'natural'));
                    Swal.fire({ icon: 'success', title: 'Cliente seleccionado', html: '<b>' + d.nombre_completo + '</b>', toast: true, position: 'top-end', timer: 2000, timerProgressBar: true, showConfirmButton: false, background: '#eafaf1', color: '#1a7a4a', iconColor: '#27ae60' });
                });
            } else {
                $('#dniRecBadge').html('<span style="background:#fff3cd;color:#856404;padding:2px 10px;border-radius:20px;font-size:.72rem;font-weight:700;"><i class="fas fa-user-plus mr-1"></i>' + (esEmpresa ? 'Nueva empresa' : 'Nuevo cliente') + '</span>');
                $('#dniRecOpciones').html(
                    '<div class="row">' +
                    '<div class="col-6 pr-1"><button type="button" class="btn btn-outline-primary btn-block font-weight-bold" id="btnDniUsarSinRec" style="border-radius:8px;padding:10px;font-size:.85rem;"><i class="fas fa-bolt mr-1"></i>Usar sin guardar</button></div>' +
                    '<div class="col-6 pl-1"><button type="button" class="btn btn-block font-weight-bold btn-rec-primary" id="btnDniGuardarRec" style="border-radius:8px;padding:10px;font-size:.85rem;"><i class="fas fa-save mr-1"></i>Guardar y usar</button></div>' +
                    '</div>'
                );
                $('#btnDniUsarSinRec').on('click', function () {
                    seleccionarClienteRec(0, d.nombre_completo, esEmpresa, res.tipo_cliente || (esEmpresa ? 'empresa' : 'natural'));
                    Swal.fire({ icon: 'success', title: 'Cliente seleccionado', html: '<b>' + d.nombre_completo + '</b>', toast: true, position: 'top-end', timer: 2000, timerProgressBar: true, showConfirmButton: false, background: '#eafaf1', color: '#1a7a4a', iconColor: '#27ae60' });
                });
                $('#btnDniGuardarRec').on('click', function () {
                    const $b = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Guardando...');
                    $.post('ajax_buscar_cliente.php', { documento: d.documento || d.dni || d.ruc, guardar: 1 }, function (res2) {
                        if (res2.success) {
                            const tipoGuardado = res2.tipo_cliente || (esEmpresa ? 'empresa' : 'natural');
                            if (res2.id_cliente) {
                                const icono = esEmpresa ? 'fas fa-building' : 'fas fa-user';
                                $('#listaClientesRec').prepend(
                                    '<div class="rec-item-selector" data-id="' + res2.id_cliente + '" data-nombre="' + d.nombre_completo + '" data-doc="' + (d.documento || d.dni || d.ruc) + '" data-tipo-persona="' + (esEmpresa ? 'juridica' : 'natural') + '" data-tipo-cliente="' + tipoGuardado + '">' +
                                    '<div class="d-flex align-items-center" style="gap:12px;">' +
                                    '<div class="rec-item-avatar ' + (esEmpresa ? 'rec-item-avatar-empresa' : '') + '"><i class="' + icono + '"></i></div>' +
                                    '<div><div class="rec-item-nombre">' + d.nombre_completo + '</div>' +
                                    '<div class="rec-item-sub"><i class="fas fa-id-card mr-1"></i>' + (esEmpresa ? 'RUC' : 'DNI') + ': ' + (d.documento || d.dni || d.ruc) + '</div></div></div></div>'
                                );
                                $('#contClientesRec').text(parseInt($('#contClientesRec').text()) + 1);
                            }
                            seleccionarClienteRec(res2.id_cliente || 0, d.nombre_completo, esEmpresa, tipoGuardado);
                            Swal.fire({ icon: 'success', title: 'Guardado', html: '<b>' + d.nombre_completo + '</b> fue registrado.', toast: true, position: 'top-end', timer: 3000, timerProgressBar: true, showConfirmButton: false, background: '#eafaf1', color: '#1a7a4a', iconColor: '#27ae60' });
                        } else {
                            $b.prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Guardar y usar');
                            Swal.fire({ icon: 'error', title: 'Error al guardar', text: res2.error, confirmButtonColor: '#2563eb' });
                        }
                    }, 'json').fail(function () {
                        $b.prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Guardar y usar');
                    });
                });
            }
            $('#dniRecResultado').show();

        }, 'json').fail(function () {
            $btn.prop('disabled', false).html('<i class="fas fa-search mr-1"></i>Buscar');
            $('#dniRecEstado').html(
                '<div class="alert alert-danger py-2 mb-0" style="border-radius:8px;font-size:.85rem;">' +
                '<i class="fas fa-wifi mr-2"></i>Error de conexión. Intenta nuevamente.</div>'
            ).show();
        });
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FORMULARIO NUEVA RECEPCIÓN
    // ══════════════════════════════════════════════════════════════════════════

    // Resumen equipo en tiempo real
    $('select[name="tipo_equipo"], input[name="marca"], input[name="modelo"]').on('change input', function () {
        const tipo   = $('select[name="tipo_equipo"]').val() || '';
        const marca  = $('input[name="marca"]').val() || '';
        const modelo = $('input[name="modelo"]').val() || '';
        $('#resumenEquipo').text([tipo, marca, modelo].filter(Boolean).join(' ') || '—');
    });

    // Limpiar modal al cerrar
    $('#modalNuevaRecepcion').on('hidden.bs.modal', function () {
        $('#formNuevaRecepcion')[0].reset();
        $('#hiddenIdCliente').val('');
        $('#campoClienteRec').removeClass('rec-seleccionado')
            .find('.rec-selected').text('').hide()
            .end().find('.rec-placeholder').show();
        $('#btnLimpiarClienteRec').hide();
        $('#previewFotos').empty();
        $('#resumenCliente').text('—');
        $('#resumenEquipo').text('—');
    });

    // Validación al enviar
    $('#formNuevaRecepcion').on('submit', function (e) {
        const cliente = $('#hiddenIdCliente').val();
        const marca   = $('input[name="marca"]').val().trim();
        const tipo    = $('select[name="tipo_equipo"]').val();
        if (!cliente) {
            e.preventDefault();
            Swal.fire({ icon: 'warning', title: 'Cliente requerido', text: 'Debes seleccionar o buscar un cliente antes de continuar.', confirmButtonColor: '#2563eb' });
            return;
        }
        if (!tipo || !marca) {
            e.preventDefault();
            Swal.fire({ icon: 'warning', title: 'Datos del equipo', text: 'El tipo de equipo y la marca son obligatorios.', confirmButtonColor: '#2563eb' });
        }
    });

    // ══════════════════════════════════════════════════════════════════════════
    // FOTOS
    // ══════════════════════════════════════════════════════════════════════════

    $('#inputFotos').on('change', function () {
        const preview = $('#previewFotos').empty();
        const files   = this.files;
        const max     = 5;
        for (let i = 0; i < Math.min(files.length, max); i++) {
            const reader = new FileReader();
            reader.onload = function (e) {
                preview.append('<div class="preview-foto-item"><img src="' + e.target.result + '" alt="preview"></div>');
            };
            reader.readAsDataURL(files[i]);
        }
        if (files.length > max) {
            Swal.fire({ icon: 'warning', title: 'Máximo 5 fotos', text: 'Solo se subirán las primeras 5 fotos.', confirmButtonColor: '#2563eb', timer: 2500, showConfirmButton: false });
        }
    });

    // Drag & drop
    const zona = document.getElementById('zonaFotos');
    if (zona) {
        zona.addEventListener('dragover',  function (e) { e.preventDefault(); zona.style.borderColor = '#2563eb'; zona.style.background = '#dbeafe'; });
        zona.addEventListener('dragleave', function ()  { zona.style.borderColor = '#93c5fd'; zona.style.background = '#eff6ff'; });
        zona.addEventListener('drop',      function (e) {
            e.preventDefault();
            zona.style.borderColor = '#93c5fd'; zona.style.background = '#eff6ff';
            document.getElementById('inputFotos').files = e.dataTransfer.files;
            $(document.getElementById('inputFotos')).trigger('change');
        });
    }

    // ══════════════════════════════════════════════════════════════════════════
    // VER DETALLE DE ORDEN
    // ══════════════════════════════════════════════════════════════════════════

    $(document).on('click', '.btn-ver-orden', function () {
        const d    = $(this).data();
        const $btn = $(this);  // referencia al botón para leer atributos directos

        $('#detOrdenNum').text('ORD-' + String(d.id).padStart(6, '0'));
        $('#detCliente').text(d.cliente   || '—');
        $('#detDoc').text(d.doc           || '—');
        $('#detTelefono').text(d.telefono || '—');
        $('#detTipo').text(d.tipo         || '—');
        $('#detMarca').text(d.marca       || '—');
        $('#detModelo').text(d.modelo     || '—');
        $('#detSerie').text(d.serie       || '—');
        $('#detAccesorios').text(d.accesorios   || '—');
        $('#detEstadoFisico').text(d['estado-fisico'] || '—');
        $('#detProblema').text(d.problema || '—');
        $('#detFechaRec').text(d.fechaRec || d['fecha-rec'] || '—');
        $('#detFechaEst').text(d.fechaEst || d['fecha-est'] || '—');
        $('#detTecnico').text(d.tecnico || '—');
        // Contraseña: mostrar con asteriscos si existe
        var clave = d.contrasena || '';
        if (clave && clave !== '—') {
            $('#detContrasena').html(
                '<span style="font-family:monospace;background:#fef9c3;padding:2px 8px;border-radius:4px;border:1px solid #fde047;">' +
                '<i class="fas fa-key mr-1" style="color:#854d0e;"></i>' + $('<span>').text(clave).html() + '</span>'
            );
        } else {
            $('#detContrasena').html('<span class="text-muted" style="font-style:italic;">No registrada — el técnico puede completarla</span>');
        }

        const pMap = { alta: 'badge-prioridad-alta', normal: 'badge-prioridad-normal', media: 'badge-prioridad-media', baja: 'badge-prioridad-baja' };
        $('#detPrioridad').html('<span class="badge-prioridad ' + (pMap[d.prioridad] || '') + '">' + (d.prioridad ? d.prioridad.charAt(0).toUpperCase() + d.prioridad.slice(1) : '—') + '</span>');

        const eLabel = (d.estado || '').replace('_', ' ');
        $('#detEstado').html('<span class="badge-estado badge-' + d.estado + '">' + eLabel.charAt(0).toUpperCase() + eLabel.slice(1) + '</span>');

        let fotos = [];
        try {
            // Con ENT_QUOTES en PHP, las comillas dobles llegan como &quot;
            // jQuery .data() las decodifica automáticamente, pero por seguridad usamos el atributo directo
            var fotosAttr = $btn.attr('data-fotos') || '[]';
            fotos = JSON.parse(fotosAttr);
        } catch (err) { fotos = []; }

        if (fotos.length > 0) {
            const cont = $('#detFotos').empty();
            fotos.forEach(function (ruta) {
                const url = '/sysinversioneschcomputer/' + ruta;
                cont.append('<div class="foto-existente" onclick="ampliarFoto(\'' + url + '\')"><img src="' + url + '" alt="foto equipo"></div>');
            });
            $('#seccionFotos').show();
        } else {
            $('#seccionFotos').hide();
        }

        // Actualizar links PDF en el footer del modal
        var urlPdf      = 'orden_trabajo_pdf.php?id_orden=' + d.id;
        var urlDescarga = 'orden_trabajo_pdf.php?id_orden=' + d.id + '&download=1';
        $('#btnImprimirPdfDetalle').attr('href', urlPdf);
        $('#btnDescargarPdfDetalle').attr('href', urlDescarga);

        $('#modalVerOrden').modal('show');
    });

    // ══════════════════════════════════════════════════════════════════════════
    // IMPRIMIR TICKET — abre PDF directamente
    // ══════════════════════════════════════════════════════════════════════════

    $(document).on('click', '.btn-imprimir-ticket', function () {
        var id = $(this).data('id');
        window.open('orden_trabajo_pdf.php?id_orden=' + id, '_blank');
    });

    // ══════════════════════════════════════════════════════════════════════════
    // CAMBIAR ESTADO
    // ══════════════════════════════════════════════════════════════════════════

    $(document).on('click', '.btn-cambiar-estado', function () {        $('#ceIdOrden').val($(this).data('id'));
        $('#ceOrdenNum').text($(this).data('orden'));
        $('#ceNuevoEstado').val($(this).data('estado'));
        $('#modalCambiarEstado').modal('show');
    });

    // ══════════════════════════════════════════════════════════════════════════
    // CANCELAR ORDEN
    // ══════════════════════════════════════════════════════════════════════════

    $(document).on('click', '.btn-cancelar-orden', function () {
        const id    = $(this).data('id');
        const orden = $(this).data('orden');
        Swal.fire({
            icon: 'warning',
            title: '¿Cancelar orden?',
            html: 'La orden <strong>' + orden + '</strong> será cancelada.<br>Esta acción quedará registrada en el historial.',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor:  '#6b7280',
            confirmButtonText:  'Sí, cancelar',
            cancelButtonText:   'No, volver'
        }).then(function (result) {
            if (result.isConfirmed) {
                $('#cancelIdOrden').val(id);
                $('#formCancelarOrden').submit();
            }
        });
    });

    // ── Fix modales anidados Bootstrap 4 ─────────────────────────────────────
    $(document).on('hidden.bs.modal', '.modal', function () {
        if ($('.modal.show').length) $('body').addClass('modal-open');
    });

});

// ── Visor de fotos con navegación ────────────────────────────────────────────
var _fotosVisor  = [];   // array de URLs de la orden actual
var _fotoActual  = 0;    // índice actual

function ampliarFoto(url) {
    // Recopilar todas las fotos del modal de detalle
    var urls = [];
    $('#detFotos .foto-existente img').each(function () {
        urls.push($(this).attr('src'));
    });
    if (urls.length === 0) urls = [url];
    _fotosVisor = urls;
    _fotoActual = urls.indexOf(url);
    if (_fotoActual < 0) _fotoActual = 0;
    _renderVisor();
    $('#modalFotoAmpliada').modal('show');
}

function navegarFoto(dir) {
    _fotoActual = (_fotoActual + dir + _fotosVisor.length) % _fotosVisor.length;
    _renderVisor();
}

function _renderVisor() {
    var url = _fotosVisor[_fotoActual];
    $('#fotoAmpliadaSrc').attr('src', url);
    $('#fotoContador').text((_fotoActual + 1) + ' / ' + _fotosVisor.length);

    // Flechas: ocultar si solo hay 1 foto
    if (_fotosVisor.length <= 1) {
        $('#fotoAnterior, #fotoSiguiente').hide();
    } else {
        $('#fotoAnterior, #fotoSiguiente').show();
    }

    // Miniaturas
    var $min = $('#fotoMiniaturas').empty();
    if (_fotosVisor.length > 1) {
        _fotosVisor.forEach(function (u, i) {
            var activo = i === _fotoActual
                ? 'border:2px solid #60a5fa;opacity:1;'
                : 'border:2px solid transparent;opacity:.55;';
            $min.append(
                '<img src="' + u + '" onclick="navegarFoto(' + (i - _fotoActual) + ')" ' +
                'style="width:56px;height:56px;object-fit:cover;border-radius:6px;cursor:pointer;' + activo + 'transition:all .15s;">'
            );
        });
        $min.show();
    } else {
        $min.hide();
    }
}

// Navegación con teclado
$(document).on('keydown', function (e) {
    if ($('#modalFotoAmpliada').hasClass('show')) {
        if (e.key === 'ArrowLeft')  navegarFoto(-1);
        if (e.key === 'ArrowRight') navegarFoto(1);
        if (e.key === 'Escape')     $('#modalFotoAmpliada').modal('hide');
    }
});
