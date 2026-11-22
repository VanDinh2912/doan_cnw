# cong-tac-pin-prod.ps1
# Bat/tat Kiem PIN + Ma duyet hai-nguoi (POS_PIN_BAT, POS_MA_DUYET_BAT)
# tren database PROD (doan_cnw) mot cach an toan.
#
# Che do:
#   -Che trang-thai : CHI DOC. Bao trang thai 2 co + chay toan bo kiem tra.
#   -Che tat        : BAT BUOC qua du kiem tra nhu -Che bat, roi tat ca 2 co.
#   -Che bat        : BAT BUOC dat het kiem tra moi duoc bat.
#
# Kiem tra truoc khi bat (bat buoc dat):
#   1. Khong co APP_ENV trong phien; artisan dang ket noi doan_cnw.
#   2. users co du cot pin_*; bang yeu_cau_duyet da ton tai.
#   3. MOI admin co pin_doi_luc moi hon moc lo PIN (nhat ky pin_dat
#      nguon=console, uu tien activity_logs id=463, fallback dong moi nhat).
#   4. Moi thu ngan/thu kho DANG HOAT DONG co PIN rieng cho muc M1
#      (thieu thi WARN kem email da che, khong in email day du).
#
# Sau khi sua .env: neu production co config cache phai chay lai
#   php artisan config:cache
# Script KHONG tu chay config:cache — in nhac nho de nguoi van hanh quyet dinh.
#
# Cach dung (PowerShell, tu thu muc goc repo):
#   .\scripts\windows\cong-tac-pin-prod.ps1 -Che trang-thai
#   .\scripts\windows\cong-tac-pin-prod.ps1 -Che bat
#   .\scripts\windows\cong-tac-pin-prod.ps1 -Che tat
#
# Ket thuc: 0 = OK; 1 = co loi / tu choi bat.

param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('bat', 'tat', 'trang-thai')]
    [string]$Che
)

$ErrorActionPreference = 'Continue'
$repo = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$envFile = Join-Path $repo '.env'
$fail = 0
$warn = 0

function Loi([string]$msg) {
    Write-Host "  [LOI] $msg" -ForegroundColor Red
    $script:fail++
}
function CanhBao([string]$msg) {
    Write-Host "  [CANH BAO] $msg" -ForegroundColor Yellow
    $script:warn++
}
function Ok([string]$msg) {
    Write-Host "  [OK]  $msg" -ForegroundColor Green
}
function Info([string]$msg) {
    Write-Host "  [INFO] $msg" -ForegroundColor Cyan
}

# Doc mot dong KEY=... tu .env (bo qua comment/dong trong)
function Doc-Co([string]$key) {
    if (-not (Test-Path $envFile)) { return $null }
    $m = Select-String -Path $envFile -Pattern "^\s*$key\s*=" | Select-Object -First 1
    if (-not $m) { return $null }
    return ($m.Line -split '=', 2)[1].Trim().Trim('"').Trim("'")
}

