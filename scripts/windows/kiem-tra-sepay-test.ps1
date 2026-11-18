# kiem-tra-sepay-test.ps1
# Kiem tra instance SePay test (chi-webhook, DB=doan_cnw_dev) TRUOC KHI khai bao
# URL tunnel voi SePay, va kiem lai qua URL cong khai sau moi lan doi tunnel.
#
# Cach dung (PowerShell):
#   .\scripts\windows\kiem-tra-sepay-test.ps1                      # chi kiem local 127.0.0.1:8082
#   .\scripts\windows\kiem-tra-sepay-test.ps1 -TunnelUrl https://xxx.trycloudflare.com
#
# Ket thuc: script tu khoi phuc APP_ENV; neu chay trong cua so serve thi nho
#   Remove-Item Env:APP_ENV  sau khi xong (xem docs/SO_TAY_TRIEN_KHAI.md).

param(
    [string]$TunnelUrl = ''
)

$ErrorActionPreference = 'Continue'
$repo = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$loi = 0

function Get-StatusCode {
    param([string]$Method, [string]$Url, [string]$Body = $null)
    try {
        $params = @{
            Method      = $Method
            Uri         = $Url
            UseBasicParsing = $true
            TimeoutSec  = 15
        }
        if (-not [string]::IsNullOrEmpty($Body)) {
            $params['Body'] = $Body
            $params['ContentType'] = 'application/json'
        }
        $r = Invoke-WebRequest @params
        return [int]$r.StatusCode
    } catch {
        if ($_.Exception.Response) { return [int]$_.Exception.Response.StatusCode }
        return -1
    }
}

function Test-Mot($ten, $ketQua, $mongDoi) {
    if ($ketQua -eq $mongDoi) {
        Write-Host "  [OK]   $ten -> $ketQua" -ForegroundColor Green
    } else {
        Write-Host "  [LOI]  $ten -> $ketQua (mong doi $mongDoi)" -ForegroundColor Red
        $script:loi++
    }
}

function Kiem-TraiMien($base) {
    Write-Host "Kiem tra tren: $base" -ForegroundColor Cyan
    Test-Mot 'GET / (phai 404)'          (Get-StatusCode 'GET' "$base/") 404
    Test-Mot 'GET /bai-viet (phai 404)'  (Get-StatusCode 'GET' "$base/bai-viet") 404
    Test-Mot 'POST webhook khong key (phai 401)' `
        (Get-StatusCode 'POST' "$base/api/webhook/thanhtoan" '{}') 401
}

# --- 1. Kiem tra cuc bo (bat buoc server dev dang chay) ---
Kiem-TraiMien 'http://127.0.0.1:8082'

# --- 2. Xac nhan instance dang doc .env.sepay-test (DB=doan_cnw_dev) ---
# APP_ENV=sepay-test khien artisan nap .env.sepay-test thay vi .env.
$envCu = $env:APP_ENV
$env:APP_ENV = 'sepay-test'
try {
    Push-Location $repo
    $dbLine = php artisan tinker --execute="echo config('database.connections.mysql.database');" 2>$null |
        Where-Object { $_ -match '^doan_cnw' } | Select-Object -Last 1
    Pop-Location
    $db = ($dbLine | Out-String).Trim()
} finally {
    if ($null -eq $envCu) { Remove-Item Env:APP_ENV -ErrorAction SilentlyContinue }
    else { $env:APP_ENV = $envCu }
}

if ($db -eq 'doan_cnw_dev') {
    Write-Host "  [OK]   DB instance = doan_cnw_dev" -ForegroundColor Green
} else {
    Write-Host "  [LOI]  DB instance = '$db' (mong doi doan_cnw_dev - TUYET DOI khong duoc la doan_cnw)" -ForegroundColor Red
    $loi++
}

# --- 3. Kiem lai qua URL tunnel cong khai (neu co) ---
if ($TunnelUrl -ne '') {
    $base = $TunnelUrl.TrimEnd('/')
    Kiem-TraiMien $base
}

Write-Host ''
if ($loi -eq 0) {
    if ($TunnelUrl -ne '') {
        Write-Host 'TAT CA KIEM TRA DAT - co the khai bao URL nay voi SePay.' -ForegroundColor Green
    } else {
        Write-Host 'TAT CA KIEM TRA CUC BO DAT.' -ForegroundColor Green
    }
    exit 0
} else {
    Write-Host "CO $loi KIEM TRA THAT BAI." -ForegroundColor Red
    if ($TunnelUrl -ne '') {
        Write-Host 'KHONG khai bao URL nay voi SePay. Kiem tra lai instance/tunnel.' -ForegroundColor Red
    }
    exit 1
}
