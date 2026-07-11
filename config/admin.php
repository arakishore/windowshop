<?php

return [
    'pagination' => [
        'per_page' => (int) env('ADMIN_PAGINATION_PER_PAGE', 2),
    ],

    'shop' => [
        'statuses' => [
            'active' => [
                'label' => 'Active',
                'badge_class' => 'bg-success',
            ],
            'inactive' => [
                'label' => 'Inactive',
                'badge_class' => 'bg-light text-body border',
            ],
            'suspended' => [
                'label' => 'Suspended',
                'badge_class' => 'bg-warning',
            ],
            'deleted' => [
                'label' => 'Deleted',
                'badge_class' => 'bg-danger',
            ],
        ],
    ],
];
