param(
    [string] $HostName = "192.168.112.30",
    [int] $Port = 28862,
    [string] $UserName = "startseite",
    [string] $TargetPath = "/var/www/html"
)

$ErrorActionPreference = "Stop"

$sourceRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$impressumPath = Join-Path $sourceRoot "impressum.php"
$datenschutzPath = Join-Path $sourceRoot "datenschutz.php"

if (!(Test-Path -LiteralPath $impressumPath)) {
    throw "Lokale Datei fehlt: $impressumPath"
}

if (!(Test-Path -LiteralPath $datenschutzPath)) {
    throw "Lokale Datei fehlt: $datenschutzPath"
}

scp -P $Port $impressumPath "${UserName}@${HostName}:${TargetPath}/impressum.php"
scp -P $Port $datenschutzPath "${UserName}@${HostName}:${TargetPath}/datenschutz.php"

Write-Host "Impressum und Datenschutz wurden hochgeladen."
