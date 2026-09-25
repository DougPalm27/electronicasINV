@echo off
rem Compila CandadoOperario.exe con el compilador que trae Windows (.NET 4). Incrusta el logo y el icono de recursos\
set CSC=%WINDIR%\Microsoft.NET\Framework64\v4.0.30319\csc.exe
"%CSC%" /nologo /target:winexe /codepage:65001 /out:"%~dp0CandadoOperario.exe" /win32icon:"%~dp0recursos\logo.ico" /resource:"%~dp0recursos\logo.png",CandadoOperario.logo.png /r:System.Windows.Forms.dll /r:System.Drawing.dll /r:System.Web.Extensions.dll /r:System.Xml.dll /r:System.Xml.Linq.dll "%~dp0Program.cs"
if errorlevel 1 (echo ERROR de compilacion) else (echo Listo: CandadoOperario.exe)
