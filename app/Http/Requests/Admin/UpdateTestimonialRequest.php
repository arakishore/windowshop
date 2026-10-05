<?php

namespace App\Http\Requests\Admin;

use App\Models\Testimonial;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTestimonialRequest extends FormRequest
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
        $thumb = config('images.testimonial.variants.thumb', [160, 160]);

        return [
            'type' => ['required', 'string', Rule::in([Testimonial::TYPE_CUSTOMER, Testimonial::TYPE_MERCHANT])],
            'name' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:150'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'business_name' => ['nullable', 'string', 'max:191'],
            'designation' => ['nullable', 'string', 'max:150'],
            'photo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.(int) config('images.testimonial.max_upload_kb', 5120),
                'dimensions:min_width='.(int) $thumb[0].',min_height='.(int) $thumb[1],
            ],
            'remove_photo' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in([Testimonial::STATUS_ACTIVE, Testimonial::STATUS_INACTIVE])],
        ];
    }
}
