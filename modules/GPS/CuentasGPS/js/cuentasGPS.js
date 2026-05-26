const CTRL_CU   = './modules/GPS/CuentasGPS/controllers/cuentasGPSController.php';
const CTRL_PLAT = './modules/GPS/Parametrizacion/Plataformas/controllers/plataformasController.php';
const CTRL_TR   = './modules/GPS/Transportes/controllers/transportesController.php';

$(document).ready(function () {
    if (!document.getElementById('tblCuentasGPS')) return;

    // ── Poblar selects del modal ───────────────────────────
    $.post(CTRL_TR,   { accion: 'listarActivos' },  r => poblarSelect('#cu_id_transporte', r.data, 'id_transporte', 'nombre'), 'json');
    $.post(CTRL_PLAT, { accion: 'listarActivas' },  r => poblarSelect('#cu_id_plataforma', r.data, 'id_plataforma', 'nombre'), 'json');

    // ── DataTable ──────────────────────────────────────────
    const tabla = $('#tblCuentasGPS').DataTable({
        ajax: { url: CTRL_CU, type: 'POST', data: { accion: 'listar' }, dataSrc: r => r.ok ? r.data : [] },
        columns: [
            { data: 'transporte',  render: v => `<strong>${v}</strong>` },
            { data: 'plataforma' },
            { data: 'url_base', render: v => v
                ? `<a href="${esc(v)}" target="_blank" rel="noopener" class="text-truncate d-inline-block" style="max-width:140px" title="${esc(v)}"><i class="bi bi-link-45deg"></i> ver</a>`
                : '<span class="text-muted">—</span>' },
            { data: 'usuario', defaultContent: '<span class="text-muted">—</span>' },
            { data: null, render: (d,t,r) => {
                const pwd = esc(r.contrasena || '');
                return `<span class="pwd-masked" data-pwd="${pwd}">••••••••</span>
                        <button class="btn btn-link btn-reveal-pwd p-0 ms-1" data-visible="0" title="Mostrar / ocultar">
                            <i class="bi bi-eye text-secondary"></i></button>`;
            }},
            { data: 'fecha_creacion',      render: v => v ? v.substring(0,10) : '—' },
            { data: 'creado_por_nombre',   defaultContent: '<span class="text-muted">—</span>' },
            { data: 'fecha_actualizacion', render: v => v ? v.substring(0,10) : '—' },
            { data: 'actualizado_por_nombre', defaultContent: '<span class="text-muted">—</span>' },
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
                            <button class="dropdown-item btn-editar-cu" type="button"
                                    data-id="${r.id_cuenta}"
                                    data-transporte="${r.id_transporte}"
                                    data-plataforma="${r.id_plataforma}"
                                    data-usuario="${esc(r.usuario||'')}"
                                    data-contrasena="${esc(r.contrasena||'')}">
                                <i class="bi bi-pencil me-2 text-warning"></i>Editar
                            </button>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <button class="dropdown-item ${r.activo==1?'text-danger':'text-success'} btn-toggle-cu"
                                    type="button" data-id="${r.id_cuenta}">
                                <i class="bi bi-${r.activo==1?'toggle-on':'toggle-off'} me-2"></i>
                                ${r.activo==1?'Desactivar':'Activar'}
                            </button>
                        </li>
                    </ul>
                </div>` }
        ],
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
        order: [[0,'asc']], pageLength: 15, scrollX: true
    });

    // ── Revelar contraseña en tabla ────────────────────────
    $('#tblCuentasGPS').on('click', '.btn-reveal-pwd', function () {
        const $btn = $(this), $m = $btn.prev('.pwd-masked'), vis = $btn.data('visible')==1;
        $m.text(vis ? '••••••••' : ($m.data('pwd') || '(vacía)'));
        $btn.data('visible', vis?0:1).find('i').toggleClass('bi-eye', vis).toggleClass('bi-eye-slash', !vis);
    });

    // ── Nuevo ──────────────────────────────────────────────
    $('#btnNuevaCuenta').on('click', () => { reset('Nueva cuenta GPS'); abrir('#modalCuenta'); });

    // ── Editar ─────────────────────────────────────────────
    $('#tblCuentasGPS').on('click', '.btn-editar-cu', function () {
        const $b = $(this);
        reset('Editar cuenta GPS');
        $('#cu_id').val($b.data('id'));
        $('#cu_id_transporte').val($b.data('transporte'));
        $('#cu_id_plataforma').val($b.data('plataforma'));
        $('#cu_usuario').val($b.data('usuario'));
        $('#cu_contrasena').val($b.data('contrasena'));
        abrir('#modalCuenta');
    });

    // ── Guardar ────────────────────────────────────────────
    $('#btnGuardarCuenta').on('click', function () {
        const id  = $('#cu_id').val();
        const itr = $('#cu_id_transporte').val();
        const ipl = $('#cu_id_plataforma').val();
        let ok = true;
        $('#cu_id_transporte').toggleClass('is-invalid', !itr); if (!itr) ok = false;
        $('#cu_id_plataforma').toggleClass('is-invalid', !ipl); if (!ipl) ok = false;
        if (!ok) return;
        $.post(CTRL_CU, {
            accion: id ? 'editar' : 'crear',
            id_cuenta: id, id_transporte: itr, id_plataforma: ipl,
            usuario:    $('#cu_usuario').val().trim(),
            contrasena: $('#cu_contrasena').val()
        }, r => {
            if (!r.ok) return Swal.fire({ icon:'error', title:'Error', text:r.mensaje });
            cerrar('#modalCuenta'); tabla.ajax.reload();
            Swal.fire({ icon:'success', title:'Listo', text:r.mensaje, timer:1800, showConfirmButton:false });
        }, 'json');
    });

    // ── Toggle ─────────────────────────────────────────────
    $('#tblCuentasGPS').on('click', '.btn-toggle-cu', function () {
        const id = $(this).data('id');
        Swal.fire({ title:'¿Cambiar estado?', icon:'question', showCancelButton:true,
            confirmButtonText:'Sí', cancelButtonText:'No', confirmButtonColor:'#198754'
        }).then(res => {
            if (!res.isConfirmed) return;
            $.post(CTRL_CU, { accion:'toggleActivo', id_cuenta:id }, r => {
                if (r.ok) tabla.ajax.reload(null,false);
            }, 'json');
        });
    });

    // ── Toggle pwd modal ───────────────────────────────────
    $(document).on('click', '.btn-toggle-pwd-cu', function () {
        const $i = $('#cu_contrasena');
        const show = $i.attr('type') === 'password';
        $i.attr('type', show ? 'text' : 'password');
        $(this).find('i').toggleClass('bi-eye', !show).toggleClass('bi-eye-slash', show);
    });

    // ── Helpers ────────────────────────────────────────────
    function poblarSelect(sel, data, valKey, txtKey) {
        const $s = $(sel);
        $s.find('option:not(:first)').remove();
        (data||[]).forEach(d => $s.append(`<option value="${d[valKey]}">${esc(d[txtKey])}</option>`));
    }
    function reset(titulo) {
        $('#modalCuentaTitulo').text(titulo);
        $('#cu_id').val('');
        $('#cu_id_transporte, #cu_id_plataforma').val('').removeClass('is-invalid');
        $('#cu_usuario').val(''); $('#cu_contrasena').val('').attr('type','password');
        $('.btn-toggle-pwd-cu i').removeClass('bi-eye-slash').addClass('bi-eye');
    }
    function abrir(sel) { const el=document.querySelector(sel); (bootstrap.Modal.getInstance(el)||new bootstrap.Modal(el)).show(); }
    function cerrar(sel) { const m=bootstrap.Modal.getInstance(document.querySelector(sel)); if(m) m.hide(); }
    function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
});
