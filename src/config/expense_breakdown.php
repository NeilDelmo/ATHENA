<?php

return [
    'template_path' => resource_path('documents/Estimated Breakdown and Details of Expenses.xlsx'),
    'max_items' => 100,
    'maximum_quantity' => 1000000,
    'maximum_unit_cost' => 1000000000,
    'categories' => [
        'mooe' => 'Maintenance and Other Operating Expenses (MOOE)',
        'capital_outlay' => 'Capital Outlay (CO)',
    ],
    'accounts' => [
        'mooe' => [
            [
                'label' => 'Travelling Expenses',
                'sub_accounts' => [
                    ['label' => 'Local', 'total_label' => 'Total for Travelling Expenses-Local'],
                ],
            ],
            [
                'label' => 'Training and Scholarship Expenses',
                'sub_accounts' => [
                    ['label' => 'none', 'total_label' => 'Total for Training and Scholarship Expenses'],
                ],
            ],
            [
                'label' => 'Supplies and Materials Expenses',
                'sub_accounts' => [
                    ['label' => 'Office Supplies Expenses', 'total_label' => 'Total for Office Supplies Expenses'],
                    ['label' => 'Semi-expendable Machinery and Equipment Expenses', 'total_label' => 'Total for Semi-expendable Machinery and Equipment Expenses'],
                    ['label' => 'Other Supplies and Materials Expenses', 'total_label' => 'Total for Other Supplies and Materials Expenses'],
                ],
            ],
            [
                'label' => 'Communication Expenses',
                'sub_accounts' => [
                    ['label' => 'Postage and Courier Expenses', 'total_label' => 'Total for Postage and Courier Expenses'],
                    ['label' => 'Telephone Expenses', 'total_label' => 'Total for Telephone Expenses'],
                ],
            ],
            [
                'label' => 'Professional Services',
                'sub_accounts' => [
                    ['label' => 'Other Professional Services', 'total_label' => 'Total for Other Professional Services'],
                ],
            ],
            [
                'label' => 'General Services',
                'sub_accounts' => [
                    ['label' => 'Other General Services', 'total_label' => 'Total for Other General Services'],
                ],
            ],
            [
                'label' => 'Repairs and Maintenance',
                'sub_accounts' => [
                    ['label' => 'none', 'total_label' => 'Total for Repairs and Maintenance'],
                ],
            ],
            [
                'label' => 'Other Maintenance and Operating Expenses',
                'sub_accounts' => [
                    ['label' => 'Printing and Publication Expenses', 'total_label' => 'Total for Printing and Publication Expenses'],
                    ['label' => 'Representation Expenses', 'total_label' => 'Total for Representation Expenses'],
                    ['label' => 'Rent/Lease Expenses', 'total_label' => 'Total for Rent/Lease Expenses'],
                    ['label' => 'Subscription Expenses', 'total_label' => 'Total for Subscription Expenses'],
                    ['label' => 'Other Maintenance and Operating Expenses', 'total_label' => 'Total for Other Maintenance and Operating Expenses'],
                ],
            ],
            [
                'label' => 'Contingency',
                'is_contingency' => true,
                'sub_accounts' => [
                    ['label' => 'none', 'total_label' => 'Total for Contingency'],
                ],
            ],
        ],
        'capital_outlay' => [
            [
                'label' => 'Machinery and Equipment Outlay',
                'sub_accounts' => [
                    ['label' => 'Office Equipment', 'total_label' => 'Total for Office Equipment'],
                    ['label' => 'ICT Equipment', 'total_label' => 'Total for ICT Equipment'],
                    ['label' => 'Technical and Scientific Equipment', 'total_label' => 'Total for Technical and Scientific Equipment'],
                    ['label' => 'Other Machinery and Equipment', 'total_label' => 'Total for Other Machinery and Equipment Outlay'],
                ],
            ],
        ],
    ],
];
