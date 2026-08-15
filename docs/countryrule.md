# Country Rule

Date: 2026-08-15

Purpose: document how WindowShop currently handles country data and define the recommended rule for checkout/delivery-address country handling.

## Current Storefront Country Behavior

The storefront does not currently have a full current-country or marketplace-country context.

Storefront location is PIN-code based:

- Session key: `storefront.shopping_postal_code`
- Cookie: `windowshop_postal_code`
- Service: `App\Services\Storefront\CustomerLocationService`

No country is currently stored in storefront session, cookie, or customer profile as part of normal browsing.

## Existing Default Country Sources

Two existing sources define India-first defaults:

- `config/location.php`
  - `default_country_code = IN`
  - `default_state_code = MH`
  - `default_city = Nashik`

- `system_settings`
  - `default_country_code = IN`
  - `default_state_code = MH`
  - `default_city_name = Nashik`

There is no existing `default_country_id` setting. Do not hardcode a numeric country ID.

## `loc_countries`

`loc_countries` is the canonical country master for saved addresses and country identity.

Important fields:

- `id`
- `name`
- `iso2`
- `iso3`
- `numeric_code`
- `phonecode`
- `currency`
- `status`
- `deleted_at`

India is seeded as:

- `name = India`
- `iso2 = IN`
- `iso3 = IND`
- `numeric_code = 356`
- `phonecode = 91`
- `currency = INR`
- `status = true`

Recommended reliable India check:

```php
loc_countries.iso2 === 'IN'
```

Use `iso3 = IND` only as a fallback. Never rely on `loc_countries.id`.

## Customer Address Storage

Customer addresses are stored in `merchant_customer_addresses`.

Location fields:

- `country_id` nullable FK to `loc_countries.id`
- `state_id` nullable FK to `loc_states.id`
- `city_id` nullable FK to `loc_cities.id`
- `postal_code` nullable string

There are no text columns for country/state/city names in this table.

Addresses are created/updated through:

- `Merchant\CustomerAddressController`
- `UpsertMerchantCustomerAddressRequest`
- `MerchantCustomerAddressService`
- checkout-specific `CheckoutAddressController`

Default delivery is stored by `is_default_shipping`. The address service unsets prior defaults when a new default is saved.

## Registration

Customer registration does not capture or assign country.

Current customer registration captures:

- name
- optional last name
- optional mobile
- email
- password
- terms acceptance

No country is stored during registration.

## Geolocation

No IP geolocation country detection is currently implemented.

Browser geolocation is used only to resolve a nearby PIN code:

1. Browser returns latitude/longitude.
2. `CustomerLocationController@detect` receives coordinates.
3. `CustomerLocationService::resolveNearestPostalCode()` finds nearest active `postal_codes` row.
4. Response includes postal code, locality, district, state, distance.

Detected country is not returned or persisted.

## Postal Code Rule

`postal_codes` is currently an India PIN-code reference dataset.

It stores:

- `postal_code`
- `office_name`
- `district`
- `state`
- `shipping_enabled`
- latitude/longitude
- `status`
- `deleted_at`

It does not store:

- `country_id`
- `state_id`
- `city_id`

Do not add foreign keys from `postal_codes` to `loc_*`.

Use `postal_codes` for:

- India PIN validation
- India city/district and state autofill
- India shipping-enabled signal

Use `loc_countries`, `loc_states`, and `loc_cities` for saved customer address location.

## Postal Code Restrictions

`postal_code_restrictions` is used for delivery restrictions by postal code.

Restriction checks must honor:

- `status = active`
- `deleted_at IS NULL`
- `starts_at`
- `ends_at`
- shop/merchant/global scope as supported by existing schema

Restriction checks should return per-shop availability for multi-shop carts.

## Merchant And Shop Country

Merchant business address stores:

- `country_id`
- `state_id`
- `city_id`
- `pincode`

Shop stores:

- `country_id`
- `state_id`
- `city_id`
- `pincode`

Both use nullable `loc_*` foreign keys.

There is no separate supported-countries, delivery-countries, or shipping-countries table.

## Checkout Country Rule

Checkout should be India-first, not India-only.

Country selection rule:

1. Resolve the default country code from `system_settings.default_country_code`.
2. If missing, fall back to `config('location.default_country_code')`.
3. Resolve the active `loc_countries` row by `iso2`.
4. Use that country only to preselect the checkout address Country field.
5. Always validate checkout behavior from the submitted `country_id`.

India-specific rule:

```text
selected country iso2 = IN
    -> require 6-digit PIN
    -> validate against postal_codes
    -> autofill district/state from postal_codes
    -> resolve saved country/state/city through loc_*
    -> check postal_code_restrictions

selected country iso2 != IN
    -> do not query postal_codes
    -> use normal loc_* country/state/city behavior
```

## Hardcoding Guidance

Allowed:

- Stable ISO codes such as `IN`, `IND`, `MH` when resolving seeded/default master data.
- `+91` only as a mobile-country-code default where the current India-first UX explicitly requires it.

Not allowed:

- Hardcoded numeric `country_id`
- Storing country in `postal_codes`
- Trusting frontend city/state for India PIN addresses

## Recommended Smallest Next Change

Centralize default country resolution in a small service/helper:

```text
system_settings.default_country_code
    fallback to config('location.default_country_code')
    resolve active loc_countries.iso2
```

Use that resolver for checkout country preselection. Keep India PIN logic based on the selected country row's `iso2 = IN`.

Do not create a new country setting, table, or schema change unless a future multi-country rollout needs a broader marketplace-country model.
