# Firma un instalador para que las PCs de planta lo acepten como actualizacion automatica.
# Se corre despues de generar el instalador (lo hace compilar-instalador.bat solo).
#
# Escribe junto al instalador un "Setup-CandadoOperario-X.exe.firma" con la version, el SHA-256 del
# archivo y la firma. El candado comprueba esa firma con la llave publica que lleva dentro, asi que
# aunque alguien alterara el servidor, no podria hacerles instalar nada que tu no hayas firmado.
param(
    [Parameter(Mandatory = $true)] [string]$Instalador,
    [string]$ClavePrivada = (Join-Path $env:USERPROFILE '.honducafe\candado-firma-privada.xml')
)
$ErrorActionPreference = 'Stop'

if (-not (Test-Path $ClavePrivada)) {
    throw "No encuentro la llave privada en $ClavePrivada. Si es la primera vez, corre crear-llaves-firma.ps1; si cambiaste de PC, copia la llave desde tu respaldo."
}
$archivo = Split-Path $Instalador -Leaf
if ($archivo -notmatch '^Setup-CandadoOperario-(\d+(\.\d+){1,3})\.exe$') {
    throw "El nombre '$archivo' no tiene la forma Setup-CandadoOperario-X.Y.Z.exe"
}
$version = $Matches[1]
$sha = (Get-FileHash -Path $Instalador -Algorithm SHA256).Hash.ToLowerInvariant()

# Lo firmado incluye la version: asi nadie puede hacer pasar un instalador viejo por uno nuevo
$texto = "CANDADO|$version|$sha"
$rsa = New-Object System.Security.Cryptography.RSACryptoServiceProvider
$rsa.PersistKeyInCsp = $false
$rsa.FromXmlString([IO.File]::ReadAllText($ClavePrivada))
$firma = [Convert]::ToBase64String($rsa.SignData([Text.Encoding]::UTF8.GetBytes($texto), 'SHA256'))
$rsa.Dispose()

# Comprobacion con la llave PUBLICA del repositorio (la misma que lleva el candado)
$pub = New-Object System.Security.Cryptography.RSACryptoServiceProvider
$pub.PersistKeyInCsp = $false
$pub.FromXmlString([IO.File]::ReadAllText((Join-Path $PSScriptRoot 'firma-publica.xml')))
if (-not $pub.VerifyData([Text.Encoding]::UTF8.GetBytes($texto), 'SHA256', [Convert]::FromBase64String($firma))) {
    throw 'La firma no coincide con firma-publica.xml: la llave privada no es la del candado. No se publica.'
}
$pub.Dispose()

$json = (@{ version = $version; archivo = $archivo; sha256 = $sha; firma = $firma } | ConvertTo-Json -Compress)
[IO.File]::WriteAllText("$Instalador.firma", $json, (New-Object System.Text.UTF8Encoding($false)))
Write-Host "Firmado: $archivo  (version $version, sha256 $($sha.Substring(0,12))...)"
