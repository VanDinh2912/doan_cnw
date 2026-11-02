<?php

namespace App\Services;

use App\Models\NhaCungCap;
use App\Models\SanPham;
use Exception;
use Illuminate\Support\Collection;
use Smalot\PdfParser\Parser;
use Throwable;

class PdfInvoiceParserService
{
    protected Parser $parser;

    public function __construct()
    {
        $this->parser = new Parser();
    }

    /**
     * Bóc tách toàn bộ file PDF hóa đơn thành mảng dữ liệu có cấu trúc.
     *
     * @param string $filePath Đường dẫn tuyệt đối đến file PDF
     * @return array Dữ liệu bóc tách chuẩn hóa (Normalized Draft Data)
     */
    public function parse(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'message' => 'Không tìm thấy file PDF được chỉ định.',
                'parsing_status' => 'failed',
                'header' => [],
                'items' => [],
                'summary' => [],
                'warnings' => ['File không tồn tại trên hệ thống.'],
            ];
        }

        try {
            $pdf = $this->parser->parseFile($filePath);
            $rawText = $pdf->getText();
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => 'Không thể đọc nội dung file PDF (có thể file bị khóa mật khẩu hoặc lỗi định dạng): ' . $e->getMessage(),
                'parsing_status' => 'failed',
                'header' => [],
                'items' => [],
                'summary' => [],
                'warnings' => ['Lỗi giải mã PDF: ' . $e->getMessage()],
            ];
        }

        if (empty(trim($rawText))) {
            return [
                'success' => false,
                'message' => 'File PDF không chứa văn bản (có thể là file scan dạng ảnh hoặc nội dung rỗng).',
                'parsing_status' => 'failed',
                'header' => [],
                'items' => [],
                'summary' => [],
                'warnings' => ['PDF không chứa lớp văn bản bóc tách được.'],
            ];
        }

        $header = $this->extractHeader($rawText);
        $items = $this->extractItems($rawText);

        // Tính toán kiểm tra và cảnh báo
        $calculatedTienHang = (int) array_sum(array_column($items, 'thanh_tien'));
        $thueSuat = (float) ($header['thue_suat'] ?? 0);
        $calculatedTienThue = (int) round($calculatedTienHang * ($thueSuat / 100));
        $calculatedTong = $calculatedTienHang + ($header['tien_thue'] ?? $calculatedTienThue);

        $warnings = [];
        if ($header['tong_thanh_toan'] && abs($header['tong_thanh_toan'] - $calculatedTong) > 100) {
            $warnings[] = 'Tổng thanh toán trên hóa đơn (' . number_format($header['tong_thanh_toan'], 0, ',', '.') . ' đ) lệch so với tổng chi tiết hàng hóa (' . number_format($calculatedTong, 0, ',', '.') . ' đ).';
        }

        $unmatchedCount = 0;
        foreach ($items as $item) {
            if (empty($item['product_id'])) {
                $unmatchedCount++;
            }
        }
        if ($unmatchedCount > 0) {
            $warnings[] = "Có {$unmatchedCount} sản phẩm chưa khớp tự động với kho hàng, vui lòng rà soát chọn sản phẩm.";
        }

        $status = 'complete';
        if (empty($items)) {
            $status = 'header_only';
            $warnings[] = 'Không bóc tách được danh sách dòng hàng tự động. Bạn có thể chọn sản phẩm thủ công.';
        } elseif (!empty($warnings)) {
            $status = 'partial';
        }

        return [
            'success' => true,
            'message' => 'Bóc tách dữ liệu PDF thành công.',
            'parsing_status' => $status,
            'header' => $header,
            'items' => $items,
            'summary' => [
                'total_items' => count($items),
                'total_quantity' => (int) array_sum(array_column($items, 'soluong')),
                'calculated_tien_hang' => $calculatedTienHang,
                'calculated_tien_thue' => $calculatedTienThue,
                'calculated_tong' => $calculatedTong,
                'unmatched_products' => $unmatchedCount,
            ],
            'warnings' => $warnings,
        ];
    }

    /**
     * Bóc tách phần thông tin chung của hóa đơn (Header)
     */
    protected function extractHeader(string $text): array
    {
        $header = [
            'so_hoadon' => null,
            'ky_hieu' => null,
            'mau_so' => null,
            'ngay_hoadon' => null,
            'ten_don_vi_ban' => null,
            'ma_so_thue' => null,
            'supplier_id' => null,
            'hinh_thuc_thanh_toan' => 'Chuyển khoản',
            'tong_tien_hang' => null,
            'thue_suat' => null,
            'tien_thue' => null,
            'tong_thanh_toan' => null,
        ];

        // 1. Ngày lập hóa đơn
        if (preg_match('/Ngày\s*([0-9]{1,2})\s*tháng\s*([0-9]{1,2})\s*năm\s*([0-9]{4})/iu', $text, $m)) {
            $header['ngay_hoadon'] = sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        } elseif (preg_match('/([0-9]{2}\/[0-9]{2}\/[0-9]{4})/', $text, $m)) {
            $parts = explode('/', $m[1]);
            $header['ngay_hoadon'] = "{$parts[2]}-{$parts[1]}-{$parts[0]}";
        }

        // 2. Tên đơn vị bán
        if (preg_match('/Đơn vị bán(?:\s*hàng)?:\s*([^\n\r]+)/iu', $text, $m)) {
            $header['ten_don_vi_ban'] = trim($m[1]);
        }

        // 3. Mã số thuế đơn vị bán & tra cứu CSDL
        if (preg_match('/(?:Mã số thuế|MST):\s*([0-9\-]+)/iu', $text, $m)) {
            $header['ma_so_thue'] = trim($m[1]);
            $supplier = NhaCungCap::where('ma_so_thue', $header['ma_so_thue'])->first();
            if ($supplier) {
                $header['supplier_id'] = $supplier->id;
                if (empty($header['ten_don_vi_ban'])) {
                    $header['ten_don_vi_ban'] = $supplier->ten_nhacungcap;
                }
            }
        }

        // 4. Mẫu số
        if (preg_match('/Mẫu số:\s*([^\s]+)/iu', $text, $m)) {
            $header['mau_so'] = trim($m[1]);
        }

        // 5. Ký hiệu
        if (preg_match('/Ký hiệu:\s*([^\s]+)/iu', $text, $m)) {
            $header['ky_hieu'] = trim($m[1]);
        }

        // 6. Số hóa đơn (tránh ăn nhầm từ "Mẫu số:")
        if (preg_match('/(?<!Mẫu\s)(?<!mẫu\s)(?:Số|No\.?):\s*([0-9]+)/iu', $text, $m)) {
            $header['so_hoadon'] = trim($m[1]);
        }

        // 7. Hình thức thanh toán
        if (preg_match('/Hình thức thanh toán:\s*([^\n\r]+)/iu', $text, $m)) {
            $header['hinh_thuc_thanh_toan'] = trim($m[1]);
        }

        // 8. Cộng tiền hàng
        if (preg_match('/Cộng tiền hàng:\s*([\d\.,]+)/iu', $text, $m)) {
            $header['tong_tien_hang'] = (int) preg_replace('/[^\d]/', '', $m[1]);
        }

        // 9. Thuế suất GTGT
        if (preg_match('/Thuế suất GTGT:\s*([\d\.,]+)%/iu', $text, $m)) {
            $header['thue_suat'] = (float) str_replace(',', '.', $m[1]);
        }

        // 10. Tiền thuế GTGT
        if (preg_match('/Tiền thuế GTGT:\s*([\d\.,]+)/iu', $text, $m)) {
            $header['tien_thue'] = (int) preg_replace('/[^\d]/', '', $m[1]);
        }

        // 11. Tổng tiền thanh toán
        if (preg_match('/Tổng(?: cộng)? tiền thanh toán:\s*([\d\.,]+)/iu', $text, $m)) {
            $header['tong_thanh_toan'] = (int) preg_replace('/[^\d]/', '', $m[1]);
        }

        return $header;
    }

    /**
     * Bóc tách các dòng sản phẩm trong bảng hóa đơn
     */
    protected function extractItems(string $text): array
    {
        $items = [];
        // Mẫu nhận diện từng khối sản phẩm:
        // [STT dòng]
        // [Tên hàng hóa (1 hoặc nhiều dòng)]
        // [Đơn vị tính]\t[Số lượng]\t[Đơn giá]\t[Thành tiền]
        $pattern = '/\n([0-9]{1,4})\n(.*?)\n([^\t\n\r]+)\t([0-9\.,]+)\t([0-9\.,]+)\t([0-9\.,]+)/su';
        preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);

        // Nạp trước danh mục sản phẩm vào bộ nhớ để so khớp nhanh
        $products = SanPham::select('id', 'sku', 'barcode', 'ten_sanpham', 'category_id', 'don_vi', 'gia_ban', 'gia_nhap')->get();

        foreach ($matches as $m) {
            $stt = (int) $m[1];
            $tenHangRaw = trim(preg_replace('/\s+/', ' ', $m[2]));
            $donVi = trim($m[3]);
            $soluong = (int) round((float) str_replace(',', '.', str_replace('.', '', $m[4])));
            $donGia = (int) preg_replace('/[^\d]/', '', explode(',', $m[5])[0]);
            $thanhTien = (int) preg_replace('/[^\d]/', '', $m[6]);

            // So khớp sản phẩm
            $matched = $this->matchProduct($tenHangRaw, $products);

            $items[] = [
                'stt' => $stt,
                'ten_hang' => $tenHangRaw,
                'don_vi' => $donVi,
                'soluong' => $soluong,
                'don_gia' => $donGia,
                'thanh_tien' => $thanhTien,
                'product_id' => $matched['product_id'],
                'sku' => $matched['sku'],
                'category_id' => $matched['category_id'],
                'gia_ban_de_xuat' => $matched['gia_ban_de_xuat'] ?? (int) round($donGia * 1.3),
                'confidence' => $matched['confidence'],
                'match_type' => $matched['match_type'],
            ];
        }

        return $items;
    }

    /**
     * So khớp thông minh tên sản phẩm bóc tách với CSDL
     */
    public function matchProduct(string $rawName, Collection $products): array
    {
        $cleanRaw = mb_strtolower(trim($rawName));

        // 1. So khớp tên chính xác 100%
        foreach ($products as $p) {
            if (mb_strtolower(trim($p->ten_sanpham)) === $cleanRaw) {
                return [
                    'product_id' => $p->id,
                    'sku' => $p->sku,
                    'category_id' => $p->category_id,
                    'gia_ban_de_xuat' => (int) $p->gia_ban,
                    'confidence' => 100,
                    'match_type' => 'exact_name',
                ];
            }
        }

        // 2. So khớp mờ (Fuzzy matching)
        $bestMatch = null;
        $highestPercent = 0.0;

        foreach ($products as $p) {
            similar_text($cleanRaw, mb_strtolower(trim($p->ten_sanpham)), $percent);
            if ($percent > $highestPercent) {
                $highestPercent = (float) $percent;
                $bestMatch = $p;
            }
        }

        if ($bestMatch && $highestPercent >= 75.0) {
            return [
                'product_id' => $bestMatch->id,
                'sku' => $bestMatch->sku,
                'category_id' => $bestMatch->category_id,
                'gia_ban_de_xuat' => (int) $bestMatch->gia_ban,
                'confidence' => round($highestPercent, 1),
                'match_type' => 'fuzzy',
            ];
        }

        // 3. Fallback: Không tìm thấy sản phẩm trùng khớp
        return [
            'product_id' => null,
            'sku' => null,
            'category_id' => null,
            'gia_ban_de_xuat' => null,
            'confidence' => 0,
            'match_type' => 'unmatched',
        ];
    }
}
