@echo off
REM ============================================================
REM  Refrescador de tokens TrackSolid - corrida unica.
REM  Programar en el Programador de tareas CADA 30 MINUTOS.
REM
REM  La ruta del proyecto se detecta sola (%~dp0 = carpeta de este .bat).
REM  Ver DESPLIEGUE.md para la configuracion de la tarea.
REM ============================================================

REM --- Ruta de php.exe (se detecta sola; ajusta si es otra) ---
set "PHP=C:\xampp\php\php.exe"
if not exist "%PHP%" set "PHP=C:\Program Files\PHP\current\php.exe"
if not exist "%PHP%" set "PHP=php"

cd /d "%~dp0"

echo [%date% %time%] Refrescando tokens TrackSolid...
"%PHP%" tracksolid_token_worker.php
echo [%date% %time%] Listo (codigo %errorlevel%).
