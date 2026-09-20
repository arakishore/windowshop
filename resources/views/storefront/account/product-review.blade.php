@extends('storefront.layouts.app')

@section('title', ($review->exists ? 'Edit' : 'Write').' Product Review | '.$marketplaceName)

@section('content')
    @component('storefront.account.partials.shell', ['customer' => $customer, 'accountPageTitle' => 'Product Review'])
        <div class="mb-24">
            <p class="text-caption-01 cl-text-3 mb-6">Order {{ $item->order->order_number }}</p>
            <h4 class="mb-8">{{ $review->exists ? 'Edit Your Review' : 'Rate & Review Product' }}</h4>
            <p class="cl-text-2 mb-0">Your review will be published after moderation.</p>
        </div>

        <div class="review-product-summary mb-24">
            <img src="{{ $item->product_image ? asset('storage/'.$item->product_image) : asset('assets/storefront/images/no-image-icon.png') }}" alt="{{ $item->product_name }}">
            <div><strong>{{ $item->product_name }}</strong>@if($item->variant_name)<p class="mb-0 cl-text-3">{{ $item->variant_name }}</p>@endif</div>
        </div>

        <form method="POST" enctype="multipart/form-data" action="{{ $review->exists ? route('storefront.account.reviews.update', $review) : route('storefront.account.reviews.store', $item) }}" class="review-form" data-review-form data-max-images="5">
            @csrf
            @if($review->exists) @method('PUT') @endif
            <fieldset class="review-rating mb-20">
                <legend>Rating <span class="text-primary">*</span></legend>
                <div class="review-stars">
                    @for($rating = 5; $rating >= 1; $rating--)
                        <input id="rating-{{ $rating }}" name="rating" type="radio" value="{{ $rating }}" @checked((int) old('rating', $review->rating) === $rating) required>
                        <label for="rating-{{ $rating }}" title="{{ $rating }} stars">&#9733;</label>
                    @endfor
                </div>
                @error('rating')<div class="text-danger mt-4">{{ $message }}</div>@enderror
            </fieldset>
            <div class="mb-20"><label for="review_text" class="fw-medium mb-8 d-block">Review <span class="text-primary">*</span></label><textarea id="review_text" name="review_text" rows="6" maxlength="5000" class="form-control" required>{{ old('review_text', $review->review_text) }}</textarea>@error('review_text')<div class="text-danger mt-4">{{ $message }}</div>@enderror</div>
            <div class="mb-24"><label for="title" class="fw-medium mb-8 d-block">Title <span class="cl-text-3">(optional)</span></label><input id="title" name="title" maxlength="150" class="form-control" value="{{ old('title', $review->title) }}">@error('title')<div class="text-danger mt-4">{{ $message }}</div>@enderror</div>
            <div class="mb-24">
                <label for="review-images" class="fw-medium mb-8 d-block">Product Images <span class="cl-text-3">(optional, up to 5)</span></label>
                @if($review->exists && $review->images->isNotEmpty())
                    <div class="review-image-grid mb-12" data-existing-review-images>
                        @foreach($review->images as $image)
                            <label class="review-image-existing">
                                <img src="{{ Storage::disk('public')->url($image->thumbnail_path) }}" alt="Review image {{ $loop->iteration }}">
                                <span><input type="checkbox" name="remove_image_ids[]" value="{{ $image->getKey() }}" data-remove-review-image> Remove</span>
                            </label>
                        @endforeach
                    </div>
                @endif
                <input id="review-images" name="images[]" type="file" class="form-control" accept="image/jpeg,image/png,image/webp" multiple data-review-images>
                <p class="text-caption-01 cl-text-3 mt-8 mb-0">JPG, PNG or WebP. Maximum {{ (int) config('images.product_review.max_upload_kb', 5120) / 1024 }} MB per image.</p>
                <div class="review-image-grid mt-12" data-review-image-preview hidden></div>
                <div class="text-danger mt-8" data-review-image-error hidden></div>
                @error('images')<div class="text-danger mt-4">{{ $message }}</div>@enderror
                @error('images.*')<div class="text-danger mt-4">{{ $message }}</div>@enderror
            </div>
            <div class="d-flex gap-3"><button class="tf-btn animate-btn" type="submit">Submit Review</button><a class="tf-btn btn-line" href="{{ route('storefront.account.orders.show', $item->order) }}">Cancel</a></div>
        </form>
    @endcomponent
