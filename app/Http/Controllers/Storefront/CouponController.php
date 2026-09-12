<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Promotion\Coupons\CouponApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CouponController extends Controller
{
    public function store(Request $request, int $shop, CouponApplicationService $coupons): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'coupon_code' => ['required', 'string', 'max:80'],
        ]);

        try {
            $payload = $coupons->apply($request, $shop, $data['coupon_code']);
        } catch (ValidationException $exception) {
            if ($request->expectsJson()) {
                throw $exception;
            }

            return back()->withErrors($exception->errors(), 'coupon');
        }

        if ($request->expectsJson()) {
            return response()->json($payload, $payload['success'] ? 200 : 422);
        }

        return back()->with($payload['success'] ? 'success' : 'error', $payload['coupon']['message'] ?? 'Coupon could not be applied.');
    }

    public function destroy(Request $request, int $shop, CouponApplicationService $coupons): JsonResponse|RedirectResponse
    {
        $payload = $coupons->remove($request, $shop);

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return back()->with('success', 'Coupon removed.');
    }
}
