<?php

/*
 * Nội dung trang tĩnh storefront (footer). Sửa nội dung tại đây,
 * KHÔNG sửa view. Mỗi page: tieu_de, mo_ta (tùy chọn), noi_dung = mảng
 * các mục ['tieu_de' => ..., 'cac_doan' => [...]] hoặc ['tieu_de'=>..., 'noi_dung'=>[...]]
 * hoặc chuỗi HTML-đơn giản (chỉ text, blade escape khi render).
 */
return [
    'pages' => [
        'gioi-thieu' => [
            'tieu_de' => 'Giới thiệu',
            'mo_ta' => config('storefront.ten_cua_hang').' — cửa hàng phụ kiện điện thoại chính hãng.',
            'noi_dung' => [
                ['tieu_de' => 'Về '.config('storefront.ten_cua_hang'), 'cac_doan' => [
                    config('storefront.ten_cua_hang').' là cửa hàng trực tuyến và trực tiếp chuyên kinh doanh phụ kiện điện thoại chính hãng: sạc, cáp, tai nghe, ốp lưng, kính cường lực...',
                    'Chúng tôi cam kết sản phẩm chính hãng, bảo hành theo chính sách của hãng và hỗ trợ khách hàng trong giờ 8:00 – 22:00 hằng ngày.',
                ]],
                ['tieu_de' => 'Mua sắm tại '.config('storefront.ten_cua_hang'), 'cac_doan' => [
                    'Bạn có thể đặt hàng online (nhận tại cửa hàng hoặc giao tận nơi) hoặc ghé cửa hàng để xem và thanh toán trực tiếp.',
                    'Mọi đơn hàng đều có mã tra cứu — bạn có thể kiểm tra trạng thái đơn bất cứ lúc nào tại trang Tra cứu đơn hàng.',
                ]],
            ],
        ],

        'he-thong-cua-hang' => [
            'tieu_de' => 'Hệ thống cửa hàng',
            'mo_ta' => 'Danh sách chi nhánh và điểm bán '.config('storefront.ten_cua_hang').'.',
            'noi_dung' => [
                ['tieu_de' => 'Chi nhánh chính', 'cac_doan' => [
                    '[ĐỊA CHỈ CHI NHÁNH CHÍNH — TODO: điền địa chỉ, quận/huyện, giờ mở cửa]',
                    'Hotline: '.config('storefront.hotline', '1900 0000').' — hỗ trợ 8:00 – 22:00 hằng ngày.',
                ]],
                ['tieu_de' => 'Lưu ý', 'cac_doan' => [
                    'Vui lòng gọi trước nếu bạn định ghé mua số lượng lớn hoặc cần tư vấn sản phẩm cụ thể.',
                ]],
            ],
        ],

        'chinh-sach-bao-hanh' => [
            'tieu_de' => 'Chính sách bảo hành',
            'noi_dung' => [
                ['tieu_de' => 'Thời gian bảo hành', 'cac_doan' => [
                    'Sản phẩm được bảo hành 12 tháng theo chính sách của hãng sản xuất (điều kiện áp dụng tùy từng dòng sản phẩm).',
                ]],
                ['tieu_de' => 'Điều kiện bảo hành', 'cac_doan' => [
                    'Sản phẩm còn tem niêm phong, không bị rơi vỡ, ngập nước, tự ý tháo lắp hoặc sửa chữa tại cơ sở ngoài.',
                    'Khách hàng cần mang theo hóa đơn/phiếu mua hàng khi bảo hành.',
                ]],
                ['tieu_de' => 'Liên hệ bảo hành', 'cac_doan' => [
                    'Hotline: '.config('storefront.hotline', '1900 0000').' (8:00 – 22:00 hằng ngày) hoặc gửi yêu cầu qua trang Liên hệ.',
                ]],
            ],
        ],

        'chinh-sach-doi-tra' => [
            'tieu_de' => 'Chính sách đổi trả',
            'noi_dung' => [
                ['tieu_de' => 'Đổi trả trong 30 ngày', 'cac_doan' => [
                    'Bạn được đổi/trả sản phẩm trong vòng 30 ngày kể từ ngày nhận hàng nếu sản phẩm lỗi do nhà sản xuất hoặc không đúng mô tả.',
                    'Sản phẩm phải còn nguyên tem, hộp và đầy đủ phụ kiện đi kèm.',
                ]],
                ['tieu_de' => 'Không áp dụng đổi trả', 'cac_doan' => [
                    'Sản phẩm đã qua sử dụng, rơi vỡ, ngâm nước hoặc có dấu hiệu can thiệp vật lý.',
                    'Sản phẩm mua tại cửa hàng khác hoặc không rõ nguồn gốc.',
                ]],
                ['tieu_de' => 'Cách yêu cầu', 'cac_doan' => [
                    'Liên hệ hotline '.config('storefront.hotline', '1900 0000').' hoặc gửi khiếu nại qua trang Liên hệ để được hướng dẫn đổi trả.',
                ]],
            ],
        ],

        'chinh-sach-van-chuyen' => [
            'tieu_de' => 'Chính sách vận chuyển',
            'noi_dung' => [
                ['tieu_de' => 'Hình thức nhận hàng', 'cac_doan' => [
                    'Nhận tại cửa hàng: miễn phí, bạn có thể ghé lấy đơn bất cứ lúc nào trong giờ hỗ trợ 8:00 – 22:00.',
                    'Giao tận nơi: áp dụng cho các khu vực cửa hàng hỗ trợ; phí giao hàng được tính khi đặt hàng.',
                ]],
                ['tieu_de' => 'Thời gian giao hàng', 'cac_doan' => [
                    'Đơn giao tận nơi thường được xử lý trong ngày làm việc; thời gian cụ thể tùy khu vực.',
                ]],
                ['tieu_de' => 'Lưu ý', 'cac_doan' => [
                    'Vui lòng kiểm tra hàng trước khi thanh toán (áp dụng khi thanh toán tại cửa hàng).',
                ]],
            ],
        ],

        'bao-mat-thong-tin' => [
            'tieu_de' => 'Bảo mật thông tin',
            'noi_dung' => [
                ['tieu_de' => 'Thông tin chúng tôi thu thập', 'cac_doan' => [
                    'Họ tên, số điện thoại, email và địa chỉ bạn cung cấp khi đặt hàng, đăng ký tài khoản hoặc liên hệ.',
                    'Thông tin này chỉ dùng để xử lý đơn hàng, chăm sóc khách hàng và cải thiện dịch vụ.',
                ]],
                ['tieu_de' => 'Cam kết', 'cac_doan' => [
                    'Chúng tôi không bán, cho thuê hay chia sẻ thông tin cá nhân của bạn cho bên thứ ba ngoài mục đích giao hàng và thanh toán.',
                    'Bạn có thể yêu cầu xem, chỉnh sửa hoặc xóa thông tin qua trang Liên hệ.',
                ]],
            ],
        ],

        'huong-dan-mua-hang' => [
            'tieu_de' => 'Hướng dẫn mua hàng',
            'noi_dung' => [
                ['tieu_de' => 'Mua online', 'cac_doan' => [
                    '1. Chọn sản phẩm và thêm vào giỏ hàng.',
                    '2. Vào trang Đặt hàng, điền thông tin người nhận và chọn nhận tại cửa hàng hoặc giao tận nơi.',
                    '3. Chọn thanh toán tiền mặt khi nhận hoặc VietQR.',
                    '4. Nhận mã tra cứu đơn hàng để theo dõi trạng thái.',
                ]],
                ['tieu_de' => 'Mua tại cửa hàng', 'cac_doan' => [
                    'Nhân viên tại quầy sẽ hỗ trợ bạn chọn sản phẩm, kiểm tra tồn kho và thanh toán trực tiếp.',
                ]],
            ],
        ],

        'cau-hoi-thuong-gap' => [
            'tieu_de' => 'Câu hỏi thường gặp',
            'noi_dung' => [
                ['tieu_de' => 'Sản phẩm có bảo hành không?', 'cac_doan' => [
                    'Có — sản phẩm được bảo hành 12 tháng theo chính sách hãng. Xem chi tiết tại Chính sách bảo hành.',
                ]],
                ['tieu_de' => 'Đổi trả trong bao lâu?', 'cac_doan' => [
                    'Bạn được đổi/trả trong 30 ngày nếu sản phẩm lỗi hoặc không đúng mô tả.',
                ]],
                ['tieu_de' => 'Làm sao tra cứu đơn hàng?', 'cac_doan' => [
                    'Vào trang Tra cứu đơn hàng và nhập mã đơn + số điện thoại đã đặt. Nếu đã đăng nhập, bạn xem lịch sử đơn tại trang Thông tin cá nhân.',
                ]],
                ['tieu_de' => 'Thanh toán như thế nào?', 'cac_doan' => [
                    'Tiền mặt khi nhận hàng, chuyển khoản hoặc quét VietQR cho đơn giao tận nơi.',
                ]],
                ['tieu_de' => 'Cần hỗ trợ thêm?', 'cac_doan' => [
                    'Gọi hotline '.config('storefront.hotline', '1900 0000').' (8:00 – 22:00) hoặc gửi tin qua trang Liên hệ.',
                ]],
            ],
        ],
    ],

    'tuyen_dung' => [
        'mo_ta' => config('storefront.ten_cua_hang').' đang tuyển dụng nhân sự cho cửa hàng và kho. Ứng tuyển bằng cách điền form liên hệ với loại "Ứng tuyển".',
        'vi_tri' => [
            'nhan-vien-ban-hang' => 'Nhân viên bán hàng',
            'thu-ngan' => 'Thu ngân',
            'thue-kho' => 'Thủ kho',
            'ky-thuat-vien' => 'Kỹ thuật viên',
        ],
        'yeu_cau_chung' => [
            'Siêng năng, trung thực, có tinh thần trách nhiệm; ưu tiên ứng viên có kinh nghiệm bán lẻ/phụ kiện.',
            'Làm việc toàn thời gian, có thể tăng ca giờ cao điểm; lương thỏa thuận khi phỏng vấn.',
        ],
    ],
];
