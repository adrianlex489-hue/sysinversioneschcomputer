/* ============================================================
   auditoria.js — Módulo Auditoría | SysInversiones CH Computer
   ============================================================ */

$(function () {

    // ── Configuración de módulos: ícono y clase CSS ──────────────────────────
    const MODULOS = {
        productos:   { icon: 'fas fa-laptop',          cls: 'badge-mod-productos'  },
        inventario:  { icon: 'fas fa-boxes',            cls: 'badge-mod-inventario' },
        ventas:      { icon: 'fas fa-shopping-cart',    cls: 'badge-mod-ventas'     },
        compras:     { icon: 'fas fa-truck-loading',    cls: 'badge-mod-compras'    },
        caja:        { icon: 'fas fa-cash-register',    cls: 'badge-mod-caja'       },
        usuarios:    { icon: 'fas fa-users-cog',        cls: 'badge-mod-usuarios'   },
        servicios:   { icon: 'fas fa-tools',            cls: 'badge-mod-servicios'  },
        empresa:     { icon: 'fas fa-building',         cls: 'badge-mod-empresa'    },
    };

    // ── Configuración de acciones: ícono y clase CSS ─────────────────────────
    const ACCIONES = {
        crear:    { icon: 'fas fa-plus-circle',    cls: 'badge-acc-crear'    },
        editar:   { icon: 'fas fa-edit',           cls: 'badge-acc-editar'   },
        eliminar: { icon: 'fas fa-trash-alt',      cls: 'badge-acc-eliminar' },
        ajuste:   { icon: 'fas fa-sliders-h',      cls: 'badge-acc-ajuste'   },
        anular:   { icon: 'fas fa-ban',            cls: 'badge-acc-anular'   },
        apertura: { icon: 'fas fa-lock-open',      cls: 'badge-acc-apertura' },
        cierre:   { icon: 'fas fa-lock',           cls: 'badge-acc-cierre'   },
        login:    { icon: 'fas fa-sign-in-alt',    cls: 'badge-acc-login'    },
        permisos: { icon: 'fas fa-shield-alt',     cls: 'badge-acc-editar'   },
        cambio_rol:{ icon: 'fas fa-user-tag',      cls: 'badge-acc-editar'   },
    };

    // ── Helpers ──────────────────────────────────────────────────────────────
    function badgeModulo(modulo) {
        const m = MODULOS[modulo] || { icon: 'fas fa-circle', cls: 'badge-mod-default' };
        const label = modulo.charAt(0).toUpperCase() + modulo.slice(1);
        return `<span class="badge-modulo ${m.cls}"><i class="${m.icon}"></i>${label}</span>`;
    }

    function badgeAccion(accion) {
        const a = ACCIONES[accion] || { icon: 'fas fa-circle', cls: 'badge-acc-default' };
        const label = accion.replace(/_/g, ' ');
        const labelCap = label.charAt(0).toUpperCase() + label.slice(1);
        return `<span class="badge-accion ${a.cls}"><i class="${a.icon}"></i>${labelCap}</span>`;
    }

    function formatFecha(fecha) {
        if (!fecha) return '<span class="audit-val-null">—</span>';
        const d = new Date(fecha.replace(' ', 'T'));
        const fecha_str = d.toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric' });
        const hora_str  = d.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        return `<div class="audit-fecha"><strong>${fecha_str}</strong>${hora_str}</div>`;
    }

    function formatValor(val) {
        if (val === null || val === undefined || val === '') return '<span class="audit-val-null">—</span>';
        return String(val).length > 30 ? String(val).substring(0, 30) + '…' : String(val);
    }

    // ── Estado de paginación y filtros ───────────────────────────────────────
    let paginaActual = 1;
    const POR_PAGINA = 25;
    let totalRegistros = 0;

    // ══════════════════════════════════════════════════════════
    // PICKER MODALS — Módulo, Acción y Usuario
    // ══════════════════════════════════════════════════════════

    // ── Picker de Módulo ─────────────────────────────────────────────────────
    $('#btn-picker-modulo').on('click', function () {
        $('#modalPickerModulo').modal('show');
    });

    $(document).on('click', '#modulo-options .audit-picker-option', function () {
        const val   = $(this).data('value');
        const label = $(this).data('label');

        $('#modulo-options .audit-picker-option').removeClass('active');
        $(this).addClass('active');

        $('#filtro_modulo').val(val);
        $('#txt-modulo').text(label);

        if (val) {
            $('#btn-picker-modulo').addClass('has-value');
        } else {
            $('#btn-picker-modulo').removeClass('has-value');
        }

        $('#modalPickerModulo').modal('hide');
    });

    // ── Picker de Acción ─────────────────────────────────────────────────────
    $('#btn-picker-accion').on('click', function () {
        $('#modalPickerAccion').modal('show');
    });

    $(document).on('click', '#accion-options .audit-picker-option', function () {
        const val   = $(this).data('value');
        const label = $(this).data('label');

        // Marcar activo
        $('#accion-options .audit-picker-option').removeClass('active');
        $(this).addClass('active');

        // Actualizar hidden input y botón
        $('#filtro_accion').val(val);
        $('#txt-accion').text(label);

        if (val) {
            $('#btn-picker-accion').addClass('has-value');
        } else {
            $('#btn-picker-accion').removeClass('has-value');
        }

        $('#modalPickerAccion').modal('hide');
    });

    // ── Picker de Usuario ────────────────────────────────────────────────────
    $('#btn-picker-usuario').on('click', function () {
        $('#usuario-search').val('');
        filtrarUsuariosModal('');
        $('#modalPickerUsuario').modal('show');
        setTimeout(function () { $('#usuario-search').focus(); }, 400);
    });

    // Búsqueda en tiempo real dentro del modal
    $('#usuario-search').on('input', function () {
        filtrarUsuariosModal($(this).val().toLowerCase().trim());
    });

    function filtrarUsuariosModal(q) {
        let visible = 0;
        $('#usuario-options .audit-picker-option').each(function () {
            const isAll = $(this).hasClass('audit-picker-option--all');
            if (isAll) { $(this).show(); return; }
            const search = ($(this).data('search') || '').toLowerCase();
            const label  = ($(this).data('label') || '').toLowerCase();
            const match  = !q || search.includes(q) || label.includes(q);
            $(this).toggle(match);
            if (match) visible++;
        });
        $('#usuario-empty').toggle(visible === 0 && q !== '');
    }

    $(document).on('click', '#usuario-options .audit-picker-option', function () {
        const val   = $(this).data('value');
        const label = $(this).data('label');

        // Marcar activo
        $('#usuario-options .audit-picker-option').removeClass('active');
        $(this).addClass('active');

        // Actualizar hidden input y botón
        $('#filtro_usuario').val(val);
        $('#txt-usuario').text(label);

        if (val) {
            $('#btn-picker-usuario').addClass('has-value');
        } else {
            $('#btn-picker-usuario').removeClass('has-value');
        }

        $('#modalPickerUsuario').modal('hide');
    });

    // ── Limpiar pickers al limpiar filtros ───────────────────────────────────
    // (se integra con el btn-limpiar existente, ver abajo)

    // ── Leer filtros del formulario ──────────────────────────────────────────
    function getFiltros() {
        return {
            modulo:     $('#filtro_modulo').val()     || '',
            accion:     $('#filtro_accion').val()     || '',
            usuario:    $('#filtro_usuario').val()    || '',
            fecha_desde:$('#filtro_fecha_desde').val()|| '',
            fecha_hasta:$('#filtro_fecha_hasta').val()|| '',
            buscar:     $('#filtro_buscar').val()     || '',
            pagina:     paginaActual,
            por_pagina: POR_PAGINA,
        };
    }

    // ── Cargar registros vía AJAX ─────────────────────────────────────────────
    function cargarAuditoria() {
        const filtros = getFiltros();
        $('#audit-tbody').html(`
            <tr>
                <td colspan="8" class="text-center py-5 text-muted">
                    <i class="fas fa-spinner fa-spin fa-2x d-block mb-2" style="opacity:.3;"></i>
                    Cargando registros...
                </td>
            </tr>
        `);
        $('#audit-pagination').hide();

        $.get('ajax_auditoria.php', filtros)
            .done(function (res) {
                if (!res.ok) {
                    mostrarError(res.msg || 'Error al cargar datos.');
                    return;
                }
                totalRegistros = res.total;
                renderTabla(res.registros);
                renderPaginacion(res.total, res.pagina, res.por_pagina);
                $('#audit-total-badge').text(res.total.toLocaleString('es-PE') + ' registros');
            })
            .fail(function () {
                mostrarError('Error de conexión con el servidor.');
            });
    }

    function mostrarError(msg) {
        $('#audit-tbody').html(`
            <tr>
                <td colspan="8" class="text-center py-5 text-danger">
                    <i class="fas fa-exclamation-triangle fa-2x d-block mb-2" style="opacity:.5;"></i>
                    ${msg}
                </td>
            </tr>
        `);
    }

    // ── Renderizar filas de la tabla ─────────────────────────────────────────
    function renderTabla(registros) {
        if (!registros || registros.length === 0) {
            $('#audit-tbody').html(`
                <tr>
                    <td colspan="8">
                        <div class="audit-empty">
                            <i class="fas fa-shield-alt"></i>
                            <p>Sin registros de auditoría</p>
                            <small>No se encontraron eventos con los filtros aplicados</small>
                        </div>
                    </td>
                </tr>
            `);
            return;
        }

        let html = '';
        registros.forEach(function (r) {
            // Valores antes/después
            let valHtml = '<span class="audit-val-null">—</span>';
            if (r.valor_antes !== null || r.valor_nuevo !== null) {
                const antes   = r.valor_antes  !== null ? `<span class="audit-val-antes" title="${escHtml(r.valor_antes)}">${escHtml(formatValor(r.valor_antes))}</span>` : '<span class="audit-val-null">—</span>';
                const despues = r.valor_nuevo  !== null ? `<span class="audit-val-despues" title="${escHtml(r.valor_nuevo)}">${escHtml(formatValor(r.valor_nuevo))}</span>` : '<span class="audit-val-null">—</span>';
                valHtml = `<div class="audit-val-wrap">${antes}<i class="fas fa-arrow-right audit-val-arrow"></i>${despues}</div>`;
            }

            // Campo
            const campo = r.campo
                ? `<code style="font-size:.72rem;background:#f1f5f9;color:#334155;padding:1px 5px;border-radius:3px;">${escHtml(r.campo)}</code>`
                : '<span class="audit-val-null">—</span>';

            html += `
            <tr>
                <td>${formatFecha(r.fecha)}</td>
                <td><span class="audit-user-chip"><i class="fas fa-user"></i>${escHtml(r.nombre_usuario || '—')}</span></td>
                <td>${badgeModulo(r.modulo)}</td>
                <td>${badgeAccion(r.accion)}</td>
                <td>${campo}</td>
                <td>${valHtml}</td>
                <td>
                    <div class="audit-desc" title="${escHtml(r.descripcion || '')}">${escHtml(r.descripcion || '—')}</div>
                    ${r.ip ? `<div class="mt-1"><span class="audit-ip">${escHtml(r.ip)}</span></div>` : ''}
                </td>
                <td class="text-center">
                    <button class="btn-audit-ver btn-ver-detalle" data-id="${r.id}" title="Ver detalle completo">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            </tr>`;
        });

        $('#audit-tbody').html(html);
    }

    // ── Renderizar paginación ────────────────────────────────────────────────
    function renderPaginacion(total, pagina, porPagina) {
        const totalPags = Math.ceil(total / porPagina);
        const desde = total === 0 ? 0 : ((pagina - 1) * porPagina) + 1;
        const hasta = Math.min(pagina * porPagina, total);

        $('#audit-pag-info').text(`Mostrando ${desde.toLocaleString('es-PE')} – ${hasta.toLocaleString('es-PE')} de ${total.toLocaleString('es-PE')} registros`);

        let btns = '';
        btns += `<button class="audit-page-btn" id="btn-pag-prev" ${pagina <= 1 ? 'disabled' : ''}><i class="fas fa-chevron-left"></i></button>`;

        // Páginas visibles
        let inicio = Math.max(1, pagina - 2);
        let fin    = Math.min(totalPags, pagina + 2);
        if (inicio > 1) btns += `<button class="audit-page-btn" data-pag="1">1</button>${inicio > 2 ? '<span style="padding:0 4px;color:#94a3b8;">…</span>' : ''}`;
        for (let p = inicio; p <= fin; p++) {
            btns += `<button class="audit-page-btn ${p === pagina ? 'active' : ''}" data-pag="${p}">${p}</button>`;
        }
        if (fin < totalPags) btns += `${fin < totalPags - 1 ? '<span style="padding:0 4px;color:#94a3b8;">…</span>' : ''}<button class="audit-page-btn" data-pag="${totalPags}">${totalPags}</button>`;
        btns += `<button class="audit-page-btn" id="btn-pag-next" ${pagina >= totalPags ? 'disabled' : ''}><i class="fas fa-chevron-right"></i></button>`;

        $('#audit-pagination-btns').html(btns);
        $('#audit-pagination').show();
    }

    // ── Escape HTML ──────────────────────────────────────────────────────────
    function escHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // ── Eventos de paginación ────────────────────────────────────────────────
    $(document).on('click', '.audit-page-btn[data-pag]', function () {
        paginaActual = parseInt($(this).data('pag'));
        cargarAuditoria();
        $('html, body').animate({ scrollTop: $('#audit-card').offset().top - 20 }, 300);
    });
    $(document).on('click', '#btn-pag-prev', function () {
        if (paginaActual > 1) { paginaActual--; cargarAuditoria(); }
    });
    $(document).on('click', '#btn-pag-next', function () {
        paginaActual++; cargarAuditoria();
    });

    // ── Filtrar ──────────────────────────────────────────────────────────────
    $('#btn-filtrar').on('click', function () {
        paginaActual = 1;
        cargarAuditoria();
    });

    // Filtrar con Enter en el campo buscar
    $('#filtro_buscar').on('keydown', function (e) {
        if (e.key === 'Enter') { paginaActual = 1; cargarAuditoria(); }
    });

    // ── Limpiar filtros ──────────────────────────────────────────────────────
    $('#btn-limpiar').on('click', function () {
        // Limpiar picker módulo
        $('#filtro_modulo').val('');
        $('#txt-modulo').text('Todos los módulos');
        $('#btn-picker-modulo').removeClass('has-value');
        $('#modulo-options .audit-picker-option').removeClass('active');
        $('#modulo-options .audit-picker-option--all').addClass('active');
        // Limpiar picker acción
        $('#filtro_accion').val('');
        $('#txt-accion').text('Todas las acciones');
        $('#btn-picker-accion').removeClass('has-value');
        $('#accion-options .audit-picker-option').removeClass('active');
        $('#accion-options .audit-picker-option--all').addClass('active');
        // Limpiar picker usuario
        $('#filtro_usuario').val('');
        $('#txt-usuario').text('Todos los usuarios');
        $('#btn-picker-usuario').removeClass('has-value');
        $('#usuario-options .audit-picker-option').removeClass('active');
        $('#usuario-options .audit-picker-option--all').addClass('active');

        $('#filtro_fecha_desde').val('');
        $('#filtro_fecha_hasta').val('');
        $('#filtro_buscar').val('');
        paginaActual = 1;
        cargarAuditoria();
    });

    // ── Ver detalle de un registro ───────────────────────────────────────────
    $(document).on('click', '.btn-ver-detalle', function () {
        const id = $(this).data('id');
        $('#modal-detalle-body').html(`
            <div class="text-center py-5">
                <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                <p class="mt-2 text-muted">Cargando detalle...</p>
            </div>
        `);
        $('#modalDetalleAudit').modal('show');

        $.get('ajax_auditoria.php', { accion: 'detalle', id: id })
            .done(function (res) {
                if (!res.ok) {
                    $('#modal-detalle-body').html(`<div class="alert alert-danger m-3">${res.msg}</div>`);
                    return;
                }
                renderDetalle(res.registro);
            })
            .fail(function () {
                $('#modal-detalle-body').html('<div class="alert alert-danger m-3">Error de conexión.</div>');
            });
    });

    // ── Renderizar modal de detalle ──────────────────────────────────────────
    function renderDetalle(r) {

        // ── Parsear JSON si aplica ────────────────────────────────────────────
        let camposAntes   = null;
        let camposDespues = null;
        try { if (r.valor_antes && r.valor_antes.startsWith('{'))  camposAntes   = JSON.parse(r.valor_antes);  } catch(e) {}
        try { if (r.valor_nuevo && r.valor_nuevo.startsWith('{'))  camposDespues = JSON.parse(r.valor_nuevo);  } catch(e) {}

        // ── Bloque de cambios ─────────────────────────────────────────────────
        let cambiosHtml = '';

        if (camposAntes || camposDespues) {
            const allKeys = new Set([
                ...Object.keys(camposAntes  || {}),
                ...Object.keys(camposDespues || {})
            ]);
            allKeys.forEach(function (key) {
                const antes   = camposAntes   ? (camposAntes[key]   !== undefined ? camposAntes[key]   : null) : null;
                const despues = camposDespues ? (camposDespues[key] !== undefined ? camposDespues[key] : null) : null;
                if (antes === despues) return;
                cambiosHtml += buildCambioRow(key, antes, despues);
            });
        } else if (r.valor_antes !== null || r.valor_nuevo !== null) {
            cambiosHtml = buildCambioRow(r.campo || 'Valor', r.valor_antes, r.valor_nuevo);
        }

        if (!cambiosHtml) {
            cambiosHtml = `<div class="adm-no-changes">
                <i class="fas fa-check-circle"></i>
                <span>Sin cambios de valores registrados</span>
            </div>`;
        }

        // ── Formatear fecha ───────────────────────────────────────────────────
        let fechaFmt = r.fecha || '—';
        try {
            const d = new Date(r.fecha.replace(' ', 'T'));
            fechaFmt = d.toLocaleDateString('es-PE', { weekday:'long', day:'2-digit', month:'long', year:'numeric' })
                     + ' · ' + d.toLocaleTimeString('es-PE', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
        } catch(e) {}

        // ── HTML completo ─────────────────────────────────────────────────────
        const html = `
        <div class="adm-detail-wrap">

            <!-- ── Tarjeta: Identificación ── -->
            <div class="adm-section">
                <div class="adm-section-head">
                    <span class="adm-section-icon" style="background:#dbeafe;color:#1e40af;"><i class="fas fa-fingerprint"></i></span>
                    <span class="adm-section-title">Identificación</span>
                    <span class="adm-event-id">#${escHtml(String(r.id))}</span>
                </div>
                <div class="adm-grid-2">
                    <div class="adm-field">
                        <span class="adm-field-label"><i class="fas fa-clock"></i>Fecha y hora</span>
                        <span class="adm-field-value adm-field-date">${escHtml(fechaFmt)}</span>
                    </div>
                    <div class="adm-field">
                        <span class="adm-field-label"><i class="fas fa-network-wired"></i>Dirección IP</span>
                        <span class="adm-field-value">${r.ip ? `<span class="adm-ip-badge">${escHtml(r.ip)}</span>` : '<span class="adm-null">—</span>'}</span>
                    </div>
                </div>
            </div>

            <!-- ── Tarjeta: Actor ── -->
            <div class="adm-section">
                <div class="adm-section-head">
                    <span class="adm-section-icon" style="background:#ede9fe;color:#5b21b6;"><i class="fas fa-user-shield"></i></span>
                    <span class="adm-section-title">Actor del Evento</span>
                </div>
                <div class="adm-user-card">
                    <div class="adm-user-avatar">${escHtml((r.nombre_usuario || 'U').charAt(0).toUpperCase())}</div>
                    <div class="adm-user-info">
                        <span class="adm-user-name">${escHtml(r.nombre_usuario || '—')}</span>
                        <span class="adm-user-id">ID de usuario: ${escHtml(String(r.id_usuario || '—'))}</span>
                    </div>
                </div>
            </div>

            <!-- ── Tarjeta: Evento ── -->
            <div class="adm-section">
                <div class="adm-section-head">
                    <span class="adm-section-icon" style="background:#fef3c7;color:#92400e;"><i class="fas fa-bolt"></i></span>
                    <span class="adm-section-title">Detalle del Evento</span>
                </div>
                <div class="adm-grid-2">
                    <div class="adm-field">
                        <span class="adm-field-label"><i class="fas fa-layer-group"></i>Módulo</span>
                        <span class="adm-field-value">${badgeModulo(r.modulo)}</span>
                    </div>
                    <div class="adm-field">
                        <span class="adm-field-label"><i class="fas fa-bolt"></i>Acción</span>
                        <span class="adm-field-value">${badgeAccion(r.accion)}</span>
                    </div>
                    ${r.tabla ? `
                    <div class="adm-field">
                        <span class="adm-field-label"><i class="fas fa-table"></i>Tabla afectada</span>
                        <span class="adm-field-value"><code class="adm-code">${escHtml(r.tabla)}</code></span>
                    </div>` : ''}
                    ${r.id_registro ? `
                    <div class="adm-field">
                        <span class="adm-field-label"><i class="fas fa-key"></i>ID Registro</span>
                        <span class="adm-field-value"><code class="adm-code">#${escHtml(String(r.id_registro))}</code></span>
                    </div>` : ''}
                </div>
                ${r.descripcion ? `
                <div class="adm-desc-box">
                    <i class="fas fa-comment-dots adm-desc-icon"></i>
                    <span>${escHtml(r.descripcion)}</span>
                </div>` : ''}
            </div>

            <!-- ── Tarjeta: Cambios ── -->
            <div class="adm-section adm-section--changes">
                <div class="adm-section-head">
                    <span class="adm-section-icon" style="background:#dcfce7;color:#166534;"><i class="fas fa-exchange-alt"></i></span>
                    <span class="adm-section-title">Cambios Registrados</span>
                </div>
                <div class="adm-changes-wrap">
                    ${cambiosHtml}
                </div>
            </div>

        </div>`;

        $('#modal-detalle-body').html(html);
    }

    function buildCambioRow(campo, antes, despues) {
        const antesHtml   = antes   !== null ? `<span class="adm-val-before">${escHtml(String(antes))}</span>`   : `<span class="adm-val-empty">vacío</span>`;
        const despuesHtml = despues !== null ? `<span class="adm-val-after">${escHtml(String(despues))}</span>`  : `<span class="adm-val-empty">vacío</span>`;
        return `
        <div class="adm-change-row">
            <div class="adm-change-field">
                <i class="fas fa-columns"></i>
                <span>${escHtml(String(campo)).toUpperCase()}</span>
            </div>
            <div class="adm-change-values">
                <div class="adm-change-before">
                    <span class="adm-change-label">Antes</span>
                    ${antesHtml}
                </div>
                <div class="adm-change-arrow"><i class="fas fa-long-arrow-alt-right"></i></div>
                <div class="adm-change-after">
                    <span class="adm-change-label">Después</span>
                    ${despuesHtml}
                </div>
            </div>
        </div>`;
    }

    // ── Exportar ─────────────────────────────────────────────────────────────
    function getExportParams(formato) {
        const filtros = getFiltros();
        filtros.exportar = formato;
        return $.param(filtros);
    }

    $('#btn-exportar-csv').on('click', function () {
        window.location.href = 'ajax_auditoria.php?' + getExportParams('csv');
    });

    $('#btn-exportar-excel').on('click', function () {
        window.location.href = 'ajax_auditoria.php?' + getExportParams('excel');
    });

    $('#btn-exportar-pdf').on('click', function () {
        window.open('auditoria_pdf.php?' + getExportParams('pdf'), '_blank');
    });

    // ── Actualizar stats en tiempo real ──────────────────────────────────────
    function cargarStats() {
        $.get('ajax_auditoria.php', { accion: 'stats' })
            .done(function (res) {
                if (!res.ok) return;
                $('#stat-hoy').text(res.hoy.toLocaleString('es-PE'));
                $('#stat-semana').text(res.semana.toLocaleString('es-PE'));
                $('#stat-total').text(res.total.toLocaleString('es-PE'));
                $('#stat-usuarios').text(res.usuarios_activos.toLocaleString('es-PE'));
            });
    }

    // ── Inicialización ───────────────────────────────────────────────────────
    // Fecha hasta = hoy por defecto
    const hoy = new Date().toISOString().split('T')[0];
    const hace30 = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
    $('#filtro_fecha_desde').val(hace30);
    $('#filtro_fecha_hasta').val(hoy);

    cargarStats();
    cargarAuditoria();

    // Refrescar stats cada 60 segundos
    setInterval(cargarStats, 60000);
});
