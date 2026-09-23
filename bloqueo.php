<?php
// Candado de operario — kiosco de planta.
// A propósito fuera del sistema de login: esta pantalla vive en la PC
// de cada máquina y corre sola, en pantalla completa, todo el turno.
// Identifica al operario por PIN (ver modulo Usuarios) y dice qué
// operario está activo en esta estación llamando a kioscoController.php.

$estacion = trim($_GET['estacion'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Candado de Turno — Honducafe</title>
<link rel="icon" href="./assets/img/logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="./assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="./assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
<style>
  :root {
    --hc-verde:       #156b45;
    --hc-verde-hover: #0f5434;
    --hc-verde-tinte: #e9f3ee;
    --hc-texto:       #1c2128;
    --hc-texto-2:     #57606a;
    --hc-texto-3:     #8a919c;
    --hc-borde:       #e4e7ec;
    --hc-fondo:       #f6f7f9;
  }
  html, body {
    height: 100%;
    margin: 0;
    font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
    background: var(--hc-fondo);
    color: var(--hc-texto);
    overflow: hidden;
    -webkit-user-select: none;
    user-select: none;
  }
  .kiosk {
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
    box-sizing: border-box;
  }
  .kiosk-top {
    position: fixed;
    top: 0; left: 0; right: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.5rem;
  }
  .kiosk-brand { display: flex; align-items: center; gap: .5rem; color: var(--hc-texto-2); font-weight: 600; font-size: .85rem; }
  .kiosk-brand .bi { color: var(--hc-verde); }
  .kiosk-estacion {
    font-size: .75rem;
    font-weight: 600;
    color: var(--hc-verde);
    background: var(--hc-verde-tinte);
    padding: .3rem .7rem;
    border-radius: 20px;
  }
  #btnFullscreen {
    border: none; background: none; color: var(--hc-texto-3);
    font-size: 1.1rem; cursor: pointer; padding: .3rem .5rem;
  }
  #relojKiosk { font-size: .8rem; color: var(--hc-texto-3); font-variant-numeric: tabular-nums; margin-right: .75rem; }

  /* ── Pantalla bloqueada: teclado ── */
  .keypad-card {
    width: 100%;
    max-width: 340px;
    text-align: center;
  }
  .keypad-card .bi-lock-fill {
    font-size: 2.2rem;
    color: var(--hc-verde);
    background: var(--hc-verde-tinte);
    width: 76px; height: 76px;
    border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    margin-bottom: 1.1rem;
  }
  .keypad-card h1 { font-size: 1.15rem; font-weight: 600; margin: 0 0 .25rem; }
  .keypad-card .subt { font-size: .82rem; color: var(--hc-texto-3); margin: 0 0 1.4rem; }

  .pin-dots { display: flex; justify-content: center; gap: .6rem; margin-bottom: 1.6rem; height: 20px; }
  .pin-dots .dot {
    width: 16px; height: 16px; border-radius: 50%;
    border: 2px solid var(--hc-borde);
    transition: background .1s, border-color .1s;
  }
  .pin-dots .dot.filled { background: var(--hc-verde); border-color: var(--hc-verde); }
  .pin-dots.shake { animation: shake .35s; }
  @keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-8px); }
    75% { transform: translateX(8px); }
  }

  .keypad-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: .7rem;
  }
  .keypad-grid button {
    height: 68px;
    border-radius: 14px;
    border: 1px solid var(--hc-borde);
    background: #fff;
    font-size: 1.4rem;
    font-weight: 600;
    color: var(--hc-texto);
    cursor: pointer;
    transition: background .1s, transform .05s;
  }
  .keypad-grid button:active { transform: scale(.94); background: var(--hc-verde-tinte); }
  .keypad-grid button.tecla-entrar { background: var(--hc-verde); color: #fff; border-color: var(--hc-verde); font-size: 1.6rem; }
  .keypad-grid button.tecla-entrar:active { background: var(--hc-verde-hover); }
  .keypad-grid button.tecla-borrar { font-size: 1.2rem; color: var(--hc-texto-2); }

  #errorPin {
    color: #b32d2d;
    font-size: .82rem;
    font-weight: 600;
    margin-top: 1rem;
    min-height: 1.2em;
  }

  /* ── Pantalla activa: turno en curso ── */
  .activo-card { width: 100%; max-width: 400px; text-align: center; }
  .activo-avatar {
    width: 96px; height: 96px; border-radius: 50%;
    margin: 0 auto 1.2rem;
    display: flex; align-items: center; justify-content: center;
    background: var(--hc-verde-tinte); color: var(--hc-verde);
    font-size: 2.1rem; font-weight: 700;
    object-fit: cover;
  }
  .activo-card h1 { font-size: 1.4rem; font-weight: 700; margin: 0 0 .3rem; }
  .activo-card .desde { font-size: .85rem; color: var(--hc-texto-3); margin: 0 0 2rem; }
  .activo-card .desde strong { color: var(--hc-texto-2); font-variant-numeric: tabular-nums; }
  #btnBloquear {
    width: 100%;
    height: 58px;
    border-radius: 14px;
    border: none;
    background: var(--hc-texto);
    color: #fff;
    font-size: 1.05rem;
    font-weight: 600;
    cursor: pointer;
  }
  #btnBloquear:active { transform: scale(.98); }

  .setup-card { max-width: 480px; text-align: left; }

  [hidden] { display: none !important; }
