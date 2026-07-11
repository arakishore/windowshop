@php
    $isEdit = $category !== null;
    $selectedStatus = old('status', $category?->status ?? 'active');
    $removeImage = old('remove_image') && $category?->image_path;
    $imageMaxMb = (int) ceil(config('images.shop_category.max_upload_kb', 4096) / 1024);
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
        <h5 class="mb-0">Category Information</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-8">
                <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                <input id="name" name="name" type="text" value="{{ old('name', $category?->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @if($isEdit)
                    <div class="form-text">Slug: {{ $category->slug }}</div>
                @endif
            </div>

            <div class="col-md-2">
                <label for="sort_order" class="form-label">Sort Order</label>
                <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $category?->sort_order ?? 0) }}" class="form-control @error('sort_order') is-invalid @enderror">
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
                <label for="description" class="form-label">Description</label>
                <textarea id="description" name="description" rows="4" class="form-control @error('description') is-invalid @enderror">{{ old('description', $category?->description) }}</textarea>
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <label class="form-label d-block">Image</label>
                <div class="card border-dashed p-3 mb-0">
                    <div class="d-flex flex-column flex-sm-row align-items-start gap-3">
                        <div class="rounded overflow-hidden bg-light border d-flex align-items-center justify-content-center" style="width: 160px; height: 160px;">
                            <img id="category_image_preview" src="{{ $category?->image_path && ! $removeImage ? asset('storage/'.$category->image_path) : '' }}" data-current-src="{{ $category?->image_path ? asset('storage/'.$category->image_path) : '' }}" alt="Category image" class="img-fluid {{ $category?->image_path && ! $removeImage ? '' : 'd-none' }}" style="width: 100%; height: 100%; object-fit: cover;">
                            <div id="category_image_placeholder" class="text-muted {{ $category?->image_path && ! $removeImage ? 'd-none' : '' }}">{{ $removeImage ? 'Will remove' : 'Image' }}</div>
                        </div>
                        <div class="flex-fill">
                            <label for="image" class="btn btn-outline-primary btn-sm">
                                <i class="ph-upload me-1"></i>
                                Choose image
                            </label>
                            <input id="image" name="image" type="file" accept=".jpg,.jpeg,.png,.webp" class="d-none @error('image') is-invalid @enderror">
                            <p class="text-muted mb-1 mt-2">JPG, JPEG, PNG or WEBP. Max {{ $imageMaxMb }}MB.</p>
                            @if($category?->image_path)<div class="text-muted small text-break">Current: {{ $category->image_path }}</div>@endif
                            @if($category?->image_path)
                                <div class="form-check mt-2">
                                    <input id="remove_image" name="remove_image" type="checkbox" value="1" class="form-check-input @error('remove_image') is-invalid @enderror" @checked($removeImage)>
                                    <label for="remove_image" class="form-check-label">Remove current image</label>
                                </div>
                                @error('remove_image')<div class="text-danger small">{{ $message }}</div>@enderror
                            @endif
                            @error('image')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<x-form-buttons
    :submit="$isEdit ? 'Update Category' : 'Create Category'"
    :cancel="route('admin.master.shop-categories.index')"
    cancel-label="Cancel"
/>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const input = document.getElementById('image');
            const preview = document.getElementById('category_image_preview');
            const placeholder = document.getElementById('category_image_placeholder');
            const remove = document.getElementById('remove_image');

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

                preview.src = URL.createObjectURL(file);
                preview.classList.remove('d-none');

                if (placeholder) {
                    placeholder.textContent = 'Image';
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
                                placeholder.textContent = 'Image';
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
        });
    </script>
@endpush
