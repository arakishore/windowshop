@php
    $isEdit = $testimonial !== null;
    $selectedType = old('type', $testimonial?->type ?? 'customer');
    $selectedStatus = old('status', $testimonial?->status ?? 'active');
    $removePhoto = old('remove_photo') && $testimonial?->photo_path;
    $photoMaxMb = (int) ceil(config('images.testimonial.max_upload_kb', 5120) / 1024);
@endphp

@if ($errors->any())
    <div class="alert alert-danger">
        <div class="fw-semibold mb-1">Please correct the highlighted fields.</div>
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Testimonial Information</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="type" class="form-label">Type <span class="text-danger">*</span></label>
                <select id="type" name="type" class="form-select @error('type') is-invalid @enderror" required>
                    <option value="customer" @selected($selectedType === 'customer')>Customer Say</option>
                    <option value="merchant" @selected($selectedType === 'merchant')>Merchant Say</option>
                </select>
                @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                <input id="name" name="name" type="text" maxlength="150" value="{{ old('name', $testimonial?->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2">
                <label for="sort_order" class="form-label">Sort Order</label>
                <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $testimonial?->sort_order ?? 0) }}" class="form-control @error('sort_order') is-invalid @enderror">
                @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2">
                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                    <option value="active" @selected($selectedStatus === 'active')>Active</option>
                    <option value="inactive" @selected($selectedStatus === 'inactive')>Inactive</option>
                </select>
                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <label for="body" class="form-label">Testimonial <span class="text-danger">*</span></label>
                <textarea id="body" name="body" rows="4" maxlength="2000" class="form-control @error('body') is-invalid @enderror" required>{{ old('body', $testimonial?->body) }}</textarea>
                @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
                <label for="location" class="form-label">Location</label>
                <input id="location" name="location" type="text" maxlength="150" value="{{ old('location', $testimonial?->location) }}" class="form-control @error('location') is-invalid @enderror" placeholder="Nashik">
                @error('location')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6" data-testimonial-rating>
                <label for="rating" class="form-label">Customer Rating</label>
                <select id="rating" name="rating" class="form-select @error('rating') is-invalid @enderror">
                    <option value="">No rating</option>
                    @for ($stars = 1; $stars <= 5; $stars++)
                        <option value="{{ $stars }}" @selected((string) old('rating', $testimonial?->rating ?? '') === (string) $stars)>{{ $stars }} out of 5</option>
                    @endfor
                </select>
                @error('rating')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6" data-testimonial-merchant>
                <label for="business_name" class="form-label">Business / Shop Name</label>
                <input id="business_name" name="business_name" type="text" maxlength="191" value="{{ old('business_name', $testimonial?->business_name) }}" class="form-control @error('business_name') is-invalid @enderror">
                @error('business_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6" data-testimonial-merchant>
                <label for="designation" class="form-label">Designation</label>
                <input id="designation" name="designation" type="text" maxlength="150" value="{{ old('designation', $testimonial?->designation) }}" class="form-control @error('designation') is-invalid @enderror" placeholder="Owner">
                @error('designation')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <label class="form-label d-block">Photo</label>
                <div class="card border-dashed p-3 mb-0">
                    <div class="d-flex flex-column flex-sm-row align-items-start gap-3">
                        <div class="rounded-circle overflow-hidden bg-light border d-flex align-items-center justify-content-center" style="width: 120px; height: 120px; flex: 0 0 auto;">
                            <img id="testimonial_photo_preview" src="{{ $testimonial?->photo_path && ! $removePhoto ? asset('storage/'.$testimonial->photo_path) : '' }}" data-current-src="{{ $testimonial?->photo_path ? asset('storage/'.$testimonial->photo_path) : '' }}" alt="Testimonial photo" class="img-fluid {{ $testimonial?->photo_path && ! $removePhoto ? '' : 'd-none' }}" style="width: 100%; height: 100%; object-fit: cover;">
                            <div id="testimonial_photo_placeholder" class="text-muted {{ $testimonial?->photo_path && ! $removePhoto ? 'd-none' : '' }}">{{ $removePhoto ? 'Will remove' : 'Photo' }}</div>
                        </div>
                        <div class="flex-fill">
                            <label for="photo" class="btn btn-outline-primary btn-sm">
                                <i class="ph-upload me-1"></i>
                                {{ $testimonial?->photo_path ? 'Change Photo' : 'Choose Photo' }}
                            </label>
                            <button type="button" id="clear_photo" class="btn btn-link btn-sm text-muted">
                                Clear
                            </button>
                            <input id="photo" name="photo" type="file" accept=".jpg,.jpeg,.png,.webp" class="d-none @error('photo') is-invalid @enderror">
                            <p class="text-muted mb-1 mt-2">JPG, JPEG, PNG or WEBP. Max {{ $photoMaxMb }}MB. Optional.</p>
                            @if($testimonial?->photo_path)<div class="text-muted small text-break">Current: {{ $testimonial->photo_path }}</div>@endif
                            @if($testimonial?->photo_path)
                                <div class="form-check mt-2">
                                    <input id="remove_photo" name="remove_photo" type="checkbox" value="1" class="form-check-input @error('remove_photo') is-invalid @enderror" @checked($removePhoto)>
                                    <label for="remove_photo" class="form-check-label">Remove current photo</label>
                                </div>
                                @error('remove_photo')<div class="text-danger small">{{ $message }}</div>@enderror
                            @endif
                            @error('photo')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<x-form-buttons
    :submit="$isEdit ? 'Update Testimonial' : 'Create Testimonial'"
    :cancel="route('admin.testimonials.index')"
    cancel-label="Cancel"
/>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const type = document.getElementById('type');
            const rating = document.querySelector('[data-testimonial-rating]');
            const merchantFields = document.querySelectorAll('[data-testimonial-merchant]');

            const syncTypeFields = function () {
                const isMerchant = type && type.value === 'merchant';

                if (rating) {
                    rating.classList.toggle('d-none', isMerchant);
                }

                merchantFields.forEach(function (field) {
                    field.classList.toggle('d-none', !isMerchant);
                });
            };

            if (type) {
                type.addEventListener('change', syncTypeFields);
                syncTypeFields();
            }

            const input = document.getElementById('photo');
            const preview = document.getElementById('testimonial_photo_preview');
            const placeholder = document.getElementById('testimonial_photo_placeholder');
            const remove = document.getElementById('remove_photo');
            const clear = document.getElementById('clear_photo');
            let objectUrl = null;

            if (!input || !preview) {
                return;
            }

            input.addEventListener('change', function () {
                const file = input.files && input.files[0];

                if (!file || !/^image\/(jpeg|jpg|png|webp)$/i.test(file.type)) {
                    return;
                }

                if (remove) {
                    remove.checked = false;
                }

                if (objectUrl) {
                    URL.revokeObjectURL(objectUrl);
                }

                objectUrl = URL.createObjectURL(file);
                preview.src = objectUrl;
                preview.classList.remove('d-none');

                if (placeholder) {
                    placeholder.textContent = 'Photo';
                    placeholder.classList.add('d-none');
                }
            });

            if (remove) {
                remove.addEventListener('change', function () {
                    if (!remove.checked) {
                        const currentSrc = preview.dataset.currentSrc;

                        if (currentSrc) {
                            preview.src = currentSrc;
                            preview.classList.remove('d-none');

                            if (placeholder) {
                                placeholder.textContent = 'Photo';
                                placeholder.classList.add('d-none');
                            }
                        }

                        return;
                    }

                    input.value = '';
                    preview.removeAttribute('src');
                    preview.classList.add('d-none');

                    if (placeholder) {
                        placeholder.textContent = 'Will remove';
                        placeholder.classList.remove('d-none');
                    }
                });
            }

            if (clear) {
                clear.addEventListener('click', function () {
                    if (objectUrl) {
                        URL.revokeObjectURL(objectUrl);
                        objectUrl = null;
                    }

                    input.value = '';

                    if (remove) {
                        remove.checked = false;
                    }

                    const currentSrc = preview.dataset.currentSrc;

                    if (currentSrc) {
                        preview.src = currentSrc;
                        preview.classList.remove('d-none');

                        if (placeholder) {
                            placeholder.textContent = 'Photo';
                            placeholder.classList.add('d-none');
                        }

                        return;
                    }

                    preview.removeAttribute('src');
                    preview.classList.add('d-none');

                    if (placeholder) {
                        placeholder.textContent = 'Photo';
                        placeholder.classList.remove('d-none');
                    }
                });
            }
        });
    </script>
@endpush
