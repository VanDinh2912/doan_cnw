<?php
// Sinh docs/KIEM_THU/TestCase_HeThong.xlsx tu data-tc.php + route:list + tests/.
// Chay: php scripts/kiem-thu/sinh-xlsx.php
require __DIR__.'/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

$root = dirname(__DIR__, 2);
$tc = require __DIR__.'/data-tc.php';
$uutien = require __DIR__.'/uutien.php';
// Uu tien: rat_cao > thap > cao(override) > mac_dinh module 'Cao' > Trung binh
$ratCao = array_flip($uutien['rat_cao']);
$caoOv  = array_flip($uutien['cao']);
$thapOv = array_flip($uutien['thap']);
$tbOv   = array_flip($uutien['tb'] ?? []);
$macDinhCao = array_flip($uutien['mac_dinh_cao']);
function uuTienTC(string $ma, string $module, array $ratCao, array $caoOv, array $thapOv, array $tbOv, array $macDinhCao): string {
    if (isset($ratCao[$ma])) return 'Rất cao';
    if (isset($thapOv[$ma])) return 'Thấp';
    if (isset($caoOv[$ma])) return 'Cao';
    if (isset($tbOv[$ma])) return 'Trung bình';
    if (isset($macDinhCao[$module])) return 'Cao';
    return 'Trung bình';
}

// ---------- 1. Doc route:list -v ----------
chdir($root);
$out = shell_exec('php artisan route:list -v 2>NUL');
$out = preg_replace('/\x1b\[[0-9;]*m/', '', (string) $out);
$routes = [];
$cur = null;
foreach (preg_split('/\r?\n/', $out) as $line) {
    if (preg_match('/^\s*(GET\|HEAD|GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS)\s+(\S+)\s+\.+\s+([\w.\-]+)\s*›\s*(\S+)/', $line, $m)) {
        $cur = ['method'=>$m[1],'uri'=>trim($m[2]),'name'=>$m[3],'action'=>$m[4],'mw'=>[]];
        $routes[] = $cur;
    } elseif ($cur && preg_match('/⇂\s*(.+)$/u', $line, $m)) {
        $cur['mw'][] = trim($m[1]);
    } elseif (trim($line) !== '' && strpos($line, '─') === false && !str_starts_with(trim($line), 'GET') && !str_starts_with(trim($line), 'POST') && !str_starts_with(trim($line), 'PUT') && !str_starts_with(trim($line), 'DELETE') && !str_starts_with(trim($line), 'PATCH')) {
        // dong khac -> ket thuc route hien tai? giu nguyen, mw lines da bat
    }
}
// route:list -v: moi route 1 khoi; mw nam trong cac dong sau toi route tiep theo
// Loc lai: chi giu entry co action chua '@' hoac 'Closure'
$routes = array_values(array_filter($routes, fn($r)=>str_contains($r['action'],'@')||str_contains($r['action'],'Closure')||str_contains($r['action'],'__invoke')));

// ---------- 2. Test files ----------
$tests = [];
foreach (glob($root.'/tests/Feature/*.php') as $f) {
    $tests[] = basename($f, '.php');
}
$testsUnit = [];
foreach (glob($root.'/tests/Unit/*.php') as $f) { $testsUnit[] = basename($f,'.php'); }
$allTests = array_merge($tests, $testsUnit);

// ---------- 2b. Test refs (route/uri/class ma test goi toi) ----------
if (!is_file($root.'/storage/app/test-refs.json')) {
    shell_exec('php '.escapeshellarg($root.'/scripts/kiem-thu/trich-test-refs.php'));
}
$testRefs = json_decode((string)@file_get_contents($root.'/storage/app/test-refs.json'), true) ?: [];

// tu dien module -> tu khoa class App\ duoc tinh la "xac nhan" khi test goi toi
$modClassKw = [
 'STO'=>['Storefront','SanPham','LoaiSanPham','Cart','GioHang'],
 'ACC'=>['Auth','User','KhachHang','DoiMatKhau','TaiKhoan','Khach'],
 'ORD'=>['DatHang','DonHang','GiuHang','Order','Invoice'],
 'POS'=>['Pos','Invoice','Cart','GioHang','Payment','Diem','Customer','SanPham'],
 'RET'=>['TraHang','Return','Invoice','SanPham'],
 'INV'=>['Kho','Nhap','KiemKe','NhaCungCap','DonNhap','HangSanXuat','Phieu','SanPham','Product'],
 'PRD'=>['SanPham','LoaiSanPham','Hang','Brand','Category','Product'],
 'CUS'=>['KhachHang','Customer','Diem','User','Loyalty'],
 'NEWS'=>['TinTuc','BaiViet','BinhLuan','ChuDe','Post','Comment'],
 'CONT'=>['LienHe','Contact'],
 'RPT'=>['BaoCao','ThongKe','NhatKy','Dashboard','TongQuan','Report','Log'],
 'IMP'=>['Import','Export','AnhTuUrl','SanPhamImport','CotMapper','Excel','Formula'],
 'PIN'=>['Pin','MaDuyet','YeuCau','PinTam','PinService'],
 'MAN'=>['XacNhan','DoiSoat','Sepay','Webhook','ThuCong','Payment'],
 'SEC'=>[],
 'SEP'=>['Sepay','Webhook','Payment','VietQr','Qr','Invoice','DoiSoat'],
 'KHAC'=>[],
];
// route name -> module, uri -> module (cho xac nhan muc route)
$routeNameMod = []; $routeUriMod = [];
foreach ($routes as $rt) {
    $m = doanModule($rt['uri'], $rt['name']);
    $routeNameMod[$rt['name']] = $m;
    $seg = trim(explode('{', $rt['uri'])[0], '/');
    if ($seg !== '') $routeUriMod[$seg] = $m;
}
$allTestBase = array_map(fn($t)=>strtolower($t), $allTests);

