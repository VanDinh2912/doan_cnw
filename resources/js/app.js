// Web POS — application JavaScript entry point.
// Vendor libraries are bundled by Vite so the app works offline (WAMP demo).

// CSS
import 'bootstrap/dist/css/bootstrap.min.css';
import 'datatables.net-bs5/css/dataTables.bootstrap5.min.css';
import '@fortawesome/fontawesome-free/css/all.min.css';

// JS globals — jQuery must be on window before DataTables loads.
import jQuery from 'jquery';
window.$ = window.jQuery = jQuery;

import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

import 'datatables.net-bs5';

import { Chart } from 'chart.js/auto';
window.Chart = Chart;

import './bootstrap';

/**
 * Initialise every table marked .js-data-table with the project's
 * Vietnamese locale and dense defaults.
 */
document.querySelectorAll('.js-data-table').forEach(function (table) {
    new window.DataTable(table, {
        language: {
            search: 'Tìm kiếm:',
            lengthMenu: 'Hiển thị _MENU_ dòng',
            info: 'Hiển thị _START_ đến _END_ trong tổng số _TOTAL_ dòng',
            infoEmpty: 'Không có dữ liệu',
            infoFiltered: '(lọc từ _MAX_ dòng)',
            zeroRecords: 'Không tìm thấy dữ liệu',
            paginate: { first: 'Đầu', last: 'Cuối', next: 'Sau', previous: 'Trước' },
        },
        pageLength: 10,
        order: [],
    });
});

// Bắt sự kiện error ở capture phase vì lỗi tải ảnh không nổi bọt; chỉ thay một lần để tránh lặp vô hạn.
document.addEventListener('error', function (event) {
    const img = event.target;
    if (!(img instanceof HTMLImageElement) || img.dataset.daFallback === '1') return;
    const fallback = document.body.dataset.imgFallback;
    if (fallback && img.src !== fallback) {
        img.dataset.daFallback = '1';
        img.src = fallback;
    }
}, true);