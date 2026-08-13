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
        var detectButton = modalEl.querySelector(".customer-location-detect-btn");
        var detectText = modalEl.querySelector(".customer-location-detect-text");
        var detectedPanel = modalEl.querySelector(".customer-location-detected");
        var detectedPin = modalEl.querySelector(".customer-location-detected-pin");
        var detectedMeta = modalEl.querySelector(".customer-location-detected-meta");
        var confirmDetectedButton = modalEl.querySelector(".customer-location-confirm-detected");
        var enterManuallyButton = modalEl.querySelector(".customer-location-enter-manually");
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        var detectedPostalCode = null;

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

        function csrfToken() {
            var token = document.querySelector('meta[name="csrf-token"]');

            return token ? token.getAttribute("content") : "";
        }

        function setLoading(isLoading) {
            if (button) {
                button.disabled = isLoading;
            }

            if (buttonText) {
                buttonText.textContent = isLoading ? "Checking PIN code..." : (modalEl.dataset.currentPostalCode ? "Update" : "Apply");
            }
        }

        function setDetectLoading(isLoading) {
            if (detectButton) {
                detectButton.disabled = isLoading;
            }

            if (detectText) {
                detectText.textContent = isLoading ? "Detecting your location..." : "Use my current location";
            }
        }

        function showDetectedLocation(data) {
            detectedPostalCode = data.postal_code || null;

            if (!detectedPostalCode || !detectedPanel) {
                return;
            }

            if (detectedPin) {
                detectedPin.textContent = detectedPostalCode;
            }

            if (detectedMeta) {
                var parts = [data.locality, data.district, data.state].filter(Boolean);
                var distance = data.distance_km != null ? "About " + data.distance_km + " km from detected point." : "";
                detectedMeta.textContent = [parts.join(", "), distance].filter(Boolean).join(" ");
            }

            detectedPanel.classList.add("is-visible");
        }

        function hideDetectedLocation() {
            detectedPostalCode = null;

            if (detectedPanel) {
                detectedPanel.classList.remove("is-visible");
            }
        }

        function savePostalCode(postalCode) {
            setLoading(true);

            return fetch(form.action, {
                method: "POST",
                headers: {
                    "Accept": "application/json",
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": csrfToken()
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
        }

        function geolocationErrorMessage(error) {
            if (!error || typeof error.code === "undefined") {
                return "We couldn't detect your location. Please enter your PIN code instead.";
            }

            if (error.code === error.PERMISSION_DENIED) {
                return "Location permission was denied. Please enter your PIN code instead.";
            }

            if (error.code === error.POSITION_UNAVAILABLE) {
                return "Your location is unavailable right now. Please enter your PIN code instead.";
            }

            if (error.code === error.TIMEOUT) {
                return "Location request timed out. Please enter your PIN code instead.";
            }

            return "We couldn't detect your location. Please enter your PIN code instead.";
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

        if (detectButton) {
            detectButton.addEventListener("click", function () {
                clearError();
                hideDetectedLocation();

                if (!navigator.geolocation) {
                    showError("Your browser does not support location detection. Please enter your PIN code instead.");
                    return;
                }

                setDetectLoading(true);

                navigator.geolocation.getCurrentPosition(function (position) {
                    fetch(modalEl.dataset.detectEndpoint, {
                        method: "POST",
                        headers: {
                            "Accept": "application/json",
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": csrfToken()
                        },
                        body: JSON.stringify({
                            latitude: position.coords.latitude,
                            longitude: position.coords.longitude,
                            accuracy: position.coords.accuracy
                        })
                    })
                        .then(function (response) {
                            return response.json().then(function (data) {
                                return { ok: response.ok, data: data };
                            });
                        })
                        .then(function (result) {
                            setDetectLoading(false);

                            if (!result.ok) {
                                showError(result.data && result.data.message ? result.data.message : "We couldn't detect your PIN code. Please enter it manually.");
                                return;
                            }

                            showDetectedLocation(result.data);
                        })
                        .catch(function () {
                            setDetectLoading(false);
                            showError("We couldn't detect your PIN code. Please enter it manually.");
                        });
                }, function (error) {
                    setDetectLoading(false);
                    showError(geolocationErrorMessage(error));
                }, {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                });
            });
        }

        if (confirmDetectedButton) {
            confirmDetectedButton.addEventListener("click", function () {
                if (!detectedPostalCode) {
                    showError("Please detect your location again or enter your PIN code manually.");
                    return;
                }

                clearError();
                savePostalCode(detectedPostalCode);
            });
        }

        if (enterManuallyButton) {
            enterManuallyButton.addEventListener("click", function () {
                hideDetectedLocation();
                clearError();

                if (input) {
                    input.focus();
                    input.select();
                }
            });
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
            savePostalCode(postalCode);
        });
    });
})();
