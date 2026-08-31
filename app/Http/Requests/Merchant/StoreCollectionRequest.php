<?php

namespace App\Http\Requests\Merchant;

use App\Models\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in([Collection::STATUS_ACTIVE, Collection::STATUS_INACTIVE])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ];
    }

    /**
     * @return array{name: string, description: string|null, status: string, sort_order: int}
     */
    public function collectionData(): array
    {
        $data = $this->validated();

        return [
            'name' => trim((string) $data['name']),
            'description' => $this->nullableString($data['description'] ?? null),
            'status' => (string) $data['status'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
