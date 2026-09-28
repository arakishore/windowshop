# Phase 3G Real POS Coupon Test Data Preparation

- Date/time: 2026-09-10 ~21:00 IST
- Branch: feature/promotion-calculation-engine (unchanged by this task)
- Merchant: priya@vanawomen.test → merchant_id 1, shop 1 "Vana Women's Studio - Main Branch"
- No orders placed. No browser checkout run. No code/tests/migrations/commits.

## 1. Supported coupon reward types (authoritative, from current code)

`PromotionRepository::SUPPORTED_REWARD_TYPES` + `PromotionReward::TYPE_*` + `PromotionTemplateSeeder` agree on exactly 9 types:

1. `percentage_discount` (reward: value_percent, +optional max_discount_amount)
2. `fixed_discount` (reward: value_amount)
3. `fixed_price` (reward: value_amount = promo unit price; applies only if it benefits customer)
4. `quantity_discount` (condition minimum_quantity >= X; reward value_type percent|amount + value)
5. `fixed_bundle_price` (reward bundle_quantity + bundle_price; whole-unit groups)
6. `buy_x_get_y_free` (reward buy_quantity + get_quantity; ROLE_BUY + ROLE_GET targets)
7. `buy_x_get_y_discount` (buy_quantity + get_quantity + value_percent; ROLE_BUY + ROLE_GET)
8. `tier_pricing` (reward tier_config [{min_quantity, unit_price}]; whole units)
9. `free_gift` (condition minimum_eligible_subtotal; ROLE_ELIGIBLE + ROLE_GIFT variant target)

No types invented. `Promotion::setupIssues()` returns [] for every record below (all setup-complete and activatable).

## 2. Coupons created (all shop_id 1, active, coupon activation, merchant origin, policy inherit/inherit)

| # | Promo ID | Coupon ID | Code | Reward type | Config | Qualifying target | Status/window |
|---|---|---|---|---|---|---|---|
| 1 | 102 | 3 | OPENCODE-TEST-PERCENT | percentage_discount | 10% | product 27 (DEMO-27) | active |
| 2 | 103 | 4 | OPENCODE-TEST-FIXED | fixed_discount | ₹50.00 | product 28 (DEMO-28) | active |
| 3 | 104 | 5 | OPENCODE-TEST-FIXEDPRICE | fixed_price | promo price ₹899.00 | product 3 (DEMO-3) | active |
| 4 | 105 | 6 | OPENCODE-TEST-QTY | quantity_discount | min qty ≥3, 10% | product 26 (DEMO-26) | active |
| 5 | 106 | 7 | OPENCODE-TEST-BUNDLE | fixed_bundle_price | any 2 for ₹2000.00 | product 4 (DEMO-4) | active |
| 6 | 107 | 8 | OPENCODE-TEST-BOGOFREE | buy_x_get_y_free | buy 1 get 1 (same pool) | buy product 1 / get product 1 | active |
| 7 | 108 | 9 | OPENCODE-TEST-BOGODISCOUNT | buy_x_get_y_discount | buy 1 get 1 at 50% off (same pool) | buy product 5 / get product 5 | active |
| 8 | 109 | 10 | OPENCODE-TEST-TIER | tier_pricing | 2+ @₹1099, 4+ @₹1049 | product 29 (DEMO-29) | active |
| 9 | 110 | 11 | OPENCODE-TEST-GIFT | free_gift | min eligible subtotal ₹1500; gift variant 25 | eligible product 10 / gift variant 25 (DEMO-25) | active |
| 10 | 111 | 12 | OPENCODE-TEST-NEWCUSTOMER | percentage_discount | 15%, new_customer_only=true | product 32 (DEMO-32) | active |
| 11 | 112 | 13 | OPENCODE-TEST-PERCUSTLIMIT | fixed_discount | ₹100.00, coupon per_customer_usage_limit=1 | product 33 (DEMO-33) | active |
| 12 | 113 | 14 | OPENCODE-TEST-GLOBALLIMIT | percentage_discount | 5%, promotion total_usage_limit=2 | product 34 (DEMO-34) | active |
| 13 | 114 | 15 | OPENCODE-TEST-INACTIVE | percentage_discount | 10%, coupon status=inactive | product 35 (DEMO-35) | inactive coupon |
| 14 | 115 | 16 | OPENCODE-TEST-EXPIRED | percentage_discount | 10%, promo 10 days ago → yesterday | product 36 (DEMO-36) | expired |
| 15 | 116 | 17 | OPENCODE-TEST-NOTSTARTED | percentage_discount | 10%, promo tomorrow → +10 days | product 37 (DEMO-37) | not started |

Pre-existing disposable (from earlier verification, same prefix, also usable): promo 101 / coupon 2 / OPENCODE-TEST-10, 10% off product 2 (DEMO-2).

Automatic promotions for competition tests:

| Promo ID | Name | Config | Target |
|---|---|---|---|
| 117 | OPENCODE-TEST Auto 20pct on DEMO-27 | automatic, 20% | product 27 (DEMO-27) |
| 118 | OPENCODE-TEST Auto 2pct on DEMO-28 | automatic, 2% | product 28 (DEMO-28) |

