(function ($) {
    'use strict';

    const CTRL = './modules/HorariosMonitoreo/controllers/horarioController.php';

    const MESES = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
                   'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    const DIAS  = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];

    // Estilos por orden_rotacion del turno
    const TURNO_ESTILO = {
        1: { bg: 'bg-primary',  text: 'text-white' },
        2: { bg: 'bg-success',  text: 'text-white' },
        3: { bg: 'bg-warning',  text: 'text-dark'  },
        4: { bg: 'bg-dark',     text: 'text-white' }
    };

    // Cobertura para días de descanso (keyed por dia_descanso del turno que descansa)
    // mismodia: modificaciones ese mismo día; siguientedia: modificaciones el día siguiente
    const COBERTURA = {
        1: { mismodia:     [{ orden: 1, label: '5am - 5pm',  nota: 'Cubre turno libre' }] },
        2: { mismodia:     [{ orden: 1, label: '10am - 10pm', nota: 'Cubre turno libre' }] },
        3: { mismodia:     [{ orden: 3, label: '2pm - 2am',  nota: 'Extiende turno'   }],
             siguientedia: [{ orden: 2, label: '2am - 2pm',  nota: 'Cubre madrugada'  }] }
    };

    let viewMes  = new Date().getMonth() + 1;
    let viewAnio = new Date().getFullYear();
    let horarioActual = null;   // datos del mes visible
    let turnosData    = [];     // catálogo de turnos
    let propuestaData    = null;   // rotación calculada
    let vistaCalendario  = 'tabla'; // 'tabla' | 'semanal'

    // ══════════════════════════════════════════════════════════
    // INIT
    // ══════════════════════════════════════════════════════════
    $(function () {
        cargarTurnos();
        cargarHorario(viewMes, viewAnio);
        bindNavegacion();
        bindGestion();
        bindExcepciones();
        bindConfiguracion();
        bindHistorial();

        // Cargar datos de pestaña al activarla
        $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
            const target = $(e.target).data('bsTarget');
            if (target === '#tab-excepciones') cargarExcepciones();
            if (target === '#tab-config')      cargarConfiguracion();
        });

        // Populate año selector para feriados
        const anioActual = new Date().getFullYear();
        for (let y = anioActual - 1; y <= anioActual + 2; y++) {
            $('#selAnioFeriados').append(`<option value="${y}" ${y === anioActual ? 'selected' : ''}>${y}</option>`);
        }
    });

    // ══════════════════════════════════════════════════════════
    // CATÁLOGO DE TURNOS
    // ══════════════════════════════════════════════════════════
    function cargarTurnos() {
        $.post(CTRL, { accion: 'listarTurnos' }, function (r) {
            if (r.ok) turnosData = r.data;
        }, 'json');
    }

    // ══════════════════════════════════════════════════════════
    // CALENDARIO
    // ══════════════════════════════════════════════════════════
    function cargarHorario(mes, anio) {
        actualizarLabelMes(mes, anio);
        $('#cal-loading').show();
        $('#cal-content, #cal-semanal, #cal-empty').addClass('d-none');

        $.post(CTRL, { accion: 'obtenerHorarioMes', mes, anio }, function (r) {
            $('#cal-loading').hide();
            if (!r.ok || !r.data) {
                $('#cal-empty').removeClass('d-none').find('#cal-empty-mes').text(MESES[mes-1] + ' ' + anio);
                $('#cal-estado-badge, #cal-anular-btn').html('');
                $('#cal-alertas').html('');
                horarioActual = null;
                actualizarBadgeExcepciones(0);
                return;
            }
            horarioActual = r.data;
            renderEstadoBadge(r.data.estado);

            if (vistaCalendario === 'semanal') {
                renderCalendarioSemanal(r.data, mes, anio);
                $('#cal-semanal').removeClass('d-none');
            } else {
                renderCalendario(r.data, mes, anio);
                $('#cal-content').removeClass('d-none');
            }

            const pendientes = (r.data.excepciones || []).filter(e => e.estado === 'pendiente').length;
            actualizarBadgeExcepciones(pendientes);
        }, 'json');
    }

    function actualizarLabelMes(mes, anio) {
        $('#cal-mes-label').text(MESES[mes - 1] + ' ' + anio);
    }

    function renderEstadoBadge(estado) {
        const map = {
            borrador:  '<span class="badge bg-secondary">Borrador</span>',
            activo:    '<span class="badge bg-success">Activo</span>',
            archivado: '<span class="badge bg-secondary">Archivado</span>'
        };
        $('#cal-estado-badge').html(map[estado] || '');

        if (window.USUARIO_ROL !== 'Administrador') { $('#cal-anular-btn').html(''); return; }
        if (estado === 'borrador') {
            $('#cal-anular-btn').html(
                `<button class="btn btn-outline-danger btn-sm" id="btnAnularHorario"
                         data-estado="borrador" title="Eliminar borrador">
                    <i class="bi bi-trash"></i>
                 </button>`
            );
        } else if (estado === 'activo') {
            $('#cal-anular-btn').html(
                `<button class="btn btn-outline-danger btn-sm" id="btnAnularHorario"
                         data-estado="activo" title="Anular horario">
                    <i class="bi bi-x-circle me-1"></i>Anular
                 </button>`
            );
        } else {
            $('#cal-anular-btn').html('');
        }
    }

    function renderAlertas(data, mes, anio) {
        const feriados = {};
        (data.feriados || []).forEach(f => { feriados[f.fecha] = f; });
        const excByFecha = {};
        (data.excepciones || []).forEach(e => { excByFecha[e.fecha] = e; });
        const tecnicos  = data.tecnicos || [];
        const diasMes   = new Date(anio, mes, 0).getDate();

        const problemas = [];

        for (let dia = 1; dia <= diasMes; dia++) {
            const fechaStr = `${anio}-${String(mes).padStart(2,'0')}-${String(dia).padStart(2,'0')}`;
            const diaSem   = new Date(fechaStr + 'T12:00:00').getDay();
            const feriado  = feriados[fechaStr];
            const exc      = excByFecha[fechaStr];

            // Incapacidades sin cobertura resuelta
            if (exc && exc.tipo === 'incapacidad' && exc.estado === 'pendiente') {
                const ausTec = exc.tecnicos && exc.tecnicos.find(et => et.rol === 'ausente');
                problemas.push({ dia, diaSem, nombre: `Incapacidad${ausTec ? ': ' + ausTec.nombre : ''}`, estado: 'pendiente' });
            }

            if (!feriado) continue;

            // Si todos descansan ese día no hay riesgo de horas vacías
            const alguienTrabaja = tecnicos.some(t => parseInt(t.dia_descanso) !== diaSem);
            if (!alguienTrabaja) continue;

            if (!exc) {
                problemas.push({ dia, diaSem, nombre: feriado.nombre, estado: 'sin_excepcion' });
            } else if (exc.estado === 'pendiente') {
                problemas.push({ dia, diaSem, nombre: feriado.nombre, estado: 'pendiente' });
            }
        }

        if (!problemas.length) { $('#cal-alertas').html(''); return; }

        const items = problemas.map(p => {
            const esPend = p.estado === 'pendiente';
            const badge  = esPend
                ? '<span class="badge bg-warning text-dark ms-1">Pendiente</span>'
                : '<span class="badge bg-danger ms-1">Sin resolver</span>';
            return `<li><strong>${p.dia} ${DIAS[p.diaSem]}</strong> — ${escH(p.nombre)}${badge}</li>`;
        }).join('');

        $('#cal-alertas').html(`
            <div class="alert alert-warning border-warning py-2 d-flex gap-2 mb-0" role="alert">
                <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1" style="color:#e6a817"></i>
                <div>
                    <strong>${problemas.length} día${problemas.length > 1 ? 's' : ''} con horas posiblemente sin cubrir:</strong>
                    <ul class="mb-0 mt-1 ps-3 small">${items}</ul>
                </div>
            </div>`);
    }

    function renderCalendario(data, mes, anio) {
        const tecnicos = data.tecnicos || [];
        const feriados = {};
        (data.feriados || []).forEach(f => { feriados[f.fecha] = f; });
        const excepciones = {};
        (data.excepciones || []).forEach(e => { excepciones[e.fecha] = e; });

        const diasMes = new Date(anio, mes, 0).getDate();

        // ── Encabezado ───────────────────────────────────────
        let thead = '<tr><th class="td-dia">Día</th>';
        tecnicos.forEach(function (t) {
            const estilo = TURNO_ESTILO[t.orden_rotacion] || { bg: 'bg-secondary', text: 'text-white' };
            let alerta = '';
            if ((+t.meses_consecutivos) >= 3) {
                alerta = `<span class="badge badge-meses-alerta ms-1" title="${t.meses_consecutivos} meses en este turno">` +
                         `<i class="bi bi-exclamation-triangle-fill"></i> ${t.meses_consecutivos}m</span>`;
            }
            let comodinBadge = t.es_comodin == 1
                ? `<span class="badge badge-comodin bg-info text-dark ms-1" title="Turno comodín (Sáb sale 8am-12pm)">⭐ Comodín</span>`
                : '';
            thead += `<th class="td-turno">
                ${escH(t.nombre_tecnico)}
                <span class="badge ${estilo.bg} ${estilo.text} th-turno-badge">${escH(t.nombre_turno)}</span>
                ${comodinBadge}${alerta}
            </th>`;
        });
        thead += '</tr>';

        // ── Filas ─────────────────────────────────────────────
        let tbody = '';
        for (let dia = 1; dia <= diasMes; dia++) {
            const fechaStr = `${anio}-${String(mes).padStart(2,'0')}-${String(dia).padStart(2,'0')}`;
            const diaSem   = new Date(fechaStr + 'T12:00:00').getDay(); // 0=Dom
            const esSabado = diaSem === 6;

            // Calcular coberturas activas para este día
            const diaPrevio       = diaSem === 0 ? 6 : diaSem - 1;
            const tecDescansaHoy  = tecnicos.find(t => parseInt(t.dia_descanso) === diaSem);
            const tecDescansaAyer = tecnicos.find(t => parseInt(t.dia_descanso) === diaPrevio);
            const coberturas = {};
            if (tecDescansaHoy)  { (COBERTURA[diaSem]    && COBERTURA[diaSem].mismodia     || []).forEach(c => { coberturas[c.orden] = c; }); }
            if (tecDescansaAyer) { (COBERTURA[diaPrevio] && COBERTURA[diaPrevio].siguientedia || []).forEach(c => { coberturas[c.orden] = c; }); }

            const feriado   = feriados[fechaStr];
            const excepcion = excepciones[fechaStr];

            // Si hay excepción resuelta donde el técnico de descanso viene a trabajar (compensación, doble pago),
            // las coberturas estáticas ya no aplican. Incapacidad NO anula coberturas (el técnico sigue ausente).
            if (excepcion && excepcion.estado === 'resuelta' && excepcion.tipo !== 'incapacidad' && excepcion.tecnicos && tecDescansaHoy) {
                if (excepcion.tecnicos.some(et => +et.id_tecnico_mon === +tecDescansaHoy.id_tecnico_mon && et.rol === 'trabaja')) {
                    Object.keys(coberturas).forEach(k => delete coberturas[k]);
                }
            }
            // Igual para el spillover del día anterior
            const dateAyerObj  = new Date(fechaStr + 'T12:00:00');
            dateAyerObj.setDate(dateAyerObj.getDate() - 1);
            const fechaAyerStr  = dateAyerObj.toISOString().slice(0, 10);
            const excepcionAyer = excepciones[fechaAyerStr];
            if (excepcionAyer && excepcionAyer.estado === 'resuelta' && excepcionAyer.tipo !== 'incapacidad' && excepcionAyer.tecnicos && tecDescansaAyer) {
                if (excepcionAyer.tecnicos.some(et => +et.id_tecnico_mon === +tecDescansaAyer.id_tecnico_mon && et.rol === 'trabaja')) {
                    (COBERTURA[diaPrevio] && COBERTURA[diaPrevio].siguientedia || []).forEach(c => delete coberturas[c.orden]);
                }
            }
            const rowClass  = feriado ? 'celda-feriado-bg' : '';

            let tdDia = `<td class="td-dia ${rowClass}">
                <strong>${dia}</strong> <span class="text-muted">${DIAS[diaSem]}</span>`;
            if (feriado) {
                tdDia += `<br><span class="badge" style="background:#ffc10722;color:#856404;border:1px solid #ffc107;font-size:.68rem">
                    <i class="bi bi-star-fill me-1" style="color:#ffc107"></i>${escH(feriado.nombre)}
                </span>`;
            }
            // Botón para registrar turno especial en días sin feriado (solo admin)
            if (!feriado && !excepcion && window.USUARIO_ROL === 'Administrador' &&
                horarioActual && (horarioActual.estado === 'activo' || horarioActual.estado === 'borrador')) {
                tdDia += `<br><button class="btn btn-link btn-sm p-0 text-muted btn-nueva-exc-dia"
                              data-fecha="${fechaStr}" style="font-size:.65rem;line-height:1.2">
                          <i class="bi bi-plus-circle"></i> especial
                          </button>`;
            }
            tdDia += '</td>';

            let row = `<tr class="${rowClass}">${tdDia}`;

            tecnicos.forEach(function (t) {
                // Rol asignado por excepción (incapacidad desde creación; otros desde resolución)
                let excRolTec = null;
                if (excepcion && excepcion.tecnicos) {
                    excRolTec = excepcion.tecnicos.find(et => +et.id_tecnico_mon === +t.id_tecnico_mon) || null;
                }

                const esDescansoNatural = (parseInt(t.dia_descanso) === diaSem);
                // Si la excepción indica que el técnico trabaja, su descanso queda anulado
                const esDescanso = esDescansoNatural && !(excRolTec && excRolTec.rol === 'trabaja');
                const estilo = TURNO_ESTILO[t.orden_rotacion] || { bg: 'bg-secondary', text: 'text-white' };

                if (esDescanso) {
                    row += `<td class="celda-descanso"><span class="badge bg-secondary">Descanso</span></td>`;
                    return;
                }

                // Técnico incapacitado ese día
                if (excRolTec && excRolTec.rol === 'ausente') {
                    let coverBtn   = '';
                    let gestionBtn = '';

                    if (window.USUARIO_ROL === 'Administrador' && excepcion) {
                        gestionBtn = `<br><button class="btn btn-link btn-sm p-0 text-secondary btn-gestionar-inc"
                                          data-id="${excepcion.id_excepcion}"
                                          data-fecha="${fechaStr}"
                                          data-tecausente="${t.id_tecnico_mon}"
                                          data-referencia="${escAttr(excepcion.referencia || '')}"
                                          style="font-size:.63rem" title="Editar / Anular incapacidad">
                                      <i class="bi bi-three-dots"></i>
                                      </button>`;
                    }

                    if (excepcion && excepcion.estado === 'pendiente' && window.USUARIO_ROL === 'Administrador') {
                        coverBtn = `<br><button class="btn btn-link btn-sm p-0 text-warning btn-cubrir-inc"
                                        data-id="${excepcion.id_excepcion}"
                                        data-fecha="${fechaStr}"
                                        data-tecausente="${t.id_tecnico_mon}"
                                        style="font-size:.68rem">
                                    <i class="bi bi-person-fill-exclamation me-1"></i>Sin cobertura
                                    </button>`;
                    } else if (excepcion && excepcion.estado === 'resuelta') {
                        const cubridor = excepcion.tecnicos && excepcion.tecnicos.find(et => et.rol === 'cubre');
                        const cubreTxt = cubridor
                            ? `${escH(cubridor.nombre)}${cubridor.turno_especial ? ' (' + escH(cubridor.turno_especial) + ')' : ''}`
                            : 'Cubierto';
                        coverBtn = `<br><span style="font-size:.68rem;color:#198754"><i class="bi bi-check2 me-1"></i>${cubreTxt}</span>`;
                    }
                    row += `<td class="${rowClass}"><span class="badge bg-danger">Incapacitado</span>${coverBtn}${gestionBtn}</td>`;
                    return;
                }

                // Excepción indica que este técnico descansa ese día (ej. los 2 que no hacen turno_12h)
                if (excRolTec && excRolTec.rol === 'descansa') {
                    row += `<td class="${rowClass}"><span class="badge bg-secondary" title="Descansa por turno especial">Descanso</span></td>`;
                    return;
                }

                let labelTurno;
                if (esSabado && t.es_comodin == 1) {
                    labelTurno = `${fmtHora(t.hora_sab_inicio)} - ${fmtHora(t.hora_sab_fin)}
                                  <small class="d-block text-muted" style="font-size:.68rem">Comodín hasta mediodía</small>`;
                } else if (excRolTec && excRolTec.turno_especial) {
                    // Turno especial registrado (12h u otro)
                    labelTurno = `<strong>${escH(excRolTec.turno_especial)}</strong>` +
                        `<small class="d-block" style="font-size:.67rem;font-style:italic;opacity:.85">Turno especial</small>`;
                } else {
                    const cobertura = coberturas[parseInt(t.orden_rotacion)];
                    if (cobertura) {
                        labelTurno = `${escH(cobertura.label)}<small class="d-block" style="font-size:.67rem;font-style:italic;opacity:.9">${escH(cobertura.nota)}</small>`;
                    } else {
                        labelTurno = escH(t.nombre_turno);
                    }
                }

                let excBadge = '';
                if (feriado) {
                    if (!excepcion || excepcion.estado === 'pendiente') {
                        const isPending = !excepcion;
                        const btnAttrs = `data-id="${excepcion ? excepcion.id_excepcion : ''}"
                                          data-fecha="${fechaStr}"
                                          data-desc="${escAttr(excepcion ? excepcion.descripcion : 'Feriado: ' + feriado.nombre)}"`;
                        excBadge = `<br><button class="btn btn-link btn-sm p-0 text-danger btn-resolver-exc" ${btnAttrs}
                                        style="font-size:.72rem">
                                        <i class="bi bi-exclamation-circle me-1"></i>${isPending ? 'Sin resolver' : 'Pendiente'}
                                    </button>`;
                    } else if (excepcion.estado === 'resuelta') {
                        const labels = {
                            doble_pago:    '<span style="color:#198754;font-size:.7rem"><i class="bi bi-check2 me-1"></i>Pago doble</span>',
                            compensacion:  '<span style="color:#0d6efd;font-size:.7rem"><i class="bi bi-check2 me-1"></i>Compensación</span>',
                            turno_12h:     '<span style="color:#6f42c1;font-size:.7rem"><i class="bi bi-check2 me-1"></i>Turno 12h</span>',
                            sin_accion:    '<span style="color:#6c757d;font-size:.7rem"><i class="bi bi-dash me-1"></i>Sin acción</span>'
                        };
                        excBadge = `<br>${labels[excepcion.resolucion] || ''}`;
                    }
                }

                row += `<td class="${rowClass}">
                    <span class="badge ${estilo.bg} ${estilo.text}">${labelTurno}</span>
                    ${excBadge}
                </td>`;
            });

            row += '</tr>';
            tbody += row;
        }

        $('#tablaCalendario thead').html(thead);
        $('#tablaCalendario tbody').html(tbody);
        renderAlertas(data, mes, anio);
    }

    function renderCalendarioSemanal(data, mes, anio) {
        const tecnicos    = data.tecnicos || [];
        const feriados    = {};
        (data.feriados    || []).forEach(f => { feriados[f.fecha]    = f; });
        const excepciones = {};
        (data.excepciones || []).forEach(e => { excepciones[e.fecha] = e; });

        const tecOrdenados = [...tecnicos].sort((a, b) => (+a.orden_rotacion) - (+b.orden_rotacion));
        const diasMes = new Date(anio, mes, 0).getDate();

        // Construir lista de días del mes
        const todosDias = [];
        for (let d = 1; d <= diasMes; d++) {
            const f = `${anio}-${String(mes).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
            todosDias.push({ dia: d, fechaStr: f, diaSem: new Date(f + 'T12:00:00').getDay() });
        }

        // Agrupar en semanas (nueva semana al llegar al domingo)
        const semanas = [];
        let actual = [];
        todosDias.forEach(d => {
            if (d.diaSem === 0 && actual.length) { semanas.push(actual); actual = []; }
            actual.push(d);
        });
        if (actual.length) semanas.push(actual);

        let html = '';

        semanas.forEach((semDias, wIdx) => {
            const dIni = semDias[0].dia;
            const dFin = semDias[semDias.length - 1].dia;

            html += `<div class="semana-bloque mb-3">`;
            html += `<div class="semana-titulo">Semana ${wIdx + 1} &mdash; ${dIni} al ${dFin} de ${MESES[mes-1]} ${anio}</div>`;
            html += `<div class="table-responsive-xxl"><table class="table table-hover w-100 table-semanal mb-0">`;

            // ── Encabezado de días ──────────────────────────
            html += '<thead><tr><th class="col-turno" style="color:#9da5b4;font-size:.72rem">Horario</th>';
            semDias.forEach(d => {
                const esFer = !!feriados[d.fechaStr];
                html += `<th class="text-center${esFer ? ' feriado-header' : ''}" style="min-width:72px">
                    <div class="fw-semibold">${DIAS[d.diaSem]}</div>
                    <div style="font-size:.7rem;color:#adb5bd">${d.dia}</div>
                    ${esFer ? `<div style="font-size:.6rem;color:#856404;white-space:nowrap"><i class="bi bi-star-fill" style="color:#ffc107"></i>&nbsp;${escH(feriados[d.fechaStr].nombre)}</div>` : ''}
                </th>`;
            });
            html += '</tr></thead><tbody>';

            // ── Filas de turnos ─────────────────────────────
            tecOrdenados.forEach(t => {
                const estilo     = TURNO_ESTILO[t.orden_rotacion] || { bg: 'bg-secondary', text: 'text-white' };
                const turnoHoras = `${fmtHora(t.hora_inicio)} – ${fmtHora(t.hora_fin)}`;

                html += '<tr>';
                html += `<td class="col-turno">
                    <span class="badge ${estilo.bg} ${estilo.text}" style="font-size:.72rem;white-space:nowrap">${escH(turnoHoras)}</span>
                    <small class="d-block text-muted mt-1" style="font-size:.63rem;max-width:108px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis">${escH(t.nombre_tecnico)}</small>
                </td>`;

                semDias.forEach(d => {
                    const exc    = excepciones[d.fechaStr];
                    let excRol   = null;
                    if (exc && exc.tecnicos) excRol = exc.tecnicos.find(et => +et.id_tecnico_mon === +t.id_tecnico_mon) || null;

                    const descansoNat = parseInt(t.dia_descanso) === d.diaSem;
                    const esDescanso  = descansoNat && !(excRol && excRol.rol === 'trabaja');
                    const esFer       = !!feriados[d.fechaStr];
                    const cellBg      = esFer ? ' celda-feriado-bg' : '';

                    if (excRol && excRol.rol === 'ausente') {
                        html += `<td class="text-center${cellBg} p-1"><span class="badge bg-danger" style="font-size:.62rem">Incap.</span></td>`;
                        return;
                    }
                    if (esDescanso) {
                        html += `<td class="text-center celda-descanso${cellBg} p-1" style="font-size:.7rem;color:#adb5bd">Día libre</td>`;
                        return;
                    }

                    // Verificar cobertura dinámica
                    const diaPrev  = d.diaSem === 0 ? 6 : d.diaSem - 1;
                    const tecDHoy  = tecnicos.find(tx => parseInt(tx.dia_descanso) === d.diaSem);
                    const tecDAyer = tecnicos.find(tx => parseInt(tx.dia_descanso) === diaPrev);
                    const cobMap   = {};
                    if (tecDHoy)  (COBERTURA[d.diaSem]  && COBERTURA[d.diaSem].mismodia     || []).forEach(c => { cobMap[c.orden] = c; });
                    if (tecDAyer) (COBERTURA[diaPrev]   && COBERTURA[diaPrev].siguientedia  || []).forEach(c => { cobMap[c.orden] = c; });

                    if (excRol && excRol.turno_especial) {
                        html += `<td class="text-center${cellBg} p-1">
                            <span class="badge bg-info text-dark" style="font-size:.62rem;white-space:nowrap">${escH(excRol.turno_especial)}</span>
                        </td>`;
                        return;
                    }

                    const cob = cobMap[parseInt(t.orden_rotacion)];
                    if (cob) {
                        html += `<td class="text-center${cellBg} p-1" title="${escAttr(cob.nota)}">
                            <span class="badge ${estilo.bg} ${estilo.text}" style="font-size:.62rem;white-space:nowrap">${escH(cob.label)}</span>
                        </td>`;
                        return;
                    }

                    if (d.diaSem === 6 && t.es_comodin == 1 && t.hora_sab_inicio) {
                        html += `<td class="text-center${cellBg} p-1">
                            <span class="badge bg-info text-dark" style="font-size:.62rem">${fmtHora(t.hora_sab_inicio)}–${fmtHora(t.hora_sab_fin)}</span>
                        </td>`;
                        return;
                    }

                    html += `<td class="text-center${cellBg} p-1">
                        <span class="badge ${estilo.bg} ${estilo.text}" style="font-size:.62rem">${escH(turnoHoras)}</span>
                    </td>`;
                });

                html += '</tr>';
            });

            // ── Sección de cobertura ────────────────────────
            const cobDias = [];
            semDias.forEach(d => {
                const diaPrev  = d.diaSem === 0 ? 6 : d.diaSem - 1;
                const tecDHoy  = tecnicos.find(t => parseInt(t.dia_descanso) === d.diaSem);
                const tecDAyer = tecnicos.find(t => parseInt(t.dia_descanso) === diaPrev);
                const items    = [];

                if (tecDHoy && COBERTURA[d.diaSem] && COBERTURA[d.diaSem].mismodia) {
                    COBERTURA[d.diaSem].mismodia.forEach(c => {
                        const tc = tecnicos.find(t => parseInt(t.orden_rotacion) === c.orden);
                        if (tc) items.push(`<strong>${escH(tc.nombre_tecnico)}</strong>: ${escH(c.label)}`);
                    });
                }
                if (tecDAyer && COBERTURA[diaPrev] && COBERTURA[diaPrev].siguientedia) {
                    COBERTURA[diaPrev].siguientedia.forEach(c => {
                        const tc = tecnicos.find(t => parseInt(t.orden_rotacion) === c.orden);
                        if (tc) items.push(`<strong>${escH(tc.nombre_tecnico)}</strong>: ${escH(c.label)} <em class="text-muted">(cont.)</em>`);
                    });
                }

                if (items.length) {
                    cobDias.push({
                        d,
                        libreNombre: tecDHoy ? tecDHoy.nombre_tecnico : null,
                        items
                    });
                }
            });

            if (cobDias.length) {
                html += `<tr class="table-secondary">
                    <td colspan="${semDias.length + 1}" class="py-1 px-2" style="font-size:.68rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:#495057">
                        <i class="bi bi-person-lines-fill me-1"></i>Días libres — Quién cubre
                    </td>
                </tr>`;
                cobDias.forEach(({ d, libreNombre, items }) => {
                    html += `<tr class="table-light">
                        <td class="py-1 px-2" style="font-size:.7rem;font-weight:600">
                            ${DIAS[d.diaSem]} ${d.dia}
                            ${libreNombre ? `<br><span class="text-muted fw-normal" style="font-size:.63rem">${escH(libreNombre)} libre</span>` : ''}
                        </td>
                        <td colspan="${semDias.length}" class="py-1 px-2" style="font-size:.72rem">
                            ${items.join(' &nbsp;&bull;&nbsp; ')}
                        </td>
                    </tr>`;
                });
            }

            html += '</tbody></table></div></div>';
        });

        $('#cal-semanal').html(html || '<p class="text-muted text-center py-4">Sin datos para mostrar.</p>');
        renderAlertas(data, mes, anio);
    }

    // ══════════════════════════════════════════════════════════
    // NAVEGACIÓN
    // ══════════════════════════════════════════════════════════
    function bindNavegacion() {
        $('#btnMesPrev').on('click', function () {
            viewMes--;
            if (viewMes < 1) { viewMes = 12; viewAnio--; }
            cargarHorario(viewMes, viewAnio);
        });
        $('#btnMesNext').on('click', function () {
            viewMes++;
            if (viewMes > 12) { viewMes = 1; viewAnio++; }
            cargarHorario(viewMes, viewAnio);
        });

        $('#btnVistaTabla, #btnVistaSemanal').on('click', function () {
            const vista = $(this).data('vista');
            if (vista === vistaCalendario) return;
            vistaCalendario = vista;

            $('#btnVistaTabla')
                .toggleClass('btn-primary active',  vista === 'tabla')
                .toggleClass('btn-outline-primary',  vista !== 'tabla');
            $('#btnVistaSemanal')
                .toggleClass('btn-primary active',  vista === 'semanal')
                .toggleClass('btn-outline-primary',  vista !== 'semanal');

            if (!horarioActual) return;
            if (vista === 'semanal') {
                $('#cal-content').addClass('d-none');
                $('#cal-semanal').removeClass('d-none');
                renderCalendarioSemanal(horarioActual, viewMes, viewAnio);
            } else {
                $('#cal-semanal').addClass('d-none');
                $('#cal-content').removeClass('d-none');
                renderCalendario(horarioActual, viewMes, viewAnio);
            }
        });
    }

    // ══════════════════════════════════════════════════════════
    // GESTIÓN (proponer / activar horario)
    // ══════════════════════════════════════════════════════════
    function bindGestion() {
        $('#btnNuevoMes').on('click', function () {
            propuestaData = null;
            $('#gestion-content').addClass('d-none');
            $('#gestion-loading').show();
            $('#btnGuardarBorrador, #btnActivarHorario').addClass('d-none');
            const modal = new bootstrap.Modal(document.getElementById('modalGestion'));
            modal.show();

            $.post(CTRL, { accion: 'proponerRotacion', mes: viewMes, anio: viewAnio }, function (r) {
                $('#gestion-loading').hide();
                if (!r.ok) {
                    Swal.fire('Error', r.mensaje, 'error');
                    return;
                }
                propuestaData = r.data;
                renderPropuesta(r.data);
                $('#gestion-content').removeClass('d-none');
                $('#btnGuardarBorrador, #btnActivarHorario').removeClass('d-none');
            }, 'json');
        });

        $('#btnGuardarBorrador').on('click', function () {
            guardarHorario(false);
        });
        $('#btnActivarHorario').on('click', function () {
            Swal.fire({
                title: '¿Activar horario?',
                text: 'Se archivará el mes activo actual y este quedará como el nuevo horario vigente.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, activar',
                cancelButtonText: 'Cancelar'
            }).then(r => { if (r.isConfirmed) guardarHorario(true); });
        });

        // Anular horario (borrador → eliminar | activo → revertir o eliminar)
        $(document).on('click', '#btnAnularHorario', function () {
            if (!horarioActual) return;
            const estado   = $(this).data('estado');
            const id       = horarioActual.id_horario_mes;
            const mesLabel = MESES[viewMes - 1] + ' ' + viewAnio;

            if (estado === 'borrador') {
                Swal.fire({
                    title: '¿Eliminar borrador?',
                    html: `Se eliminará el borrador de <strong>${mesLabel}</strong>.<br>Esta acción no se puede deshacer.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#dc3545',
                }).then(r => {
                    if (!r.isConfirmed) return;
                    $.post(CTRL, { accion: 'eliminarHorario', id_horario_mes: id }, function (res) {
                        if (!res.ok) { Swal.fire('Error', res.mensaje, 'error'); return; }
                        Swal.fire({ icon: 'success', title: 'Eliminado', text: res.mensaje, timer: 1800, showConfirmButton: false });
                        horarioActual = null;
                        $('#cal-anular-btn').html('');
                        cargarHorario(viewMes, viewAnio);
                    }, 'json');
                });

            } else if (estado === 'activo') {
                Swal.fire({
                    title: 'Anular horario activo',
                    html: `El horario de <strong>${mesLabel}</strong> está activo.<br><br>
                           <strong>Revertir a borrador</strong>: conserva los datos y permite corregirlos.<br>
                           <strong>Eliminar completamente</strong>: borra el horario y todas sus excepciones.`,
                    icon: 'warning',
                    showCancelButton: true,
                    showDenyButton: true,
                    cancelButtonText: 'Cancelar',
                    confirmButtonText: '<i class="bi bi-arrow-counterclockwise me-1"></i>Revertir a borrador',
                    denyButtonText:    '<i class="bi bi-trash me-1"></i>Eliminar todo',
                    confirmButtonColor: '#fd7e14',
                    denyButtonColor:    '#dc3545',
                }).then(r => {
                    if (r.isConfirmed) {
                        $.post(CTRL, { accion: 'revertirHorario', id_horario_mes: id }, function (res) {
                            if (!res.ok) { Swal.fire('Error', res.mensaje, 'error'); return; }
                            Swal.fire({ icon: 'success', title: 'Revertido', text: res.mensaje, timer: 2000, showConfirmButton: false });
                            cargarHorario(viewMes, viewAnio);
                        }, 'json');
                    } else if (r.isDenied) {
                        Swal.fire({
                            title: '¿Confirmas la eliminación?',
                            html: `Se borrarán el horario y <strong>todas las excepciones e incapacidades</strong> de ${mesLabel}.<br>Esto no se puede deshacer.`,
                            icon: 'error',
                            showCancelButton: true,
                            confirmButtonText: 'Sí, eliminar todo',
                            cancelButtonText: 'Cancelar',
                            confirmButtonColor: '#dc3545',
                        }).then(r2 => {
                            if (!r2.isConfirmed) return;
                            $.post(CTRL, { accion: 'eliminarHorario', id_horario_mes: id }, function (res) {
                                if (!res.ok) { Swal.fire('Error', res.mensaje, 'error'); return; }
                                Swal.fire({ icon: 'success', title: 'Eliminado', text: res.mensaje, timer: 1800, showConfirmButton: false });
                                horarioActual = null;
                                $('#cal-anular-btn').html('');
                                cargarHorario(viewMes, viewAnio);
                            }, 'json');
                        });
                    }
                });
            }
        });
    }

    function renderPropuesta(data) {
        const turnoOpts = (data.turnos || []).map(t =>
            `<option value="${t.id_turno}">${escH(t.nombre)}</option>`
        ).join('');

        const mes  = data.target_mes;
        const anio = data.target_anio;
        $('#gestion-mes-titulo').text(MESES[mes - 1] + ' ' + anio);

        let rows = '';
        (data.propuesta || []).forEach(function (p) {
            const selTurno = turnoOpts.replace(`value="${p.id_turno}"`, `value="${p.id_turno}" selected`);
            const estActual = getBadgeEstilo(p.id_turno_actual, data.turnos);
            const mesAlerta = (+p.meses_consecutivos) >= 3
                ? `<span class="badge badge-meses-alerta"><i class="bi bi-exclamation-triangle-fill me-1"></i>${p.meses_consecutivos} meses</span>`
                : `<span class="badge bg-light text-dark">${p.meses_consecutivos} mes</span>`;

            rows += `<tr>
                <td class="fw-semibold">${escH(p.nombre)}</td>
                <td>${estActual}</td>
                <td><i class="bi bi-arrow-right text-muted"></i></td>
                <td>
                    <select class="form-select form-select-sm sel-turno-propuesto"
                            data-tecnico="${p.id_tecnico_mon}"
                            data-meses="${p.meses_consecutivos}">
                        ${selTurno}
                    </select>
                </td>
                <td class="text-center">${mesAlerta}</td>
            </tr>`;
        });

        $('#tbodyGestion').html(rows || '<tr><td colspan="5" class="text-center text-muted">Sin técnicos asignados.</td></tr>');
        $('#gestion-notas').val('');
    }

    function getBadgeEstilo(idTurno, turnos) {
        if (!idTurno) return '<span class="text-muted">—</span>';
        const t = (turnos || []).find(x => +x.id_turno === +idTurno);
        if (!t) return '<span class="text-muted">—</span>';
        const est = TURNO_ESTILO[t.orden_rotacion] || { bg: 'bg-secondary', text: 'text-white' };
        return `<span class="badge ${est.bg} ${est.text}">${escH(t.nombre)}</span>`;
    }

    function guardarHorario(activar) {
        if (!propuestaData) return;
        const mes  = propuestaData.target_mes;
        const anio = propuestaData.target_anio;

        const detalle = [];
        $('#tbodyGestion .sel-turno-propuesto').each(function () {
            const idTecnico = $(this).data('tecnico');
            const idTurno   = +$(this).val();
            // Calcular meses_consecutivos real según turno elegido vs turno actual
            const pOrig = propuestaData.propuesta.find(p => +p.id_tecnico_mon === +idTecnico);
            let meses = 1;
            if (pOrig && pOrig.id_turno_actual === idTurno) {
                meses = (+pOrig.meses_consecutivos || 0) + 1;
            }
            detalle.push({ id_tecnico_mon: idTecnico, id_turno: idTurno, meses_consecutivos: meses });
        });

        if (!detalle.length) {
            Swal.fire('Atención', 'No hay técnicos en la propuesta.', 'warning');
            return;
        }

        const payload = {
            accion:  'guardarHorario',
            mes, anio,
            notas:   $('#gestion-notas').val(),
            detalle: JSON.stringify(detalle)
        };

        $.post(CTRL, payload, function (r) {
            if (!r.ok) { Swal.fire('Error', r.mensaje, 'error'); return; }

            if (activar) {
                $.post(CTRL, { accion: 'activarHorario', id_horario_mes: r.data.id_horario_mes }, function (ra) {
                    bootstrap.Modal.getInstance(document.getElementById('modalGestion')).hide();
                    if (!ra.ok) { Swal.fire('Error al activar', ra.mensaje, 'error'); return; }
                    Swal.fire({ icon: 'success', title: '¡Listo!', text: ra.mensaje, timer: 2000, showConfirmButton: false });
                    cargarHorario(mes, anio);
                    viewMes = mes; viewAnio = anio;
                }, 'json');
            } else {
                bootstrap.Modal.getInstance(document.getElementById('modalGestion')).hide();
                Swal.fire({ icon: 'success', title: 'Borrador guardado', text: r.mensaje, timer: 2000, showConfirmButton: false });
            }
        }, 'json');
    }

    // ══════════════════════════════════════════════════════════
    // EXCEPCIONES
    // ══════════════════════════════════════════════════════════
    function cargarExcepciones() {
        if (!horarioActual) {
            $('#exc-lista').html('<p class="text-muted text-center">No hay horario activo visible.</p>');
            return;
        }
        const id = horarioActual.id_horario_mes;
        $('#exc-loading').removeClass('d-none');
        $('#exc-lista').html('');
        $('#exc-empty').addClass('d-none');

        $.post(CTRL, { accion: 'listarExcepciones', id_horario_mes: id }, function (r) {
            $('#exc-loading').addClass('d-none');
            if (!r.ok || !r.data.length) {
                $('#exc-empty').removeClass('d-none');
                actualizarBadgeExcepciones(0);
                return;
            }
            renderExcepciones(r.data, id);
            const pendientes = r.data.filter(e => e.estado === 'pendiente').length;
            actualizarBadgeExcepciones(pendientes);
            $('#exc-mes-titulo').text('Excepciones — ' + MESES[(+horarioActual.mes)-1] + ' ' + horarioActual.anio);
        }, 'json');
    }

    function renderExcepciones(list, id_horario_mes) {
        let html = '';
        list.forEach(function (e) {
            const resuelta = e.estado === 'resuelta';
            const tipoLabel = {
                feriado:         '<span class="badge bg-warning text-dark">Feriado</span>',
                urgencia_sabado: '<span class="badge bg-info text-dark">Urgencia Sáb</span>',
                cambio_turno:    '<span class="badge bg-secondary">Cambio Turno</span>',
                otro:            '<span class="badge bg-light text-dark border">Otro</span>'
            };
            const resLabels = {
                doble_pago:   '<span class="text-success fw-semibold"><i class="bi bi-cash-coin me-1"></i>Pago doble</span>',
                compensacion: '<span class="text-primary fw-semibold"><i class="bi bi-calendar-check me-1"></i>Compensación</span>',
                turno_12h:    '<span style="color:#6f42c1" class="fw-semibold"><i class="bi bi-arrow-left-right me-1"></i>Turno 12h</span>',
                sin_accion:   '<span class="text-muted"><i class="bi bi-dash me-1"></i>Sin acción</span>'
            };

            let detTecnicos = '';
            if (e.tecnicos && e.tecnicos.length) {
                detTecnicos = '<div class="mt-1 d-flex flex-wrap gap-1">';
                e.tecnicos.forEach(function (tec) {
                    const rolMap = { trabaja: 'success', descansa: 'secondary', cubre: 'info' };
                    const bg = rolMap[tec.rol] || 'light';
                    const turnoExtra = tec.turno_especial ? ` (${escH(tec.turno_especial)})` : '';
                    detTecnicos += `<span class="badge bg-${bg}">${escH(tec.nombre)}${turnoExtra}</span>`;
                });
                detTecnicos += '</div>';
            }

            let accionBtn = '';
            if (!resuelta) {
                accionBtn = `<button class="btn btn-sm btn-warning btn-resolver-exc"
                                data-id="${e.id_excepcion}"
                                data-fecha="${e.fecha}"
                                data-desc="${escAttr(e.descripcion)}">
                             <i class="bi bi-tools me-1"></i>Resolver
                             </button>`;
            }

            html += `<div class="exc-card ${resuelta ? 'resuelta' : ''} border p-3 mb-2 rounded">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <strong>${fmtFechaDisplay(e.fecha)}</strong>
                            ${tipoLabel[e.tipo] || ''}
                        </div>
                        <p class="mb-0 text-muted small">${escH(e.descripcion || '')}</p>
                        ${detTecnicos}
                        ${resuelta ? `<div class="mt-1">${resLabels[e.resolucion] || ''}</div>` : ''}
                        ${e.notas ? `<p class="mt-1 mb-0 small text-muted fst-italic">${escH(e.notas)}</p>` : ''}
                    </div>
                    <div>${accionBtn}</div>
                </div>
            </div>`;
        });
        $('#exc-lista').html(html);
    }

    function bindExcepciones() {
        // Resolver desde calendario o lista de excepciones
        $(document).on('click', '.btn-resolver-exc', function () {
            const id     = $(this).data('id');
            const fecha  = $(this).data('fecha');
            const desc   = $(this).data('desc');
            abrirModalResolucion(id || null, fecha, desc, 'feriado');
        });

        // Cobertura de incapacidad
        $(document).on('click', '.btn-cubrir-inc', function () {
            const idExc      = $(this).data('id');
            const fecha      = $(this).data('fecha');
            const idAusente  = +$(this).data('tecausente');
            const tecs       = horarioActual ? horarioActual.tecnicos : [];
            const tecAusente = tecs.find(t => +t.id_tecnico_mon === idAusente);

            $('#cob-id-excepcion').val(idExc);
            $('#cob-id-tecausente').val(idAusente);
            $('#cob-fecha-titulo').text(fmtFechaDisplay(fecha));
            $('#cob-nombre-ausente').text(tecAusente ? tecAusente.nombre_tecnico : '—');
            $('#cob-turno-ausente').text(tecAusente ? tecAusente.nombre_turno : '—');
            $('#cob-notas').val('');

            // Pre-llenar horario con el turno normal del ausente
            if (tecAusente) {
                $('#cob-horario').val(fmtHora(tecAusente.hora_inicio) + ' - ' + fmtHora(tecAusente.hora_fin));
            } else {
                $('#cob-horario').val('');
            }

            // Selector de técnico que cubre (todos menos el ausente)
            let opts = '<option value="">— Selecciona —</option>';
            tecs.forEach(t => {
                if (+t.id_tecnico_mon !== idAusente) {
                    opts += `<option value="${t.id_tecnico_mon}">${escH(t.nombre_tecnico)}</option>`;
                }
            });
            $('#cob-tecnico-cubre').html(opts);

            new bootstrap.Modal(document.getElementById('modalCoberturaInc')).show();
        });

        $('#btnConfirmarCobertura').on('click', function () {
            const idExc    = $('#cob-id-excepcion').val();
            const idAusente = +$('#cob-id-tecausente').val();
            const idCubre  = +$('#cob-tecnico-cubre').val();
            const horario  = $('#cob-horario').val().trim();
            const notas    = $('#cob-notas').val().trim();

            if (!idCubre)  { Swal.fire('Atención', 'Selecciona quién cubre el turno.', 'warning'); return; }
            if (!horario)  { Swal.fire('Atención', 'Indica el horario de cobertura.', 'warning'); return; }

            const tecnicoRoles = [
                { id_tecnico_mon: idAusente, rol: 'ausente',  turno_especial: null },
                { id_tecnico_mon: idCubre,   rol: 'cubre',    turno_especial: horario }
            ];

            $.post(CTRL, {
                accion:        'resolverExcepcion',
                id_excepcion:  idExc,
                resolucion:    'cobertura',
                notas,
                tecnico_roles: JSON.stringify(tecnicoRoles)
            }, function (r) {
                bootstrap.Modal.getInstance(document.getElementById('modalCoberturaInc')).hide();
                if (!r.ok) { Swal.fire('Error', r.mensaje, 'error'); return; }
                Swal.fire({ icon: 'success', title: '¡Cobertura asignada!', text: r.mensaje, timer: 1800, showConfirmButton: false });
                cargarHorario(viewMes, viewAnio);
                cargarExcepciones();
            }, 'json');
        });

        // Registrar turno especial en días sin feriado (botón en celda de fecha)
        $(document).on('click', '.btn-nueva-exc-dia', function () {
            const fecha = $(this).data('fecha');
            abrirModalResolucion('', fecha, 'Turno especial / cambio de horario', 'otro');
        });

        // Modal de incapacidad — crear nueva
        $('#btnNuevaIncapacidad').on('click', function () {
            if (!horarioActual) { Swal.fire('Atención', 'Carga un horario primero.', 'warning'); return; }
            const tecs = horarioActual.tecnicos || [];
            let opts = '<option value="">— Selecciona técnico —</option>';
            tecs.forEach(t => { opts += `<option value="${t.id_tecnico_mon}">${escH(t.nombre_tecnico)}</option>`; });
            $('#inc-tecnico').html(opts);
            $('#inc-fecha-ini, #inc-fecha-fin, #inc-notas').val('');
            $('#inc-modo').val('crear');
            $('#inc-referencia-editar, #inc-id-excepcion-editar').val('');
            $('#inc-modal-titulo').text('Registrar incapacidad');
            $('#inc-btn-texto').text('Registrar');
            new bootstrap.Modal(document.getElementById('modalIncapacidad')).show();
        });

        $('#btnConfirmarIncapacidad').on('click', function () {
            const idTec  = $('#inc-tecnico').val();
            const fechaI = $('#inc-fecha-ini').val();
            const fechaF = $('#inc-fecha-fin').val();
            const notas  = $('#inc-notas').val().trim();
            const modo   = $('#inc-modo').val();

            if (!idTec)             { Swal.fire('Atención', 'Selecciona el técnico ausente.', 'warning'); return; }
            if (!fechaI || !fechaF) { Swal.fire('Atención', 'Ingresa el rango de fechas.', 'warning');   return; }
            if (fechaI > fechaF)    { Swal.fire('Atención', 'La fecha inicio debe ser anterior o igual a la fecha fin.', 'warning'); return; }

            const crearNueva = function () {
                $.post(CTRL, {
                    accion:         'crearIncapacidad',
                    id_horario_mes: horarioActual.id_horario_mes,
                    id_tecnico_mon: idTec,
                    fecha_inicio:   fechaI,
                    fecha_fin:      fechaF,
                    descripcion:    notas
                }, function (r) {
                    bootstrap.Modal.getInstance(document.getElementById('modalIncapacidad')).hide();
                    $('#inc-modo').val('crear');
                    $('#inc-referencia-editar, #inc-id-excepcion-editar').val('');
                    $('#inc-modal-titulo').text('Registrar incapacidad');
                    $('#inc-btn-texto').text('Registrar');
                    if (!r.ok) { Swal.fire('Error', r.mensaje, 'error'); return; }
                    const titulo = modo === 'editar' ? '¡Actualizado!' : '¡Registrado!';
                    Swal.fire({ icon: 'success', title: titulo, text: r.mensaje, timer: 2000, showConfirmButton: false });
                    cargarHorario(viewMes, viewAnio);
                    cargarExcepciones();
                }, 'json');
            };

            if (modo === 'editar') {
                const idExcViejo = $('#inc-id-excepcion-editar').val();
                $.post(CTRL, {
                    accion: 'anularIncapacidad',
                    id_excepcion: idExcViejo,
                    todo_grupo: '1'
                }, function (r) {
                    if (!r.ok) { Swal.fire('Error', 'No se pudo anular el período anterior: ' + r.mensaje, 'error'); return; }
                    crearNueva();
                }, 'json');
            } else {
                crearNueva();
            }
        });

        // Crear excepción manual
        $('#btnAgregarExcepcion').on('click', function () {
            if (!horarioActual) {
                Swal.fire('Atención', 'No hay horario activo cargado.', 'warning');
                return;
            }
            $('#nueva-exc-id-horario').val(horarioActual.id_horario_mes);
            $('#nueva-exc-fecha').val('');
            $('#nueva-exc-tipo').val('urgencia_sabado');
            $('#nueva-exc-desc').val('');
            new bootstrap.Modal(document.getElementById('modalNuevaExcepcion')).show();
        });

        $('#btnGuardarNuevaExcepcion').on('click', function () {
            const fecha = $('#nueva-exc-fecha').val();
            if (!fecha) { Swal.fire('Atención', 'La fecha es requerida.', 'warning'); return; }

            $.post(CTRL, {
                accion:          'crearExcepcion',
                id_horario_mes:  $('#nueva-exc-id-horario').val(),
                fecha,
                tipo:            $('#nueva-exc-tipo').val(),
                descripcion:     $('#nueva-exc-desc').val()
            }, function (r) {
                bootstrap.Modal.getInstance(document.getElementById('modalNuevaExcepcion')).hide();
                if (!r.ok) { Swal.fire('Error', r.mensaje, 'error'); return; }
                Swal.fire({ icon: 'success', title: 'Registrado', text: r.mensaje, timer: 1500, showConfirmButton: false });
                cargarExcepciones();
            }, 'json');
        });

        // Gestionar incapacidad (editar / anular)
        $(document).on('click', '.btn-gestionar-inc', function () {
            const idExc      = $(this).data('id');
            const fecha      = $(this).data('fecha');
            const idAusente  = +$(this).data('tecausente');
            const referencia = $(this).data('referencia') || '';

            // Obtener días del grupo desde los datos ya cargados
            const grupoExcs  = referencia
                ? (horarioActual ? horarioActual.excepciones : []).filter(e => e.referencia === referencia)
                : [];
            const nDias      = grupoExcs.length || 1;
            const fechasGrupo = grupoExcs.map(e => e.fecha).sort();
            const fechaIni   = fechasGrupo[0]  || fecha;
            const fechaFin   = fechasGrupo[fechasGrupo.length - 1] || fecha;

            Swal.fire({
                title: 'Gestionar incapacidad',
                html: `Día seleccionado: <strong>${fmtFechaDisplay(fecha)}</strong><br>
                       <small class="text-muted">Período completo: ${nDias} día${nDias > 1 ? 's' : ''} (${fmtFechaDisplay(fechaIni)} – ${fmtFechaDisplay(fechaFin)})</small>`,
                icon: 'question',
                showCancelButton: true,
                showDenyButton: true,
                cancelButtonText: 'Cancelar',
                confirmButtonText: '<i class="bi bi-trash me-1"></i>Anular',
                denyButtonText:    '<i class="bi bi-pencil me-1"></i>Editar',
                confirmButtonColor: '#dc3545',
                denyButtonColor:    '#0d6efd',
            }).then(result => {
                if (result.isDenied) {
                    abrirEditarIncapacidad(idExc, idAusente, fechaIni, fechaFin, grupoExcs);
                } else if (result.isConfirmed) {
                    Swal.fire({
                        title: '¿Qué deseas anular?',
                        icon: 'warning',
                        showCancelButton: true,
                        showDenyButton: true,
                        cancelButtonText: 'Cancelar',
                        confirmButtonText: `Solo el ${fmtFechaDisplay(fecha)}`,
                        denyButtonText:    `Todo el período (${nDias} día${nDias > 1 ? 's' : ''})`,
                        confirmButtonColor: '#fd7e14',
                        denyButtonColor:    '#dc3545',
                    }).then(r2 => {
                        if (r2.isConfirmed) anularDia(idExc, false);
                        else if (r2.isDenied) anularDia(idExc, true);
                    });
                }
            });
        });

        // Opción de resolución: click en card
        $(document).on('click', '.opcion-resolucion', function () {
            $(this).find('input[type="radio"]').prop('checked', true);
            $('.opcion-resolucion').removeClass('selected');
            $(this).addClass('selected');
        });

        $('#btnConfirmarExcepcion').on('click', confirmarResolucion);
    }

    function abrirModalResolucion(idExcepcion, fecha, descripcion, tipo) {
        $('#exc-id-excepcion').val(idExcepcion || '');
        $('#exc-tipo-hidden').val(tipo || 'feriado');
        $('#exc-fecha-titulo').text(fmtFechaDisplay(fecha));
        $('#exc-descripcion-modal').text(descripcion || '');
        $('input[name="resolucion"]').prop('checked', false);
        $('.opcion-resolucion').removeClass('selected');
        $('#exc-notas').val('');

        // Técnicos que NO descansan ese día (informativo)
        const tecs   = horarioActual ? horarioActual.tecnicos : [];
        const diaSem = fecha ? new Date(fecha + 'T12:00:00').getDay() : -1;
        const afectados = tecs.filter(t => +t.dia_descanso !== diaSem).map(t => t.nombre_tecnico);
        $('#exc-tecnicos-afectados').text(
            afectados.length
                ? 'Trabajan ese día: ' + afectados.join(', ')
                : 'Todos descansan ese día (sin impacto).'
        );

        new bootstrap.Modal(document.getElementById('modalExcepcion')).show();
    }

    function confirmarResolucion() {
        const resolucion = $('input[name="resolucion"]:checked').val();
        if (!resolucion) {
            Swal.fire('Atención', 'Selecciona una resolución.', 'warning');
            return;
        }

        let idExcepcion = $('#exc-id-excepcion').val();
        const notas     = $('#exc-notas').val();

        // Si no existe la excepción aún, crearla primero
        const crearYResolver = function (excId) {
            const tecnicoRoles = [];

            $.post(CTRL, {
                accion:         'resolverExcepcion',
                id_excepcion:   excId,
                resolucion,
                notas,
                tecnico_roles:  JSON.stringify(tecnicoRoles)
            }, function (r) {
                bootstrap.Modal.getInstance(document.getElementById('modalExcepcion')).hide();
                if (!r.ok) { Swal.fire('Error', r.mensaje, 'error'); return; }
                Swal.fire({ icon: 'success', title: 'Resuelta', text: r.mensaje, timer: 1800, showConfirmButton: false });
                cargarHorario(viewMes, viewAnio);
                cargarExcepciones();
            }, 'json');
        };

        if (!idExcepcion && horarioActual) {
            const fecha = ($('#exc-fecha-titulo').text() || '').trim();
            $.post(CTRL, {
                accion:         'crearExcepcion',
                id_horario_mes: horarioActual.id_horario_mes,
                fecha:          parseFechaDisplay(fecha),
                tipo:           $('#exc-tipo-hidden').val() || 'feriado',
                descripcion:    $('#exc-descripcion-modal').text()
            }, function (r) {
                if (!r.ok) { Swal.fire('Error', r.mensaje, 'error'); return; }
                crearYResolver(r.data.id_excepcion);
            }, 'json');
        } else {
            crearYResolver(idExcepcion);
        }
    }

    function anularDia(idExc, todoGrupo) {
        $.post(CTRL, {
            accion:        'anularIncapacidad',
            id_excepcion:  idExc,
            todo_grupo:    todoGrupo ? '1' : '0'
        }, function (r) {
            if (!r.ok) { Swal.fire('Error', r.mensaje, 'error'); return; }
            Swal.fire({ icon: 'success', title: '¡Anulado!', text: r.mensaje, timer: 1800, showConfirmButton: false });
            cargarHorario(viewMes, viewAnio);
            cargarExcepciones();
        }, 'json');
    }

    function abrirEditarIncapacidad(idExc, idAusente, fechaIni, fechaFin, grupoExcs) {
        const tecs = horarioActual ? horarioActual.tecnicos : [];
        let opts = '<option value="">— Selecciona técnico —</option>';
        tecs.forEach(t => {
            const sel = +t.id_tecnico_mon === idAusente ? ' selected' : '';
            opts += `<option value="${t.id_tecnico_mon}"${sel}>${escH(t.nombre_tecnico)}</option>`;
        });
        $('#inc-tecnico').html(opts);
        $('#inc-fecha-ini').val(fechaIni);
        $('#inc-fecha-fin').val(fechaFin);
        const desc = grupoExcs.length ? (grupoExcs[0].descripcion || '') : '';
        $('#inc-notas').val(desc);

        // Modo edición
        const refExc = grupoExcs.length ? (grupoExcs[0].referencia || '') : '';
        $('#inc-modo').val('editar');
        $('#inc-referencia-editar').val(refExc);
        $('#inc-id-excepcion-editar').val(idExc);
        $('#inc-modal-titulo').text('Editar incapacidad');
        $('#inc-btn-texto').text('Guardar cambios');

        new bootstrap.Modal(document.getElementById('modalIncapacidad')).show();
    }

    function actualizarBadgeExcepciones(n) {
        const badge = $('#badgeExcepciones');
        badge.text(n).toggleClass('d-none', n === 0);
    }

    // ══════════════════════════════════════════════════════════
    // CONFIGURACIÓN
    // ══════════════════════════════════════════════════════════
    function bindConfiguracion() {
        $('#btnAgregarTecnico').on('click', function () {
            $.post(CTRL, { accion: 'listarUsuariosDisponibles' }, function (r) {
                let opts = '<option value="">— Selecciona usuario —</option>';
                (r.data || []).forEach(u => {
                    opts += `<option value="${u.id_usuario}">${escH(u.nombre)} (${escH(u.username)})</option>`;
                });
                $('#sel-nuevo-tecnico').html(opts);
            }, 'json');
            new bootstrap.Modal(document.getElementById('modalAgregarTecnico')).show();
        });

        $('#btnConfirmarAgregarTecnico').on('click', function () {
            const idU = +$('#sel-nuevo-tecnico').val();
            if (!idU) { Swal.fire('Atención', 'Selecciona un usuario.', 'warning'); return; }
            $.post(CTRL, { accion: 'agregarTecnico', id_usuario: idU }, function (r) {
                bootstrap.Modal.getInstance(document.getElementById('modalAgregarTecnico')).hide();
                if (!r.ok) { Swal.fire('Error', r.mensaje, 'error'); return; }
                Swal.fire({ icon: 'success', title: '¡Listo!', text: r.mensaje, timer: 1800, showConfirmButton: false });
                cargarTecnicosConfig();
            }, 'json');
        });

        $(document).on('click', '.btn-toggle-tecnico', function () {
            const id = +$(this).data('id');
            Swal.fire({
                title: '¿Cambiar estado?',
                icon: 'question', showCancelButton: true,
                confirmButtonText: 'Sí', cancelButtonText: 'No'
            }).then(r => {
                if (!r.isConfirmed) return;
                $.post(CTRL, { accion: 'toggleTecnico', id_tecnico_mon: id }, function (res) {
                    if (!res.ok) { Swal.fire('Error', res.mensaje, 'error'); return; }
                    cargarTecnicosConfig();
                }, 'json');
            });
        });

        // Feriados
        $('#btnAgregarFeriado').on('click', function () {
            $('#feriado-id').val('');
            $('#feriado-fecha').val('').prop('disabled', false);
            $('#feriado-nombre').val('');
            $('#feriado-tipo').val('nacional');
            $('#feriado-modal-titulo').html('<i class="bi bi-calendar-event me-2"></i>Agregar feriado');
            new bootstrap.Modal(document.getElementById('modalFeriado')).show();
        });

        $(document).on('click', '.btn-editar-feriado', function () {
            const id = $(this).data('id');
            const tr = $(this).closest('tr');
            $('#feriado-id').val(id);
            $('#feriado-fecha').val(tr.data('fecha')).prop('disabled', true);
            $('#feriado-nombre').val(tr.data('nombre'));
            $('#feriado-tipo').val(tr.data('tipo'));
            $('#feriado-modal-titulo').html('<i class="bi bi-pencil me-2"></i>Editar feriado');
            new bootstrap.Modal(document.getElementById('modalFeriado')).show();
        });

        $('#btnGuardarFeriado').on('click', function () {
            const nombre = $('#feriado-nombre').val().trim();
            const fecha  = $('#feriado-fecha').val();
            const tipo   = $('#feriado-tipo').val();
            const id     = $('#feriado-id').val();
            if (!nombre) { Swal.fire('Atención', 'El nombre es requerido.', 'warning'); return; }
            if (!id && !fecha) { Swal.fire('Atención', 'La fecha es requerida.', 'warning'); return; }
            $.post(CTRL, { accion: 'guardarFeriado', id_feriado: id, fecha, nombre, tipo }, function (r) {
                bootstrap.Modal.getInstance(document.getElementById('modalFeriado')).hide();
                if (!r.ok) { Swal.fire('Error', r.mensaje, 'error'); return; }
                Swal.fire({ icon: 'success', title: '¡Listo!', text: r.mensaje, timer: 1500, showConfirmButton: false });
                cargarFeriadosConfig(+$('#selAnioFeriados').val());
            }, 'json');
        });

        $(document).on('click', '.btn-toggle-feriado', function () {
            const id = +$(this).data('id');
            $.post(CTRL, { accion: 'toggleFeriado', id_feriado: id }, function (r) {
                if (!r.ok) { Swal.fire('Error', r.mensaje, 'error'); return; }
                cargarFeriadosConfig(+$('#selAnioFeriados').val());
            }, 'json');
        });

        $('#selAnioFeriados').on('change', function () {
            cargarFeriadosConfig(+$(this).val());
        });
    }

    function cargarConfiguracion() {
        cargarTecnicosConfig();
        cargarFeriadosConfig(+$('#selAnioFeriados').val());
    }

    function cargarTecnicosConfig() {
        $('#cfg-tecnicos-loading').show();
        $('#tablaTecnicos').addClass('d-none');
        $.post(CTRL, { accion: 'listarTecnicosMon' }, function (r) {
            $('#cfg-tecnicos-loading').hide();
            if (!r.ok) return;
            let rows = '';
            (r.data || []).forEach(function (t) {
                const activo = +t.activo;
                rows += `<tr>
                    <td>${escH(t.nombre)}</td>
                    <td class="text-muted small">${escH(t.username)}</td>
                    <td class="text-muted small">${escH(t.fecha_asignacion)}</td>
                    <td class="text-center">
                        <span class="badge ${activo ? 'bg-success' : 'bg-secondary'}">
                            ${activo ? 'Activo' : 'Inactivo'}
                        </span>
                    </td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-secondary btn-toggle-tecnico py-1"
                                data-id="${t.id_tecnico_mon}"
                                title="${activo ? 'Desactivar' : 'Activar'}">
                            <i class="bi bi-toggle-${activo ? 'on text-success' : 'off'}"></i>
                        </button>
                    </td>
                </tr>`;
            });
            $('#tablaTecnicos tbody').html(rows || '<tr><td colspan="5" class="text-center text-muted">Sin técnicos.</td></tr>');
            $('#tablaTecnicos').removeClass('d-none');
        }, 'json');
    }

    function cargarFeriadosConfig(anio) {
        $('#cfg-feriados-loading').show();
        $('#tablaFeriados').addClass('d-none');
        $.post(CTRL, { accion: 'listarFeriados', anio }, function (r) {
            $('#cfg-feriados-loading').hide();
            if (!r.ok) return;
            let rows = '';
            (r.data || []).forEach(function (f) {
                const activo = +f.activo;
                rows += `<tr data-fecha="${f.fecha}" data-nombre="${escAttr(f.nombre)}" data-tipo="${f.tipo}">
                    <td>${fmtFechaDisplay(f.fecha)}</td>
                    <td>${escH(f.nombre)}</td>
                    <td><span class="badge ${f.tipo === 'nacional' ? 'bg-primary' : 'bg-secondary'}">${escH(f.tipo)}</span></td>
                    <td class="text-center">
                        <span class="badge ${activo ? 'bg-success' : 'bg-secondary'}">${activo ? 'Sí' : 'No'}</span>
                    </td>
                    <td class="text-center">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-primary dropdown-toggle py-1"
                                    type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                <li>
                                    <button class="dropdown-item btn-editar-feriado" data-id="${f.id_feriado}">
                                        <i class="bi bi-pencil me-2 text-warning"></i>Editar
                                    </button>
                                </li>
                                <li>
                                    <button class="dropdown-item btn-toggle-feriado" data-id="${f.id_feriado}">
                                        <i class="bi bi-toggle-${activo ? 'off' : 'on'} me-2 text-secondary"></i>
                                        ${activo ? 'Desactivar' : 'Activar'}
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </td>
                </tr>`;
            });
            $('#tablaFeriados tbody').html(rows || '<tr><td colspan="5" class="text-center text-muted">Sin feriados para este año.</td></tr>');
            $('#tablaFeriados').removeClass('d-none');
        }, 'json');
    }

    // ══════════════════════════════════════════════════════════
    // HISTORIAL
    // ══════════════════════════════════════════════════════════
    function bindHistorial() {
        $('#btnVerHistorial').on('click', function () {
            $('#hist-loading').show();
            $('#tablaHistorial').addClass('d-none');
            const modal = new bootstrap.Modal(document.getElementById('modalHistorial'));
            modal.show();

            $.post(CTRL, { accion: 'listarHistorial' }, function (r) {
                $('#hist-loading').hide();
                if (!r.ok) return;
                const estadoMap = {
                    borrador:  '<span class="badge bg-secondary">Borrador</span>',
                    activo:    '<span class="badge bg-success">Activo</span>',
                    archivado: '<span class="badge bg-light text-dark border">Archivado</span>'
                };
                let rows = '';
                (r.data || []).forEach(function (h) {
                    rows += `<tr>
                        <td class="fw-semibold">${MESES[h.mes-1]} ${h.anio}</td>
                        <td class="text-center">${h.total_tecnicos}</td>
                        <td class="text-center">${estadoMap[h.estado] || h.estado}</td>
                        <td class="text-muted small">${h.fecha_activacion || '—'}</td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary py-1 btn-ver-mes"
                                    data-mes="${h.mes}" data-anio="${h.anio}">
                                <i class="bi bi-eye"></i>
                            </button>
                        </td>
                    </tr>`;
                });
                $('#tablaHistorial tbody').html(rows || '<tr><td colspan="5" class="text-center text-muted">Sin historial.</td></tr>');
                $('#tablaHistorial').removeClass('d-none');
            }, 'json');
        });

        $(document).on('click', '.btn-ver-mes', function () {
            const mes  = +$(this).data('mes');
            const anio = +$(this).data('anio');
            bootstrap.Modal.getInstance(document.getElementById('modalHistorial')).hide();
            viewMes = mes; viewAnio = anio;
            cargarHorario(mes, anio);
            // Cambiar a pestaña Horario
            bootstrap.Tab.getOrCreateInstance(
                document.querySelector('#tabsHorario button[data-bs-target="#tab-horario"]')
            ).show();
        });
    }

    // ══════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════
    function fmtHora(hhmm) {
        if (!hhmm) return '';
        const parts = hhmm.split(':');
        const h = parseInt(parts[0], 10);
        const m = parseInt(parts[1] || '0', 10);
        const ampm = h < 12 ? 'am' : 'pm';
        const h12  = h % 12 || 12;
        return m === 0 ? `${h12}${ampm}` : `${h12}:${String(m).padStart(2,'0')}${ampm}`;
    }

    function fmtFechaDisplay(iso) {
        if (!iso) return '';
        const [y, m, d] = iso.split('-');
        return `${+d}/${+m}/${y}`;
    }

    function parseFechaDisplay(display) {
        // Intenta extraer la fecha ISO del titulo del modal (viene como fmtFechaDisplay)
        // Fallback: usar el atributo data-fecha original si disponible
        if (!display) return '';
        const p = display.split('/');
        if (p.length === 3) return `${p[2]}-${String(p[1]).padStart(2,'0')}-${String(p[0]).padStart(2,'0')}`;
        return display;
    }

    function parsearHoraNum(texto) {
        const m = texto.trim().toLowerCase().match(/^(\d+)(am|pm)$/);
        if (!m) return null;
        let h = parseInt(m[1]);
        if (m[2] === 'pm' && h !== 12) h += 12;
        if (m[2] === 'am' && h === 12) h = 0;
        return h;
    }

    // Valida que dos turnos escritos como "Xam - Ypm" cubran exactamente 24h continuas
    function validarCobertura24h(turno1, turno2) {
        const p1 = turno1.split('-').map(s => s.trim());
        const p2 = turno2.split('-').map(s => s.trim());
        if (p1.length < 2 || p2.length < 2)
            return { ok: false, msg: 'Formato inválido. Usa el formato "6am - 6pm".' };
        const ini1 = parsearHoraNum(p1[0]), fin1 = parsearHoraNum(p1[1]);
        const ini2 = parsearHoraNum(p2[0]), fin2 = parsearHoraNum(p2[1]);
        if (ini1 === null || fin1 === null || ini2 === null || fin2 === null)
            return { ok: false, msg: 'No se pudieron leer las horas. Usa formato como "6am", "12pm", "10pm".' };
        const dur1 = fin1 <= ini1 ? 24 - ini1 + fin1 : fin1 - ini1;
        const dur2 = fin2 <= ini2 ? 24 - ini2 + fin2 : fin2 - ini2;
        if (dur1 + dur2 !== 24)
            return { ok: false, msg: `Los turnos suman ${dur1 + dur2}h en total. Deben sumar exactamente 24h.` };
        if (fin1 !== ini2 && fin2 !== ini1)
            return { ok: false, msg: 'Los turnos no son contiguos. El fin de uno debe coincidir con el inicio del otro.' };
        return { ok: true };
    }

    function escH(str) {
        if (!str && str !== 0) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function escAttr(str) {
        if (!str) return '';
        return String(str).replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }

})(jQuery);
