# kiem-tra-db-dev.ps1
# Kiem tra moi lenh artisan/serve se doc DB nao KHI chay o che do dev-test,
# TRUOC KHI thuc hien bat ky thao tac GHI nao len doan_cnw_dev.
#
# Cach dung (PowerShell, chay tu bat ky thu muc nao):
#   .\scripts\windows\kiem-tra-db-dev.ps1
#
# Cach chay dung cho dev-test (sau khi script nay DAT):
#   $env:APP_ENV = 'dev-test'   # artisan se nap .env.dev-test (DB=doan_cnw_dev)
#   php artisan serve --port=8899
#   php artisan migrate / bat ky lenh GHI nao
#   Remove-Item Env:APP_ENV     # khi xong, de tranh anh huong cua so khac
#
# Ket thuc: 0 = DB dung doan_cnw_dev; 1 = DUNG LAI, khong duoc ghi.

$ErrorActionPreference = 'Continue'
$repo = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$loi = 0

if (-not (Test-Path (Join-Path $repo '.env.dev-test'))) {
    Write-Host "  [LOI]  Khong tim thay .env.dev-test trong $repo" -ForegroundColor Red
    Write-Host "         Tao file nay (gitignored) voi DB_DATABASE=doan_cnw_dev, APP_ENV=dev-test." -ForegroundColor Red
    exit 1
}

# APP_ENV=dev-test khien artisan nap .env.dev-test thay vi .env.
$envCu = $env:APP_ENV
$env:APP_ENV = 'dev-test'
try {
    Push-Location $repo
    $dbLine = php artisan tinker --execute="echo config('database.connections.mysql.database');" 2>$null |
        Where-Object { $_ -match '^doan_cnw' } | Select-Object -Last 1
    $envLine = php artisan tinker --execute="echo app()->environment();" 2>$null |
        Where-Object { $_ -match '^\S+$' } | Select-Object -Last 1
    Pop-Location
    $db = ($dbLine | Out-String).Trim()
    $appEnv = ($envLine | Out-String).Trim()
} finally {
    if ($null -eq $envCu) { Remove-Item Env:APP_ENV -ErrorAction SilentlyContinue }
    else { $env:APP_ENV = $envCu }
}

if ($appEnv -eq 'dev-test') {
    Write-Host "  [OK]   APP_ENV = dev-test" -ForegroundColor Green
} else {
    Write-Host "  [LOI]  APP_ENV = '$appEnv' (mong doi dev-test - .env.dev-test co the khong duoc nap)" -ForegroundColor Red
    $loi++
}

if ($db -eq 'doan_cnw_dev') {
    Write-Host "  [OK]   DB ma artisan ket noi = doan_cnw_dev" -ForegroundColor Green
} else {
    Write-Host "  [LOI]  DB ma artisan ket noi = '$db' (mong doi doan_cnw_dev - TUYET DOI khong duoc la doan_cnw)" -ForegroundColor Red
    $loi++
}

Write-Host ''
if ($loi -eq 0) {
    Write-Host 'DAT - co the chay artisan/serve o che do dev-test.' -ForegroundColor Green
    exit 0
} else {
    Write-Host "CO $loi KIEM TRA THAT BAI - KHONG duoc ghi du lieu." -ForegroundColor Red
    exit 1
}
