/* PIN step-up + mã duyệt hai-người (M2).
 * - data-can-pin="m1" -> PIN thường; "a" -> PIN nghiêm trọng; "m2" -> mã duyệt.
 * - fetch 423 CAN_PIN/CAN_MA_DUYET -> mở dialog -> thử lại.
 */
(function () {
    'use strict';

    var KIEM_TRA = '/xac-nhan-pin/kiem-tra';
    var GUI_YEU_CAU = '/yeu-cau-duyet/gui';
    var dialog = null;
    var pending = null;

    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        if (m) return m.content;
        var i = document.querySelector('input[name="_token"]');
        return i ? i.value : '';
    }
    function closeDialog() { if (dialog) { dialog.remove(); dialog = null; } }

    // ---------- Dialog PIN (m1/a) ----------
    function openPinDialog(muc, onOk, onCancel) {
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

    // ---------- Dialog mã duyệt (m2) ----------
    // onXong(ma): gọi khi có mã hợp lệ để gửi lại thao tác.
    // ctx: {method, uri, fields(FormData-ish entries)} để tạo yêu cầu.
    function openMaDuyetDialog(ctx, onXong, onHuy) {
        closeDialog();
        dialog = document.createElement('div');
        dialog.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;display:flex;align-items:center;justify-content:center;';
        dialog.innerHTML =
            '<div style="background:#fff;border-radius:12px;padding:1.5rem;width:340px;max-width:92vw;box-shadow:0 8px 30px rgba(0,0,0,.25)">' +
            '<h5 style="margin:0 0 .4rem">Thao tác cần quản lý duyệt</h5>' +
            '<p style="font-size:.85rem;color:#666;margin:.25rem 0 .75rem">Gửi yêu cầu cho quản lý cấp mã, hoặc nhập mã đã được cấp.</p>' +
            '<div id="maYcTrangThai" style="font-size:.85rem;min-height:1.4em;margin-bottom:.5rem"></div>' +
            '<button id="maYcGui" class="btn btn-outline-primary btn-sm w-100 mb-2">Gửi yêu cầu cho quản lý</button>' +
            '<input id="maYcInput" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="off" placeholder="Mã duyệt 6 số" ' +
            'style="width:100%;text-align:center;font-size:1.3rem;letter-spacing:.35rem;padding:.5rem;border:1px solid #ccc;border-radius:8px">' +
            '<div id="maYcErr" style="color:#b02a37;font-size:.85rem;min-height:1.2em;margin-top:.4rem"></div>' +
            '<div style="display:flex;gap:.5rem;margin-top:.75rem">' +
            '<button id="maYcOk" class="btn btn-primary" style="flex:1">Dùng mã &amp; gửi lại</button>' +
            '<button id="maYcCancel" class="btn btn-secondary" style="flex:1">Hủy</button></div></div>';
        document.body.appendChild(dialog);
        var st = dialog.querySelector('#maYcTrangThai');
        var err = dialog.querySelector('#maYcErr');
        var input = dialog.querySelector('#maYcInput');
        var guiBtn = dialog.querySelector('#maYcGui');
        dialog.querySelector('#maYcCancel').onclick = function () { closeDialog(); if (onHuy) onHuy(); };
        dialog.querySelector('#maYcOk').onclick = dungMa;
        input.addEventListener('keydown', function (e) { if (e.key === 'Enter') dungMa(); });
        guiBtn.onclick = function () {
            guiBtn.disabled = true;
            st.textContent = 'Đang gửi yêu cầu…';
            var fd = new FormData();
            fd.append('_token', csrf());
            fd.append('_route_method', ctx.method);
            fd.append('_route_uri', ctx.uri);
            (ctx.fields || []).forEach(function (kv) { fd.append(kv[0], kv[1]); });
            fetch(GUI_YEU_CAU, { method: 'POST', headers: { 'Accept': 'application/json' }, body: fd })
                .then(function (r) { return r.json().then(function (j) { return { s: r.status, j: j }; }); })
                .then(function (res) {
                    guiBtn.disabled = false;
                    if (res.j && (res.j.yeu_cau_id || res.j.ma === 'DA_CO_YEU_CAU')) {
                        st.innerHTML = 'Đã gửi yêu cầu <b>#' + (res.j.yeu_cau_id || '') + '</b>. Nhờ quản lý cấp mã.';
                    } else {
                        st.textContent = '';
                        err.textContent = (res.j && res.j.message) || 'Không gửi được yêu cầu.';
                    }
                })
                .catch(function () { guiBtn.disabled = false; err.textContent = 'Lỗi kết nối.'; });
        };
        function dungMa() {
            var ma = input.value.trim();
            if (!/^\d{6}$/.test(ma)) { err.textContent = 'Mã gồm đúng 6 chữ số.'; return; }
            err.textContent = '';
            closeDialog();
            onXong(ma);
        }
    }

    // Thu thập field từ FormData/fetch body để gửi kèm yêu cầu.
    function fieldsFromForm(form) {
        var out = [];
        try {
            var fd = new FormData(form);
            fd.forEach(function (v, k) {
                if (k !== '_token' && k !== '_method' && k !== '_ma_duyet' && k !== '_pin_token' && typeof v === 'string') out.push([k, v]);
            });
        } catch (e) {}
        return out;
    }
    function fieldsFromFetch(args) {
        var out = [];
        try {
            var opt = args[1] || {};
            var body = opt.body;
            if (body instanceof FormData) {
                body.forEach(function (v, k) { if (typeof v === 'string' && k !== '_token') out.push([k, v]); });
            } else if (typeof body === 'string') {
                try {
                    var j = JSON.parse(body);
                    Object.keys(j || {}).forEach(function (k) { out.push([k, String(j[k])]); });
                } catch (e) {
                    new URLSearchParams(body).forEach(function (v, k) { out.push([k, v]); });
                }
            }
        } catch (e) {}
        return out;
    }
    function methodUriFromForm(form) {
        var action = form.getAttribute('action') || window.location.pathname;
        var uri = new URL(action, window.location.origin).pathname;
        var m = (form.getAttribute('method') || 'POST').toUpperCase();
        // Form Laravel PUT/DELETE giả qua _method
        try {
            var fd = new FormData(form);
            var mm = fd.get('_method');
            if (mm) m = String(mm).toUpperCase();
        } catch (e) {}
        return { method: m, uri: uri };
    }

    var pendingHanhDong = '';

    // 0) Non-JS: server redirect-back kèm cờ -> mở lại dialog rồi gửi lại form.
    document.addEventListener('DOMContentLoaded', function () {
        var flag = document.getElementById('pin-can-xin');
        if (!flag) return;
        var loai = flag.getAttribute('data-loai');
        var actionPath = flag.getAttribute('data-action') || '';
        var form = null;
        document.querySelectorAll('form').forEach(function (f) {
            if (!form) {
                try {
                    var p = new URL(f.getAttribute('action') || '', window.location.origin).pathname;
                    if (p === actionPath || p.indexOf(actionPath) >= 0) { form = f; }
                } catch (e) {}
            }
        });
        if (!form) return;
        if (loai === 'm2') {
            var mu0 = methodUriFromForm(form);
            openMaDuyetDialog({ method: mu0.method, uri: mu0.uri, fields: fieldsFromForm(form) }, function (ma) {
                var inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = '_ma_duyet'; inp.value = ma;
                form.appendChild(inp);
                form.submit();
            }, function () {});
        } else {
            var muc0 = loai === 'a' ? 'nghiemtrong' : 'thuong';
            var mu1 = methodUriFromForm(form);
            pendingHanhDong = mu1.method + ' ' + mu1.uri;
            openPinDialog(muc0, function (token) {
                if (token === null) return;
                if (token !== true) {
                    var inp = document.createElement('input');
                    inp.type = 'hidden'; inp.name = '_pin_token'; inp.value = token;
                    form.appendChild(inp);
                }
                form.submit();
            }, function () {});
        }
    });

    // 1) Chặn submit form data-can-pin
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        var mucRaw = form.getAttribute('data-can-pin');
        if (!mucRaw) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        if (mucRaw === 'm2') {
            var mu = methodUriFromForm(form);
            openMaDuyetDialog({ method: mu.method, uri: mu.uri, fields: fieldsFromForm(form) }, function (ma) {
                var inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = '_ma_duyet'; inp.value = ma;
                form.appendChild(inp);
                form.submit();
            }, function () {});
            return;
        }
        var muc = mucRaw === 'm1' ? 'thuong' : (mucRaw === 'a' ? 'nghiemtrong' : mucRaw);
        var mu2 = methodUriFromForm(form); // tôn trọng _method (PUT/DELETE giả)
        pendingHanhDong = mu2.method + ' ' + mu2.uri;
        openPinDialog(muc, function (token) {
            if (token === null) return;
            if (token !== true) {
                var inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = '_pin_token'; inp.value = token;
                form.appendChild(inp);
            }
            form.submit();
        }, function () { /* hủy */ });
    }, true);

    // 2) Bắt fetch/AJAX trả 423 -> mở dialog -> thử lại
    var _fetch = window.fetch;
    window.fetch = function () {
        var args = arguments;
        return _fetch.apply(this, args).then(function (res) {
            if (res.status !== 423) return res;
            return res.clone().json().then(function (j) {
                if (!j) return res;
                var laMaDuyet = j.ma === 'CAN_MA_DUYET';
                var laPin = j.ma === 'CAN_PIN' || j.ma === 'CAN_THIET_LAP_PIN' || j.ma === 'PIN_KHOA';
                if (!laMaDuyet && !laPin) return res;
                return new Promise(function (resolve, reject) {
                    var url = (typeof args[0] === 'string') ? args[0] : args[0].url;
                    var pathname = new URL(url, window.location.origin).pathname;
                    var method = (args[1] && args[1].method ? args[1].method : 'POST').toUpperCase();
                    pendingHanhDong = method + ' ' + pathname;
                    if (laMaDuyet) {
                        openMaDuyetDialog({ method: method, uri: pathname, fields: fieldsFromFetch(args) }, function (ma) {
                            var nu = args[0], no = args[1] || {};
                            no = Object.assign({}, no);
                            var hd = Object.assign({}, no.headers || {});
                            // thêm _ma_duyet vào body
                            var body = no.body;
                            if (typeof body === 'string') {
                                try {
                                    var jj = JSON.parse(body); jj._ma_duyet = ma; no.body = JSON.stringify(jj);
                                } catch (e) { no.body = (body ? body + '&' : '') + '_ma_duyet=' + encodeURIComponent(ma); }
                            } else if (body instanceof FormData) {
                                body.append('_ma_duyet', ma);
                            } else if (body instanceof URLSearchParams) {
                                body.append('_ma_duyet', ma);
                            } else {
                                var u = new URL(url, window.location.origin);
                                u.searchParams.set('_ma_duyet', ma);
                                nu = u.toString();
                            }
                            _fetch.call(window, nu, no).then(resolve, reject);
                        }, function () { resolve(res); });
                        return;
                    }
                    openPinDialog(j.muc || 'thuong', function (token) {
                        if (token === null) { resolve(res); return; }
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
