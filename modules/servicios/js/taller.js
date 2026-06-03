/**
 * taller.js — Interfaz del Técnico | SysInversiones CH Computer 2026
 * Solo maneja: DataTable de mis órdenes + confirmación SweetAlert para tomar orden.
 * El trabajo de la orden se gestiona en orden_trabajo.php (página dedicada).
 */

$(function () {

    // ── DataTable mis órdenes ─────────────────────────────────────────────────
    if ($('#tablaMisOrdenes').length) {
        $('#tablaMisOrdenes').DataTable({
            language: { url: 'https://cdn.datatables.net/plug-ins/1.10.25/i18n/Spanish.json' },
            order: [[5, 'asc']],
            pageLength: 15,
            responsive: true,
            columnDefs: [{ orderable: false, targets: -1 }]
        });
    }

    // ── TOMAR ORDEN — confirmación con SweetAlert ─────────────────────────────
    $(document).on('click', '.btn-tomar-swal', function () {
        var id       = $(this).data('id');
        var num      = $(this).data('num');
        var cliente  = $(this).data('cliente');
        var equipo   = $(this).data('equipo');
        var problema = $(this).data('problema');
        var prio     = $(this).data('prioridad');

        var coloresPrio = {
            alta:   { bg: '#fee2e2', color: '#991b1b', label: '🔴 Alta' },
            normal: { bg: '#fef9c3', color: '#854d0e', label: '🟡 Normal' },
            media:  { bg: '#fef9c3', color: '#854d0e', label: '🟡 Normal' },
            baja:   { bg: '#dcfce7', color: '#166534', label: '🟢 Baja' }
        };
        var cp = coloresPrio[prio] || coloresPrio['normal'];

        Swal.fire({
            title: '¿Tomar esta orden?',
            html:
                '<div style="text-align:left;font-size:.88rem;">' +
                '<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:12px 14px;margin-bottom:10px;">' +
                '<div style="font-family:monospace;font-weight:700;color:#1e40af;font-size:.95rem;margin-bottom:8px;">' + num + '</div>' +
                '<div style="margin-bottom:5px;"><i class="fas fa-user" style="color:#059669;width:16px;"></i> <strong>' + cliente + '</strong></div>' +
                '<div style="margin-bottom:5px;"><i class="fas fa-laptop" style="color:#059669;width:16px;"></i> ' + equipo + '</div>' +
                '<div style="margin-bottom:8px;"><i class="fas fa-exclamation-triangle" style="color:#f59e0b;width:16px;"></i> ' + (problema || '—') + '</div>' +
                '<span style="background:' + cp.bg + ';color:' + cp.color + ';padding:2px 10px;border-radius:20px;font-size:.75rem;font-weight:700;">' + cp.label + '</span>' +
                '</div>' +
                '<p style="color:#475569;margin:0;font-size:.82rem;">Al tomar esta orden quedará asignada a ti y pasará a estado <strong>Diagnóstico</strong>.</p>' +
                '</div>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#059669',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="fas fa-hand-pointer mr-1"></i> Sí, tomar orden',
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        }).then(function (result) {
            if (result.isConfirmed) {
                $('#formTomar_' + id).submit();
            }
        });
    });

});
