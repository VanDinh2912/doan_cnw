<?php

return [
    'bank_code' => env('POS_BANK_CODE', ''),
    'bank_account' => env('POS_BANK_ACCOUNT', ''),
    'bank_name' => env('POS_BANK_NAME', 'Cửa hàng phụ kiện điện thoại'),
    'qr_template' => env('POS_QR_TEMPLATE', 'compact2'),
    'diem_moi_dong' => (int) env('POS_DIEM_MOI_DONG', 100000),
    'gia_tri_diem' => (int) env('POS_GIA_TRI_DIEM', 1000),
    'han_tra_ngay' => (int) env('POS_HAN_TRA_NGAY', 7),
    'nguong_tra_khong_duyet' => (int) env('POS_NGUONG_TRA_KHONG_DUYET', 500000),
    'webhook_secret' => env('POS_WEBHOOK_SECRET', ''),
    'cho_thanh_toan_phut' => (int) env('POS_CHO_THANH_TOAN_PHUT', 15),
    'sepay_webhook_api_key' => env('SEPAY_WEBHOOK_API_KEY', ''),
    'telegram_bot_token' => env('TELEGRAM_BOT_TOKEN', ''),
    'telegram_chat_id' => env('TELEGRAM_CHAT_ID', ''),
];
