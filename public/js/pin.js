/* PIN step-up: hộp thoại nhập PIN cho form[data-can-pin] và fetch 423.
 * Không chứa logic nghiệp vụ; chỉ điều phối UI + gọi /xac-nhan-pin/kiem-tra.
 */
(function () {
    'use strict';

    var KIEM_TRA = '/xac-nhan-pin/kiem-tra';
    var dialog = null;
    var pending = null; // {resolve, reject, retry}

    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    function openDialog(muc, onOk, onCancel) {
        closeDialog();
        dialog = document.createElement('div');
        dialog.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;display:flex;align-items:center;justify-content:center;';
        dialog.innerHTML =
            '<div style="background:#fff;border-radius:12px;padding:1.5rem;width:320px;max-width:92vw;box-shadow:0 8px 30px rgba(0,0,0,.25)">' +
            '<h5 style="margin:0 0 .5rem">Xác nhận PIN' + (muc === 'nghiemtrong' ? ' (thao tác nhạy cảm)' : '') + '</h5>' +
            '<p style="font-size:.85rem;color:#666;margin:.25rem 0 .75rem">Nhập PIN 6 số của bạn.</p>' +
            '<input id="pinDlgInput" type="password" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="off" ' +
            'style="width:100%;text-align:center;font-size:1.4rem;letter-spacing:.35rem;padding:.5rem;border:1px solid #ccc;border-radius:8px">' +
            '<div id="pinDlgErr" style="color:#b02a37;font-size:.85rem;min-height:1.2em;margin-top:.4rem"></div>' +
            '<div style="display:flex;gap:.5rem;margin-top:.75rem">' +
            '<button id="pinDlgOk" class="btn btn-primary" style="flex:1">Xác nhận</button>' +
            '<button id="pinDlgCancel" class="btn btn-secondary" style="flex:1">Hủy</button></div></div>';
        document.body.appendChild(dialog);
        var input = dialog.querySelector('#pinDlgInput');
        var err = dialog.querySelector('#pinDlgErr');
        input.focus();

        function done(val) { closeDialog(); if (onOk) onOk(val); }
        dialog.querySelector('#pinDlgCancel').onclick = function () { closeDialog(); if (onCancel) onCancel(); };
        dialog.querySelector('#pinDlgOk').onclick = submit;
        input.addEventListener('keydown', function (e) { if (e.key === 'Enter') submit(); });
        function submit() {
            var pin = input.value.trim();
            if (!/^\d{6}$/.test(pin)) { err.textContent = 'PIN gồm đúng 6 chữ số.'; return; }
            err.textContent = 'Đang kiểm tra…';
            fetch(KIEM_TRA, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ pin: pin, muc: muc, hanh_dong: pendingHanhDong })
            }).then(function (r) { return r.json().then(function (j) { return { s: r.status, j: j }; }); })
              .then(function (res) {
                  if (res.j && res.j.ok) { done(res.j.token || true); return; }
                  var msg = (res.j && res.j.message) || 'PIN không đúng.';
                  if (res.j && res.j.ma === 'KHOA') { done(null, msg); return; }
                  err.textContent = msg;
                  input.select();
              })
              .catch(function () { err.textContent = 'Lỗi kết nối, thử lại.'; });
        }
    }

    function closeDialog() { if (dialog) { dialog.remove(); dialog = null; } }

    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        if (m) return m.content;
        var i = document.querySelector('input[name="_token"]');
        return i ? i.value : '';
    }

    var pendingHanhDong = '';

    // 1) Chặn submit các form đánh dấu data-can-pin
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        var mucRaw = form.getAttribute('data-can-pin');
        if (!mucRaw) return;
        // m2 = mã duyệt hai-người: không chặn ở đây, server trả CAN_MA_DUYET
        // và pin.js phần fetch/dialog mã duyệt xử lý (commit giao diện).
        if (mucRaw === 'm2') return;
        var muc = mucRaw === 'm1' ? 'thuong' : (mucRaw === 'a' ? 'nghiemtrong' : mucRaw);
        e.preventDefault();
        e.stopImmediatePropagation();
        var action = form.getAttribute('action') || window.location.pathname;
        pendingHanhDong = (form.method || 'POST').toUpperCase() + ' ' + new URL(action, window.location.origin).pathname;
        openDialog(muc, function (token) {
            if (token === null) return; // bị hủy/khóa
            if (token !== true) { // nghiêmtrong: gắn mã một lần
                var inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = '_pin_token'; inp.value = token;
                form.appendChild(inp);
            }
            form.submit();
        }, function () { /* hủy */ });
    }, true);

    // 2) Bắt fetch/AJAX trả 423 CAN_PIN -> mở dialog -> thử lại 1 lần
    var _fetch = window.fetch;
    window.fetch = function () {
        var args = arguments;
        return _fetch.apply(this, args).then(function (res) {
            if (res.status !== 423) return res;
            return res.clone().json().then(function (j) {
                if (!j || (j.ma !== 'CAN_PIN' && j.ma !== 'CAN_THIET_LAP_PIN' && j.ma !== 'PIN_KHOA')) return res;
                return new Promise(function (resolve, reject) {
                    var url = (typeof args[0] === 'string') ? args[0] : args[0].url;
                    pendingHanhDong = 'POST ' + new URL(url, window.location.origin).pathname;
                    openDialog(j.muc || 'thuong', function (token) {
                        if (token === null) { resolve(res); return; }
                        // thử lại request với _pin_token (nghiemtrong) — GET thêm query, còn lại giữ nguyên
                        var nu = args[0];
                        if (token !== true) {
                            var sep = (nu.indexOf('?') >= 0 || (nu.url && nu.url.indexOf('?') >= 0)) ? '&' : '?';
                            if (typeof nu === 'string') nu = nu + sep + '_pin_token=' + encodeURIComponent(token);
                            else { var u = new URL(nu.url, window.location.origin); u.searchParams.set('_pin_token', token); nu = new Request(u.toString(), nu); }
                        }
                        _fetch.call(window, nu, args[1]).then(resolve, reject);
                    }, function () { resolve(res); });
                });
            });
        });
    };
})();
