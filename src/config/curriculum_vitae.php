<?php

return [
    'template_path' => resource_path('documents/BatStateU-FO-RES-02-Curriculum-Vitae.docx'),
    'max_people' => 50,
    'max_rows_per_section' => 50,
    'sections' => [
        'academic_background' => [
            'label' => 'Academic Background',
            'default_rows' => 4,
            'fields' => [
                ['key' => 'degree', 'label' => 'Degree Earned', 'type' => 'text'],
                ['key' => 'major_field', 'label' => 'Major Field', 'type' => 'text'],
                ['key' => 'sector', 'label' => 'Sector', 'type' => 'text'],
                ['key' => 'learning_institution', 'label' => 'Learning Institution', 'type' => 'text'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['Graduated', 'Ongoing', 'Dropped', 'Terminated']],
                ['key' => 'year_start', 'label' => 'Year Started', 'type' => 'year'],
                ['key' => 'year_end', 'label' => 'Year Ended', 'type' => 'year'],
                ['key' => 'thesis', 'label' => 'Thesis', 'type' => 'text', 'wide' => true],
            ],
        ],
        'scholarships' => [
            'label' => 'Scholarship',
            'default_rows' => 5,
            'fields' => [
                ['key' => 'sponsor', 'label' => 'Sponsor', 'type' => 'text'],
                ['key' => 'primary_sponsor', 'label' => 'Primary Sponsor', 'type' => 'yes_no'],
                ['key' => 'period_start', 'label' => 'Scholarship Start', 'type' => 'date'],
                ['key' => 'period_end', 'label' => 'Scholarship End', 'type' => 'date'],
                ['key' => 'extension_start', 'label' => 'Extension Start', 'type' => 'date'],
                ['key' => 'extension_end', 'label' => 'Extension End', 'type' => 'date'],
                ['key' => 'item_expenses', 'label' => 'Item Expenses', 'type' => 'text'],
                ['key' => 'amount_approved', 'label' => 'Amount Approved', 'type' => 'money'],
                ['key' => 'amount_released', 'label' => 'Amount Released', 'type' => 'money'],
                ['key' => 'date_released', 'label' => 'Date Released', 'type' => 'date'],
            ],
        ],
        'employment' => [
            'label' => 'Employment',
            'default_rows' => 5,
            'fields' => [
                ['key' => 'agency', 'label' => 'Agency', 'type' => 'text'],
                ['key' => 'plantilla_position', 'label' => 'Plantilla Position', 'type' => 'text'],
                ['key' => 'appointment_status', 'label' => 'Status of Appointment', 'type' => 'text'],
                ['key' => 'start_date', 'label' => 'Appointment Start', 'type' => 'date'],
                ['key' => 'end_date', 'label' => 'Appointment End', 'type' => 'date'],
                ['key' => 'monthly_salary', 'label' => 'Monthly Salary', 'type' => 'money'],
            ],
        ],
        'specializations' => [
            'label' => 'Field of Specialization',
            'default_rows' => 4,
            'fields' => [
                ['key' => 'field', 'label' => 'Field of Specialization', 'type' => 'text', 'wide' => true],
                ['key' => 'primary_field', 'label' => 'Primary Field', 'type' => 'yes_no'],
            ],
        ],
        'awards' => [
            'label' => 'R&D Awards',
            'default_rows' => 3,
            'fields' => [
                ['key' => 'title', 'label' => 'Title of R&D Award', 'type' => 'text', 'wide' => true],
                ['key' => 'rank', 'label' => 'Rank', 'type' => 'text'],
                ['key' => 'category', 'label' => 'Category', 'type' => 'text'],
                ['key' => 'granting_institution', 'label' => 'Granting Institution', 'type' => 'text'],
                ['key' => 'year_granted', 'label' => 'Year Granted', 'type' => 'year'],
            ],
        ],
        'projects' => [
            'label' => 'R&D Projects Headed/Conducted',
            'default_rows' => 5,
            'fields' => [
                ['key' => 'title', 'label' => 'Title of R&D Project', 'type' => 'text', 'wide' => true],
                ['key' => 'designation', 'label' => 'Designation', 'type' => 'text'],
                ['key' => 'sector', 'label' => 'Sector', 'type' => 'text'],
                ['key' => 'current_status', 'label' => 'Current Status', 'type' => 'text', 'wide' => true],
                ['key' => 'year_from', 'label' => 'Year From', 'type' => 'year'],
                ['key' => 'year_to', 'label' => 'Year To', 'type' => 'year'],
            ],
        ],
        'publications' => [
            'label' => 'R&D Related Publications (last 3 years)',
            'default_rows' => 5,
            'fields' => [
                ['key' => 'title', 'label' => 'Title of R&D Publication', 'type' => 'text', 'wide' => true],
                ['key' => 'year_published', 'label' => 'Year Published', 'type' => 'year'],
                ['key' => 'place', 'label' => 'Place of Publication', 'type' => 'text'],
                ['key' => 'publication_group', 'label' => 'Publication Group', 'type' => 'text', 'wide' => true],
                ['key' => 'authoring_type', 'label' => 'Authoring Type', 'type' => 'text'],
            ],
        ],
        'presentations' => [
            'label' => 'R&D Presentation (last 3 years)',
            'default_rows' => 5,
            'fields' => [
                ['key' => 'title', 'label' => 'Title of Research Paper', 'type' => 'text', 'wide' => true],
                ['key' => 'conference_title', 'label' => 'Conference Title', 'type' => 'text', 'wide' => true],
                ['key' => 'category', 'label' => 'Category', 'type' => 'text'],
                ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                ['key' => 'venue', 'label' => 'Venue', 'type' => 'text'],
                ['key' => 'sponsor', 'label' => 'Sponsor', 'type' => 'text'],
            ],
        ],
    ],
];
