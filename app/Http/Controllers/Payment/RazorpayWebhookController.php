<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\PaymentAccount;
use App\Services\Payment\RazorpayWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RazorpayWebhookController extends Controller
{
    public function __invoke(Request $request, string $token, RazorpayWebhookService $webhooks): JsonResponse
    {
        $account = PaymentAccount::query()->where('provider', 'razorpay')->where('webhook_token', $token)->first();
        if (! $account) {
            return response()->json(['status' => 'not_found'], 404);
        }
        $result = $webhooks->handle($account, $request->getContent(), $request->header('X-Razorpay-Signature'), $request->header('X-Razorpay-Event-Id'));

        return response()->json(['status' => $result['disposition']], $result['status']);
    }
}