Push-Location $repo
try {
    Write-Host "=== Cong tac PIN + Ma duyet tren PROD (che do: $Che) ===" -ForegroundColor White

    # ---------- DOC trang thai hien tai (moi che do) ----------
    $pinBat = Doc-Co 'POS_PIN_BAT'
    $maDuyetBat = Doc-Co 'POS_MA_DUYET_BAT'
    Info "POS_PIN_BAT      = $(if ($null -eq $pinBat) {'(mac dinh false)'} else {$pinBat})"
    Info "POS_MA_DUYET_BAT  = $(if ($null -eq $maDuyetBat) {'(mac dinh true)'} else {$maDuyetBat})"

    if ($Che -eq 'trang-thai') {
        # Chi doc: van chay nhanh cac kiem tra doc duoi day roi thoat.
        $pinHieuLuc = ($pinBat -eq 'true')
    } else {
        $pinHieuLuc = ($Che -eq 'bat')
    }

    # ---------- Kiem tra moi truong ----------
    if (-not [string]::IsNullOrWhiteSpace($env:APP_ENV)) {
        Loi "Phien dang co APP_ENV='$($env:APP_ENV)'. Go bo truoc: Remove-Item Env:APP_ENV"
    } else {
        Ok 'Khong co APP_ENV trong phien'
    }

    $db = (php artisan tinker --execute="echo config('database.connections.'.config('database.default').'.database');" 2>$null |
        Where-Object { $_ -match '^\S+$' } | Select-Object -Last 1 | Out-String).Trim()
    Write-Host "  Database artisan dang dung: $db"
    if ($db -ne 'doan_cnw') {
        Loi "DB = '$db' (mong doi doan_cnw). Dung lai - khong cham DB khac."
    } else {
        Ok 'DB = doan_cnw'
    }

    if ($fail -gt 0) {
        Write-Host "`n$fail KIEM TRA THAT BAI - dung lai." -ForegroundColor Red
        exit 1
    }

    # ---------- Schema ----------
    $cotPin = @('pin_hash', 'pin_sai_lien_tiep', 'pin_khoa_den', 'pin_doi_luc', 'bat_buoc_doi_pin')
    $thieu = php artisan tinker --execute="echo count(array_filter(['pin_hash','pin_sai_lien_tiep','pin_khoa_den','pin_doi_luc','bat_buoc_doi_pin'], fn(`$c) => Illuminate\Support\Facades\Schema::hasColumn('users', `$c)));" 2>$null |
        Where-Object { $_ -match '^\d+$' } | Select-Object -Last 1 | Out-String
    $soCot = [int](($thieu).Trim())
    if ($soCot -lt 5) {
        Loi "users chi co $soCot/5 cot pin_* (thieu migration PIN?)"
    } else {
        Ok 'users du 5 cot pin_*'
    }

    $coBangYc = php artisan tinker --execute="echo Illuminate\Support\Facades\Schema::hasTable('yeu_cau_duyet') ? 'CO' : 'KHONG';" 2>$null |
        Where-Object { $_ -match '^(CO|KHONG)$' } | Select-Object -Last 1 | Out-String
    $coBangYc = $coBangYc.Trim()
    if ($coBangYc -eq 'CO') {
        Ok 'bang yeu_cau_duyet da ton tai'
    } elseif ($Che -eq 'bat') {
        Loi 'thieu bang yeu_cau_duyet - can migrate tren doan_cnw TRUOC khi bat ma duyet'
    } else {
        CanhBao 'thieu bang yeu_cau_duyet - can migrate truoc khi bat ma duyet (hien khong anh huong neu PIN dang tat)'
    }

    # ---------- Moc lo PIN: activity_logs id=463 (pin_dat, console) ----------
    # Fallback: dong pin_dat nguon=console moi nhat. In moc thoi gian, khong PII.
    $mocLo = php artisan tinker --execute="`$r = App\Models\NhatKyHoatDong::whereKey(463)->first(); if (!`$r) { `$r = App\Models\NhatKyHoatDong::where('hanh_dong','pin_dat')->where('nguon','console')->latest('id')->first(); } echo `$r ? `$r->created_at->toDateTimeString() : 'KHONG_CO';" 2>$null |
        Where-Object { $_ -match '^\d{4}-\d{2}-\d{2}' -or $_ -match '^KHONG_CO$' } | Select-Object -Last 1 | Out-String
    $mocLo = $mocLo.Trim()
    if ($mocLo -eq 'KHONG_CO' -or $mocLo -eq '') {
        CanhBao 'khong tim thay nhat ky pin_dat (console) - khong the doi chieu moc lo PIN; van cho phep doc trang thai nhung -Che bat se tu choi'
        $mocLoOk = $false
    } else {
        Info "moc lo PIN (pin_dat console): $mocLo"
        $mocLoOk = $true
    }

    # ---------- Admin: pin_doi_luc phai moi hon moc lo ----------
    if ($pinHieuLuc -and $mocLoOk) {
        $adminChuaDoi = php artisan tinker --execute="echo App\Models\User::where('role','admin')->get()->filter(fn(`$u) => !`$u->pin_doi_luc || `$u->pin_doi_luc->lte('$mocLo'))->pluck('id')->implode(',');" 2>$null |
            Where-Object { $_ -match '^\d+(,\d+)*$|^$' } | Select-Object -Last 1 | Out-String
        $adminChuaDoi = $adminChuaDoi.Trim()
        if ($adminChuaDoi -eq '') {
            Ok 'moi admin da doi PIN sau moc lo'
        } else {
            Loi "admin chua doi PIN sau moc lo (user id: $adminChuaDoi) - chay dat-pin-prod.ps1 truoc"
        }
    } elseif ($pinHieuLuc) {
        Info 'bo qua doi chieu moc lo PIN (khong co moc) - -Che bat se bi chan'
    }

    # ---------- NV thu ngan/thu kho dang hoat dong phai co PIN ----------
    if ($pinHieuLuc) {
        $thieuPin = php artisan tinker --execute="echo App\Models\User::whereIn('role',['thungan','thukho'])->where(function(`$q){`$q->whereNull('khoa_luc')->orWhere('khoa_luc','<=',now());})->whereNull('pin_hash')->get()->map(fn(`$u)=>preg_replace('/^(.).*(@.*)$/','$1***$2',`$u->email))->implode(',');" 2>$null |
            Where-Object { $_ -match '@|^$' } | Select-Object -Last 1 | Out-String
        $thieuPin = $thieuPin.Trim()
        if ($thieuPin -eq '') {
            Ok 'moi thu ngan/thu kho dang hoat dong deu co PIN'
        } else {
            CanhBao "NV thieu PIN (email che): $thieuPin - ho se khong qua duoc muc M1"
        }
    }

    # ---------- Trang thai che do ----------
    if ($Che -eq 'trang-thai') {
        Write-Host "`nCHE DO DOC: khong thay doi gi. fail=$fail warn=$warn" -ForegroundColor $(if ($fail -gt 0) {'Red'} else {'Green'})
        exit $(if ($fail -gt 0) {1} else {0})
    }

    # ---------- Ghi .env ----------
    $pinMoi = if ($Che -eq 'bat') { 'true' } else { 'false' }
    $maMoi = if ($Che -eq 'bat') { 'true' } else { 'false' }

    if ($Che -eq 'bat') {
        # Dieu kien bat: khong loi + co moc lo + bang da co
        if ($fail -gt 0 -or -not $mocLoOk -or $coBangYc -ne 'CO') {
            Write-Host "`nTU CHOI BAT: con $fail loi (hoac thieu moc lo/bang). Khong sua .env." -ForegroundColor Red
            exit 1
        }
    } else {
        # Che tat: van yeu cau DB dung, nhung khong can cac dieu kien bat
        if ($fail -gt 0) {
            Write-Host "`nTU CHOI TAT: $fail kiem tra moi truong that bai." -ForegroundColor Red
            exit 1
        }
    }

    if (-not (Test-Path $envFile)) {
        Loi ".env khong ton tai tai $envFile"
        exit 1
    }

    $backup = "$envFile.bak-congtac-$(Get-Date -Format 'yyyyMMdd-HHmmss')"
    Copy-Item $envFile $backup
    Info "da sao luu .env -> $backup"

    $noiDung = Get-Content $envFile -Raw
    foreach ($cap in @(@('POS_PIN_BAT', $pinMoi), @('POS_MA_DUYET_BAT', $maMoi))) {
        $key = $cap[0]; $val = $cap[1]
        if ($noiDung -match "(?m)^\s*$key\s*=.*$") {
            $noiDung = $noiDung -replace "(?m)^\s*$key\s*=.*$", "$key=$val"
        } else {
            $noiDung = $noiDung.TrimEnd() + "`r`n$key=$val`r`n"
        }
    }
    Set-Content -Path $envFile -Value $noiDung -NoNewline -Encoding UTF8

    Write-Host "`nDa dat: POS_PIN_BAT=$pinMoi, POS_MA_DUYET_BAT=$maMoi" -ForegroundColor Green
    Write-Host "NHAC: neu production da config:cache, chay 'php artisan config:cache' de co hieu luc." -ForegroundColor Yellow
    Write-Host "Kiem tra lai: .\scripts\windows\cong-tac-pin-prod.ps1 -Che trang-thai" -ForegroundColor Yellow
    exit 0
} finally {
    Pop-Location
}