// cham do tin cay anh xa cho 1 TC: 'xac nhan' | 'tu khoa' | 'khong co'
function chamDoTinCay(string $testCell, string $mod, array $testRefs, array $routeNameMod, array $routeUriMod, array $modClassKw, array $allTestBase): string {
    $names = [];
    foreach (preg_split('/[,;]/', $testCell) as $p) {
        $p = trim(preg_replace('/\s*\(.*$/', '', trim($p)));
        if ($p !== '' && in_array(strtolower($p), $allTestBase, true)) $names[] = $p;
    }
    if ($names === []) return 'không có';
    $totKiem = 0;
    foreach ($names as $nm) {
        $ref = $testRefs[$nm] ?? null;
        if (!$ref) continue;
        $totKiem++;
        // route name cung module
        foreach ($ref['routes'] ?? [] as $rn) {
            if (($routeNameMod[$rn] ?? null) === $mod) return 'xác nhận';
        }
        // uri cung module (so sanh doan tinh dau)
        foreach ($ref['uris'] ?? [] as $u) {
            $path = parse_url($u, PHP_URL_PATH) ?: $u;
            $seg = trim(explode('/', trim($path,'/'))[0] ?? '', '/');
            if ($seg !== '' && ($routeUriMod[$seg] ?? null) === $mod) return 'xác nhận';
        }
        // class App\ cung module
        foreach ($ref['classes'] ?? [] as $cl) {
            foreach ($modClassKw[$mod] ?? [] as $kw) {
                if (stripos($cl, $kw) !== false) return 'xác nhận';
            }
        }
    }
    return $totKiem > 0 ? 'từ khóa' : 'không có';
}

// ---------- 2c. pin_muc ----------
$pinMuc = require $root.'/config/pin_muc.php';

// ---------- 4. doan module cho route ----------
function doanModule(string $uri, string $name): string {
    $u = $uri.'/'. $name;
    $rules = [
        'IMP' => ['import','export','xuatexcel','template'],
        'MAN' => ['doisoat','xacnhan','thu-cong','thucu','sepay:doi'],
        'SEP' => ['webhook','thanhtoan','qr','viro','sepay','doisoat/xuly'],
        'PIN' => ['pin','duyet','xac-nhan-pin','yeu-cau'],
        'ORD' => ['dat-hang','don-hang','gio-hang','hold','tracuu','poll'],
        'POS'  => ['/pos','pos/','donhang.'],
        'RET'  => ['trahang','tra-hang','phieu-tra','tra.'],
        'INV'  => ['nhaphang','nhacungcap','kho','kiemke','hoadonnhap','hangsxuat','xuatnhapton','nhap-xuat'],
        'PRD'  => ['sanpham','san-pham','loaisanpham','hang-','/hang'],
        'CUS'  => ['khachhang','khach-hang','diem'],
        'NEWS' => ['bai-viet','tintuc','binhluan','chu-de','chude'],
        'CONT' => ['lien-he','lienhe','tuyen-dung','lienlac'],
        'RPT'  => ['baocao','thongke','tongquan','nhatky','dashboard'],
        'ACC'  => ['dangnhap','dang-ky','dangxuat','auth','doi-mat-khau','taikhoan','profile','ho-so','pin/tam','pin/xin'],
        'STO'  => ['/','danh-muc','san-pham/','tim-kiem','storefront','gio-hang'],
    ];
    foreach ($rules as $mod=>$keys) {
        foreach ($keys as $k) { if (str_contains($u, $k)) return $mod; }
    }
    return 'KHAC';
}
$tenModule = array_map(fn($m)=>$tc[$m]['sheet'] ?? $m, array_flip(array_map(fn($m)=>$m, array_keys($tc))));
$modLabel = [];
foreach ($tc as $k=>$v) $modLabel[$k] = preg_replace('/^\d+\s*/','',$v['sheet']);

