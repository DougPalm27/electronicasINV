<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Acceso — Honducafe</title>
  <link rel="icon" href="./assets/img/logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
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

    body {
      font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: var(--hc-fondo);
      color: var(--hc-texto);
      margin: 0;
    }

    .login-wrapper {
      width: 100%;
      max-width: 380px;
      padding: 1rem;
    }

    .login-brand {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: .6rem;
      margin-bottom: 1.5rem;
    }

    .login-brand .brand-icon {
      width: 34px;
      height: 34px;
      border-radius: 8px;
      background: var(--hc-verde);
      color: #fff;
      font-size: 1.05rem;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .login-brand .brand-name {
      font-size: 1rem;
      font-weight: 600;
      line-height: 1.2;
    }
    .login-brand .brand-sub {
      display: block;
      font-size: .7rem;
      font-weight: 400;
      color: var(--hc-texto-3);
      line-height: 1.2;
    }

    .login-card {
      background: #fff;
      border: 1px solid var(--hc-borde);
      border-radius: 12px;
      padding: 1.75rem 1.75rem 1.5rem;
    }

    .login-card h1 {
      font-size: 1rem;
      font-weight: 600;
      margin: 0 0 .2rem;
    }

    .login-card .subtitulo {
      font-size: .78rem;
      color: var(--hc-texto-3);
      margin: 0 0 1.4rem;
    }

    .form-label {
      font-size: .75rem;
      font-weight: 600;
      color: var(--hc-texto-2);
      margin-bottom: .3rem;
    }

    .input-icon-wrap { position: relative; }

    .input-icon-wrap > .bi {
      position: absolute;
      left: .8rem;
      top: 50%;
      transform: translateY(-50%);
      color: var(--hc-texto-3);
      font-size: .9rem;
      pointer-events: none;
    }

    .input-icon-wrap input {
      padding-left: 2.35rem;
      font-size: .84rem;
      height: 42px;
      border-radius: 8px;
      border: 1px solid #d8dce2;
      color: var(--hc-texto);
    }

    .input-icon-wrap input:focus {
      border-color: var(--hc-verde);
      box-shadow: 0 0 0 3px rgba(21, 107, 69, .12);
    }
    .input-icon-wrap:focus-within .bi { color: var(--hc-verde); }

    .btn-toggle-pwd {
      position: absolute;
      right: .7rem;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: var(--hc-texto-3);
      cursor: pointer;
      padding: 0;
      font-size: .9rem;
      z-index: 2;
    }
    .btn-toggle-pwd:hover { color: var(--hc-verde); }

    .alert-login {
      border-radius: 8px;
      font-size: .78rem;
      padding: .55rem .8rem;
      margin-bottom: 1rem;
      border: 1px solid #f5c2c7;
      background: #fdecec;
      color: #b32d2d;
      display: flex;
      align-items: center;
      gap: .5rem;
    }

    .btn-login {
      width: 100%;
      height: 42px;
      border-radius: 8px;
      border: none;
      background: var(--hc-verde);
      color: #fff;
      font-weight: 500;
      font-size: .85rem;
      cursor: pointer;
      transition: background .15s;
    }
    .btn-login:hover { background: var(--hc-verde-hover); }

    .btn-login .spinner-border { display: none; }
    .btn-login.loading .spinner-border { display: inline-block; }
    .btn-login.loading .btn-text { display: none; }

    .login-footer {
      text-align: center;
      font-size: .7rem;
      color: var(--hc-texto-3);
      margin-top: 1.25rem;
    }
  </style>
</head>
<body>

  <div class="login-wrapper">

    <div class="login-brand">
      <span class="brand-icon"><i class="bi bi-box-seam"></i></span>
      <span>
        <span class="brand-name">Honducafe</span>
        <span class="brand-sub">Sistema de gestión interno</span>
      </span>
    </div>

    <div class="login-card">

      <h1>Iniciar sesión</h1>
      <p class="subtitulo">Ingresa tus credenciales para continuar</p>

      <?php if (!empty($_GET['error'])): ?>
        <div class="alert-login">
          <i class="bi bi-exclamation-circle"></i>
          <?php
            $err = $_GET['error'];
            if ($err === 'invalid')      echo 'Usuario o contraseña incorrectos.';
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
            <i class="bi bi-person"></i>
          </div>
        </div>

        <div class="mb-4">
          <label for="password" class="form-label">Contraseña</label>
          <div class="input-icon-wrap">
            <input type="password" id="password" name="password"
                   class="form-control" placeholder="••••••••"
                   autocomplete="current-password" required>
            <i class="bi bi-lock"></i>
            <button type="button" class="btn-toggle-pwd" id="togglePwd" tabindex="-1">
              <i class="bi bi-eye" id="togglePwdIcon"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-login" id="btnLogin">
          <span class="spinner-border spinner-border-sm me-2" role="status"></span>
          <span class="btn-text">Acceder</span>
        </button>

      </form>
    </div>

    <div class="login-footer">
      &copy; <?= date('Y') ?> Honducafe &mdash; Sistema de gestión interno
    </div>

  </div>

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
