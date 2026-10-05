@php
    use App\Models\Faq;
    $isEdit = $faq !== null;
    $selectedCategory = old('category', $faq?->category ?? Faq::CATEGORY_GENERAL);
    $selectedStatus = old('status', $faq?->status ?? 'active');
    $categoryLabels = Faq::categories();
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
        <h5 class="mb-0">FAQ Information</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                <select id="category" name="category" class="form-select @error('category') is-invalid @enderror" required>
                    @foreach($categoryLabels as $value => $label)
                        <option value="{{ $value }}" @selected($selectedCategory === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label for="sort_order" class="form-label">Sort Order</label>
                <input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $faq?->sort_order ?? 0) }}" class="form-control @error('sort_order') is-invalid @enderror">
                @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                    <option value="active" @selected($selectedStatus === 'active')>Active</option>
                    <option value="inactive" @selected($selectedStatus === 'inactive')>Inactive</option>
                </select>
                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <label for="question" class="form-label">Question <span class="text-danger">*</span></label>
                <input id="question" name="question" type="text" maxlength="255" value="{{ old('question', $faq?->question) }}" class="form-control @error('question') is-invalid @enderror" required>
                @error('question')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <label for="answer" class="form-label">Answer <span class="text-danger">*</span></label>
                <textarea id="answer" name="answer" rows="5" maxlength="2000" class="form-control @error('answer') is-invalid @enderror" required>{{ old('answer', $faq?->answer) }}</textarea>
                @error('answer')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<x-form-buttons
    :submit="$isEdit ? 'Update FAQ' : 'Create FAQ'"
    :cancel="route('admin.faqs.index')"
    cancel-label="Cancel"
/>
