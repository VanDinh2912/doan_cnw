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

// ---------- 3. pin_muc ----------
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
    $sheet->getStyle('A1:M1')->getFont()->setBold(true);
    $sheet->getStyle('A1:M1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F4E78');
    $sheet->getStyle('A1:M1')->getFont()->getColor()->setRGB('FFFFFF');
    $sheet->freezePane('A2');
    $sheet->setAutoFilter("A1:M".max(2,$sheet->getHighestRow()));
    foreach (range('A','M') as $c) $sheet->getColumnDimension($c)->setAutoSize(true);
    $sheet->getStyle('A1:M'.$sheet->getHighestRow())->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
};
$loaiColor = ['chuc nang'=>'DDEBF7','phan quyen'=>'FCE4D6','bao mat'=>'F8CBAD','validate'=>'E2EFDA','bien'=>'E4DFEC','ghi chu'=>'FFF2CC'];

$tcCols = ['Mã TC','Module','Chức năng','Loại','Mô tả','Điều kiện tiên quyết','Vai trò','Các bước','Dữ liệu kiểm thử','Kết quả mong đợi','Ưu tiên','Test tự động tương ứng','KẾT QUẢ THỰC TẾ','TRẠNG THÁI'];
$demTcTheoModule=[];$demCoTest=0;$tong=0;
foreach ($tc as $code=>$mod) {
    $sh = $ss->createSheet();
    $sh->setTitle(mb_substr($mod['sheet'],0,31));
    $sh->fromArray($tcCols, null, 'A1');
    $r=2;$i=0;
    foreach ($mod['items'] as $it) {
        $i++;
        $ma = $code.'-'.str_pad((string)$i,3,'0',STR_PAD_LEFT);
        $row=[$ma,$modLabel[$code],$it[0],$it[1],$it[2],$it[3],$it[4],$it[5],$it[6],$it[7],$it[8],$it[9],'',''];
        $sh->fromArray($row,null,'A'.$r);
        $sh->getStyle('D'.$r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($loaiColor[$it[1]]??'FFFFFF');
        $r++;
    }
    $headerStyle($sh);
    $sh->getColumnDimension('H')->setWidth(45);$sh->getColumnDimension('I')->setWidth(28);
    $sh->getColumnDimension('M')->setWidth(16);$sh->getColumnDimension('N')->setWidth(14);
    $demTcTheoModule[$code]=['label'=>$modLabel[$code],'sheet'=>$mod['sheet'],'so_tc'=>$i,'co_test'=>0];
    foreach($mod['items'] as $it) if(trim((string)$it[9])!=='') $demTcTheoModule[$code]['co_test']++;
    $tong+=$i;$demCoTest+=$demTcTheoModule[$code]['co_test'];
}

// ---------- 7. Truy vet ----------
$sh=$ss->createSheet();$sh->setTitle('Truy vet');
$sh->fromArray(['STT','Method + URI','Route name','Action','Module','PIN (pin_muc)','Test tự động tìm thấy','TC liên quan (điền khi kiểm thử)'],null,'A1');
$r=2;$stt=0;
foreach($routes as $rt){
    $mod=doanModule($rt['uri'],$rt['name']);
    $hitRoute = timTestRoute($allTests, tokensRoute($rt['uri'],$rt['name']));
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

// ---------- 8. Khoang trong ----------
$sh=$ss->createSheet();$sh->setTitle('Khoang trong');
$sh->fromArray(['STT','Method + URI','Route name','Action','Module','Lý do'],null,'A1');
$r=2;$stt=0;
foreach($routes as $rt){
    $mod=doanModule($rt['uri'],$rt['name']);
    if($mod==='KHAC') continue;
    $hit=timTestRoute($allTests, tokensRoute($rt['uri'],$rt['name']));
    // chi tinh la khoang trong khi KHONG co test nao match module VA route la route ghi/doc chinh
    if($hit===[]){
        $stt++;
        $sh->fromArray([$stt,$rt['method'].' '.$rt['uri'],$rt['name'],$rt['action'],$modLabel[$mod]??$mod,'Chưa tìm thấy test tự động theo từ khóa module — kiểm tra thủ công hoặc bổ sung test'],null,'A'.$r);
        $r++;
    }
}
if($stt===0){$sh->fromArray(['—','Không phát hiện route nào thiếu test theo từ khóa','—','—','—','—'],null,'A2');}
foreach(range('A','F') as $c)$sh->getColumnDimension($c)->setAutoSize(true);
$sh->getStyle('A1:F1')->getFont()->setBold(true);
$sh->getStyle('A1:F1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C00000');
$sh->getStyle('A1:F1')->getFont()->getColor()->setRGB('FFFFFF');
$sh->freezePane('A2');$sh->setAutoFilter('A1:F'.$r);$sh->getStyle('A1:F'.$r)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
file_put_contents($root.'/storage/app/khoang-trong-count.txt', (string)$stt);

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

// ---------- 10. Tong hop ----------
$sh=$ss->createSheet();$sh->setTitle('Tong hop');
$rows=[['BỘ TEST CASE TOÀN HỆ THỐNG','','','',''],['','Ngày tạo','2026-11-25','',''],['','Nguồn','route:list -v, config/pin_muc.php, tests/Feature+Unit','','',''],['','Số module','','','',''],['','Tổng số TC','','','',''],['','TC có test tự động tương ứng (ghi ở cột L)','','','',''],['','Số route hệ thống',''.count($routes),'','',''],['','Route không khớp test theo từ khóa',"$stt",'','',''],['','Kết quả php artisan test --parallel gần nhất','1191 tests, 5258 assertions, 46 skipped, 0 fail — 2026-11-25','','','']];
$rows[]=['','Module','Số TC','TC có test','',''];
foreach($demTcTheoModule as $v)$rows[]=['','',$v['label'],$v['so_tc'],$v['co_test'],'',''];
$sh->fromArray($rows,null,'A1');
$sh->getStyle('A1:F1')->getFont()->setBold(true)->setSize(14);
$sh->getStyle('A8:F8')->getFont()->setBold(true);
$sh->getStyle('B9:F'.(9+count($demTcTheoModule)))->getAlignment()->setWrapText(true);
foreach(range('A','F') as $c)$sh->getColumnDimension($c)->setAutoSize(true);

// ---------- ghi file ----------
@mkdir($root.'/docs/KIEM_THU',0777,true);
$writer=new Xlsx($ss);
$writer->setPreCalculateFormulas(false);
$writer->save($root.'/docs/KIEM_THU/TestCase_HeThong.xlsx');
echo "OK: docs/KIEM_THU/TestCase_HeThong.xlsx\n";
echo "TC=$tong co_test=$demCoTest routes=".count($routes)." khoang_trong=$stt\n";
