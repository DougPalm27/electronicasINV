; Instalador del Candado de Operario (Honducafe)
; Compilar: compilar-instalador.bat  ->  Output\Setup-CandadoOperario.exe
;
; Instalacion silenciosa (despliegue en varias PCs):
;   Setup-CandadoOperario.exe /VERYSILENT /SERVIDOR=192.168.1.10 /ESTACION=Evolution-Linea2 /TOKEN=xxxxxxxx /MINUTOS=15 /DELVIS=C:\Satake\Delvis\Gui

#define AppName "Candado de Operario"
#define AppVer "1.1.1"

[Setup]
AppId={{B7C2E1A4-5D3F-4E8A-9C61-2F0A7D9E4B13}
AppName={#AppName}
AppVersion={#AppVer}
AppPublisher=Honducafe · Desarrollado por Douglas Palma
AppCopyright=Desarrollado por Douglas Palma · © 2026
VersionInfoVersion=1.1.1.0
VersionInfoCompany=Honducafe
VersionInfoCopyright=Desarrollado por Douglas Palma · © 2026
VersionInfoDescription=Instalador de Candado de Operario
VersionInfoProductName=Candado de Operario
DefaultDirName={autopf}\Honducafe\CandadoOperario
DisableDirPage=yes
DisableProgramGroupPage=yes
PrivilegesRequired=admin
OutputDir=Output
OutputBaseFilename=Setup-CandadoOperario
Compression=lzma2
SolidCompression=yes
WizardStyle=modern
SetupIconFile=recursos\logo.ico
WizardImageFile=recursos\wizard-grande.bmp
WizardSmallImageFile=recursos\wizard-pequena.bmp
ArchitecturesInstallIn64BitMode=x64compatible
UninstallDisplayIcon={app}\CandadoOperario.exe
CloseApplications=no

[Languages]
Name: "es"; MessagesFile: "compiler:Languages\Spanish.isl"

[Messages]
WelcomeLabel2=Este asistente instalará [name/ver] en su equipo.%n%nControla qué operario trabaja en cada máquina y registra sus turnos, fallas y ajustes.%n%nDesarrollado por Douglas Palma para Honducafe.%n%nSe recomienda cerrar las demás aplicaciones antes de continuar.
FinishedLabel=Se instaló [name] en su equipo.%n%nDesarrollado por Douglas Palma · Honducafe.

[Files]
Source: "CandadoOperario.exe"; DestDir: "{app}"; Flags: ignoreversion

[Dirs]
; Copia local de operarios y cola de turnos: la escribe el candado, sea cual sea el usuario de Windows
Name: "{commonappdata}\CandadoOperario"; Permissions: users-modify

[Registry]
Root: HKLM; Subkey: "Software\Microsoft\Windows\CurrentVersion\Run"; ValueType: string; ValueName: "CandadoOperario"; ValueData: """{app}\CandadoOperario.exe"""; Flags: uninsdeletevalue

[Tasks]
Name: "modoprueba"; Description: "Modo prueba: permitir cerrar el candado con Ctrl+Alt+Shift+Q (NO usar en produccion)"; Flags: unchecked

[Run]
Filename: "{app}\CandadoOperario.exe"; Description: "Iniciar el candado ahora"; Flags: nowait postinstall skipifsilent

[UninstallRun]
Filename: "{sys}\taskkill.exe"; Parameters: "/F /IM CandadoOperario.exe"; Flags: runhidden; RunOnceId: "CerrarCandado"

[Code]
var
  Pagina: TInputQueryWizardPage;

function LeerConfig(const Clave, Defecto: String): String;
var
  Lineas: TArrayOfString;
  I, P: Integer;
  L: String;
begin
  Result := Defecto;
  // {app} aún no está inicializado aquí; la ruta es fija (DefaultDirName)
  if LoadStringsFromFile(ExpandConstant('{autopf}\Honducafe\CandadoOperario\candado.config'), Lineas) then
    for I := 0 to GetArrayLength(Lineas) - 1 do
    begin
      L := Trim(Lineas[I]);
      P := Pos('=', L);
      if (P > 1) and (Copy(L, 1, 1) <> '#') and (CompareText(Trim(Copy(L, 1, P - 1)), Clave) = 0) then
      begin
        Result := Trim(Copy(L, P + 1, Length(L)));
        Exit;
      end;
    end;
end;

procedure InitializeWizard;
begin
  Pagina := CreateInputQueryPage(wpWelcome, 'Configuración de esta PC',
    'Datos de conexión con el servidor de Honducafe',
    'Estos datos los da quien administra el servidor. La estación es el nombre único de esta máquina.');
  Pagina.Add('Servidor (IP o nombre de la PC con XAMPP):', False);
  Pagina.Add('Estación (ej. Evolution-Linea1):', False);
  Pagina.Add('Token del servidor (KIOSCO_TOKEN del .env):', False);
  Pagina.Add('Minutos sin actividad para bloquear solo:', False);
  Pagina.Add('Carpeta de Delvis (para registrar fallas y ajustes):', False);

  Pagina.Edits[0].Text := ExpandConstant('{param:SERVIDOR|' + LeerConfig('Servidor', '') + '}');
  Pagina.Edits[1].Text := ExpandConstant('{param:ESTACION|' + LeerConfig('Estacion', '') + '}');
  Pagina.Edits[2].Text := ExpandConstant('{param:TOKEN|' + LeerConfig('Token', '') + '}');
  Pagina.Edits[3].Text := ExpandConstant('{param:MINUTOS|' + LeerConfig('AutoBloqueoMinutos', '15') + '}');
  Pagina.Edits[4].Text := ExpandConstant('{param:DELVIS|' + LeerConfig('DelvisDir', 'C:\Satake\Delvis\Gui') + '}');
end;

function ServidorResponde(const Servidor, Token: String): Boolean;
var
  Http: Variant;
begin
  Result := False;
  try
    Http := CreateOleObject('WinHttp.WinHttpRequest.5.1');
    Http.SetTimeouts(3000, 3000, 5000, 5000);
    Http.Open('POST', 'http://' + Servidor + '/electronicasINV/modules/Turnos/controllers/kioscoController.php', False);
    Http.SetRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    Http.Send('accion=sync&estacion=instalador&token=' + Token);
    Result := Pos('"ok":true', String(Http.ResponseText)) > 0;
  except
    Result := False;
  end;
end;

function NextButtonClick(CurPageID: Integer): Boolean;
var
  Min: Integer;
begin
  Result := True;
  if CurPageID = Pagina.ID then
  begin
    if Trim(Pagina.Edits[0].Text) = '' then begin MsgBox('Escribe el servidor.', mbError, MB_OK); Result := False; Exit; end;
    if Trim(Pagina.Edits[1].Text) = '' then begin MsgBox('Escribe el nombre de la estación.', mbError, MB_OK); Result := False; Exit; end;
    if Length(Trim(Pagina.Edits[2].Text)) < 16 then begin MsgBox('El token debe tener al menos 16 caracteres.', mbError, MB_OK); Result := False; Exit; end;
    Min := StrToIntDef(Trim(Pagina.Edits[3].Text), 0);
    if Min < 1 then begin MsgBox('Los minutos deben ser un número mayor que 0.', mbError, MB_OK); Result := False; Exit; end;

    if (Trim(Pagina.Edits[4].Text) <> '') and (not DirExists(Trim(Pagina.Edits[4].Text))) then
      if MsgBox('No se encontró la carpeta de Delvis:' + #13#10 + Trim(Pagina.Edits[4].Text) + #13#10#13#10 +
        'Sin ella no se registrarán fallas ni ajustes del turno. ¿Continuar?', mbConfirmation, MB_YESNO) = IDNO then
      begin
        Result := False;
        Exit;
      end;

    if not ServidorResponde(Trim(Pagina.Edits[0].Text), Trim(Pagina.Edits[2].Text)) then
      Result := MsgBox('No se pudo verificar el servidor o el token.' + #13#10 +
        'Puede que el servidor esté apagado, la IP sea otra o el token sea incorrecto.' + #13#10#13#10 +
        '¿Instalar de todos modos?', mbConfirmation, MB_YESNO) = IDYES;
  end;
end;

function PrepareToInstall(var NeedsRestart: Boolean): String;
var
  Codigo: Integer;
begin
  Exec(ExpandConstant('{sys}\taskkill.exe'), '/F /IM CandadoOperario.exe', '', SW_HIDE, ewWaitUntilTerminated, Codigo);
  Result := '';
end;

procedure CurStepChanged(CurStep: TSetupStep);
var
  Salir, Cfg: String;
begin
  if CurStep = ssPostInstall then
  begin
    if WizardIsTaskSelected('modoprueba') then Salir := 'true' else Salir := 'false';
    Cfg := '# Candado de operario - configuracion de ESTA PC (generado por el instalador)' + #13#10 +
           'Servidor=' + Trim(Pagina.Edits[0].Text) + #13#10 +
           'Estacion=' + Trim(Pagina.Edits[1].Text) + #13#10 +
           'Token=' + Trim(Pagina.Edits[2].Text) + #13#10 +
           'AutoBloqueoMinutos=' + Trim(Pagina.Edits[3].Text) + #13#10 +
           'DelvisDir=' + Trim(Pagina.Edits[4].Text) + #13#10 +
           'PermitirSalir=' + Salir + #13#10;
    SaveStringToFile(ExpandConstant('{app}\candado.config'), Cfg, False);
  end;
end;
