<?php

/**
 * Anh qua URL giai doan 1 — cau hinh tai anh tu URL (import san pham).
 *
 * Bao mat SSRF: mac dinh CHAN moi dia chi noi bo/dac biet; DNS phai phan giai
 * ra IP cong cong va ket noi ghim vao IP da kiem (chong DNS rebinding).
 *
 * ten_mien_tin_cay: danh sach hau to mien duoc PHEP du tra IP noi bo
 * (vi du CDN noi bo). MAC DINH RONG = khong mien nao duoc ngoai le.
 * Chi them mien that su kiem soat duoc — khong them 'localhost'.
 */
return [
    'ten_mien_tin_cay' => [],

    'gioi_han' => [
        'kich_thuoc_byte' => 2 * 1024 * 1024, // 2MB / anh
        'canh_px' => 6000,                    // toi da moi canh
        'anh_moi_luot' => 20,                 // toi da anh moi lan xac nhan import
        'chuyen_huong' => 3,                  // toi da 3 lan redirect
        'timeout_ket_noi' => 5,                // giay
        'timeout_tong' => 15,                 // giay
    ],
];
