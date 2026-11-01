<h1>Danh sách hóa đơn</h1>
<p>Kỳ: {{ $tuNgay }} đến {{ $denNgay }}</p>
<table border="1" cellpadding="4" cellspacing="0"><thead><tr><th>Mã</th><th>Thời gian</th><th>Thu ngân</th><th>Thanh toán</th></tr></thead><tbody>
@foreach ($rows as $row)<tr><td>{{ $row[0] }}</td><td>{{ $row[1] }}</td><td>{{ $row[2] }}</td><td>{{ number_format($row[5], 0, ',', '.') }}</td></tr>@endforeach
</tbody></table>
