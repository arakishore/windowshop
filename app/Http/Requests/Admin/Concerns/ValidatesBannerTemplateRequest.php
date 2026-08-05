<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Enums\BannerPosition;
use App\Enums\BannerTemplateAvailability;
use App\Enums\BannerTemplateCategory;
use App\Models\BannerTemplate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesBannerTemplateRequest
{
    protected function templateRules(bool $creating): array
    {
        $template = $this->route('banner_template');
        $templateId = $template instanceof BannerTemplate ? $template->getKey() : null;

        return [
            'code' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique('banner_templates', 'code')->ignore($templateId),
            ],
            'category' => ['required', Rule::in(BannerTemplateCategory::values())],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'default_title' => ['required', 'string', 'max:180'],
            'default_subtitle' => ['nullable', 'string', 'max:255'],
            'default_button_text' => ['nullable', 'string', 'max:80'],
            'desktop_image' => [$creating ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'mobile_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_mobile_image' => ['nullable', 'boolean'],
            'default_position' => ['required', Rule::in(BannerPosition::values())],
            'availability' => ['required', Rule::in(BannerTemplateAvailability::values())],
            'event_code' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9_-]+$/'],
            'start_offset_days' => ['nullable', 'integer', 'between:-365,365'],
            'end_offset_days' => ['nullable', 'integer', 'between:-365,365'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::in([BannerTemplate::STATUS_ACTIVE, BannerTemplate::STATUS_INACTIVE])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $start = $this->input('start_offset_days');
            $end = $this->input('end_offset_days');

            if ($start !== null && $start !== '' && $end !== null && $end !== '' && (int) $end < (int) $start) {
                $validator->errors()->add('end_offset_days', 'The end offset must not be earlier than the start offset.');
            }

            $position = BannerPosition::tryFrom((string) $this->input('default_position'));
            $availability = BannerTemplateAvailability::tryFrom((string) $this->input('availability'));

            if (! $position || ! $availability || $availability === BannerTemplateAvailability::BOTH) {
                return;
            }

            if ($position->scope() !== $availability->value) {
                $validator->errors()->add('availability', 'The availability must match the selected default position scope.');
            }
        });
    }

    public function templateData(): array
    {
        return [
            'code' => $this->string('code')->toString(),
            'category' => $this->string('category')->toString(),
            'name' => $this->string('name')->toString(),
            'description' => $this->filled('description') ? $this->string('description')->toString() : null,
            'default_title' => $this->string('default_title')->toString(),
            'default_subtitle' => $this->filled('default_subtitle') ? $this->string('default_subtitle')->toString() : null,
            'default_button_text' => $this->filled('default_button_text') ? $this->string('default_button_text')->toString() : null,
            'default_position' => $this->string('default_position')->toString(),
            'availability' => $this->string('availability')->toString(),
            'event_code' => $this->filled('event_code') ? $this->string('event_code')->toString() : null,
            'start_offset_days' => $this->filled('start_offset_days') ? (int) $this->input('start_offset_days') : null,
            'end_offset_days' => $this->filled('end_offset_days') ? (int) $this->input('end_offset_days') : null,
            'sort_order' => (int) $this->input('sort_order', 0),
            'status' => $this->string('status')->toString(),
        ];
    }
}
