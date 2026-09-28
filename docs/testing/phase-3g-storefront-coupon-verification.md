# Phase 3G Storefront Coupon Verification — Customer Portal Regression

- Date/time: 2026-09-11 (~07:30–08:00 IST browser session; suite after)
- Branch: feature/promotion-calculation-engine, HEAD 9dbea09 (unchanged by this task)
- Customer: kishore@example.test (storefront session = customer id 1,
  CUS-000001 Kishore Mishra 9876601001 — Quick-Add row from POS verification,
  same email; global user id 14)
- Shop: 1 (Vana Women's Studio - Main Branch). No app code/tests/migrations/commits.

## 1. Architecture verification — PASS, no divergence

Storefront coupon flow uses the same shared engine as POS:
- `Storefront\CouponController` → `CouponApplicationService` (wraps
  `CouponResolver` + `CouponSessionStore`).
- `Cart\CartPageService` uses `PromotionCalculator` + `CouponResolver` +
  `CouponSessionStore` (incl. `resolveForShop`, per-shop session state).
- `Checkout\StorefrontCheckoutOrderService` uses the shared
  `Order\OrderCreationService` (server re-resolution + `CouponRedemptionService`
  locking at placement).
Phase 3G touched only `PosPricingService`/`PosController`/POS views (+ shared
`OrderCreationService` customer guard); no storefront calculation path was
duplicated or forked.

## 2. Browser matrix (real Chromium session, logged in as kishore)

| # | Reward type / case | Coupon → product | Expected → actual | Result |
|---|---|---|---|---|
| 1 | Baseline | — → DEMO-2 ×1 | add works, subtotal/total ₹949, shop group + policy line; qty 1→2 recalcs to ₹1,898; remove w/ confirm empties cart | PASS |
| 2 | Automatic | auto promo 117 → DEMO-27 | −₹189.80 (20%) automatically, line/total ₹759.20 | PASS |
| 3 | Percentage + normalize/remove | OPENCODE-TEST-PERCENT (102/3) → DEMO-27, entered as `  opencode-test-percent  ` | normalized; "Coupon is valid, but a better offer has been applied."; auto −189.80 retained; Remove clears coupon, auto stays | PASS |
| 4 | Fixed + coupon-wins | OPENCODE-TEST-FIXED (103/4) → DEMO-28 (auto 118 = 2%) | "Coupon applied.", −₹50, total ₹999 (coupon beats auto ₹20.98) | PASS |
| 5 | Fixed price | OPENCODE-TEST-FIXEDPRICE (104/5) → DEMO-3 | −₹150, total ₹899 | PASS |
| 6 | Quantity | OPENCODE-TEST-QTY (105/6) → DEMO-26 | qty2: "not valid for the items in your cart" (₹1,698); qty3: applied, −₹254.70, ₹2,292.30 | PASS |
| 7 | Bundle | OPENCODE-TEST-BUNDLE (106/7) → DEMO-4 | qty1: not valid (₹1,149); qty2: applied, −₹298, ₹2,000 | PASS |
| 8 | BOGO free | OPENCODE-TEST-BOGOFREE (107/8) → DEMO-6 ×2 | applied, −₹1,349, total ₹1,349, charged qty 2 | PASS |
| 9 | BOGO discount | OPENCODE-TEST-BOGODISCOUNT (108/9) → DEMO-5 ×2 | applied, −₹624.50, total ₹1,873.50 | PASS |
| 10 | Tier | OPENCODE-TEST-TIER (109/10) → DEMO-29 | qty1: not valid; qty2: −₹100 → ₹2,198; qty4: −₹400 → ₹4,196 | PASS |
| 11 | Free gift preview | OPENCODE-TEST-GIFT (110/11), DEMO-10 + gift DEMO-25 | applied; gift row `gift-110-25` virtual in mini-cart (1 × ₹749, subtotal ₹0.00, dead `href="#"`, no qty/remove); main cart keeps only paid line; de-qualify removes gift; re-qualify restores it | PASS |
| 12 | Lifecycle | INACTIVE/EXPIRED/NOTSTARTED (114–116/15–17) → DEMO-35 | "currently unavailable." / "has expired." / "not active yet."; total stays ₹1,749; 0 redemptions | PASS |
| 13A | Auto better | PERCENT vs auto 20% (DEMO-27) | valid-but-not-best, auto wins, coupon 3 redemptions = 0 | PASS |
| 13B | Coupon better | FIXED vs auto 2% (DEMO-28) | coupon wins (−50 vs −20.98) | PASS |
| 14 | Customer restriction | NEWCUSTOMER (111/12) → DEMO-32, as kishore (customer 1) | "not valid for the items in your cart" — customer 1 is NOT new: 1 completed shop-1 order (POS order 11). No history touched; reason verified in DB + `isNewCustomerForShop` (completed-only) | PASS (ineligibility correctly enforced) |
| 10′ | Per-customer limit | PERCUSTLIMIT (112/13) → DEMO-33, as customer 1 (already redeemed) | cart/checkout preview optimistically "applied" (−100); order NOT placed per plan (checkout is the enforcement point; automated tests prove authoritative blocking) | PASS (see §7 note) |
| 15 | Persistence | FIXED on shop 1 | retained after cart refresh AND after navigating to storefront and back (subtotal ₹999, "applied"); Remove stays removed | PASS |
| 16 | Single-shop | shop-2 product | NOT executable live: shop 2 is `inactive` and product 51 is `draft`; activating/publishing would alter merchant data. Covered by automated tests (`same coupon code resolves independently per shop`, `checkout multishop …` — all passing) | PASS via suite (live blocked by safety rule) |

## 3. Automatic promotion result — PASS (§2 rows 2, 13A)

## 4. Apply/Remove result — PASS (rows 3, 4; remove restores auto-only pricing)

## 5. Coupon persistence result — PASS (row 15)

## 6. Invalid/lifecycle results — PASS (row 12)

## 7. Customer restriction result — PASS (row 14; genuine ineligibility, see above)

Observation (not a defect): usage-exhausted coupons (PERCUSTLIMIT for customer 1)
still preview as "applied" in cart AND checkout summary; enforcement happens at
placement via `lockAvailableCouponForCheckout` — identical to the POS design
verified earlier (POS showed the same optimistic preview + hard checkout
rejection). Consistent cross-channel behavior.

## 8. Automatic-vs-coupon result — PASS (rows 13A/13B, existing resolver rules)

## 9. Single-shop checkout result — PASS via automated suite (live blocked, §2 row 16)

## 10. Real storefront orders created (all shop 1, customer 1, pickup + Cash at Shop, status pending)

| Order | Reference | Coupon (promo/coupon) | Lines | Subtotal | Discount | Grand total |
|---|---|---|---|---|---|---|
| 16 | ORD-20260911-000001 | OPENCODE-TEST-GIFT (110/11) | DEMO-10 ×1 @1749 (total 1749) + DEMO-25 ×1 price 749 total 0.00 | 2498.00 | −749.00 | 1749.00 |
| 17 | ORD-20260911-000002 | OPENCODE-TEST-FIXED (103/4) | DEMO-28 ×1, total 999.00 | 1049.00 | −50.00 | 999.00 |
| 18 | ORD-20260911-000003 | OPENCODE-TEST-GLOBALLIMIT (113/14) | DEMO-34 ×1, total 1566.55 | 1649.00 | −82.45 | 1566.55 |
| 19 | ORD-20260911-000004 | OPENCODE-TEST-GLOBALLIMIT (113/14) | DEMO-34 ×1, total 1566.55 | 1649.00 | −82.45 | 1566.55 |
| 20 | ORD-20260911-000005 | coupon EXCLUDED (limit reached) | DEMO-34 ×1, no promo metadata | 1649.00 | 0 | 1649.00 |

Browser vs saved: gift checkout preview (1749/−749/0 ship/1749) == order 16;
FIXED preview (999) == order 17; GLOBALLIMIT previews (1566.55) == orders 18/19.

## 11. Coupon redemptions

- id 13: promo 110 / coupon 11, order 16, 749.00, redeemed (gift).
- id 14: promo 103 / coupon 4, order 17, 50.00, redeemed (fixed).
- ids 15, 16: promo 113 / coupon 14, orders 18, 19, 82.45 each, redeemed.
- Coupon 14 total = 4/4 (2 prior POS + 2 storefront). 5th attempt (order 20):
  server excluded the coupon — full-price order, no metadata, no redemption.
- Coupons 3, 12, 15, 16, 17: still 0 redemptions (correct).

## 12. Free Gift verification — PASS

Customer never submitted the gift (virtual `gift-110-25` row only); server wrote
the real DEMO-25 @0.00 line with coupon metadata (promo 110 / coupon 11 /
discount 749.00); redemption 13; stock DEMO-10 14→13, DEMO-25 29→28.

## 13. Stock before/after (storefront session)

DEMO-28 32→31; DEMO-10 14→13; DEMO-25 29→28; DEMO-34 7→4 (3 units).
Other preview-only SKUs untouched.

## 14. Policy snapshot verification — PASS

Every coupon order item: refund not allowed / 0 days (shop), exchange allowed /
7 days (shop) — same snapshot shape as POS orders.

## 15/16. Automated test results — full suite: 989 passed (7619 assertions), 0 failures

Relevant suites all green: StorefrontCartPageTest (coupon normalize/per-shop/
invalid/winner/complex/gift/requalify), StorefrontCheckoutGateTest (session
coupon, gift order, stale revalidation, all usage-limit isolations, forged-value
rejection, multishop), PromotionCalculationEngineTest, MerchantPosTest,
OrderReturnPolicySnapshotTest, OrderTaxSnapshot*.

## 17. git diff --check — clean

## 18. git status — only the six pre-existing Phase 3G files modified;
untracked `.playwright-mcp/` (harness) + `docs/testing/` (reports).

## 19. Test data requiring cleanup (NOT cleaned)

Storefront orders 16–20; redemptions 13–16; coupon-14 now at 4/4 (exhausted);
promo 113 limit 2→4; stock deltas above; all prior POS data intact
(orders 1–11/13/14, redemptions 1–12, promos 101–118).

## 20. Defects — Blocking: none. High: none. Medium: none. Low/notes:

1. Exhausted-limit coupons preview "applied" until placement (by design,
   enforced authoritatively at checkout on both channels).
2. Shop-2 inactive + product draft blocks live multi-shop checkout (safety rule
   respected; suite-covered).
3. Storefront delivery option showed "No payment method available"; pickup +
   Cash at Shop worked (pre-existing shop config, unrelated to coupons).

## 21. Final recommendation

**STOREFRONT REGRESSION PASS**