// ---------- 5. tu khoa test theo module ----------
$kw = [
 'STO'=>['storefront','menu','phanch','trangchu'],
 'ACC'=>['auth','taikhoan','hosokhach','sdt','khachhangsodienthoai','dieubienthoai','stage1','guard','quanlytaikhoan'],
 'ORD'=>['dathang','donhang','nhanhang','giuhang','chongom','giay','email','chuanhoa','tracuu','pickup','nhanh','webhook'],
 'POS'=>['pos','workflow','thanhtoan','qr','phieugiaohang','diem','giamgia'],
 'RET'=>['trahang','phieutra'],
 'INV'=>['nhap','kiemke','kho','hangsanxuat','pdf','donnhap','nhacungcap','xuatnhapton','nhapkho'],
 'PRD'=>['sanpham','import','export','formula','hangsanxuat','loai'],
 'CUS'=>['khachhang','diem','hosokhach','lienket','sodienthoai'],
 'NEWS'=>['tintuc','binhluan','storefrontnoidung','cheten'],
 'CONT'=>['lienhe'],
 'RPT'=>['baocao','tongquan','nhatky','thongke','phantrang','taicu'],
 'IMP'=>['import','export','formula'],
 'PIN'=>['pin','mayduyet','duyet','kiemke'],
 'MAN'=>['xacnhan','doisoat','thucu','thucong'],
 'SEC'=>['bammat','utf8','logbi','dieuhuong','khoiblock','khautru','routekhoi','kiemtra','cheten','hin url'],
 'SEP'=>['sepay','webhook','thanhtoan','qr','doisoat','chi chowebhook'],
 'KHAC'=>[],
];
function timTest(array $tests, string $mod, array $kw): array {
    $hit=[];
    foreach ($tests as $t) {
        $tl=strtolower($t);
        foreach ($kw[$mod]??[] as $k) { if (str_contains($tl, strtolower($k))) { $hit[]=$t; break; } }
    }
    return $hit;
}

// tokens tu route: uri segments + name segments -> slug khong dau/gach
function tokensRoute(string $uri, string $name): array {
    $parts = array_merge(explode('/', $uri), explode('.', $name));
    $out = [];
    $stop = ['test','post','get','put','delete','patch','head','admin','user','local','web','api','con','index','show','store','create','edit','update','destroy','them','sua','xoa','danhsach','dat','form'];
    foreach ($parts as $p) {
        $p = preg_replace('/\{[^}]*\}/', '', $p);
        $slug = preg_replace('/[^a-z0-9]/', '', strtolower($p));
        if (strlen($slug) >= 4 && !in_array($slug, $stop, true)) $out[$slug] = true;
    }
    return array_keys($out);
}
function timTestRoute(array $tests, array $tokens): array {
    $hit = [];
    foreach ($tests as $t) {
        $tl = strtolower($t);
        foreach ($tokens as $tk) { if (str_contains($tl, $tk)) { $hit[] = $t; break; } }
    }
    return $hit;
}

// ---------- 6. build spreadsheet ----------
$ss = new Spreadsheet();
$ss->removeSheetByIndex(0);

$headerStyle = function($sheet){
    $sheet->getStyle('A1:O1')->getFont()->setBold(true);
    $sheet->getStyle('A1:O1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F4E78');
    $sheet->getStyle('A1:O1')->getFont()->getColor()->setRGB('FFFFFF');
    $sheet->freezePane('A2');
    $sheet->setAutoFilter("A1:O".max(2,$sheet->getHighestRow()));
    foreach (range('A','O') as $c) $sheet->getColumnDimension($c)->setAutoSize(true);
    $sheet->getStyle('A1:O'.$sheet->getHighestRow())->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
};
$loaiColor = ['chuc nang'=>'DDEBF7','phan quyen'=>'FCE4D6','bao mat'=>'F8CBAD','validate'=>'E2EFDA','bien'=>'E4DFEC','ghi chu'=>'FFF2CC'];

