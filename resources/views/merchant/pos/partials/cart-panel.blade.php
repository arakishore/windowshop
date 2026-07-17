<aside class="pos-cart-panel">
    <div class="d-flex align-items-center justify-content-between gap-2 border rounded p-2 mb-3 bg-light">
        <div class="d-flex align-items-center min-w-0">
            <span class="btn btn-primary btn-icon btn-sm rounded-pill me-2 flex-shrink-0">
                <i class="ph-user"></i>
            </span>
            <div class="fw-semibold text-truncate">Walk-in Customer</div>
        </div>
        <select class="form-select form-select-sm w-auto flex-shrink-0 js-pos-payment-method" id="pos_payment_method" aria-label="Payment method">
            <option value="cash" selected>Cash</option>
            <option value="upi">UPI</option>
            <option value="card">Card</option>
            <option value="wallet">Wallet</option>
            <option value="other">Other</option>
        </select>
    </div>

    <div class="d-flex align-items-center justify-content-between mb-1">
        <h6 class="mb-0">Cart</h6>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-light btn-sm js-pos-held-orders">
                <i class="ph-receipt me-1"></i>
                Held
                <span class="badge bg-primary ms-1 js-pos-held-count">0</span>
            </button>
            <button type="button" class="btn btn-light btn-sm js-pos-clear">
                <i class="ph-trash me-1"></i>
                Clear
            </button>
        </div>
    </div>

    <div class="pos-cart-items js-pos-cart-items">
        <div class="pos-empty-cart text-center text-muted py-5">
            <i class="ph-shopping-cart-simple ph-2x d-block mb-2"></i>
            Add products to start a sale.
        </div>
    </div>

    <div class="pos-cart-footer">
        <div class="pos-totals border-top pt-2 mt-2">
            <button
                type="button"
                class="btn btn-light btn-sm w-100 d-flex align-items-center justify-content-between mb-2"
                data-bs-toggle="collapse"
                data-bs-target="#posTotalsBreakdown"
                aria-expanded="false"
                aria-controls="posTotalsBreakdown"
            >
                <span>
                    <i class="ph-list-dashes me-1"></i>
                    Totals breakdown
                </span>
                <i class="ph-caret-down"></i>
            </button>

            <div class="collapse" id="posTotalsBreakdown">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Subtotal</span>
                    <span class="fw-semibold js-pos-subtotal">INR 0.00</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Discount</span>
                    <span class="fw-semibold">INR 0.00</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Shipping</span>
                    <span class="fw-semibold">INR 0.00</span>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted">Tax</span>
                    <span class="fw-semibold">INR 0.00</span>
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center border-top pt-2">
                <span class="fw-bold">Grand Total</span>
                <span class="fw-bold pos-grand-total js-pos-grand-total">INR 0.00</span>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-2">
                <span class="text-muted">
                    <i class="ph-timer me-1"></i>
                    Order time
                </span>
                <span class="fw-semibold js-pos-elapsed-time">00:00</span>
            </div>
        </div>

        <div class="mt-2">
            <label for="pos_cash_received" class="form-label fw-semibold js-pos-paid-label">Cash Received</label>
            <div class="input-group">
                <span class="input-group-text">INR</span>
                <input type="number" min="0" step="0.01" class="form-control js-pos-cash-received" id="pos_cash_received" placeholder="0.00">
            </div>
            <div class="d-flex justify-content-between align-items-center mt-1">
                <span class="text-muted">Change</span>
                <span class="fw-bold fs-5 js-pos-change">INR 0.00</span>
            </div>
        </div>

        <div class="pos-actions mt-2">
            <div class="pos-quick-actions">
                <input type="radio" class="btn-check" name="fulfilment_type" id="fulfilment_counter" value="counter" checked>
                <label class="btn btn-outline-primary" for="fulfilment_counter">Counter</label>

                <input type="radio" class="btn-check" name="fulfilment_type" id="fulfilment_pickup" value="pickup">
                <label class="btn btn-outline-primary" for="fulfilment_pickup">Pickup</label>

                <button type="button" class="btn btn-light js-pos-hold">
                    <i class="ph-pause-circle me-1"></i>
                    Hold
                </button>
            </div>

            <button type="button" class="btn btn-primary js-pos-complete" disabled>
                <i class="ph-check-circle me-2"></i>
                Complete Sale
            </button>
        </div>
    </div>
</aside>

<div class="modal fade" id="posHeldOrdersModal" tabindex="-1" aria-labelledby="posHeldOrdersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="posHeldOrdersModalLabel">Held orders</h5>
                    <div class="text-muted fs-sm">Resume a paused cart, or delete it to discard.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="js-pos-held-empty text-center text-muted py-4">
                    <i class="ph-receipt ph-2x d-block mb-2"></i>
                    No held orders.
                </div>
                <div class="d-grid gap-2 js-pos-held-list"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="posRecentSalesModal" tabindex="-1" aria-labelledby="posRecentSalesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="posRecentSalesModalLabel">Recent sales</h5>
                    <div class="text-muted fs-sm">20 most recent completed sales in this shop.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="js-pos-recent-loading text-center text-muted py-4">
                    <span class="spinner-border spinner-border-sm me-2"></span>
                    Loading recent sales...
                </div>
                <div class="js-pos-recent-empty text-center text-muted py-4 d-none">
                    <i class="ph-clock-counter-clockwise ph-2x d-block mb-2"></i>
                    No recent sales.
                </div>
                <div class="d-grid gap-2 js-pos-recent-list"></div>
            </div>
        </div>
    </div>
</div>
