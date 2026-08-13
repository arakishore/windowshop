<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\PostalCode;
use App\Services\Storefront\CustomerLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CustomerLocationController extends Controller
{
    public function __construct(
        private readonly CustomerLocationService $location,
    ) {}

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'postal_code' => [
                'required',
                'string',
                'regex:/^\d{6}$/',
                Rule::exists('postal_codes', 'postal_code')
                    ->where('status', PostalCode::STATUS_ACTIVE)
                    ->whereNull('deleted_at'),
            ],
        ], [
            'postal_code.required' => 'Please enter your PIN code.',
            'postal_code.regex' => 'Enter a valid 6-digit PIN code.',
            'postal_code.exists' => "We couldn't find this PIN code. Please check and try again.",
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'The selected PIN code is invalid.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            return back()
                ->withErrors($validator, 'customerLocation')
                ->withInput();
        }

        $postalCode = $this->location->store($request, (string) $validator->validated()['postal_code']);
        $cookie = cookie(CustomerLocationService::COOKIE_NAME, $postalCode, CustomerLocationService::COOKIE_MINUTES);

        if ($request->expectsJson()) {
            return response()
                ->json([
                    'message' => 'Shopping location updated.',
                    'postal_code' => $postalCode,
                ])
                ->withCookie($cookie);
        }

        return back()
            ->with('customer_location_saved', true)
            ->withCookie($cookie);
    }
}
