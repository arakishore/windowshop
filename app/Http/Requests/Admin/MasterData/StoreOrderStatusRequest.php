<?php

namespace App\Http\Requests\Admin\MasterData;

use App\Models\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $orderStatus = $this->route('orderStatus') ?? $this->route('order_status');
        $descriptionRules = ['nullable', 'string', 'max:500'];

        if ($orderStatus instanceof OrderStatus && $orderStatus->is_system) {
            $descriptionRules[0] = 'required';
        }

        return [
            'name' => ['required', 'string', 'max:150'],
            'customer_label' => ['nullable', 'string', 'max:150'],
            'description' => $descriptionRules,
            'internal_notes' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', Rule::in(OrderStatus::categories())],
            'badge_type' => ['required', Rule::in(OrderStatus::badgeTypes())],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999999'],
            'is_terminal' => ['nullable', 'boolean'],
            'customer_visible' => ['nullable', 'boolean'],
            'merchant_visible' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in([OrderStatus::STATUS_ACTIVE, OrderStatus::STATUS_INACTIVE])],
        ];
    }
}
