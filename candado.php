<?php
// Página de descarga del Candado de Operario para las PCs de planta.
// Pública dentro de la red a propósito: las PCs de máquina no tienen login,
// y el instalador no contiene secretos (el token se escribe al instalar).

// El instalador lleva la version en el nombre (Setup-CandadoOperario-1.2.1.exe): se ofrece la mas nueva
$archivos = glob(__DIR__ . '/descargas/Setup-CandadoOperario-*.exe') ?: [];
usort($archivos, function ($a, $b) {
    $va = preg_replace('/^.*Setup-CandadoOperario-(.+)\.exe$/', '$1', $a);
    $vb = preg_replace('/^.*Setup-CandadoOperario-(.+)\.exe$/', '$1', $b);
    return version_compare($vb, $va);
});
$archivo  = $archivos[0] ?? '';
$existe   = $archivo !== '';
$nombre   = $existe ? basename($archivo) : '';
$version  = $existe ? preg_replace('/^Setup-CandadoOperario-(.+)\.exe$/', '$1', $nombre) : '';
$tamano   = $existe ? round(filesize($archivo) / 1048576, 1) : 0;
$fecha    = $existe ? date('d/m/Y H:i', filemtime($archivo)) : '';
$sha      = $existe ? hash_file('sha256', $archivo) : '';
$servidor = $_SERVER['HTTP_HOST'] ?? 'servidor';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Candado de Operario — Honducafe</title>
<link rel="icon" href="./assets/img/logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link href="./assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="./assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
<style>
  :root {
    --hc-marca: #620e0f; --hc-marca-hover: #460909; --hc-marca-tinte: #f6ecec;
    --hc-texto: #261a1b; --hc-texto-2: #695a5b; --hc-texto-3: #9a8b8c;
    --hc-borde: #e7dcdd; --hc-fondo: #fbf8f8;
  }
  body { font-family: 'Inter','Segoe UI',system-ui,sans-serif; background: var(--hc-fondo); color: var(--hc-texto); margin: 0; }
  .wrap { max-width: 640px; margin: 0 auto; padding: 2rem 1rem; }
  .brand { display: flex; align-items: center; gap: .6rem; margin-bottom: 1.5rem; }
  .brand .brand-icon { width: 34px; height: 34px; border-radius: 8px; background: var(--hc-marca); color: #fff; display: flex; align-items: center; justify-content: center; }
  .brand .brand-name { font-weight: 600; line-height: 1.2; }
  .brand .brand-sub { display: block; font-size: .7rem; font-weight: 400; color: var(--hc-texto-3); }
  .tarjeta { background: #fff; border: 1px solid var(--hc-borde); border-radius: 12px; padding: 1.5rem; margin-bottom: 1rem; }
  h1 { font-size: 1.15rem; font-weight: 600; margin: 0 0 .25rem; }
  .sub { font-size: .85rem; color: var(--hc-texto-3); margin: 0 0 1.25rem; }
  .btn-desc { display: flex; align-items: center; justify-content: center; gap: .6rem; width: 100%; height: 52px; border-radius: 10px;
              background: var(--hc-marca); color: #fff; font-weight: 600; text-decoration: none; }
  .btn-desc:hover { background: var(--hc-marca-hover); color: #fff; }
  .meta { font-size: .78rem; color: var(--hc-texto-3); margin-top: .75rem; text-align: center; }
  .meta code { font-size: .7rem; word-break: break-all; }
  ol { padding-left: 1.1rem; margin: 0; }
  ol li { margin-bottom: .55rem; font-size: .88rem; color: var(--hc-texto-2); }
  ol li strong, .dato { color: var(--hc-texto); }
  .dato { font-family: Consolas, monospace; background: var(--hc-marca-tinte); padding: .05rem .4rem; border-radius: 5px; }
  h2 { font-size: .8rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--hc-texto-3); margin: 0 0 .9rem; }
  .aviso { font-size: .82rem; color: var(--hc-texto-2); background: #fff8e6; border: 1px solid #f0dda6; border-radius: 8px; padding: .7rem .9rem; }
</style>
</head>
<body>
<div class="wrap">

  <div class="brand">
    <img src="./assets/img/logo-honducafe.png" alt="Honducafe" style="height:84px">
    <span><span class="brand-name">Candado de Operario</span><span class="brand-sub">Instalador para PCs de planta</span></span>
  </div>

  <div class="tarjeta">
    <h1>Instalador para la PC de la máquina</h1>
    <p class="sub">Bloquea la pantalla de Delvis y registra qué operario trabaja en cada máquina.</p>

    <?php if ($existe): ?>
      <a class="btn-desc" href="./descargas/<?= rawurlencode($nombre) ?>" download>
        <i class="bi bi-download"></i> Descargar <?= htmlspecialchars($nombre) ?>
      </a>
      <div class="meta">
        Versión <?= htmlspecialchars($version) ?> · <?= $tamano ?> MB · generado el <?= htmlspecialchars($fecha) ?><br>
        SHA-256: <code><?= htmlspecialchars($sha) ?></code>
      </div>
    <?php else: ?>
      <div class="aviso"><i class="bi bi-exclamation-triangle me-1"></i>
        El instalador todavía no está publicado en este servidor. Avisa al administrador.</div>
    <?php endif; ?>
  </div>

  <div class="tarjeta">
    <h2>Cómo instalarlo</h2>
    <ol>
      <li>Descarga el archivo y ábrelo con doble clic. Acepta el permiso de administrador.</li>
      <li>Servidor: <span class="dato"><?= htmlspecialchars($servidor) ?></span></li>
      <li>Estación: el nombre único de la máquina, por ejemplo <span class="dato">Evolution-Linea1</span>. No lo repitas en otra PC.</li>
      <li>Token: <strong>pídeselo al administrador</strong>. No aparece en esta página.</li>
      <li>Al terminar, el candado se abre solo y arranca con Windows en cada inicio.</li>
    </ol>
  </div>

  <div class="aviso">
    <strong>Si Windows o el navegador advierten algo:</strong> el instalador no está firmado digitalmente.
    En Edge elige <em>Conservar</em>; en la ventana azul de SmartScreen, <em>Más información → Ejecutar de todas formas</em>.
    Puedes comparar el SHA-256 de arriba con el del archivo descargado si quieres estar seguro.
  </div>

</div>
</body>
</html>
