<?php

namespace Tests\Feature\Concerns;

use App\Models\User;
use App\Services\MaDuyetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Dựng nhanh một yêu cầu duyệt M2 đã được admin cấp mã (bỏ qua HTTP
 * /yeu-cau-duyet + PIN admin — luồng đó được test riêng ở MaDuyetTest).
 * Trả về mã 6 số để gắn vào request gốc qua field `_ma_duyet`.
 */
trait LayMaDuyet
{
    private function adminMau(): User
    {
        return User::create([
            'name' => 'Admin Test', 'email' => 'adm-'.uniqid().'@t.local',
            'password' => Hash::make('Mk@12345'), 'role' => 'admin',
        ]);
    }

    private function requestGia(User $user): Request
    {
        $req = Request::create('/gia', 'POST');
        $req->setUserResolver(fn () => $user);

        return $req;
    }

    /**
     * @param  array<string,mixed>  $thamSo  tham số ràng buộc (server chuẩn hóa,
     *         giống MaDuyetService::thamSoRequest của request thật)
     */
    private function capMaCho(User $nv, string $route, array $thamSo, ?User $admin = null, ?string $loai = null, ?int $id = null): string
    {
        $admin ??= $this->adminMau();
        $kq = MaDuyetService::taoYeuCau($nv, $route, 'POST', $loai, $id, $thamSo, $this->requestGia($nv));
        $this->assertNotNull($kq['yc'] ?? null, 'Tạo yêu cầu duyệt thất bại: '.($kq['loi'] ?? '?'));

        $cap = MaDuyetService::capMa($admin, $kq['yc']->id, $this->requestGia($admin));
        $this->assertTrue($cap['ok'] ?? false, 'Cấp mã thất bại: '.($cap['message'] ?? '?'));

        return (string) $cap['ma_hien'];
    }
}
