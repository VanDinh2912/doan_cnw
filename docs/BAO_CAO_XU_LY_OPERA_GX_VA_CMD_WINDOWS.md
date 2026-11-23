# Báo cáo: Xử lý Opera GX (tắt cập nhật, gỡ autostart, gỡ hoàn toàn) và cửa sổ cmd nhấp nháy

- **Ngày:** 2026-11-11
- **Phạm vi:** máy cá nhân Windows (user `vandi`), tác vụ `\WebPOS-Scheduler` của dự án DA
- **Kết quả cuối:** Opera GX đã gỡ hoàn toàn; cửa sổ cmd nhấp nháy mỗi phút đã hết;
  scheduler Laravel vẫn chạy đúng mỗi phút, log cập nhật bình thường
- **Sao lưu:** `<thư-mục-sao-lưu>\OperaGX-2026-11-11` (140.8 MB, profile + export registry)

---

## 1. Kết luận nhanh

| Hạng mục | Trước | Sau |
|---|---|---|
| Cập nhật tự động Opera GX | Bật, có task + updater + assistant | Đã tắt 4 lớp, sau đó gỡ hẳn |
| Autostart `browser_assistant.exe` | Có giá trị trong `HKCU\...\Run` | Đã xóa, không tự tạo lại |
| Opera GX trên máy | 968.4 MB chương trình + 140.7 MB profile | Không còn thư mục, khóa registry, task, shortcut, firewall rule |
| Cửa sổ cmd nhấp nháy mỗi phút | `WebPOS-Scheduler` chạy thẳng `.bat` với InteractiveToken | Chạy qua `wscript.exe` ẩn cửa sổ, vẫn đúng 1 phút/lần |
| Scheduler Laravel | Chạy, log ghi | Chạy, log ghi, không đổi hành vi nghiệp vụ |
| Trình duyệt mặc định | Chrome (`ChromeHTML`) | Không đổi |

---

## 2. Phần A — Opera GX

### 2.1 Tắt cập nhật tự động (4 lớp độc lập)

Chạy elevated `opera_gx_disable_update.ps1`, log `opera_gx_disable_update.log`:

1. **Registry policy** — đặt `AutoUpdateCheckPeriodMinutes = 0` (DWORD) tại 6 nhánh:
   - `HKLM\SOFTWARE\Policies\Opera Software\Opera GX`
   - `HKLM\SOFTWARE\Policies\Opera Software\Opera`
   - `HKLM\SOFTWARE\Policies\Opera Software\Update`
   - tương ứng 3 nhánh `HKCU\SOFTWARE\Policies\Opera Software\...`
2. **`installer_prefs.json`** — `"autoupdate": true` → `false`
   (bản sao lưu `.bak-before-disable-autoupdate-20261111`).
3. **Đổi tên binary cập nhật**:
   - `autoupdate\opera_autoupdate.exe` → `.disabled`
   - `assistant\assistant_installer.exe` → `.disabled`
4. **Dừng tiến trình cập nhật**: `browser_assistant.exe` PID 18112, 18276.

Kiểm chứng lại toàn bộ 6 khóa policy = 0, prefs `autoupdate=false`, không còn task
cập nhật, không còn tiến trình.

### 2.2 Gỡ autostart của Browser Assistant

- Sao lưu `HKCU\...\Run` ra `opera_gx_run_backup.reg`.
- Xóa giá trị `Opera GX Browser Assistant` =
  `"...\Opera GX\assistant\browser_assistant.exe" --autostart`.
- Giữ nguyên `Opera GX Stable` (autostart của trình duyệt).

### 2.3 Kiểm tra assistant có tự tạo lại autostart

Lần 1 dùng `opera.exe --autostart`: tiến trình thoát ngay, không kết luận được.
Lần 2 mở Opera bình thường (14 tiến trình, cửa sổ "GX Corner - Opera"), chờ, rồi đóng:

- Không tạo lại giá trị Run.
- Không tạo task.
- Không sinh `browser_assistant.exe`.
→ Kết luận: sau khi xóa, assistant **không** tự khôi phục autostart.

