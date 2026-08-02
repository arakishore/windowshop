<?php

namespace App\Http\Requests\Admin\MasterData;

use App\Models\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $paymentStatus = $this->route('paymentStatus') ?? $this->route('payment_status');
        $descriptionRules = ['nullable', 'string', 'max:500'];

        if ($paymentStatus instanceof PaymentStatus && $paymentStatus->is_system) {
            $descriptionRules[0] = 'required';
        }

        return [
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(PaymentStatus::categories())],
            'description' => $descriptionRules,
            'category_description' => ['nullable', 'string', 'max:500'],
            'badge_type' => ['required', Rule::in(PaymentStatus::badgeTypes())],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999999'],
            'is_terminal' => ['nullable', 'boolean'],
            'merchant_visible' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in([PaymentStatus::STATUS_ACTIVE, PaymentStatus::STATUS_INACTIVE])],
        ];
    }
}
