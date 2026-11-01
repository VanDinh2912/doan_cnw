@extends('layouts.master')

@section('title', 'Thống kê')

@section('content')
<h1 class="h3 mb-4">Thống kê bán hàng</h1>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Doanh thu theo ngày</div>
            <div class="card-body">
                <canvas id="doanhThuChart" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Top 5 sản phẩm bán chạy</div>
            <div class="card-body">
                <canvas id="topSanPhamChart" height="180"></canvas>
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
const formatTien = value => new Intl.NumberFormat('vi-VN').format(value) + ' đ';

Promise.all([
    fetch(@json(route('thongke.doanhthu'))).then(response => response.json()),
    fetch(@json(route('thongke.topsanpham'))).then(response => response.json())
]).then(([doanhThu, topSanPhams]) => {
    new Chart(document.getElementById('doanhThuChart'), {
        type: 'line',
        data: {
            labels: doanhThu.map(item => item.ngay),
            datasets: [{
                label: 'Doanh thu',
                data: doanhThu.map(item => Number(item.doanh_thu)),
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, .15)',
                fill: true,
                tension: .25
            }]
        },
        options: {
            responsive: true,
            scales: { y: { ticks: { callback: value => formatTien(value) } } },
            plugins: { tooltip: { callbacks: { label: context => formatTien(context.raw) } } }
        }
    });

    new Chart(document.getElementById('topSanPhamChart'), {
        type: 'bar',
        data: {
            labels: topSanPhams.map(item => item.ten_sanpham),
            datasets: [{
                label: 'Số lượng bán',
                data: topSanPhams.map(item => Number(item.tong_so_luong)),
                backgroundColor: '#198754'
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            scales: { x: { beginAtZero: true, ticks: { precision: 0 } } },
            plugins: { legend: { display: false } }
        }
    });
}).catch(() => {
    document.querySelectorAll('canvas').forEach(canvas => {
        canvas.replaceWith(Object.assign(document.createElement('p'), {
            className: 'text-danger mb-0',
            textContent: 'Không thể tải dữ liệu thống kê.'
        }));
    });
});
</script>
@endpush
@endsection