### 2.4 Gỡ hoàn toàn

Chạy elevated `remove_opera_gx.ps1`, log `remove_opera_gx.log`.

**Sao lưu trước khi gỡ** (robocopy exit 1 = thành công):

```
<thư-mục-sao-lưu>\OperaGX-2026-11-11\
  Roaming-Opera Software\
  Local-Opera Software\
  registry-HKCU-OperaSoftware.reg
  registry-HKCU-Uninstall-OperaGX.reg
  registry-HKCU-Policies-Opera.reg
  registry-HKLM-Policies-Opera.reg
  MANIFEST.txt
```

**Gỡ bằng uninstaller chính thức:**

```powershell
Start-Process -FilePath '<thư-mục-người-dùng>\AppData\Local\Programs\Opera GX\opera.exe' `
  -ArgumentList '--uninstall','--runimmediately','--deleteuserprofile=1' -PassThru
```

Kết quả: exit code **38**, thư mục chương trình **vẫn còn** sau 120 giây; thư mục
`%APPDATA%\Opera Software` và `%LOCALAPPDATA%\Opera Software` đã bị uninstaller xóa.
Vì vậy tiến hành dọn thủ công phần còn lại.

**Dọn thủ công:** thư mục chương trình 968.4 MB; giá trị
`StartupApproved\Run` (`Opera GX Stable`, `Opera GX Browser Assistant`);
khóa `HKCU\Software\Opera Software`; policy HKCU/HKLM; mục Uninstall;
giá trị Run; `RegisteredApplications`; `Clients\StartMenuInternet`; `App Paths`;
lớp `HKCU\Software\Classes` của Opera; shortcut Start Menu.

**Dọn tàn dư phát hiện ở bước kiểm chứng cuối (18:29):**

- `HKCU\...\App Paths\opera.exe` — khóa rỗng, đã xóa.
- Firewall rule `Opera Internet Browser (mDNS-In)` — trỏ tới `opera.exe` đã bị xóa,
  đã xóa (cần elevated).

### 2.5 Kiểm chứng cuối (read-only, sau dọn tàn dư)

```
Thư mục:
  False  %LOCALAPPDATA%\Programs\Opera GX
  False  %APPDATA%\Opera Software
  False  %LOCALAPPDATA%\Opera Software
  False  C:\ProgramData\Opera Software
  False  C:\Program Files\Opera GX
  False  C:\Program Files (x86)\Opera GX

Registry:
  False  HKCU\Software\Opera Software
  False  HKLM\SOFTWARE\Opera Software
  False  HKCU\SOFTWARE\Policies\Opera Software
  False  HKLM\SOFTWARE\Policies\Opera Software
  False  HKCU\...\App Paths\opera.exe

Uninstall entries:      none
Run / StartupApproved:  none
Classes / Clients / RegisteredApplications: none
Shortcuts:              none
Scheduled tasks:        none
Processes:              none
Firewall rules Opera:   none
Installer trong Downloads: không có
Backup:                 EXISTS 140.8 MB
Default browser:        ProgId=ChromeHTML (không đổi)
```

**Trạng thái: đã gỡ hoàn toàn.** Nếu muốn cài lại, chạy installer Opera GX mới;
dữ liệu cũ chỉ còn trong thư mục sao lưu (không tự khôi phục).

---

## 3. Phần B — Cửa sổ cmd nhấp nháy mỗi phút

### 3.1 Truy nguồn gốc

Watcher `watch_cmd_flash.ps1` bắt được chuỗi tiến trình thật:

```
svchost.exe (PID 2300, Task Scheduler)
  → cmd.exe /c ""<thư-mục-dự-án>\scripts\windows\chay-schedule.bat""
    → php artisan schedule:run
      → cmd /c ... donhang:huy-qua-han   (17:39:01, 17:40:01)
