# Phase 3G Storefront Regression — Test Data Preparation (Run 3)

- Date/time: 2026-09-11
- Branch: feature/promotion-calculation-engine (unchanged)
- Status: **SUPERSEDED — see §5 correction below. Dataset verified intact; one limit adjusted.**

## 0. CORRECTION (same day, after re-authorization check)

The "freshly seeded / dataset gone" conclusion above was a **false alarm caused
by a wrong LIKE pattern** (`OPENCODE-TEST-%` does not match names like
`OPENCODE-TEST Percent 10`, which contain a space, not a second dash).
Re-querying by coupon code and by promotion ID proves the full dataset is intact:

- Promos 102–116 (all 9 reward types + restriction/lifecycle coupons) all exist,
  shop 1 / merchant 1, correct activation, rewards, targets, conditions, windows.
- Coupons 3–17 all exist with correct codes/status/limits.
- Automatic promos 117 (20% on DEMO-27) and 118 (2% on DEMO-28) exist and active.
- `setupIssues() == []` for every promotion.
- BOGOFREE (promo 107) is still on the DEMO-6 same-pool retarget (buy product 6 /
  get product 6), as documented.
- Prior POS verification orders (13) and redemptions also persist (see counts below).

One deliberate test-data adjustment (own disposable data only): promo 113
(OPENCODE-TEST-GLOBALLIMIT) `total_usage_limit` raised 2 → 4, because its 2
prior POS redemptions would otherwise leave zero capacity for the storefront
limit scenario. Now: 2 redeemed / 4 allowed → storefront can prove 2 further
redemptions + a 5th-attempt rejection. No other record was created, modified,
or deleted. Full validation table is in the session report below (§5).

## 1. Database state check

Live DB query shows the database was freshly seeded since the POS verification:

- `OPENCODE-TEST-*` promotions: only **1** remains — promo 101
  (`OPENCODE-TEST-10 Percent`, shop 1, active).
- Promos 102–118 and coupons 3–17 (the full 9-reward-type dataset + restriction/
  lifecycle/automatic records) are **gone**.
- Product stock is back at seeded levels (e.g. DEMO-26: 28→ back toward seed;
  DEMO-28: 32; DEMO-34: 7 — seed values, not post-test values).
- Prior POS verification orders may also be gone (not enumerated; irrelevant —
  the coupon dataset required for storefront scenarios does not exist).

Per the task instructions, the dataset was **NOT silently recreated**.
No promotions/coupons were created, modified, or deleted by this run.

## 2. Storefront architecture spot-check (read-only, no changes)

Shared promotion/coupon infrastructure is present and untouched:

- app/Services/Promotion/Coupons/: CouponResolver.php, CouponApplicationService.php,
  CouponResolution.php, CouponSessionStore.php
- app/Http/Controllers/Storefront/CouponController.php
- app/Services/Cart/CartPageService.php
- PromotionCalculator / PromotionCombinationResolver / CouponRedemptionService /
  OrderCreationService were confirmed shared in the earlier Phase 3G review
  (PosPricingService reuses CouponResolver + PromotionCalculator; no separate
  POS engine), so no storefront/POS calculation divergence is expected from
  Phase 3G code. Full architecture + browser verification is deferred until
  test data is recreated and approved.

## 3. Confirmations

- No application code changed. No tests changed. No migrations. No commit/push/merge.
- No orders placed. No browser checkout run.
- No test data created or deleted.

## 4. Required before storefront verification can proceed

Recreate (with explicit approval) the Run-1 dataset from
docs/testing/Phase3GRealPOSCouponTestDataPreparation.md:
promos 102–118 / coupons OPENCODE-TEST-PERCENT, -FIXED, -FIXEDPRICE, -QTY,
-BUNDLE, -BOGOFREE, -BOGODISCOUNT, -TIER, -GIFT, -NEWCUSTOMER, -PERCUSTLIMIT,
-GLOBALLIMIT, -INACTIVE, -EXPIRED, -NOTSTARTED + the two automatic promotions,
with the documented BOGOFREE retarget note (product 6, since DEMO-1 is not in
the POS grid) re-applied if the same grid cap persists. Note usage limits on
PERCUSTLIMIT/GLOBALLIMIT will be fresh (0 redemptions) after recreation.
Then re-run the 19-scenario storefront plan.
