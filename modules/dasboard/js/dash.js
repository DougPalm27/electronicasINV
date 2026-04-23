const CTRL_DASH = './modules/dasboard/controllers/dashController.php';

$(document).ready(function () {
    if (!document.getElementById('chartMantenimientos')) return;

    cargarKpis();
    cargarChartMantenimientos();
    cargarChartTipos();
    cargarUltimosMantenimientos();
    cargarStockBajo();
});

// ── KPIs ───────────────────────────────────────────────────
function cargarKpis() {
    $.post(CTRL_DASH, { accion: 'kpis' }, function (resp) {
        if (!resp.ok) return;
        const d = resp.data;
        $('#kpi_repuestos').text(d.total_repuestos ?? 0);
        $('#kpi_maquinas').text(d.total_maquinas ?? 0);
        $('#kpi_mantenimientos').text(d.mantenimientos_mes ?? 0);

        const bajo = parseInt(d.stock_bajo ?? 0);
        $('#kpi_stock_bajo').html(
            bajo > 0
                ? `<span class="fw-bold" style="color:#f59e0b">${bajo}</span>`
                : `<span class="text-success">0</span>`
        );
    }, 'json');
}

// ── Barras: Mantenimientos por mes ─────────────────────────
function cargarChartMantenimientos() {
    $.post(CTRL_DASH, { accion: 'mantenimientosPorMes' }, function (resp) {
        if (!resp.ok || !resp.data) return;

        new ApexCharts(document.getElementById('chartMantenimientos'), {
            chart:  { type: 'bar', height: 280, toolbar: { show: false } },
            series: [{ name: 'Mantenimientos', data: resp.data.map(r => parseInt(r.total)) }],
            xaxis:  { categories: resp.data.map(r => r.mes_label) },
            colors: ['#4154f1'],
            plotOptions: { bar: { borderRadius: 4, columnWidth: '50%' } },
            dataLabels: { enabled: false },
            grid: { borderColor: '#f1f1f1' },
            yaxis: { labels: { formatter: v => Math.round(v) } }
        }).render();
    }, 'json');
}

// ── Donut: Repuestos por tipo ───────────────────────────────
function cargarChartTipos() {
    $.post(CTRL_DASH, { accion: 'repuestosPorTipo' }, function (resp) {
        if (!resp.ok || !resp.data || !resp.data.length) {
            $('#chartTipos').html('<p class="text-muted text-center pt-4">Sin datos</p>');
            return;
        }

        new ApexCharts(document.getElementById('chartTipos'), {
            chart:  { type: 'donut', height: 280 },
            series: resp.data.map(r => parseInt(r.total)),
            labels: resp.data.map(r => r.tipo),
            colors: ['#4154f1','#2eca6a','#ff771d','#0dcaf0','#6f42c1','#fd7e14'],
            legend: { position: 'bottom' },
            dataLabels: { enabled: true, formatter: val => Math.round(val) + '%' },
            plotOptions: { pie: { donut: { size: '60%' } } }
        }).render();
    }, 'json');
}

// ── Tabla: Últimos mantenimientos ──────────────────────────
function cargarUltimosMantenimientos() {
    $.post(CTRL_DASH, { accion: 'ultimosMantenimientos' }, function (resp) {
        if (!resp.ok) return;

        if (!resp.data || !resp.data.length) {
            $('#tbodyUltimos').html('<tr><td colspan="4" class="text-center text-muted">Sin registros</td></tr>');
            return;
        }

        const tipoBadge = { 'Preventivo': 'bg-success', 'Correctivo': 'bg-danger', 'Predictivo': 'bg-info' };

        $('#tbodyUltimos').html(resp.data.map(r => `
            <tr>
                <td>${r.maquina}</td>
                <td><span class="badge ${tipoBadge[r.tipo] || 'bg-secondary'}">${r.tipo}</span></td>
                <td>${r.tecnico}</td>
                <td class="text-muted">${r.fecha}</td>
            </tr>`).join(''));
    }, 'json');
}

// ── Tabla: Stock bajo ──────────────────────────────────────
function cargarStockBajo() {
    $.post(CTRL_DASH, { accion: 'stockBajo' }, function (resp) {
        if (!resp.ok) return;

        if (!resp.data || !resp.data.length) {
            $('#tbodyStockBajo').html(
                '<tr><td colspan="3" class="text-center text-success">✔ Todo el stock está OK</td></tr>'
            );
            return;
        }

        $('#tbodyStockBajo').html(resp.data.map(r => `
            <tr>
                <td>${r.nombre}</td>
                <td class="text-center">
                    <span class="badge ${r.stock === 0 ? 'bg-danger' : 'bg-warning text-dark'}">${r.stock}</span>
                </td>
                <td class="text-center text-muted">${r.stock_minimo}</td>
            </tr>`).join(''));
    }, 'json');
}
