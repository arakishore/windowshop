(function () {
    "use strict";

    function ready(callback) {
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", callback);
            return;
        }

        callback();
    }

    ready(function () {
        var modalEl = document.getElementById("customer-location-modal");

        if (!modalEl || typeof bootstrap === "undefined") {
            return;
        }

        var form = modalEl.querySelector(".customer-location-form");
        var input = modalEl.querySelector("#customer-location-postal-code");
        var errorEl = modalEl.querySelector(".customer-location-error");
        var button = form ? form.querySelector("button[type='submit']") : null;
        var buttonText = modalEl.querySelector(".customer-location-button-text");
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);

        function showError(message) {
            if (!errorEl) {
                return;
            }

            errorEl.textContent = message;
            errorEl.classList.add("is-visible");
        }

        function clearError() {
            if (!errorEl) {
                return;
            }

            errorEl.textContent = "";
            errorEl.classList.remove("is-visible");
        }

        function setLoading(isLoading) {
            if (button) {
                button.disabled = isLoading;
            }

            if (buttonText) {
                buttonText.textContent = isLoading ? "Applying..." : (modalEl.dataset.currentPostalCode ? "Update" : "Apply");
            }
        }

        modalEl.addEventListener("shown.bs.modal", function () {
            if (input) {
                input.focus();
                input.select();
            }
        });

        if (modalEl.dataset.autoOpen === "1") {
            window.setTimeout(function () {
                modal.show();
            }, 350);
        }

        if (!form || !input) {
            return;
        }

        input.addEventListener("input", function () {
            input.value = input.value.replace(/\D/g, "").slice(0, 6);
            clearError();
        });

        form.addEventListener("submit", function (event) {
            var postalCode = input.value.trim();

            if (!/^\d{6}$/.test(postalCode)) {
                event.preventDefault();
                showError("Enter a valid 6-digit PIN code.");
                return;
            }

            if (!window.fetch) {
                return;
            }

            event.preventDefault();
            clearError();
            setLoading(true);

            fetch(form.action, {
                method: "POST",
                headers: {
                    "Accept": "application/json",
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content")
                },
                body: JSON.stringify({ postal_code: postalCode })
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function (result) {
                    if (!result.ok) {
                        var errors = result.data && result.data.errors && result.data.errors.postal_code;
                        showError(errors && errors.length ? errors[0] : "We couldn't save this PIN code. Please try again.");
                        setLoading(false);
                        return;
                    }

                    modalEl.dataset.currentPostalCode = result.data.postal_code || postalCode;
                    modal.hide();
                    window.location.reload();
                })
                .catch(function () {
                    showError("We couldn't save this PIN code. Please try again.");
                    setLoading(false);
                });
        });
    });
})();
