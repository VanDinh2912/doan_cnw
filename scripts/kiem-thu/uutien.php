<?php
// Phan loai uu tien TC — tieu chi: Rất cao = luong cot loi trinh dien bao ve
// + cham tien/tồn kho/quyen; Cao = chuc nang chinh con lai; Trung binh = phu;
// Thap = edge case / it quan trong. Uu tien tinh: rat_cao > thap > cao > mac_dinh module.
return [
    // ~20 TC Rất cao: ban POS+in, dat hang online, xac nhan thu cong+ma duyet,
    // tra hang, nhap kho/kiem ke, phan quyen, PIN, khoa KH, import 2 buoc, login
    'rat_cao' => [
        'STO-001', // trang chu
        'STO-004', // tim kiem
        'ACC-003', // dang nhap/dang xuat
        'ACC-008', // phan quyen khu NV
        'ORD-002', // dat hang online
        'ORD-008', // tra cuu don
        'ORD-010', // NV xu ly don online
        'POS-003', // POS barcode/ton
        'POS-005', // thanh toan
        'POS-006', // in hoa don
        'RET-001', // tao phieu tra
        'INV-001', // nhap kho
        'INV-005', // kiem ke
        'CUS-006', // khoa khach hang
        'IMP-001', // import upload/preview
        'IMP-007', // all-or-nothing + token
        'IMP-008', // xac nhan can PIN A
        'PIN-001', // nhap PIN dung muc
        'PIN-006', // ma duyet hai nguoi
        'MAN-001', // NV xac nhan bang chung
    ],
    // Module mac dinh "Cao"; module khong liet ke mac dinh "Trung bình"
    'mac_dinh_cao' => ['ACC','ORD','POS','RET','INV','PIN','MAN'],
    // Nang tu Trung binh len Cao
    'cao' => [
        'PRD-001', 'PRD-004', 'PRD-006',
        'CUS-003', 'CUS-004', 'CUS-005',
        'RPT-001', 'RPT-004',
        'IMP-009', 'IMP-010',
        'SEC-001', 'SEC-002', 'SEC-004', 'SEC-005', 'SEC-006', 'SEC-009',
        'SEP-001', 'SEP-002', 'SEP-003',
        'STO-002', 'STO-003', 'STO-005',
    ],
    // Ha xuong Trung binh (module mac dinh Cao nhung la chuc nang phu)
    'tb' => [
        // ACC — quan ly tai khoan la phu
        'ACC-001','ACC-002','ACC-004','ACC-005','ACC-006','ACC-007','ACC-009','ACC-010',
        // ORD — chi giu ORD-011 (giu hang/ton kho)
        'ORD-001','ORD-003','ORD-004','ORD-005','ORD-006','ORD-007','ORD-009','ORD-012','ORD-013',
        // POS — chi giu POS-004 (giam gia/diem cham PIN)
        'POS-001','POS-002','POS-007','POS-009','POS-010','POS-011',
        // RET — giu RET-004 (hoan tien/diem), RET-007 (PIN)
        'RET-002','RET-003','RET-005',
        // INV — giu INV-002 (M2), INV-003 (don nhap), INV-007 (bien dong)
        'INV-004','INV-006','INV-008',
        // PIN — giu PIN-004 (PIN tam), PIN-005 (xin cap)
        'PIN-002','PIN-003','PIN-007','PIN-008','PIN-009',
        // MAN — giu MAN-003 (nhap sai bang chung), MAN-004 (trang soat)
        'MAN-002','MAN-005','MAN-006',
    ],
    // Ha xuong Thap — chi edge case thuc su; con lai roi ve Trung binh
    'thap' => [
        'ORD-013',          // giao tan noi dang tat
        'POS-008',          // giu don (da co PosWorkflowTest)
        'RET-006',          // ma phieu trung (edge)
        'INV-009',          // export xnt (da co FormulaInjection)
        'PRD-008',          // xoa SP M2 (quyen, da co MaDuyetTest)
        'CUS-009',          // lich su mua (read)
        'RPT-006',          // the doi soat (read)
        'IMP-017',          // thieu cot DB (edge)
        'PIN-009',          // moc lo PIN co dinh (da co test)
        'SEC-007',          // open redirect (edge)
        'SEC-010',          // UTF-8 (da co Utf8EncodingTest)
        'STO-009', 'STO-010',
        'NEWS-005', 'NEWS-006', 'NEWS-007',
        'CONT-003', 'CONT-005',
        'SEP-006',          // ghi chu chua test that
    ],
];
