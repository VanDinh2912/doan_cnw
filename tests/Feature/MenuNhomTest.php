<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Menu sidebar nhom: moi vai tro thay dung nhom/muc, nhom rong khong render. */
class MenuNhomTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create([
            'name' => 'NV '.$role, 'email' => $role.uniqid().'@test.local',
            'password' => Hash::make('MatKhau@123'), 'role' => $role,
        ]);
    }

    public function test_admin_thay_du_bon_nhom_va_muc_rieng(): void
    {
        $this->actingAs($this->user('admin'))->get(route('tongquan'))->assertOk()
            ->assertSee('data-nav-group="ban-hang"', false)
            ->assertSee('data-nav-group="kho"', false)
            ->assertSee('data-nav-group="noi-dung"', false)
            ->assertSee('data-nav-group="he-thong"', false)
            ->assertSee('Đơn cần hoàn tiền')
            ->assertSee('Tài khoản')
            ->assertSee('Đối soát thanh toán')
            ->assertSee('Bình luận');
    }

    public function test_thungan_chi_co_ban_hang_va_noi_dung_lien_he(): void
    {
        $res = $this->actingAs($this->user('thungan'))->get(route('tongquan'))->assertOk();
        $html = $res->getContent();

        $this->assertStringContainsString('data-nav-group="ban-hang"', $html);
        $this->assertStringContainsString('data-nav-group="noi-dung"', $html);
        $this->assertStringNotContainsString('data-nav-group="kho"', $html);
        $this->assertStringNotContainsString('data-nav-group="he-thong"', $html);
        $this->assertStringContainsString('Hãng sản xuất', $html);
        $this->assertStringContainsString('Liên hệ', $html);
        // Khong thay muc chi admin
        $this->assertStringNotContainsString('Đối soát thanh toán', $html);
        $this->assertStringNotContainsString('Bài viết', $html);
    }

    public function test_thukho_chi_co_kho_kem_don_online(): void
    {
        $res = $this->actingAs($this->user('thukho'))->get(route('tongquan'))->assertOk();
        $html = $res->getContent();

        $this->assertStringContainsString('data-nav-group="kho"', $html);
        $this->assertStringNotContainsString('data-nav-group="ban-hang"', $html);
        $this->assertStringNotContainsString('data-nav-group="noi-dung"', $html);
        $this->assertStringNotContainsString('data-nav-group="he-thong"', $html);
        $this->assertStringContainsString('Đơn online', $html);
        $this->assertStringContainsString('Kiểm kê', $html);
    }

    public function test_muc_active_neu_trong_nhom_tuong_ung(): void
    {
        $res = $this->actingAs($this->user('admin'))->get(route('sanpham.danhsach'))->assertOk();
        $html = $res->getContent();

        // Link San pham active va nam trong nhom kho
        $this->assertMatchesRegularExpression(
            '/data-nav-group="kho".*?app-nav-link active" href="[^"]*sanpham/s',
            $html
        );
        // Tong quan khong nam trong nhom nao
        $this->assertMatchesRegularExpression(
            '/app-nav-link active" href="[^"]*"[^>]*>\s*<i class="fa-solid fa-gauge-high"><\/i><span>Tổng quan<\/span>/s',
            $this->actingAs($this->user('admin'))->get(route('tongquan'))->assertOk()->getContent()
        );
    }
}
