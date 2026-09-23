const CTRL_TURNOS = './modules/Turnos/controllers/turnosController.php';

$(document).ready(function () {
    if (!document.getElementById('tblTurnos')) return;

    // ── Estaciones activas ahora mismo ─────────────────────
    function cargarActivos() {
        $.post(CTRL_TURNOS, { accion: 'activos' }, function (r) {
            if (!r.ok) return;
            const cont = $('#estacionesActivas');
            if (!r.data.length) {
                cont.html('<span class="text-muted small">Ninguna estación tiene un turno abierto en este momento.</span>');
                return;
            }
            cont.html(r.data.map(a => `
                <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle py-2 px-3">
                    <i class="bi bi-circle-fill me-1" style="font-size:.5rem"></i>
                    <strong>${a.estacion}</strong> — ${a.nombre}
                </span>
            `).join(''));
        }, 'json');
    }
    cargarActivos();
    setInterval(cargarActivos, 30000);

    // ── DataTable de historial ──────────────────────────────
    $('#tblTurnos').DataTable({
        ajax: {
            url: CTRL_TURNOS,
            type: 'POST',
            data: { accion: 'listar' },
            dataSrc: function (resp) { return resp.ok ? resp.data : []; }
        },
        columns: [
            { data: 'estacion' },
            { data: 'nombre' },
            { data: 'inicio' },
            {
                data: 'fin',
                render: v => v || '<span class="badge bg-success">En curso</span>'
            },
            {
                data: 'minutos',
                className: 'text-center',
                render: v => {
                    if (v === null) return '<span class="text-muted small">—</span>';
                    const h = Math.floor(v / 60), m = v % 60;
                    return h > 0 ? `${h} h ${m} min` : `${m} min`;
                }
            }
        ],
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
        order: [[2, 'desc']],
        pageLength: 10
    });
});