</style>
</head>
<body>

<?php if (!$estacion): ?>

  <div class="kiosk">
    <div class="card shadow-sm border-0 setup-card">
      <div class="card-body p-4">
        <h5><i class="bi bi-exclamation-triangle text-warning me-1"></i> Falta identificar la estación</h5>
        <p class="text-muted small">
          Esta pantalla necesita saber en qué máquina está instalada. Agrega el nombre
          de la estación al final del enlace, por ejemplo:
        </p>
        <code>bloqueo.php?estacion=Evolution-Linea1</code>
        <p class="text-muted small mt-3 mb-0">
          Luego guarda esa dirección completa como página de inicio del navegador
          en la PC de esa máquina.
        </p>
      </div>
    </div>
  </div>

<?php else: ?>

  <div class="kiosk-top">
    <div class="kiosk-brand"><i class="bi bi-box-seam"></i> Honducafe</div>
    <div style="display:flex;align-items:center">
      <span id="relojKiosk"></span>
      <span class="kiosk-estacion"><i class="bi bi-cpu me-1"></i><?= htmlspecialchars($estacion) ?></span>
      <button id="btnFullscreen" title="Pantalla completa"><i class="bi bi-arrows-fullscreen"></i></button>
    </div>
  </div>

  <div class="kiosk">

    <!-- ── Bloqueada: teclado numérico ── -->
    <div id="vistaBloqueada" class="keypad-card">
      <div><i class="bi bi-lock-fill"></i></div>
      <h1>Estación bloqueada</h1>
      <p class="subt">Ingresa tu código para comenzar tu turno</p>

      <div class="pin-dots" id="pinDots"></div>

      <div class="keypad-grid">
        <button data-k="1">1</button><button data-k="2">2</button><button data-k="3">3</button>
        <button data-k="4">4</button><button data-k="5">5</button><button data-k="6">6</button>
        <button data-k="7">7</button><button data-k="8">8</button><button data-k="9">9</button>
        <button class="tecla-borrar" data-k="borrar"><i class="bi bi-backspace"></i></button>
        <button data-k="0">0</button>
        <button class="tecla-entrar" data-k="entrar"><i class="bi bi-check-lg"></i></button>
      </div>

      <div id="errorPin"></div>
    </div>

    <!-- ── Activa: turno en curso ── -->
    <div id="vistaActiva" class="activo-card" hidden>
      <div class="activo-avatar" id="activoAvatar"></div>
      <h1 id="activoNombre"></h1>
      <p class="desde">Trabajando desde <strong id="activoDesde"></strong></p>
      <button id="btnBloquear"><i class="bi bi-lock-fill me-2"></i>Bloquear estación</button>
    </div>

  </div>

