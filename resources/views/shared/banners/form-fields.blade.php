@php
    $selectedLinkValue = old('link_value', $banner->link_value);
    $openInNewTab = old('open_in_new_tab', $banner->open_in_new_tab ?? false);
    $sourceType = old('source_type', $banner->source_type instanceof \App\Enums\BannerSourceType ? $banner->source_type->value : ($banner->source_type ?: 'custom_upload'));
    $selectedTemplateUuid = old('banner_template_uuid');
    if (!$selectedTemplateUuid && $banner->banner_template_id && isset($bannerTemplates)) {
        $selectedTemplateUuid = optional($bannerTemplates->firstWhere('id', $banner->banner_template_id))->uuid;
    }
    $desktopSrc = $banner->desktop_image_path ? asset('storage/'.$banner->desktop_image_path) : '';
    $mobileSrc = $banner->mobile_image_path ? asset('storage/'.$banner->mobile_image_path) : '';
    $scheduleStatus = 'Active Now';

    if (old('status', $banner->status) !== 'active') {
        $scheduleStatus = 'Inactive';
    } elseif ($banner->starts_at && $banner->starts_at->isFuture()) {
        $scheduleStatus = 'Scheduled';
    } elseif ($banner->ends_at && $banner->ends_at->isPast()) {
        $scheduleStatus = 'Expired';
    }
@endphp
@php
    $titleSuggestions = [
        'Popular' => ["Today's Deals", 'Trending Now', 'Just Launched', 'Top Picks', 'Shop Local, Save More', 'Everything You Need'],
        'Offers' => ['Up to 50% OFF', 'Flat ₹500 OFF', 'Buy More, Save More', 'Limited Time Offer', 'Mega Savings', 'Big Deals Await', 'Special Offers', 'Hot Deals', "Today's Deals", 'Weekend Sale'],
        'Products' => ['New Arrivals', 'Best Sellers', 'Trending Now', 'Premium Collection', 'Latest Collection', 'Fresh Picks', 'Top Picks', "Editor's Choice", 'Just Launched', 'Must Have Products'],
        'Festival' => ['Diwali Sale', 'Holi Offers', 'Christmas Sale', 'New Year Sale', 'Independence Day Sale', 'Republic Day Sale', 'Eid Special', 'Raksha Bandhan Offers', 'Navratri Collection', 'Ganesh Festival Sale', 'Wedding Season', "Valentine's Special", "Mother's Day Gifts", "Father's Day Specials", 'Anniversary Sale'],
        'Seasonal' => ['Summer Collection', 'Winter Collection', 'Monsoon Sale', 'Spring Collection', 'Back to School', 'End of Season Sale', 'Holiday Specials', 'Vacation Essentials', 'Rainy Day Deals', 'Sunny Savings'],
        'Services' => ['Free Delivery', 'Same Day Delivery', 'Easy Returns', 'Secure Payments', 'Trusted Local Stores', 'Cash on Delivery', 'Fast Checkout', '24/7 Customer Support', 'Quality Guaranteed', 'Safe Shopping'],
        'General' => ['Shop the Latest Trends', 'Exclusive Online Deals', 'Fresh Every Day', 'Quality You Can Trust', 'Great Value Everyday', 'Handpicked for You', 'Discover Something New', 'Your Favourite Store'],
    ];
    $subtitleSuggestions = [
        'Popular' => ['Great products. Great prices.', 'Trusted by thousands of happy customers.', 'Everything you need in one place.', 'Premium quality at affordable prices.', "Don't miss these exclusive deals.", 'Offers ending soon.'],
        'Fresh' => ["Discover styles you'll love.", 'Fresh arrivals added every week.', 'New collections now in store.', 'Explore our newest arrivals.', 'Your next favourite product is here.', 'Fresh products every day.'],
        'Deals' => ['Limited stock available. Shop today!', 'Exclusive offers for a limited time.', 'Save big on selected products.', 'Shop top brands at amazing prices.', 'Limited period savings.', 'Shop smarter and save more.'],
        'Trust' => ['Quality products at unbeatable prices.', 'Handpicked collections just for you.', 'Premium quality at affordable prices.', 'Trusted by thousands of happy customers.', 'Enjoy hassle-free shopping.', 'Quality you can trust every day.'],
        'Lifestyle' => ['Upgrade your wardrobe today.', 'Perfect picks for every occasion.', 'Upgrade your lifestyle today.', 'Exclusive collections now available.', 'Celebrate with exciting offers.', 'Find your favourites today.'],
        'Service' => ['Fast delivery and easy returns.', 'Everything you need in one place.', 'Amazing prices on everyday essentials.', 'Discover the best value today.', 'Enjoy hassle-free shopping.', 'Fresh products every day.'],
    ];
    $buttonSuggestions = [
        'Popular' => ['Shop Now', 'Explore', 'View Offers', 'Start Shopping', 'Grab the Deal', 'Shop Today'],
        'Shopping' => ['Shop Now', 'Buy Now', 'Order Now', 'Browse Now', 'Start Shopping', 'Shop Today', 'Get Yours'],
        'Browse' => ['Explore', 'Explore More', 'Discover', 'View Collection', 'View Products', 'See More'],
        'Deals' => ['View Offers', 'Grab the Deal', 'Save Now', "Don't Miss Out"],
        'Details' => ['Learn More', 'View Details', 'Find Out More'],
    ];
