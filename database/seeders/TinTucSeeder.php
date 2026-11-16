<?php

namespace Database\Seeders;

use App\Models\BaiViet;
use App\Models\ChuDe;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeder nhẹ, idempotent (firstOrCreate theo slug) cho tin tức.
 * Chạy: php artisan db:seed --class=TinTucSeeder
 */
class TinTucSeeder extends Seeder
{
    public function run(): void
    {
        $chuDes = [
            ['ten_chude' => 'Khuyến mãi', 'slug' => 'khuyen-mai', 'mo_ta' => 'Chương trình ưu đãi và giảm giá của cửa hàng.'],
            ['ten_chude' => 'Thủ thuật', 'slug' => 'thu-thuat', 'mo_ta' => 'Mẹo sử dụng điện thoại và phụ kiện hiệu quả.'],
            ['ten_chude' => 'Tin công nghệ', 'slug' => 'tin-cong-nghe', 'mo_ta' => 'Tin tức công nghệ mới nhất.'],
        ];

        foreach ($chuDes as $chuDe) {
            ChuDe::firstOrCreate(['slug' => $chuDe['slug']], $chuDe);
        }

        $tacGia = User::where('role', 'admin')->orderBy('id')->first();

        $baiViets = [
            [
                'topic_id' => ChuDe::where('slug', 'khuyen-mai')->value('id'),
                'tieu_de' => 'Ưu đãi phụ kiện chính hãng — giảm đến 20%',
                'slug' => 'uu-dai-phu-kien-chinh-hang-giam-den-20',
                'tom_tat' => 'Chương trình giảm giá sạc, cáp, tai nghe chính hãng trong tháng này.',
                'noi_dung' => '<p>Cửa hàng triển khai chương trình ưu đãi <strong>giảm đến 20%</strong> cho các sản phẩm phụ kiện chính hãng: sạc nhanh, cáp sạc, tai nghe bluetooth.</p><p>Chương trình áp dụng cho cả mua tại quầy và đặt hàng online. Số lượng có hạn — nhanh tay đặt hàng!</p>',
            ],
            [
                'topic_id' => ChuDe::where('slug', 'thu-thuat')->value('id'),
                'tieu_de' => '5 mẹo sạc pin điện thoại đúng cách, chai pin chậm hơn',
                'slug' => '5-meo-sac-pin-dien-thoai-dung-cach',
                'tom_tat' => 'Giữ pin bền lâu với những thói quen sạc đơn giản hằng ngày.',
                'noi_dung' => '<p>Pin lithium-ion bền nhất khi giữ mức sạc trong khoảng <strong>20%–80%</strong>. Dưới đây là 5 mẹo đơn giản:</p><ul><li>Không để pin cạn kiệt rồi mới sạc.</li><li>Dùng sạc chính hãng, đúng công suất.</li><li>Tránh vừa sạc vừa chơi game nặng.</li><li>Không để máy quá nóng khi sạc.</li><li>Sạc nhồi ngắn nhiều lần tốt hơn sạc đầy 100% qua đêm.</li></ul>',
            ],
            [
                'topic_id' => ChuDe::where('slug', 'tin-cong-nghe')->value('id'),
                'tieu_de' => 'Xu hướng phụ kiện điện thoại đáng chú ý năm nay',
                'slug' => 'xu-huong-phu-kien-dien-thoai-dang-chu-y',
                'tom_tat' => 'Sạc GaN, cáp bền chuẩn quân đội và tai nghe chống ồn ngày càng phổ biến.',
                'noi_dung' => '<p>Sạc <strong>GaN</strong> nhỏ gọn công suất cao, cáp bọc dù bền bỉ và tai nghe chống ồn chủ động là những nhóm phụ kiện được quan tâm nhiều nhất.</p><p>Cửa hàng đã cập nhật đầy đủ các sản phẩm này trong danh mục — mời bạn tham khảo.</p>',
            ],
        ];

        foreach ($baiViets as $baiViet) {
            BaiViet::firstOrCreate(
                ['slug' => $baiViet['slug']],
                $baiViet + [
                    'user_id' => $tacGia?->id,
                    'kiem_duyet' => true,
                    'kich_hoat' => true,
                ]
            );
        }
    }
}