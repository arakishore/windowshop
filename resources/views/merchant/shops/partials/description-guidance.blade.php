@php
    $initialSuggestionKey = $initialSuggestionKey ?? null;
    $initialSuggestionLabel = $initialSuggestionLabel ?? null;
@endphp

<div class="mt-3"
    data-description-guidance
    data-initial-suggestion-key="{{ $initialSuggestionKey }}"
    data-initial-suggestion-label="{{ $initialSuggestionLabel }}">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="text-muted">
            <i class="ph-sparkle me-1"></i>
            <span data-suggestion-message>Select a Shop Type to see a suggested description.</span>
        </span>
        <button type="button" class="btn btn-sm btn-light" data-use-description-suggestion disabled>
            Use suggested description
        </button>
    </div>
</div>
<script type="application/json" data-description-suggestions>@json($descriptionSuggestions)</script>

<script>
    (function () {
        const initializeDescriptionGuidance = function () {
                const guidance = document.querySelector('[data-description-guidance]');

                if (!guidance) return;

                const form = guidance.closest('form');
                const shopType = form ? form.querySelector('#root_product_category_id') : null;
                const shortDescription = form ? form.querySelector('#short_description') : null;
                const description = form ? form.querySelector('#description') : null;
                const suggestionMessage = guidance.querySelector('[data-suggestion-message]');
                const useButton = guidance.querySelector('[data-use-description-suggestion]');
                const suggestionsNode = document.querySelector('[data-description-suggestions]');
                const suggestions = JSON.parse(suggestionsNode ? suggestionsNode.textContent : '{}');
                let currentKey = guidance.dataset.initialSuggestionKey || '';

                const selectedShopType = function () {
                    if (!shopType) return null;

                    return shopType.options[shopType.selectedIndex] || null;
                };

                const refreshGuidance = function () {
                    const option = selectedShopType();
                    currentKey = shopType && option
                        ? (option.getAttribute('data-description-suggestion-key') || '')
                        : currentKey;
                    const suggestion = suggestions[currentKey];
                    const label = option && option.textContent
                        ? option.textContent.trim()
                        : (guidance.dataset.initialSuggestionLabel || currentKey);

                    useButton.disabled = !suggestion;

                    if (suggestionMessage) {
                        suggestionMessage.textContent = suggestion
                            ? 'Suggested description available for ' + label + '.'
                            : 'Select a Shop Type to see a suggested description.';
                    }
                };

                const applySuggestion = function () {
                    const suggestion = suggestions[currentKey];

                    if (!suggestion || !shortDescription || !description) return;

                    shortDescription.value = suggestion.short_description || '';
                    description.value = suggestion.description || '';
                    shortDescription.dispatchEvent(new Event('input', {bubbles: true}));
                    description.dispatchEvent(new Event('input', {bubbles: true}));
                };

                if (useButton) useButton.addEventListener('click', function () {
                    if (shortDescription && description && !shortDescription.value.trim() && !description.value.trim()) {
                        applySuggestion();
                        return;
                    }

                    bootbox.confirm({
                        title: 'Replace Existing Description',
                        message: 'Replace the current short description and description with the suggested text? Your existing text in these fields will be replaced.',
                        buttons: {
                            cancel: {label: 'Keep Existing', className: 'btn-link'},
                            confirm: {label: 'Use Suggestion', className: 'btn-primary'},
                        },
                        callback: function (confirmed) {
                            if (confirmed) applySuggestion();
                        },
                    });
                });

                if (shopType) {
                    shopType.addEventListener('change', refreshGuidance);
                    shopType.addEventListener('input', refreshGuidance);
                }

                refreshGuidance();
                window.addEventListener('pageshow', refreshGuidance);
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeDescriptionGuidance, {once: true});
        } else {
            initializeDescriptionGuidance();
        }
    })();
</script>
