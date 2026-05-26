const CTRL_PLAT = './modules/GPS/Parametrizacion/Plataformas/controllers/plataformasController.php';

$(document).ready(function () {
    if (!document.getElementById('tblPlataformas')) return;

    const tabla = $('#tblPlataformas').DataTable({
        ajax: { url: CTRL_PLAT, type: 'POST', data: { accion: 'listar' }, dataSrc: r => r.ok ? r.data : [] },
        columns: [
            { data: null, render: (d,t,r,m) => m.row + 1 },
            { data: 'nombre', render: v => `<strong>${v}</strong>` },
            { data: 'url_base', render: v => v
                ? `<a href="${esc(v)}" target="_blank" rel="noopener" class="text-truncate d-inline-block" style="max-width:200px" title="${esc(v)}"><i class="bi bi-link-45deg me-1"></i>${esc(v)}</a>`
                : '<span class="text-muted">—</span>' },
            { data: 'fecha_creacion', render: v => v ? v.substring(0,10) : '—' },
            { data: 'creado_por_nombre', defaultContent: '<span class="text-muted">—</span>' },
            { data: 'activo', className: 'text-center',
              render: v => v==1 ? '<span class="badge bg-success">Activo</span>'
                                : '<span class="badge bg-secondary">Inactivo</span>' },
            { data: null, className: 'text-center', orderable: false,
              render: (d,t,r) => `
                <div class="dropdown">
                    <button class="btn btn-sm btn-primary dropdown-toggle py-1"
                            type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li>
                            <button class="dropdown-item btn-editar-plat" type="button"
                                    data-id="${r.id_plataforma}" data-nombre="${esc(r.nombre)}"
                                    data-url="${esc(r.url_base||'')}">
                                <i class="bi bi-pencil me-2 text-warning"></i>Editar
                            </button>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <button class="dropdown-item ${r.activo==1?'text-danger':'text-success'} btn-toggle-plat"
                                    type="button" data-id="${r.id_plataforma}">
                                <i class="bi bi-${r.activo==1?'toggle-on':'toggle-off'} me-2"></i>
                                ${r.activo==1?'Desactivar':'Activar'}
                            </button>
                        </li>
                    </ul>
                </div>` }
        ],
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
        order: [[1,'asc']], pageLength: 10
    });

    $('#btnNuevaPlataforma').on('click', () => { reset('Nueva plataforma GPS'); abrir('#modalPlataforma'); });

    $('#tblPlataformas').on('click', '.btn-editar-plat', function () {
        reset('Editar plataforma GPS');
        $('#plat_id').val($(this).data('id'));
        $('#plat_nombre').val($(this).data('nombre'));
        $('#plat_url').val($(this).data('url'));
        abrir('#modalPlataforma');
    });

    $('#btnGuardarPlataforma').on('click', function () {
        const id = $('#plat_id').val(), nombre = $('#plat_nombre').val().trim();
        if (!nombre) { $('#plat_nombre').addClass('is-invalid'); return; }
        $('#plat_nombre').removeClass('is-invalid');
        $.post(CTRL_PLAT, { accion: id?'editar':'crear', id_plataforma: id,
            nombre, url_base: $('#plat_url').val().trim() }, r => {
            if (!r.ok) return Swal.fire({ icon:'error', title:'Error', text:r.mensaje });
            cerrar('#modalPlataforma'); tabla.ajax.reload();
            Swal.fire({ icon:'success', title:'Listo', text:r.mensaje, timer:1800, showConfirmButton:false });
        }, 'json');
    });

    $('#tblPlataformas').on('click', '.btn-toggle-plat', function () {
        const id = $(this).data('id');
        Swal.fire({ title:'¿Cambiar estado?', icon:'question', showCancelButton:true,
            confirmButtonText:'Sí', cancelButtonText:'No', confirmButtonColor:'#198754'
        }).then(res => {
            if (!res.isConfirmed) return;
            $.post(CTRL_PLAT, { accion:'toggleActivo', id_plataforma:id }, r => {
                if (r.ok) tabla.ajax.reload(null,false);
            }, 'json');
        });
    });

    function reset(titulo) {
        $('#modalPlataformaTitulo').text(titulo);
        $('#plat_id').val(''); $('#plat_nombre').val('').removeClass('is-invalid'); $('#plat_url').val('');
    }
    function abrir(sel) { const el=document.querySelector(sel); (bootstrap.Modal.getInstance(el)||new bootstrap.Modal(el)).show(); }
    function cerrar(sel) { const m=bootstrap.Modal.getInstance(document.querySelector(sel)); if(m) m.hide(); }
    function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
});
