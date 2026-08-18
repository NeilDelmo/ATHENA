<?php

return [
    'template_path' => resource_path('documents/BatStateU-FO-RES-02-Line-Item-Budget.docx'),
    'default_campus' => 'ARASOF-Nasugbu',
    'college_options' => ['CICS', 'CTE', 'CABEIHM', 'CCJE', 'CAS', 'CHS'],
    'max_custom_items' => 50,
    'maximum_amount' => 999999999.99,
    'sections' => [
        'mooe' => [
            'label' => 'Maintenance and Other Operating Expenses (MOOE)',
            'items' => [
                ['key' => 'travelling_expenses', 'label' => 'Travelling Expenses', 'level' => 0],
                ['key' => 'travelling_local', 'label' => 'Local', 'level' => 1],
                ['key' => 'training_scholarship', 'label' => 'Training and Scholarship Expenses', 'level' => 0],
                ['key' => 'supplies_materials', 'label' => 'Supplies and Materials Expenses', 'level' => 0],
                ['key' => 'office_supplies', 'label' => 'Office Supplies Expenses', 'level' => 1],
                ['key' => 'semi_expendable_equipment', 'label' => 'Semi-expendable Machinery and Equipment Expenses', 'level' => 1],
                ['key' => 'other_supplies_materials', 'label' => 'Other Supplies and Materials Expenses', 'level' => 1],
                ['key' => 'communication_expenses', 'label' => 'Communication Expenses', 'level' => 0],
                ['key' => 'postage_courier', 'label' => 'Postage and Courier Expenses', 'level' => 1],
                ['key' => 'telephone_expenses', 'label' => 'Telephone Expenses', 'level' => 1],
                ['key' => 'professional_services', 'label' => 'Professional Services', 'level' => 0],
                ['key' => 'other_professional_services', 'label' => 'Other Professional Services', 'level' => 1],
                ['key' => 'general_services', 'label' => 'General Services', 'level' => 0],
                ['key' => 'other_general_services', 'label' => 'Other General Services', 'level' => 1],
                ['key' => 'repairs_maintenance', 'label' => 'Repairs and Maintenance', 'level' => 0],
                ['key' => 'other_mooe', 'label' => 'Other Maintenance and Operating Expenses', 'level' => 0],
                ['key' => 'printing_publication', 'label' => 'Printing and Publication Expenses', 'level' => 1],
                ['key' => 'representation_expenses', 'label' => 'Representation Expenses', 'level' => 1],
                ['key' => 'rent_lease', 'label' => 'Rent/Lease Expenses', 'level' => 1],
                ['key' => 'subscription_expenses', 'label' => 'Subscription Expenses', 'level' => 1],
                ['key' => 'other_mooe_expenses', 'label' => 'Other Maintenance and Operating Expenses', 'level' => 1],
                ['key' => 'contingency', 'label' => 'Contingency*', 'level' => 0],
            ],
        ],
        'co' => [
            'label' => 'Capital Outlays (CO)',
            'items' => [
                ['key' => 'machinery_equipment_outlay', 'label' => 'Machinery and Equipment Outlay', 'level' => 0],
                ['key' => 'office_equipment', 'label' => 'Office Equipment', 'level' => 1],
                ['key' => 'ict_equipment', 'label' => 'ICT Equipment', 'level' => 1],
                ['key' => 'technical_scientific_equipment', 'label' => 'Technical and Scientific Equipment', 'level' => 1],
                ['key' => 'other_machinery_equipment', 'label' => 'Other Machinery and Equipment', 'level' => 1],
            ],
        ],
    ],
];
