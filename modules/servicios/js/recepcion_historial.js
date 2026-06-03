/**
 * recepcion_historial.js — Modal detalle de orden en historial
 * SysInversiones CH Computer 2026
 */
$(function () {

    // ── Configuración de estados y prioridades ───────────────────────────────
    var ESTADOS = {
        recibido:    { label:'Recibido',          bg:'#dbeafe', color:'#1e40af', dot:'#3b82f6' },
        diagnostico: { label:'En Diagnóstico',    bg:'#fef3c7', color:'#92400e', dot:'#f59e0b' },
        en_proceso:  { label:'En Proceso',         bg:'#ede9fe', color:'#5b21b6', dot:'#8b5cf6' },
        listo:       { label:'Listo p/ Entrega',   bg:'#d1fae5', color:'#065f46', dot:'#10b981' },
        entregado:   { label:'Entregado',           bg:'#dcfce7', color:'#166534', dot:'#22c55e' },
        cancelado:   { label:'Cancelado',           bg:'#fee2e2', color:'#991b1b', dot:'#ef4444' }
    };
    var PRIORIDADES = {
        alta:   { label:'Alta',   bg:'#fee2e2', color:'#991b1b' },
        normal: { label:'Normal', bg:'#fef3c7', color:'#92400e' },
        media:  { label:'Media',  bg:'#fef3c7', color:'#92400e' },
        baja:   { label:'Baja',   bg:'#d1fae5', color:'#065f46' }
    };

    // ── Abrir modal ──────────────────────────────────────────────────────────
    $(document).on('click', '.btn-ver-historial', function () {
        var idOrden  = $(this).data('id');
        var numOrden = padOrden(idOrden);

        $('#tituloDetalleHist').text(numOrden);
        $('#mdhSubtitulo').text('Cargando...');
        $('#mdhBtnVerCompleto').attr('href', 'orden_trabajo.php?id=' + idOrden);
        $('#mdhCuerpo').html(spinnerHtml());
        $('#modalDetalleHistorial').modal('show');

        $.getJSON('ajax_detalle_historial.php', { id: idOrden })
            .done(function (res) {
                if (!res.ok) { $('#mdhCuerpo').html(errorHtml(res.msg)); return; }
                renderModal(res);
            })
            .fail(function () { $('#mdhCuerpo').html(errorHtml('Error de conexión.')); });
    });

    // ── Render principal ─────────────────────────────────────────────────────
    function renderModal(res) {
        var o   = res.orden;
        var num = padOrden(o.id_orden);
        var ec  = ESTADOS[o.estado]       || { label: o.estado,    bg:'#f3f4f6', color:'#374151', dot:'#9ca3af' };
        var pc  = PRIORIDADES[o.prioridad]|| { label: o.prioridad, bg:'#f3f4f6', color:'#374151' };

        $('#tituloDetalleHist').text('Orden ' + num);
        $('#mdhSubtitulo').text(
            o.cliente_nombre + ' — ' + o.equipo_tipo + ' ' + o.marca +
            (o.modelo ? ' ' + o.modelo : '')
        );

        var html = '';

        // ── 1. Barra de estado ───────────────────────────────────────────────
        html += sec('', '', buildBarraEstado(o, num, ec, pc, res.costo_total, res), {
            bg: 'linear-gradient(135deg,#f0f7ff,#e8f4fd)',
            border: '#bee3f8',
            pad: '16px 24px 14px'
        });

        // ── 2. Cliente | Equipo ──────────────────────────────────────────────
        html += '<div style="display:grid;grid-template-columns:1fr 1fr;border-bottom:1px solid #e9ecef;">';
        html += tarjeta('fas fa-user', 'Cliente', buildCliente(o), '#1a5276', 'border-right:1px solid #e9ecef;');
        html += tarjeta('fas fa-laptop', 'Equipo', buildEquipo(o), '#1a5276');
        html += '</div>';

        // ── 3. Problema reportado ────────────────────────────────────────────
        html += sec('fas fa-exclamation-triangle', 'Problema Reportado',
            '<p style="margin:0;font-size:.88rem;color:#374151;line-height:1.6;">' + esc(o.problema_reportado || '—') + '</p>',
            { bg:'#fffbf0', border:'#fde68a', labelColor:'#92400e' }
        );

        // ── 4. Diagnóstico + Solución ────────────────────────────────────────
        if (o.diagnostico || o.solucion) {
            html += '<div style="display:grid;grid-template-columns:1fr 1fr;border-bottom:1px solid #e9ecef;">';
            if (o.diagnostico) {
                html += tarjeta('fas fa-search', 'Diagnóstico',
                    '<p style="margin:0;font-size:.87rem;color:#374151;line-height:1.6;white-space:pre-wrap;">' + esc(o.diagnostico) + '</p>',
                    '#5b21b6', 'border-right:1px solid #e9ecef;background:#faf5ff;'
                );
            } else {
                html += '<div></div>';
            }
            if (o.solucion) {
                html += tarjeta('fas fa-tools', 'Solución Aplicada',
                    '<p style="margin:0;font-size:.87rem;color:#374151;line-height:1.6;white-space:pre-wrap;">' + esc(o.solucion) + '</p>',
                    '#065f46', 'background:#f0fdf4;'
                );
            }
            html += '</div>';
        }

        // ── 5. Componentes / Repuestos ───────────────────────────────────────
        if (o.descripcion_componentes) {
            html += sec('fas fa-microchip', 'Componentes / Repuestos',
                '<p style="margin:0;font-size:.87rem;color:#374151;line-height:1.6;white-space:pre-wrap;">' + esc(o.descripcion_componentes) + '</p>',
                { bg:'#f0fdf4', border:'#bbf7d0', labelColor:'#065f46' }
            );
        }

        // ── 6. Servicios realizados ──────────────────────────────────────────
        if (res.servicios && res.servicios.length > 0) {
            html += sec('fas fa-clipboard-list', 'Servicios Realizados',
                buildTablaServicios(res.servicios),
                { labelColor:'#1a5276' }
            );
        }

        // ── 6b. Cotizaciones de repuestos ─────────────────────────────────────
        if (res.cotizaciones && res.cotizaciones.length > 0) {
            html += sec('fas fa-cogs', 'Cotizaciones de Repuestos',
                buildTablaCotizaciones(res.cotizaciones, res.total_cotizaciones),
                { labelColor:'#92400e', bg:'#fffbf0', border:'#fde68a' }
            );
        }

        // ── 7. Historial de estados ──────────────────────────────────────────
        if (res.historial && res.historial.length > 0) {
            html += sec('fas fa-history', 'Historial de Estados',
                buildTimeline(res.historial),
                { labelColor:'#1a5276' }
            );
        }

        // ── 8. Personal ──────────────────────────────────────────────────────
        html += '<div style="display:grid;grid-template-columns:1fr 1fr;background:#f8fafc;">';
        html += personalBox('fas fa-user-check', 'Recepcionado por', o.recepcionado_por, 'border-right:1px solid #e9ecef;');
        html += personalBox('fas fa-user-cog',   'Técnico asignado', o.tecnico_nombre);
        html += '</div>';

        $('#mdhCuerpo').html(html);
    }

    // ── Constructores de secciones ───────────────────────────────────────────

    function buildBarraEstado(o, num, ec, pc, costoTotal, res) {
        var h = '';
        h += '<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">';

        // Izquierda: número + badges
        h += '<div>';
        h += '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px;">';
        h += '<span style="font-size:1.05rem;font-weight:800;color:#1a5276;font-family:monospace;letter-spacing:.5px;">' + num + '</span>';
        h += badge(ec.label, ec.bg, ec.color);
        h += badge('Prioridad: ' + ucfirst(o.prioridad), pc.bg, pc.color);
        h += '</div>';
        h += '<div style="font-size:.8rem;color:#64748b;display:flex;gap:14px;flex-wrap:wrap;">';
        h += '<span><i class="fas fa-calendar-alt mr-1" style="color:#1a5276;"></i>Recepción: <strong>' + formatFecha(o.fecha_recepcion) + '</strong></span>';
        if (o.fecha_entrega_estimada)
            h += '<span><i class="fas fa-calendar-check mr-1" style="color:#059669;"></i>Entrega est.: <strong>' + formatFecha(o.fecha_entrega_estimada) + '</strong></span>';
        h += '</div>';
        h += '</div>';

        // Derecha: costo total
        if (costoTotal && parseFloat(costoTotal) > 0) {
            h += '<div style="margin-left:auto;text-align:right;padding-left:20px;">';
            h += '<div style="font-size:1.5rem;font-weight:800;color:#1a7a4a;line-height:1;">S/. ' + parseFloat(costoTotal).toFixed(2) + '</div>';
            h += '<div style="font-size:.72rem;color:#64748b;margin-top:2px;text-transform:uppercase;letter-spacing:.5px;">Costo total del servicio</div>';
            if (res.costo_servicios > 0 && res.total_cotizaciones > 0) {
                h += '<div style="font-size:.7rem;color:#94a3b8;margin-top:3px;">';
                h += 'Servicios: S/. ' + parseFloat(res.costo_servicios).toFixed(2);
                h += ' &nbsp;+&nbsp; Repuestos: S/. ' + parseFloat(res.total_cotizaciones).toFixed(2);
                h += '</div>';
            }
            h += '</div>';
        }

        h += '</div>';
        return h;
    }

    function buildCliente(o) {
        var h = '';
        h += infoRow('fas fa-user-circle', o.cliente_nombre, true);
        if (o.documento)     h += infoRow('fas fa-id-card',  o.documento);
        if (o.telefono)      h += infoRow('fas fa-phone',    o.telefono);
        if (o.cliente_email) h += infoRow('fas fa-envelope', o.cliente_email);
        return h;
    }

    function buildEquipo(o) {
        var h = '';
        h += infoRow('fas fa-laptop', o.equipo_tipo + ' ' + o.marca + (o.modelo ? ' ' + o.modelo : ''), true);
        if (o.numero_serie)  h += infoRow('fas fa-barcode',    'S/N: ' + o.numero_serie);
        if (o.estado_fisico) h += infoRow('fas fa-shield-alt', 'Estado físico: ' + o.estado_fisico);
        if (o.accesorios)    h += infoRow('fas fa-box-open',   'Accesorios: ' + o.accesorios);
        return h;
    }

    function buildTablaServicios(servicios) {
        var total = 0;
        var h = '<div class="table-responsive">';
        h += '<table class="table table-sm table-bordered mb-0" style="font-size:.84rem;">';
        h += '<thead style="background:#1a5276;color:#fff;">';
        h += '<tr><th style="width:55%;">Servicio</th><th class="text-center" style="width:10%;">Cant.</th>';
        h += '<th class="text-right" style="width:17.5%;">Precio</th><th class="text-right" style="width:17.5%;">Subtotal</th></tr>';
        h += '</thead><tbody>';
        servicios.forEach(function (s) {
            var sub = parseFloat(s.subtotal || 0);
            total += sub;
            h += '<tr>';
            h += '<td><strong>' + esc(s.nombre_servicio || s.descripcion || '—') + '</strong></td>';
            h += '<td class="text-center">' + (s.cantidad || 1) + '</td>';
            h += '<td class="text-right">S/. ' + parseFloat(s.precio || 0).toFixed(2) + '</td>';
            h += '<td class="text-right font-weight-bold">S/. ' + sub.toFixed(2) + '</td>';
            h += '</tr>';
        });
        h += '</tbody>';
        h += '<tfoot><tr style="background:#f0f7ff;">';
        h += '<td colspan="3" class="text-right font-weight-bold" style="color:#1a5276;font-size:.85rem;">TOTAL</td>';
        h += '<td class="text-right font-weight-bold" style="color:#1a7a4a;font-size:.95rem;">S/. ' + total.toFixed(2) + '</td>';
        h += '</tr></tfoot>';
        h += '</table></div>';
        return h;
    }

    function buildTablaCotizaciones(cotizaciones, totalCot) {
        var ESTADOS_COT = {
            cotizado:         { label: 'Cotizado',          bg: '#dbeafe', color: '#1e40af' },
            aprobado:         { label: 'Aprobado',          bg: '#d1fae5', color: '#065f46' },
            rechazado:        { label: 'Rechazado',         bg: '#fee2e2', color: '#991b1b' },
            comprado:         { label: 'Comprado',          bg: '#ede9fe', color: '#5b21b6' },
            pendiente_compra: { label: 'Pend. Compra',      bg: '#fef3c7', color: '#92400e' },
            completado:       { label: 'Completado',        bg: '#dcfce7', color: '#166534' }
        };

        var h = '<div class="table-responsive">';
        h += '<table class="table table-sm table-bordered mb-0" style="font-size:.84rem;">';
        h += '<thead style="background:#92400e;color:#fff;">';
        h += '<tr>';
        h += '<th style="width:38%;">Repuesto / Descripción</th>';
        h += '<th class="text-center" style="width:9%;">Cant.</th>';
        h += '<th class="text-right" style="width:14%;">P. Unit.</th>';
        h += '<th class="text-right" style="width:14%;">Subtotal</th>';
        h += '<th class="text-center" style="width:13%;">Estado</th>';
        h += '<th style="width:12%;">Fecha</th>';
        h += '</tr>';
        h += '</thead><tbody>';

        cotizaciones.forEach(function (c) {
            var sub    = parseFloat(c.subtotal || 0);
            var ec     = ESTADOS_COT[c.estado] || { label: c.estado || '—', bg: '#f3f4f6', color: '#374151' };
            var nombre = c.nombre_producto || c.descripcion || '—';
            var rechazado = c.estado === 'rechazado';

            h += '<tr' + (rechazado ? ' style="opacity:.55;"' : '') + '>';
            h += '<td>';
            h += '<strong>' + esc(nombre) + '</strong>';
            if (c.codigo_producto) h += '<br><small class="text-muted">' + esc(c.codigo_producto) + '</small>';
            if (c.nota)            h += '<br><small class="text-muted"><i class="fas fa-comment-alt mr-1"></i>' + esc(c.nota) + '</small>';
            h += '</td>';
            h += '<td class="text-center">' + (c.cantidad || 1) + '</td>';
            h += '<td class="text-right">S/. ' + parseFloat(c.precio_unitario || 0).toFixed(2) + '</td>';
            h += '<td class="text-right font-weight-bold' + (rechazado ? ' text-muted' : '') + '">S/. ' + sub.toFixed(2) + '</td>';
            h += '<td class="text-center"><span style="background:' + ec.bg + ';color:' + ec.color + ';font-size:.68rem;font-weight:700;padding:2px 8px;border-radius:20px;white-space:nowrap;">' + ec.label + '</span></td>';
            h += '<td style="font-size:.75rem;color:#64748b;">' + formatFecha(c.fecha_cotizacion) + '</td>';
            h += '</tr>';
        });

        h += '</tbody>';
        if (totalCot && parseFloat(totalCot) > 0) {
            h += '<tfoot><tr style="background:#fffbf0;">';
            h += '<td colspan="3" class="text-right font-weight-bold" style="color:#92400e;font-size:.85rem;">TOTAL REPUESTOS</td>';
            h += '<td class="text-right font-weight-bold" style="color:#92400e;font-size:.95rem;">S/. ' + parseFloat(totalCot).toFixed(2) + '</td>';
            h += '<td colspan="2"></td>';
            h += '</tr></tfoot>';
        }
        h += '</table></div>';
        return h;
    }

    function buildTimeline(historial) {
        var h = '<div style="position:relative;padding-left:24px;">';
        // Línea vertical
        h += '<div style="position:absolute;left:8px;top:8px;bottom:8px;width:2px;background:linear-gradient(to bottom,#cbd5e1,#e2e8f0);border-radius:2px;"></div>';

        historial.forEach(function (item, idx) {
            var ec  = ESTADOS[item.estado] || { label: item.estado, bg:'#f3f4f6', color:'#374151', dot:'#9ca3af' };
            var isLast = idx === historial.length - 1;
            h += '<div style="position:relative;padding-left:20px;margin-bottom:' + (isLast ? '0' : '16px') + ';">';
            // Punto del timeline
            h += '<div style="position:absolute;left:-8px;top:5px;width:16px;height:16px;border-radius:50%;';
            h += 'background:' + ec.dot + ';border:3px solid #fff;box-shadow:0 0 0 2px ' + ec.dot + ';"></div>';
            // Contenido
            h += '<div style="background:#fff;border:1px solid #e9ecef;border-radius:8px;padding:10px 14px;box-shadow:0 1px 3px rgba(0,0,0,.05);">';
            h += '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:' + (item.descripcion ? '6px' : '0') + ';">';
            h += badge(ec.label, ec.bg, ec.color);
            h += '<span style="font-size:.75rem;color:#94a3b8;"><i class="fas fa-clock mr-1"></i>' + formatFechaHora(item.fecha) + '</span>';
            if (item.usuario_nombre)
                h += '<span style="font-size:.75rem;color:#64748b;"><i class="fas fa-user mr-1"></i>' + esc(item.usuario_nombre) + '</span>';
            h += '</div>';
            if (item.descripcion)
                h += '<p style="margin:0;font-size:.82rem;color:#475569;line-height:1.5;">' + esc(item.descripcion) + '</p>';
            h += '</div>';
            h += '</div>';
        });

        h += '</div>';
        return h;
    }

    function personalBox(icon, label, value, extraStyle) {
        return '<div style="padding:14px 20px;' + (extraStyle || '') + '">' +
               '<div style="font-size:.68rem;font-weight:700;letter-spacing:1.2px;color:#94a3b8;text-transform:uppercase;margin-bottom:5px;">' +
               '<i class="' + icon + ' mr-1"></i>' + label + '</div>' +
               '<span style="font-size:.9rem;font-weight:600;color:#374151;">' + esc(value || '—') + '</span>' +
               '</div>';
    }

    // ── Componentes base ─────────────────────────────────────────────────────

    /** Sección con título y contenido */
    function sec(icon, title, content, opts) {
        opts = opts || {};
        var bg          = opts.bg          || '#fff';
        var borderColor = opts.border      || '#e9ecef';
        var labelColor  = opts.labelColor  || '#1a5276';
        var pad         = opts.pad         || '14px 24px';

        var h = '<div style="padding:' + pad + ';border-bottom:1px solid ' + borderColor + ';background:' + bg + ';">';
        if (title) {
            h += '<div style="font-size:.7rem;font-weight:700;letter-spacing:1.2px;color:' + labelColor + ';';
            h += 'text-transform:uppercase;margin-bottom:10px;display:flex;align-items:center;gap:6px;">';
            if (icon) h += '<i class="' + icon + '"></i>';
            h += title + '</div>';
        }
        h += content;
        h += '</div>';
        return h;
    }

    /** Tarjeta de dos columnas (cliente / equipo) */
    function tarjeta(icon, title, content, color, extraStyle) {
        return '<div style="padding:14px 20px;' + (extraStyle || '') + '">' +
               '<div style="font-size:.7rem;font-weight:700;letter-spacing:1.2px;color:' + color + ';' +
               'text-transform:uppercase;margin-bottom:10px;display:flex;align-items:center;gap:6px;">' +
               '<i class="' + icon + '"></i>' + title + '</div>' +
               content +
               '</div>';
    }

    /** Fila de información con ícono */
    function infoRow(icon, text, bold) {
        return '<div style="display:flex;align-items:flex-start;gap:8px;margin-bottom:6px;">' +
               '<i class="' + icon + '" style="color:#1a5276;margin-top:3px;width:14px;text-align:center;flex-shrink:0;font-size:.8rem;"></i>' +
               '<span style="font-size:.85rem;' + (bold ? 'font-weight:600;' : 'color:#475569;') + '">' + esc(text || '—') + '</span>' +
               '</div>';
    }

    /** Badge de estado/prioridad */
    function badge(label, bg, color) {
        return '<span style="background:' + bg + ';color:' + color + ';font-size:.72rem;font-weight:700;' +
               'padding:3px 10px;border-radius:20px;white-space:nowrap;">' + label + '</span>';
    }

    // ── Utilidades ───────────────────────────────────────────────────────────

    function spinnerHtml() {
        return '<div class="text-center py-5">' +
               '<i class="fas fa-spinner fa-spin fa-2x" style="color:#1a5276;"></i>' +
               '<p class="mt-3 text-muted" style="font-size:.88rem;">Cargando información...</p>' +
               '</div>';
    }

    function errorHtml(msg) {
        return '<div class="alert alert-danger m-3" style="border-radius:8px;">' +
               '<i class="fas fa-exclamation-circle mr-2"></i>' + esc(msg || 'Error desconocido.') + '</div>';
    }

    function padOrden(id) {
        return 'ORD-' + String(id).padStart(6, '0');
    }

    function esc(str) {
        return String(str || '')
            .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }

    function ucfirst(str) {
        if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    function formatFecha(str) {
        if (!str) return '—';
        var d = new Date(str.replace(' ', 'T'));
        if (isNaN(d)) return str;
        return d.toLocaleDateString('es-PE', { day:'2-digit', month:'2-digit', year:'numeric' });
    }

    function formatFechaHora(str) {
        if (!str) return '—';
        var d = new Date(str.replace(' ', 'T'));
        if (isNaN(d)) return str;
        return d.toLocaleDateString('es-PE', { day:'2-digit', month:'2-digit', year:'numeric' }) +
               ' ' + d.toLocaleTimeString('es-PE', { hour:'2-digit', minute:'2-digit' });
    }

});