Wrong-shop case: no new record needed — earlier live check proved a coupon from another merchant's shop is rejected with "not valid for this shop"; that record was deleted.

## 3. Products/variants selected (shop 1, all active; stock BEFORE = at creation time)

| SKU | Variant ID | Product ID | Price (₹) | Stock before |
|---|---|---|---|---|
| DEMO-27 | 27 | 27 | 949.00 | 32 |
| DEMO-28 | 28 | 28 | 1049.00 | 33 |
| DEMO-3 | 3 | 3 | 1049.00 | 8 |
| DEMO-26 | 26 | 26 | 849.00 | 31 |
| DEMO-4 | 4 | 4 | 1149.00 | 9 |
| DEMO-1 | 1 | 1 | 849.00 | 6 |
| DEMO-5 | 5 | 5 | 1249.00 | 10 |
| DEMO-29 | 29 | 29 | 1149.00 | 34 |
| DEMO-10 | 10 | 10 | 1749.00 | 15 |
| DEMO-25 (gift) | 25 | 25 | 749.00 | 30 |
| DEMO-32 | 32 | 32 | 1449.00 | 7 |
| DEMO-33 | 33 | 33 | 1549.00 | 8 |
| DEMO-34 | 34 | 34 | 1649.00 | 9 |
| DEMO-35 | 35 | 35 | 1749.00 | 10 |
| DEMO-36 | 36 | 36 | 1849.00 | 11 |
| DEMO-37 | 37 | 37 | 1949.00 | 12 |

Note: DEMO-2 stock is 5 (two real sales from the earlier verification step consumed 2 of 7); DEMO-2 is NOT reused by the new coupons. No stock was moved by this preparation step.

## 4. Expected calculations (before-tax; POS then adds tax/rounding per existing order)

Tax/rounding behavior is the existing engine's (unchanged): promotion discount applies before tax; cash rounding (nearest, cash) may add a Round Off row. Expected promo math:

1. PERCENT: 949 × 1 → subtotal 949.00; 10% = 94.90; discounted 854.10.
2. FIXED: 1049 × 1 → subtotal 1049.00; −50.00; discounted 999.00.
3. FIXEDPRICE: 1049 × 1 → fixed 899.00; discount 150.00 (beneficial, applies).
4. QTY: 849 × 2 → no discount (below min 3), 1698.00. 849 × 3 → 10% = 254.70; discounted 2292.30.
5. BUNDLE: 1149 × 1 → no discount. 1149 × 2 = 2298 → bundle 2000.00; discount 298.00.
6. BOGOFREE: DEMO-1 × 2 → 1 group: 1 paid (849) + 1 free; discount 849.00; payable 849.00 + tax/rounding.
7. BOGODISCOUNT: DEMO-5 × 2 (1249) → 1 paid + 1 at 50% (624.50); discount 624.50; payable 1873.50.
8. TIER: 1149 × 1 → 1149.00 (no tier). × 2 → 2×1099 = 2198.00 (discount 100.00). × 4 → 4×1049 = 4196.00 (discount 400.00).
9. GIFT: DEMO-10 × 1 = 1749 ≥ 1500 → gift DEMO-25 × 1 generated server-side at 0.00 (line total correct, stock deducted); payable 1749.00. Below threshold (no qualifying subtotal) → no gift.
10. NEWCUSTOMER: walk-in/no-customer → `customer_required`, not applied. With kishore@example.test selected (if new for shop 1): 1449 × 15% = 217.35; discounted 1231.65. (Newness = no prior completed orders for shop 1.)
11. PERCUSTLIMIT: first use → 1549 − 100 = 1449.00 + redemption; second use by same customer → limit blocks re-redeem.
12. GLOBALLIMIT: 5% off 1649 = 82.45; promotion total_usage_limit=2 → third redemption globally blocked.
13. INACTIVE / 14. EXPIRED / 15. NOTSTARTED: no discount; POS feedback states invalid/inactive/expired/not-started; checkout cannot use them.
16. Scenario A (DEMO-27): auto 20% (189.80) beats coupon 10% (94.90) → automatic wins; coupon reports valid-but-not-best; coupon NOT redeemed.
17. Scenario B (DEMO-28): coupon ₹50 beats auto 2% (20.98) → coupon wins, applied + redeemed.

## 5. Setup problems

- One mis-scoped record (shop 3, wrong merchant, from an earlier step's user-id/merchant-id mix-up) was found and deleted before this task; POS had correctly rejected it live. All records above are shop 1 and setup-complete.
- None otherwise. No validation rules were altered.

## 6. Confirmations

- No application code changed (git status shows only the pre-existing Phase 3G diff).
- No migration created.
- No order placed (no checkout run; stock untouched by this step).
- No commit/push/merge.

## 7. Cleanup list (when tests finish)

Promotions 101–118 (incl. 2 automatic), coupons 2–17, their rewards/targets/conditions/redemptions, resulting orders, and stock restoration for all touched variants. Stop for review before browser transaction tests.
