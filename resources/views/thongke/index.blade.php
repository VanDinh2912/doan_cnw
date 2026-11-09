@extends('layouts.master')

@section('title', 'Thống kê')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Thống kê bán hàng</h1>
        <p class="page-subtitle">Doanh thu và hiệu suất sản phẩm</p>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">Doanh thu theo ngày</div>
            <div class="card-body">
                <canvas id="doanhThuChart" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">Top 5 sản phẩm bán chạy</div>
            <div class="card-body">
                <canvas id="topSanPhamChart" height="180"></canvas>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const token = name => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
const formatTien = value => new Intl.NumberFormat('vi-VN').format(value) + ' đ';

const COLOR_PRIMARY = token('--color-primary');
const COLOR_SUCCESS = token('--color-success');
const COLOR_GRID    = token('--color-border');
const COLOR_TEXT    = token('--color-fg-muted');
const FONT_BODY     = token('--prim-font-body');

Chart.defaults.font.family = FONT_BODY;
Chart.defaults.color = COLOR_TEXT;

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
                borderColor: COLOR_PRIMARY,
                backgroundColor: 'rgba(37, 99, 235, .12)',
                borderWidth: 2,
                pointRadius: 3,
                pointHoverRadius: 5,
                fill: true,
                tension: .25
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { grid: { display: false }, border: { color: COLOR_GRID } },
                y: {
                    beginAtZero: true,
                    grid: { color: COLOR_GRID },
                    border: { display: false },
                    ticks: { callback: value => formatTien(value) }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: context => formatTien(context.raw) } }
            }
        }
    });

    new Chart(document.getElementById('topSanPhamChart'), {
        type: 'bar',
        data: {
            labels: topSanPhams.map(item => item.ten_sanpham),
            datasets: [{
                label: 'Số lượng bán',
                data: topSanPhams.map(item => Number(item.tong_so_luong)),
                backgroundColor: COLOR_SUCCESS,
                borderRadius: 4,
                maxBarThickness: 22
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    beginAtZero: true,
                    grid: { color: COLOR_GRID },
                    border: { display: false },
                    ticks: { precision: 0 }
                },
                y: { grid: { display: false }, border: { color: COLOR_GRID } }
            },
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