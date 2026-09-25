const CTRL_TURNOS = './modules/Turnos/controllers/turnosController.php';

// Estos textos vienen de las PCs de planta y de los logs de Delvis: siempre escapados.
const esc = s => $('<div>').text(s == null ? '' : String(s)).html();

const BADGE_TIPO = {
    falla:         'bg-danger',
    falla_ok:      'bg-success',
    ajuste:        'bg-primary',
    calibracion:   'bg-info text-dark',
    crash:         'bg-dark',
    mantenimiento: 'bg-warning text-dark',
    aplicacion:    'bg-secondary'
};

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
                    <strong>${esc(a.estacion)}</strong> — ${esc(a.nombre)}
                </span>
            `).join(''));
        }, 'json');
    }
    cargarActivos();
    setInterval(cargarActivos, 30000);

    const contador = (v, clase) => Number(v) > 0
        ? `<span class="badge ${clase}">${Number(v)}</span>`
        : '<span class="text-muted small">—</span>';

    // ── DataTable de historial ──────────────────────────────
    const tabla = $('#tblTurnos').DataTable({
        ajax: {
            url: CTRL_TURNOS,
            type: 'POST',
            data: { accion: 'listar' },
            dataSrc: function (resp) { return resp.ok ? resp.data : []; }
        },
        columns: [
            { data: 'estacion', render: $.fn.dataTable.render.text() },
            { data: 'nombre',   render: $.fn.dataTable.render.text() },
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
            },
            { data: 'n_fallas',   className: 'text-center', render: v => contador(v, 'bg-danger') },
            { data: 'n_ajustes',  className: 'text-center', render: v => contador(v, 'bg-primary') },
            { data: 'n_crashes',  className: 'text-center', render: v => contador(v, 'bg-dark') },
            {
                data: null,
                className: 'text-center',
                orderable: false,
                render: (d, t, r) => `
                    <div class="dropdown">
                        <button class="btn btn-sm btn-primary dropdown-toggle py-1"
                                type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li>
                                <button class="dropdown-item btn-detalle" type="button"
                                        data-id="${Number(r.id_sesion)}">
                                    <i class="bi bi-list-ul me-2 text-secondary"></i>Ver detalle
                                </button>
                            </li>
                        </ul>
                    </div>`
            }
        ],
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
        order: [[2, 'desc']],
        pageLength: 10
    });

    // ── Detalle del turno ───────────────────────────────────
    $('#tblTurnos').on('click', '.btn-detalle', function () {
        const fila = tabla.row($(this).closest('tr')).data();
        $('#eventosResumen').text(`${fila.estacion} · ${fila.nombre} · ${fila.inicio}`);
        $('#eventosCuerpo').empty();
        $('#eventosVacio').addClass('d-none');

        $.post(CTRL_TURNOS, { accion: 'eventos', id_sesion: fila.id_sesion }, function (r) {
            if (!r.ok) {
                Swal.fire({ icon: 'error', title: 'Error', text: r.mensaje, confirmButtonColor: '#156b45' });
                return;
            }
            if (!r.data.length) {
                $('#eventosVacio').removeClass('d-none');
            } else {
                $('#eventosCuerpo').html(r.data.map(e => {
                    const cambio = (e.valor_anterior !== null || e.valor_nuevo !== null)
                        ? `<span class="text-muted">${esc(e.valor_anterior ?? '—')}</span> <i class="bi bi-arrow-right mx-1"></i> <strong>${esc(e.valor_nuevo ?? '—')}</strong>`
                        : '';
                    return `<tr>
                        <td class="small text-nowrap">${esc(e.fecha)}</td>
                        <td><span class="badge ${BADGE_TIPO[e.tipo] || 'bg-secondary'}">${esc(e.etiqueta)}</span></td>
                        <td>${esc(e.descripcion)}</td>
                        <td class="small">${cambio}</td>
                    </tr>`;
                }).join(''));
            }
            const el = document.getElementById('modalEventos');
            (bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el)).show();
        }, 'json');
    });
});
