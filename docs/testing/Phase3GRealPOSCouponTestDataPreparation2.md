# Phase 3G Real POS Coupon Test Data Preparation (Run 2 — full verification session)

- Date/time: 2026-09-10 ~21:00–22:00 IST
- Branch: feature/promotion-calculation-engine (no code/tests/migrations/commits by this task)
- Companion report: docs/testing/phase-3g-pos-coupon-verification.md §16 (results) + §17 (recommendation)
- Prior prep doc: docs/testing/Phase3GRealPOSCouponTestDataPreparation.md (dataset definition)

## 1. Dataset changes during this session

Base dataset (promos 102–118, coupons 3–17) was used as prepared, with ONE
adjustment to the tester's OWN disposable data (no merchant data touched):

- Promo 107 (OPENCODE-TEST-BOGOFREE) targets changed from product 1 to product 6
  (buy product 6 / get product 6, same pool), because variant DEMO-1 is not
  rendered in the POS product grid (grid shows 45 of 50 variants; DEMO-1/11/21/31/41
  absent — pre-existing grid cap, unrelated to Phase 3G). Setup re-checked:
  `setupIssues() = []`. Expected benefit updated: 1349.00 (DEMO-6 × 2, one free).

Customer identity note: kishore@example.test (global user 14) had NO
merchant-customer link for merchant 1, so POS search could not select him. POS
Quick-Add (name Kishore Mishra, mobile 9876601001) created a NEW customer row
id 1 (CUS-000001) with address, genuinely new (0 completed orders for shop 1).
No order history was manipulated. The new-customer coupon path was tested
legitimately against this new row; the per-customer-limit path likewise
(customer_id 1 on order 11 + redemption 10).

## 2. Orders placed (all shop 1, source pos, completed, paid, cash, change 0)

| Order ID | Reference | Coupon (promo/coupon) | Lines | Subtotal | Discount | Rounding | Grand total |
|---|---|---|---|---|---|---|---|
| 3 | ORD-20260910-000003 | OPENCODE-TEST-FIXED (103/4) | DEMO-28 ×1 | 1049.00 | −50.00 | 0.00 | 999.00 |
| 4 | ORD-20260910-000004 | OPENCODE-TEST-FIXEDPRICE (104/5) | DEMO-3 ×1 | 1049.00 | −150.00 | 0.00 | 899.00 |
| 5 | ORD-20260910-000005 | OPENCODE-TEST-QTY (105/6) | DEMO-26 ×3 | 2547.00 | −254.70 | −0.30 | 2292.00 |
| 6 | ORD-20260910-000006 | OPENCODE-TEST-BUNDLE (106/7) | DEMO-4 ×2 | 2298.00 | −298.00 | 0.00 | 2000.00 |
| 7 | ORD-20260910-000007 | OPENCODE-TEST-BOGOFREE (107/8) | DEMO-6 ×2 | 2698.00 | −1349.00 | 0.00 | 1349.00 |
| 8 | ORD-20260910-000008 | OPENCODE-TEST-BOGODISCOUNT (108/9) | DEMO-5 ×2 | 2498.00 | −624.50 | +0.50 | 1874.00 |
| 9 | ORD-20260910-000009 | OPENCODE-TEST-TIER (109/10) | DEMO-29 ×4 | 4596.00 | −400.00 | 0.00 | 4196.00 |
| 10 | ORD-20260910-000010 | OPENCODE-TEST-GIFT (110/11) | DEMO-10 ×1 + DEMO-25 gift ×1 @0.00 | 2498.00 | −749.00 | 0.00 | 1749.00 |
| 11 | ORD-20260910-000011 | OPENCODE-TEST-PERCUSTLIMIT (112/13), cust 1 | DEMO-33 ×1 | 1549.00 | −100.00 | 0.00 | 1449.00 |
| 13 | ORD-20260910-000012 | OPENCODE-TEST-GLOBALLIMIT (113/14) | DEMO-34 ×1 | 1649.00 | −82.45 | +0.45 | 1567.00 |
| 14 | ORD-20260910-000013 | OPENCODE-TEST-GLOBALLIMIT (113/14) | DEMO-34 ×1 | 1649.00 | −82.45 | +0.45 | 1567.00 |

(Order id 12 was never created: the blocked per-customer-limit second checkout
failed atomically with no order row. Plus earlier session orders 1–2 from the
baseline/percent verification.)

## 3. Redemptions (promotion_redemptions ids 2–12; id 1 = earlier OPENCODE-TEST-10)

| Redemption | Promo/coupon | Order | Customer | Amount | Status |
|---|---|---|---|---|---|
| 2 | 103/4 | 3 | walk-in (null) | 50.00 | redeemed |
| 3 | 104/5 | 4 | null | 150.00 | redeemed |
| 4 | 105/6 | 5 | null | 254.70 | redeemed |
| 5 | 106/7 | 6 | null | 298.00 | redeemed |
| 6 | 107/8 | 7 | null | 1349.00 | redeemed |
| 7 | 108/9 | 8 | null | 624.50 | redeemed |
| 8 | 109/10 | 9 | null | 400.00 | redeemed |
| 9 | 110/11 | 10 | null | 749.00 | redeemed |
| 10 | 112/13 | 11 | 1 | 100.00 | redeemed |
| 11 | 113/14 | 13 | null | 82.45 | redeemed |
| 12 | 113/14 | 14 | null | 82.45 | redeemed |

Zero redemptions (correct): coupons 3 (auto won), 12 (preview only), 15/16/17
(lifecycle). No double-redeem anywhere. Blocked attempts created neither orders
nor redemptions.

## 4. Stock before → after (this session)

DEMO-28 33→32; DEMO-3 8→7; DEMO-26 31→28; DEMO-4 9→7; DEMO-6 11→9; DEMO-5 10→8;
DEMO-29 34→30; DEMO-10 15→14; DEMO-25 30→29; DEMO-33 8→7; DEMO-34 9→7.
(DEMO-2 7→5 from the earlier session.)

## 5. Automated tests (after browser testing)

- Targeted (MerchantPosTest + PromotionCalculationEngineTest): 145 passed, 1084 assertions.
- Full suite: 989 passed, 7619 assertions. Zero failures.
- git diff --check: clean. git status: only the six pre-existing Phase 3G files
  modified; untracked `.playwright-mcp/` + `docs/testing/`.

## 6. Confirmations

- No application code, automated tests, or migrations changed/created.
- No commit/push/merge.
- No additional promotions/coupons created beyond the one documented retarget.
- Test data NOT cleaned up (per instructions).

## 7. Cleanup list (for the cleanup step)

Orders 1–11, 13, 14 + items/totals; promotions 101–118 + rewards/targets/
conditions; coupons 2–17; redemptions 1–12; customer row id 1 (CUS-000001) +
address; stock restore per §4 (plus DEMO-2 +2).
