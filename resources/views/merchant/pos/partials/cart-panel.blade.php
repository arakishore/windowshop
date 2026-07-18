<aside class="pos-cart-panel">
    <div class="border rounded mb-2 bg-light p-2">
        <div class="d-flex align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center min-w-0">
                <span class="btn btn-primary btn-icon btn-sm rounded-pill me-2 flex-shrink-0">
                    <i class="ph-user"></i>
                </span>
                <div class="min-w-0">
                    <div class="fw-semibold text-truncate js-pos-selected-customer-name">Walk-in Customer</div>
                    <div class="text-muted fs-sm text-truncate js-pos-selected-customer-meta">No customer selected</div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-1 flex-shrink-0">
                <button type="button" class="btn btn-light btn-icon btn-sm js-pos-open-customer-modal" data-bs-toggle="modal" data-bs-target="#posCustomerModal" data-bs-popup="tooltip" title="Search or select customer" aria-label="Search or select customer">
                    <i class="ph-magnifying-glass"></i>
                </button>
                <select class="form-select form-select-sm w-auto pos-compact-control js-pos-payment-method" id="pos_payment_method" aria-label="Payment method" data-bs-popup="tooltip" title="Choose how the customer is paying">
                    <option value="cash" selected>Cash</option>
                    <option value="upi">UPI</option>
                    <option value="card">Card</option>
                    <option value="wallet">Wallet</option>
                    <option value="other">Other</option>
                </select>
            </div>
        </div>
    </div>

    <div class="d-flex align-items-center justify-content-between mb-1">
        <h6 class="mb-0">Cart</h6>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-light btn-sm js-pos-held-orders" data-bs-popup="tooltip" title="View carts parked for later">
                <i class="ph-receipt me-1"></i>
                Held
                <span class="badge bg-primary ms-1 js-pos-held-count">0</span>
            </button>
            <button type="button" class="btn btn-light btn-sm js-pos-clear" data-bs-popup="tooltip" title="Clear all items from this cart">
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
                data-bs-popup="tooltip"
                title="Show subtotal, discounts, shipping, and tax"
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
                    <span class="fw-semibold text-danger js-pos-item-discount">INR 0.00</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Order Discount</span>
                    <span class="fw-semibold text-danger js-pos-order-discount-total">INR 0.00</span>
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
            <div class="d-flex justify-content-between align-items-center border-top pt-1">
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
            <div class="input-group input-group-sm">
                <span class="input-group-text">INR</span>
                <input type="number" min="0" step="0.01" class="form-control pos-compact-control js-pos-cash-received" id="pos_cash_received" placeholder="0.00" data-bs-popup="tooltip" title="Enter the amount received from customer">
            </div>
            <div class="d-flex justify-content-between align-items-center mt-1">
                <span class="text-muted">Change</span>
                <span class="fw-bold fs-5 js-pos-change">INR 0.00</span>
            </div>
            <button type="button" class="btn btn-light btn-sm w-100 mt-2 js-pos-order-discount" data-bs-popup="tooltip" title="Apply discount to the whole order">
                <i class="ph-tag me-1"></i>
                Order Discount
                <span class="badge bg-primary ms-1 d-none js-pos-order-discount-badge"></span>
            </button>
        </div>

        <div class="pos-actions mt-2">
            <div class="pos-quick-actions">
                <input type="radio" class="btn-check" name="fulfilment_type" id="fulfilment_counter" value="counter" checked>
                <label class="btn btn-outline-primary btn-sm" for="fulfilment_counter" data-bs-popup="tooltip" title="Counter sale with no address required">Counter</label>

                <input type="radio" class="btn-check" name="fulfilment_type" id="fulfilment_pickup" value="pickup">
                <label class="btn btn-outline-primary btn-sm" for="fulfilment_pickup" data-bs-popup="tooltip" title="Customer will pick up the order later">Pickup</label>

                <input type="radio" class="btn-check" name="fulfilment_type" id="fulfilment_delivery" value="delivery">
                <label class="btn btn-outline-primary btn-sm" for="fulfilment_delivery" data-bs-popup="tooltip" title="Delivery sale requires a customer and address">Delivery</label>

                <button type="button" class="btn btn-light btn-sm js-pos-hold" data-bs-popup="tooltip" title="Park this cart and resume it later">
                    <i class="ph-pause-circle me-1"></i>
                    Hold
                </button>
            </div>

            <div class="border rounded p-2 mt-2 d-none js-pos-delivery-panel">
                <div class="fw-semibold mb-1">Delivery Address</div>
                <div class="text-muted fs-sm js-pos-shipping-address-summary">Delivery requires a selected customer and address.</div>
                <button type="button" class="btn btn-light btn-sm mt-2 js-pos-open-customer-modal" data-bs-toggle="modal" data-bs-target="#posCustomerModal" data-bs-popup="tooltip" title="Choose customer and delivery address">
                    <i class="ph-map-pin me-1"></i>
                    Choose Customer / Address
                </button>
            </div>

            <button type="button" class="btn btn-primary js-pos-complete" disabled data-bs-popup="tooltip" title="Create the order and print receipt">
                <i class="ph-check-circle me-2"></i>
                Complete Sale
            </button>
        </div>
    </div>
