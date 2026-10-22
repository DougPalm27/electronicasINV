; Instalador del Candado de Operario (Honducafe)
; Compilar: compilar-instalador.bat  ->  Output\Setup-CandadoOperario-<version>.exe
;
; Instalacion silenciosa (despliegue en varias PCs):
;   Setup-CandadoOperario-1.3.0.exe /VERYSILENT /SERVIDOR=electronicas.simfcoh.com /ESTACION=Evolution-Linea2 /TOKEN=xxxxxxxx /MINUTOS=15 /DELVIS=C:\Satake\Delvis\Gui

#define AppName "Candado de Operario"
#define AppVer "1.3.0"

[Setup]
AppId={{B7C2E1A4-5D3F-4E8A-9C61-2F0A7D9E4B13}
AppName={#AppName}
AppVersion={#AppVer}
AppPublisher=Honducafe · Desarrollado por Douglas Palma
AppCopyright=Desarrollado por Douglas Palma · © 2026
VersionInfoVersion=1.3.0.0
VersionInfoCompany=Honducafe
VersionInfoCopyright=Desarrollado por Douglas Palma · © 2026
VersionInfoDescription=Instalador de Candado de Operario
VersionInfoProductName=Candado de Operario
DefaultDirName={autopf}\Honducafe\CandadoOperario
DisableDirPage=yes
DisableProgramGroupPage=yes
PrivilegesRequired=admin
OutputDir=Output
OutputBaseFilename=Setup-CandadoOperario-{#AppVer}
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

[Icons]
Name: "{commonprograms}\Candado de Operario"; Filename: "{app}\CandadoOperario.exe"; Comment: "Abre el candado de operario"; Tasks: iconoinicio
Name: "{commondesktop}\Candado de Operario"; Filename: "{app}\CandadoOperario.exe"; Comment: "Abre el candado de operario"; Tasks: iconoescritorio

[Dirs]
; Copia local de operarios y cola de turnos: la escribe el candado, sea cual sea el usuario de Windows
Name: "{commonappdata}\CandadoOperario"; Permissions: users-modify

[Registry]
Root: HKLM; Subkey: "Software\Microsoft\Windows\CurrentVersion\Run"; ValueType: string; ValueName: "CandadoOperario"; ValueData: """{app}\CandadoOperario.exe"""; Flags: uninsdeletevalue

[Tasks]
Name: "iconoinicio"; Description: "Crear un acceso directo en el menú Inicio"; GroupDescription: "Accesos directos:"
Name: "iconoescritorio"; Description: "Crear un acceso directo en el escritorio"; GroupDescription: "Accesos directos:"
Name: "modoprueba"; Description: "Modo prueba: permitir cerrar el candado con Ctrl+Alt+Shift+Q (NO usar en produccion)"; GroupDescription: "Pruebas:"; Flags: unchecked

[Run]
Filename: "{app}\CandadoOperario.exe"; Description: "Iniciar el candado ahora"; Flags: nowait postinstall skipifsilent
; En una actualizacion automatica el instalador corre como SYSTEM y no puede abrir el candado en la sesion del
; operario: se le pide a la tarea vigilante (que corre en su sesion) que lo abra enseguida, sin esperar sus 5 minutos.
Filename: "{sys}\schtasks.exe"; Parameters: "/Run /TN ""Honducafe\Candado de Operario Vigilante"""; Flags: runhidden; Check: WizardSilent

[UninstallRun]
Filename: "{sys}\schtasks.exe"; Parameters: "/Delete /TN ""Honducafe\Candado de Operario Actualizador"" /F"; Flags: runhidden; RunOnceId: "QuitarActualizador"
Filename: "{sys}\schtasks.exe"; Parameters: "/Delete /TN ""Honducafe\Candado de Operario Instalar"" /F"; Flags: runhidden; RunOnceId: "QuitarInstalador"
Filename: "{sys}\schtasks.exe"; Parameters: "/Delete /TN ""Honducafe\Candado de Operario Vigilante"" /F"; Flags: runhidden; RunOnceId: "QuitarVigilante"
Filename: "{sys}\taskkill.exe"; Parameters: "/F /IM CandadoOperario.exe"; Flags: runhidden; RunOnceId: "CerrarCandado"

[Code]
var
  Pagina: TInputQueryWizardPage;      // servidor, token, minutos y carpeta de Delvis
  PaginaMaq: TWizardPage;             // elegir la máquina de esta PC
  CmbMaq: TNewComboBox;
  LblIntro, LblSerie, LblDetalle, LblAviso, LblManual: TNewStaticText;
  EdtManual: TNewEdit;
  ModoManual: Boolean;                // sin lista del servidor: la estación se escribe a mano
  MotivoManual: String;
  EstacionParam: String;              // /ESTACION= (instalación silenciosa): no se pregunta
  EstacionElegida, MaquinaNombre, MaquinaSerie: String;
  // Paralelos al combo; la posición 0 es el aviso "Seleccione la máquina"
  Estaciones, Nombres, Series, Detalles: TArrayOfString;
  EquipoId, EquipoNombre: String;     // identifican esta PC ante el servidor

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

// El servidor puede ser una IP de LAN sin TLS ("192.168.1.10") o un dominio real
// ("electronicas.simfcoh.com" o "https://electronicas.simfcoh.com"). Si no trae
// esquema se asume http:// (uso en LAN); para usar https hay que escribirlo explícito.
function UrlBase(const Servidor: String): String;
begin
  if (Pos('http://', Lowercase(Servidor)) = 1) or (Pos('https://', Lowercase(Servidor)) = 1) then
    Result := Servidor
  else
    Result := 'http://' + Servidor;
  while (Length(Result) > 0) and (Result[Length(Result)] = '/') do
    Delete(Result, Length(Result), 1);
end;

function HexByte(B: Integer): String;
begin
  Result := '%' + Copy('0123456789ABCDEF', (B div 16) + 1, 1) + Copy('0123456789ABCDEF', (B mod 16) + 1, 1);
end;

// Codifica un valor para application/x-www-form-urlencoded (UTF-8). Sin esto, un
// token con "+" llega al servidor con un espacio y parece incorrecto.
function UrlCodificar(const S: String): String;
var
  I, C: Integer;
begin
  Result := '';
  for I := 1 to Length(S) do
  begin
    C := Ord(S[I]);
    if ((C >= 48) and (C <= 57)) or ((C >= 65) and (C <= 90)) or ((C >= 97) and (C <= 122))
       or (C = 45) or (C = 46) or (C = 95) or (C = 126) then
      Result := Result + Chr(C)
    else if C < 128 then
      Result := Result + HexByte(C)
    else if C < 2048 then
      Result := Result + HexByte(192 + (C div 64)) + HexByte(128 + (C mod 64))
    else
      Result := Result + HexByte(224 + (C div 4096)) + HexByte(128 + ((C div 64) mod 64)) + HexByte(128 + (C mod 64));
  end;
end;

// Solo letras, números y guion (lo que acepta el servidor como identificador de equipo)
function SoloSeguros(const S: String): String;
var
  I, C: Integer;
begin
  Result := '';
  for I := 1 to Length(S) do
  begin
    C := Ord(S[I]);
    if ((C >= 48) and (C <= 57)) or ((C >= 65) and (C <= 90)) or ((C >= 97) and (C <= 122)) or (C = 45) then
      Result := Result + Chr(C)
    else
      Result := Result + '-';
  end;
end;

// Identifica esta PC: el MachineGuid de Windows (no cambia al renombrarla ni al
// reinstalar el candado) y, solo para mostrarlo en el sistema, su nombre.
procedure ObtenerEquipo;
var
  Guid: String;
begin
  EquipoNombre := GetComputerNameString;
  Guid := '';
  try
    if not RegQueryStringValue(HKLM64, 'SOFTWARE\Microsoft\Cryptography', 'MachineGuid', Guid) then
      Guid := '';
  except
    Guid := '';
  end;
  if Guid = '' then
  begin
    try
      if not RegQueryStringValue(HKLM, 'SOFTWARE\Microsoft\Cryptography', 'MachineGuid', Guid) then
        Guid := '';
    except
      Guid := '';
    end;
  end;
  Guid := SoloSeguros(Guid);
  if Length(Guid) < 8 then
    Guid := 'EQUIPO-' + SoloSeguros(EquipoNombre);
  EquipoId := Copy(Guid, 1, 64);
end;

function PostServidor(const Servidor, Cuerpo: String; var Respuesta: String): Boolean;
var
  Http: Variant;
begin
  Result := False;
  Respuesta := '';
  try
    Http := CreateOleObject('WinHttp.WinHttpRequest.5.1');
    Http.SetTimeouts(3000, 3000, 8000, 8000);
    Http.Open('POST', UrlBase(Servidor) + '/modules/Turnos/controllers/kioscoController.php', False);
    Http.SetRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    Http.Send(Cuerpo);
    Respuesta := String(Http.ResponseText);
    Result := True;
  except
    Result := False;
  end;
end;

function ServidorResponde(const Servidor, Token: String): Boolean;
var
  Resp: String;
begin
  Result := PostServidor(Servidor, 'accion=sync&estacion=instalador&token=' + UrlCodificar(Token), Resp)
            and (Pos('"ok":true', Resp) > 0);
end;

// Letras, números, espacio, punto, guion y guion bajo (lo mismo que acepta el servidor)
function EstacionValida(const E: String): Boolean;
var
  I, C: Integer;
begin
  Result := (Length(E) >= 1) and (Length(E) <= 100);
  for I := 1 to Length(E) do
  begin
    C := Ord(E[I]);
    if not (((C >= 48) and (C <= 57)) or ((C >= 65) and (C <= 90)) or ((C >= 97) and (C <= 122))
            or (C = 32) or (C = 45) or (C = 46) or (C = 95) or (C > 127)) then
      Result := False;
  end;
end;

function EstacionFinal: String;
begin
  if EstacionElegida <> '' then
    Result := EstacionElegida
  else if EstacionParam <> '' then
    Result := EstacionParam
  else
    Result := LeerConfig('Estacion', '');
end;

procedure AgregarItem(const Texto, Nombre, Detalle, Serie, Estacion: String);
var
  N: Integer;
begin
  N := CmbMaq.Items.Add(Texto);
  SetArrayLength(Estaciones, N + 1);
  SetArrayLength(Nombres, N + 1);
  SetArrayLength(Series, N + 1);
  SetArrayLength(Detalles, N + 1);
  Estaciones[N] := Estacion;
  Nombres[N] := Nombre;
  Series[N] := Serie;
  Detalles[N] := Detalle;
end;

// Parte un texto por un separador y conserva los campos vacíos (la serie puede venir vacía).
function Trocear(const S: String; Sep: Char): TArrayOfString;
var
  I, N: Integer;
  Actual: String;
begin
  SetArrayLength(Result, 0);
  N := 0;
  Actual := '';
  for I := 1 to Length(S) do
  begin
    if S[I] = Sep then
    begin
      SetArrayLength(Result, N + 1);
      Result[N] := Actual;
      N := N + 1;
      Actual := '';
    end
    else
      Actual := Actual + S[I];
  end;
  SetArrayLength(Result, N + 1);
  Result[N] := Actual;
end;

// Pide al servidor las máquinas habilitadas que todavía no tienen candado en otra PC.
// Devuelve False si no pudo (servidor viejo, sin red...): ahí se escribe la estación a mano.
function CargarMaquinas(const Servidor, Token: String): Boolean;
var
  Resp: String;
  Lineas, P: TArrayOfString;
  I, Sugerida: Integer;
begin
  Result := False;
  ModoManual := True;
  CmbMaq.Items.Clear;
  SetArrayLength(Estaciones, 0);
  SetArrayLength(Nombres, 0);
  SetArrayLength(Series, 0);
  SetArrayLength(Detalles, 0);

  if not PostServidor(Servidor,
       'accion=maquinas&estacion=instalador&formato=texto&token=' + UrlCodificar(Token) +
       '&equipo_id=' + UrlCodificar(EquipoId), Resp) then
  begin
    MotivoManual := 'No se pudo pedir la lista de máquinas al servidor.';
    Exit;
  end;

  Lineas := Trocear(Resp, #10);
  if (GetArrayLength(Lineas) = 0) or (Trim(Lineas[0]) <> 'OK') then
  begin
    MotivoManual := 'El servidor no entregó la lista de máquinas (puede que falte actualizarlo).';
    Exit;
  end;

  AgregarItem('— Seleccione la máquina —', '', '', '', '');
  Sugerida := 0;
  for I := 1 to GetArrayLength(Lineas) - 1 do
  begin
    P := Trocear(Trim(Lineas[I]), '|');
    if GetArrayLength(P) >= 7 then
    begin
      AgregarItem(P[1] + '    (' + P[2] + ' · ' + P[3] + ')', P[1], P[2] + ' · ' + P[3], P[4], P[5]);
      // Si esta PC ya tiene una máquina (reinstalación o actualización), queda elegida
      if P[6] = '1' then
        Sugerida := CmbMaq.Items.Count - 1
      else if (Sugerida = 0) and (CompareText(P[5], LeerConfig('Estacion', '')) = 0) then
        Sugerida := CmbMaq.Items.Count - 1;
    end;
  end;

  Result := True;
  if CmbMaq.Items.Count <= 1 then
  begin
    MotivoManual := 'No hay máquinas disponibles: todas tienen ya su candado o ninguna tiene "Estación del candado" asignada en el sistema (Máquinas).';
    Exit;
  end;
  ModoManual := False;
  CmbMaq.ItemIndex := Sugerida;
end;

procedure ComboCambio(Sender: TObject);
var
  I: Integer;
begin
  I := CmbMaq.ItemIndex;
  if I > 0 then
  begin
    if Series[I] <> '' then
      LblSerie.Caption := 'Serie: ' + Series[I]
    else
      LblSerie.Caption := 'Serie: (sin registrar en el sistema)';
    LblDetalle.Caption := Nombres[I] + '  ·  ' + Detalles[I];
  end
  else
  begin
    LblSerie.Caption := 'Serie: —';
    LblDetalle.Caption := '';
  end;
end;

procedure InitializeWizard;
begin
  ObtenerEquipo;
  EstacionParam := Trim(ExpandConstant('{param:ESTACION|}'));
  ModoManual := False;

  Pagina := CreateInputQueryPage(wpWelcome, 'Conexión con el servidor',
    'Datos para hablar con el servidor de Honducafe',
    'Estos datos los da quien administra el servidor. En el siguiente paso se elige la máquina donde se instala.');
  Pagina.Add('Servidor (dominio o IP; si el sitio no está en la raíz, agrega la carpeta):', False);
  Pagina.Add('Token del servidor (KIOSCO_TOKEN del .env):', False);
  Pagina.Add('Minutos sin actividad para bloquear solo:', False);
  Pagina.Add('Carpeta de Delvis (para registrar fallas y ajustes):', False);

  Pagina.Edits[0].Text := ExpandConstant('{param:SERVIDOR|' + LeerConfig('Servidor', '') + '}');
  Pagina.Edits[1].Text := ExpandConstant('{param:TOKEN|' + LeerConfig('Token', '') + '}');
  Pagina.Edits[2].Text := ExpandConstant('{param:MINUTOS|' + LeerConfig('AutoBloqueoMinutos', '15') + '}');
  Pagina.Edits[3].Text := ExpandConstant('{param:DELVIS|' + LeerConfig('DelvisDir', 'C:\Satake\Delvis\Gui') + '}');

  PaginaMaq := CreateCustomPage(Pagina.ID, 'Máquina de esta PC',
    'Elija la máquina donde se instala este candado');

  LblIntro := TNewStaticText.Create(PaginaMaq);
  LblIntro.Parent := PaginaMaq.Surface;
  LblIntro.AutoSize := False;
  LblIntro.WordWrap := True;
  LblIntro.Left := 0;
  LblIntro.Top := 0;
  LblIntro.Width := PaginaMaq.SurfaceWidth;
  LblIntro.Height := ScaleY(44);

  CmbMaq := TNewComboBox.Create(PaginaMaq);
  CmbMaq.Parent := PaginaMaq.Surface;
  CmbMaq.Style := csDropDownList;
  CmbMaq.Left := 0;
  CmbMaq.Top := ScaleY(50);
  CmbMaq.Width := PaginaMaq.SurfaceWidth;
  CmbMaq.OnChange := @ComboCambio;

  LblSerie := TNewStaticText.Create(PaginaMaq);
  LblSerie.Parent := PaginaMaq.Surface;
  LblSerie.AutoSize := False;
  LblSerie.Left := 0;
  LblSerie.Top := ScaleY(92);
  LblSerie.Width := PaginaMaq.SurfaceWidth;
  LblSerie.Height := ScaleY(26);
  LblSerie.Font.Size := 14;
  LblSerie.Font.Style := [fsBold];

  LblDetalle := TNewStaticText.Create(PaginaMaq);
  LblDetalle.Parent := PaginaMaq.Surface;
  LblDetalle.AutoSize := False;
  LblDetalle.Left := 0;
  LblDetalle.Top := ScaleY(124);
  LblDetalle.Width := PaginaMaq.SurfaceWidth;
  LblDetalle.Height := ScaleY(20);

  LblAviso := TNewStaticText.Create(PaginaMaq);
  LblAviso.Parent := PaginaMaq.Surface;
  LblAviso.AutoSize := False;
  LblAviso.WordWrap := True;
  LblAviso.Left := 0;
  LblAviso.Top := ScaleY(156);
  LblAviso.Width := PaginaMaq.SurfaceWidth;
  LblAviso.Height := ScaleY(40);
  LblAviso.Caption := 'Compare la serie con la placa de la máquina antes de continuar: si no coincide, elija otra. ' +
                      'Solo aparecen las máquinas que todavía no tienen su candado instalado.';

  LblManual := TNewStaticText.Create(PaginaMaq);
  LblManual.Parent := PaginaMaq.Surface;
  LblManual.AutoSize := False;
  LblManual.Left := 0;
  LblManual.Top := ScaleY(60);
  LblManual.Width := PaginaMaq.SurfaceWidth;
  LblManual.Height := ScaleY(18);
  LblManual.Caption := 'Nombre de la estación (ej. EVO1-A):';

  EdtManual := TNewEdit.Create(PaginaMaq);
  EdtManual.Parent := PaginaMaq.Surface;
  EdtManual.Left := 0;
  EdtManual.Top := ScaleY(82);
  EdtManual.Width := PaginaMaq.SurfaceWidth;
  EdtManual.Text := LeerConfig('Estacion', '');
end;

// Con /ESTACION= (instalación silenciosa en varias PCs) no se pregunta la máquina
function ShouldSkipPage(PageID: Integer): Boolean;
begin
  Result := False;
  if (PaginaMaq <> nil) and (PageID = PaginaMaq.ID) and (EstacionParam <> '') then
    Result := True;
end;

procedure CurPageChanged(CurPageID: Integer);
begin
  if (PaginaMaq <> nil) and (CurPageID = PaginaMaq.ID) then
  begin
    CmbMaq.Visible := not ModoManual;
    LblSerie.Visible := not ModoManual;
    LblDetalle.Visible := not ModoManual;
    LblAviso.Visible := not ModoManual;
    LblManual.Visible := ModoManual;
    EdtManual.Visible := ModoManual;
    if ModoManual then
      LblIntro.Caption := MotivoManual + ' Escribe a mano el nombre de la estación; avisa al administrador para que quede ligada a su máquina en el sistema.'
    else
      LblIntro.Caption := 'Elija en la lista la máquina donde está esta PC. Se muestra su serie para confirmar que es la correcta.';
    ComboCambio(nil);
  end;
end;

function NextButtonClick(CurPageID: Integer): Boolean;
var
  Min, I: Integer;
  E: String;
begin
  Result := True;

  if CurPageID = Pagina.ID then
  begin
    if Trim(Pagina.Edits[0].Text) = '' then begin MsgBox('Escribe el servidor.', mbError, MB_OK); Result := False; Exit; end;
    if Length(Trim(Pagina.Edits[1].Text)) < 16 then begin MsgBox('El token debe tener al menos 16 caracteres.', mbError, MB_OK); Result := False; Exit; end;
    Min := StrToIntDef(Trim(Pagina.Edits[2].Text), 0);
    if Min < 1 then begin MsgBox('Los minutos deben ser un número mayor que 0.', mbError, MB_OK); Result := False; Exit; end;

    if (Trim(Pagina.Edits[3].Text) <> '') and (not DirExists(Trim(Pagina.Edits[3].Text))) then
      if MsgBox('No se encontró la carpeta de Delvis:' + #13#10 + Trim(Pagina.Edits[3].Text) + #13#10#13#10 +
        'Sin ella no se registrarán fallas ni ajustes del turno. ¿Continuar?', mbConfirmation, MB_YESNO) = IDNO then
      begin
        Result := False;
        Exit;
      end;

    if ServidorResponde(Trim(Pagina.Edits[0].Text), Trim(Pagina.Edits[1].Text)) then
      CargarMaquinas(Trim(Pagina.Edits[0].Text), Trim(Pagina.Edits[1].Text))
    else
    begin
      if MsgBox('No se pudo verificar el servidor o el token.' + #13#10 +
        'Puede que el servidor esté apagado, la IP sea otra o el token sea incorrecto.' + #13#10#13#10 +
        '¿Instalar de todos modos?', mbConfirmation, MB_YESNO) = IDNO then
      begin
        Result := False;
        Exit;
      end;
      ModoManual := True;
      MotivoManual := 'No se pudo conectar con el servidor para pedir la lista de máquinas.';
    end;
  end
  else if (PaginaMaq <> nil) and (CurPageID = PaginaMaq.ID) then
  begin
    if ModoManual then
    begin
      E := Trim(EdtManual.Text);
      if E = '' then begin MsgBox('Escribe el nombre de la estación.', mbError, MB_OK); Result := False; Exit; end;
      if not EstacionValida(E) then
      begin
        MsgBox('El nombre de la estación solo admite letras, números, espacios, punto, guion y guion bajo (máximo 100).', mbError, MB_OK);
        Result := False;
        Exit;
      end;
      EstacionElegida := E;
      MaquinaNombre := '';
      MaquinaSerie := '';
    end
    else
    begin
      I := CmbMaq.ItemIndex;
      if I <= 0 then begin MsgBox('Elige la máquina de esta PC.', mbError, MB_OK); Result := False; Exit; end;
      EstacionElegida := Estaciones[I];
      MaquinaNombre := Nombres[I];
      MaquinaSerie := Series[I];
    end;
  end;
end;

// Resumen antes de instalar: lo último que se ve para confirmar la máquina y su serie
function UpdateReadyMemo(Space, NewLine, MemoUserInfoInfo, MemoDirInfo, MemoTypeInfo, MemoComponentsInfo, MemoGroupInfo, MemoTasksInfo: String): String;
begin
  Result := 'Conexión:' + NewLine + Space + 'Servidor: ' + Trim(Pagina.Edits[0].Text) + NewLine + NewLine +
            'Máquina de esta PC:' + NewLine + Space + 'Estación: ' + EstacionFinal;
  if MaquinaNombre <> '' then
  begin
    Result := Result + NewLine + Space + 'Máquina: ' + MaquinaNombre;
    if MaquinaSerie <> '' then
      Result := Result + NewLine + Space + 'Serie: ' + MaquinaSerie;
  end;
  Result := Result + NewLine + NewLine + MemoDirInfo;
  if MemoTasksInfo <> '' then
    Result := Result + NewLine + NewLine + MemoTasksInfo;
end;

function PrepareToInstall(var NeedsRestart: Boolean): String;
var
  Codigo: Integer;
begin
  Exec(ExpandConstant('{sys}\taskkill.exe'), '/F /IM CandadoOperario.exe', '', SW_HIDE, ewWaitUntilTerminated, Codigo);
  Result := '';
end;

// Tarea de Windows que cada 5 minutos intenta abrir el candado (si ya esta abierto, no hace nada).
// Corre con el grupo Usuarios para que use la sesion del operario que este conectado.
procedure CrearTareaVigilante;
var
  Ps, Ruta: String;
  Codigo: Integer;
begin
  Ps :=
    '$ErrorActionPreference = ''Stop''' + #13#10 +
    '$exe = ''' + ExpandConstant('{app}\CandadoOperario.exe') + '''' + #13#10 +
    '$a = New-ScheduledTaskAction -Execute $exe -Argument ''--vigilante''' + #13#10 +
    '$t = New-ScheduledTaskTrigger -Once -At (Get-Date ''2026-01-01'') -RepetitionInterval (New-TimeSpan -Minutes 5) -RepetitionDuration (New-TimeSpan -Days 3650)' + #13#10 +
    '$p = New-ScheduledTaskPrincipal -GroupId ''S-1-5-32-545'' -RunLevel Limited' + #13#10 +
    '$s = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -ExecutionTimeLimit (New-TimeSpan -Seconds 0)' + #13#10 +
    'Register-ScheduledTask -TaskName ''Candado de Operario Vigilante'' -TaskPath ''\Honducafe\'' -Action $a -Trigger $t -Principal $p -Settings $s -Description ''Reabre el Candado de Operario si se cierra. Honducafe.'' -Force | Out-Null' + #13#10;
  Ruta := ExpandConstant('{tmp}\vigilante.ps1');
  SaveStringToFile(Ruta, Ps, False);

  if (not Exec(ExpandConstant('{sys}\WindowsPowerShell\v1.0\powershell.exe'),
        '-NoProfile -ExecutionPolicy Bypass -File "' + Ruta + '"', '', SW_HIDE, ewWaitUntilTerminated, Codigo))
     or (Codigo <> 0) then
  begin
    Log('No se pudo crear la tarea vigilante. Codigo: ' + IntToStr(Codigo));
    if not WizardSilent then
      MsgBox('No se pudo crear la tarea que reabre el candado si se cierra (codigo ' + IntToStr(Codigo) + ').' + #13#10 +
             'El candado funciona, pero no se volvera a abrir solo hasta el proximo inicio de sesion.', mbInformation, MB_OK);
  end;
end;

// Dos tareas de Windows, ambas como SYSTEM (la instalacion necesita administrador y el operario no lo es):
//  - "Actualizador": cada 30 min corre "CandadoOperario.exe --actualizar", que consulta al servidor, verifica
//    la FIRMA y descarga el instalador a una carpeta que solo SYSTEM puede escribir.
//  - "Instalar": corre ese instalador ya verificado, aparte de la anterior (porque el instalador cierra el candado).
procedure CrearTareasActualizacion;
var
  Ps, Ruta, Sil: String;
  Codigo: Integer;
begin
  if WizardSilent then Sil := 'true' else Sil := 'false';
  Ps :=
    '$ErrorActionPreference = ''Stop''' + #13#10 +
    '$exe = ''' + ExpandConstant('{app}\CandadoOperario.exe') + '''' + #13#10 +
    '$silencioso = $' + Sil + #13#10 +
    '$upd = Join-Path $env:SystemRoot ''System32\config\systemprofile\AppData\Local\Honducafe\upd''' + #13#10 +
    '$sys = New-ScheduledTaskPrincipal -UserId ''SYSTEM'' -LogonType ServiceAccount -RunLevel Highest' + #13#10 +
    '# 1) Cada 30 min pregunta si hay una version nueva FIRMADA (solo descarga y verifica; no instala)' + #13#10 +
    '$a1 = New-ScheduledTaskAction -Execute $exe -Argument ''--actualizar''' + #13#10 +
    '$t1 = New-ScheduledTaskTrigger -Once -At (Get-Date ''2026-01-01'') -RepetitionInterval (New-TimeSpan -Minutes 30) -RepetitionDuration (New-TimeSpan -Days 3650)' + #13#10 +
    '$s1 = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -ExecutionTimeLimit (New-TimeSpan -Minutes 20)' + #13#10 +
    'Register-ScheduledTask -TaskName ''Candado de Operario Actualizador'' -TaskPath ''\Honducafe\'' -Action $a1 -Trigger $t1 -Principal $sys -Settings $s1 -Description ''Revisa si hay una version nueva firmada del Candado de Operario. Honducafe.'' -Force | Out-Null' + #13#10 +
    '# 2) Instala el instalador ya verificado. Sin horario: solo la inicia el actualizador. Si es una actualizacion' + #13#10 +
    '#    silenciosa no se reescribe, porque es esta misma tarea la que esta ejecutando la instalacion.' + #13#10 +
    '$existe = [bool](Get-ScheduledTask -TaskName ''Candado de Operario Instalar'' -TaskPath ''\Honducafe\'' -ErrorAction SilentlyContinue)' + #13#10 +
    'if ((-not $silencioso) -or (-not $existe)) {' + #13#10 +
    '  $args2 = ''/VERYSILENT /SUPPRESSMSGBOXES /NORESTART /MERGETASKS=!iconoinicio,!iconoescritorio /LOG='' + (Join-Path $upd ''instalacion.log'')' + #13#10 +
    '  $a2 = New-ScheduledTaskAction -Execute (Join-Path $upd ''Setup-actual.exe'') -Argument $args2' + #13#10 +
    '  $s2 = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -ExecutionTimeLimit (New-TimeSpan -Minutes 30)' + #13#10 +
    '  Register-ScheduledTask -TaskName ''Candado de Operario Instalar'' -TaskPath ''\Honducafe\'' -Action $a2 -Principal $sys -Settings $s2 -Description ''Instala la version ya descargada y verificada del Candado de Operario. Honducafe.'' -Force | Out-Null' + #13#10 +
    '}' + #13#10;
  Ruta := ExpandConstant('{tmp}\actualizacion.ps1');
  SaveStringToFile(Ruta, Ps, False);

  if (not Exec(ExpandConstant('{sys}\WindowsPowerShell\v1.0\powershell.exe'),
        '-NoProfile -ExecutionPolicy Bypass -File "' + Ruta + '"', '', SW_HIDE, ewWaitUntilTerminated, Codigo))
     or (Codigo <> 0) then
  begin
    Log('No se pudieron crear las tareas de actualizacion. Codigo: ' + IntToStr(Codigo));
    if not WizardSilent then
      MsgBox('No se pudieron crear las tareas de la actualizacion automatica (codigo ' + IntToStr(Codigo) + ').' + #13#10 +
             'El candado funciona, pero no se actualizara solo: habra que instalar cada version a mano.', mbInformation, MB_OK);
  end;
end;

// Anota en el servidor que esta PC tiene el candado de esa máquina; así no se ofrece a otra PC.
// Si falla, el candado queda instalado igual (solo se avisa).
procedure RegistrarInstalacion;
var
  Resp, Msg: String;
begin
  if not PostServidor(Trim(Pagina.Edits[0].Text),
       'accion=registrarInstalacion&token=' + UrlCodificar(Trim(Pagina.Edits[1].Text)) +
       '&estacion=' + UrlCodificar(EstacionFinal) +
       '&equipo_id=' + UrlCodificar(EquipoId) +
       '&equipo_nombre=' + UrlCodificar(EquipoNombre) +
       '&version=' + UrlCodificar('{#AppVer}'), Resp) then
  begin
    Log('No se pudo contactar al servidor para registrar la instalacion.');
    if not WizardSilent then
      MsgBox('El candado quedó instalado, pero no se pudo avisar al servidor que esta PC lo tiene.' + #13#10 +
             'Avisa al administrador para que registre la máquina.', mbInformation, MB_OK);
    Exit;
  end;

  if Trim(Resp) = 'OK' then
  begin
    Log('Instalacion registrada en el servidor: ' + EstacionFinal);
    Exit;
  end;

  if Pos('ERROR|', Resp) = 1 then
    Msg := Trim(Copy(Resp, 7, Length(Resp)))
  else
    Msg := 'Respuesta inesperada del servidor.';
  Log('No se registro la instalacion: ' + Msg);
  // En modo manual la estación puede no existir en el inventario: no se molesta con el aviso
  if (not WizardSilent) and (not ModoManual) then
    MsgBox('El candado quedó instalado, pero esta PC no quedó registrada en el sistema:' + #13#10 + Msg + #13#10#13#10 +
           'Avisa al administrador.', mbInformation, MB_OK);
end;

procedure CurStepChanged(CurStep: TSetupStep);
var
  Salir, Cfg: String;
begin
  if CurStep = ssPostInstall then
  begin
    // En una actualizacion silenciosa no hay casilla que marcar: se conserva lo que ya tenia esta PC
    if WizardIsTaskSelected('modoprueba') then Salir := 'true'
    else if WizardSilent then Salir := LeerConfig('PermitirSalir', 'false')
    else Salir := 'false';
    Cfg := '# Candado de operario - configuracion de ESTA PC (generado por el instalador)' + #13#10 +
           'Servidor=' + Trim(Pagina.Edits[0].Text) + #13#10 +
           'Estacion=' + EstacionFinal + #13#10 +
           'Token=' + Trim(Pagina.Edits[1].Text) + #13#10 +
           'AutoBloqueoMinutos=' + Trim(Pagina.Edits[2].Text) + #13#10 +
           'DelvisDir=' + Trim(Pagina.Edits[3].Text) + #13#10 +
           'PermitirSalir=' + Salir + #13#10;
    SaveStringToFile(ExpandConstant('{app}\candado.config'), Cfg, False);
    CrearTareaVigilante;
    CrearTareasActualizacion;
    RegistrarInstalacion;
  end;
end;
