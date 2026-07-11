@php
    $isEdit = $brand !== null;
    $selectedStatus = old('status', $brand?->status ?? 'active');
    $removeLogo = old('remove_logo') && $brand?->logo_path;
    $logoMaxMb = (int) ceil(config('images.brand_logo.max_upload_kb', 5120) / 1024);
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
        <h5 class="mb-0">Brand Information</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-8">
                <label for="name" class="form-label">Brand Name <span class="text-danger">*</span></label>
                <input id="name" name="name" type="text" value="{{ old('name', $brand?->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @if($isEdit)
                    <div class="form-text">Slug: {{ $brand->slug }}</div>
                @endif
            </div>

            <div class="col-md-2">
                <label for="sort_order" class="form-label">Sort Order</label>
                <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $brand?->sort_order ?? 0) }}" class="form-control @error('sort_order') is-invalid @enderror">
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
                <label for="website_url" class="form-label">Website URL</label>
                <input id="website_url" name="website_url" type="url" value="{{ old('website_url', $brand?->website_url) }}" class="form-control @error('website_url') is-invalid @enderror">
                @error('website_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <label for="description" class="form-label">Description</label>
                <textarea id="description" name="description" rows="4" class="form-control @error('description') is-invalid @enderror">{{ old('description', $brand?->description) }}</textarea>
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <label class="form-label d-block">Brand Logo</label>
                <div class="card border-dashed p-3 mb-0">
                    <div class="d-flex flex-column flex-sm-row align-items-start gap-3">
                        <div class="rounded overflow-hidden bg-light border d-flex align-items-center justify-content-center" style="width: 160px; height: 160px;">
                            <img id="brand_logo_preview" src="{{ $brand?->logo_path && ! $removeLogo ? asset('storage/'.$brand->logo_path) : '' }}" data-current-src="{{ $brand?->logo_path ? asset('storage/'.$brand->logo_path) : '' }}" alt="Brand logo" class="img-fluid {{ $brand?->logo_path && ! $removeLogo ? '' : 'd-none' }}" style="width: 100%; height: 100%; object-fit: cover;">
                            <div id="brand_logo_placeholder" class="text-muted {{ $brand?->logo_path && ! $removeLogo ? 'd-none' : '' }}">{{ $removeLogo ? 'Will remove' : 'Logo' }}</div>
                        </div>
                        <div class="flex-fill">
                            <label for="logo" class="btn btn-outline-primary btn-sm">
                                <i class="ph-upload me-1"></i>
                                {{ $brand?->logo_path ? 'Change Logo' : 'Choose Logo' }}
                            </label>
                            <input id="logo" name="logo" type="file" accept=".jpg,.jpeg,.png,.webp" class="d-none @error('logo') is-invalid @enderror">
                            <p class="text-muted mb-1 mt-2">JPG, JPEG, PNG or WEBP. Max {{ $logoMaxMb }}MB.</p>
                            @if($brand?->logo_path)<div class="text-muted small text-break">Current: {{ $brand->logo_path }}</div>@endif
                            @if($brand?->logo_path)
                                <div class="form-check mt-2">
                                    <input id="remove_logo" name="remove_logo" type="checkbox" value="1" class="form-check-input @error('remove_logo') is-invalid @enderror" @checked($removeLogo)>
                                    <label for="remove_logo" class="form-check-label">Remove current logo</label>
                                </div>
                                @error('remove_logo')<div class="text-danger small">{{ $message }}</div>@enderror
                            @endif
                            @error('logo')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<x-form-buttons
    :submit="$isEdit ? 'Update Brand' : 'Create Brand'"
    :cancel="route('admin.master.brands.index')"
    cancel-label="Cancel"
/>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const input = document.getElementById('logo');
            const preview = document.getElementById('brand_logo_preview');
            const placeholder = document.getElementById('brand_logo_placeholder');
            const remove = document.getElementById('remove_logo');

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
                    placeholder.textContent = 'Logo';
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
                                placeholder.textContent = 'Logo';
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
