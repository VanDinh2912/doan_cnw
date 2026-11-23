<?php

/**
 * M2 — Anh xa cot Excel <-> truong san pham cho import linh hoat.
 *
 * khoa chinh   : ten truong noi bo (dung trong inputs/plan).
 * nhan         : ten cot chuan trong file (hien trong template huong dan).
 * aliases      : ten goi khac duoc chap nhan (khop sau chuan hoa:
 *                chu thuong, bo dau, bo khoang trang va dau gach).
 *
 * cot_he_thong : he thong quan ly — neu file co cot nay thi BO QUA va
 *                thong bao ro trong preview (khong bao gio nhap).
 * cot_chi_doc  : chi de doc (vd ten_loai trong file xuat) — bo qua.
 */
return [
    'truong' => [
        'sku' => [
            'nhan' => 'sku',
            'aliases' => ['sku', 'ma_sp', 'ma san pham', 'ma', 'product code', 'ma hang'],
            'bat_buoc' => true,
        ],
        'barcode' => [
            'nhan' => 'barcode',
            'aliases' => ['barcode', 'ma vach', 'ma vach san pham'],
        ],
        'ten_sanpham' => [
            'nhan' => 'ten_sanpham',
            'aliases' => ['ten_sanpham', 'ten sp', 'ten', 'ten san pham', 'product name'],
        ],
        'danh_muc' => [
            'nhan' => 'danh_muc',
            'aliases' => ['danh_muc', 'danh muc', 'ma_loai', 'ma loai', 'loai', 'nhom'],
        ],
        'hang' => [
            'nhan' => 'hang',
            'aliases' => ['hang', 'hang sx', 'hang san xuat', 'thuong hieu', 'brand'],
        ],
        'don_vi' => [
            'nhan' => 'don_vi',
            'aliases' => ['don_vi', 'don vi', 'dvt', 'don vi tinh', 'don_vi_tinh'],
        ],
        'gia_ban' => [
            'nhan' => 'gia_ban',
            'aliases' => ['gia_ban', 'gia ban', 'gia', 'price', 'gia ban le'],
        ],
        // GD1: URL anh — tai ve va luu cuc bo khi XAC NHAN (preview khong goi mang).
        // Chi co tac dung khi products da co 3 cot hinh_anh_nguon/sha256/tai_luc.
        'hinh_anh_url' => [
            'nhan' => 'hinh_anh_url',
            'aliases' => ['hinh_anh_url', 'hinh anh url', 'anh', 'anh url', 'url anh', 'hinh_anh', 'image url', 'image', 'photo url'],
        ],
    ],

    // He thong quan ly — bo qua + thong bao "bi bo qua (he thong quan ly)"
    'cot_he_thong' => [
        'soluong' => ['soluong', 'ton kho', 'ton', 'sl', 'so luong', 'ton kho hien tai'],
        'soluong_hong' => ['soluong_hong', 'ton hong', 'sl hong', 'so luong hong'],
        'soluong_toithieu' => ['soluong_toithieu', 'ton toi thieu', 'sl toi thieu', 'so luong toi thieu'],
        'gia_nhap' => ['gia_nhap', 'gia nhap', 'cost', 'gia von'],
        'trang_thai' => ['trang_thai', 'trang thai', 'dang ban', 'trang_thai_ban'],
    ],

    // Cot chi de doc — bo qua, thong bao "cot chi doc"
    'cot_chi_doc' => [
        'ten_loai' => ['ten_loai', 'ten loai', 'ten danh muc', 'category name'],
        'ma_sanpham_noibo' => ['id', 'product id'],
    ],
];