$tcCols = ['Mã TC','Module','Chức năng','Loại','Mô tả','Điều kiện tiên quyết','Vai trò','Các bước','Dữ liệu kiểm thử','Kết quả mong đợi','Ưu tiên','Test tự động tương ứng','Độ tin cậy ánh xạ','KẾT QUẢ THỰC TẾ','TRẠNG THÁI'];
$demTcTheoModule=[];$demCoTest=0;$tong=0;$thongKeTinCay=['xác nhận'=>0,'từ khóa'=>0,'không có'=>0];$thongKeUuTien=['Rất cao'=>0,'Cao'=>0,'Trung bình'=>0,'Thấp'=>0];$tcLookup=[];
foreach ($tc as $code=>$mod) {
    $sh = $ss->createSheet();
    $sh->setTitle(mb_substr($mod['sheet'],0,31));
    $sh->fromArray($tcCols, null, 'A1');
    $r=2;$i=0;
    foreach ($mod['items'] as $it) {
        $i++;
        $ma = $code.'-'.str_pad((string)$i,3,'0',STR_PAD_LEFT);
        $tcLookup[$ma] = [$modLabel[$code], $it[0]];
        $tinCay = chamDoTinCay((string)$it[9], $code, $testRefs, $routeNameMod, $routeUriMod, $modClassKw, $allTestBase);
        $thongKeTinCay[$tinCay]++;
        $uuTien = uuTienTC($ma, $code, $ratCao, $caoOv, $thapOv, $tbOv, $macDinhCao);
        $thongKeUuTien[$uuTien] = ($thongKeUuTien[$uuTien] ?? 0) + 1;
        // Rat cao ma chua co test xac nhan -> danh dau noi bat
        if ($uuTien === 'Rất cao' && $tinCay !== 'xác nhận') {
            $tinCay .= ' ⚠ chưa có test xác nhận';
        }
        $row=[$ma,$modLabel[$code],$it[0],$it[1],$it[2],$it[3],$it[4],$it[5],$it[6],$it[7],$uuTien,$it[9],$tinCay,'',''];
        $sh->fromArray($row,null,'A'.$r);
        $sh->getStyle('D'.$r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($loaiColor[$it[1]]??'FFFFFF');
        // to mau do tin cay: xanh la xac nhan, vang la tu khoa, do la khong co
        $mauTinCay=['xác nhận'=>'C6EFCE','từ khóa'=>'FFEB9C','không có'=>'FFC7CE'][$tinCay];
        $sh->getStyle('M'.$r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($mauTinCay);
        $r++;
    }
    $headerStyle($sh);
    $sh->getColumnDimension('H')->setWidth(45);$sh->getColumnDimension('I')->setWidth(28);
    $sh->getColumnDimension('N')->setWidth(16);$sh->getColumnDimension('O')->setWidth(14);
    $demTcTheoModule[$code]=['label'=>$modLabel[$code],'sheet'=>$mod['sheet'],'so_tc'=>$i,'co_test'=>0];
    foreach($mod['items'] as $it) if(trim((string)$it[9])!=='') $demTcTheoModule[$code]['co_test']++;
    $tong+=$i;$demCoTest+=$demTcTheoModule[$code]['co_test'];
}

function routeDuocTest(array $rt, array $testRefs): array {
    // route duoc test khi co file test goi route('name') hoac URI khop
    $hit = [];
    // regex tu uri: /a/{b}/c -> ^/a/[^/]+/c$
    $pat = '#^'.preg_replace('/\\{[^}]+\\}/', '[^/]+', preg_quote($rt['uri'], '#')).'$#';
    $static = trim(explode('{', $rt['uri'])[0], '/');
    foreach ($testRefs as $tname => $ref) {
        if (in_array($rt['name'], $ref['routes'] ?? [], true)) { $hit[] = $tname; continue; }
        foreach ($ref['uris'] ?? [] as $u) {
            $path = parse_url($u, PHP_URL_PATH) ?: $u;
            if (preg_match($pat, $path) || ($static !== '' && rtrim($path,'/') === '/'.$static)) { $hit[] = $tname; break; }
        }
    }
    return array_values(array_unique($hit));
}

// ---------- 7. Truy vet ----------
$sh=$ss->createSheet();$sh->setTitle('Truy vet');
$sh->fromArray(['STT','Method + URI','Route name','Action','Module','PIN (pin_muc)','Test tự động tìm thấy (gọi route/URI thật)','TC liên quan (điền khi kiểm thử)'],null,'A1');
$r=2;$stt=0;$soRouteCoTest=0;
foreach($routes as $rt){
    $mod=doanModule($rt['uri'],$rt['name']);
    $hitRoute = routeDuocTest($rt, $testRefs);
    if($hitRoute) $soRouteCoTest++;
    $hitTxt = implode(', ', array_slice($hitRoute,0,8)).(count($hitRoute)>8?' …(+' . (count($hitRoute)-8).')':'');
    $pin=$pinMuc[$rt['name']]??'';
    $stt++;
    $sh->fromArray([$stt,$rt['method'].' '.$rt['uri'],$rt['name'],$rt['action'],$modLabel[$mod]??$mod,$pin,$hitTxt,''],null,'A'.$r);
    if($hitRoute===[]) $sh->getStyle('G'.$r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8CBAD');
    $r++;
}
foreach(range('A','H') as $c)$sh->getColumnDimension($c)->setAutoSize(true);
$sh->getStyle('A1:H1')->getFont()->setBold(true);
$sh->getStyle('A1:H1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F4E78');
$sh->getStyle('A1:H1')->getFont()->getColor()->setRGB('FFFFFF');
$sh->freezePane('A2');$sh->setAutoFilter('A1:H'.$r);
$sh->getStyle('A1:H'.$r)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);

// ---------- 8. Khoang trong (route KHONG co test nao goi toi) ----------
// VAI TRO tu middleware: role:a,b | staff.only | auth | public
function tomTatVaiTro(array $mw): string {
    $mwS = implode(' ', $mw);
    $roles = [];
    if (preg_match_all('/role:([a-z,]+)/', $mwS, $mm)) {
        foreach ($mm[1] as $rs) foreach (explode(',', $rs) as $ro) $roles[trim($ro)] = true;
    }
    $out = array_keys($roles);
    if (str_contains($mwS, 'staff.only')) $out[] = 'staff';
    if (str_contains($mwS, 'auth') && $out === []) $out[] = 'da dang nhap (moi role)';
    if ($out === []) return 'guest/public';
    return implode(',', $out);
}
$laGhi = function(string $m): bool {
    // route da phuong thuc kieu "GET|HEAD" van la doc; chi tinh GHI khi co POST/PUT/PATCH/DELETE
    foreach (explode('|', strtoupper($m)) as $p) {
        if (in_array(trim($p), ['POST','PUT','PATCH','DELETE'], true)) return true;
    }
    return false;
};
$sh=$ss->createSheet();$sh->setTitle('Khoang trong');
$sh->fromArray(['STT','Nhóm','Method + URI','Route name','Action','Module','Mức PIN (pin_muc)','Vai trò (middleware)','Đề xuất test viết trước'],null,'A1');
$r=2;$stt=0;$soGhi=0;$soDoc=0;
$thutuModule = ['ORD'=>1,'POS'=>2,'RET'=>3,'INV'=>4,'PIN'=>5,'MAN'=>6,'IMP'=>7,'CUS'=>8,'ACC'=>9,'PRD'=>10,'RPT'=>11,'SEC'=>12,'SEP'=>13,'STO'=>14,'NEWS'=>15,'CONT'=>16,'KHAC'=>17];
$gapGhi=[];$gapDoc=[];
foreach($routes as $rt){
    $mod=doanModule($rt['uri'],$rt['name']);
    if($mod==='KHAC') continue;
    if(routeDuocTest($rt, $testRefs)===[]) {
        ($laGhi($rt['method']) ? $gapGhi[] = $rt + ['mod'=>$mod] : $gapDoc[] = $rt + ['mod'=>$mod]);
    }
}
usort($gapGhi, fn($a,$b)=>($thutuModule[$a['mod']]??99)<=>( $thutuModule[$b['mod']]??99));
usort($gapDoc, fn($a,$b)=>($thutuModule[$a['mod']]??99)<=>( $thutuModule[$b['mod']]??99));
$deXuat = 0;
foreach (array_merge($gapGhi, $gapDoc) as $idx => $rt) {
    $ghi = $laGhi($rt['method']);
    $ghi ? $soGhi++ : $soDoc++;
    $stt++;
    $pin=$pinMuc[$rt['name']]??'—';
    $vaiTro=tomTatVaiTro($rt['mw']);
    $deXuatTxt='';
    if ($ghi && $deXuat < 5) {
        $deXuat++;
        $deXuatTxt='tests/Feature/'.$rt['mod'].'GapTest.php — gọi route(\''.$rt['name'].'\') với payload hợp lệ + thiếu quyền, assert redirect/4xx và không ghi DB';
    }
    $nhom=$ghi?'GHI (POST/PUT/PATCH/DELETE)':'Đọc (GET)';
    $sh->fromArray([$stt,$nhom,$rt['method'].' '.$rt['uri'],$rt['name'],$rt['action'],$modLabel[$rt['mod']]??$rt['mod'],$pin,$vaiTro,$deXuatTxt],null,'A'.$r);
    if($ghi){$sh->getStyle('B'.$r)->getFont()->setBold(true);$sh->getStyle('B'.$r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8CBAD');}
    $r++;
}
if($stt===0){$sh->fromArray(['—','—','Không phát hiện route nào thiếu test','—','—','—','—','—','—'],null,'A2');}
$soKhoangTrong=$soGhi+$soDoc;
// Khoi de xuat 5 test (route GHI chua co test, uu tien module cham tien/tồn kho)
$r++;
$sh->fromArray(['','ĐỀ XUẤT 5 TEST VIẾT TRƯỚC','','','','','',''],null,'A'.$r);$r++;
$sh->fromArray(['','Route GHI chưa có test (xếp theo rủi ro: đơn hàng → POS → trả/kho → PIN → duyệt)','','','','','','',''],null,'A'.$r);$r++;
$dem=0;
foreach($gapGhi as $rt){
    if($dem>=5) break;
    $dem++;
    $sh->fromArray(['','  '.($dem).'. '.$rt['method'].' /'.$rt['uri'].'  ('.$rt['name'].') — PIN '.($pinMuc[$rt['name']]??'—').' — '.$modLabel[$rt['mod']],'','','','','','','Viết '.$rt['mod'].'GapTest: route(\''.$rt['name'].'\') happy-path + thiếu PIN/quyền'],null,'A'.$r);
    $r++;
}
foreach(range('A','I') as $c)$sh->getColumnDimension($c)->setAutoSize(true);
$sh->getStyle('A1:I1')->getFont()->setBold(true);
$sh->getStyle('A1:I1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C00000');
$sh->getStyle('A1:I1')->getFont()->getColor()->setRGB('FFFFFF');
$sh->freezePane('A2');$sh->setAutoFilter('A1:I'.$r);$sh->getStyle('A1:I'.$r)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
file_put_contents($root.'/storage/app/khoang-trong-count.txt', (string)$soKhoangTrong);
file_put_contents($root.'/storage/app/khoang-trong-ghi-count.txt', (string)$soGhi);
file_put_contents($root.'/storage/app/route-co-test-count.txt', (string)$soRouteCoTest);

// ---------- 9. Ma tran quyen ----------
$sh=$ss->createSheet();$sh->setTitle('Ma tran quyen');
$sh->fromArray(['Module','Guest (chưa login)','Admin','Thu ngân','Thủ kho','Khách hàng (khachhang)'],null,'A1');
$r=2;
$roleCols=['admin'=>'C','thungan'=>'D','thukho'=>'E','khachhang'=>'F'];
$matrix=[];
foreach($tc as $code=>$mod){
    $modRoutes=array_filter($routes,fn($rt)=>doanModule($rt['uri'],$rt['name'])===$code);
    $allow=['guest'=>false,'admin'=>false,'thungan'=>false,'thukho'=>false,'khachhang'=>false];
    foreach($modRoutes as $rt){
        $mw=implode(' ',$rt['mw']);
        $coAuth=str_contains($mw,'auth')||str_contains($mw,'role:')||str_contains($mw,'staff.only');
        if(!$coAuth) $allow['guest']=true;
        // route public storefront cung danh cho khach da login
        if(!$coAuth) $allow['khachhang']=true;
        if(preg_match_all('/role:([a-z,]+)/',$mw,$mm)){
            foreach($mm[1] as $roles) foreach(explode(',',$roles) as $ro) $allow[trim($ro)]=true;
        }
        // auth ma khong role -> moi role dang nhap deu vao duoc (ke ca khach)
        if(str_contains($mw,'auth') && !str_contains($mw,'role:') && !str_contains($mw,'staff.only')){
            foreach(['admin','thungan','thukho','khachhang'] as $ro)$allow[$ro]=true;
        }
        if(str_contains($mw,'staff.only')){foreach(['admin','thungan','thukho'] as $ro)$allow[$ro]=true;}
    }
    $matrix[$modLabel[$code]]=$allow;
    $row=[$modLabel[$code]];
    foreach(['guest','admin','thungan','thukho','khachhang'] as $k)$row[]=$allow[$k]?'✓':'—';
    $sh->fromArray($row,null,'A'.$r);
    // to dam o duoc phep
    foreach(['guest','admin','thungan','thukho','khachhang'] as $k){
        $col=$roleCols[$k]??'B';
        if($k==='guest')$col='B';
        if($allow[$k]){$sh->getStyle($col.$r)->getFont()->setBold(true);$sh->getStyle($col.$r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2EFDA');}
    }
    $r++;
}
$sh->fromArray(['Ghi chú','✓ = có ít nhất 1 route trong module cho phép vai trò đó (đọc từ middleware route:list -v). — = không route nào. Guest gồm cả khách đã login vào khu public.','—','—','—','—'],null,'A'.($r+1));
foreach(range('A','F') as $c)$sh->getColumnDimension($c)->setAutoSize(true);
$sh->getStyle('A1:F1')->getFont()->setBold(true);
$sh->getStyle('A1:F1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F4E78');
$sh->getStyle('A1:F1')->getFont()->getColor()->setRGB('FFFFFF');
$sh->freezePane('A2');$sh->setAutoFilter('A1:F'.$r);$sh->getStyle('A1:F'.$r)->getAlignment()->setWrapText(true);

// ---------- 9b. Kich ban demo (anh xa tu docs/KICH_BAN_BAO_VE.md, KHONG chua MK/PIN/URL) ----------
$sh=$ss->createSheet();$sh->setTitle('Kich ban demo');
$sh->fromArray(['STT','Tên bước demo','Chặng','Vai trò','TC liên quan','Ghi chú'],null,'A1');
$demo=[
 [1,'Mở trang chủ — hero, danh mục, sản phẩm nổi bật','Chặng 1 — Storefront','Khách (guest)','STO-001',''],
 [2,'Tìm kiếm + lọc theo hãng','Chặng 1 — Storefront','Khách (guest)','STO-004, STO-002, PRD-005',''],
 [3,'Chi tiết sản phẩm — giá, tồn, nhãn còn hàng','Chặng 1 — Storefront','Khách (guest)','STO-003, STO-007',''],
 [4,'Thêm giỏ → xem giỏ','Chặng 1 — Storefront','Khách (guest)','STO-005, STO-006',''],
 [5,'Đặt hàng nhận tại cửa hàng (pickup) — đơn giữ kho','Chặng 1 — Storefront','Khách (guest)','ORD-002, ORD-003, ORD-011',''],
 [6,'Tra cứu đơn bằng mã + SĐT','Chặng 1 — Storefront','Khách (guest)','ORD-008',''],
 [7,'Tin tức: đọc bài + bình luận (tên rút gọn)','Chặng 1 — Storefront','Khách','NEWS-001, NEWS-002, NEWS-003, NEWS-004',''],
 [8,'Gửi form liên hệ + throttle','Chặng 1 — Storefront','Khách (guest)','CONT-001, CONT-002, CONT-004',''],
 [9,'Đăng nhập vai trò thu ngân','Chặng 2 — POS','Thu ngân','ACC-003, ACC-007',''],
 [10,'POS: tìm SP theo barcode/SKU, tồn 0 bị chặn','Chặng 2 — POS','Thu ngân','POS-003, PRD-004',''],
 [11,'Áp giảm giá + chọn khách tích điểm','Chặng 2 — POS','Thu ngân','POS-004, CUS-004, PIN-006',''],
 [12,'Giữ đơn rồi mở lại','Chặng 2 — POS','Thu ngân','POS-007',''],
 [13,'Thanh toán tiền mặt → in hóa đơn, trừ kho','Chặng 2 — POS','Thu ngân','POS-005, POS-006, INV-007',''],
 [14,'Tạo phiếu trả hàng → admin duyệt','Chặng 2 — POS','Thu ngân + Admin','RET-001, RET-004, RET-007',''],
 [15,'Xử lý đơn online vừa đặt','Chặng 2 — POS','Thu ngân','ORD-010, ORD-009',''],
 [16,'Nhập kho nhanh 1 SP','Chặng 3 — Kho','Thủ kho','INV-001, INV-002',''],
 [17,'Đơn nhập từ NCC → duyệt → nhận hàng','Chặng 3 — Kho','Thủ kho','INV-003, INV-006',''],
 [18,'Kiểm kê: tạo phiếu → nhập thực tế → chênh lệch → duyệt','Chặng 3 — Kho','Thủ kho + Admin','INV-005, INV-007',''],
 [19,'Xuất nhập tồn + lọc khoảng ngày + export','Chặng 3 — Kho','Thủ kho','INV-009, RPT-003',''],
 [20,'Dashboard + thống kê/lợi nhuận + cảnh báo giá vốn','Chặng 4 — Admin','Admin','RPT-001, RPT-002, PRD-007',''],
 [21,'Quản lý hãng + lọc storefront','Chặng 4 — Admin','Admin','PRD-006',''],
 [22,'Import Excel 2 bước: file lỗi + file 120 dòng','Chặng 4 — Admin','Admin','IMP-001..IMP-017',''],
 [23,'Khóa khách hàng → chặn đăng nhập/đặt hàng','Chặng 4 — Admin','Admin','CUS-006, ACC-008',''],
 [24,'Đặt lại MK khách — MK tạm hiện 1 lần','Chặng 4 — Admin','Admin','CUS-007, PIN-004',''],
 [25,'Nhật ký hoạt động + export','Chặng 4 — Admin','Admin','RPT-004, RPT-005',''],
];
$r=2;foreach($demo as $d){$sh->fromArray([$d[0],$d[1],$d[2],$d[3],$d[4],$d[5]],null,'A'.$r);$r++;}
foreach(range('A','F') as $c)$sh->getColumnDimension($c)->setAutoSize(true);
$sh->getStyle('A1:F1')->getFont()->setBold(true);
$sh->getStyle('A1:F1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F4E78');
$sh->getStyle('A1:F1')->getFont()->getColor()->setRGB('FFFFFF');
$sh->freezePane('A2');$sh->setAutoFilter('A1:F'.($r-1));$sh->getStyle('A1:F'.($r-1))->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
$sh->fromArray(['Ghi chú','Ánh xạ từ docs/KICH_BAN_BAO_VE.md (file gitignored). Cột TC là mã trong bộ test case này.','—','—','—','—'],null,'A'.($r+1));

// ---------- 9c. Danh sach chay tay truoc bao ve (chon loc, co thu tu + thoi gian) ----------
$sh=$ss->createSheet();$sh->setTitle('Chay tay truoc bao ve');
$sh->fromArray(['STT','Mã TC','Module','Chức năng','Thời gian ~phút','Ghi chú'],null,'A1');
// A — chi doc, an toan tren du lieu that. B — co ghi du lieu, theo thu tu: dat don -> thanh toan -> nhan hang -> tra -> kho -> PIN/quyen -> import
$dsA=[
 ['STO-001',3,''],['STO-002',2,''],['STO-003',2,''],['STO-004',3,''],
 ['ORD-008',3,'Tra cứu chỉ đọc, không tạo đơn'],
 ['NEWS-001',2,''],['NEWS-002',2,''],
 ['RPT-001',3,'Dashboard'],['RPT-004',3,'Nhật ký'],
 ['MAN-006',3,'Trang soát/đối soát chỉ xem'],['SEC-010',2,'Probe UTF-8'],
];
$dsB=[
 ['ACC-003',3,'Đăng nhập — cần trước mọi thao tác NV'],
 ['ORD-002',5,'Đặt đơn pickup → tạo đơn thật'],
 ['ORD-010',4,'NV xử lý/nhận đơn online'],
 ['POS-005',5,'Bán + thanh toán tiền mặt'],
 ['POS-006',2,'In hóa đơn'],
 ['RET-001',5,'Tạo phiếu trả từ HĐ'],
 ['POS-004',4,'Giảm giá/điểm — có thể chạm PIN'],
 ['INV-001',4,'Nhập kho nhanh'],
 ['INV-005',7,'Kiểm kê: tạo→nhập→chênh lệch→duyệt'],
 ['CUS-006',4,'Khóa khách hàng'],
 ['PIN-001',4,'Nhập PIN đúng mức'],
 ['PIN-006',4,'Mã duyệt hai người'],
 ['MAN-001',4,'NV xác nhận bằng chứng'],
 ['IMP-001',4,'Upload + preview import (gồm all-or-nothing IMP-007)'],
 ['ACC-008',4,'Phân quyền vai trò'],
];
$r=2;
$sh->fromArray(['','NHÓM A — CHỈ ĐỌC (an toàn chạy trên dữ liệu thật)','','','',''],null,'A'.$r);$r++;
$tongA=0;$sttA=0;
foreach($dsA as $d){
    $sttA++;$tongA+=$d[1];
    [$mod,$chucNang]=$tcLookup[$d[0]]??['—','—'];
    $sh->fromArray([$stt,$d[0],$mod,$chucNang,$d[1],$d[2]],null,'A'.$r);
    $sh->getStyle('D'.$r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2EFDA');
    $r++;
}
$sh->fromArray(['','Tổng A','',"$sttA TC","~{$tongA} phút",''],null,'A'.$r);$r+=2;
$tongB=0;$sttB=0;
$sh->fromArray(['','NHÓM B — CÓ GHI DỮ LIỆU (chạy trên doan_cnw; dữ liệu sẽ lưu lại — dùng quy ước dữ liệu thử TEST/[thử], xem thu-test:liet-ke)','','','',''],null,'A'.$r);$r++;
foreach($dsB as $d){
    $sttB++;$tongB+=$d[1];
    [$mod,$chucNang]=$tcLookup[$d[0]]??['—','—'];
    $sh->fromArray([$stt,$d[0],$mod,$chucNang,$d[1],$d[2].' — chạy trên doan_cnw; dữ liệu sẽ lưu lại; dùng quy ước dữ liệu thử'],null,'A'.$r);
    $sh->getStyle('D'.$r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FCE4D6');
    $r++;
}
$sh->fromArray(['','Tổng B','',"$sttB TC","~{$tongB} phút",''],null,'A'.$r);$r++;
$sh->fromArray(['','Tổng cộng','',$sttA.' + '.$sttB.' TC',"~".($tongA+$tongB)." phút (45–60' nếu chạy đúng nhịp, chưa tính thời gian chuẩn bị dữ liệu)",''],null,'A'.$r);
foreach(range('A','F') as $c)$sh->getColumnDimension($c)->setAutoSize(true);
$sh->getStyle('A1:F1')->getFont()->setBold(true);
$sh->getStyle('A1:F1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F4E78');
$sh->getStyle('A1:F1')->getFont()->getColor()->setRGB('FFFFFF');
$sh->freezePane('A2');$sh->getStyle('A1:F'.$r)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);

// ---------- 10. Tong hop ----------
$sh=$ss->createSheet();$sh->setTitle('Tong hop');
$rows=[['BỘ TEST CASE TOÀN HỆ THỐNG','','','','',''],['','Ngày tạo','2026-11-25','','',''],['','Nguồn','route:list -v, config/pin_muc.php, tests/Feature+Unit, test-refs.json, uutien.php','','',''],['','Số module','','','',''],['','Tổng số TC','','','',''],['','TC có test tự động tương ứng (ghi ở cột L)','','','',''],['','TC — ánh xạ XÁC NHẬN (test gọi route/URI/service cùng module)',"{$thongKeTinCay['xác nhận']}",'','',''],['','TC — ánh xạ chỉ TỪ KHÓA (test tồn tại, chưa chứng minh gọi đúng route)',"{$thongKeTinCay['từ khóa']}",'','',''],['','TC — KHÔNG CÓ test',"{$thongKeTinCay['không có']}",'','',''],['','TC theo ưu tiên — Rất cao',"{$thongKeUuTien['Rất cao']}",'','',''],['','TC theo ưu tiên — Cao',"{$thongKeUuTien['Cao']}",'','',''],['','TC theo ưu tiên — Trung bình',"{$thongKeUuTien['Trung bình']}",'','',''],['','TC theo ưu tiên — Thấp',"{$thongKeUuTien['Thấp']}",'','',''],['','Số route hệ thống',''.count($routes),'','',''],['','Route có test gọi tới (route()/URI thật)',"$soRouteCoTest",'','',''],['','Route KHÔNG có test gọi tới (sheet Khoảng trống)',"$soKhoangTrong (GHI: $soGhi | Đọc: $soDoc)",'','',''],['','Kết quả php artisan test --parallel gần nhất','1191 tests, 5258 assertions, 46 skipped, 0 fail — 2026-11-25','','',''],['','TC chạy tay trước bảo vệ','A chỉ đọc: '.$sttA.' TC (~'.$tongA.' phút) | B có ghi: '.$sttB.' TC (~'.$tongB.' phút) — sheet "Chay tay truoc bao ve"','','','']];
$rows[]=['','Module','Số TC','TC có test','',''];
foreach($demTcTheoModule as $v)$rows[]=['','',$v['label'],$v['so_tc'],$v['co_test'],'',''];
$sh->fromArray($rows,null,'A1');
$sh->getStyle('A1:F1')->getFont()->setBold(true)->setSize(14);
$hdrRow = 2 + 17; // title + 17 dong thong ke
$sh->getStyle('A'.$hdrRow.':F'.$hdrRow)->getFont()->setBold(true);
$sh->getStyle('B'.($hdrRow+1).':F'.($hdrRow+count($demTcTheoModule)))->getAlignment()->setWrapText(true);
foreach(range('A','F') as $c)$sh->getColumnDimension($c)->setAutoSize(true);

// ---------- ghi file ----------
@mkdir($root.'/docs/KIEM_THU',0777,true);
$writer=new Xlsx($ss);
$writer->setPreCalculateFormulas(false);
$writer->save($root.'/docs/KIEM_THU/TestCase_HeThong.xlsx');
echo "OK: docs/KIEM_THU/TestCase_HeThong.xlsx\n";
echo "TC=$tong co_test=$demCoTest routes=".count($routes)." routes_co_test=$soRouteCoTest khoang_trong=$soKhoangTrong (ghi=$soGhi doc=$soDoc)\n";
echo "tin_cay: xac_nhan={$thongKeTinCay['xác nhận']} tu_khoa={$thongKeTinCay['từ khóa']} khong_co={$thongKeTinCay['không có']}\n";
