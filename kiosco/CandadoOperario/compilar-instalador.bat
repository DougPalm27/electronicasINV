@echo off
rem Genera Output\Setup-CandadoOperario-<version>.exe, lo FIRMA y lo publica en <app>\descargas (lo que baja candado.php
rem y lo que las PCs de planta aceptan como actualizacion automatica).
rem La version se toma de "#define AppVer" en CandadoOperario.iss. Requiere Inno Setup 6 y CandadoOperario.exe compilado
rem (compilar.bat). Para firmar hace falta la llave privada: ver LEEME-actualizaciones.md.
set ISCC=%LOCALAPPDATA%\Programs\Inno Setup 6\ISCC.exe
if not exist "%ISCC%" set ISCC=%ProgramFiles(x86)%\Inno Setup 6\ISCC.exe
for /f "tokens=3" %%v in ('findstr /b "#define AppVer" "%~dp0CandadoOperario.iss"') do set VER=%%~v
"%ISCC%" "%~dp0CandadoOperario.iss"
if errorlevel 1 exit /b 1
if not exist "%~dp0..\..\descargas" mkdir "%~dp0..\..\descargas"
del /q "%~dp0..\..\descargas\Setup-CandadoOperario*.exe" "%~dp0..\..\descargas\Setup-CandadoOperario*.firma" 2>nul
copy /Y "%~dp0Output\Setup-CandadoOperario-%VER%.exe" "%~dp0..\..\descargas\"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0firmar-instalador.ps1" -Instalador "%~dp0..\..\descargas\Setup-CandadoOperario-%VER%.exe"
if errorlevel 1 (
  echo.
  echo *** NO SE PUDO FIRMAR: el instalador quedo publicado para descarga manual, pero las PCs de planta NO lo recibiran como actualizacion.
  exit /b 1
)
