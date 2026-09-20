<?php

namespace App\Http\Requests\Storefront;

use App\Models\ProductReview;
use App\Services\Review\ProductReviewImageService;
use Illuminate\Foundation\Http\FormRequest;

class SaveProductReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:150'],
            'review_text' => ['required', 'string', 'max:5000'],
            'images' => ['nullable', 'array', 'max:'.ProductReviewImageService::MAX_IMAGES],
            'images.*' => [
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.(int) config('images.product_review.max_upload_kb', 5120),
            ],
            'remove_image_ids' => ['nullable', 'array'],
            'remove_image_ids.*' => ['integer', 'distinct'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $review = $this->route('review');

            if (! $review instanceof ProductReview) {
                return;
            }

            $existingIds = $review->images()->pluck('id');
            $removedCount = $existingIds->intersect($this->input('remove_image_ids', []))->count();
            $newCount = count($this->file('images', []));

            if (($existingIds->count() - $removedCount + $newCount) > ProductReviewImageService::MAX_IMAGES) {
                $validator->errors()->add('images', 'A review can have a maximum of 5 images.');
            }
        });
    }
}
