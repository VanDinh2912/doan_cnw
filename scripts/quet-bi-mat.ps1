# quet-bi-mat.ps1 — Quet nhanh secret/PII truoc khi commit/push.
#
# Quet phan thay doi: mac dinh `git diff --staged` (staged), hoac
# `-PhamVi commits` de quet `origin/main..HEAD` (truoc khi push).
# Thoat ma 1 neu phat hien mau cam. KHONG in gia tri nhay cam — chi in
# ten file + loai mau.
#
# Cach dung (PowerShell, tu goc repo):
#   .\scripts\quet-bi-mat.ps1                 # quet staged
#   .\scripts\quet-bi-mat.ps1 -PhamVi commits # quet origin/main..HEAD
#
# LUU Y: day la lop ho tro, khong thay the review thu cong.

param(
    [ValidateSet('staged', 'commits')]
    [string]$PhamVi = 'staged'
)

$ErrorActionPreference = 'Stop'
$repo = Split-Path -Parent $PSScriptRoot
Set-Location $repo

# Lay diff dang chuoi text (them moi dong '+', bo qua '+++ b/...')
if ($PhamVi -eq 'staged') {
    $diff = git diff --staged -U0
    $nguon = 'staged (git diff --staged)'
} else {
    $base = git merge-base origin/main HEAD 2>$null
    if (-not $base) { $base = 'origin/main' }
    $diff = git diff "$base..HEAD" -U0
    $nguon = "commits $base..HEAD"
}
if (-not $diff) { Write-Host "Khong co thay doi de quet ($nguon)."; exit 0 }

# So tai khoan ngan hang that tu .env — KHONG in gia tri
$bank = $null
$envFile = Join-Path $repo '.env'
if (Test-Path $envFile) {
    $m = Select-String -Path $envFile -Pattern '^\s*POS_BANK_ACCOUNT\s*=' | Select-Object -First 1
    if ($m) { $bank = ($m.Line -split '=', 2)[1].Trim().Trim('"').Trim("'") }
}
$bankDigits = $null
if ($bank) { $bankDigits = ($bank -replace '[^0-9]', '') }

# Cac mau cam: ten | regex. Chi bao ten mau + file, khong in gia tri.
$patterns = @(
    @{ Ten = 'Google OAuth secret';   Re = 'GOCSPX-[A-Za-z0-9_-]{10,}' },
    @{ Ten = 'AWS access key';        Re = 'AKIA[0-9A-Z]{16}' },
    @{ Ten = 'APP_KEY base64';        Re = 'APP_KEY=base64:[A-Za-z0-9+/=]{20,}' },
    @{ Ten = 'Telegram bot token';    Re = '[0-9]{8,10}:[A-Za-z0-9_-]{30,}' },
    @{ Ten = 'MAIL_PASSWORD co gia tri'; Re = 'MAIL_PASSWORD=\S' },
    @{ Ten = 'SEPAY key/token that';  Re = 'SEPAY_(WEBHOOK_API_KEY|API_TOKEN)=[A-Za-z0-9_-]{8,}' },
    @{ Ten = 'DB_PASSWORD co gia tri';   Re = 'DB_PASSWORD=\S' },
    @{ Ten = 'duong dan may ca nhan'; Re = '[A-Za-z]:\\(Study|Users\\vandi|xampp)' },
    @{ Ten = 'email sinh vien that'; Re = '[A-Za-z0-9._%+-]+@student\.agu\.edu\.vn' },
    @{ Ten = 'PIN 6 so trong code';  Re = "'pin'\s*=>\s*'[0-9]{6}'" }
)

$phatHien = @()
$fileHienTai = ''
foreach ($line in $diff) {
    if ($line -match '^\+\+\+ b/(.+)$') { $fileHienTai = $Matches[1]; continue }
    # Bo qua chinh file scanner (chua dinh nghia mau, gay false positive)
    if ($fileHienTai -eq 'scripts/quet-bi-mat.ps1') { continue }
    if (-not $line.StartsWith('+') -or $line.StartsWith('+++')) { continue }
    $nd = $line.Substring(1)
    foreach ($p in $patterns) {
        # PIN 6 so dang fixture test ('pin' => '246813'...) khong phai bi mat
        if ($p.Ten -eq 'PIN 6 so trong code' -and $fileHienTai -like 'tests/*') { continue }
        if ($nd -match $p.Re) {
            $phatHien += "$fileHienTai : $($p.Ten)"
            break
        }
    }
    # So tai khoan: so dang ro (co the co dau cach/gach) hoac chi chuoi so
    if ($bank -and $bank.Length -ge 6) {
        $escaped = [regex]::Escape($bank)
        $escapedDigits = [regex]::Escape($bankDigits)
        if ($nd -match $escaped -or ($bankDigits.Length -ge 8 -and $nd -match "\b$escapedDigits\b")) {
            $phatHien += "$fileHienTai : SO TAI KHOAN NGAN HANG (POS_BANK_ACCOUNT)"
        }
    }
}

$phatHien = $phatHien | Sort-Object -Unique
if ($phatHien.Count -gt 0) {
    Write-Host "PHAT HIEN $($phatHien.Count) mau cam trong ${nguon}:" -ForegroundColor Red
    $phatHien | ForEach-Object { Write-Host "  - $_" -ForegroundColor Yellow }
    Write-Host "Kiem tra thu cong truoc khi commit/push. Khong in gia tri o day." -ForegroundColor Yellow
    exit 1
}
Write-Host "OK - khong phat hien mau cam trong ${nguon}." -ForegroundColor Green
exit 0