@endphp

<div class="col-12">
    <div class="row g-3 align-items-start">
        <div class="col-xl-8">
            <div class="accordion accordion-flush" id="banner_form_sections">
                <div class="accordion-item">
                    <h2 class="accordion-header" id="banner_details_heading">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#banner_details_section" aria-expanded="true" aria-controls="banner_details_section">
                            Banner Details
                        </button>
                    </h2>
                    <div id="banner_details_section" class="accordion-collapse collapse show" aria-labelledby="banner_details_heading">
                        <div class="accordion-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label d-block">Banner Source</label>
                                    <div class="d-flex flex-wrap gap-3">
                                        <label class="form-check">
                                            <input type="radio" name="source_type" value="template" class="form-check-input js-banner-source" @checked($sourceType === 'template')>
                                            <span class="form-check-label">Use WindowShop Template</span>
                                        </label>
                                        <label class="form-check">
                                            <input type="radio" name="source_type" value="custom_upload" class="form-check-input js-banner-source" @checked($sourceType !== 'template')>
                                            <span class="form-check-label">Upload Custom Banner</span>
                                        </label>
                                    </div>
                                    @error('source_type')<div class="text-danger small">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12 js-template-source-panel">
                                    <label class="form-label" for="banner_template_uuid">Template</label>
                                    <select id="banner_template_uuid" name="banner_template_uuid" class="form-select @error('banner_template_uuid') is-invalid @enderror">
                                        <option value="">Select template</option>
                                        @foreach(($bannerTemplates ?? collect()) as $template)
                                            <option
                                                value="{{ $template->uuid }}"
                                                data-position="{{ $template->default_position }}"
                                                data-title="{{ $template->default_title }}"
                                                data-subtitle="{{ $template->default_subtitle }}"
                                                data-description="{{ $template->description }}"
                                                data-button-text="{{ $template->default_button_text }}"
                                                data-sort-order="{{ $template->sort_order }}"
                                                data-desktop-src="{{ asset('storage/'.$template->desktop_image_path) }}"
                                                data-mobile-src="{{ $template->mobile_image_path ? asset('storage/'.$template->mobile_image_path) : '' }}"
                                                @selected($selectedTemplateUuid === $template->uuid)
                                            >
                                                {{ $template->name }} - {{ $template->positionLabel() }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('banner_template_uuid')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <div class="row g-2 mt-1">
                                        <div class="col-sm-7">
                                            <div class="rounded overflow-hidden bg-light border d-flex align-items-center justify-content-center" style="aspect-ratio: 16 / 6;">
                                                <img id="template_desktop_preview" alt="Template desktop preview" class="img-fluid d-none" style="width: 100%; height: 100%; object-fit: cover;">
                                                <span id="template_desktop_empty" class="text-muted small">Desktop template</span>
                                            </div>
                                        </div>
                                        <div class="col-sm-5">
                                            <div class="rounded overflow-hidden bg-light border d-flex align-items-center justify-content-center mx-auto" style="width: min(100%, 120px); aspect-ratio: 4 / 5;">
                                                <img id="template_mobile_preview" alt="Template mobile preview" class="img-fluid d-none" style="width: 100%; height: 100%; object-fit: cover;">
                                                <span id="template_mobile_empty" class="text-muted small">Mobile</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label" for="position">Position</label>
                                    <select id="position" name="position" class="form-select @error('position') is-invalid @enderror">
                                        <option value="">Select position</option>
                                        @foreach($positions as $value => $label)
                                            @php($meta = $positionMeta[$value] ?? null)
                                            <option value="{{ $value }}" @selected($positionValue === $value)>
                                                {{ $label }}{{ $meta ? ' - '.$meta['description'] : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('position')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-7">
                                    <div class="border rounded bg-light p-3 h-100">
                                        <div class="fw-semibold" id="position_label">Position</div>
                                        <div class="text-muted small" id="position_help"></div>
                                        <div class="small mt-2" id="position_recommendation"></div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="title">Title</label>
                                    <input id="title" name="title" value="{{ old('title', $banner->title) }}" class="form-control @error('title') is-invalid @enderror" maxlength="180">
                                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="subtitle">Subtitle</label>
                                    <input id="subtitle" name="subtitle" value="{{ old('subtitle', $banner->subtitle) }}" class="form-control @error('subtitle') is-invalid @enderror">
                                    @error('subtitle')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="button_text">Button Text</label>
                                    <input id="button_text" name="button_text" value="{{ old('button_text', $banner->button_text) }}" class="form-control @error('button_text') is-invalid @enderror" maxlength="80">
                                    @error('button_text')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="description">Description</label>
                                    <textarea id="description" name="description" class="form-control @error('description') is-invalid @enderror" rows="3">{{ old('description', $banner->description) }}</textarea>
                                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="accordion-item js-custom-upload-panel">
                    <h2 class="accordion-header" id="images_heading">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#images_section" aria-expanded="false" aria-controls="images_section">
                            Images
                        </button>
                    </h2>
                    <div id="images_section" class="accordion-collapse collapse" aria-labelledby="images_heading">
                        <div class="accordion-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label d-block">Desktop Image</label>
                                    <div class="card border-dashed p-3 mb-0">
                                        <div class="d-flex flex-column align-items-start gap-3">
                                            <div class="rounded overflow-hidden bg-light border d-flex align-items-center justify-content-center w-100" style="aspect-ratio: 16 / 6;">
                                                <img id="desktop_image_preview" src="{{ $desktopSrc }}" data-current-src="{{ $desktopSrc }}" alt="Desktop banner" class="img-fluid {{ $desktopSrc ? '' : 'd-none' }}" style="width: 100%; height: 100%; object-fit: cover;">
                                                <div id="desktop_image_placeholder" class="text-muted {{ $desktopSrc ? 'd-none' : '' }}">Desktop</div>
                                            </div>
                                            <div class="w-100">
                                                <label for="desktop_image" class="btn btn-outline-primary btn-sm">
                                                    <i class="ph-upload me-1"></i>
                                                    {{ $desktopSrc ? 'Change Image' : 'Choose Image' }}
                                                </label>
                                                <button type="button" class="btn btn-link btn-sm text-muted js-clear-banner-image" data-input-id="desktop_image">Clear</button>
                                                <input id="desktop_image" name="desktop_image" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="d-none @error('desktop_image') is-invalid @enderror">
                                                <p class="text-muted mb-1 mt-2">Recommended: <span data-banner-reco="desktop">Select a position</span>. JPG, JPEG, PNG or WEBP. Max 5MB.</p>
                                                @if($banner->desktop_image_path)
                                                    <div class="text-muted small text-break">Current: {{ $banner->desktop_image_path }}</div>
                                                @endif
                                                @error('desktop_image')<div class="text-danger small">{{ $message }}</div>@enderror
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label d-block">Mobile Image</label>
                                    <div class="card border-dashed p-3 mb-0">
                                        <div class="d-flex flex-column align-items-start gap-3">
                                            <div class="rounded overflow-hidden bg-light border d-flex align-items-center justify-content-center mx-auto" style="width: min(100%, 180px); aspect-ratio: 4 / 5;">
                                                <img id="mobile_image_preview" src="{{ $mobileSrc }}" data-current-src="{{ $mobileSrc }}" alt="Mobile banner" class="img-fluid {{ $mobileSrc ? '' : 'd-none' }}" style="width: 100%; height: 100%; object-fit: cover;">
                                                <div id="mobile_image_placeholder" class="text-muted {{ $mobileSrc ? 'd-none' : '' }}">Mobile</div>
                                            </div>
                                            <div class="w-100">
                                                <label for="mobile_image" class="btn btn-outline-primary btn-sm">
                                                    <i class="ph-upload me-1"></i>
                                                    {{ $mobileSrc ? 'Change Image' : 'Choose Image' }}
                                                </label>
                                                <button type="button" class="btn btn-link btn-sm text-muted js-clear-banner-image" data-input-id="mobile_image">Clear</button>
                                                <input id="mobile_image" name="mobile_image" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="d-none @error('mobile_image') is-invalid @enderror">
                                                <p class="text-muted mb-1 mt-2">Recommended: <span data-banner-reco="mobile">Select a position</span>. Optional; falls back to desktop.</p>
                                                @if($banner->mobile_image_path)
                                                    <div class="text-muted small text-break">Current: {{ $banner->mobile_image_path }}</div>
                                                    <div class="form-check mt-2">
                                                        <input id="remove_mobile_image" name="remove_mobile_image" type="checkbox" value="1" class="form-check-input">
                                                        <label for="remove_mobile_image" class="form-check-label">Remove current mobile image</label>
                                                    </div>
                                                @endif
                                                @error('mobile_image')<div class="text-danger small">{{ $message }}</div>@enderror
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header" id="navigation_heading">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#navigation_section" aria-expanded="false" aria-controls="navigation_section">
                            Navigation
                        </button>
                    </h2>
                    <div id="navigation_section" class="accordion-collapse collapse" aria-labelledby="navigation_heading">
                        <div class="accordion-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label" for="link_type">Link Type</label>
                                    <select id="link_type" name="link_type" class="form-select @error('link_type') is-invalid @enderror">
                                        @foreach($linkTypes as $value => $label)
                                            <option value="{{ $value }}" @selected($linkType === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('link_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-8">
                                    <div class="js-link-target-panel" data-link-panel="none">
                                        <label class="form-label">Link Target</label>
                                        <input class="form-control" value="No link" disabled>
                                    </div>
                                    @foreach(['product' => 'Search Product', 'category' => 'Search Category', 'brand' => 'Search Brand', 'shop' => 'Search Shop'] as $type => $label)
                                        <div class="js-link-target-panel d-none" data-link-panel="{{ $type }}">
                                            <label class="form-label" for="link_value_{{ $type }}">{{ $label }}</label>
                                            <select id="link_value_{{ $type }}" name="link_value" class="form-select js-link-value-control" disabled>
                                                <option value="">Select {{ strtolower(str_replace('Search ', '', $label)) }}</option>
                                                @foreach(($linkTargets[$type] ?? []) as $target)
                                                    <option
                                                        value="{{ $target['id'] }}"
                                                        data-merchant-id="{{ $target['merchant_id'] ?? '' }}"
                                                        data-shop-id="{{ $target['shop_id'] ?? '' }}"
                                                        @selected($linkType === $type && (string) $selectedLinkValue === (string) $target['id'])
                                                    >
                                                        {{ $target['label'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endforeach
                                    <div class="js-link-target-panel d-none" data-link-panel="custom_url">
                                        <label class="form-label" for="link_value_custom_url">Custom URL</label>
                                        <input id="link_value_custom_url" name="link_value" value="{{ $linkType === 'custom_url' ? $selectedLinkValue : '' }}" class="form-control js-link-value-control" placeholder="https://example.com or /internal-path" disabled>
                                        <label class="form-check mt-2 js-open-new-tab-wrap">
                                            <input type="checkbox" name="open_in_new_tab" value="1" class="form-check-input" @checked($openInNewTab)>
                                            <span class="form-check-label">Open in new tab</span>
                                        </label>
                                    </div>
                                    @error('link_value')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header" id="display_schedule_heading">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#display_schedule_section" aria-expanded="false" aria-controls="display_schedule_section">
                            Display Settings And Schedule
                        </button>
                    </h2>
                    <div id="display_schedule_section" class="accordion-collapse collapse" aria-labelledby="display_schedule_heading">
                        <div class="accordion-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label" for="sort_order">Sort Order</label>
                                    <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $banner->sort_order ?? 0) }}" class="form-control @error('sort_order') is-invalid @enderror">
                                    @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="status">Status</label>
                                    <select id="status" name="status" class="form-select @error('status') is-invalid @enderror">
                                        <option value="active" @selected(old('status', $banner->status) === 'active')>Active</option>
                                        <option value="inactive" @selected(old('status', $banner->status) === 'inactive')>Inactive</option>
                                    </select>
                                    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label d-block">Schedule State</label>
                                    <span id="schedule_state" class="badge bg-success">{{ $scheduleStatus }}</span>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="starts_at">Start Date/Time</label>
                                    <input id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', app_datetime_local($banner->starts_at)) }}" class="form-control @error('starts_at') is-invalid @enderror">
                                    @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="ends_at">End Date/Time</label>
                                    <input id="ends_at" name="ends_at" type="datetime-local" value="{{ old('ends_at', app_datetime_local($banner->ends_at)) }}" class="form-control @error('ends_at') is-invalid @enderror">
                                    @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="position-sticky" style="top: 1rem;">
                <div class="card mb-3">
                    <div class="card-header py-2">
                        <h6 class="mb-0">Desktop Preview</h6>
                    </div>
                    <div class="card-body">
                        <div class="rounded overflow-hidden bg-light border position-relative" style="aspect-ratio: 16 / 6;">
                            <img id="live_desktop_preview_image" src="{{ $desktopSrc }}" alt="Desktop preview" class="{{ $desktopSrc ? '' : 'd-none' }}" style="width: 100%; height: 100%; object-fit: cover;">
                            <div id="live_desktop_preview_empty" class="h-100 d-flex align-items-center justify-content-center text-muted {{ $desktopSrc ? 'd-none' : '' }}">Banner</div>
                            <div class="position-absolute start-0 bottom-0 w-100 p-3 text-white" style="background: linear-gradient(transparent, rgba(0,0,0,.65));">
                                <div id="live_preview_title" class="fw-semibold">{{ old('title', $banner->title) ?: 'Banner title' }}</div>
                                <div id="live_preview_subtitle" class="small">{{ old('subtitle', $banner->subtitle) }}</div>
                                <span id="live_preview_button" class="badge bg-primary mt-2 {{ old('button_text', $banner->button_text) ? '' : 'd-none' }}">{{ old('button_text', $banner->button_text) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header py-2">
                        <h6 class="mb-0">Mobile Preview</h6>
                    </div>
                    <div class="card-body">
                        <div class="rounded overflow-hidden bg-light border position-relative mx-auto" style="width: min(100%, 220px); aspect-ratio: 4 / 5;">
                            <img id="live_mobile_preview_image" src="{{ $mobileSrc ?: $desktopSrc }}" alt="Mobile preview" class="{{ $mobileSrc || $desktopSrc ? '' : 'd-none' }}" style="width: 100%; height: 100%; object-fit: cover;">
                            <div id="live_mobile_preview_empty" class="h-100 d-flex align-items-center justify-content-center text-muted {{ $mobileSrc || $desktopSrc ? 'd-none' : '' }}">Banner</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="col-12">
    <div class="card border bg-light mb-0">
        <div class="card-header py-2">
            <h6 class="mb-0">Quick Suggestions</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="fw-semibold mb-2">Title Suggestions</div>
                    <div class="btn-group flex-wrap mb-2" role="group" aria-label="Title suggestion filters">
                        @foreach(array_keys($titleSuggestions) as $group)
                            <button type="button" class="btn {{ $loop->first ? 'btn-primary text-white active' : 'btn-light' }} border btn-sm js-suggestion-filter" data-suggestion-target="title" data-suggestion-group="{{ $group }}">{{ $group }}</button>
                        @endforeach
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($titleSuggestions as $group => $examples)
                            @foreach($examples as $example)
                                <button type="button" class="btn btn-light border btn-sm js-copy-example {{ $group !== 'Popular' ? 'd-none' : '' }}" data-suggestion-group="{{ $group }}" data-copy-value="{{ $example }}" data-target-field="title">{{ $example }}</button>
                            @endforeach
                        @endforeach
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="fw-semibold mb-2">Subtitle Suggestions</div>
                    <div class="btn-group flex-wrap mb-2" role="group" aria-label="Subtitle suggestion filters">
                        @foreach(array_keys($subtitleSuggestions) as $group)
                            <button type="button" class="btn {{ $loop->first ? 'btn-primary text-white active' : 'btn-light' }} border btn-sm js-suggestion-filter" data-suggestion-target="subtitle" data-suggestion-group="{{ $group }}">{{ $group }}</button>
                        @endforeach
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($subtitleSuggestions as $group => $examples)
                            @foreach($examples as $example)
                                <button type="button" class="btn btn-light border btn-sm js-copy-example {{ $group !== 'Popular' ? 'd-none' : '' }}" data-suggestion-group="{{ $group }}" data-copy-value="{{ $example }}" data-target-field="subtitle">{{ $example }}</button>
                            @endforeach
                        @endforeach
                    </div>
                </div>
                <div class="col-lg-3">
                    <div class="fw-semibold mb-2">Button Text Suggestions</div>
                    <div class="btn-group flex-wrap mb-2" role="group" aria-label="Button text suggestion filters">
                        @foreach(array_keys($buttonSuggestions) as $group)
                            <button type="button" class="btn {{ $loop->first ? 'btn-primary text-white active' : 'btn-light' }} border btn-sm js-suggestion-filter" data-suggestion-target="button_text" data-suggestion-group="{{ $group }}">{{ $group }}</button>
                        @endforeach
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($buttonSuggestions as $group => $examples)
                            @foreach($examples as $example)
                                <button type="button" class="btn btn-light border btn-sm js-copy-example {{ $group !== 'Popular' ? 'd-none' : '' }}" data-suggestion-group="{{ $group }}" data-copy-value="{{ $example }}" data-target-field="button_text">{{ $example }}</button>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="text-muted small mt-3">Use the filters to keep suggestions relevant. Click any suggestion to fill the matching field and copy it.</div>
        </div>
    </div>
</div>
