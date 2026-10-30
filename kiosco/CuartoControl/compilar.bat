@echo off
rem Compila CuartoControl.exe con el compilador que trae Windows (.NET 4). No hace falta instalar nada.
rem El visor 3D usa WebView2 (lib\: paquete oficial Microsoft.Web.WebView2 1.0.2903.40); sus DLL van junto al .exe.
set CSC=%WINDIR%\Microsoft.NET\Framework64\v4.0.30319\csc.exe
"%CSC%" /nologo /target:winexe /platform:x64 /codepage:65001 /out:"%~dp0CuartoControl.exe" /win32icon:"%~dp0recursos\logo.ico" /r:System.Windows.Forms.dll /r:System.Drawing.dll /r:System.Web.Extensions.dll /r:"%~dp0lib\Microsoft.Web.WebView2.Core.dll" /r:"%~dp0lib\Microsoft.Web.WebView2.WinForms.dll" "%~dp0Program.cs"
if errorlevel 1 (echo ERROR de compilacion) else (
  copy /y "%~dp0lib\*.dll" "%~dp0" >nul
  echo Listo: CuartoControl.exe
)
