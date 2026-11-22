<?php

namespace Tests\Feature;

use App\Models\KhachHang;
use App\Models\LoaiSanPham;
use App\Models\SanPham;
use App\Models\User;
use App\Models\YeuCauDuyet;
use App\Services\MaDuyetService;
use App\Services\PinService;
use App\Support\KiemTraSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Concerns\LayMaDuyet;
use Tests\TestCase;

/**
 * Luồng mã duyệt hai-người (M2) + phân mức PIN theo vai trò.
 * Convention: pos.pin_bat=true cho mọi test ở đây (cờ tắt test riêng cuối file).
 */
class MaDuyetTest extends TestCase
{
    use LayMaDuyet;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['pos.pin_bat' => true]);
    }

    private function nv(string $role = 'thungan', string $pin = '246813'): User
    {
        $u = User::create(['name' => 'NV', 'email' => 'nv-'.uniqid().'@t.local',
            'password' => Hash::make('Mk@12345'), 'role' => $role]);
        PinService::datPin($u, $pin);

        return $u;
    }

    private function admin(): User
    {
        $u = User::create(['name' => 'AD', 'email' => 'ad-'.uniqid().'@t.local',
            'password' => Hash::make('Mk@12345'), 'role' => 'admin']);
        PinService::datPin($u, '135790');

        return $u;
    }

    private function sanPhamMau(): SanPham
    {
        $loai = LoaiSanPham::create(['ma_loai' => 'L'.uniqid(), 'ten_loai' => 'Loại '.uniqid(), 'he_so_lai' => 0]);

        return SanPham::create([
            'category_id' => $loai->id, 'sku' => 'SKU-'.uniqid(), 'ten_sanpham' => 'SP test',
            'don_vi' => 'cái', 'gia_nhap' => 10000, 'gia_ban' => 20000,
            'soluong' => 5, 'soluong_toithieu' => 0, 'trang_thai' => true,
        ]);
    }

    private function payloadSanPham(SanPham $sp, array $ghiDe = []): array
    {
        return array_merge([
            'category_id' => $sp->category_id, 'sku' => $sp->sku,
            'ten_sanpham' => $sp->ten_sanpham, 'don_vi' => $sp->don_vi,
            'gia_nhap' => $sp->gia_nhap, 'gia_ban' => $sp->gia_ban,
            'soluong' => $sp->soluong, 'soluong_toithieu' => $sp->soluong_toithieu,
            'trang_thai' => 1,
        ], $ghiDe);
    }

    // ---------------------------------------------------------------
    // Tạo yêu cầu — mô tả server, quyền, giới hạn
    // ---------------------------------------------------------------

    public function test_mo_ta_do_client_gui_bi_bo_qua(): void
    {
        $nv = $this->nv('thungan');
        $kh = KhachHang::create(['ho_ten' => 'Khách A', 'so_dienthoai' => '090'.random_int(1000000, 9999999)]);

        $res = $this->actingAs($nv)->postJson('/yeu-cau-duyet/gui', [
            '_route_method' => 'DELETE',
            '_route_uri' => '/khachhang/xoa/'.$kh->id,
            'mo_ta_thao_tac' => 'XOA SACH DATABASE', // client cố tình gửi mô tả giả
            'ly_do' => 'test',
        ]);
        $res->assertOk()->assertJsonPath('ma', 'OK');

        $yc = YeuCauDuyet::latest('id')->firstOrFail();
        $this->assertStringNotContainsString('XOA SACH', $yc->mo_ta_thao_tac);
        $this->assertStringContainsString('Xóa hồ sơ khách hàng', $yc->mo_ta_thao_tac);
        $this->assertSame('khach_hang', $yc->doi_tuong_loai);
        $this->assertSame($kh->id, (int) $yc->doi_tuong_id);
    }

    public function test_khoa_trung_chan_trung_lap(): void
    {
        $nv = $this->nv();
        $du = ['nhan_vien_id' => $nv->id, 'ten_route' => 'khachhang.xoa', 'method' => 'DELETE',
            'mo_ta_thao_tac' => 'test', 'trang_thai' => YeuCauDuyet::TT_CHO_CAP, 'created_at' => now()];
        YeuCauDuyet::create($du + ['khoa_trung' => 'khoa-test-1']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        YeuCauDuyet::create($du + ['khoa_trung' => 'khoa-test-1']);
    }

    public function test_nv_khong_tao_yeu_cau_cho_route_ngoai_quyen(): void
    {
        $nv = $this->nv('thungan');
        $sp = $this->sanPhamMau();

        // sanpham.xoa chỉ dành admin/thukho — thungan không được xin mã.
        $this->actingAs($nv)->postJson('/yeu-cau-duyet/gui', [
            '_route_method' => 'DELETE', '_route_uri' => '/sanpham/xoa/'.$sp->id,
        ])->assertStatus(422)->assertJsonPath('ma', 'KHONG_CO_QUYEN');

        // Route M1 (khong phai M2) khong nam trong whitelist -> tu choi.
        $kh = KhachHang::create(['ho_ten' => 'K', 'so_dienthoai' => '090'.random_int(1000000, 9999999)]);
        $this->actingAs($nv)->postJson('/yeu-cau-duyet/gui', [
            '_route_method' => 'PUT', '_route_uri' => '/khachhang/sua/'.$kh->id,
        ])->assertStatus(422)->assertJsonPath('ma', 'ROUTE_KHONG_HOP_LE');

        $this->assertSame(0, YeuCauDuyet::count());
    }

    public function test_admin_khong_gui_duoc_yeu_cau(): void
    {
        $this->actingAs($this->admin())->postJson('/yeu-cau-duyet/gui', [
            '_route_method' => 'DELETE', '_route_uri' => '/khachhang/xoa/1',
        ])->assertForbidden();
    }

    public function test_qua_5_yeu_cau_cho_bi_tu_choi(): void
    {
        $nv = $this->nv('thungan');
        // 5 khách hàng -> 5 yêu cầu chờ khác nhau (khoa_trung khác nhau).
        for ($i = 0; $i < 5; $i++) {
            $kh = KhachHang::create(['ho_ten' => "K{$i}", 'so_dienthoai' => '090'.random_int(1000000, 9999999)]);
            $this->actingAs($nv)->postJson('/yeu-cau-duyet/gui', [
                '_route_method' => 'DELETE', '_route_uri' => '/khachhang/xoa/'.$kh->id,
            ])->assertOk();
        }
        $this->assertSame(5, YeuCauDuyet::where('trang_thai', YeuCauDuyet::TT_CHO_CAP)->count());

        $kh6 = KhachHang::create(['ho_ten' => 'K6', 'so_dienthoai' => '090'.random_int(1000000, 9999999)]);
        $this->actingAs($nv)->postJson('/yeu-cau-duyet/gui', [
            '_route_method' => 'DELETE', '_route_uri' => '/khachhang/xoa/'.$kh6->id,
        ])->assertStatus(429)->assertJsonPath('ma', 'QUA_NHIEU_YEU_CAU');
    }

    // ---------------------------------------------------------------
    // Phân mức PIN theo vai trò (pin.muc)
    // ---------------------------------------------------------------

    public function test_admin_m1_chi_can_pin_thuong(): void
    {
        $admin = $this->admin();
        $kh = KhachHang::create(['ho_ten' => 'K', 'so_dienthoai' => '090'.random_int(1000000, 9999999)]);

        $this->actingAs($admin);
        // Chưa PIN -> 423 mức thường
        $this->putJson("/khachhang/sua/{$kh->id}", [])->assertStatus(423)->assertJsonPath('ma', 'CAN_PIN');

        // PIN thường (cửa sổ) -> qua middleware, vào controller (validate fail chứ không 423)
        $this->postJson('/xac-nhan-pin/kiem-tra', ['pin' => '135790', 'muc' => 'thuong', 'hanh_dong' => 'PUT /x'])->assertOk();
        $res = $this->put("/khachhang/sua/{$kh->id}", []);
        $this->assertNotEquals(423, $res->status());
    }

    public function test_admin_m2_can_pin_nghiem_trong_khong_phai_thuong(): void
    {
        $admin = $this->admin();
        $sp = $this->sanPhamMau();
        $hd = 'DELETE /sanpham/xoa/'.$sp->id;

        $this->actingAs($admin);
        // PIN thường KHÔNG đủ cho M2 — admin cần nghiêm trọng mỗi lần.
        $this->postJson('/xac-nhan-pin/kiem-tra', ['pin' => '135790', 'muc' => 'thuong', 'hanh_dong' => $hd])->assertOk();
        $this->deleteJson("/sanpham/xoa/{$sp->id}")->assertStatus(423);

        // PIN nghiêm trọng đúng hành động -> qua middleware.
        $kq = $this->postJson('/xac-nhan-pin/kiem-tra', ['pin' => '135790', 'muc' => 'nghiemtrong', 'hanh_dong' => $hd])->json();
        $this->assertTrue($kq['ok']);
        $res = $this->deleteJson("/sanpham/xoa/{$sp->id}", ['_pin_token' => $kq['token']]);
        $this->assertNotEquals(423, $res->status());

        // Token một lần đã đốt -> request sau (sản phẩm khác, còn tồn tại)
        // lại bị 423.
        $sp2 = $this->sanPhamMau();
        $this->deleteJson("/sanpham/xoa/{$sp2->id}")->assertStatus(423);
    }

    public function test_admin_a_can_pin_nghiem_trong_moi_lan(): void
    {
        $admin = $this->admin();
        $nv = $this->nv();
        $yc = YeuCauDuyet::create([
            'nhan_vien_id' => $nv->id, 'ten_route' => 'khachhang.xoa', 'method' => 'DELETE',
            'mo_ta_thao_tac' => 'test', 'trang_thai' => YeuCauDuyet::TT_CHO_CAP,
            'khoa_trung' => 'k-'.uniqid(), 'created_at' => now(),
        ]);
        $hd = "POST /yeu-cau-duyet/{$yc->id}/cap-ma";

        $this->actingAs($admin);
        $this->postJson("/yeu-cau-duyet/{$yc->id}/cap-ma", [])->assertStatus(423);

        $kq = $this->postJson('/xac-nhan-pin/kiem-tra', ['pin' => '135790', 'muc' => 'nghiemtrong', 'hanh_dong' => $hd])->json();
        $this->assertTrue($kq['ok']);
        $res = $this->postJson("/yeu-cau-duyet/{$yc->id}/cap-ma", ['_pin_token' => $kq['token']]);
        $this->assertNotEquals(423, $res->status());
        $this->assertSame(YeuCauDuyet::TT_DA_CAP, $yc->fresh()->trang_thai);
    }

    public function test_nv_khong_duoc_goi_route_muc_a(): void
    {
        $nv = $this->nv('thungan');
        $yc = YeuCauDuyet::create([
            'nhan_vien_id' => $nv->id, 'ten_route' => 'khachhang.xoa', 'method' => 'DELETE',
            'mo_ta_thao_tac' => 'test', 'trang_thai' => YeuCauDuyet::TT_CHO_CAP,
            'khoa_trung' => 'k-'.uniqid(), 'created_at' => now(),
        ]);
        $this->actingAs($nv)->postJson("/yeu-cau-duyet/{$yc->id}/cap-ma", [])->assertForbidden();
    }

    public function test_nv_route_m2_khong_ma_bi_423(): void
    {
        $nv = $this->nv('thukho');
        $sp = $this->sanPhamMau();
        // PIN thường cũng không thay thế được mã duyệt.
        $this->actingAs($nv);
        $this->postJson('/xac-nhan-pin/kiem-tra', ['pin' => '246813', 'muc' => 'thuong', 'hanh_dong' => 'DELETE /x'])->assertOk();
        $this->deleteJson("/sanpham/xoa/{$sp->id}")->assertStatus(423)->assertJsonPath('ma', 'CAN_MA_DUYET');
        $this->assertNotNull(SanPham::find($sp->id));
    }

    // ---------------------------------------------------------------
    // sanpham.postsua — leo thang theo giá trị
    // ---------------------------------------------------------------

    public function test_sanpham_postsua_doi_ten_khong_can_ma(): void
    {
        $nv = $this->nv('thukho');
        $sp = $this->sanPhamMau();
        $this->actingAs($nv);
        $this->postJson('/xac-nhan-pin/kiem-tra', ['pin' => '246813', 'muc' => 'thuong', 'hanh_dong' => 'PUT /x'])->assertOk();

        $res = $this->put("/sanpham/sua/{$sp->id}", $this->payloadSanPham($sp, ['ten_sanpham' => 'Tên mới']));
        $res->assertRedirect();
        $this->assertSame('Tên mới', $sp->fresh()->ten_sanpham);
        $this->assertSame(0, YeuCauDuyet::count());
    }

    public function test_sanpham_postsua_doi_gia_can_ma_va_ma_khoa_gia(): void
    {
        // Tạo admin TRƯỚC: PinService::datPin() xoa session PIN của phiên
        // hiện tại — gọi giữa chừng sẽ xóa cửa sổ PIN thường của NV trong test.
        $admin = $this->admin();
        $nv = $this->nv('thukho');
        $sp = $this->sanPhamMau();
        $this->actingAs($nv);
        $this->postJson('/xac-nhan-pin/kiem-tra', ['pin' => '246813', 'muc' => 'thuong', 'hanh_dong' => 'PUT /x'])->assertOk();

        $giaMoi = 35000;
        // Đổi giá chưa có mã -> 423 CAN_MA_DUYET
        $this->putJson("/sanpham/sua/{$sp->id}", $this->payloadSanPham($sp, ['gia_ban' => $giaMoi]))
            ->assertStatus(423)->assertJsonPath('ma', 'CAN_MA_DUYET');
        $this->assertSame(20000.0, (float) $sp->fresh()->gia_ban);

        // Admin duyệt giá 35000 -> mã chỉ đúng cho đúng giá đó.
        $ma = $this->capMaCho($nv, 'sanpham.postsua', [
            'gia_ban_moi' => $giaMoi, 'trang_thai_ban_moi' => '1',
        ], $admin, 'san_pham', $sp->id);

        // Giá KHÁC (kể cả thấp hơn — rule eq) -> từ chối.
        $this->putJson("/sanpham/sua/{$sp->id}", $this->payloadSanPham($sp, [
            'gia_ban' => 30000, 'trang_thai' => 1, '_ma_duyet' => $ma,
        ]))->assertStatus(423);
        $this->assertSame(20000.0, (float) $sp->fresh()->gia_ban);

        // Đúng giá đã duyệt -> thành công, mã bị đốt.
        $this->putJson("/sanpham/sua/{$sp->id}", $this->payloadSanPham($sp, [
            'gia_ban' => $giaMoi, 'trang_thai' => 1, '_ma_duyet' => $ma,
        ]))->assertStatus(302);
        $this->assertSame(35000.0, (float) $sp->fresh()->gia_ban);
        $this->assertSame(YeuCauDuyet::TT_DA_DUNG, YeuCauDuyet::latest('id')->first()->trang_thai);
    }

    // ---------------------------------------------------------------
    // POS — giá trị gửi lên phải <= giá trị đã duyệt
    // ---------------------------------------------------------------

    public function test_pos_giam_gia_lon_hon_da_duyet_bi_tu_choi(): void
    {
        $nv = $this->nv('thungan');
        // Duyệt giảm giá 50%.
        $ma = $this->capMaCho($nv, 'donhang.thanhtoan', ['giam_gia_phantram' => 50]);

        $this->actingAs($nv);
        // Gửi 60% (> 50 đã duyệt) kèm mã -> từ chối.
        $this->postJson('/donhang/thanhtoan', [
            'giam_gia_phantram' => 60, 'tien_khach_dua' => 0, 'phuong_thuc' => 'tienmat',
            '_ma_duyet' => $ma,
        ])->assertStatus(423);

        // Gửi 30% (<= 50 đã duyệt) cùng mã -> qua auth (giỏ trống -> 302).
        $this->postJson('/donhang/thanhtoan', [
            'giam_gia_phantram' => 30, 'tien_khach_dua' => 0, 'phuong_thuc' => 'tienmat',
            '_ma_duyet' => $ma,
        ])->assertStatus(302);
    }

    // ---------------------------------------------------------------
    // Dùng mã một lần + nguyên tử
    // ---------------------------------------------------------------

    public function test_ma_duyet_chi_dung_mot_lan(): void
    {
        $nv = $this->nv('thukho');
        $sp = $this->sanPhamMau();
        $ma = $this->capMaCho($nv, 'sanpham.xoa', [], $this->admin(), 'san_pham', $sp->id);

        $this->actingAs($nv);
        // Lần 1: xóa sản phẩm thành công -> mã da_dung.
        $this->deleteJson("/sanpham/xoa/{$sp->id}", ['_ma_duyet' => $ma])->assertStatus(302);
        $this->assertNull(SanPham::find($sp->id));
        $yc = YeuCauDuyet::latest('id')->firstOrFail();
        $this->assertSame(YeuCauDuyet::TT_DA_DUNG, $yc->trang_thai);

        // Lần 2 cùng mã (request mới, sản phẩm khác): không còn yc da_cap -> 423.
        $sp2 = $this->sanPhamMau();
        $this->deleteJson("/sanpham/xoa/{$sp2->id}", ['_ma_duyet' => $ma])
            ->assertStatus(423)->assertJsonPath('ma', 'CAN_MA_DUYET');
        $this->assertNotNull(SanPham::find($sp2->id));
    }

    public function test_hai_lan_verify_cung_ma_chi_mot_thanh_cong(): void
    {
        $nv = $this->nv();
        $yc = YeuCauDuyet::create([
            'nhan_vien_id' => $nv->id, 'ten_route' => 'khachhang.xoa', 'method' => 'DELETE',
            'doi_tuong_loai' => 'khach_hang', 'doi_tuong_id' => 1,
            'mo_ta_thao_tac' => 'test',
            'trang_thai' => YeuCauDuyet::TT_CHO_CAP, 'khoa_trung' => null, 'created_at' => now(),
        ]);
        $cap = MaDuyetService::capMa($this->admin(), $yc->id, $this->requestGia($this->admin()));
        $this->assertTrue($cap['ok']);

        // Transaction 1: verify + đốt.
        $req = $this->requestGia($nv);
        $req->merge(['_ma_duyet' => $cap['ma_hien']]);
        DB::transaction(function () use ($nv, $cap, $req) {
            $kq = MaDuyetService::xacNhanM2($req, 'khachhang.xoa', 'khach_hang', 1, []);
            $this->assertTrue($kq['ok']);
            MaDuyetService::danhDauDaDung($kq['yc'], $req);
        });

        // Transaction 2 (giả lập request đồng thời đến sau): mã đã đốt -> từ chối.
        $req2 = $this->requestGia($nv);
        $req2->merge(['_ma_duyet' => $cap['ma_hien']]);
        $kq2 = MaDuyetService::xacNhanM2($req2, 'khachhang.xoa', 'khach_hang', 1, []);
        $this->assertFalse($kq2['ok']);
    }

    // ---------------------------------------------------------------
    // Cờ / schema
    // ---------------------------------------------------------------

    public function test_thieu_bang_va_hai_co_bat_thi_kiem_tra_fail(): void
    {
        Schema::drop('yeu_cau_duyet');
        KiemTraSchema::datLai();
        config(['pos.pin_bat' => true, 'pos.ma_duyet_bat' => true]);

        $this->assertFalse(MaDuyetService::bat());
        $this->artisan('he-thong:kiem-tra')
            ->expectsOutputToContain('yeu_cau_duyet')
            ->assertExitCode(1);
    }

    public function test_ma_duyet_tat_thi_m2_ha_m1_va_kiem_tra_warn(): void
    {
        config(['pos.pin_bat' => true, 'pos.ma_duyet_bat' => false]);
        $this->artisan('he-thong:kiem-tra')
            ->expectsOutputToContain('MẤT nguyên tắc hai người')
            ->assertExitCode(1); // vẫn FAIL vì scheduler chưa chạy trong test — chỉ quan tâm dòng WARN
    }

    public function test_pin_tat_khong_doi_hanh_vi_va_khong_doc_bang(): void
    {
        Schema::drop('yeu_cau_duyet');
        KiemTraSchema::datLai();
        config(['pos.pin_bat' => false]);

        $this->assertFalse(MaDuyetService::bat());

        // NV vượt ngưỡng giảm giá: hành vi cũ — không PIN, không bảng, vào thẳng controller.
        $nv = $this->nv('thungan');
        $this->actingAs($nv);
        $res = $this->postJson('/donhang/thanhtoan', [
            'giam_gia_phantram' => 50, 'tien_khach_dua' => 0, 'phuong_thuc' => 'tienmat',
        ]);
        $this->assertSame(302, $res->getStatusCode()); // giỏ trống -> redirect như cũ
    }
}
