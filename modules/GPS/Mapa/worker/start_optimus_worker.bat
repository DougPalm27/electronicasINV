@echo off
REM ============================================================
REM  Worker de Optimus - rastreo en vivo hacia gps.Posiciones
REM  Mantiene el proceso corriendo y lo reinicia si se detiene.
REM
REM  Para que arranque solo: Programador de tareas de Windows
REM  -> Nueva tarea -> Iniciar al iniciar sesion -> Accion:
REM     C:\xampp\htdocs\electronicasINV\modules\GPS\Mapa\worker\start_optimus_worker.bat
REM ============================================================
cd /d C:\xampp\htdocs\electronicasINV\modules\GPS\Mapa\worker

:loop
echo [%date% %time%] Iniciando worker de Optimus...
C:\xampp\php\php.exe optimus_worker.php
echo [%date% %time%] El worker se detuvo. Reintentando en 10s...
timeout /t 10 /nobreak >nul
goto loop
