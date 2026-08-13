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

    public function detect(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
        ], [
            'latitude.between' => 'Latitude must be between -90 and 90.',
            'longitude.between' => 'Longitude must be between -180 and 180.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The detected location is invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $nearest = $this->location->resolveNearestPostalCode((float) $data['latitude'], (float) $data['longitude']);

        if ($nearest === null) {
            return response()->json([
                'message' => "We couldn't match your location to a nearby PIN code. Please enter your PIN code instead.",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'postal_code' => $nearest['postal_code'],
            'locality' => $nearest['locality'],
            'district' => $nearest['district'],
            'state' => $nearest['state'],
            'distance_km' => $nearest['distance_km'],
            'accuracy' => $data['accuracy'] ?? null,
        ]);
    }
}
