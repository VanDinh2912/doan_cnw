# cong-tac-pin-prod.ps1
# Bat/tat Kiem PIN + Ma duyet hai-nguoi (POS_PIN_BAT, POS_MA_DUYET_BAT)
# tren database PROD (doan_cnw) mot cach an toan.
#
# Che do:
#   -Che trang-thai : CHI DOC. Bao trang thai 2 co + chay toan bo kiem tra.
#   -Che tat        : BAT BUOC qua du kiem tra nhu -Che bat, roi tat ca 2 co.
#   -Che bat        : BAT BUOC dat het kiem tra moi duoc bat.
#   -TuKiemTra      : SELF-TEST logic tren sqlite tam (khong cham DB that).
#
# Du lieu kiem tra lay tu `php artisan pin:trang-thai` (JSON co cau truc,
# app/Services/KiemTraPinService) — KHONG parse chuoi artisan nua, khong
# nuot stderr: lenh loi/rong -> FAIL "khong kiem tra duoc".
#
# Kiem tra (bat buoc dat truoc khi -Che bat):
#   1. Khong co APP_ENV trong phien; artisan dang ket noi doan_cnw.
#   2. users co du 5 cot pin_*; bang yeu_cau_duyet da ton tai.
#   3. MOI admin co pin_hash VA pin_doi_luc LON HON moc lo PIN
#      (activity_logs pin_dat nguon=console, uu tien id=463).
#      BANG moc = chua doi sau su co = FAIL.
#   4. MOI thu ngan/thu kho DANG HOAT DONG (khoa_luc null/qua han) co
#      pin_hash — thieu la FAIL (khong qua duoc muc M1).
#
# Sau khi sua .env: neu production co config cache phai chay lai
#   php artisan config:cache
# Script KHONG tu chay config:cache — in nhac nho de nguoi van hanh quyet dinh.
#
# Cach dung (PowerShell, tu thu muc goc repo):
#   .\scripts\windows\cong-tac-pin-prod.ps1 -Che trang-thai
#   .\scripts\windows\cong-tac-pin-prod.ps1 -Che bat
#   .\scripts\windows\cong-tac-pin-prod.ps1 -Che tat
#   .\scripts\windows\cong-tac-pin-prod.ps1 -TuKiemTra
#
# Ket thuc: 0 = OK; 1 = co loi / tu choi bat / self-test that bai.

param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('bat', 'tat', 'trang-thai', 'TuKiemTra')]
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

# Chay artisan va lay JSON dong dau tien bat dau bang '{'.
# Tra $null neu exit != 0 hoac khong co JSON -> caller bao FAIL.
function Lay-TrangThai {
    $out = php artisan pin:trang-thai 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Host "  [LOI] artisan pin:trang-thai exit=$LASTEXITCODE" -ForegroundColor Red
        return $null
    }
    $dongJson = $out | Where-Object { $_ -match '^\s*\{' } | Select-Object -First 1
    if (-not $dongJson) {
        Write-Host "  [LOI] artisan pin:trang-thai khong tra JSON" -ForegroundColor Red
        return $null
    }
    try {
        return ($dongJson | ConvertFrom-Json)
    } catch {
        Write-Host "  [LOI] JSON khong parse duoc" -ForegroundColor Red
        return $null
    }
}