</aside>

<div class="modal fade" id="posCustomerModal" tabindex="-1" aria-labelledby="posCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="posCustomerModalLabel">Customer & Delivery Address</h5>
                    <div class="text-muted fs-sm">Search by mobile, name, email, or customer code.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" data-bs-popup="tooltip" title="Close customer window" aria-label="Close customer window"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-lg-5">
                        <label for="pos_customer_search" class="form-label fw-semibold">Search Customer</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="ph-magnifying-glass"></i></span>
                            <input id="pos_customer_search" type="search" class="form-control js-pos-customer-search" placeholder="e.g. 9422945125" autocomplete="off">
                            <button type="button" class="btn btn-light js-pos-clear-customer" data-bs-popup="tooltip" title="Remove the selected customer">
                                <i class="ph-x"></i>
                            </button>
                        </div>
                        <div class="list-group mt-2 js-pos-customer-results">
                            <div class="list-group-item text-muted">Start typing to search customers.</div>
                        </div>
                    </div>

                    <div class="col-lg-7">
                        <div class="border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between gap-2 align-items-start">
                                <div class="min-w-0">
                                    <div class="text-muted fs-sm">Selected Customer</div>
                                    <div class="fw-semibold text-truncate js-pos-selected-customer-name">Walk-in Customer</div>
                                    <div class="text-muted fs-sm text-truncate js-pos-selected-customer-meta">No customer selected</div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <label for="pos_shipping_address_id" class="form-label fw-semibold">Shipping Address</label>
                                <select id="pos_shipping_address_id" class="form-select js-pos-shipping-address">
                                    <option value="">Select customer first</option>
                                </select>
                                <div class="text-muted fs-sm mt-1 js-pos-shipping-address-summary">Delivery requires a selected customer and address.</div>
                            </div>
                        </div>

                        <div class="border rounded p-3">
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                                <div>
                                    <div class="fw-semibold">Add Shipping Address</div>
                                    <div class="text-muted fs-sm">Optional unless the sale is for delivery.</div>
                                </div>
                                <button type="button" class="btn btn-light btn-sm js-pos-toggle-address-form" disabled data-bs-popup="tooltip" title="Add a new address for the selected customer">
                                    <i class="ph-plus me-1"></i>
                                    Add Address
                                </button>
                            </div>

                            <div class="d-none js-pos-address-form">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <input type="text" class="form-control js-pos-address-label" placeholder="Label e.g. Home">
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" class="form-control js-pos-address-recipient" placeholder="Recipient name">
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" class="form-control js-pos-address-country-code" value="+91" maxlength="10">
                                    </div>
                                    <div class="col-md-9">
                                        <input type="text" class="form-control js-pos-address-mobile" placeholder="Mobile">
                                    </div>
                                    <div class="col-12">
                                        <input type="text" class="form-control js-pos-address-line1" placeholder="Address line 1">
                                    </div>
                                    <div class="col-12">
                                        <input type="text" class="form-control js-pos-address-line2" placeholder="Address line 2">
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" class="form-control js-pos-address-landmark" placeholder="Landmark">
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" class="form-control js-pos-address-postal" placeholder="Postal code">
                                    </div>
                                    <div class="col-12 d-flex gap-2">
                                        <button type="button" class="btn btn-primary js-pos-save-address" data-bs-popup="tooltip" title="Save this address to the customer profile">
                                            <i class="ph-floppy-disk me-1"></i>
                                            Save Address
                                        </button>
                                        <button type="button" class="btn btn-light js-pos-cancel-address" data-bs-popup="tooltip" title="Close address form without saving">Cancel</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal" data-bs-popup="tooltip" title="Close customer and address window">Done</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="posLineDiscountModal" tabindex="-1" aria-labelledby="posLineDiscountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="posLineDiscountModalLabel">Line Discount</h5>
                    <div class="text-muted fs-sm js-pos-line-discount-product"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" data-bs-popup="tooltip" title="Close line discount window" aria-label="Close line discount window"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs nav-tabs-underline mb-3" role="tablist">
                    <li class="nav-item flex-fill" role="presentation">
                        <button type="button" class="nav-link active w-100 js-pos-line-discount-mode" data-mode="percent" data-bs-popup="tooltip" title="Give this item a percentage discount">Percent</button>
                    </li>
                    <li class="nav-item flex-fill" role="presentation">
                        <button type="button" class="nav-link w-100 js-pos-line-discount-mode" data-mode="amount" data-bs-popup="tooltip" title="Give this item a fixed amount discount">Amount</button>
                    </li>
                </ul>
                <label for="pos_line_discount_value" class="form-label fw-semibold js-pos-line-discount-label">Discount %</label>
                <input id="pos_line_discount_value" type="number" min="0" step="0.01" class="form-control js-pos-line-discount-value" placeholder="0.00">
                <div class="invalid-feedback d-block js-pos-line-discount-error"></div>

                <div class="border rounded p-3 mt-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Original Line Total</span>
                        <span class="fw-semibold js-pos-line-preview-original">INR 0.00</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Discount</span>
                        <span class="fw-semibold text-danger js-pos-line-preview-discount">INR 0.00</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="fw-semibold">New Line Total</span>
                        <span class="fw-bold js-pos-line-preview-total">INR 0.00</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light js-pos-line-discount-remove" disabled data-bs-popup="tooltip" title="Remove discount from this item">Remove Discount</button>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal" data-bs-popup="tooltip" title="Close without changing item discount">Cancel</button>
                <button type="button" class="btn btn-primary js-pos-line-discount-apply" data-bs-popup="tooltip" title="Apply this discount to the item">
                    <i class="ph-check me-1"></i>
                    Apply
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="posOrderDiscountModal" tabindex="-1" aria-labelledby="posOrderDiscountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="posOrderDiscountModalLabel">Order Discount</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" data-bs-popup="tooltip" title="Close order discount window" aria-label="Close order discount window"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs nav-tabs-underline mb-3" role="tablist">
                    <li class="nav-item flex-fill" role="presentation">
                        <button type="button" class="nav-link active w-100 js-pos-order-discount-mode" data-mode="percent" data-bs-popup="tooltip" title="Give the order a percentage discount">Percent</button>
                    </li>
                    <li class="nav-item flex-fill" role="presentation">
                        <button type="button" class="nav-link w-100 js-pos-order-discount-mode" data-mode="amount" data-bs-popup="tooltip" title="Give the order a fixed amount discount">Amount</button>
                    </li>
                </ul>
                <div class="row g-2 mb-3 js-pos-order-quick-buttons">
                    @foreach([5, 10, 15, 20] as $percent)
                        <div class="col-3">
                            <button type="button" class="btn btn-light w-100 js-pos-order-quick" data-value="{{ $percent }}" data-bs-popup="tooltip" title="Use {{ $percent }}% order discount">{{ $percent }}%</button>
                        </div>
                    @endforeach
                </div>
                <label for="pos_order_discount_value" class="form-label fw-semibold js-pos-order-discount-label">Discount Value</label>
                <input id="pos_order_discount_value" type="number" min="0" step="0.01" class="form-control js-pos-order-discount-value" placeholder="0.00">
                <div class="invalid-feedback d-block js-pos-order-discount-error"></div>

                <div class="border rounded p-3 mt-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal</span>
                        <span class="fw-semibold js-pos-order-preview-subtotal">INR 0.00</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Discount</span>
                        <span class="fw-semibold text-danger js-pos-order-preview-discount">INR 0.00</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="fw-semibold">Grand Total</span>
                        <span class="fw-bold js-pos-order-preview-total">INR 0.00</span>
                    </div>
                </div>

                <div class="mt-3">
                    <button type="button" class="btn btn-light btn-sm" data-bs-toggle="collapse" data-bs-target="#posOrderDiscountAdvanced" data-bs-popup="tooltip" title="Add reason or note for this discount" aria-expanded="false" aria-controls="posOrderDiscountAdvanced">
                        Advanced Options
                    </button>
                    <div class="collapse mt-3" id="posOrderDiscountAdvanced">
                        <label for="pos_order_discount_reason" class="form-label">Reason</label>
                        <select id="pos_order_discount_reason" class="form-select js-pos-order-discount-reason">
                            <option value="">No reason</option>
                            <option value="Customer Loyalty">Customer Loyalty</option>
                            <option value="Festival Offer">Festival Offer</option>
                            <option value="Price Match">Price Match</option>
                            <option value="Staff Discount">Staff Discount</option>
                            <option value="Damaged Packaging">Damaged Packaging</option>
                            <option value="Other">Other</option>
                        </select>
                        <label for="pos_order_discount_note" class="form-label mt-3">Note</label>
                        <textarea id="pos_order_discount_note" class="form-control js-pos-order-discount-note" rows="2" maxlength="500" placeholder="Optional note"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light js-pos-order-discount-remove" disabled data-bs-popup="tooltip" title="Remove discount from this order">Remove Discount</button>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal" data-bs-popup="tooltip" title="Close without changing order discount">Cancel</button>
                <button type="button" class="btn btn-primary js-pos-order-discount-apply" data-bs-popup="tooltip" title="Apply discount to the order">
                    <i class="ph-check me-1"></i>
                    Apply
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="posHeldOrdersModal" tabindex="-1" aria-labelledby="posHeldOrdersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="posHeldOrdersModalLabel">Held orders</h5>
                    <div class="text-muted fs-sm">Resume a paused cart, or delete it to discard.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" data-bs-popup="tooltip" title="Close held orders" aria-label="Close held orders"></button>
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
                <button type="button" class="btn-close" data-bs-dismiss="modal" data-bs-popup="tooltip" title="Close recent sales" aria-label="Close recent sales"></button>
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
