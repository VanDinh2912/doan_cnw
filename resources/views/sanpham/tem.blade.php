<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Tem {{ $sanPham->sku }}</title>
    <style>
        @page { size: 50mm 30mm; margin: 0; }
        body { margin: 0; font-family: Arial, sans-serif; }
        .actions { margin: 1rem; }
        .labels { display: grid; grid-template-columns: repeat(3, 50mm); gap: 2mm; }
        .label { width: 50mm; height: 30mm; box-sizing: border-box; padding: 2mm; text-align: center; overflow: hidden; }
        .label svg { width: 45mm; height: 15mm; }
        .name { font-size: 10px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .code { font-size: 9px; }
        @media print {
            .actions { display: none; }
            .labels { gap: 0; }
        }
    </style>
</head>
<body>
<div class="actions">
    <form method="get">
        <label>Số lượng <input type="number" name="soluong_tem" min="1" max="100" value="{{ $soLuongTem }}"></label>
        <button type="submit">Cập nhật</button>
        <button type="button" onclick="window.print()">In tem</button>
    </form>
</div>
<div class="labels">
    @for ($i = 0; $i < $soLuongTem; $i++)
        <div class="label">
            <div class="name">{{ $sanPham->ten_sanpham }}</div>
            {!! $barcodeSvg !!}
            <div class="code">{{ $maVach }}</div>
        </div>
    @endfor
</div>
</body>
</html>