Push-Location $repo
try {
    # ---------- SELF-TEST: khong cham DB that ----------
    if ($Che -eq 'TuKiemTra') {
        Write-Host "=== SELF-TEST cong tac PIN (sqlite tam, khong cham DB that) ===" -ForegroundColor White
        php artisan pin:tu-kiem-tra
        exit $LASTEXITCODE
    }

    Write-Host "=== Cong tac PIN + Ma duyet tren PROD (che do: $Che) ===" -ForegroundColor White

    # ---------- DOC trang thai hien tai (moi che do) ----------
    $pinBat = Doc-Co 'POS_PIN_BAT'
    $maDuyetBat = Doc-Co 'POS_MA_DUYET_BAT'
    Info "POS_PIN_BAT      = $(if ($null -eq $pinBat) {'(mac dinh false)'} else {$pinBat})"
    Info "POS_MA_DUYET_BAT  = $(if ($null -eq $maDuyetBat) {'(mac dinh true)'} else {$maDuyetBat})"

    if ($Che -eq 'trang-thai') {
        # Chi doc: chay kiem tra khi tinh nang dang bat; -Che bat luon kiem tra.
        $pinHieuLuc = ($pinBat -eq 'true') -or ($Che -eq 'bat')
    } else {
        $pinHieuLuc = ($Che -eq 'bat') -or ($pinBat -eq 'true')
    }

    # ---------- Kiem tra moi truong ----------
    if (-not [string]::IsNullOrWhiteSpace($env:APP_ENV)) {
        Loi "Phien dang co APP_ENV='$($env:APP_ENV)'. Go bo truoc: Remove-Item Env:APP_ENV"
    } else {
        Ok 'Khong co APP_ENV trong phien'
    }

    if ($fail -gt 0) {
        Write-Host "`n$fail KIEM TRA THAT BAI - dung lai." -ForegroundColor Red
        exit 1
    }

    # ---------- JSON trang thai tu artisan ----------
    $tt = Lay-TrangThai
    if ($null -eq $tt) {
        Loi 'khong lay duoc trang thai tu artisan pin:trang-thai - khong the kiem tra'
        exit 1
    }
    Write-Host "  Database artisan dang dung: $($tt.db)"
    if ($tt.db -ne 'doan_cnw') {
        Loi "DB = '$($tt.db)' (mong doi doan_cnw). Dung lai - khong cham DB khac."
    } else {
        Ok 'DB = doan_cnw'
    }

    # ---------- Schema ----------
    if ([int]$tt.cot_pin -lt 5) {
        Loi "users chi co $($tt.cot_pin)/5 cot pin_* (thieu migration PIN?)"
    } else {
        Ok 'users du 5 cot pin_*'
    }

    if ($tt.bang_yc) {
        Ok 'bang yeu_cau_duyet da ton tai'
    } elseif ($Che -eq 'bat') {
        Loi 'thieu bang yeu_cau_duyet - can migrate tren doan_cnw TRUOC khi bat ma duyet'
    } else {
        CanhBao 'thieu bang yeu_cau_duyet - can migrate truoc khi bat ma duyet (hien khong anh huong neu PIN dang tat)'
    }

    if ($fail -gt 0) {
        Write-Host "`n$fail KIEM TRA THAT BAI - dung lai." -ForegroundColor Red
        exit 1
    }

    # ---------- Moc lo PIN ----------
    $mocLoOk = ($null -ne $tt.moc_lo -and $tt.moc_lo -ne '')
    if ($mocLoOk) {
        Info "moc lo PIN (pin_dat console): $($tt.moc_lo)"
    } else {
        CanhBao 'khong tim thay nhat ky pin_dat (console) - khong the doi chieu moc lo PIN; -Che bat se bi chan'
    }

    # ---------- Cac kiem tra chi khi tinh nang hieu luc / sap bat ----------
    if ($pinHieuLuc) {
        # Admin: pin_doi_luc phai LON HON moc (bang = chua doi sau su co)
        if ($mocLoOk) {
            if (@($tt.admin_thieu_pin).Count -gt 0) {
                Loi "admin thieu pin_hash (user id: $($tt.admin_thieu_pin -join ',')) - chay dat-pin-prod.ps1 truoc"
            }
            if (@($tt.admin_chua_doi).Count -gt 0) {
                Loi "admin chua doi PIN SAU moc lo (user id: $($tt.admin_chua_doi -join ',')) - PIN dang dung la ban dat qua console, co the da lo. Doi PIN qua /taikhoan roi chay lai"
            }
            if (@($tt.admin_thieu_pin).Count -eq 0 -and @($tt.admin_chua_doi).Count -eq 0) {
                Ok 'moi admin da co PIN va doi PIN sau moc lo'
            }
        } else {
            Info 'bo qua doi chieu moc lo PIN (khong co moc) - -Che bat se bi chan'
        }

        # NV thungan/thukho dang hoat dong phai co PIN — thieu la FAIL
        $nvThieu = @($tt.nv_thieu_pin)
        if ($nvThieu.Count -eq 0) {
            Ok 'moi thu ngan/thu kho dang hoat dong deu co PIN'
        } else {
            Loi "NV thieu PIN (email che): $($nvThieu -join ', ') - ho se KHONG qua duoc muc M1. Cap PIN qua /taikhoan"
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

    if ($fail -gt 0 -or (-not $mocLoOk) -or ($tt.bang_yc -ne $true)) {
        Write-Host "`nTU CHOI $($Che.ToUpper()): con $fail loi (hoac thieu moc lo/bang). Khong sua .env." -ForegroundColor Red
        exit 1
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
