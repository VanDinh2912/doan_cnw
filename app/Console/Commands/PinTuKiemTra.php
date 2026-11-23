<?php

namespace App\Console\Commands;

use App\Models\NhatKyHoatDong;
use App\Models\User;
use App\Services\KiemTraPinService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * pin:tu-kiem-tra — SELF-TEST cho KiemTraPinService + cong-tac-pin-prod.ps1.
 *
 * Dựng sqlite TẠM (file trong sys_get_temp_dir, xóa sau khi chạy), migrate,
 * tạo fixture giả, chạy kiemTra() ở 2 kịch bản:
 *   A. "sach"   -> mong doi KHONG co admin_chua_doi / nv_thieu_pin
 *   B. "loi"   -> admin pin_doi_luc <= moc_lo + thu kho thieu PIN
 *                 -> mong doi phat hien DUNG
 *
 * KHÔNG đụng DB thật (.env không bị sửa — chỉ config trong tiến trình).
 * Exit 0 = self-test đạt; 1 = self-test thất bại.
 */
class PinTuKiemTra extends Command
{
    protected $signature = 'pin:tu-kiem-tra';

    protected $description = 'Self-test logic kiem tra PIN tren sqlite tam (khong cham DB that)';

    public function handle(KiemTraPinService $svc): int
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pintt_').'.sqlite';
        touch($tmp);

        $configCu = config('database.default');
        $sqliteCu = config('database.connections.sqlite');
        $fail = 0;

        try {
            config([
                'database.default' => 'sqlite',
                'database.connections.sqlite' => array_merge($sqliteCu ?? [], [
                    'driver' => 'sqlite',
                    'database' => $tmp,
                    'prefix' => '',
                ]),
            ]);
            DB::purge('sqlite');
            DB::setDefaultConnection('sqlite');
            DB::reconnect('sqlite');

            Artisan::call('migrate', ['--force' => true, '--quiet' => true]);

            // Bật cờ PIN cho service đọc (config trong tiến trình).
            config(['pos.pin_bat' => true, 'pos.ma_duyet_bat' => true]);

            $this->taoCoSo();

            // --- Kịch bản A: sạch ---
            $this->resetDuLieu();
            $this->taoFixtureSach();
            $a = $svc->kiemTra();
            $aOk = $a['admin_chua_doi'] === [] && $a['admin_thieu_pin'] === [] && $a['nv_thieu_pin'] === [];
            $this->dong($aOk, 'A. du lieu sach -> khong phat hien gi', $a);
            if (! $aOk) {
                $fail++;
            }

            // --- Kịch bản B: lỗi ---
            $this->resetDuLieu();
            $this->taoFixtureLoi();
            $b = $svc->kiemTra();
            $bOk = count($b['admin_chua_doi']) === 1
                && count($b['nv_thieu_pin']) === 1
                && $b['nv_thieu_pin'][0] !== null;
            $this->dong($bOk, 'B. admin chua doi PIN + thu kho thieu PIN -> phat hien dung', $b);
            if (! $bOk) {
                $fail++;
            }
        } catch (\Throwable $e) {
            $this->error('SELF-TEST LOI: '.$e->getMessage());
            $fail++;
        } finally {
            // Khôi phục config + xóa file tạm.
            config(['database.default' => $configCu]);
            DB::setDefaultConnection($configCu);
            DB::purge('sqlite');
            @unlink($tmp);
        }

        if ($fail === 0) {
            $this->info('SELF-TEST DAT: service phat hien dung 2 kich ban.');

            return self::SUCCESS;
        }
        $this->error("SELF-TEST THAT BAI: $fail muc.");

        return self::FAILURE;
    }

    private function taoCoSo(): void
    {
        // users + activity_logs da co sau migrate; dam bao PIN migration đã chạy
        // (migrate --force chạy toàn bộ nên đủ).
    }

    private function resetDuLieu(): void
    {
        foreach (['activity_logs', 'users'] as $t) {
            DB::table($t)->delete();
        }
    }

    private function user(string $role, ?string $pin, ?string $doiLuc): int
    {
        return DB::table('users')->insertGetId([
            'name' => 'Test '.$role,
            'email' => $role.'@test.local',
            'password' => Hash::make('x'),
            'role' => $role,
            'pin_hash' => $pin,
            'pin_doi_luc' => $doiLuc,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function mocLo(string $luc): void
    {
        DB::table('activity_logs')->insert([
            'hanh_dong' => 'pin_dat',
            'doi_tuong' => 'user',
            'doi_tuong_loai' => 'user',
            'doi_tuong_id' => 1,
            'mo_ta' => 'self-test',
            'ip' => '127.0.0.1',
            'user_agent' => 'pin-tu-kiem-tra',
            'nguon' => 'console',
            'created_at' => $luc,
            'updated_at' => $luc,
        ]);
    }

    private function taoFixtureSach(): void
    {
        $moc = now()->subHour();
        $this->mocLo($moc->toDateTimeString());
        $this->user('admin', Hash::make('111111'), now()->toDateTimeString());
        $this->user('thungan', Hash::make('222222'), null);
        $this->user('thukho', Hash::make('333333'), null);
    }

    private function taoFixtureLoi(): void
    {
        $moc = now()->subHour();
        $this->mocLo($moc->toDateTimeString());
        // Admin: pin_doi_luc BANG moc (chua doi sau su co) -> phai bi bat.
        $this->user('admin', Hash::make('111111'), $moc->toDateTimeString());
        $this->user('thungan', Hash::make('222222'), null);
        // Thu kho KHONG co pin -> phai bi bat.
        $this->user('thukho', null, null);
    }

    private function dong(bool $ok, string $ten, array $kq): void
    {
        $this->line(json_encode($kq, JSON_UNESCAPED_UNICODE));
        $this->line(($ok ? '[DAT] ' : '[SAI] ').$ten);
    }
}
