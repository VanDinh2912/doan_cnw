<!-- Quick View Modal (Bootstrap 5 modal integrated with Storefront Design Tokens) -->
<div class="modal fade" id="sfQuickViewModal" tabindex="-1" aria-labelledby="sfQuickViewTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content sf-qv-modal">
            <div class="modal-header border-0 pb-0">
                <span class="sf-qv-badge badge bg-light text-dark px-3 py-2 border" id="qvCategory">Điện thoại</span>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="row g-4 align-items-center">
                    <div class="col-md-5 text-center">
                        <div class="sf-qv-image-box p-3 border rounded-3 bg-white">
                            <img id="qvImage" src="{{ asset('img/no-image.svg') }}" alt="Sản phẩm" class="img-fluid sf-qv-img" style="max-height: 280px; object-fit: contain;">
                        </div>
                    </div>
                    <div class="col-md-7">
                        <h4 class="sf-qv-title fw-bold mb-2" id="qvTitle">Tên sản phẩm</h4>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="sf-stock-badge is-in" id="qvStock">Còn hàng</span>
                            <span class="text-muted small">SKU: <strong id="qvSku">--</strong></span>
                        </div>
                        <div class="sf-qv-price-box p-3 rounded-3 mb-3" style="background: var(--sf-accent-soft, #fff7ed); border: 1px solid var(--sf-accent-border, #fed7aa);">
                            <span class="text-muted small d-block">Giá niêm yết chính hãng:</span>
                            <div class="h3 fw-bolder mb-0" id="qvPrice" style="color: var(--sf-accent, #ea580c);">0 ₫</div>
                        </div>

                        <div class="sf-qv-perks mb-3">
                            <ul class="list-unstyled small mb-0 text-secondary">
                                <li class="mb-1"><i class="fa-solid fa-shield-halved text-success me-2"></i>Bảo hành chính hãng 12 tháng tại các trung tâm uỷ quyền.</li>
                                <li class="mb-1"><i class="fa-solid fa-rotate-left text-primary me-2"></i>1 đổi 1 trong 30 ngày nếu phát sinh lỗi từ nhà sản xuất.</li>
                                <li class="mb-1"><i class="fa-solid fa-box text-info me-2"></i>Sản phẩm mới 100%, nguyên seal, đầy đủ phụ kiện theo nhà sản xuất.</li>
                            </ul>
                        </div>

                        <div class="alert alert-light border small text-muted mb-0">
                            <i class="fa-solid fa-circle-info text-primary me-1"></i>
                            Quý khách có thể đến trực tiếp hệ thống cửa hàng PhoneShop để trải nghiệm máy và nhận tư vấn chi tiết từ nhân viên.
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>
