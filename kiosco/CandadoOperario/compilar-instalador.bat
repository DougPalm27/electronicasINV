@echo off
rem Genera Output\Setup-CandadoOperario.exe y lo publica en <app>\descargas (lo que baja candado.php)
rem Requiere Inno Setup 6 y CandadoOperario.exe compilado (compilar.bat)
set ISCC=%LOCALAPPDATA%\Programs\Inno Setup 6\ISCC.exe
if not exist "%ISCC%" set ISCC=%ProgramFiles(x86)%\Inno Setup 6\ISCC.exe
"%ISCC%" "%~dp0CandadoOperario.iss"
if errorlevel 1 exit /b 1
if not exist "%~dp0..\..\descargas" mkdir "%~dp0..\..\descargas"
copy /Y "%~dp0Output\Setup-CandadoOperario.exe" "%~dp0..\..\descargas\Setup-CandadoOperario.exe"
