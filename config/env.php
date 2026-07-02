<?php
/**
 * Carga variables de entorno desde .env a $_ENV / getenv().
 * Sin dependencias externas — no requiere `composer install`.
 */

function cargarEnv(string $path): void
{
    static $cargado = false;
    if ($cargado) return;

    if (!is_file($path)) {
        throw new RuntimeException(
            "No se encontró el archivo .env en: $path. " .
            "Copia .env.example a .env y completa los valores."
        );
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);
        if ($linea === '' || str_starts_with($linea, '#') || !str_contains($linea, '=')) {
            continue;
        }

        [$clave, $valor] = explode('=', $linea, 2);
        $clave = trim($clave);
        $valor = trim($valor);

        if ($clave === '') continue;

        // Quitar comillas envolventes
        if (strlen($valor) >= 2 && (
            ($valor[0] === '"' && substr($valor, -1) === '"') ||
            ($valor[0] === "'" && substr($valor, -1) === "'")
        )) {
            $valor = substr($valor, 1, -1);
        }

        putenv("$clave=$valor");
        $_ENV[$clave]    = $valor;
        $_SERVER[$clave] = $valor;
    }

    $cargado = true;
}

cargarEnv(__DIR__ . '/../.env');

function env(string $clave, $default = null)
{
    $valor = $_ENV[$clave] ?? getenv($clave);
    return ($valor === false || $valor === null) ? $default : $valor;
}
