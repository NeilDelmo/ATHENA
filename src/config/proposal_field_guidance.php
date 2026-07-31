<?php

return [
    'route_patterns' => [
        'faculty.proposal-drafts.details.*' => 'project-details',
        'faculty.proposal-drafts.detailed-proposal.*' => 'detailed-proposal',
        'faculty.proposal-drafts.work-plan.*' => 'work-plan',
        'faculty.proposal-drafts.expense-breakdown.*' => 'expense-breakdown',
        'faculty.proposal-drafts.line-item-budget.*' => 'line-item-budget',
        'faculty.proposal-drafts.curriculum-vitae.*' => 'curriculum-vitae',
        'faculty.proposal-drafts.gad-checklist.*' => 'gad-checklist',
        'faculty.proposal-drafts.initial-screening-form.*' => 'initial-screening-form',
    ],

    /*
    |--------------------------------------------------------------------------
    | Official paper relationships
    |--------------------------------------------------------------------------
    |
    | These entries describe application data flow and consistency rules. They
    | are reused by the assistant instead of scattering workflow explanations
    | through prompts or individual proposal editors.
    |
    */
    'relationships' => [
        'shared-project-details' => [
            'papers' => [
                'project-details',
                'detailed-proposal',
                'work-plan',
                'expense-breakdown',
                'line-item-budget',
                'gad-checklist',
                'initial-screening-form',
            ],
            'source' => 'Project Details',
            'destinations' => [
                'Detailed Research Proposal',
                'Attachment A: Work Plan',
                'Estimated Expense Breakdown',
                'Attachment B: Line-Item Budget',
                'GAD Generic Checklist',
                'Initial Screening Form',
            ],
            'value_flows' => [
                'Detailed Research Proposal: Project Title and Project Leader',
                'Attachment A: Project Title, Total Duration, Planned Start, Planned End, and Project Leader as Prepared By',
                'Estimated Expense Breakdown: Project Title',
                'Attachment B: Project Title, Planned Start, Planned End, and Project Leader',
                'GAD Generic Checklist: Project Title and Project Leader',
                'Initial Screening Form: Project Title and Project Leader',
            ],
            'behavior' => 'ATHENA reuses the applicable shared project values when it renders or regenerates proposal papers.',
            'correction_workflow' => 'Correct a shared value in Project Details instead of editing a generated copy.',
        ],
        'project-team-to-curriculum-vitae' => [
            'papers' => ['project-details', 'line-item-budget', 'curriculum-vitae'],
            'sources' => ['Project Details Project Leader', 'saved Attachment B Project Staff', 'proposal workspace members'],
            'destination' => 'Attachment C: Curriculum Vitae people',
            'behavior' => 'When no saved Curriculum Vitae source exists, ATHENA uses these people as the initial CV list. A saved CV remains independently editable and is not rebuilt from later team changes.',
        ],
        'expense-breakdown-to-attachment-b' => [
            'papers' => ['expense-breakdown', 'line-item-budget'],
            'source' => 'Estimated Expense Breakdown',
            'destination' => 'Attachment B: Line-Item Budget',
            'values' => ['MOOE amounts', 'Capital Outlay amounts', 'Total Project Cost'],
            'behavior' => 'Saved itemized expenses can prefill matching Attachment B lines when the Attachment B editor opens. Existing saved Attachment B amounts take precedence over prefills and remain independently editable.',
            'consistency_rule' => 'The saved MOOE, Capital Outlay, and Total Project Cost must agree across both papers before final submission.',
            'repair_workflow' => 'Review and edit the paper containing the incorrect value. ATHENA reports differences but does not overwrite either paper automatically.',
        ],
        'attachment-b-to-detailed-proposal' => [
            'papers' => ['line-item-budget', 'detailed-proposal'],
            'source' => 'Attachment B: Line-Item Budget',
            'destination' => 'Detailed Research Proposal',
            'values' => ['MOOE', 'Capital Outlay', 'Total Project Cost'],
            'behavior' => 'ATHENA regenerates the Detailed Research Proposal budget section from the latest saved Attachment B during preview, download, and final submission.',
            'historical_output_rule' => 'A previously downloaded PDF or Word file is a snapshot and does not update automatically.',
        ],
        'expense-item-calculation' => [
            'papers' => ['expense-breakdown'],
            'source' => 'Estimated Expense Breakdown row',
            'destination' => 'Estimated Expense Breakdown totals',
            'values' => ['Unit', 'Quantity', 'Unit Cost', 'Item Total', 'section totals', 'Grand Total'],
            'calculation' => 'Item Total = Quantity × Unit Cost. Section totals and Grand Total sum the applicable item totals. A Contingency row is calculated as quantity 1 multiplied by its entered amount.',
        ],
        'attachment-b-total-calculation' => [
            'papers' => ['line-item-budget'],
            'source' => 'Attachment B MOOE and Capital Outlay sections',
            'destination' => 'Attachment B Total Project Cost',
            'values' => ['Total MOOE', 'Total Capital Outlay', 'Total Project Cost'],
            'calculation' => 'Total Project Cost = Total MOOE + Total Capital Outlay.',
            'consistency_rule' => 'A manual Total Project Cost cannot differ from the sum of the effective MOOE and Capital Outlay totals.',
        ],
        'objectives-to-work-plan' => [
            'papers' => ['detailed-proposal', 'work-plan'],
            'source' => 'Detailed Research Proposal objectives',
            'destination' => 'Attachment A: Work Plan objectives',
            'values' => ['Objectives', 'Expected Outputs', 'Activities', 'Project Months'],
            'behavior' => 'The papers remain independently editable; ATHENA does not copy objective wording automatically.',
            'consistency_rule' => 'Each work-plan row should use the same objective wording or clearly correspond to an objective in the Detailed Research Proposal.',
        ],
    ],

    'field_metadata_defaults' => [
        'examples' => ['No application-specific example is currently documented for this field.'],
        'rules' => ['The field guide does not add a validation rule beyond the constraints and errors shown by the current form.'],
        'related_fields' => ['No additional application-specific field relationship is currently documented.'],
        'common_mistakes' => ['No application-specific common mistake is currently documented for this field.'],
        'calculations' => ['No application-specific calculation is currently documented for this field.'],
    ],

    'papers' => [
        'project-details' => [
            'label' => 'Project Details',
            'aliases' => ['shared project information', 'proposal details'],
            'purpose' => 'Stores the shared title, project period, duration, and leader used by the generated proposal papers.',
            'relationships' => [
                'ATHENA reuses each applicable shared value in the Detailed Research Proposal, Work Plan, Estimated Expense Breakdown, Attachment B, GAD Checklist, and Initial Screening Form.',
                'The Project Leader also helps seed the initial Attachment C people list.',
                'Edit shared values here instead of retyping them separately in every paper.',
            ],
            'fields' => [
                'project_title' => [
                    'label' => 'Project Title',
                    'patterns' => ['project_title'],
                    'guidance' => 'Enter the complete, specific title of the proposed research project. It should identify the main topic or intervention and, when useful, the population or setting.',
                    'examples' => ['Digital literacy practices of senior high school teachers in Nasugbu'],
                ],
                'duration_months' => [
                    'label' => 'Total Duration',
                    'patterns' => ['duration_months'],
                    'guidance' => 'Enter the total number of months needed to complete the project from the planned start through the planned end.',
                    'rules' => ['The value must agree with the planned start and planned end dates.'],
                    'examples' => ['12 for a one-year project', '18 for a one-and-a-half-year project'],
                ],
                'planned_start' => [
                    'label' => 'Planned Start',
                    'patterns' => ['planned_start'],
                    'guidance' => 'Choose the intended first date of project implementation, not the date the proposal is being encoded.',
                ],
                'planned_end' => [
                    'label' => 'Planned End',
                    'patterns' => ['planned_end'],
                    'guidance' => 'Choose the intended final date of project implementation. It must be after the planned start and consistent with Total Duration.',
                ],
                'project_leader' => [
                    'label' => 'Project Leader',
                    'patterns' => ['project_leader'],
                    'guidance' => 'Enter or select the person who has primary responsibility for leading the project and coordinating the research team.',
                ],
            ],
        ],

        'detailed-proposal' => [
            'label' => 'Detailed Research Proposal',
            'aliases' => ['detailed proposal', 'research proposal', 'BatStateU-FO-RES-02'],
            'purpose' => 'Contains the project alignment, team, narrative, methodology, expected outputs, responsibilities, and references for the official research proposal.',
            'relationships' => [
                'Project title, duration, dates, and project leader come from Project Details.',
                'The MOOE, Capital Outlay, and Total Project Cost shown in this paper come from the latest saved Attachment B.',
            ],
            'fields' => [
                'research_agenda' => [
                    'label' => 'BatStateU Research Agenda',
                    'patterns' => ['research_agenda', 'research-agenda'],
                    'guidance' => 'State the university research-agenda priority or thematic area to which the project directly contributes. Use the official agenda wording when it is available.',
                ],
                'sdgs' => [
                    'label' => 'Sustainable Development Goals',
                    'patterns' => ['sdgs', 'sdgs.*'],
                    'guidance' => 'Select every UN Sustainable Development Goal that the project materially supports. Choose goals based on the actual objectives and expected outcomes, not merely broad relevance.',
                    'examples' => ['SDG 4 for a project that directly improves education access or quality', 'SDG 13 for research directly addressing climate action'],
                ],
                'project_team' => [
                    'label' => 'Project Leader and Staff',
                    'patterns' => ['leader_email', 'leader_contact', 'staff', 'staff.*', 'staff.*.*'],
                    'guidance' => 'Provide the institutional email and 11-digit contact number for the leader, then list participating staff with their correct name, email, and contact number.',
                ],
                'proponent_unit' => [
                    'label' => 'Proponent Department, College, and Campus',
                    'patterns' => ['proponent_department', 'proponent_college', 'proponent_campus'],
                    'guidance' => 'Identify the leader’s institutional unit. Department is optional when it does not apply; college and campus identify the main BatStateU unit proposing the work.',
                ],
                'cooperating_agency' => [
                    'label' => 'Cooperating Agency',
                    'patterns' => ['cooperating_agency', 'cooperating-agency'],
                    'guidance' => 'List an external or internal organization formally helping implement the project. Leave it blank when no cooperating agency is involved.',
                    'examples' => ['Municipal Agriculture Office', 'Partner public school'],
                ],
                'executive_brief' => [
                    'label' => 'Executive Brief',
                    'patterns' => ['executive_brief', 'executive-brief'],
                    'guidance' => 'Write a compact overview of the problem, proposed approach, target beneficiaries or setting, major outputs, and intended contribution. It should let a reviewer understand the project quickly.',
                ],
                'rationale' => [
                    'label' => 'Rationale',
                    'patterns' => ['rationale'],
                    'guidance' => 'Explain why the project is needed. Describe the problem, evidence of its extent, the local or disciplinary gap, and why the proposed work is timely. Include reliable statistics when available.',
                ],
                'objectives' => [
                    'label' => 'Objectives of the Project',
                    'patterns' => ['objectives'],
                    'guidance' => 'State one general objective and clear specific objectives. Specific objectives should use observable verbs and align with the Work Plan, methodology, outputs, and analysis.',
                    'examples' => ['Determine the factors associated with…', 'Develop and evaluate…'],
                ],
                'expected_outputs' => [
                    'label' => 'Expected Outputs (6Ps and 2Is)',
                    'patterns' => ['expected_outputs', 'expected_outputs.*', 'expected-output-*'],
                    'guidance' => 'Describe only the output types the project is expected to produce: publication, patent, product, people service, place and partnership, policy, social impact, or economic impact. Give a concrete, measurable deliverable for each applicable field.',
                    'examples' => ['Publication: one manuscript submitted to a peer-reviewed journal', 'People Service: training module delivered to 40 teachers'],
                    'rules' => ['At least one expected-output field is required.'],
                ],
                'introduction' => [
                    'label' => 'Introduction',
                    'patterns' => ['introduction'],
                    'guidance' => 'Introduce the research topic, its context, the central problem, and the direction of the study. Move from the broader issue toward the specific setting and purpose of the proposed project.',
                ],
                'related_literature' => [
                    'label' => 'Related Studies and Literature',
                    'patterns' => ['related_literature', 'related-literature'],
                    'guidance' => 'Synthesize relevant studies by themes, findings, methods, and gaps instead of listing one summary after another. Connect the identified gap to the proposed project.',
                    'rules' => ['The editor asks for at least ten relevant studies or literature sources.'],
                ],
                'research_design' => [
                    'label' => 'Research Design',
                    'patterns' => ['methodology.research_design', 'methodology[research_design]', 'methodology-research_design'],
                    'guidance' => 'Name and justify the overall research design, describe the setting and participants or data sources, and explain how the design answers the objectives.',
                    'examples' => ['Descriptive-correlational design', 'Sequential explanatory mixed-methods design'],
                ],
                'specific_methods' => [
                    'label' => 'Specific Method to Obtain the Objectives',
                    'patterns' => ['methodology.specific_methods', 'methodology[specific_methods]', 'methodology-specific_methods'],
                    'guidance' => 'Explain the concrete procedures for sampling, instruments, data collection, intervention or development activities, quality controls, and ethics. The sequence should map clearly to the objectives.',
                ],
                'data_analysis' => [
                    'label' => 'Data Analysis',
                    'patterns' => ['methodology.data_analysis', 'methodology[data_analysis]', 'methodology-data_analysis'],
                    'guidance' => 'State how each type of data will be processed and analyzed, including the statistical tests or qualitative technique and how these answer the objectives. This field may be left blank when genuinely not applicable.',
                    'examples' => ['Frequencies and percentages for profiles; multiple regression for predictors', 'Reflexive thematic analysis for interview transcripts'],
                ],
                'methodology_visual' => [
                    'label' => 'Research Design Visual',
                    'patterns' => ['methodology_images.*.*', 'methodology-image-*'],
                    'guidance' => 'Add an image only when it materially clarifies the research design or workflow. Use a concise figure title, appropriate size, and an alignment that keeps the paper readable.',
                ],
                'responsibilities' => [
                    'label' => 'Duties and Responsibilities of Each Member',
                    'patterns' => ['responsibilities', 'responsibilities.*', 'responsibilities.*.*'],
                    'guidance' => 'List the leader and every participating member, assign a responsibility percentage, and describe the specific work each person will perform.',
                    'rules' => ['Responsibility percentages across all listed members must total 100%.'],
                ],
                'approval_signatories' => [
                    'label' => 'Approval Signatory Names',
                    'patterns' => ['checked_verified_by_name', 'recommending_approval_name', 'approved_by_name'],
                    'guidance' => 'Enter the appropriate institutional signatory names only when they are known and applicable to the proposal. Do not guess names or approvals.',
                ],
                'references' => [
                    'label' => 'References',
                    'patterns' => ['references'],
                    'guidance' => 'List every source cited in the proposal using one consistent citation style. Check author names, year, title, publication details, and DOI or URL where applicable.',
                ],
                'budget_totals' => [
                    'label' => 'MOOE, Capital Outlay, and Total Project Cost',
                    'patterns' => ['mooe_total', 'co_total', 'project_total'],
                    'guidance' => 'These values are generated from the latest saved Attachment B and are not independently edited in the Detailed Research Proposal.',
                    'rules' => ['Attachment B remains the authoritative source for these summary budget values.'],
                ],
            ],
        ],

        'work-plan' => [
            'label' => 'Attachment A: Work Plan',
            'aliases' => ['work plan', 'Attachment A', 'Gantt schedule'],
            'purpose' => 'Connects each project objective to its expected output, implementation activities, and scheduled project months.',
            'relationships' => [
                'Project title, implementation dates, duration, and leader come from Project Details.',
                'Months are project-relative: M1 is the first project month, M13 is the first month of Year 2, and so on.',
            ],
            'fields' => [
                'objective' => [
                    'label' => 'Objective',
                    'patterns' => ['entries.*.objective', 'objective-*'],
                    'guidance' => 'Enter one specific project objective that this work-plan row will accomplish. Use the same wording or a clearly corresponding objective from the Detailed Research Proposal.',
                ],
                'expected_output' => [
                    'label' => 'Expected Output',
                    'patterns' => ['entries.*.expected_output', 'output-*'],
                    'guidance' => 'State the tangible result or verifiable deliverable produced by the activities for this objective.',
                    'examples' => ['Validated survey instrument', 'Completed baseline dataset', 'Draft training module'],
                ],
                'activity' => [
                    'label' => 'Activities or Workplan',
                    'patterns' => ['entries.*.activity', 'activity-*'],
                    'guidance' => 'List the main tasks needed to achieve the objective and output. Use an actionable sequence rather than repeating the objective.',
                    'examples' => ['Develop and validate the questionnaire; recruit participants; collect and clean responses'],
                ],
                'months' => [
                    'label' => 'M1–M12 Schedule',
                    'patterns' => ['entries.*.months', 'entries.*.months.*'],
                    'guidance' => 'Select every project month during which the activity will be performed. Month numbers are relative to the project start, not calendar month numbers.',
                    'rules' => ['Each work-plan entry needs at least one selected month.', 'Selections cannot exceed the project duration.'],
                ],
            ],
        ],

        'expense-breakdown' => [
            'label' => 'Estimated Expense Breakdown',
            'aliases' => ['estimated breakdown', 'expense budget breakdown', 'details of expenses', 'expense spreadsheet'],
            'purpose' => 'Records the item-level basis of the project budget, including what will be purchased or paid for, how it is measured, its price, and its project purpose.',
            'relationships' => [
                'Saving this paper can prefill matching summary amounts in Attachment B.',
                'Later edits remain independent, so the MOOE, Capital Outlay, and total must agree with the latest saved Attachment B before final submission.',
                'Item total equals Quantity multiplied by Unit Cost.',
            ],
            'fields' => [
                'category' => [
                    'label' => 'Expense Type',
                    'patterns' => ['items.*.category', 'expense-category-*'],
                    'guidance' => 'Choose MOOE for operating or consumable project costs and Capital Outlay for qualifying equipment or assets.',
                    'rules' => ['Use the applicable university accounting classification; ask the Research Office when classification is uncertain.'],
                ],
                'account' => [
                    'label' => 'Account',
                    'patterns' => ['items.*.account', 'expense-account-*'],
                    'guidance' => 'Choose the broad official expense account that best describes the item, such as Travelling Expenses, Supplies and Materials, Professional Services, or Machinery and Equipment Outlay.',
                ],
                'sub_account' => [
                    'label' => 'Sub-account',
                    'patterns' => ['items.*.sub_account', 'expense-sub-account-*'],
                    'guidance' => 'Choose the more specific classification under the selected account. If the editor displays “none,” that account does not require a narrower sub-account.',
                ],
                'particulars' => [
                    'label' => 'Particular/s',
                    'patterns' => ['items.*.particulars', 'expense-particulars-*'],
                    'guidance' => 'Enter the short name of the good or service being budgeted.',
                    'examples' => ['A4 bond paper', 'Mobile data prepaid card', 'Laboratory analysis service'],
                ],
                'unit' => [
                    'label' => 'Unit',
                    'patterns' => ['items.*.unit', 'expense-unit-*', 'unit'],
                    'aliases' => ['unit of measure', 'measurement basis'],
                    'guidance' => 'Enter how one expense item is counted or measured. The unit must describe what the Quantity counts; it is not a currency or price.',
                    'examples' => ['piece or pc', 'box', 'ream', 'set', 'hour', 'person-day', 'trip', 'month', 'license'],
                    'rules' => ['Example: 10 reams at PHP 250 per ream means Unit = ream, Quantity = 10, and Unit Cost = 250.', 'Contingency does not require a regular unit because it is entered as one amount.'],
                    'related_fields' => ['Particular/s identifies the item.', 'Quantity counts the stated unit.', 'Unit Cost is the price of one stated unit.', 'Item Total is calculated from Quantity and Unit Cost.'],
                    'common_mistakes' => ['Entering PHP or a peso amount as the unit.', 'Entering the quantity, such as 10, in the Unit field.', 'Using a vague unit such as item when a clearer measure such as ream, box, hour, or trip is available.'],
                    'calculations' => ['Item Total = Quantity × Unit Cost.'],
                ],
                'quantity' => [
                    'label' => 'Quantity',
                    'patterns' => ['items.*.quantity', 'expense-quantity-*', 'quantity', 'qty'],
                    'guidance' => 'Enter how many of the stated units are required.',
                    'examples' => ['10 when Unit is ream', '6 when Unit is month'],
                    'rules' => ['Quantity multiplied by Unit Cost produces the item total.'],
                    'related_fields' => ['Unit', 'Unit Cost', 'Item Total'],
                    'common_mistakes' => ['Entering the combined price instead of the number of units.', 'Using a quantity that does not match the stated Unit.'],
                    'calculations' => ['Item Total = Quantity × Unit Cost.'],
                ],
                'unit_cost' => [
                    'label' => 'Unit Cost (PHP)',
                    'patterns' => ['items.*.unit_cost', 'expense-unit-cost-*', 'unit_cost'],
                    'guidance' => 'Enter the estimated price for one stated unit, in Philippine pesos, without multiplying it by the quantity.',
                    'examples' => ['250 when one ream costs PHP 250'],
                    'rules' => ['Quantity multiplied by Unit Cost produces the item total.'],
                    'related_fields' => ['Unit', 'Quantity', 'Item Total'],
                    'common_mistakes' => ['Entering the total price for all units instead of the price of one unit.', 'Including text or a currency symbol in a numeric input.'],
                    'calculations' => ['Item Total = Quantity × Unit Cost.'],
                ],
                'details' => [
                    'label' => 'Descriptions / Specifications / Details',
                    'patterns' => ['items.*.details', 'expense-details-*'],
                    'guidance' => 'Describe the specifications needed to justify the estimate and distinguish the item, such as size, capacity, material, service scope, or quality requirement. Avoid unnecessary brand restrictions unless technically justified.',
                ],
                'purpose' => [
                    'label' => 'Purpose in the Project',
                    'patterns' => ['items.*.purpose', 'expense-purpose-*'],
                    'guidance' => 'Explain how the expense supports a specific activity, objective, method, or output in the project.',
                    'examples' => ['For printing participant questionnaires used during field data collection'],
                ],
                'contingency' => [
                    'label' => 'Contingency Amount',
                    'patterns' => ['contingency', 'expense-unit-cost-*'],
                    'guidance' => 'Enter the proposed contingency as one peso amount and explain its project purpose. Regular particulars, unit, quantity, and detailed specifications are not required for this special row.',
                ],
            ],
        ],

        'line-item-budget' => [
            'label' => 'Attachment B: Line-Item Budget',
            'aliases' => ['Attachment B', 'line item budget', 'budget summary'],
            'purpose' => 'Stores the official summary budget by MOOE and Capital Outlay account and supplies budget totals to the Detailed Research Proposal.',
            'relationships' => [
                'Attachment B is the current authoritative source for the Detailed Research Proposal budget totals.',
                'The Estimated Expense Breakdown can prefill Attachment B, but the two papers remain independently editable and must agree before final submission.',
                'Total Project Cost must equal Total MOOE plus Total Capital Outlay.',
            ],
            'fields' => [
                'leader_unit' => [
                    'label' => 'Project Leader Campus and College',
                    'patterns' => ['leader_campus', 'leader_college'],
                    'guidance' => 'Identify the project leader’s campus and college for the official Attachment B heading.',
                ],
                'staff' => [
                    'label' => 'Project Staff',
                    'patterns' => ['staff', 'staff.*', 'staff.*.*'],
                    'guidance' => 'List the participating staff and their campus and college. Workspace members can be selected to reuse their stored institutional information.',
                ],
                'amounts' => [
                    'label' => 'Budget Line Amount',
                    'patterns' => ['amounts', 'amounts.*', 'amount-*'],
                    'guidance' => 'Enter the peso amount assigned to the most specific applicable MOOE or Capital Outlay line. Leave unrelated lines empty and enter numbers without commas.',
                    'rules' => ['Use amounts supported by the itemized Estimated Expense Breakdown.'],
                ],
                'custom_particular' => [
                    'label' => 'Custom Category or Sub-category',
                    'patterns' => ['custom_mooe_items.*.particular', 'custom_co_items.*.particular'],
                    'guidance' => 'Use a custom line only when the needed budget classification is not represented by the predefined lines. Enter the official or clearly descriptive particular.',
                ],
                'custom_amount' => [
                    'label' => 'Custom Line Amount',
                    'patterns' => ['custom_mooe_items.*.amount', 'custom_co_items.*.amount'],
                    'guidance' => 'Enter the peso amount for the accompanying custom particular. It contributes to the applicable section total.',
                ],
                'mooe_total_override' => [
                    'label' => 'Manual MOOE Total',
                    'patterns' => ['mooe_total_override'],
                    'guidance' => 'Use a manual total only when an authorized budget value cannot be represented by the visible lines. It must still agree with the Estimated Expense Breakdown before submission.',
                    'related_fields' => ['MOOE line amounts', 'Manual Total Project Cost', 'Estimated Expense Breakdown MOOE total'],
                    'common_mistakes' => ['Using an override to hide incomplete line amounts.', 'Changing Attachment B without reconciling the itemized Estimated Expense Breakdown.'],
                ],
                'co_total_override' => [
                    'label' => 'Manual Capital Outlay Total',
                    'patterns' => ['co_total_override'],
                    'guidance' => 'Use a manual total only when an authorized budget value cannot be represented by the visible lines. It must still agree with the Estimated Expense Breakdown before submission.',
                    'related_fields' => ['Capital Outlay line amounts', 'Manual Total Project Cost', 'Estimated Expense Breakdown Capital Outlay total'],
                    'common_mistakes' => ['Using an override to hide incomplete line amounts.', 'Changing Attachment B without reconciling the itemized Estimated Expense Breakdown.'],
                ],
                'project_total_override' => [
                    'label' => 'Manual Total Project Cost',
                    'patterns' => ['project_total_override'],
                    'guidance' => 'The project total is the sum of Total MOOE and Total Capital Outlay. A manually entered project total cannot differ from that sum.',
                    'rules' => ['Total Project Cost = Total MOOE + Total Capital Outlay.'],
                    'related_fields' => ['Manual MOOE Total', 'Manual Capital Outlay Total', 'Estimated Expense Breakdown Grand Total'],
                    'common_mistakes' => ['Entering a project total that differs from the effective MOOE plus Capital Outlay totals.'],
                    'calculations' => ['Total Project Cost = Total MOOE + Total Capital Outlay.'],
                ],
                'research_office' => [
                    'label' => 'Research Office Section',
                    'patterns' => ['level_of_call', 'approval_body', 'resolution_number', 'resolution_year'],
                    'guidance' => 'These optional fields record the level of call, approving body, and approval resolution details. Leave them blank unless the official approval information is known.',
                ],
            ],
        ],

        'curriculum-vitae' => [
            'label' => 'Attachment C: Curriculum Vitae',
            'aliases' => ['Attachment C', 'CV', 'curriculum vitae'],
            'purpose' => 'Creates one official curriculum-vitae block for every member of the research team.',
            'relationships' => [
                'Workspace account names and institutional emails are prefilled where available.',
                'When there is no saved CV yet, ATHENA also seeds people from the Project Leader and the latest saved Attachment B Project Staff list.',
                'After the CV is saved, its people remain independently editable and are not automatically rebuilt from later team changes.',
                'A separate CV block is generated for each listed person.',
            ],
            'fields' => [
                'personal_information' => [
                    'label' => 'Personal Information',
                    'patterns' => ['people.*.last_name', 'people.*.first_name', 'people.*.middle_name', 'people.*.agency', 'people.*.gender', 'people.*.birthday', 'people.*.street', 'people.*.barangay', 'people.*.municipality', 'people.*.province', 'people.*.landline', 'people.*.cellphone', 'people.*.email'],
                    'guidance' => 'Enter the person’s current identifying and contact information. Last Name and First Name are required; other fields may be left blank when not applicable or unavailable.',
                    'rules' => ['Landline and cellphone fields accept 11 digits in the current editor.'],
                ],
                'academic_background' => [
                    'label' => 'Academic Background',
                    'patterns' => ['people.*.academic_background.*.*'],
                    'guidance' => 'Add each degree or program, major field, sector, learning institution, study status, years, and thesis title where applicable.',
                    'rules' => ['When Status is Ongoing, Year Ended is shown as Present.'],
                ],
                'scholarships' => [
                    'label' => 'Scholarship',
                    'patterns' => ['people.*.scholarships.*.*'],
                    'guidance' => 'Record scholarship sponsors, coverage dates, extensions, supported expenses, approved and released amounts, and release date. Primary Sponsor means the main funding organization when more than one sponsor is involved.',
                ],
                'employment' => [
                    'label' => 'Employment',
                    'patterns' => ['people.*.employment.*.*'],
                    'guidance' => 'Record the employing agency, official plantilla position, appointment status, appointment period, and monthly salary for relevant employment.',
                ],
                'specializations' => [
                    'label' => 'Field of Specialization',
                    'patterns' => ['people.*.specializations.*.*'],
                    'guidance' => 'List the person’s established areas of expertise. Mark Primary Field as Yes only for the person’s main specialization.',
                ],
                'awards' => [
                    'label' => 'R&D Awards',
                    'patterns' => ['people.*.awards.*.*'],
                    'guidance' => 'List research-and-development awards, including title, rank, level or category, granting institution, and year granted.',
                ],
                'projects' => [
                    'label' => 'R&D Projects Headed or Conducted',
                    'patterns' => ['people.*.projects.*.*'],
                    'guidance' => 'List relevant R&D projects and identify the person’s designation, sector, current project status, and inclusive years.',
                ],
                'publications' => [
                    'label' => 'R&D Related Publications',
                    'patterns' => ['people.*.publications.*.*'],
                    'guidance' => 'List relevant publications from the last three years, including title, year, place of publication, publication group, and authoring role.',
                ],
                'presentations' => [
                    'label' => 'R&D Presentations',
                    'patterns' => ['people.*.presentations.*.*'],
                    'guidance' => 'List relevant research presentations from the last three years, including paper title, conference, category, date, venue, and sponsor.',
                ],
            ],
        ],

        'gad-checklist' => [
            'label' => 'GAD Generic Checklist',
            'aliases' => ['Box 7a', 'gender and development checklist', 'GAD checklist'],
            'purpose' => 'Provides the official Gender and Development checklist included with the initial proposal package.',
            'relationships' => [
                'ATHENA automatically fills the Project Title and Project Leader from Project Details.',
                'The current workspace does not ask the faculty user to encode the checklist’s evaluator fields.',
            ],
            'fields' => [
                'project_identity' => [
                    'label' => 'Project Title and Project Leader',
                    'patterns' => ['project_title', 'project_leader'],
                    'guidance' => 'These values are filled automatically from Project Details. Correct them in Project Details if they are wrong.',
                ],
                'checklist_items' => [
                    'label' => 'GAD Checklist Items',
                    'patterns' => ['checklist', 'gad'],
                    'guidance' => 'The generated paper preserves the official checklist. Follow the Research Office’s evaluation process; do not invent checklist scores or approvals in ATHENA.',
                ],
            ],
        ],

        'initial-screening-form' => [
            'label' => 'Initial Screening Form',
            'aliases' => ['screening form', 'initial evaluation form'],
            'purpose' => 'Provides the official form used by the Research Office and assigned evaluator during initial screening.',
            'relationships' => [
                'ATHENA automatically fills the Project Title and Project Leader from Project Details.',
                'Screening decisions, findings, and evaluator fields remain blank for the Research Office.',
            ],
            'fields' => [
                'project_identity' => [
                    'label' => 'Project Title and Project Leader',
                    'patterns' => ['project_title', 'project_leader'],
                    'guidance' => 'These values are filled automatically from Project Details. Correct them in Project Details if they are wrong.',
                ],
                'screening_fields' => [
                    'label' => 'Screening and Evaluator Fields',
                    'patterns' => ['screening', 'evaluation', 'evaluator'],
                    'guidance' => 'These fields are reserved for the Research Office or assigned evaluator and are intentionally not completed by the faculty proposal author in the ATHENA workspace.',
                ],
            ],
        ],
    ],
];
