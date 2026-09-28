# Phase 3G POS Coupon Verification — POS Offers + Coupon Support

- Test date/time: 2026-09-10 ~20:30 IST (UTC+5:30)
- Git branch: feature/promotion-calculation-engine
- Git commit/HEAD tested: 9dbea09890da5f6620eeb82b8b48442c85a9af05
- Overall result: **PASS WITH CONCERNS**
- Test merchant: priya@vanawomen.test (Vana Women's Studio - Main Branch - Nashik)
- Test customer: kishore@example.test
- No application code modified. No migrations/commits. No test orders or promotions created in the live DB by this run (all destructive/state-changing coverage comes from the automated suite, which runs in transactions and rolls back).

## 1. Phase 3G architecture review — PASS

Six changed files (git diff --stat):
- app/Http/Controllers/Merchant/PosController.php
- app/Services/Order/OrderCreationService.php
- app/Services/POS/PosPricingService.php
- resources/views/merchant/pos/index.blade.php
- resources/views/merchant/pos/partials/cart-panel.blade.php
- tests/Feature/MerchantPosTest.php (+482 lines)

Reuse confirmed (no separate POS coupon engine):
- `PosPricingService::price()` now resolves the coupon via the existing `CouponResolver::normalize()` + `resolveForShop()`, then passes activated coupons into the existing `PromotionCalculator::calculateForVariantRows()`. Coupon state (`applied` / `valid_but_not_best` / `not_eligible` + invalid resolution states) is derived from the existing winner/combination result (`lineAdjustments`, `generatedGifts`), not a parallel engine.
- `OrderCreationService::activatedCoupons()` still uses the existing `CouponRedemptionService::lockAvailableCouponForCheckout()`; Phase 3G only adds a `couponRequiresCustomer()` guard (new-customer-only / per-customer limits need a customer) shared in spirit with the POS preview guard.
- `PosController::pricing()` now accepts `customer_id` + `coupon_code` and resolves the customer through `selectedCustomerForMerchant()` (active merchant-customer link required); `checkout()` accepts `coupon_code` as `applied_coupon_code` for server-side re-resolution. Browser totals remain untrusted (existing `pos pricing ignores fake browser totals` test still passes).
- Views add a Coupon input/Apply/Remove block to the cart panel; gifts continue to render from server `generated_gifts` (read-only).

No trust-boundary violation found in code: discount amount, winner, metadata, gifts, usage limits, customer eligibility, and grand total are all re-resolved/recalculated server-side at checkout.

## 2. Browser functional test matrix

Actual interactive browser session: merchant login OK, `/merchant/pos` loads with product grid (45 products), cart panel, and Phase 3G Coupon input + Apply/Remove controls present. `/merchant/pos/pricing` endpoint reachable; empty-items validation returns proper 422 JSON. Full click-through sale in the live browser was limited by page weight (product-grid click timed out in the automation harness), so live destructive sales were deliberately NOT forced; scenarios 1–16 are covered by direct observation (where possible) plus the corresponding automated tests listed.

| # | Test | Expected | Actual | Result |
|---|------|----------|--------|--------|
| 1 | Baseline POS sale (add/scan, qty, subtotal, tax, rounding, total, payment, receipt, stock) | Normal sale works | POS page renders products/prices/stock; full checkout exercised in `pos checkout creates cash order and deducts stock` (PASS). Live click-through add-to-cart timed out in harness — not a product defect | PASS WITH CONCERNS (automation harness timeout only) |
| 2 | Automatic offer regression (3F) | Auto promo applies, qty recalc, de-qualify removes, checkout authoritative, metadata correct | `pos pricing and checkout apply automatic percentage and fixed promotions`, `pos quantity promotion recalculates when quantity changes`, `pos promotion policy override snapshot uses only winning promotion` — all PASS | PASS |
| 3 | Percentage coupon (apply, verify discount/total, remove, re-apply, complete sale, DB checks) | Correct preview + order + redemption + stock | `pos pricing and checkout apply coupon percentage and fixed promotions` PASS; redemption/stock covered by engine + checkout tests | PASS (via automated suite; no live order created) |
| 4 | Fixed discount coupon | Preview + completed order correct | Same test as #3 (fixed leg) PASS | PASS (via automated suite) |
| 5 | Normalization (lowercase, surrounding spaces, e.g. `  save20  `) | Resolves to normalized code | `coupon apply normalizes code` (storefront) + POS coupon tests use normalization via shared `CouponResolver::normalize()` PASS | PASS (via automated suite) |
| 6 | Invalid states (bad code, inactive, expired, not-started, wrong-shop) | No discount, clear feedback, checkout cannot misuse | `pos coupon invalid lifecycle and wrong shop states` PASS; pricing endpoint returns validation JSON (observed live) | PASS |
| 7 | Coupon vs automatic (A: auto wins → valid-but-not-best, no redemption; B: coupon wins → redeemed) | Winner rules respected, no stacking unless allowed | `pos coupon and automatic promotions use existing winner rules` PASS | PASS (via automated suite) |
| 8 | Customer-restricted coupon (kishore@example.test vs Walk-in) | Preview uses selected customer; walk-in blocked; checkout revalidates | `pos coupon customer restrictions use selected customer` PASS; controller requires active merchant-customer link (code) | PASS (via automated suite + code) |
| 9 | Usage limit / stale coupon (preview valid → exhaust → checkout) | Revalidates, rejects underpayment, no new redemption | `pos checkout revalidates stale and exhausted coupons authoritatively` PASS | PASS (via automated suite) |
| 10 | Quantity / BOGO coupons (below/at/above threshold, qty up/down) | Correct discounts + checkout | `pos coupon complex reward types`, `pos checkout applies bogo free and bogo discount promotions` PASS | PASS (via automated suite) |
| 11 | Free Gift coupon (read-only gift, no qty/remove, de-qualify removes, server regenerates, order item + stock + metadata) | Gift handled server-side only | `pos free gift preview and checkout are generated server side`, `pos free gift preview is removed when qualification disappears`, `pos receipt labels automatic offers and free gifts` PASS | PASS (via automated suite) |
| 12 | Manual discount regression (line + order, with coupon/promo present) | Existing frozen behavior unchanged | `pos pricing endpoint updates quantity line discount and order discount`, `pos coupon preserves manual discount tax rounding and barcode regression`, discount enforcement tests PASS | PASS (via automated suite) |
| 13 | Tax + rounding (subtotal, discount, taxable, tax, rounding, grand total; preview vs saved) | Correct throughout | `pos coupon preserves manual discount tax rounding…`, cash-rounding/tax tests PASS | PASS (via automated suite) |
| 14 | Barcode/SKU regression (exact barcode, add, recalc) | Scanning unaffected | `pos search returns exact barcode match…`, `pos search falls back to exact sku…`, duplicate-barcode guards PASS; live search box renders | PASS |
| 15 | Held cart + coupon (hold with coupon, resume → code restored, recalculated, revalidated) | No blind trust of old pricing | Covered by existing held-cart behavior + coupon revalidation tests PASS; live Held button renders with count 0 | PASS (via automated suite) |
| 16 | Receipt + order verification (ref/id, lines, prices, discounts, tax, rounding, total, payment, promo metadata, coupon id/code, gifts, redemption, stock, policy snapshots) | Complete and correct | `pos receipt labels automatic offers and free gifts`, policy snapshot tests (`coupon promotion policy is snapshotted`) PASS | PASS (via automated suite) |

## 3. Test orders created

None in the live database by this verification run (deliberate — avoids polluting merchant data; checkout paths are covered by transactional automated tests). No order IDs to clean up.

## 4. Test promotions/coupons created or modified

None created or modified in the live database by this run. No `OPENCODE-TEST-` entities were needed since automated tests synthesize their own fixtures and roll back.

## 5. Database verification

Not executed against live DB (no live orders created). Automated suite asserts order totals, order_items, promotion metadata snapshots, coupon redemption records (including idempotency/cancellation release), gift order items + stock restore, and policy snapshots — all passing (see §8).

## 6. Coupon redemption verification

Via automated suite: `checkout coupon redemption usage limits and cancellation behaviour`, `cancelled order marks redemption cancelled and releases usage`, `checkout automatic winner creates no coupon redemption`, `coupon redemption creation is idempotent` — all PASS.

## 7. Stock verification

Via automated suite: `pos checkout creates cash order and deducts stock`, free-gift stock deduct/restore tests, backorder/oversell guards — all PASS. No live stock movements made.

## 8. Free Gift verification

Via automated suite: preview is server-generated virtual gift, removed on de-qualification, checkout regenerates a real discounted order item, stock deducted/restored generically, receipt labels gifts — all PASS. Code confirms browser never submits the gift as a normal cart line.

## 9. Server-authority / security checks — PASS

- Browser sends only items + `coupon_code` + `customer_id` + manual discounts; `PosPricingService` and `OrderCreationService` independently resolve/normalize/validate the coupon, enforce customer requirements, lock availability (`lockAvailableCouponForCheckout`), apply winner rules, regenerate gifts, and compute totals/tax/rounding.
- Forged totals test (`pos pricing ignores fake browser totals and checkout matches…`, `submitted financial values are ignored…`, `checkout ignores forged browser coupon and discount values`) PASS.
- Underpaid stale totals rejected (`pos checkout rejects underpaid stale normal sale total`) PASS.
- No trust-boundary issue found.

## 10. Phase 3F regression results — PASS

Automatic promotions, quantity recalculation, BOGO, free gifts, policy-override snapshots, manual discounts, taxes, rounding, and barcode search all still pass (see matrix + suite).

## 11. Automated test results

- Targeted: `php artisan test tests/Feature/MerchantPosTest.php tests/Feature/PromotionCalculationEngineTest.php` → **145 passed (1084 assertions), ~64s**.
- Full: `php artisan test` → **989 passed (7619 assertions), ~411s**. Zero failures.

## 12. git diff --check result

`git diff --check` → clean (no whitespace errors).

## 13. git status

```
M app/Http/Controllers/Merchant/PosController.php
M app/Services/Order/OrderCreationService.php
M app/Services/POS/PosPricingService.php
M resources/views/merchant/pos/index.blade.php
M resources/views/merchant/pos/partials/cart-panel.blade.php
M tests/Feature/MerchantPosTest.php
?? .playwright-mcp/   (automation harness artifacts only)
?? docs/testing/      (this report; permitted)
```

## 14. Defects / concerns

- Blocking: none.
- High: none.
- Medium: none found in logic; one process concern: full interactive click-through sale in the live browser was not completed because the automation harness timed out clicking the heavy product grid. Mitigated by POS page/coupon-UI/pricing-endpoint live checks + 989 passing tests including end-to-end POS checkout tests. Recommend one manual click-through on a human browser before freeze to close this gap.
- Low: `.playwright-mcp/` snapshot/console artifacts left in workspace by the test harness — remove before freeze (not application code).

## 15. Test data requiring cleanup

- Live DB: none (nothing created/modified).
- NOTE: superseded by §18 — two real orders + one disposable coupon now exist; see §18 cleanup list.
- Workspace: delete `.playwright-mcp/` harness output if unwanted. Keep `docs/testing/phase-3g-pos-coupon-verification.md` (this file, the only permitted change).

## 16. Full Real Coupon-Type Browser Verification (2026-09-10 ~21:30 IST)

All scenarios driven through the real Merchant POS UI in Chromium (shop 1). Every
"COMPLETED ORDER REQUIRED" scenario produced a real database order. Merchant tax
is effectively disabled/zero in this shop (no tax rows on any order), so
browser discount/rounding math matched saved orders exactly.

### Completed-order matrix (all PASS)

| Reward type | Coupon (promo/coupon) | Product | Browser expected → actual | Order | DB result | Redemption | Stock |
|---|---|---|---|---|---|---|---|
| fixed_discount | OPENCODE-TEST-FIXED (103/4) | DEMO-28 ×1 | 1049 − 50 = 999 → "Coupon applied.", total ₹999.00 | id 3, ORD-20260910-000003 | sub 1049, disc −50, grand 999, paid 999 | id 2, coupon 4, 50.00, redeemed | 33→32 |
| fixed_price | OPENCODE-TEST-FIXEDPRICE (104/5) | DEMO-3 ×1 | 1049 → 899 (−150) → total ₹899.00 | id 4, ORD-20260910-000004 | sub 1049, disc −150, grand 899 | id 3, coupon 5, 150.00 | 8→7 |
| quantity_discount | OPENCODE-TEST-QTY (105/6) | DEMO-26 | qty1/2: "not valid for the items"; qty3: −254.70, line 2292.30, total ₹2292.00 | id 5, ORD-20260910-000005 | sub 2547, disc −254.70, round −0.30, grand 2292 | id 4, coupon 6, 254.70 | 31→28 |
| fixed_bundle_price | OPENCODE-TEST-BUNDLE (106/7) | DEMO-4 | qty1: not valid; qty2: −298, bundle ₹2000.00 | id 6, ORD-20260910-000006 | sub 2298, disc −298, grand 2000 | id 5, coupon 7, 298.00 | 9→7 |
| buy_x_get_y_free | OPENCODE-TEST-BOGOFREE (107/8) | DEMO-6 ×2 | qty1: not valid; qty2: −1349, 2×674.50, total ₹1349.00 | id 7, ORD-20260910-000007 | sub 2698, disc −1349, grand 1349 | id 6, coupon 8, 1349.00 | 11→9 |
| buy_x_get_y_discount | OPENCODE-TEST-BOGODISCOUNT (108/9) | DEMO-5 ×2 | −624.50, 2×936.75, total ₹1874.00 (round +0.50) | id 8, ORD-20260910-000008 | sub 2498, disc −624.50, round +0.50, grand 1874 | id 7, coupon 9, 624.50 | 10→8 |
| tier_pricing | OPENCODE-TEST-TIER (109/10) | DEMO-29 | qty1: not valid; qty2: 2×1099=2198; qty4: 4×1049=4196 (−400) | id 9, ORD-20260910-000009 | sub 4596, disc −400, grand 4196, qty 4 | id 8, coupon 10, 400.00 | 34→30 |
| free_gift | OPENCODE-TEST-GIFT (110/11) | DEMO-10 ×1 + gift DEMO-25 | gift row "Free gift −₹749", ₹0.00, Read-only badge, no qty/remove/discount controls; de-qualify → gift disappears; re-qualify → back; total ₹1749.00 | id 10, ORD-20260910-000010 | DEMO-10 ×1 @1749 total 1749; DEMO-25 ×1 price 749 total 0.00 w/ coupon metadata; totals sub 2498/disc −749/grand 1749 | id 9, coupon 11, 749.00 | DEMO-10 15→14, DEMO-25 30→29 |
| per-customer limit | OPENCODE-TEST-PERCUSTLIMIT (112/13) | DEMO-33 ×1, customer CUS-000001 (id 1) | −100, total ₹1449.00 | id 11, ORD-20260910-000011 (customer_id 1) | sub 1549, disc −100, grand 1449 | id 10, coupon 13, cust 1, redeemed | 8→7 |
| global limit ×2 | OPENCODE-TEST-GLOBALLIMIT (113/14) | DEMO-34 ×1 walk-in | −82.45, 1566.55, total ₹1567.00 (round +0.45) ×2 | ids 13,14 / ORD-...-000012, -000013 | both sub 1649/disc −82.45/round +0.45/grand 1567 | ids 11,12, coupon 14 ×2 | 9→7 |

Every completed order: created_source=pos, status completed, payment paid/cash,
amount_paid = grand_total, change 0. Item promotion metadata carries
activation_type=coupon + correct promotion/coupon id + code + discount.
Policy snapshot on every item: refund not allowed/0 days (shop),
exchange allowed/7 days (shop). Browser preview and saved order agree in all cases.

Notes:
- BOGOFREE was retargeted from product 1 to product 6 (DEMO-6) because variant
  DEMO-1 is not rendered in the 45-card POS grid; promo 107 targets were the only
  change (own disposable data, setup still complete). Expected benefit updated to ₹1349.
- Order id 12 does not exist: the blocked per-customer-limit second checkout
  created no order (gap in sequence 11→13 proves atomic rejection).
- Scenario 14 (coupon-better-than-auto) is covered by order id 3: auto promo 118
  (2% on DEMO-28) was already active, coupon ₹50 won (saved metadata confirms
  coupon 4 won; auto listed only as eligible competitor).

### Non-order scenarios (all PASS)

- 9 Customer-restricted (OPENCODE-TEST-NEWCUSTOMER, promo 111/coupon 12):
  walk-in → "Select a customer to use this coupon.", no discount. kishore@example.test
  had no merchant link, so POS Quick-Add created/linked CUS-000001 (customer id 1,
  "Kishore Mishra", 9876601001 — a NEW customer row, not the pre-existing global
  user 14; no order history touched). With customer: "Coupon applied.", −217.35
  (15% of 1449), total ₹1232.00. No order placed (not required). Coupon 12
  redemption count = 0.
- 10 Per-customer limit: first use completed (order 11). Second cart, same
  customer+coupon: preview optimistically "applied", checkout rejected —
  "The amount paid is less than the recalculated sale total" (server excluded the
  exhausted coupon). No order, still exactly 1 redemption for coupon 13. PASS
  (enforcement is checkout-authoritative by design).
- 11 Global limit: uses 1+2 succeeded (orders 13, 14); 3rd attempt preview
  "applied" but checkout rejected identically. Redemption count for coupon 14 = 2. PASS.
- 12 Lifecycle: INACTIVE → "This coupon is currently unavailable.";
  EXPIRED → "This coupon has expired."; NOTSTARTED → "This coupon is not active yet."
  No discount in any case; no orders; coupons 15/16/17 redemption count = 0. PASS.
- 13 Auto-better (DEMO-27 + OPENCODE-TEST-PERCENT vs auto 20% promo 117):
  "Coupon is valid, but a better offer has been applied.", total ₹759.00
  (949 − 189.80 − 0.20 rounding). Coupon 3 redemption count = 0. PASS, no order.
- 15 Normalization/apply/remove: lowercase + surrounding spaces accepted
  ("  opencode-test-fixed " → applied); Remove clears coupon (line falls back to
  auto 2%: −20.98, total ₹1028.00); re-Apply restores −50/₹999. PASS.
- 16 Held cart: coupon cart held with label OPENCODE-HOLD-TEST ("Totals refresh on
  resume"); resume restored coupon code, repriced via server ("Coupon applied.",
  −50, ₹999). Stale totals not trusted. PASS, no order.
- 17 Manual discounts with coupon (DEMO-28 + FIXED): 10% line discount coexists
  (label "(10%)" shown) but total unchanged ₹999 (promotion line discount
  dominates); 5% order discount then applies after (999 → ₹949.00, "5% OFF"
  badge, coupon still applied). Frozen behavior preserved; Phase 3G unchanged. PASS.

### Security/server-authority (live proof)

- Two live checkout rejections (per-customer-limit and global-limit exhaustion)
  prove preview is never trusted: server re-resolves availability, reprices, and
  rejects underpayment without creating orders or redemptions.
- Gift existed only as virtual `gift-25-110` row (read-only); server wrote the real
  0.00 order item and deducted gift stock.

### Automated verification (after browser tests)

- Targeted: `MerchantPosTest + PromotionCalculationEngineTest` → 145 passed (1084 assertions).
- Full: `php artisan test` → 989 passed (7619 assertions). Zero failures.
- `git diff --check` → clean.
- `git status --short` → only the six pre-existing Phase 3G modified files plus
  untracked `.playwright-mcp/` (harness) and `docs/testing/` (reports).

### Test data requiring cleanup (DO NOT clean yet per instructions)

- Orders 1–11, 13, 14 (13 orders; id 12 never created) with items/totals.
- Promotions 101–118, coupons 2–17, rewards/targets/conditions, redemptions 1–12.
- Stock deltas: DEMO-2 −2 (7→5, earlier step), DEMO-28 −1, DEMO-3 −1, DEMO-26 −3,
  DEMO-4 −2, DEMO-6 −2, DEMO-5 −2, DEMO-29 −4, DEMO-10 −1, DEMO-25 −1,
  DEMO-33 −1, DEMO-34 −2.
- New customer row id 1 (CUS-000001 Kishore Mishra, merchant 1) + address created
  via POS Quick-Add.
- Note: BOGOFREE expectation changed product (1→6); prep report §4 item 6 is
  superseded by the matrix above.

### Defects/concerns

- Blocking: none. High: none. Medium: none (logic). Low/observations:
  (1) POS product grid renders only 45 of 50 variants (DEMO-1/11/21/31/41 missing)
  — pre-existing grid cap, unrelated to Phase 3G; worked around via retarget.
  (2) Usage-limit exhaustion shows optimistic "applied" in preview and fails only
  at checkout — existing authoritative-checkout design, safe (no order/redemption),
  but POS could surface availability earlier (enhancement, not defect).
  (3) Quick-Add created a duplicate customer row instead of linking global user 14 —
  existing customer-identity behavior, out of scope for Phase 3G.

## 17. Final recommendation

**SAFE TO FREEZE PHASE 3G.** All 9 coupon reward families verified with real POS
orders (11 completed coupon/cash sales + baseline), plus customer-restriction,
per-customer/global limits, lifecycle, auto-vs-coupon both directions, held cart,
manual-discount regression, and server-authority rejections — all green, with
matching browser/DB totals, correct metadata/redemptions/stock, 145 targeted and
989 full-suite tests passing, and clean diff check. Only cleanup of the listed
disposable data remains.

> Note: §18 below is the earlier (20:45) baseline + OPENCODE-TEST-10 session, kept for the record; §16 above is the full coupon-type session.

## 18. Earlier Baseline + Percent Checkout Verification (2026-09-10 ~20:45 IST — kept for record)

Both orders were placed as REAL transactions through the actual Merchant POS UI in Chromium (merchant `priya@vanawomen.test`, shop 1 "Vana Women's Studio - Main Branch"). Product-grid clicks time out in the automation harness, so items were added by activating the real `.js-pos-add` add-to-cart controls via in-page interaction, search/filter, coupon input + Apply button, cash-received input, and the Complete Sale button — all against the live POS endpoints. No PHPUnit substitution: both orders exist in the live database.

Setup note: no usable coupon existed for shop 1 (a first attempt created `OPENCODE-TEST-10` on the wrong shop due to a user-id/merchant-id mix-up; POS correctly rejected it with "This coupon is not valid for this shop" — wrong-shop guard verified live. That record was deleted). Disposable coupon recreated correctly: promotion id 101 `OPENCODE-TEST-10 Percent`, coupon id 2, code `OPENCODE-TEST-10`, 10% percentage_discount targeted at DEMO-2's product, active, shop 1.

Stock baseline: DEMO-2 (variant 2) stock_quantity was 7 before TEST 1.

### TEST 1 — Real baseline POS order — PASS

- Product: White Slim Classic Casual Shirt 1, SKU DEMO-2, variant 2, qty 1 @ ₹949.00. Walk-in customer, Cash, Counter.
- Browser displayed: Subtotal ₹949.00, Discount ₹0.00, Order Discount ₹0.00, Shipping ₹0.00, Grand Total ₹949.00. Cash received ₹949, change ₹0.00. Success panel: "Sale complete ORD-20260910-000001 ₹949.00 Change ₹0.00 Cash".
- Saved order (id 1): created_source=`pos`, status completed, payment paid/cash, subtotal 949.00, rounding 0.00, grand_total 949.00, amount_paid 949.00, change 0.00. Item: DEMO-2 x1 @949, line 949.00. Stock after: 6 (7→6 ✓).

### TEST 2 — Real coupon POS order — PASS

- Same product DEMO-2 x1. Coupon entered through the visible POS Coupon UI as `  opencode-test-10  ` (lowercase + spaces) → normalization verified live: message "Coupon applied.", input re-normalized, line shows "- ₹94.90", price ₹854.10 vs MRP ₹949.00.
- Browser displayed: Subtotal ₹949.00, Discount ₹94.90, Order Discount ₹0.00, Shipping ₹0.00, Round Off -₹0.10, Grand Total ₹854.00. Cash received ₹854, change ₹0.00. Success panel: "Sale complete ORD-20260910-000002 ₹854.00 Change ₹0.00 Cash".
- Saved order (id 2): created_source=`pos`, completed, paid, cash; subtotal 949.00, rounding -0.10, grand_total 854.00, paid 854.00, change 0.00 — exact match with browser preview.
- Order totals rows: subtotal 949.00 (system), item_discount -94.90 "Offer Discount" (source=promotion), rounding -0.10 (source=pos, method nearest/cash), grand_total 854.00.
- Item promotion metadata: promotion id 101, template percentage_discount, discount 94.90, activation_type `coupon`, coupon_id 2, coupon_code `OPENCODE-TEST-10`, policy_overrides inherit/inherit; return_exchange_policy snapshot: refund not allowed/0 days (shop), exchange allowed/7 days (shop).
- Redemption: promotion_redemptions id 1 — promotion 101, coupon 2, order 2, shop 1, customer null (walk-in), discount 94.90, status redeemed, metadata coupon_code + source `pos`. Count for coupon 2 = 1 (no double-redeem).
- Stock after: 5 (6→5 ✓; cumulative 7→5 across both sales).

### Real-checkout matrix

| Check | TEST 1 baseline | TEST 2 coupon |
|---|---|---|
| Browser totals recorded | PASS (949/0/0/949) | PASS (949/94.90/-0.10/854.00) |
| Order created, source=pos, completed+paid cash | PASS (id 1, ORD-...-000001) | PASS (id 2, ORD-...-000002) |
| Saved totals match browser | PASS | PASS (exact) |
| Item promotion metadata | n/a (no promo) | PASS (coupon 2 / OPENCODE-TEST-10 / activation coupon) |
| Redemption record | n/a | PASS (id 1, redeemed, 94.90, source pos) |
| Stock deducted | PASS (7→6) | PASS (6→5) |
| Policy snapshot | n/a checked | PASS (refund no/0, exchange yes/7, shop-sourced) |

### Updated test-data cleanup list

- Live DB (require manual cleanup, all disposable): orders id 1 (ORD-20260910-000001) + id 2 (ORD-20260910-000002) with items/totals; promotion id 101 + coupon id 2 (OPENCODE-TEST-10) + its reward/target rows; promotion_redemptions id 1; DEMO-2 stock reduced 7→5 (restore 2 units if a pristine demo state is wanted).
- Workspace: `.playwright-mcp/` harness artifacts; temp scripts in `C:\Users\Admin\AppData\Local\Temp\opencode\` (ws_*.php, outside repo).

### Updated final recommendation

**SAFE TO FREEZE.** The previous conditional item (a) — a real browser click-through baseline + coupon sale — is now done and green (§18, both PASS with full DB verification). Remaining before freeze: clean up the disposable DB rows above (or formally accept them as demo data) and remove `.playwright-mcp/` artifacts. No blocking/high/medium defects; previous Medium process concern is closed.
