const CTRL_TV = './modules/GPS/Parametrizacion/TiposVehiculo/controllers/tiposVehiculoController.php';

$(document).ready(function () {
    if (!document.getElementById('tblTiposVehiculo')) return;

    const tabla = $('#tblTiposVehiculo').DataTable({
        ajax: { url: CTRL_TV, type: 'POST', data: { accion: 'listar' }, dataSrc: r => r.ok ? r.data : [] },
        columns: [
            { data: null, render: (d,t,r,m) => m.row + 1 },
            { data: 'nombre', render: v => `<strong>${v}</strong>` },
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
                            <button class="dropdown-item btn-editar-tv" type="button"
                                    data-id="${r.id_tipo_vehiculo}" data-nombre="${esc(r.nombre)}">
                                <i class="bi bi-pencil me-2 text-warning"></i>Editar
                            </button>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <button class="dropdown-item ${r.activo==1?'text-danger':'text-success'} btn-toggle-tv"
                                    type="button" data-id="${r.id_tipo_vehiculo}">
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

    $('#btnNuevoTipoV').on('click', () => { reset('Nuevo tipo de vehículo'); abrir('#modalTipoV'); });

    $('#tblTiposVehiculo').on('click', '.btn-editar-tv', function () {
        reset('Editar tipo de vehículo');
        $('#tv_id').val($(this).data('id'));
        $('#tv_nombre').val($(this).data('nombre'));
        abrir('#modalTipoV');
    });

    $('#btnGuardarTipoV').on('click', function () {
        const id = $('#tv_id').val(), nombre = $('#tv_nombre').val().trim();
        if (!nombre) { $('#tv_nombre').addClass('is-invalid'); return; }
        $('#tv_nombre').removeClass('is-invalid');
        $.post(CTRL_TV, { accion: id?'editar':'crear', id_tipo_vehiculo: id, nombre }, r => {
            if (!r.ok) return Swal.fire({ icon:'error', title:'Error', text:r.mensaje });
            cerrar('#modalTipoV'); tabla.ajax.reload();
            Swal.fire({ icon:'success', title:'Listo', text:r.mensaje, timer:1800, showConfirmButton:false });
        }, 'json');
    });

    $('#tblTiposVehiculo').on('click', '.btn-toggle-tv', function () {
        const id = $(this).data('id');
        Swal.fire({ title:'¿Cambiar estado?', icon:'question', showCancelButton:true,
            confirmButtonText:'Sí', cancelButtonText:'No', confirmButtonColor:'#198754'
        }).then(res => {
            if (!res.isConfirmed) return;
            $.post(CTRL_TV, { accion:'toggleActivo', id_tipo_vehiculo:id }, r => {
                if (r.ok) tabla.ajax.reload(null,false);
            }, 'json');
        });
    });

    function reset(titulo) {
        $('#modalTipoVTitulo').text(titulo);
        $('#tv_id').val(''); $('#tv_nombre').val('').removeClass('is-invalid');
    }
    function abrir(sel) { const el=document.querySelector(sel); (bootstrap.Modal.getInstance(el)||new bootstrap.Modal(el)).show(); }
    function cerrar(sel) { const m=bootstrap.Modal.getInstance(document.querySelector(sel)); if(m) m.hide(); }
    function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
});
