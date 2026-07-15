@php
    $imageMaxMb = (int) ceil(config('images.product.max_upload_kb', 3072) / 1024);
    $imageLimits = $imageLimits ?? ['total' => 8, 'has_primary_variant' => false, 'per_variant_value' => 2, 'entire_product' => 2, 'attribute_label' => 'primary variant'];
    $productRoutePrefix = $productRoutePrefix ?? 'admin';
@endphp

<div class="card-body">
    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1">Please correct the highlighted image fields.</div>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $activeImageCount = $product->images->where('status', 'active')->count();
        $activeEntireImageCount = $product->images
            ->where('status', 'active')
            ->filter(fn ($image) => $image->attributeValues->isEmpty())
            ->count();
        $totalRemainingImages = max(0, (int) $imageLimits['total'] - $activeImageCount);
    @endphp

    <form method="POST" action="{{ route($productRoutePrefix.'.products.images.store', $product) }}" enctype="multipart/form-data" class="border rounded p-3 mb-3 js-product-images-upload-form" data-total-remaining="{{ $totalRemainingImages }}">
        @csrf
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-2 mb-3">
            <div>
                <div class="fw-semibold">Upload Images</div>
                <div class="text-muted small">
                    JPG, JPEG, PNG, or WebP. Maximum {{ $imageMaxMb }} MB each.
                    Maximum {{ $imageLimits['total'] }} active images.
                    {{ $totalRemainingImages }} upload slot{{ $totalRemainingImages === 1 ? '' : 's' }} remaining.
                    @if($imageLimits['has_primary_variant'])
                        {{ $imageLimits['entire_product'] }} entire product and {{ $imageLimits['per_variant_value'] }} per {{ $imageLimits['attribute_label'] }} value.
                    @endif
                </div>
                @if($imageAttributeMapping && $imageAttributeValues->isEmpty())
                    <div class="text-warning small">Select {{ $imageAttributeMapping->group?->name }} values in the Attributes tab before mapping images to them.</div>
                @elseif(! $imageAttributeMapping)
                    <div class="text-muted small">No image attribute is configured for this category, so images apply to the entire product.</div>
                @endif
            </div>
            <div class="text-lg-end">
                <button type="submit" class="btn btn-primary">
                    <i class="ph-upload-simple me-2"></i>
                    Upload
                </button>
            </div>
        </div>

        @error('images')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
        @error('images.*')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
        @error('image_groups')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
        @error('image_groups.*')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
        @error('image_groups.*.*')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

        <div class="row g-3">
            @php
                $entireRemainingImages = $imageLimits['has_primary_variant']
                    ? max(0, (int) $imageLimits['entire_product'] - $activeEntireImageCount)
                    : $totalRemainingImages;
                $uploadTargets = collect([[
                    'key' => 'entire',
                    'label' => 'Entire Product'.($imageLimits['has_primary_variant'] ? ' (max '.$imageLimits['entire_product'].')' : ' (max '.$imageLimits['total'].')'),
                    'remaining' => $entireRemainingImages,
                    'input_id' => 'product_images_entire',
                    'name' => 'image_groups[entire][]',
                ]]);

                if ($imageAttributeMapping && $imageAttributeValues->isNotEmpty()) {
                    $uploadTargets = $uploadTargets->merge($imageAttributeValues->map(function ($value) use ($product, $imageAttributeMapping, $imageLimits) {
                        $activeValueImageCount = $product->images
                            ->where('status', 'active')
                            ->filter(fn ($image) => $image->attributeValues->pluck('id')->contains($value->getKey()))
                            ->count();

                        return [
                            'key' => (string) $value->getKey(),
                            'label' => ($imageAttributeMapping->group?->name ?? 'Attribute').': '.$value->name.' (max '.$imageLimits['per_variant_value'].')',
                            'remaining' => max(0, (int) $imageLimits['per_variant_value'] - $activeValueImageCount),
                            'input_id' => 'product_images_attribute_'.$value->getKey(),
                            'name' => 'image_groups['.$value->getKey().'][]',
                        ];
                    }));
                }
            @endphp

            @foreach($uploadTargets as $target)
                <div class="col-lg-4 col-md-6">
                    <div class="card border-dashed p-3 mb-0 h-100 js-product-images-upload-card" data-remaining="{{ $target['remaining'] }}" data-label="{{ $target['label'] }}">
                        <div class="fw-semibold mb-2">{{ $target['label'] }}</div>
                        <div class="text-muted small mb-2">{{ $target['remaining'] }} slot{{ $target['remaining'] === 1 ? '' : 's' }} remaining</div>
                        <div class="js-product-images-preview d-flex flex-wrap gap-2 mb-3">
                            <div class="js-product-images-placeholder rounded bg-light border d-flex align-items-center justify-content-center text-muted" style="width: 96px; height: 96px;">
                                Images
                            </div>
                        </div>
                        <div>
                            <label for="{{ $target['input_id'] }}" class="btn btn-outline-primary btn-sm mb-0">
                                <i class="ph-upload me-1"></i>
                                Choose Images
                            </label>
                            <button type="button" class="btn btn-link btn-sm text-muted js-product-images-clear">
                                Clear
                            </button>
                            <input id="{{ $target['input_id'] }}" name="{{ $target['name'] }}" type="file" class="d-none js-product-images-input" accept="image/jpeg,image/png,image/webp" multiple>
                            <span class="js-product-images-count text-muted small ms-2">No files selected</span>
                        </div>
                        <div class="js-product-images-error text-danger small mt-2 d-none"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </form>

    @if($product->images->isEmpty())
        <div class="text-center py-4">
            <div class="mb-3">
                <i class="ph-images text-muted" style="font-size: 2.5rem;"></i>
            </div>
            <h5 class="mb-1">Images</h5>
            <div class="text-muted">Upload product images and choose one primary image for product cards and lists.</div>
        </div>
    @else
        <form id="bulk-delete-product-images" method="POST" action="{{ route($productRoutePrefix.'.products.images.bulk-destroy', $product) }}" class="d-none">
            @csrf
            @method('DELETE')
        </form>

        <form method="POST" action="{{ route($productRoutePrefix.'.products.images.update', $product) }}">
            @csrf
            @method('PUT')

            <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-sm-between gap-2 mb-3">
                <div class="text-muted small">
                    Select images to permanently delete them from the database and storage.
                </div>
                <button type="button" class="btn btn-danger js-bulk-delete-product-images" disabled>
                    <i class="ph-trash me-2"></i>
                    Delete Selected
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 48px;">
                                <input type="checkbox" class="form-check-input js-product-image-select-all" data-bs-popup="tooltip" title="Select all images">
                            </th>
                            <th style="width: 96px;">Image</th>
                            <th>Details</th>
                            <th>Applies To</th>
                            <th style="width: 120px;">Sort Order</th>
                            <th style="width: 140px;">Status</th>
                            <th class="text-center" style="width: 100px;">Primary</th>
                            <th class="text-center" style="width: 80px;">Delete</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($product->images as $image)
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" name="image_ids[]" value="{{ $image->getKey() }}" class="form-check-input js-product-image-checkbox" form="bulk-delete-product-images">
                                </td>
                                <td>
                                    <img src="{{ asset('storage/'.($image->thumbnail_path ?: $image->image_path)) }}" alt="{{ $image->alt_text ?: $product->product_name }}" class="rounded border" style="width: 72px; height: 72px; object-fit: cover;">
                                </td>
                                <td>
                                    <input type="text" name="images[{{ $image->getKey() }}][title]" value="{{ old("images.{$image->getKey()}.title", $image->title) }}" class="form-control mb-2" placeholder="Title">
                                    <input type="text" name="images[{{ $image->getKey() }}][alt_text]" value="{{ old("images.{$image->getKey()}.alt_text", $image->alt_text) }}" class="form-control" placeholder="Alt text">
                                </td>
                                <td>
                                    @if($image->attributeValues->isEmpty())
                                        <span class="badge bg-light text-body border">Entire Product</span>
                                    @else
                                        @foreach($image->attributeValues as $value)
                                            <span class="badge bg-light text-body border">{{ $imageAttributeMapping?->group?->name ?? $value->group?->name }}: {{ $value->name }}</span>
                                        @endforeach
                                    @endif
                                </td>
                                <td>
                                    <input type="number" name="images[{{ $image->getKey() }}][sort_order]" value="{{ old("images.{$image->getKey()}.sort_order", $image->sort_order) }}" class="form-control" min="0">
                                </td>
                                <td>
                                    <select name="images[{{ $image->getKey() }}][status]" class="form-select">
                                        <option value="active" @selected(old("images.{$image->getKey()}.status", $image->status) === 'active')>Active</option>
                                        <option value="inactive" @selected(old("images.{$image->getKey()}.status", $image->status) === 'inactive')>Inactive</option>
                                    </select>
                                </td>
                                <td class="text-center">
                                    <input type="radio" name="primary_image_id" value="{{ $image->getKey() }}" class="form-check-input" @checked((int) $product->primary_image_id === (int) $image->getKey()) @disabled($image->status !== 'active')>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-link text-danger p-0 js-delete-product-image" data-form-id="delete-product-image-{{ $image->uuid }}" data-bs-popup="tooltip" title="Delete image">
                                        <i class="ph-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="ph-floppy-disk me-2"></i>
                    Save Images
                </button>
            </div>
        </form>

        @foreach($product->images as $image)
            <form id="delete-product-image-{{ $image->uuid }}" method="POST" action="{{ route($productRoutePrefix.'.products.images.destroy', ['product' => $product, 'productImage' => $image]) }}" class="d-none">
                @csrf
                @method('DELETE')
            </form>
        @endforeach
    @endif
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const uploadForm = document.querySelector('.js-product-images-upload-form');
            const selectAll = document.querySelector('.js-product-image-select-all');
            const checkboxes = Array.from(document.querySelectorAll('.js-product-image-checkbox'));
            const bulkDeleteButton = document.querySelector('.js-bulk-delete-product-images');
            const bulkDeleteForm = document.getElementById('bulk-delete-product-images');
            const previewUrlsByInput = new Map();

            const selectedUploadFiles = function (input) {
                return Array.from(input.files || [])
                    .filter(function (file) {
                        return /^image\/(jpeg|jpg|png|webp)$/i.test(file.type);
                    });
            };

            const clearUploadError = function (card) {
                const error = card ? card.querySelector('.js-product-images-error') : null;

                if (!error) {
                    return;
                }

                error.textContent = '';
                error.classList.add('d-none');
            };

            const setUploadError = function (card, message) {
                const error = card ? card.querySelector('.js-product-images-error') : null;

                if (!error) {
                    return;
                }

                error.textContent = message;
                error.classList.remove('d-none');
            };

            const clearUploadSelection = function (card) {
                const input = card ? card.querySelector('.js-product-images-input') : null;
                const uploadPreview = card ? card.querySelector('.js-product-images-preview') : null;
                const uploadPlaceholder = card ? card.querySelector('.js-product-images-placeholder') : null;
                const uploadCount = card ? card.querySelector('.js-product-images-count') : null;

                if (!input || !uploadPreview) {
                    return;
                }

                (previewUrlsByInput.get(input) || []).forEach(function (url) {
                    URL.revokeObjectURL(url);
                });
                previewUrlsByInput.set(input, []);

                input.value = '';

                Array.from(uploadPreview.querySelectorAll('.js-product-upload-preview')).forEach(function (node) {
                    node.remove();
                });

                if (uploadPlaceholder) {
                    uploadPlaceholder.classList.remove('d-none');
                }

                if (uploadCount) {
                    uploadCount.textContent = 'No files selected';
                }

                clearUploadError(card);
            };

            const validateUploadSelection = function () {
                if (!uploadForm) {
                    return true;
                }

                let isValid = true;
                let selectedTotal = 0;
                const totalRemaining = Number(uploadForm.dataset.totalRemaining || 0);

                document.querySelectorAll('.js-product-images-upload-card').forEach(function (card) {
                    clearUploadError(card);

                    const input = card.querySelector('.js-product-images-input');
                    const selectedCount = input ? selectedUploadFiles(input).length : 0;
                    const remaining = Number(card.dataset.remaining || 0);
                    selectedTotal += selectedCount;

                    if (selectedCount > remaining) {
                        isValid = false;
                        setUploadError(card, 'You can upload only ' + remaining + ' more image' + (remaining === 1 ? '' : 's') + ' here.');
                    }
                });

                if (selectedTotal > totalRemaining) {
                    isValid = false;

                    const firstCardWithFiles = Array.from(document.querySelectorAll('.js-product-images-upload-card'))
                        .find(function (card) {
                            const input = card.querySelector('.js-product-images-input');
                            return input && selectedUploadFiles(input).length > 0;
                        });

                    setUploadError(firstCardWithFiles, 'You selected ' + selectedTotal + ' images, but only ' + totalRemaining + ' total upload slot' + (totalRemaining === 1 ? '' : 's') + ' remain.');
                }

                return isValid;
            };

            if (uploadForm) {
                uploadForm.addEventListener('submit', function (event) {
                    if (!validateUploadSelection()) {
                        event.preventDefault();
                    }
                });
            }

            document.querySelectorAll('.js-product-images-input').forEach(function (uploadInput) {
                uploadInput.addEventListener('change', function () {
                    const card = uploadInput.closest('.card');
                    const uploadPreview = card ? card.querySelector('.js-product-images-preview') : null;
                    const uploadPlaceholder = card ? card.querySelector('.js-product-images-placeholder') : null;
                    const uploadCount = card ? card.querySelector('.js-product-images-count') : null;

                    if (!uploadPreview) {
                        return;
                    }

                    (previewUrlsByInput.get(uploadInput) || []).forEach(function (url) {
                        URL.revokeObjectURL(url);
                    });

                    const previewUrls = [];
                    previewUrlsByInput.set(uploadInput, previewUrls);

                    Array.from(uploadPreview.querySelectorAll('.js-product-upload-preview')).forEach(function (node) {
                        node.remove();
                    });

                    clearUploadError(card);

                    const files = selectedUploadFiles(uploadInput);

                    if (uploadPlaceholder) {
                        uploadPlaceholder.classList.toggle('d-none', files.length > 0);
                    }

                    if (uploadCount) {
                        uploadCount.textContent = files.length === 0
                            ? 'No files selected'
                            : files.length + ' file' + (files.length === 1 ? '' : 's') + ' selected';
                    }

                    files.forEach(function (file) {
                        const url = URL.createObjectURL(file);
                        previewUrls.push(url);

                        const wrapper = document.createElement('div');
                        wrapper.className = 'js-product-upload-preview rounded overflow-hidden bg-light border';
                        wrapper.style.width = '96px';
                        wrapper.style.height = '96px';

                        const image = document.createElement('img');
                        image.src = url;
                        image.alt = file.name;
                        image.className = 'img-fluid';
                        image.style.width = '100%';
                        image.style.height = '100%';
                        image.style.objectFit = 'cover';

                        wrapper.appendChild(image);
                        uploadPreview.appendChild(wrapper);
                    });

                    validateUploadSelection();
                });
            });

            document.querySelectorAll('.js-product-images-clear').forEach(function (button) {
                button.addEventListener('click', function () {
                    clearUploadSelection(button.closest('.js-product-images-upload-card'));
                    validateUploadSelection();
                });
            });

            const selectedCount = function () {
                return checkboxes.filter(function (checkbox) {
                    return checkbox.checked;
                }).length;
            };

            const syncBulkControls = function () {
                const count = selectedCount();

                if (bulkDeleteButton) {
                    bulkDeleteButton.disabled = count === 0;
                }

                if (selectAll) {
                    selectAll.checked = checkboxes.length > 0 && count === checkboxes.length;
                    selectAll.indeterminate = count > 0 && count < checkboxes.length;
                }
            };

            if (selectAll) {
                selectAll.addEventListener('change', function () {
                    checkboxes.forEach(function (checkbox) {
                        checkbox.checked = selectAll.checked;
                    });
                    syncBulkControls();
                });
            }

            checkboxes.forEach(function (checkbox) {
                checkbox.addEventListener('change', syncBulkControls);
            });

            if (bulkDeleteButton && bulkDeleteForm) {
                bulkDeleteButton.addEventListener('click', function () {
                    const count = selectedCount();

                    if (count === 0) {
                        return;
                    }

                    bootbox.confirm({
                        title: 'Delete Selected Images',
                        message: 'This will permanently delete ' + count + ' selected image' + (count === 1 ? '' : 's') + ' from the database and storage.',
                        buttons: {
                            cancel: {
                                label: 'Cancel',
                                className: 'btn-link',
                            },
                            confirm: {
                                label: 'Yes, Delete',
                                className: 'btn-danger',
                            },
                        },
                        callback: function (confirmed) {
                            if (confirmed) {
                                bulkDeleteForm.submit();
                            }
                        },
                    });
                });
            }

            document.addEventListener('click', function (event) {
                const button = event.target.closest('.js-delete-product-image');

                if (!button) {
                    return;
                }

                const form = document.getElementById(button.dataset.formId);

                if (!form) {
                    return;
                }

                bootbox.confirm({
                    title: 'Delete Image',
                    message: 'This will permanently delete the image from the database and storage.',
                    buttons: {
                        cancel: {
                            label: 'Cancel',
                            className: 'btn-link',
                        },
                        confirm: {
                            label: 'Yes, Delete',
                            className: 'btn-danger',
                        },
                    },
                    callback: function (confirmed) {
                        if (confirmed) {
                            form.submit();
                        }
                    },
                });
            });

            syncBulkControls();
        });
    </script>
@endpush
