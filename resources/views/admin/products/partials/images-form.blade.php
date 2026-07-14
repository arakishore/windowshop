@php
    $imageMaxMb = (int) ceil(config('images.product.max_upload_kb', 3072) / 1024);
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

    <form method="POST" action="{{ route('admin.products.images.store', $product) }}" enctype="multipart/form-data" class="border rounded p-3 mb-3">
        @csrf
        <div class="row g-3 align-items-end">
            <div class="col-md-5">
                <label for="product_images" class="form-label">Upload Images</label>
                <input id="product_images" name="images[]" type="file" class="form-control @error('images') is-invalid @enderror @error('images.*') is-invalid @enderror" accept="image/jpeg,image/png,image/webp" multiple required>
                <div class="form-text">JPG, JPEG, PNG, or WebP. Maximum {{ $imageMaxMb }} MB each. Maximum 8 active images.</div>
            </div>

            <div class="col-md-5">
                <div class="form-label">Applies To</div>
                <div class="d-flex flex-wrap gap-3">
                    <label class="form-check mb-0">
                        <input type="radio" name="attribute_value_id" value="" class="form-check-input" checked>
                        <span class="form-check-label">Entire Product</span>
                    </label>

                    @if($imageAttributeMapping && $imageAttributeValues->isNotEmpty())
                        @foreach($imageAttributeValues as $value)
                            <label class="form-check mb-0">
                                <input type="radio" name="attribute_value_id" value="{{ $value->getKey() }}" class="form-check-input">
                                <span class="form-check-label">{{ $imageAttributeMapping->group?->name }}: {{ $value->name }}</span>
                            </label>
                        @endforeach
                    @endif
                </div>
                @if($imageAttributeMapping && $imageAttributeValues->isEmpty())
                    <div class="form-text text-warning">Select {{ $imageAttributeMapping->group?->name }} values in the Attributes tab before mapping images to them.</div>
                @elseif(! $imageAttributeMapping)
                    <div class="form-text">No image attribute is configured for this category, so images apply to the entire product.</div>
                @endif
            </div>

            <div class="col-md-2 text-md-end">
                <button type="submit" class="btn btn-primary">
                    <i class="ph-upload-simple me-2"></i>
                    Upload
                </button>
            </div>
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
        <form method="POST" action="{{ route('admin.products.images.update', $product) }}">
            @csrf
            @method('PUT')

            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
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
                                    <button type="submit" form="delete-product-image-{{ $image->uuid }}" class="btn btn-link text-danger p-0" data-bs-popup="tooltip" title="Delete image">
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
            <form id="delete-product-image-{{ $image->uuid }}" method="POST" action="{{ route('admin.products.images.destroy', ['product' => $product, 'productImage' => $image]) }}" class="d-none">
                @csrf
                @method('DELETE')
            </form>
        @endforeach
    @endif
</div>
