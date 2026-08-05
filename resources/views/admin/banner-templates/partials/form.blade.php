@php
    $isEdit = $template->exists;
    $selectedCategory = old('category', $template->category instanceof \App\Enums\BannerTemplateCategory ? $template->category->value : $template->category);
    $selectedAvailability = old('availability', $template->availability instanceof \App\Enums\BannerTemplateAvailability ? $template->availability->value : $template->availability);
    $selectedPosition = old('default_position', $template->default_position ?? \App\Enums\BannerPosition::STORE_HERO->value);
    $selectedStatus = old('status', $template->status ?? \App\Models\BannerTemplate::STATUS_ACTIVE);
    $removeMobile = old('remove_mobile_image') && $template->mobile_image_path;
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
    <div class="card-header d-flex flex-column flex-md-row align-items-md-center justify-content-md-between gap-2">
        <h5 class="mb-0">Banner Template Information</h5>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-light border btn-sm" data-template-accordion="open">
                <i class="ph-arrows-out-simple me-1"></i>
                Open All
            </button>
            <button type="button" class="btn btn-light border btn-sm" data-template-accordion="collapse">
                <i class="ph-arrows-in-simple me-1"></i>
                Collapse All
            </button>
        </div>
    </div>

    <div class="accordion accordion-flush" id="banner-template-form">
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#template-details">Template Details</button>
            </h2>
            <div id="template-details" class="accordion-collapse collapse show">
            <div class="accordion-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
                        <input id="code" name="code" type="text" value="{{ old('code', $template->code) }}" class="form-control @error('code') is-invalid @enderror" required placeholder="general_store_hero">
                        <div class="form-text">Lowercase letters, numbers, underscores, and hyphens.</div>
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                        <input id="name" name="name" type="text" value="{{ old('name', $template->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2">
                        <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                        <select id="category" name="category" class="form-select @error('category') is-invalid @enderror" required>
                            @foreach($categories as $value => $label)
                                <option value="{{ $value }}" @selected($selectedCategory === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                            @foreach($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="availability" class="form-label">Available For <span class="text-danger">*</span></label>
                        <select id="availability" name="availability" class="form-select @error('availability') is-invalid @enderror" required>
                            @foreach($availabilities as $value => $label)
                                <option value="{{ $value }}" @selected($selectedAvailability === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('availability')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-8">
                        <label for="description" class="form-label">Description</label>
                        <textarea id="description" name="description" rows="2" class="form-control @error('description') is-invalid @enderror">{{ old('description', $template->description) }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#default-content">Default Content</button>
            </h2>
            <div id="default-content" class="accordion-collapse collapse">
            <div class="accordion-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="default_title" class="form-label">Default Title <span class="text-danger">*</span></label>
                        <input id="default_title" name="default_title" type="text" value="{{ old('default_title', $template->default_title) }}" class="form-control @error('default_title') is-invalid @enderror" required>
                        @error('default_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-5">
                        <label for="default_subtitle" class="form-label">Default Subtitle</label>
                        <input id="default_subtitle" name="default_subtitle" type="text" value="{{ old('default_subtitle', $template->default_subtitle) }}" class="form-control @error('default_subtitle') is-invalid @enderror">
                        @error('default_subtitle')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label for="default_button_text" class="form-label">Default Button Text</label>
                        <input id="default_button_text" name="default_button_text" type="text" value="{{ old('default_button_text', $template->default_button_text) }}" class="form-control @error('default_button_text') is-invalid @enderror">
                        @error('default_button_text')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#template-images">Images</button>
            </h2>
            <div id="template-images" class="accordion-collapse collapse">
            <div class="accordion-body">
                <div class="row g-3">
                    <div class="col-lg-6">
                        <label for="desktop_image" class="form-label">Desktop Image @unless($isEdit)<span class="text-danger">*</span>@endunless</label>
                        <input id="desktop_image" name="desktop_image" type="file" accept=".jpg,.jpeg,.png,.webp" class="form-control @error('desktop_image') is-invalid @enderror" @required(! $isEdit)>
                        <div class="form-text">Recommended: <span data-template-dimension="desktop">1600 x 500</span>. JPG, PNG, or WEBP. Max 5MB.</div>
                        @error('desktop_image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-lg-6">
                        <label for="mobile_image" class="form-label">Mobile Image</label>
                        <input id="mobile_image" name="mobile_image" type="file" accept=".jpg,.jpeg,.png,.webp" class="form-control @error('mobile_image') is-invalid @enderror">
                        <div class="form-text">Recommended: <span data-template-dimension="mobile">800 x 900</span>. JPG, PNG, or WEBP. Max 5MB.</div>
                        @error('mobile_image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @if($template->mobile_image_path)
                            <div class="form-check mt-2">
                                <input id="remove_mobile_image" name="remove_mobile_image" type="checkbox" value="1" class="form-check-input" @checked($removeMobile)>
                                <label for="remove_mobile_image" class="form-check-label">Remove current mobile image</label>
                            </div>
                        @endif
                    </div>
                    <div class="col-lg-8">
                        <div class="fw-semibold mb-2">Desktop Preview</div>
                        <div class="position-relative overflow-hidden rounded border bg-light" style="aspect-ratio: 16 / 5;">
                            <img id="template_desktop_preview" src="{{ $template->desktop_image_path ? asset('storage/'.$template->desktop_image_path) : '' }}" alt="Desktop preview" class="w-100 h-100 {{ $template->desktop_image_path ? '' : 'd-none' }}" style="object-fit: cover;">
                            <div id="template_desktop_placeholder" class="position-absolute top-50 start-50 translate-middle text-muted {{ $template->desktop_image_path ? 'd-none' : '' }}">Desktop Preview</div>
                            <div class="position-absolute bottom-0 start-0 end-0 p-3 text-white" style="background: linear-gradient(180deg, transparent, rgba(0,0,0,.55));">
                                <div id="preview_title" class="fw-bold fs-4">{{ old('default_title', $template->default_title) ?: 'Banner Title' }}</div>
                                <div id="preview_subtitle" class="small">{{ old('default_subtitle', $template->default_subtitle) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="fw-semibold mb-2">Mobile Preview</div>
                        <div class="position-relative overflow-hidden rounded border bg-light mx-auto" style="max-width: 220px; aspect-ratio: 4 / 5;">
                            <img id="template_mobile_preview" src="{{ $template->mobile_image_path && ! $removeMobile ? asset('storage/'.$template->mobile_image_path) : ($template->desktop_image_path ? asset('storage/'.$template->desktop_image_path) : '') }}" alt="Mobile preview" class="w-100 h-100 {{ ($template->mobile_image_path && ! $removeMobile) || $template->desktop_image_path ? '' : 'd-none' }}" style="object-fit: cover;">
                            <div id="template_mobile_placeholder" class="position-absolute top-50 start-50 translate-middle text-muted {{ ($template->mobile_image_path && ! $removeMobile) || $template->desktop_image_path ? 'd-none' : '' }}">Mobile Preview</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#display-defaults">Display Defaults</button>
            </h2>
            <div id="display-defaults" class="accordion-collapse collapse">
            <div class="accordion-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="default_position" class="form-label">Default Position <span class="text-danger">*</span></label>
                        <select id="default_position" name="default_position" class="form-select @error('default_position') is-invalid @enderror" required>
                            @foreach($positions as $value => $label)
                                <option value="{{ $value }}" @selected($selectedPosition === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <div id="template_position_description" class="form-text"></div>
                        @error('default_position')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="sort_order" class="form-label">Sort Order <span class="text-danger">*</span></label>
                        <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $template->sort_order ?? 0) }}" class="form-control @error('sort_order') is-invalid @enderror" required>
                        @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#event-defaults">Event Scheduling Defaults</button>
            </h2>
            <div id="event-defaults" class="accordion-collapse collapse">
            <div class="accordion-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="event_code" class="form-label">Event Code</label>
                        <input id="event_code" name="event_code" type="text" value="{{ old('event_code', $template->event_code) }}" class="form-control @error('event_code') is-invalid @enderror" placeholder="diwali">
                        <div class="form-text">Examples: diwali, holi, new_year, independence_day.</div>
                        @error('event_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="start_offset_days" class="form-label">Start Offset Days</label>
                        <input id="start_offset_days" name="start_offset_days" type="number" min="-365" max="365" value="{{ old('start_offset_days', $template->start_offset_days) }}" class="form-control @error('start_offset_days') is-invalid @enderror" placeholder="-10">
                        @error('start_offset_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label for="end_offset_days" class="form-label">End Offset Days</label>
                        <input id="end_offset_days" name="end_offset_days" type="number" min="-365" max="365" value="{{ old('end_offset_days', $template->end_offset_days) }}" class="form-control @error('end_offset_days') is-invalid @enderror" placeholder="2">
                        <div class="form-text">Offsets may be saved without an event code for later campaign-calendar use.</div>
                        @error('end_offset_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>
</div>

<x-form-buttons
    :submit="$isEdit ? 'Update Template' : 'Create Template'"
    :cancel="route('admin.banner-templates.index')"
    cancel-label="Cancel"
/>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const positionMeta = @json($positionMeta);
            const positionSelect = document.getElementById('default_position');
            const description = document.getElementById('template_position_description');
            const desktopDimension = document.querySelector('[data-template-dimension="desktop"]');
            const mobileDimension = document.querySelector('[data-template-dimension="mobile"]');
            const title = document.getElementById('default_title');
            const subtitle = document.getElementById('default_subtitle');
            const previewTitle = document.getElementById('preview_title');
            const previewSubtitle = document.getElementById('preview_subtitle');
            const accordion = document.getElementById('banner-template-form');
            const openAll = document.querySelector('[data-template-accordion="open"]');
            const collapseAll = document.querySelector('[data-template-accordion="collapse"]');

            function updatePositionMeta() {
                const meta = positionMeta[positionSelect.value] || {};
                const dimensions = meta.dimensions || {};

                description.textContent = meta.description || '';
                desktopDimension.textContent = dimensions.desktop || '1600 x 500';
                mobileDimension.textContent = dimensions.mobile || '800 x 900';
            }

            function bindImagePreview(inputId, previewId, placeholderId, fallbackPreviewId) {
                const input = document.getElementById(inputId);
                const preview = document.getElementById(previewId);
                const placeholder = document.getElementById(placeholderId);
                const fallbackPreview = fallbackPreviewId ? document.getElementById(fallbackPreviewId) : null;
                let objectUrl = null;

                if (!input || !preview) {
                    return;
                }

                input.addEventListener('change', function () {
                    const file = input.files && input.files[0];

                    if (!file || !/^image\/(jpeg|jpg|png|webp)$/i.test(file.type)) {
                        return;
                    }

                    if (objectUrl) {
                        URL.revokeObjectURL(objectUrl);
                    }

                    objectUrl = URL.createObjectURL(file);
                    preview.src = objectUrl;
                    preview.classList.remove('d-none');

                    if (placeholder) {
                        placeholder.classList.add('d-none');
                    }

                    if (fallbackPreview) {
                        fallbackPreview.src = objectUrl;
                        fallbackPreview.classList.remove('d-none');
                    }
                });
            }

            function updatePreviewCopy() {
                previewTitle.textContent = title.value || 'Banner Title';
                previewSubtitle.textContent = subtitle.value || '';
            }

            bindImagePreview('desktop_image', 'template_desktop_preview', 'template_desktop_placeholder', 'template_mobile_preview');
            bindImagePreview('mobile_image', 'template_mobile_preview', 'template_mobile_placeholder');

            if (accordion && window.bootstrap) {
                openAll.addEventListener('click', function () {
                    accordion.querySelectorAll('.accordion-collapse').forEach(function (panel) {
                        bootstrap.Collapse.getOrCreateInstance(panel, { toggle: false }).show();
                    });
                });

                collapseAll.addEventListener('click', function () {
                    accordion.querySelectorAll('.accordion-collapse').forEach(function (panel) {
                        bootstrap.Collapse.getOrCreateInstance(panel, { toggle: false }).hide();
                    });
                });
            }

            positionSelect.addEventListener('change', updatePositionMeta);
            title.addEventListener('input', updatePreviewCopy);
            subtitle.addEventListener('input', updatePreviewCopy);

            updatePositionMeta();
            updatePreviewCopy();
        });
    </script>
@endpush
