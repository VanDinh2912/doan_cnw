# dat-pin-prod.ps1
# Dat/doi PIN cho NHAN VIEN tren database PROD (doan_cnw) mot cach an toan.
#
# Cac buoc kiem tra (bat buoc dat het moi goi artisan):
#   1. Phien hien tai KHONG co APP_ENV (tranh nap nham .env.*).
#   2. Artisan dang ket noi dung database doan_cnw (in ten DB; sai thi dung).
#   3. Bang users co du 5 cot pin_*.
#   4. Tai khoan dich ton tai va khong phai role khachhang.
# Sau do goi: php artisan nguoidung:dat-pin <email>
#   -> nguoi dung TU GO PIN an (6 so, nhap 2 lan). Script khong nhan/khong in PIN.
# Chi in "Da dat PIN cho <email>" khi artisan tra ve 0; nguoc lai in loi ngan.
#
# Cach dung (PowerShell, tu thu muc goc repo):
#   .\scripts\windows\dat-pin-prod.ps1 -Email admin@example.com
#   .\scripts\windows\dat-pin-prod.ps1 -Email admin@example.com -DryRun   # chi kiem tra
#
# Ket thuc: 0 = OK; 1 = mot kiem tra that bai / artisan bao loi.

param(
    [Parameter(Mandatory = $true)]
    [string]$Email,

    [switch]$DryRun
)

$ErrorActionPreference = 'Continue'
$repo = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$fail = 0

function Loi([string]$msg) {
    Write-Host "  [LOI] $msg" -ForegroundColor Red
    $script:fail++
}
function Ok([string]$msg) {
    Write-Host "  [OK]  $msg" -ForegroundColor Green
}

Push-Location $repo
try {
    # 1) Khong duoc co APP_ENV trong phien (se nap .env.<env> thay vi .env)
    if (-not [string]::IsNullOrWhiteSpace($env:APP_ENV)) {
        Loi "Phien dang co APP_ENV='$($env:APP_ENV)'. Go bo truoc: Remove-Item Env:APP_ENV"
    } else {
        Ok 'Khong co APP_ENV trong phien'
    }

    # 2) Artisan phai dang tro doan_cnw
    $db = (php artisan tinker --execute="echo config('database.connections.'.config('database.default').'.database');" 2>$null |
        Where-Object { $_ -match '^\S+$' } | Select-Object -Last 1 | Out-String).Trim()
    Write-Host "  Database artisan dang dung: $db"
    if ($db -ne 'doan_cnw') {
        Loi "DB = '$db' (mong doi doan_cnw). Dung lai - khong dat PIN len DB khac."
    } else {
        Ok 'DB = doan_cnw'
    }

    # 3) users phai co du 5 cot pin_*
    $cotPin = @('pin_hash', 'pin_sai_lien_tiep', 'pin_khoa_den', 'pin_doi_luc', 'bat_buoc_doi_pin')
    $thieu = php artisan tinker --execute="echo count(array_filter(['pin_hash','pin_sai_lien_tiep','pin_khoa_den','pin_doi_luc','bat_buoc_doi_pin'], fn(`$c) => Illuminate\Support\Facades\Schema::hasColumn('users', `$c)));" 2>$null |
        Where-Object { $_ -match '^\d+$' } | Select-Object -Last 1 | Out-String
    $soCot = [int](($thieu).Trim())
    if ($soCot -lt 5) {
        Loi "users chi co $soCot/5 cot pin_* (thieu migration PIN?)"
    } else {
        Ok 'users du 5 cot pin_*'
    }

    # 4) Tai khoan dich: ton tai + khong phai khachhang (in role, khong in PII)
    $emailJs = $Email.Replace('\', '\\').Replace("'", "\'")
    $role = (php artisan tinker --execute="`$u = App\Models\User::where('email', '$($emailJs)')->first(); echo `$u ? `$u->role : 'KHONG_TIM_THAY';" 2>$null |
        Where-Object { $_ -match '^\S+$' } | Select-Object -Last 1 | Out-String).Trim()
    if ($role -eq 'KHONG_TIM_THAY' -or $role -eq '') {
        Loi "Khong tim thay tai khoan '$Email'"
    } elseif ($role -eq 'khachhang') {
        Loi "Tai khoan '$Email' la khachhang - khong dat PIN nhan vien"
    } else {
        Ok "Tai khoan '$Email' hop le (role: $role)"
    }

    if ($fail -gt 0) {
        Write-Host "`n$fail KIEM TRA THAT BAI - khong dat PIN." -ForegroundColor Red
        exit 1
    }

    if ($DryRun) {
        Write-Host "`nDRY-RUN: moi kiem tra deu DAT. Chay lai khong co -DryRun de dat PIN." -ForegroundColor Cyan
        exit 0
    }

    Write-Host "`nSap dat PIN cho $Email tren doan_cnw."
    Write-Host "Artisan se hoi PIN 2 lan (ky tu an) - hay go truc tiep, khong qua script.`n"

    # Goi artisan that; an output cua no, chi giu exit code.
    # Nguoi dung tu go PIN vao stdin (secret() doc STDIN, khong qua output).
    php artisan nguoidung:dat-pin $Email *> $null
    $code = $LASTEXITCODE

    if ($code -eq 0) {
        Write-Host "Da dat PIN cho $Email" -ForegroundColor Green
        exit 0
    } else {
        Write-Host "Dat PIN that bai (artisan exit $code) - kiem tra lai." -ForegroundColor Red
        exit 1
    }
} finally {
    Pop-Location
}