@endsection

@push('styles')
<style>
.review-product-summary{display:flex;align-items:center;gap:16px;padding:16px;border:1px solid #e5e7eb;border-radius:6px}.review-product-summary img{width:76px;height:76px;object-fit:cover;border-radius:4px}.review-form{max-width:720px}.review-rating{border:0;padding:0}.review-rating legend{font-weight:600;margin-bottom:8px}.review-stars{display:inline-flex;flex-direction:row-reverse;gap:6px}.review-stars input{position:absolute;opacity:0}.review-stars label{font-size:34px;line-height:1;color:#d5d8dc;cursor:pointer}.review-stars input:checked~label,.review-stars label:hover,.review-stars label:hover~label{color:#f5a623}.review-image-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px}.review-image-grid img{width:100%;aspect-ratio:1;object-fit:cover;border-radius:6px;border:1px solid #e5e7eb}.review-image-existing{display:grid;gap:6px;font-size:12px}.review-image-existing:has(input:checked){opacity:.45}.review-image-preview-item{position:relative}.review-image-preview-remove{position:absolute;top:6px;right:6px;display:grid;place-items:center;width:26px;height:26px;padding:0;border:0;border-radius:50%;background:rgba(17,24,39,.86);color:#fff;font-size:18px;line-height:1;cursor:pointer}.review-image-preview-remove:hover,.review-image-preview-remove:focus-visible{background:var(--primary)}@media(max-width:575px){.review-image-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-review-form]');
    if (!form) return;
    const input = form.querySelector('[data-review-images]');
    const preview = form.querySelector('[data-review-image-preview]');
    const error = form.querySelector('[data-review-image-error]');
    const maxImages = Number(form.dataset.maxImages || 5);
    let selectedFiles = [];
    const remainingExisting = () => form.querySelectorAll('[data-remove-review-image]:not(:checked)').length;
    const syncInput = () => {
        const transfer = new DataTransfer();
        selectedFiles.forEach((file) => transfer.items.add(file));
        input.files = transfer.files;
    };
    const render = () => {
        preview.replaceChildren();
        error.hidden = true;
        if (remainingExisting() + selectedFiles.length > maxImages) {
            selectedFiles = [];
            syncInput();
            error.textContent = `A review can have a maximum of ${maxImages} images.`;
            error.hidden = false;
            preview.hidden = true;
            return;
        }
        selectedFiles.forEach((file, index) => {
            const item = document.createElement('div');
            item.className = 'review-image-preview-item';
            const image = document.createElement('img');
            image.alt = 'New review image preview';
            image.src = URL.createObjectURL(file);
            image.addEventListener('load', () => URL.revokeObjectURL(image.src), { once: true });
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'review-image-preview-remove';
            remove.setAttribute('aria-label', `Remove selected image ${index + 1}`);
            remove.title = 'Remove image';
            remove.textContent = '\u00d7';
            remove.addEventListener('click', () => {
                selectedFiles.splice(index, 1);
                syncInput();
                render();
            });
            item.append(image, remove);
            preview.append(item);
        });
        preview.hidden = selectedFiles.length === 0;
    };
    input.addEventListener('change', () => {
        selectedFiles = Array.from(input.files || []);
        render();
    });
    form.querySelectorAll('[data-remove-review-image]').forEach((checkbox) => checkbox.addEventListener('change', render));
});
</script>
@endpush
