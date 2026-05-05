<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Acceso — Honducafe</title>
  <link rel="icon" href="./assets/img/favicon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="./assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="./assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">

  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Poppins', sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(160deg, #0b3d20 0%, #145c30 40%, #1e8449 75%, #27ae60 100%);
      overflow: hidden;
      position: relative;
    }

    /* ── Círculos decorativos de fondo ── */
    .bg-circle {
      position: fixed;
      border-radius: 50%;
      opacity: .07;
      background: #fff;
      animation: floatCircle linear infinite;
    }
    .bg-circle:nth-child(1) { width: 500px; height: 500px; top: -150px; left: -150px; animation-duration: 20s; }
    .bg-circle:nth-child(2) { width: 300px; height: 300px; top: 60%; right: -80px; animation-duration: 25s; animation-delay: -8s; }
    .bg-circle:nth-child(3) { width: 180px; height: 180px; top: 20%; right: 15%; animation-duration: 18s; animation-delay: -4s; }

    @keyframes floatCircle {
      0%   { transform: translateY(0) rotate(0deg); }
      50%  { transform: translateY(-30px) rotate(180deg); }
      100% { transform: translateY(0) rotate(360deg); }
    }

    /* ── Ondas SVG ── */
    .waves-container {
      position: fixed;
      bottom: 0;
      left: 0;
      width: 100%;
      height: 220px;
      pointer-events: none;
      overflow: hidden;
    }

    .wave {
      position: absolute;
      bottom: 0;
      left: 0;
      width: 200%;
      height: 100%;
    }

    .wave-1 { opacity: .15; animation: waveMove 8s linear infinite; }
    .wave-2 { opacity: .10; animation: waveMove 12s linear infinite reverse; }
    .wave-3 { opacity: .07; animation: waveMove 6s linear infinite; }

    @keyframes waveMove {
      0%   { transform: translateX(0); }
      100% { transform: translateX(-50%); }
    }

    /* ── Card de login ── */
    .login-wrapper {
      position: relative;
      z-index: 10;
      width: 100%;
      max-width: 420px;
      padding: 1rem;
    }

    .login-card {
      background: rgba(255, 255, 255, 0.97);
      border-radius: 24px;
      box-shadow: 0 24px 64px rgba(0, 0, 0, 0.25), 0 4px 16px rgba(0,0,0,.1);
      overflow: hidden;
      animation: slideUp .6s cubic-bezier(.16,1,.3,1) both;
    }

    @keyframes slideUp {
      from { opacity: 0; transform: translateY(32px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ── Cabecera verde de la card ── */
    .login-header {
      background: linear-gradient(135deg, #145c30 0%, #1e8449 100%);
      padding: 2rem 2rem 2.5rem;
      text-align: center;
      position: relative;
    }

    .login-header::after {
      content: '';
      position: absolute;
      bottom: -1px;
      left: 0;
      right: 0;
      height: 32px;
      background: rgba(255,255,255,0.97);
      border-radius: 50% 50% 0 0 / 100% 100% 0 0;
    }

    .login-logo {
      width: 68px;
      height: 68px;
      background: rgba(255,255,255,.15);
      border: 2px solid rgba(255,255,255,.3);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1rem;
      font-size: 2rem;
      color: #fff;
      backdrop-filter: blur(4px);
    }

    .login-header h1 {
      color: #fff;
      font-size: 1.4rem;
      font-weight: 600;
      letter-spacing: .5px;
      margin: 0;
    }

    .login-header p {
      color: rgba(255,255,255,.75);
      font-size: .82rem;
      margin: .25rem 0 0;
    }

    /* ── Cuerpo del formulario ── */
    .login-body {
      padding: 1.5rem 2rem 2rem;
    }

    .form-label {
      font-size: .82rem;
      font-weight: 600;
      color: #374151;
      margin-bottom: .4rem;
    }

    .input-icon-wrap {
      position: relative;
    }

    .input-icon-wrap .bi {
      position: absolute;
      left: .9rem;
      top: 50%;
      transform: translateY(-50%);
      color: #9ca3af;
      font-size: 1rem;
      pointer-events: none;
      transition: color .2s;
    }

    .input-icon-wrap input {
      padding-left: 2.6rem;
      border-radius: 10px;
      border: 1.5px solid #e5e7eb;
      font-size: .9rem;
      height: 46px;
      transition: border-color .2s, box-shadow .2s;
    }

    .input-icon-wrap input:focus {
      border-color: #1e8449;
      box-shadow: 0 0 0 3px rgba(30,132,73,.12);
      outline: none;
    }

    .input-icon-wrap input:focus + .bi,
    .input-icon-wrap:focus-within .bi {
      color: #1e8449;
    }

    /* toggle contraseña */
    .btn-toggle-pwd {
      position: absolute;
      right: .75rem;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: #9ca3af;
      cursor: pointer;
      padding: 0;
      font-size: 1rem;
      transition: color .2s;
      z-index: 2;
    }
    .btn-toggle-pwd:hover { color: #1e8449; }

    /* Mensajes de error */
    .alert-login {
      border-radius: 10px;
      font-size: .84rem;
      padding: .65rem 1rem;
      margin-bottom: 1rem;
      border: none;
      background: #fef2f2;
      color: #dc2626;
      display: flex;
      align-items: center;
      gap: .5rem;
      animation: fadeIn .3s ease;
    }
    @keyframes fadeIn { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:none; } }

    /* Botón principal */
    .btn-login {
      width: 100%;
      height: 48px;
      border-radius: 12px;
      border: none;
      background: linear-gradient(135deg, #145c30 0%, #1e8449 100%);
      color: #fff;
      font-weight: 600;
      font-size: .95rem;
      letter-spacing: .3px;
      cursor: pointer;
      transition: transform .15s, box-shadow .15s, filter .15s;
      box-shadow: 0 4px 16px rgba(30,132,73,.35);
      position: relative;
      overflow: hidden;
    }

    .btn-login::after {
      content: '';
      position: absolute;
      inset: 0;
      background: rgba(255,255,255,0);
      transition: background .15s;
    }

    .btn-login:hover {
      transform: translateY(-1px);
      box-shadow: 0 8px 24px rgba(30,132,73,.45);
      filter: brightness(1.05);
    }

    .btn-login:active {
      transform: translateY(0);
      box-shadow: 0 4px 12px rgba(30,132,73,.3);
    }

    .btn-login .spinner-border { display: none; }
    .btn-login.loading .spinner-border { display: inline-block; }
    .btn-login.loading .btn-text { display: none; }

    /* Pie de la card */
    .login-footer {
      text-align: center;
      font-size: .75rem;
      color: #9ca3af;
      padding: .5rem 2rem 1.5rem;
    }

    /* Divisor */
    .form-divider {
      height: 1px;
      background: #f3f4f6;
      margin: 1.25rem 0;
    }
  </style>
</head>
<body>

  <!-- Círculos flotantes de fondo -->
  <div class="bg-circle"></div>
  <div class="bg-circle"></div>
  <div class="bg-circle"></div>

  <!-- Ondas animadas -->
  <div class="waves-container">
    <!-- Onda 1 -->
    <svg class="wave wave-1" viewBox="0 0 1440 220" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
      <path fill="#ffffff" d="M0,160 C180,200 360,80 540,120 C720,160 900,200 1080,160 C1260,120 1380,80 1440,100 L1440,220 L0,220 Z
                              M1440,160 C1260,200 1080,80 900,120 C720,160 540,200 360,160 C180,120 60,80 0,100 L0,220 L1440,220 Z"/>
    </svg>
    <!-- Onda 2 -->
    <svg class="wave wave-2" viewBox="0 0 1440 220" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
      <path fill="#a7f3d0" d="M0,180 C240,140 480,220 720,180 C960,140 1200,180 1440,160 L1440,220 L0,220 Z
                              M1440,180 C1200,140 960,220 720,180 C480,140 240,180 0,160 L0,220 L1440,220 Z"/>
    </svg>
    <!-- Onda 3 -->
    <svg class="wave wave-3" viewBox="0 0 1440 220" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
      <path fill="#6ee7b7" d="M0,200 C360,160 720,220 1080,190 C1260,175 1380,195 1440,200 L1440,220 L0,220 Z
                              M1440,200 C1080,160 720,220 360,190 C180,175 60,195 0,200 L0,220 L1440,220 Z"/>
    </svg>
  </div>

  <!-- Card de login -->
  <div class="login-wrapper">
    <div class="login-card">

      <!-- Cabecera -->
      <div class="login-header">
        <div class="login-logo">
          <i class="bi bi-shield-lock-fill"></i>
        </div>
        <h1>Bienvenido</h1>
        <p>Ingresa tus credenciales para continuar</p>
      </div>

      <!-- Formulario -->
      <div class="login-body">

        <?php if (!empty($_GET['error'])): ?>
          <div class="alert-login">
            <i class="bi bi-exclamation-circle-fill"></i>
            <?php
              $err = $_GET['error'];
              if ($err === 'invalid')    echo 'Usuario o contraseña incorrectos.';
              elseif ($err === 'inactive') echo 'Tu cuenta está desactivada.';
              else echo htmlspecialchars($err);
            ?>
          </div>
        <?php endif; ?>

        <form action="./modules/login/controller/validarUsuario.php" method="POST" id="loginForm">

          <div class="mb-3">
            <label for="username" class="form-label">Usuario</label>
            <div class="input-icon-wrap">
              <input type="text" id="username" name="username"
                     class="form-control" placeholder="Tu nombre de usuario"
                     autocomplete="username" required autofocus>
              <i class="bi bi-person-fill"></i>
            </div>
          </div>

          <div class="mb-4">
            <label for="password" class="form-label">Contraseña</label>
            <div class="input-icon-wrap" style="position:relative;">
              <input type="password" id="password" name="password"
                     class="form-control" placeholder="••••••••"
                     autocomplete="current-password" required>
              <i class="bi bi-lock-fill"></i>
              <button type="button" class="btn-toggle-pwd" id="togglePwd" tabindex="-1">
                <i class="bi bi-eye" id="togglePwdIcon"></i>
              </button>
            </div>
          </div>

          <button type="submit" class="btn-login" id="btnLogin">
            <span class="spinner-border spinner-border-sm me-2" role="status"></span>
            <span class="btn-text">
              <i class="bi bi-box-arrow-in-right me-1"></i> Acceder
            </span>
          </button>

        </form>
      </div>

      <div class="login-footer">
        &copy; <?= date('Y') ?> Honducafe &mdash; Sistema de gestión interno
      </div>

    </div>
  </div>

  <script src="./assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script>
    // Toggle contraseña
    document.getElementById('togglePwd').addEventListener('click', function () {
      const inp  = document.getElementById('password');
      const icon = document.getElementById('togglePwdIcon');
      const show = inp.type === 'password';
      inp.type = show ? 'text' : 'password';
      icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    });

    // Spinner al enviar
    document.getElementById('loginForm').addEventListener('submit', function () {
      document.getElementById('btnLogin').classList.add('loading');
    });
  </script>

</body>
</html>
