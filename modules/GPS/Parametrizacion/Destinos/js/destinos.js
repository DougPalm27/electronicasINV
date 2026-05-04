const CTRL_DEST = './modules/GPS/Parametrizacion/Destinos/controllers/destinosController.php';

$(document).ready(function () {
    if (!document.getElementById('tblDestinos')) return;

    const tabla = $('#tblDestinos').DataTable({
        ajax: { url: CTRL_DEST, type: 'POST', data: { accion: 'listar' }, dataSrc: r => r.ok ? r.data : [] },
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
                <button class="btn btn-sm btn-warning btn-editar-dest me-1"
                        data-id="${r.id_destino}" data-nombre="${esc(r.nombre)}" title="Editar">
                    <i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm ${r.activo==1?'btn-danger':'btn-success'} btn-toggle-dest"
                        data-id="${r.id_destino}" title="${r.activo==1?'Desactivar':'Activar'}">
                    <i class="bi bi-${r.activo==1?'toggle-on':'toggle-off'}"></i></button>` }
        ],
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
        order: [[1,'asc']], pageLength: 10
    });

    $('#btnNuevoDestino').on('click', () => { reset('Nuevo destino'); abrir('#modalDestino'); });

    $('#tblDestinos').on('click', '.btn-editar-dest', function () {
        reset('Editar destino');
        $('#dest_id').val($(this).data('id'));
        $('#dest_nombre').val($(this).data('nombre'));
        abrir('#modalDestino');
    });

    $('#btnGuardarDestino').on('click', function () {
        const id = $('#dest_id').val(), nombre = $('#dest_nombre').val().trim();
        if (!nombre) { $('#dest_nombre').addClass('is-invalid'); return; }
        $('#dest_nombre').removeClass('is-invalid');
        $.post(CTRL_DEST, { accion: id?'editar':'crear', id_destino: id, nombre }, r => {
            if (!r.ok) return Swal.fire({ icon:'error', title:'Error', text:r.mensaje });
            cerrar('#modalDestino'); tabla.ajax.reload();
            Swal.fire({ icon:'success', title:'Listo', text:r.mensaje, timer:1800, showConfirmButton:false });
        }, 'json');
    });

    $('#tblDestinos').on('click', '.btn-toggle-dest', function () {
        const id = $(this).data('id');
        Swal.fire({ title:'¿Cambiar estado?', icon:'question', showCancelButton:true,
            confirmButtonText:'Sí', cancelButtonText:'No', confirmButtonColor:'#198754'
        }).then(res => {
            if (!res.isConfirmed) return;
            $.post(CTRL_DEST, { accion:'toggleActivo', id_destino:id }, r => {
                if (r.ok) tabla.ajax.reload(null,false);
            }, 'json');
        });
    });

    function reset(titulo) {
        $('#modalDestinoTitulo').text(titulo);
        $('#dest_id').val(''); $('#dest_nombre').val('').removeClass('is-invalid');
    }
    function abrir(sel) { const el=document.querySelector(sel); (bootstrap.Modal.getInstance(el)||new bootstrap.Modal(el)).show(); }
    function cerrar(sel) { const m=bootstrap.Modal.getInstance(document.querySelector(sel)); if(m) m.hide(); }
    function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
});