```

Nguồn không phải Opera GX mà là task `\WebPOS-Scheduler`:
action chạy thẳng file `.bat` mỗi 1 phút, principal `vandi`, `InteractiveToken`,
`Hidden = False` → mỗi lần chạy hiện một cửa sổ console.

### 3.2 Cách sửa

Giữ nguyên lịch mỗi phút, chỉ đổi lớp khởi chạy sang host GUI ẩn cửa sổ.

Tạo `scripts\windows\chay-schedule-hidden.vbs`:

```vbs
Option Explicit
Dim sh, rc, bat
Set sh = CreateObject("WScript.Shell")
bat = "<thư-mục-dự-án>\scripts\windows\chay-schedule.bat"
rc = sh.Run("""" & bat & """", 0, True)
WScript.Quit rc
```

Đổi action của task thành:

```
wscript.exe //B //Nologo "<thư-mục-dự-án>\scripts\windows\chay-schedule-hidden.vbs"
```

- XML task gốc sao lưu tại `<thư-mục-người-dùng>\AppData\Local\Temp\webpos_scheduler_task_backup.xml`.
- `chay-schedule.bat` **không sửa** — vẫn tự `cd` về gốc dự án, ghi log
  `storage\logs\schedule-YYYY-MM-DD.log`, giữ log 14 ngày, trả `ERRORLEVEL`.
- `WScript.Shell.Run(..., 0, True)` chạy ẩn và chờ; process con kế thừa console ẩn.

### 3.3 Kiểm chứng luồng (18:20–18:23)

| Mốc | Kết quả |
|---|---|
| 18:21:02 | Chu kỳ ẩn chạy, `MainWindowHandle = 0`, không cửa sổ cmd |
| 18:22:01 | Chu kỳ ẩn chạy, log scheduler tăng |
| 18:23:01 | Chu kỳ ẩn chạy; `WebPOS-Scheduler` LastRun 18:23:01 |
| LastResult | `267009` (0x00041301 = đang chạy) — bình thường với task 1 phút |
| Log scheduler | 47,610 bytes, LastWrite 18:23:10 |
| Nội dung log | `donhang:huy-qua-han` DONE sau 8 giây; `he-thong:scheduler-heartbeat` |
| Quét cửa sổ cmd hiện | `VISIBLE CMD: none` |

**Lưu ý false positive:** watcher bắt 2 tiến trình `cmd` thoáng qua với
`MainWindowHandle = -1` và gắn nhãn FAIL. Đó là tiến trình đã thoát/không có cửa sổ
(giá trị -1 của tiến trình không còn hiệu lực), không phải cửa sổ hiện.
Lần quét cuối `VISIBLE CMD: none` xác nhận không còn cửa sổ nào.

### 3.4 Rà soát toàn bộ task khác

- Quét **200 task**: 33 action là chương trình console (subsystem 3), **tất cả**
  chạy dưới `SYSTEM`/`LOCAL SERVICE` (session 0) hoặc đang Disabled → không hiện cửa sổ.
- 8 task chạy interactive: OneDrive ×2, WebPOS, GoogleUserPEH ×2,
  VisualStudio BackgroundDownload, SoftLanding ×2.
- Kiểm tra PE header: 4 exe interactive đều là subsystem **2 (GUI)**;
  SoftLanding là **COM handler**.
→ Không còn task nào khác có khả năng nháy cửa sổ cmd.

---

## 4. Việc cần lưu ý

1. `scripts\windows\chay-schedule-hidden.vbs` là file mới trong repo DA, **chưa commit**.
   Cần commit nếu muốn cấu hình này đi theo dự án (task trên máy đã trỏ tới file này).
2. Nếu đổi đường dẫn dự án, phải sửa đường dẫn trong `chay-schedule-hidden.vbs`
   và trong action của task.
3. Muốn khôi phục task như cũ: import
   `<thư-mục-người-dùng>\AppData\Local\Temp\webpos_scheduler_task_backup.xml`.
4. Backup Opera GX chỉ để tra cứu; xóa thư mục backup là mất dữ liệu profile cũ.
5. Không có thay đổi nào tới mã nghiệp vụ Laravel, CSDL hay cấu hình SePay.
