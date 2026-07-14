<?php

return [
    'max_variant_combinations' => (int) env('PRODUCT_MAX_VARIANT_COMBINATIONS', 100),

    'images' => [
        'no_variant_max' => (int) env('PRODUCT_IMAGES_NO_VARIANT_MAX', 8),
        'per_variant_value' => (int) env('PRODUCT_IMAGES_PER_VARIANT_VALUE', 2),
        'entire_product' => (int) env('PRODUCT_IMAGES_ENTIRE_PRODUCT', 2),
    ],
];
