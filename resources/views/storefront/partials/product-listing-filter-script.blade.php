<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-server-sort-link]').forEach((link) => {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopImmediatePropagation();
                window.location.assign(link.href);
            }, true);
        });

        if (typeof bootstrap === 'undefined') {
            return;
        }

        document.querySelectorAll('.canvas-filter').forEach((filterDrawer) => {
            filterDrawer.querySelector('.storefront-filter-expand-all')?.addEventListener('click', () => {
                filterDrawer.querySelectorAll('.storefront-filter-collapse').forEach((element) => {
                    bootstrap.Collapse.getOrCreateInstance(element, { toggle: false }).show();
                });
            });

            filterDrawer.querySelector('.storefront-filter-collapse-all')?.addEventListener('click', () => {
                filterDrawer.querySelectorAll('.storefront-filter-collapse').forEach((element) => {
                    bootstrap.Collapse.getOrCreateInstance(element, { toggle: false }).hide();
                });
            });

            const shopSearch = filterDrawer.querySelector('[data-shop-option-search]');

            shopSearch?.addEventListener('input', () => {
                const term = shopSearch.value.trim().toLowerCase();
                let visibleCount = 0;

                filterDrawer.querySelectorAll('[data-shop-option]').forEach((option) => {
                    const isVisible = (option.dataset.shopName || '').includes(term);

                    option.style.display = isVisible ? '' : 'none';
                    visibleCount += isVisible ? 1 : 0;
                });

                const emptyState = filterDrawer.querySelector('[data-shop-option-empty]');

                if (emptyState) {
                    emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
                }
            });
        });
    });
</script>