<script>
(function () {
  const ESTACION = <?= json_encode($estacion) ?>;
  const CTRL = './modules/Turnos/controllers/kioscoController.php';
  const AUTO_LOCK_MS = 15 * 60 * 1000;

  const vistaBloqueada = document.getElementById('vistaBloqueada');
  const vistaActiva    = document.getElementById('vistaActiva');
  const pinDotsEl      = document.getElementById('pinDots');
  const errorEl        = document.getElementById('errorPin');
  let pin = '';
  let autoLockTimer = null;

  function post(accion, extra) {
    const body = new URLSearchParams(Object.assign({ accion, estacion: ESTACION }, extra || {}));
    return fetch(CTRL, { method: 'POST', body })
      .then(r => r.json())
      .catch(() => ({ ok: false, mensaje: 'No se pudo conectar con el servidor.' }));
  }

  function iniciales(nombre) {
    return (nombre || '').trim().split(/\s+/).slice(0, 2).map(p => p[0].toUpperCase()).join('');
  }

  function renderDots() {
    pinDotsEl.innerHTML = '';
    const len = Math.max(pin.length, 4);
    for (let i = 0; i < len; i++) {
      const d = document.createElement('div');
      d.className = 'dot' + (i < pin.length ? ' filled' : '');
      pinDotsEl.appendChild(d);
    }
  }

  function mostrarBloqueada() {
    clearTimeout(autoLockTimer);
    pin = '';
    renderDots();
    errorEl.textContent = '';
    vistaActiva.hidden = true;
    vistaBloqueada.hidden = false;
  }

  function mostrarActiva(nombre, foto, desde) {
    document.getElementById('activoNombre').textContent = nombre;
    document.getElementById('activoDesde').textContent = desde;
    const av = document.getElementById('activoAvatar');
    if (foto) {
      av.style.backgroundImage = `url(./${foto})`;
      av.style.backgroundSize = 'cover';
      av.textContent = '';
    } else {
      av.style.backgroundImage = '';
      av.textContent = iniciales(nombre);
    }
    vistaBloqueada.hidden = true;
    vistaActiva.hidden = false;
    reiniciarAutoLock();
  }

  function fmtHora(fechaStr) {
    const d = new Date(fechaStr.replace(' ', 'T'));
    if (isNaN(d)) return fechaStr;
    return d.toLocaleTimeString('es-HN', { hour: '2-digit', minute: '2-digit' });
  }

  function reiniciarAutoLock() {
    clearTimeout(autoLockTimer);
    autoLockTimer = setTimeout(function () {
      post('bloquear').then(mostrarBloqueada);
    }, AUTO_LOCK_MS);
  }
  ['mousemove', 'keydown', 'touchstart', 'click'].forEach(ev => {
    document.addEventListener(ev, function () {
      if (!vistaActiva.hidden) reiniciarAutoLock();
    });
  });

  function estadoInicial() {
    post('estado').then(r => {
      if (r.ok && r.data.activa) {
        mostrarActiva(r.data.activa.nombre, r.data.activa.foto, fmtHora(r.data.activa.inicio));
      } else {
        mostrarBloqueada();
      }
    });
  }

  function intentarDesbloquear() {
    errorEl.textContent = '';
    post('desbloquear', { pin: pin }).then(r => {
      if (!r.ok) {
        errorEl.textContent = r.mensaje || 'Código incorrecto.';
        pinDotsEl.classList.add('shake');
        setTimeout(() => pinDotsEl.classList.remove('shake'), 350);
        pin = '';
        renderDots();
        return;
      }
      mostrarActiva(r.data.nombre, r.data.foto, 'ahora mismo');
    });
  }

  document.querySelectorAll('.keypad-grid button').forEach(btn => {
    btn.addEventListener('click', function () {
      const k = this.dataset.k;
      if (k === 'borrar') {
        pin = pin.slice(0, -1);
      } else if (k === 'entrar') {
        if (pin.length >= 4) intentarDesbloquear();
        return;
      } else if (pin.length < 6) {
        pin += k;
      }
      renderDots();
    });
  });

  document.addEventListener('keydown', function (e) {
    if (!vistaBloqueada.hidden) {
      if (/^\d$/.test(e.key) && pin.length < 6) { pin += e.key; renderDots(); }
      else if (e.key === 'Backspace') { pin = pin.slice(0, -1); renderDots(); }
      else if (e.key === 'Enter' && pin.length >= 4) { intentarDesbloquear(); }
    }
  });

  document.getElementById('btnBloquear').addEventListener('click', function () {
    post('bloquear').then(mostrarBloqueada);
  });

  document.getElementById('btnFullscreen').addEventListener('click', function () {
    const el = document.documentElement;
    if (el.requestFullscreen) el.requestFullscreen().catch(() => {});
  });

  function actualizarReloj() {
    document.getElementById('relojKiosk').textContent =
      new Date().toLocaleTimeString('es-HN', { hour: '2-digit', minute: '2-digit' });
  }
  actualizarReloj();
  setInterval(actualizarReloj, 15000);

  estadoInicial();
})();
</script>

<?php endif; ?>

</body>
</html>
