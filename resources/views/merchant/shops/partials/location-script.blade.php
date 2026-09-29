<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('[data-shop-location-form]');

        if (!form) return;

        const postal = form.querySelector('[data-shop-postal-code]');
        const country = form.querySelector('#country_id');
        const state = form.querySelector('#state_id');
        const city = form.querySelector('#city_id');
        const latitude = form.querySelector('#latitude');
        const longitude = form.querySelector('#longitude');
        const feedback = form.querySelector('[data-shop-postal-feedback]');

        const selectResolvedOption = function (select, id, label) {
            if (!select || !id || !label) return;

            let option = Array.from(select.options).find(item => String(item.value) === String(id));

            if (!option) {
                option = new Option(label, id);
                select.add(option);
            }

            select.value = String(id);
        };

        const lookupPostalCode = async function () {
            const pin = postal ? postal.value.trim() : '';

            if (!postal || !country || String(country.value) !== String(form.dataset.indiaCountryId) || pin === '') return;

            if (!/^\d{6}$/.test(pin)) {
                postal.classList.add('is-invalid');
                feedback.textContent = 'Please enter a valid 6-digit Indian PIN code.';
                feedback.classList.add('text-danger');
                return;
            }

            feedback.textContent = 'Looking up PIN code…';
            feedback.classList.remove('text-danger', 'text-success');

            try {
                const response = await fetch(form.dataset.postalCodeUrlTemplate.replace('__PIN__', encodeURIComponent(pin)), {
                    headers: {'Accept': 'application/json'},
                });
                const payload = await response.json();

                if (!response.ok || !payload.valid) throw new Error(payload.message || 'Please enter a valid Indian PIN code.');

                selectResolvedOption(country, payload.country_id, 'India');
                selectResolvedOption(state, payload.state_id, payload.state);
                selectResolvedOption(city, payload.city_id, payload.city);

                if (latitude && payload.latitude !== null) latitude.value = payload.latitude;
                if (longitude && payload.longitude !== null) longitude.value = payload.longitude;

                postal.classList.remove('is-invalid');
                feedback.textContent = [payload.city, payload.state, 'India'].filter(Boolean).join(', ');
                feedback.classList.remove('text-danger');
                feedback.classList.add('text-success');
            } catch (error) {
                postal.classList.add('is-invalid');
                feedback.textContent = error.message || 'Unable to look up this PIN code.';
                feedback.classList.remove('text-success');
                feedback.classList.add('text-danger');
            }
        };

        postal?.addEventListener('blur', lookupPostalCode);
        postal?.addEventListener('change', lookupPostalCode);
    });
</script>
