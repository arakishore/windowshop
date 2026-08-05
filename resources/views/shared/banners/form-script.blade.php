@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const meta = @json($positionMeta);
            const position = document.getElementById('position');
            const positionLabel = document.getElementById('position_label');
            const help = document.getElementById('position_help');
            const recommendation = document.getElementById('position_recommendation');
            const previewUrls = new Map();
            const desktopReco = document.querySelector('[data-banner-reco="desktop"]');
            const mobileReco = document.querySelector('[data-banner-reco="mobile"]');
            const ownerFields = Array.from(document.querySelectorAll('.js-owner-field'));
            const ownerControls = Array.from(document.querySelectorAll('.js-owner-type'));
            const merchantSelect = document.getElementById('merchant_id');
            const shopSelect = document.getElementById('shop_id');
            const linkType = document.getElementById('link_type');
            const linkPanels = Array.from(document.querySelectorAll('.js-link-target-panel'));
            const title = document.getElementById('title');
            const subtitle = document.getElementById('subtitle');
            const buttonText = document.getElementById('button_text');
            const startsAt = document.getElementById('starts_at');
            const endsAt = document.getElementById('ends_at');
            const status = document.getElementById('status');
            const scheduleState = document.getElementById('schedule_state');
            const accordion = document.getElementById('banner_form_sections');
            const openAll = document.querySelector('[data-banner-accordion="open"]');
            const collapseAll = document.querySelector('[data-banner-accordion="collapse"]');

            function ownerType() {
                const checked = ownerControls.find((control) => control.checked);

                return checked ? checked.value : 'merchant';
            }

            function syncOwnerFields() {
                const marketplace = ownerType() === 'marketplace';

                ownerFields.forEach((field) => {
                    field.classList.toggle('d-none', marketplace);
                    field.querySelectorAll('select, input').forEach((control) => {
                        control.disabled = marketplace;
                    });
                });

                syncPositionOptions();
                syncShopOptions();
                syncLinkTargets();
            }

            function syncPositionOptions() {
                if (!position) {
                    return;
                }

                const desiredScope = ownerType() === 'marketplace' ? 'admin' : 'merchant';
                let currentAllowed = true;

                Array.from(position.options).forEach((option) => {
                    if (!option.value || !meta[option.value]) {
                        return;
                    }

                    const allowed = meta[option.value].scope === desiredScope;
                    option.disabled = !allowed;

                    if (option.selected && !allowed) {
                        currentAllowed = false;
                    }
                });

                if (!currentAllowed) {
                    position.value = '';
                }

                renderPositionHelp();
            }

            function syncShopOptions() {
                if (!merchantSelect || !shopSelect) {
                    return;
                }

                const merchantId = merchantSelect.value;
                let selectedStillVisible = false;

                Array.from(shopSelect.options).forEach((option) => {
                    if (!option.value) {
                        return;
                    }

                    const visible = !merchantId || option.dataset.merchantId === merchantId;
                    option.hidden = !visible;
                    option.disabled = !visible;

                    if (option.selected && visible) {
                        selectedStillVisible = true;
                    }
                });

                if (merchantId && !selectedStillVisible) {
                    shopSelect.value = '';
                }
            }

            function renderPositionHelp() {
                if (!position) {
                    return;
                }

                const item = meta[position.value];
                const desktop = item ? item.dimensions.desktop : 'Select a position';
                const mobile = item ? item.dimensions.mobile : 'Select a position';

                if (positionLabel) {
                    positionLabel.textContent = item ? item.label : 'Position';
                }

                if (help) {
                    help.textContent = item ? item.description : 'Choose where this banner will appear.';
                }

                if (recommendation) {
                    recommendation.textContent = item ? `Recommended: Desktop ${desktop}, Mobile ${mobile}. Maximum active or scheduled banners: ${item.max}.` : '';
                }

                if (desktopReco) {
                    desktopReco.textContent = desktop;
                }

                if (mobileReco) {
                    mobileReco.textContent = mobile;
                }
            }

            function syncLinkTargets() {
                if (!linkType) {
                    return;
                }

                const selectedType = linkType.value || 'none';
                const merchantId = merchantSelect && !merchantSelect.disabled ? merchantSelect.value : '';
                const shopId = shopSelect && !shopSelect.disabled ? shopSelect.value : '';

                linkPanels.forEach((panel) => {
                    const active = panel.dataset.linkPanel === selectedType;
                    panel.classList.toggle('d-none', !active);
                    panel.querySelectorAll('.js-link-value-control').forEach((control) => {
                        control.disabled = !active;
                    });
                });

                document.querySelectorAll('select.js-link-value-control').forEach((select) => {
                    const panelType = select.closest('.js-link-target-panel')?.dataset.linkPanel;
                    let selectedStillVisible = false;

                    Array.from(select.options).forEach((option) => {
                        if (!option.value) {
                            return;
                        }

                        let visible = true;

                        if (ownerType() === 'merchant' && merchantId && ['product', 'shop'].includes(panelType)) {
                            visible = option.dataset.merchantId === merchantId;
                        }

                        if (ownerType() === 'merchant' && shopId && panelType === 'product') {
                            visible = visible && option.dataset.shopId === shopId;
                        }

                        option.hidden = !visible;
                        option.disabled = !visible;

                        if (option.selected && visible) {
                            selectedStillVisible = true;
                        }
                    });

                    if (!selectedStillVisible && !select.disabled) {
                        select.value = '';
                    }
                });
            }

            function imageSrc(id) {
                const preview = document.getElementById(id);

                return preview && !preview.classList.contains('d-none') ? preview.src : '';
            }

            function syncLivePreview() {
                const desktopImage = document.getElementById('live_desktop_preview_image');
                const mobileImage = document.getElementById('live_mobile_preview_image');
                const desktopEmpty = document.getElementById('live_desktop_preview_empty');
                const mobileEmpty = document.getElementById('live_mobile_preview_empty');
                const desktopSrc = imageSrc('desktop_image_preview');
                const mobileSrc = imageSrc('mobile_image_preview') || desktopSrc;
                const titleNode = document.getElementById('live_preview_title');
                const subtitleNode = document.getElementById('live_preview_subtitle');
                const buttonNode = document.getElementById('live_preview_button');

                if (desktopImage && desktopEmpty) {
                    desktopImage.src = desktopSrc;
                    desktopImage.classList.toggle('d-none', !desktopSrc);
                    desktopEmpty.classList.toggle('d-none', !!desktopSrc);
                }

                if (mobileImage && mobileEmpty) {
                    mobileImage.src = mobileSrc;
                    mobileImage.classList.toggle('d-none', !mobileSrc);
                    mobileEmpty.classList.toggle('d-none', !!mobileSrc);
                }

                if (titleNode) {
                    titleNode.textContent = title?.value || 'Banner title';
                }

                if (subtitleNode) {
                    subtitleNode.textContent = subtitle?.value || '';
                }

                if (buttonNode) {
                    buttonNode.textContent = buttonText?.value || '';
                    buttonNode.classList.toggle('d-none', !buttonText?.value);
                }
            }

            function syncScheduleState() {
                if (!scheduleState) {
                    return;
                }

                const now = new Date();
                const start = startsAt?.value ? new Date(startsAt.value) : null;
                const end = endsAt?.value ? new Date(endsAt.value) : null;
                let label = 'Active Now';
                let className = 'badge bg-success';

                if (status?.value !== 'active') {
                    label = 'Inactive';
                    className = 'badge bg-light text-body border';
                } else if (start && start > now) {
                    label = 'Scheduled';
                    className = 'badge bg-info';
                } else if (end && end < now) {
                    label = 'Expired';
                    className = 'badge bg-danger';
                }

                scheduleState.textContent = label;
                scheduleState.className = className;
            }

            function setupCopySuggestions() {
                document.querySelectorAll('.js-suggestion-filter').forEach((filter) => {
                    filter.addEventListener('click', function () {
                        const targetField = filter.dataset.suggestionTarget;
                        const group = filter.dataset.suggestionGroup;

                        document.querySelectorAll('.js-suggestion-filter[data-suggestion-target="' + targetField + '"]').forEach((button) => {
                            button.classList.toggle('active', button === filter);
                            button.classList.toggle('btn-primary', button === filter);
                            button.classList.toggle('text-white', button === filter);
                            button.classList.toggle('btn-light', button !== filter);
                        });

                        document.querySelectorAll('.js-copy-example[data-target-field="' + targetField + '"]').forEach((suggestion) => {
                            suggestion.classList.toggle('d-none', suggestion.dataset.suggestionGroup !== group);
                        });
                    });
                });

                document.querySelectorAll('.js-copy-example').forEach((button) => {
                    button.addEventListener('click', async function () {
                        const value = button.dataset.copyValue || button.textContent.trim();
                        const target = document.getElementById(button.dataset.targetField);
                        const originalText = button.dataset.originalText || button.textContent.trim();

                        button.dataset.originalText = originalText;

                        if (target) {
                            target.value = value;
                            target.dispatchEvent(new Event('input', { bubbles: true }));
                            target.focus();
                        }

                        document.querySelectorAll('.js-copy-example[data-target-field="' + button.dataset.targetField + '"]').forEach((sibling) => {
                            const siblingText = sibling.dataset.originalText || sibling.dataset.copyValue || sibling.textContent.trim().replace(/^✓\s*/, '');

                            sibling.dataset.originalText = siblingText;
                            sibling.textContent = siblingText;
                            sibling.classList.remove('btn-primary', 'text-white', 'border-primary');
                            sibling.classList.add('btn-light', 'border');
                            sibling.setAttribute('aria-pressed', 'false');
                        });

                        button.textContent = '✓ ' + originalText;
                        button.classList.remove('btn-light');
                        button.classList.add('btn-primary', 'text-white', 'border-primary');
                        button.setAttribute('aria-pressed', 'true');

                        try {
                            await navigator.clipboard.writeText(value);
                        } catch (error) {
                            const textarea = document.createElement('textarea');
                            textarea.value = value;
                            textarea.className = 'position-fixed top-0 start-0 opacity-0';
                            document.body.appendChild(textarea);
                            textarea.focus();
                            textarea.select();
                            document.execCommand('copy');
                            textarea.remove();
                        }
                    });
                });
            }

            function setupImagePreview(inputId, previewId, placeholderId, removeId, emptyLabel) {
                const input = document.getElementById(inputId);
                const preview = document.getElementById(previewId);
                const placeholder = document.getElementById(placeholderId);
                const remove = removeId ? document.getElementById(removeId) : null;
                const clear = document.querySelector('.js-clear-banner-image[data-input-id="' + inputId + '"]');

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

                    if (previewUrls.has(inputId)) {
                        URL.revokeObjectURL(previewUrls.get(inputId));
                    }

                    const objectUrl = URL.createObjectURL(file);
                    previewUrls.set(inputId, objectUrl);
                    preview.src = objectUrl;
                    preview.classList.remove('d-none');

                    if (placeholder) {
                        placeholder.textContent = emptyLabel;
                        placeholder.classList.add('d-none');
                    }

                    syncLivePreview();
                });

                if (remove) {
                    remove.addEventListener('change', function () {
                        if (!remove.checked) {
                            const currentSrc = preview.dataset.currentSrc;

                            if (currentSrc) {
                                preview.src = currentSrc;
                                preview.classList.remove('d-none');

                                if (placeholder) {
                                    placeholder.textContent = emptyLabel;
                                    placeholder.classList.add('d-none');
                                }
                            }

                            syncLivePreview();

                            return;
                        }

                        input.value = '';
                        preview.removeAttribute('src');
                        preview.classList.add('d-none');

                        if (placeholder) {
                            placeholder.textContent = 'Will remove';
                            placeholder.classList.remove('d-none');
                        }

                        syncLivePreview();
                    });
                }

                if (clear) {
                    clear.addEventListener('click', function () {
                        if (previewUrls.has(inputId)) {
                            URL.revokeObjectURL(previewUrls.get(inputId));
                            previewUrls.delete(inputId);
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
                                placeholder.textContent = emptyLabel;
                                placeholder.classList.add('d-none');
                            }

                            syncLivePreview();

                            return;
                        }

                        preview.removeAttribute('src');
                        preview.classList.add('d-none');

                        if (placeholder) {
                            placeholder.textContent = emptyLabel;
                            placeholder.classList.remove('d-none');
                        }

                        syncLivePreview();
                    });
                }
            }

            ownerControls.forEach((control) => control.addEventListener('change', syncOwnerFields));
            merchantSelect?.addEventListener('change', function () {
                syncShopOptions();
                syncLinkTargets();
            });
            shopSelect?.addEventListener('change', syncLinkTargets);
            position?.addEventListener('change', renderPositionHelp);
            linkType?.addEventListener('change', syncLinkTargets);
            [title, subtitle, buttonText].forEach((field) => field?.addEventListener('input', syncLivePreview));
            [startsAt, endsAt, status].forEach((field) => field?.addEventListener('change', syncScheduleState));

            if (accordion && window.bootstrap) {
                openAll?.addEventListener('click', function () {
                    accordion.querySelectorAll('.accordion-collapse').forEach(function (panel) {
                        bootstrap.Collapse.getOrCreateInstance(panel, { toggle: false }).show();
                    });
                });

                collapseAll?.addEventListener('click', function () {
                    accordion.querySelectorAll('.accordion-collapse').forEach(function (panel) {
                        bootstrap.Collapse.getOrCreateInstance(panel, { toggle: false }).hide();
                    });
                });
            }

            setupImagePreview('desktop_image', 'desktop_image_preview', 'desktop_image_placeholder', null, 'Desktop');
            setupImagePreview('mobile_image', 'mobile_image_preview', 'mobile_image_placeholder', 'remove_mobile_image', 'Mobile');
            syncOwnerFields();
            renderPositionHelp();
            syncLinkTargets();
            syncLivePreview();
            syncScheduleState();
            setupCopySuggestions();
        });
    </script>
@endpush
