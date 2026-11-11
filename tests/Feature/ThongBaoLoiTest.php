<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bước 3.3: trang lỗi tiếng Việt và thông báo lỗi AJAX ở POS.
 */
class ThongBaoLoiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_trang_404_hien_thi_tieng_viet(): void
    {
        $this->get('/duong-dan-khong-ton-tai-'.uniqid())
            ->assertNotFound()
            ->assertSee('Không tìm thấy trang');
    }

    public function test_trang_403_hien_thi_tieng_viet(): void
    {
        $thuNgan = User::where('role', 'thungan')->firstOrFail();

        $this->actingAs($thuNgan)
            ->get(route('nhatky.index'))
            ->assertForbidden()
            ->assertSee('Không có quyền truy cập');
    }

    public function test_trang_419_hien_thi_tieng_viet(): void
    {
        $this->view('errors.419')
            ->assertSee('Phiên làm việc đã hết hạn');
    }

    public function test_trang_500_hien_thi_tieng_viet_va_khong_lo_chi_tiet(): void
    {
        $this->view('errors.500')
            ->assertSee('Máy chủ gặp sự cố')
            ->assertDontSee('Exception')
            ->assertDontSee('SQLSTATE');
    }

    public function test_pos_co_xu_ly_loi_ajax_than_thien(): void
    {
        $thuNgan = User::where('role', 'thungan')->firstOrFail();

        $this->actingAs($thuNgan)
            ->get(route('pos.index'))
            ->assertOk()
            ->assertSee('Phiên làm việc đã hết hạn', false)
            ->assertSee('Giỏ hàng vẫn được giữ', false)
            ->assertSee('qr-polling-status', false)
            ->assertSee('Đã tạm dừng tự động kiểm tra', false);
    }
}
