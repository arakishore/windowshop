<?php

return [
    'pagination' => [
        'per_page' => (int) env('ADMIN_PAGINATION_PER_PAGE', 50),
    ],

    'shop' => [
        'statuses' => [
            'pending' => [
                'label' => 'Pending',
                'badge_class' => 'bg-info',
            ],
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
            'rejected' => [
                'label' => 'Rejected',
                'badge_class' => 'bg-danger',
            ],
            'deleted' => [
                'label' => 'Deleted',
                'badge_class' => 'bg-danger',
            ],
        ],
    ],
];
