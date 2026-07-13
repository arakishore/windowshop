<?php

return [

    'driver' => env('IMAGE_DRIVER', 'gd'),

    'format' => 'webp',

    'default_quality' => 82,

    'shop_logo' => [
        'max_upload_kb' => 5120,
        'quality' => 82,
        'fit' => 'cover',
        'variants' => [
            'thumb' => [120, 120],
            'app' => [256, 256],
            'web' => [512, 512],
        ],
    ],

    'shop_banner' => [
        'max_upload_kb' => 8192,
        'quality' => 80,
        'fit' => 'cover',
        'variants' => [
            'thumb' => [480, 270],
            'app' => [1280, 720],
            'web' => [1920, 1080],
        ],
    ],

    'product_category' => [
        'max_upload_kb' => 4096,
        'quality' => 82,
        'fit' => 'cover',
        'variants' => [
            'thumb' => [160, 160],
            'app' => [320, 320],
            'web' => [640, 640],
        ],
    ],

    'brand_logo' => [
        'max_upload_kb' => 5120,
        'quality' => 82,
        'fit' => 'cover',
        'variants' => [
            'thumb' => [120, 120],
            'app' => [256, 256],
            'web' => [512, 512],
        ],
    ],

    'product' => [
        'max_upload_kb' => 10240,
        'quality' => 82,
        'fit' => 'cover',
        'variants' => [
            'thumb' => [200, 200],
            'card' => [480, 480],
            'app' => [800, 800],
            'web' => [1200, 1200],
        ],
    ],

    'offer_banner_app' => [
        'max_upload_kb' => 6144,
        'quality' => 80,
        'fit' => 'cover',
        'variants' => [
            'thumb' => [480, 240],
            'app' => [1200, 600],
        ],
    ],

    'offer_banner_web' => [
        'max_upload_kb' => 8192,
        'quality' => 80,
        'fit' => 'cover',
        'variants' => [
            'thumb' => [600, 200],
            'web' => [1800, 600],
        ],
    ],

];
