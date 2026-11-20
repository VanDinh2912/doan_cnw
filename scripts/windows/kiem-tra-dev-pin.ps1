# Kiem tra 2 ban dev PIN: 8899 = PIN bat (dev-test), 8898 = gia lap prod (dev-cu)
# Chi doc, khong in secret. Chay: powershell -File scripts/windows/kiem-tra-dev-pin.ps1
$ErrorActionPreference = 'Continue'
$root = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$mysql = 'D:\Study\xampp\bin\mysql\mysql8.4.7\bin\mysql.exe'
$fail = 0

function Doc-Env($file) {
    $h = @{}
    Get-Content $file | ForEach-Object {
        if ($_ -match '^\s*([A-Z_]+)\s*=\s*(.*)$') { $h[$Matches[1]] = $Matches[2].Trim() }
    }
    return $h
}
function Test-Port($port, $ten) {
    $c = Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($c) { Write-Host "[OK]   $ten :$port dang lang nghe (PID $($c.OwningProcess))" }
    else { Write-Host "[FAIL] $ten :$port KHONG chay"; $script:fail++ }
}
function Test-Env($file, $port, $ten, $dbMong, $pinMong, $coPinMong) {
    if (-not (Test-Path $file)) { Write-Host "[FAIL] $ten thieu $file"; $script:fail++; return }
    $e = Doc-Env $file
    $ok = $true
    if ($e['DB_DATABASE'] -ne $dbMong) { Write-Host "[FAIL] $ten DB_DATABASE=$($e['DB_DATABASE']) (mong $dbMong)"; $ok=$false }
    $pin = ($e['POS_PIN_BAT'] -eq 'true')
    $pinMongStr = if ($pinMong) { 'true' } else { 'false' }
    if ($pin -ne $pinMong) { Write-Host "[FAIL] $ten POS_PIN_BAT=$($e['POS_PIN_BAT']) (mong $pinMongStr)"; $ok=$false }
    $cols = & $mysql -h 127.0.0.1 -u root -N -e "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$dbMong' AND TABLE_NAME='users' AND (COLUMN_NAME LIKE 'pin_%' OR COLUMN_NAME='bat_buoc_doi_pin')" 2>$null
    if ($coPinMong -and [int]$cols -lt 5) { Write-Host "[FAIL] $ten $dbMong thieu cot pin_* (co $cols)"; $ok=$false }
    if (-not $coPinMong -and [int]$cols -gt 0) { Write-Host "[FAIL] $ten $dbMong CO cot pin_* ($cols)"; $ok=$false }
    if ($ok) { Write-Host "[OK]   $ten :$port -> $dbMong, POS_PIN_BAT=$($e['POS_PIN_BAT']), pin_cols=$cols" }
    else { $script:fail++ }
}

Write-Host "== Ban 1: PIN BAT (dev-test) =="
Test-Port 8899 'Ban-PIN'
Test-Env "$root\.env.dev-test" 8899 'Ban-PIN' 'doan_cnw_dev' $true $true
Write-Host "== Ban 2: gia lap prod (dev-cu) =="
Test-Port 8898 'Ban-prod-gia'
Test-Env "$root\.env.dev-cu" 8898 'Ban-prod-gia' 'doan_cnw_dev_cu' $false $false
Write-Host "== Tien trinh artisan serve =="
Get-CimInstance Win32_Process -Filter "Name='php.exe'" | Where-Object { $_.CommandLine -match 'artisan serve' } |
    ForEach-Object { Write-Host ("  PID {0}: {1}" -f $_.ProcessId, ($_.CommandLine -replace '.*artisan','artisan')) }
if ($fail -eq 0) { Write-Host "`nTAT CA OK" -ForegroundColor Green } else { Write-Host "`nCO $fail LOI" -ForegroundColor Red }
