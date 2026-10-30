# Arma salida\CuartoControl-<version>.apk (app de la tablet) sin Android Studio ni Gradle.
# Herramientas en %LOCALAPPDATA%\Honducafe\android-build: OpenJDK 17 (Temurin), Android build-tools 34 y la
# plataforma android-34 (licencia del Android SDK aceptada por el usuario el 2026-10-30).
# Toma de Planos los visores 3D y las máquinas (IPs de salida\...\remoto.json y posiciones de layout_*.json).
# La llave para firmar vive fuera del repo: %USERPROFILE%\.honducafe\android-cuartocontrol.jks (+ .pass).
#   Uso:  powershell -ExecutionPolicy Bypass -File compilar.ps1 -Version 1.0 -Codigo 1
param([string]$Version = "1.0", [int]$Codigo = 1, [string]$Planos = "C:\xampp\htdocs\Planos")
$ErrorActionPreference = 'Stop'
$P = $PSScriptRoot
$B = Join-Path $env:LOCALAPPDATA 'Honducafe\android-build'
$JDK = (Get-ChildItem (Join-Path $B 'jdk') -Directory | Select-Object -First 1).FullName
$BT = Join-Path $B 'sdk\build-tools\android-14'
$JAR = Join-Path $B 'sdk\platforms\android-34\android.jar'
foreach ($x in @($JDK, $BT, $JAR)) { if (-not $x -or -not (Test-Path $x)) { throw "Falta $x (herramientas de Android)." } }
$env:JAVA_HOME = $JDK
$env:PATH = "$JDK\bin;$env:PATH"
$O = Join-Path $P 'obj'
$OUT = Join-Path $P 'salida'

function Paso($t) { Write-Host "-- $t" }
function Revisar($t) { if ($LASTEXITCODE -ne 0) { throw "Falló: $t (código $LASTEXITCODE)" } }

if (Test-Path $O) { Remove-Item $O -Recurse -Force }
New-Item -ItemType Directory "$O\assets", "$O\classes", "$O\gen", $OUT -Force | Out-Null

Paso 'pantalla, visores 3D y plano'
Copy-Item "$P\assets\*" "$O\assets" -Recurse
$plantas = @(
    @{ nombre = 'Planta 1'; remoto = "$Planos\salida\planta1\remoto.json"; layout = "$Planos\layout_planta1.json"; visorOrigen = "$Planos\salida\planta1\visor_3d.html"; visor = 'visor_p1.html' },
    @{ nombre = 'Planta 2'; remoto = "$Planos\salida\remoto.json"; layout = "$Planos\layout_mm.json"; visorOrigen = "$Planos\salida\visor_3d.html"; visor = 'visor_p2.html' }
)
$lista = @()
foreach ($pl in $plantas) {
    Copy-Item $pl.visorOrigen (Join-Path "$O\assets" $pl.visor)
    $rem = (Get-Content -Raw -Encoding UTF8 $pl.remoto | ConvertFrom-Json).maquinas
    $lay = Get-Content -Raw -Encoding UTF8 $pl.layout | ConvertFrom-Json
    $ms = @()
    foreach ($m in $lay.maquinas) {
        $r = $rem.($m.id)
        $host_ = ''; $puerto = ''
        if ($r -and $r.protocolo -eq 'vnc') { $host_ = [string]$r.host; $puerto = [string]$r.puerto }
        $ms += [ordered]@{ id = $m.id; host = $host_; puerto = $puerto; s = @($m.s_mm); t = @($m.t_mm) }
    }
    $lista += [ordered]@{ nombre = $pl.nombre; visor = $pl.visor; maquinas = $ms }
}
$json = [ordered]@{ plantas = $lista; generado = (Get-Date -Format 'yyyy-MM-dd HH:mm') } | ConvertTo-Json -Depth 8 -Compress
[IO.File]::WriteAllText("$O\assets\plano.js", "window.PLANO_DATOS = $json;`n", (New-Object Text.UTF8Encoding $false))

Paso 'recursos y manifiesto (aapt2)'
& "$BT\aapt2.exe" compile --dir "$P\res" -o "$O\res.zip"; Revisar 'aapt2 compile'
& "$BT\aapt2.exe" link -o "$O\base.apk" -I $JAR --manifest "$P\AndroidManifest.xml" --java "$O\gen" -A "$O\assets" `
    --min-sdk-version 24 --target-sdk-version 34 --version-code $Codigo --version-name $Version "$O\res.zip"; Revisar 'aapt2 link'

Paso 'Java -> dex'
$src = Get-ChildItem "$P\src", "$O\gen" -Recurse -Filter *.java | ForEach-Object FullName
& "$JDK\bin\javac.exe" -source 8 -target 8 -Xlint:-options -encoding UTF-8 -bootclasspath $JAR -d "$O\classes" @src; Revisar 'javac'
$clases = Get-ChildItem "$O\classes" -Recurse -Filter *.class | ForEach-Object FullName
& "$BT\d8.bat" --release --lib $JAR --min-api 24 --output $O @clases; Revisar 'd8'
Add-Type -AssemblyName System.IO.Compression.FileSystem
$z = [IO.Compression.ZipFile]::Open("$O\base.apk", 'Update')
[void][IO.Compression.ZipFileExtensions]::CreateEntryFromFile($z, "$O\classes.dex", 'classes.dex')
$z.Dispose()

Paso 'alinear y firmar'
& "$BT\zipalign.exe" -f -p 4 "$O\base.apk" "$O\alineado.apk"; Revisar 'zipalign'
$K = Join-Path $env:USERPROFILE '.honducafe'
$KS = Join-Path $K 'android-cuartocontrol.jks'
$PASS = Join-Path $K 'android-cuartocontrol.pass'
if (-not (Test-Path $KS)) {
    New-Item -ItemType Directory $K -Force | Out-Null
    $bytes = New-Object byte[] 24; [Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    [IO.File]::WriteAllText($PASS, [Convert]::ToBase64String($bytes).Replace('+', 'A').Replace('/', 'B').Replace('=', ''), (New-Object Text.UTF8Encoding $false))
    & "$JDK\bin\keytool.exe" -genkeypair -keystore $KS -storetype PKCS12 -storepass:file $PASS -keypass:file $PASS -alias cuartocontrol `
        -keyalg RSA -keysize 2048 -validity 10000 -dname "CN=Honducafe Cuarto de control, O=Honducafe, C=HN"; Revisar 'keytool'
    Write-Host "   Llave nueva: $KS (guárdala: sin ella, las versiones siguientes no se instalan encima)"
}
$apk = Join-Path $OUT "CuartoControl-$Version.apk"
& "$BT\apksigner.bat" sign --ks $KS --ks-pass "file:$PASS" --ks-key-alias cuartocontrol --out $apk "$O\alineado.apk"; Revisar 'apksigner sign'
& "$BT\apksigner.bat" verify --print-certs $apk; Revisar 'apksigner verify'
Write-Host "Listo: $apk ($([math]::Round((Get-Item $apk).Length / 1KB)) KB)"
