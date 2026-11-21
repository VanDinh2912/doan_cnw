<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Lọc email rác cho form KHÁCH CÔNG KHAI (đăng ký, hồ sơ, liên hệ,
    | đặt hàng online). Đây là các lớp lọc chặn email giả — KHÔNG phải
    | xác thực sở hữu (chưa có SMTP; xem docs/SO_TAY_TRIEN_KHAI.md mục 11).
    |--------------------------------------------------------------------------
    */

    // Bật kiểm tra DNS (MX, fallback A). Lỗi mạng/timeout -> cho qua (fail-open).
    // Tắt trong phpunit.xml để test không phụ thuộc mạng.
    'kiem_tra_dns' => filter_var(env('EMAIL_KIEM_TRA_DNS', true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,

    // Thời gian cache kết quả DNS theo tên miền.
    'dns_cache_gio' => 6,

    // Tên miền ví dụ/giữ chỗ — chặn ở production.
    'ten_mien_vi_du' => [
        'example.com', 'example.org', 'example.net', 'example.edu',
        'test.com', 'test.org', 'test.net',
        'localhost', 'invalid', 'invalid.com', 'domain.com', 'email.com',
        'yourdomain.com', 'domain.example',
    ],

    // TLD nội bộ/giữ chỗ không bao giờ là email thật.
    'tld_cam' => ['local', 'localhost', 'test', 'invalid', 'example', 'internal', 'lan', 'home', 'corp'],

    // Email dùng một lần (disposable) — chỉ liệt kê dịch vụ phổ biến,
    // chắc chắn là rác. Bổ sung dần khi gặp; nguồn: danh sách công khai
    // disposable-email-domains, cập nhật 2026-11.
    'disposable' => [
        'mailinator.com',
        'guerrillamail.com', 'guerrillamail.net', 'guerrillamail.org',
        '10minutemail.com',
        'temp-mail.org',
        'yopmail.com',
        'trashmail.com',
        'tempmail.com', 'tempmailo.com',
        'tempail.com',
        'getnada.com',
        'maildrop.cc',
        'throwawaymail.com',
        'fakeinbox.com',
        'mohmal.com',
        'dispostable.com',
        'mailnesia.com',
        'mintemail.com',
        'mytemp.email',
        'emailondeck.com',
        'tmpmail.org',
        'sharklasers.com',
    ],

    // Gợi ý khi gõ nhầm tên miền phổ biến -> domain đúng.
    // Chỉ gợi ý, KHÔNG tự sửa input.
    'goi_y_chinh_ta' => [
        'gmial.com' => 'gmail.com',
        'gmai.com' => 'gmail.com',
        'gamil.com' => 'gmail.com',
        'gmail.con' => 'gmail.com',
        'gmal.com' => 'gmail.com',
        'gmial.con' => 'gmail.com',
        'gmail.comn' => 'gmail.com',
        'yaho.com' => 'yahoo.com',
        'yahoo.con' => 'yahoo.com',
        'yahho.com' => 'yahoo.com',
        'hotmial.com' => 'hotmail.com',
        'hotmal.com' => 'hotmail.com',
        'hotmali.com' => 'hotmail.com',
        'hotmial.con' => 'hotmail.com',
        'outlok.com' => 'outlook.com',
        'outloook.com' => 'outlook.com',
        'outlook.con' => 'outlook.com',
    ],

    // Nhà cung cấp email phổ biến — dùng cho gợi ý levenshtein khi
    // không khớp map chính xác.
    'ten_mien_pho_bien' => [
        'gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com',
        'icloud.com', 'mail.com', 'proton.me', 'zohomail.com',
    ],

    // NGOẠI LỆ môi trường test: các tên miền này được bỏ qua toàn bộ
    // lớp lọc domain khi app()->environment('testing') — giữ cho test
    // cũ dùng @test.local/@example.com không bị ảnh hưởng.
    // Production không đọc danh sách này.
    'cho_phep_khi_test' => [
        'example.com', 'example.org', 'example.net', 'example.edu',
        'test.com', 'test.org', 'test.net', 'test.local', 'localhost',
        'invalid',
    ],
];
