@echo off
rem Compila CuartoControl.exe con el compilador que trae Windows (.NET 4). No hace falta instalar nada.
set CSC=%WINDIR%\Microsoft.NET\Framework64\v4.0.30319\csc.exe
"%CSC%" /nologo /target:winexe /codepage:65001 /out:"%~dp0CuartoControl.exe" /win32icon:"%~dp0recursos\logo.ico" /r:System.Windows.Forms.dll /r:System.Drawing.dll /r:System.Web.Extensions.dll "%~dp0Program.cs"
if errorlevel 1 (echo ERROR de compilacion) else (echo Listo: CuartoControl.exe)
