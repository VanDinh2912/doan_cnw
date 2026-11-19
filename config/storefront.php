<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Thông tin storefront hiển thị công khai
    |--------------------------------------------------------------------------
    */

    // Tên cửa hàng + tagline hiển thị trên storefront, email, tiêu đề trang.
    // Đổi tên chỉ cần sửa STORE_NAME trong .env, không sửa view.
    'ten_cua_hang' => env('STORE_NAME', 'PhoneShop'),
    'tieu_de' => env('STORE_TITLE', 'Phụ kiện điện thoại chính hãng'),

    // Hotline hỗ trợ khách — hiển thị ở footer, thanh tin cậy và thông báo giỏ hàng.
    'hotline' => env('HOTLINE', '1900 0000'),

    // Giờ hỗ trợ khách — hiển thị ở footer và thanh tin cậy.
    'gio_lam_viec' => env('STORE_GIO_LAM_VIEC', '8:00 – 22:00'),
];
