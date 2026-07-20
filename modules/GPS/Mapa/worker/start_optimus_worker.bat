@echo off
REM ============================================================
REM  Worker de Optimus - rastreo en vivo hacia gps.Posiciones
REM  Mantiene el proceso corriendo y lo reinicia si se detiene.
REM
REM  La ruta del proyecto se detecta sola (%~dp0 = carpeta de este .bat).
REM  Solo ajusta PHP abajo si php.exe no esta en esa ruta.
REM
REM  Para que arranque solo: Programador de tareas -> ver DESPLIEGUE.md
REM ============================================================

REM --- Ruta de php.exe (ajusta si es distinta en el servidor) ---
set "PHP=C:\xampp\php\php.exe"
if not exist "%PHP%" set "PHP=php"

REM --- Ubicarse en la carpeta de este .bat (donde esta optimus_worker.php) ---
cd /d "%~dp0"

:loop
echo [%date% %time%] Iniciando worker de Optimus...
"%PHP%" optimus_worker.php
echo [%date% %time%] El worker se detuvo. Reintentando en 10s...
REM ping como pausa (funciona en tareas sin consola, a diferencia de timeout)
ping -n 11 127.0.0.1 >nul
goto loop
