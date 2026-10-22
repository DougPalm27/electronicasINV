# Crea el par de llaves con el que se firman las actualizaciones del candado.
# SE CORRE UNA SOLA VEZ. Despues:
#   - La llave PRIVADA queda en tu usuario de Windows (fuera del repositorio). Quien la tenga puede
#     instalar programas en todas las PCs de planta: no la subas a Git, no la mandes por correo.
#     GUARDA UNA COPIA en una memoria USB: si se pierde, las PCs ya instaladas no podran recibir
#     mas actualizaciones y habra que reinstalarlas a mano.
#   - La llave PUBLICA (firma-publica.xml) si va al repositorio: es la que lleva el candado para
#     comprobar que una actualizacion fue firmada con tu llave privada.
param([string]$Carpeta = (Join-Path $env:USERPROFILE '.honducafe'))
$ErrorActionPreference = 'Stop'

$privada = Join-Path $Carpeta 'candado-firma-privada.xml'
$publica = Join-Path $PSScriptRoot 'firma-publica.xml'

if (Test-Path $privada) {
    throw "Ya existe una llave privada en $privada. No se sobrescribe: cambiarla dejaria sin actualizaciones a las PCs ya instaladas."
}

New-Item -ItemType Directory -Path $Carpeta -Force | Out-Null
$rsa = New-Object System.Security.Cryptography.RSACryptoServiceProvider 2048
$rsa.PersistKeyInCsp = $false
[IO.File]::WriteAllText($privada, $rsa.ToXmlString($true))
[IO.File]::WriteAllText($publica, $rsa.ToXmlString($false))
$rsa.Dispose()

# Solo tu usuario puede leer la llave privada
& icacls.exe $privada /inheritance:r /grant:r ($env:USERNAME + ':(R,W)') | Out-Null

Write-Host "Llave privada: $privada  (RESPALDALA en una USB; no la subas a Git)"
Write-Host "Llave publica: $publica  (esta si va al repositorio)"
