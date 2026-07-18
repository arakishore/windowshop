{{-- Purpose: Admin-owned global settings inherited by every merchant and POS surface. --}}
@extends('layouts.admin')

@section('title', 'Admin Settings | WindowShop')

@section('page_title', 'Admin Settings')

@push('styles')
    <style>
        .admin-settings-layout {
            display: grid;
            grid-template-columns: 220px minmax(0, 1fr);
            gap: 1rem;
            align-items: start;
        }

        .admin-settings-tabs {
            position: sticky;
            top: 1rem;
        }

        .admin-settings-tabs .nav-link {
            justify-content: flex-start;
            gap: .5rem;
            border-radius: .375rem;
            color: var(--body-color);
        }

        .admin-settings-tabs .nav-link.active {
            background: var(--primary);
            color: #fff;
        }

        .admin-settings-hero {
            border-left: 4px solid var(--primary);
        }

        .admin-settings-card .card-body {
            padding-top: 1rem;
            padding-bottom: 1rem;
        }

        .admin-settings-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .75rem 1rem;
        }

        .admin-settings-preview {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 290px;
            gap: 1rem;
            align-items: start;
        }

        .admin-settings-preview-card {
            position: sticky;
            top: 1rem;
        }

        .admin-settings-savebar {
            position: sticky;
            bottom: 0;
            z-index: 10;
            background: var(--body-bg, #f5f7fb);
            border-top: 1px solid var(--border-color, #ddd);
            padding: .75rem 0;
        }

        @media (max-width: 991.98px) {
            .admin-settings-layout,
            .admin-settings-preview {
                grid-template-columns: 1fr;
            }

            .admin-settings-tabs,
            .admin-settings-preview-card {
                position: static;
            }

            .admin-settings-tabs .nav {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 575.98px) {
            .admin-settings-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $oldSettings = old('settings', []);
        $field = function (string $group, string $key) use ($settings, $defaults, $oldSettings) {
            return [
                'name' => "settings[{$group}][{$key}]",
                'id' => 'setting_'.Str::slug($group.'_'.$key, '_'),
                'value' => $oldSettings[$group][$key] ?? $settings["{$group}.{$key}"] ?? $defaults[$group][$key]['value'] ?? null,
                'type' => $defaults[$group][$key]['type'] ?? \App\Models\AdminSetting::TYPE_STRING,
                'errorKey' => "settings.{$group}.{$key}",
            ];
        };
        $timezone = $field('regional', 'timezone');
        $dateFormat = $field('regional', 'date_format');
        $timeFormat = $field('regional', 'time_format');
        $financialMonth = $field('regional', 'financial_year_start_month');
        $currency = $field('currency', 'base_currency');
        $symbol = $field('currency', 'symbol');
        $decimalPlaces = $field('currency', 'decimal_places');
        $thousandsSeparator = $field('currency', 'thousands_separator');
        $decimalSeparator = $field('currency', 'decimal_separator');
        $symbolPosition = $field('currency', 'symbol_position');
        $monthOptions = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
        ];
    @endphp

    <div class="card admin-settings-hero">
        <div class="card-body d-flex align-items-center gap-3">
            <span class="btn btn-primary btn-icon rounded-pill">
                <i class="ph-gear"></i>
            </span>
            <div>
                <h4 class="mb-1">Admin Settings</h4>
                <div class="text-muted">Global configuration inherited by all merchants, POS, apps, and storefronts.</div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf
        @method('PUT')

        <div class="admin-settings-layout">
            <div class="admin-settings-tabs">
                <div class="card">
                    <div class="card-body p-2">
                        <div class="nav nav-pills flex-column" role="tablist">
                            @foreach ([
                                'general' => ['General', 'ph-gear'],
                                'regional' => ['Regional', 'ph-globe-hemisphere-east'],
                                'currency' => ['Currency', 'ph-currency-inr'],
                                'advanced' => ['Advanced', 'ph-sliders'],
                            ] as $tab => [$label, $icon])
                                <button
                                    type="button"
                                    class="nav-link d-flex align-items-center {{ $tab === 'regional' ? 'active' : '' }}"
                                    data-bs-toggle="tab"
                                    data-bs-target="#settings_{{ $tab }}"
                                    role="tab"
                                >
                                    <i class="{{ $icon }}"></i>
                                    <span>{{ $label }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-content">
                <div class="tab-pane fade" id="settings_general" role="tabpanel">
                    <div class="card admin-settings-card">
                        <div class="card-header">
                            <h5 class="mb-0">General</h5>
                        </div>
                        <div class="card-body text-muted">
                            Global marketplace preferences will appear here later.
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade show active" id="settings_regional" role="tabpanel">
                    <div class="admin-settings-preview">
                        <div>
                            <div class="card admin-settings-card">
                                <div class="card-header">
                                    <h5 class="mb-0">Time zone</h5>
                                    <div class="text-muted fs-sm mt-1">Timestamps are stored in UTC and shown in this zone.</div>
                                </div>
                                <div class="card-body">
                                    <label for="{{ $timezone['id'] }}" class="form-label fw-semibold">Time zone</label>
                                    <select id="{{ $timezone['id'] }}" name="{{ $timezone['name'] }}" class="form-select js-admin-timezone">
                                        @foreach ($timezones as $option)
                                            <option value="{{ $option['value'] }}" @selected($timezone['value'] === $option['value'])>{{ $option['label'] }} - {{ $option['value'] }}</option>
                                        @endforeach
                                    </select>
                                    @error($timezone['errorKey'])
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="card admin-settings-card">
                                <div class="card-header">
                                    <h5 class="mb-0">Date & time format</h5>
                                    <div class="text-muted fs-sm mt-1">How dates and times appear on screens, receipts, and reports.</div>
                                </div>
                                <div class="card-body">
                                    <div class="admin-settings-grid">
                                        <div>
                                            <label for="{{ $dateFormat['id'] }}" class="form-label fw-semibold">Date format</label>
                                            <select id="{{ $dateFormat['id'] }}" name="{{ $dateFormat['name'] }}" class="form-select js-admin-date-format">
                                                @foreach (['d-m-Y' => '31-01-2026 - d-m-Y', 'd/m/Y' => '31/01/2026 - d/m/Y', 'Y-m-d' => '2026-01-31 - Y-m-d', 'd M Y' => '31 Jan 2026 - d M Y'] as $option => $label)
                                                    <option value="{{ $option }}" @selected($dateFormat['value'] === $option)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            @error($dateFormat['errorKey'])
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div>
                                            <label for="{{ $timeFormat['id'] }}" class="form-label fw-semibold">Time format</label>
                                            <select id="{{ $timeFormat['id'] }}" name="{{ $timeFormat['name'] }}" class="form-select js-admin-time-format">
                                                @foreach (['h:i A' => '02:05 PM - h:i A', 'H:i' => '14:05 - H:i'] as $option => $label)
                                                    <option value="{{ $option }}" @selected($timeFormat['value'] === $option)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            @error($timeFormat['errorKey'])
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card admin-settings-card">
                                <div class="card-header">
                                    <h5 class="mb-0">Financial year</h5>
                                    <div class="text-muted fs-sm mt-1">The month your accounting year begins.</div>
                                </div>
                                <div class="card-body">
                                    <label for="{{ $financialMonth['id'] }}" class="form-label fw-semibold">Financial year starts in</label>
                                    <select id="{{ $financialMonth['id'] }}" name="{{ $financialMonth['name'] }}" class="form-select js-admin-financial-month">
                                        @foreach ($monthOptions as $month => $label)
                                            <option value="{{ $month }}" @selected((int) $financialMonth['value'] === $month)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <div class="text-muted fs-sm mt-2 js-admin-financial-preview">Your financial year runs April - March.</div>
                                    @error($financialMonth['errorKey'])
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    <div class="alert alert-warning mt-3 mb-0">
                                        <i class="ph-warning-circle me-1"></i>
                                        Changing this only affects future reporting periods. Existing reports keep their original period.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card admin-settings-preview-card">
                            <div class="card-header">
                                <h5 class="mb-0">Preview</h5>
                            </div>
                            <div class="card-body">
                                <div class="fs-3 fw-bold js-admin-date-preview">31-01-2026</div>
                                <div class="fs-5 fw-semibold text-muted js-admin-time-preview">02:05 PM</div>
                                <div class="text-muted mt-2 js-admin-timezone-preview">Asia/Kolkata</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="settings_currency" role="tabpanel">
                    <div class="admin-settings-preview">
                        <div>
                            <div class="card admin-settings-card">
                                <div class="card-header">
                                    <h5 class="mb-0">Base currency</h5>
                                    <div class="text-muted fs-sm mt-1">Applied to every price, total, and report.</div>
                                </div>
                                <div class="card-body">
                                    <label for="{{ $currency['id'] }}" class="form-label fw-semibold">Base currency</label>
                                    <select id="{{ $currency['id'] }}" name="{{ $currency['name'] }}" class="form-select js-admin-base-currency">
                                        @foreach ($currencies as $option)
                                            <option value="{{ $option['code'] }}" @selected($currency['value'] === $option['code'])>{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                    @error($currency['errorKey'])
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="card admin-settings-card">
                                <div class="card-header">
                                    <h5 class="mb-0">Display format</h5>
                                    <div class="text-muted fs-sm mt-1">How amounts are written across the system.</div>
                                </div>
                                <div class="card-body">
                                    <div class="admin-settings-grid">
                                        <div>
                                            <label for="{{ $symbol['id'] }}" class="form-label fw-semibold">Symbol</label>
                                            <input id="{{ $symbol['id'] }}" name="{{ $symbol['name'] }}" value="{{ $symbol['value'] }}" class="form-control js-admin-currency-symbol">
                                        </div>
                                        <div>
                                            <label for="{{ $decimalPlaces['id'] }}" class="form-label fw-semibold">Decimal places</label>
                                            <select id="{{ $decimalPlaces['id'] }}" name="{{ $decimalPlaces['name'] }}" class="form-select js-admin-decimal-places">
                                                @foreach ([0, 1, 2, 3, 4] as $places)
                                                    <option value="{{ $places }}" @selected((int) $decimalPlaces['value'] === $places)>{{ $places }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label for="{{ $thousandsSeparator['id'] }}" class="form-label fw-semibold">Thousands separator</label>
                                            <input id="{{ $thousandsSeparator['id'] }}" name="{{ $thousandsSeparator['name'] }}" value="{{ $thousandsSeparator['value'] }}" class="form-control js-admin-thousands-separator">
                                        </div>
                                        <div>
                                            <label for="{{ $decimalSeparator['id'] }}" class="form-label fw-semibold">Decimal separator</label>
                                            <input id="{{ $decimalSeparator['id'] }}" name="{{ $decimalSeparator['name'] }}" value="{{ $decimalSeparator['value'] }}" class="form-control js-admin-decimal-separator">
                                        </div>
                                    </div>

                                    <div class="form-check mt-3">
                                        <input type="hidden" name="{{ $symbolPosition['name'] }}" value="after">
                                        <input id="{{ $symbolPosition['id'] }}" type="checkbox" name="{{ $symbolPosition['name'] }}" value="before" class="form-check-input js-admin-symbol-before" @checked($symbolPosition['value'] === 'before')>
                                        <label for="{{ $symbolPosition['id'] }}" class="form-check-label">Show symbol before the amount</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card admin-settings-preview-card">
                            <div class="card-header">
                                <h5 class="mb-0">Preview</h5>
                            </div>
                            <div class="card-body">
                                <div class="fs-2 fw-bold js-admin-currency-preview">₹1,234,567.50</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="settings_advanced" role="tabpanel">
                    <div class="card admin-settings-card">
                        <div class="card-header">
                            <h5 class="mb-0">Advanced</h5>
                        </div>
                        <div class="card-body text-muted">
                            Advanced global settings will appear here later.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-settings-savebar">
            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('admin.dashboard') }}" class="btn btn-light">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="ph-floppy-disk me-1"></i>
                    Save Changes
                </button>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const formatDate = (format) => ({
                'd-m-Y': '31-01-2026',
                'd/m/Y': '31/01/2026',
                'Y-m-d': '2026-01-31',
                'd M Y': '31 Jan 2026',
            }[format] || '31-01-2026');
            const formatTime = (format) => format === 'H:i' ? '14:05' : '02:05 PM';
            const renderRegionalPreview = () => {
                document.querySelector('.js-admin-date-preview').textContent = formatDate(document.querySelector('.js-admin-date-format')?.value);
                document.querySelector('.js-admin-time-preview').textContent = formatTime(document.querySelector('.js-admin-time-format')?.value);
                document.querySelector('.js-admin-timezone-preview').textContent = document.querySelector('.js-admin-timezone')?.value || 'Asia/Kolkata';
                const month = Number(document.querySelector('.js-admin-financial-month')?.value || 4);
                const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                const start = months[month - 1] || 'April';
                const end = months[(month + 10) % 12] || 'March';
                document.querySelector('.js-admin-financial-preview').textContent = `Your financial year runs ${start} - ${end}.`;
            };
            const renderCurrencyPreview = () => {
                const symbol = document.querySelector('.js-admin-currency-symbol')?.value || '₹';
                const places = Number(document.querySelector('.js-admin-decimal-places')?.value || 2);
                const thousand = document.querySelector('.js-admin-thousands-separator')?.value || ',';
                const decimal = document.querySelector('.js-admin-decimal-separator')?.value || '.';
                const before = document.querySelector('.js-admin-symbol-before')?.checked ?? true;
                const fixed = Number(1234567.5).toFixed(places);
                const [whole, fraction] = fixed.split('.');
                const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, thousand);
                const amount = fraction === undefined ? grouped : `${grouped}${decimal}${fraction}`;
                document.querySelector('.js-admin-currency-preview').textContent = before ? `${symbol}${amount}` : `${amount} ${symbol}`;
            };

            document.querySelectorAll('.js-admin-timezone, .js-admin-date-format, .js-admin-time-format, .js-admin-financial-month')
                .forEach((input) => input.addEventListener('input', renderRegionalPreview));
            document.querySelectorAll('.js-admin-currency-symbol, .js-admin-decimal-places, .js-admin-thousands-separator, .js-admin-decimal-separator, .js-admin-symbol-before')
                .forEach((input) => input.addEventListener('input', renderCurrencyPreview));
            const currencies = @json($currencies->keyBy('code'));
            document.querySelector('.js-admin-base-currency')?.addEventListener('change', (event) => {
                const selected = currencies[event.target.value];
                if (!selected) {
                    renderCurrencyPreview();
                    return;
                }

                document.querySelector('.js-admin-currency-symbol').value = selected.symbol;
                document.querySelector('.js-admin-decimal-places').value = selected.decimals;
                document.querySelector('.js-admin-thousands-separator').value = selected.thousands_separator;
                document.querySelector('.js-admin-decimal-separator').value = selected.decimal_separator;
                document.querySelector('.js-admin-symbol-before').checked = Boolean(selected.symbol_first);
                renderCurrencyPreview();
            });
            renderRegionalPreview();
            renderCurrencyPreview();
        });
    </script>
@endpush
