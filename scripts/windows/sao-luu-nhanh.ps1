# sao-luu-nhanh.ps1 - Sao luu nhanh doan_cnw ra backups/ cho buoi tap / buoi bao ve.
# AN TOAN: chi doc .env de KIEM TRA DB=doan_cnw, khong ghi gi len DB.
# Chay tu bat ky thu muc nao:  powershell -File scripts\windows\sao-luu-nhanh.ps1
# Ket qua: file .sql trong backups/ + kiem chung co bang users. KHONG tu dong khoi phuc.

$ErrorActionPreference = 'Stop'
$repo = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$envFile = Join-Path $repo '.env'

# 1. Doc DB_DATABASE tu .env (dong DB_DATABASE=...)
$db = $null
foreach ($line in Get-Content $envFile) {
    if ($line -match '^\s*DB_DATABASE\s*=\s*(.+?)\s*$') { $db = $Matches[1].Trim('"', "'"); break }
}
if ($db -ne 'doan_cnw') {
    Write-Host "[LOI] .env DB_DATABASE='$db' - chi cho phep sao luu doan_cnw. DUNG." -ForegroundColor Red
    exit 1
}
Write-Host "[OK] DB dich = doan_cnw" -ForegroundColor Green

# 2. Tim mysqldump: uu tien DB_DUMP_BINARY trong .env, roi PATH
$dbDumpEnv = $null
foreach ($line in Get-Content $envFile) {
    if ($line -match '^\s*DB_DUMP_BINARY\s*=\s*(.+?)\s*$') { $dbDumpEnv = $Matches[1].Trim('"', "'"); break }
}
$cmd = Get-Command mysqldump -ErrorAction SilentlyContinue
$candidates = @($dbDumpEnv, $cmd.Source) | Where-Object { $_ }
$dump = $candidates | Where-Object { Test-Path $_ } | Select-Object -First 1
if (-not $dump) { Write-Host '[LOI] Khong tim thay mysqldump (dat DB_DUMP_BINARY trong .env hoac cai vao PATH)' -ForegroundColor Red; exit 1 }

# 3. Dump co dau thoi gian
$ts = Get-Date -Format 'yyyyMMdd_HHmmss'
$dir = Join-Path $repo 'backups'
New-Item -ItemType Directory -Force -Path $dir | Out-Null
$out = Join-Path $dir "doan_cnw_sao_luu_${ts}.sql"

& $dump -u root --single-transaction --routines --triggers doan_cnw > $out
$sizeThatBai = 0
if (Test-Path $out) { $sizeThatBai = (Get-Item $out).Length }
if ($LASTEXITCODE -ne 0 -or $sizeThatBai -lt 10000) {
    Write-Host "[LOI] Dump that bai (exit=$LASTEXITCODE, size=$sizeThatBai)" -ForegroundColor Red
    exit 1
}

# 4. Kiem chung dump co bang users
$coUsers = Select-String -Path $out -Pattern 'CREATE TABLE `users`' -Quiet
if (-not $coUsers) {
    Write-Host '[LOI] Dump KHONG co CREATE TABLE `users` - ban sao luu khong day du, KHONG dung.' -ForegroundColor Red
    exit 1
}

$sizeKB = [math]::Round((Get-Item $out).Length / 1KB)
Write-Host ''
Write-Host "SAO LUU XONG: $out" -ForegroundColor Green
Write-Host "  Kich thuoc: $sizeKB KB | DB: doan_cnw | co bang users: OK"
Write-Host '  Khoi phuc: xem docs/SO_TAY_TRIEN_KHAI.md muc "Runbook sao luu & khoi phuc".'
exit 0
