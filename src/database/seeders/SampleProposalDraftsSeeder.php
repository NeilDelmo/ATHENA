<?php

namespace Database\Seeders;

use App\Actions\SubmitProposalDraft;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftLiteratureSource;
use App\Models\ResearchCall;
use App\Models\ResearchCategory;
use App\Models\User;
use App\Support\LineItemBudgetData;
use App\Support\ProposalDraftReadiness;
use Illuminate\Database\Seeder;
use RuntimeException;

class SampleProposalDraftsSeeder extends Seeder
{
    private const DRAFTS_PER_USER = 10;

    private const CALL_CLOSES_AT = '2026-12-31 23:59:59';

    private const PROPONENTS = [
        ['campus' => 'Alangilan Campus', 'college' => 'College of Informatics and Computing Sciences', 'department' => 'Department of Information Technology'],
        ['campus' => 'Alangilan Campus', 'college' => 'College of Informatics and Computing Sciences', 'department' => 'Department of Computer Science'],
        ['campus' => 'Alangilan Campus', 'college' => 'College of Engineering', 'department' => 'Department of Electronics Engineering'],
        ['campus' => 'ARASOF-Nasugbu Campus', 'college' => 'College of Accountancy, Business, Economics and International Hospitality Management', 'department' => 'Department of Entrepreneurship'],
    ];

    private ProposalDraftReadiness $readiness;

    private SubmitProposalDraft $submitAction;

    /**
     * @var list<array{title: string, domain: string, output: string, agenda: string, sdgs: list<int>, objectives: list<string>, activities: list<string>, outputs: list<string>, reference: string}>
     */
    private array $projects = [
        [
            'title' => 'IoT-Based Flood Monitoring and Early Warning System for Coastal Barangays',
            'domain' => 'disaster preparedness and environmental monitoring',
            'output' => 'a solar-powered flood monitoring and early warning platform',
            'agenda' => 'Smart Communities and Digital Transformation',
            'sdgs' => [11, 13, 9],
            'objectives' => ['Assess flood-prone zones and current warning practices', 'Deploy sensor nodes and an alerting platform', 'Evaluate alert accuracy and community response'],
            'activities' => ['Map flood-prone areas and install sensor nodes', 'Build the monitoring dashboard and public alert channel', 'Run community drills and analyze system performance'],
            'outputs' => ['Validated flood risk and sensor placement study', 'Operational early warning system with public alerts', 'Community drill results and deployment guide'],
            'reference' => 'Reyes, P. (2025). Low-cost sensor networks for flood-prone communities. Philippine Engineering Journal, 47(1), 12-29.',
        ],
        [
            'title' => 'Machine Learning Approach to Rice Yield Prediction Using Weather and Soil Data',
            'domain' => 'agricultural analytics',
            'output' => 'a rice yield prediction model with a farmer-friendly advisory interface',
            'agenda' => 'Agriculture, Food Security, and Climate Resilience',
            'sdgs' => [2, 13, 9],
            'objectives' => ['Collect weather, soil, and yield records from partner farms', 'Train and benchmark yield prediction models', 'Pilot advisories with farmers and measure usefulness'],
            'activities' => ['Collect and clean multi-season farm datasets', 'Train and benchmark prediction models', 'Run advisory pilots and analyze farmer feedback'],
            'outputs' => ['Cleaned multi-season farm dataset', 'Validated yield prediction models', 'Advisory pilot report with farmer feedback'],
            'reference' => 'Villamor, C. (2024). Machine learning for tropical crop yield estimation. Agriculture and Natural Resources, 18(3), 55-70.',
        ],
        [
            'title' => 'Development of a University Chatbot for Student Services Using Natural Language Processing',
            'domain' => 'student services automation',
            'output' => 'a multilingual campus services chatbot integrated with student records',
            'agenda' => 'Smart Communities and Digital Transformation',
            'sdgs' => [4, 9, 16],
            'objectives' => ['Analyze frequent student service inquiries and workflows', 'Develop the chatbot with staff escalation support', 'Measure resolution rates and student satisfaction'],
            'activities' => ['Audit service tickets and build conversation intents', 'Implement the chatbot and escalation workflow', 'Pilot with students and analyze transcripts'],
            'outputs' => ['Deployed campus services chatbot', 'Intent and escalation playbook', 'Pilot evaluation with satisfaction metrics'],
            'reference' => 'Aquino, L. (2025). Conversational agents in higher education services. International Journal of Educational Technology, 22(2), 88-104.',
        ],
        [
            'title' => 'Solar-Powered Smart Irrigation System for Smallholder Vegetable Farms',
            'domain' => 'sustainable agriculture technology',
            'output' => 'a solar-powered irrigation controller with soil moisture sensing',
            'agenda' => 'Agriculture, Food Security, and Climate Resilience',
            'sdgs' => [2, 6, 9],
            'objectives' => ['Profile irrigation schedules of partner vegetable farms', 'Fabricate and install solar irrigation controllers', 'Measure water savings and crop outcomes'],
            'activities' => ['Survey partner farms and design irrigation profiles', 'Fabricate, install, and calibrate controller prototypes', 'Run a cropping-season pilot and analyze results'],
            'outputs' => ['Installed solar irrigation units', 'Water and yield savings report', 'Replication and maintenance guide'],
            'reference' => 'Mendoza, R. (2024). Photovoltaic irrigation for tropical smallholders. Journal of Agricultural Engineering, 55(2), 33-48.',
        ],
        [
            'title' => 'Blockchain-Based Academic Credential Verification Platform',
            'domain' => 'secure digital records',
            'output' => 'a tamper-evident credential verification service for the university',
            'agenda' => 'Smart Communities and Digital Transformation',
            'sdgs' => [9, 16, 17],
            'objectives' => ['Map credential issuance and verification workflows', 'Implement the blockchain-backed verification service', 'Evaluate verification time and forgery resistance'],
            'activities' => ['Model the credential lifecycle and stakeholders', 'Implement issuance and verification services', 'Run registrar and employer pilot testing'],
            'outputs' => ['Live credential verification service', 'Issuer integration guide', 'Pilot evaluation with verification metrics'],
            'reference' => 'Lim, K. (2025). Distributed ledgers for academic credentials. Computers and Security Advances, 12(1), 5-21.',
        ],
        [
            'title' => 'Community-Based Solid Waste Segregation Tracking with Mobile Reporting',
            'domain' => 'environmental management',
            'output' => 'a barangay waste segregation tracking and incentive platform',
            'agenda' => 'Environment, Climate Change, and Climate Resilience',
            'sdgs' => [11, 12, 6],
            'objectives' => ['Audit current waste segregation and collection flows', 'Deploy the tracking and incentive platform', 'Measure segregation compliance over collection cycles'],
            'activities' => ['Audit waste flows in partner barangays', 'Develop the mobile reporting and incentives app', 'Run a three-month compliance pilot'],
            'outputs' => ['Segregation baseline and flow audit', 'Deployed tracking platform', 'Compliance and diversion report'],
            'reference' => 'Domingo, F. (2024). Behavioral nudges for household waste segregation. Waste Management and Research, 42(3), 210-225.',
        ],
        [
            'title' => 'AI-Assisted Reading Comprehension Tool for Elementary Learners',
            'domain' => 'educational technology',
            'output' => 'an adaptive reading comprehension practice tool for early graders',
            'agenda' => 'Quality Education and Human Development',
            'sdgs' => [4, 5, 10],
            'objectives' => ['Assess reading levels of participating pupils', 'Develop the adaptive comprehension tool', 'Measure reading gains over one school quarter'],
            'activities' => ['Run baseline reading assessments in partner schools', 'Build and iterate the adaptive practice tool', 'Run classroom pilots and analyze gains'],
            'outputs' => ['Validated reading level baseline', 'Adaptive comprehension tool', 'Quasi-experimental learning gains report'],
            'reference' => 'Bautista, H. (2025). Adaptive reading tools for Filipino classrooms. Asia Pacific Reading Research, 9(1), 40-58.',
        ],
        [
            'title' => 'Low-Cost Water Quality Sensor Network for Local River Systems',
            'domain' => 'environmental monitoring',
            'output' => 'a distributed water quality sensing and reporting network',
            'agenda' => 'Environment, Climate Change, and Climate Resilience',
            'sdgs' => [6, 14, 13],
            'objectives' => ['Characterize sampling needs of partner river sites', 'Fabricate and deploy low-cost sensor buoys', 'Validate readings against laboratory baselines'],
            'activities' => ['Select sites and gather laboratory baseline samples', 'Fabricate, calibrate, and deploy sensor buoys', 'Correlate sensor and laboratory measurements'],
            'outputs' => ['Calibrated sensor buoy fleet', 'River water quality dataset', 'Correlation and deployment cost report'],
            'reference' => 'Sarmiento, J. (2024). Low-cost turbidity and pH sensing for tropical rivers. Environmental Monitoring and Assessment, 196(8), 77-93.',
        ],
        [
            'title' => 'Digital Marketplace and Logistics Matching for Local Fisherfolk Catch',
            'domain' => 'community commerce platforms',
            'output' => 'a catch-to-market digital marketplace with delivery matching',
            'agenda' => 'Smart Communities and Digital Transformation',
            'sdgs' => [1, 8, 14],
            'objectives' => ['Document fisherfolk selling channels and buyer demand', 'Build the marketplace and logistics matching service', 'Pilot transactions and measure income effects'],
            'activities' => ['Interview fisherfolk associations and institutional buyers', 'Implement marketplace and matching modules', 'Run live transaction pilots and analyze results'],
            'outputs' => ['Deployed marketplace platform', 'Logistics matching playbook', 'Pilot income and turnaround report'],
            'reference' => 'Padilla, S. (2025). Digital market access for artisanal fisheries. Marine Policy Studies, 31(1), 66-82.',
        ],
        [
            'title' => 'Computer Vision System for Automated Attendance in Large Lecture Classes',
            'domain' => 'applied artificial intelligence',
            'output' => 'a privacy-aware automated classroom attendance system',
            'agenda' => 'Smart Communities and Digital Transformation',
            'sdgs' => [4, 8, 9],
            'objectives' => ['Benchmark recognition accuracy in high-density classrooms', 'Integrate recognition with enrollment records', 'Evaluate accuracy, throughput, and privacy acceptance'],
            'activities' => ['Collect consented classroom image datasets', 'Train and optimize recognition models', 'Run parallel manual and automated attendance trials'],
            'outputs' => ['Consented classroom benchmark dataset', 'Attendance recognition service', 'Accuracy and privacy acceptance report'],
            'reference' => 'Gonzales, T. (2024). Vision-based attendance in high-density classrooms. IEEE Access Education, 12, 33012-33028.',
        ],
        [
            'title' => 'Mental Health Chatbot for Stress Management Among College Students',
            'domain' => 'student wellness technology',
            'output' => 'a guided self-help chatbot for stress and coping support',
            'agenda' => 'Quality Education and Human Development',
            'sdgs' => [3, 4, 5],
            'objectives' => ['Assess student stress profiles and support barriers', 'Develop guided coping conversation flows', 'Evaluate stress outcomes and usability'],
            'activities' => ['Run baseline wellness surveys and focus groups', 'Build and clinically review conversation flows', 'Pilot with volunteers and measure outcomes'],
            'outputs' => ['Baseline wellness assessment', 'Clinically reviewed coping library', 'Pilot outcomes and referral workflow'],
            'reference' => 'Villanueva, M. (2025). Conversational agents for campus mental health. Philippine Journal of Psychology, 58(1), 22-40.',
        ],
        [
            'title' => 'Telehealth Triage Assistant for Rural Health Unit Queue Management',
            'domain' => 'primary health care',
            'output' => 'a triage assistant that prioritizes rural health unit consultations',
            'agenda' => 'Health Systems and Community Wellness',
            'sdgs' => [3, 9, 10],
            'objectives' => ['Study triage workflows in partner health units', 'Implement the triage and queue assistant', 'Compare waiting times and triage accuracy'],
            'activities' => ['Shadow triage nurses and encode workflow rules', 'Build the assistant and clinic dashboard', 'Run paired trials across clinic days'],
            'outputs' => ['Triage workflow reference model', 'Deployed triage assistant', 'Waiting time and safety evaluation'],
            'reference' => 'Cruz, A. (2024). Digital triage in resource-limited clinics. Journal of Primary Care and Community Health, 15(2), 71-88.',
        ],
        [
            'title' => 'Mobile Nutrition Tracking App for Pregnant and Lactating Mothers',
            'domain' => 'maternal and child nutrition',
            'output' => 'an offline-capable nutrition tracking companion for mothers',
            'agenda' => 'Health Systems and Community Wellness',
            'sdgs' => [2, 3, 5],
            'objectives' => ['Assess nutrition information needs of partner mothers', 'Develop the tracking and reminder application', 'Measure app use and nutrition knowledge change'],
            'activities' => ['Interview mothers and barangay nutrition scholars', 'Develop the offline-first tracking app', 'Run an eight-week usage study'],
            'outputs' => ['Needs assessment report', 'Offline-first nutrition app', 'Knowledge and usage evaluation'],
            'reference' => 'Reyes, B. (2025). Mobile support for maternal nutrition in low-resource settings. Maternal and Child Nutrition Journal, 21(1), 9-26.',
        ],
        [
            'title' => 'Sensor-Based Dengue Hotspot Mapping Using Mosquito Trap Data',
            'domain' => 'public health surveillance',
            'output' => 'a dengue hotspot mapping service driven by smart trap counts',
            'agenda' => 'Health Systems and Community Wellness',
            'sdgs' => [3, 6, 11],
            'objectives' => ['Deploy smart ovitraps across partner barangays', 'Build hotspot detection and alerting maps', 'Correlate trap data with reported dengue cases'],
            'activities' => ['Install and service smart ovitrap sites', 'Develop the mapping and alerting dashboard', 'Validate correlations over one dengue season'],
            'outputs' => ['Smart trap deployment dataset', 'Hotspot mapping dashboard', 'Case correlation and alerting study'],
            'reference' => 'Navarro, E. (2024). Ovitrap-based dengue risk mapping. Acta Tropica Regional Reports, 19(2), 55-72.',
        ],
        [
            'title' => 'E-Learning Micro-Course Platform for Senior High STEM Review',
            'domain' => 'learning platforms',
            'output' => 'an offline-friendly micro-course platform for STEM entrance review',
            'agenda' => 'Quality Education and Human Development',
            'sdgs' => [4, 9, 10],
            'objectives' => ['Design micro-course modules from past STEM assessments', 'Build the low-bandwidth learning platform', 'Measure completion and mock exam improvement'],
            'activities' => ['Map assessment domains and write micro-lessons', 'Implement the platform with offline sync', 'Run a cohort study with partner schools'],
            'outputs' => ['STEM micro-course library', 'Offline-first learning platform', 'Completion and improvement report'],
            'reference' => 'Torres, D. (2025). Micro-learning for entrance exam preparation. Asian Journal of Distance Education, 20(1), 88-105.',
        ],
        [
            'title' => 'Smart Aquaculture Water Quality and Feeding Automation for Tilapia Ponds',
            'domain' => 'aquaculture technology',
            'output' => 'a pond water quality monitor with automated feeding control',
            'agenda' => 'Agriculture, Food Security, and Climate Resilience',
            'sdgs' => [2, 9, 14],
            'objectives' => ['Profile pond water quality and feeding routines', 'Deploy monitors and automated feeders', 'Compare growth and feed conversion outcomes'],
            'activities' => ['Instrument partner ponds with water sensors', 'Implement monitoring and feeding automation', 'Run one production cycle comparison'],
            'outputs' => ['Instrumented pond testbed', 'Automated feeding controller', 'Growth and feed conversion report'],
            'reference' => 'Salvador, P. (2024). Automation in small-scale tilapia aquaculture. Aquacultural Engineering Reports, 44(2), 101-118.',
        ],
        [
            'title' => 'Bamboo Fiber Reinforcement Study for Low-Cost Housing Panels',
            'domain' => 'sustainable construction materials',
            'output' => 'a bamboo-fiber reinforced wall panel prototype with testing data',
            'agenda' => 'Sustainable Industrialization and Infrastructure',
            'sdgs' => [9, 11, 12],
            'objectives' => ['Characterize local bamboo fiber treatment methods', 'Fabricate and test wall panel specimens', 'Model cost and thermal performance for housing'],
            'activities' => ['Treat and prepare fiber specimens', 'Cast and mechanically test panel batches', 'Model cost and thermal performance'],
            'outputs' => ['Material characterization dataset', 'Panel prototype test results', 'Cost and performance model'],
            'reference' => 'Fernandez, G. (2024). Bamboo composites for structural wall panels. Construction and Building Materials Letters, 27(3), 140-155.',
        ],
        [
            'title' => 'Offline-First Clinic Records System for Barangay Health Stations',
            'domain' => 'health information systems',
            'output' => 'an offline-first patient records system for barangay health stations',
            'agenda' => 'Smart Communities and Digital Transformation',
            'sdgs' => [3, 9, 16],
            'objectives' => ['Document health station recordkeeping workflows', 'Implement the offline-first records system', 'Evaluate record completeness and retrieval time'],
            'activities' => ['Observe health station workflows and forms', 'Build the offline-first records application', 'Run paired station trials and analyze records'],
            'outputs' => ['Records workflow reference model', 'Offline-first records system', 'Completeness and retrieval evaluation'],
            'reference' => 'Ocampo, N. (2025). Offline-first health records for last-mile clinics. Health Informatics Journal, 29(2), 33-51.',
        ],
        [
            'title' => 'Gamified Disaster Preparedness Training for Coastal Communities',
            'domain' => 'community resilience education',
            'output' => 'a gamified preparedness training kit for coastal barangays',
            'agenda' => 'Environment, Climate Change, and Climate Resilience',
            'sdgs' => [4, 11, 13],
            'objectives' => ['Baseline community preparedness knowledge', 'Develop the gamified training kit', 'Measure preparedness knowledge retention'],
            'activities' => ['Run baseline preparedness assessments', 'Design and build the gamified training kit', 'Deliver training sessions and analyze retention'],
            'outputs' => ['Preparedness baseline study', 'Gamified training kit', 'Retention evaluation report'],
            'reference' => 'Marasigan, O. (2024). Game-based learning for disaster risk reduction. International Journal of Disaster Risk Reduction, 13(2), 60-77.',
        ],
        [
            'title' => 'Predictive Maintenance Dashboard for University Laboratory Equipment',
            'domain' => 'facilities intelligence',
            'output' => 'a predictive maintenance dashboard for shared laboratory equipment',
            'agenda' => 'Smart Communities and Digital Transformation',
            'sdgs' => [4, 9, 12],
            'objectives' => ['Compile failure and maintenance histories of laboratory units', 'Implement the predictive maintenance dashboard', 'Evaluate downtime reduction across one semester'],
            'activities' => ['Compile maintenance logs and failure histories', 'Build the failure prediction and scheduling dashboard', 'Pilot scheduling with laboratory technicians'],
            'outputs' => ['Maintenance history dataset', 'Predictive dashboard service', 'Downtime and cost evaluation'],
            'reference' => 'Ramos, K. (2025). Condition-based maintenance in academic laboratories. Journal of Facilities Management, 23(1), 44-61.',
        ],
    ];

    public function run(): void
    {
        $this->readiness = app(ProposalDraftReadiness::class);
        $this->submitAction = app(SubmitProposalDraft::class);

        $call = ResearchCall::query()->firstOrFail();

        if ($call->closes_at === null || $call->closes_at->isPast()) {
            $call->update(['closes_at' => self::CALL_CLOSES_AT]);
            $this->command?->info('Extended research call window to '.self::CALL_CLOSES_AT.'.');
        }

        $categories = collect([
            'Information and Communications Technology',
            'Health and Wellness',
            'Agriculture and Food Security',
            'Environment and Climate Resilience',
            'Education and Human Development',
        ])->map(fn (string $name): ResearchCategory => ResearchCategory::query()->firstOrCreate(['name' => $name]));
        $call->categories()->sync($categories->pluck('id'));
        $this->command?->info('Seeded '.$categories->count().' research categories and linked them to the call.');

        $signatorySelections = ProposalDraft::query()
            ->whereNotNull('signatory_selections')
            ->orderByDesc('id')
            ->value('signatory_selections');

        if (! is_array($signatorySelections)) {
            throw new RuntimeException('No signatory selections found to copy from existing drafts.');
        }

        $users = User::query()
            ->whereHas('roles', fn ($roles) => $roles->where('name', 'faculty'))
            ->orderBy('id')
            ->get();

        foreach ($users as $userIndex => $user) {
            $this->command?->info('Preparing drafts for '.$user->name.'.');

            for ($i = 0; $i < self::DRAFTS_PER_USER; $i++) {
                $this->seedDraft($user, $userIndex, $i, $call, $signatorySelections);
            }
        }

        $this->command?->info('Done. Total drafts in database: '.ProposalDraft::query()->count().'.');
    }

    private function seedDraft(User $user, int $userIndex, int $index, ResearchCall $call, array $signatorySelections): void
    {
        $project = $this->projects[$userIndex * self::DRAFTS_PER_USER + $index];

        if (ProposalDraft::query()->where('project_title', $project['title'])->exists()) {
            $this->command?->warn('Skipped (already exists): '.$project['title']);

            return;
        }

        $duration = 6 + ($index % 7);
        $plannedStart = now()->addDays(14 + $index * 3)->startOfDay();
        $plannedEnd = (clone $plannedStart)->addMonths($duration)->subDay();

        $draft = ProposalDraft::query()->create([
            'user_id' => $user->getKey(),
            'research_call_id' => $call->getKey(),
            'project_title' => $project['title'],
            'duration_months' => $duration,
            'planned_start' => $plannedStart,
            'planned_end' => $plannedEnd,
            'project_leader' => $user->name,
            'signatory_selections' => $signatorySelections,
        ]);

        $items = $this->expenseItems($duration, $userIndex, $index);
        $mooe = round(collect($items)->where('category', 'mooe')->sum(fn (array $item): float => round($item['quantity'] * $item['unit_cost'], 2)), 2);
        $co = round(collect($items)->where('category', 'capital_outlay')->sum(fn (array $item): float => round($item['quantity'] * $item['unit_cost'], 2)), 2);
        $total = round($mooe + $co, 2);

        $selections = $draft->signatory_selections;

        $sources = [
            'detailed_proposal' => $this->detailedProposalSource($draft, $user, $project, $index),
            'work_plan' => $this->workPlanSource($draft, $project, $selections),
            'expense_breakdown' => [
                'items' => $items,
                'project_title' => $project['title'],
            ],
            'line_item_budget' => [
                'amounts' => LineItemBudgetData::synchronizedAmountsFromExpenseBreakdown($items),
                'mooe_total' => $mooe,
                'co_total' => $co,
                'project_total' => $total,
                'computed_mooe_total' => $mooe,
                'computed_co_total' => $co,
                'computed_project_total' => $total,
                'planned_start' => $plannedStart->toDateString(),
                'planned_end' => $plannedEnd->toDateString(),
                'certified_by' => $selections['certified_by']['name'] ?? null,
                'certified_role' => $selections['certified_by']['position'] ?? null,
                'project_title' => $project['title'],
                'project_leader' => $user->name,
            ],
            'curriculum_vitae' => [
                'people' => [$this->cvPerson($user)],
            ],
        ];

        foreach ([
            'detailed_proposal',
            'work_plan',
            'expense_breakdown',
            'line_item_budget',
            'curriculum_vitae',
        ] as $documentType) {
            $draft->documents()->create([
                'document_type' => $documentType,
                'position' => 0,
                'source_data' => $sources[$documentType],
                'completed_at' => now(),
                'lock_version' => 0,
            ]);
        }

        $this->seedMembers($draft, $userIndex, $index);
        $this->seedLiterature($draft, $project);

        $draft->refresh();
        $errors = $this->readiness->errors($draft);

        if ($errors !== []) {
            throw new RuntimeException($project['title'].' is not ready: '.json_encode($errors));
        }

        $this->submitAction->prepare($draft, $user);
        $draft->refresh();

        if (! $this->readiness->isReady($draft)) {
            throw new RuntimeException($project['title'].' failed preparation checks.');
        }

        $staged = $draft->documents()->whereNotNull('file_path')->count();

        if ($staged < 7) {
            throw new RuntimeException($project['title'].' only staged '.$staged.' PDFs.');
        }

        $this->command?->info('Draft ready: '.$project['title'].' ('.$staged.'/7 PDFs staged)');
    }

    /**
     * @param  array{title: string, domain: string, output: string, agenda: string, sdgs: list<int>, objectives: list<string>, activities: list<string>, outputs: list<string>, reference: string}  $project
     * @return array<string, mixed>
     */
    private function detailedProposalSource(ProposalDraft $draft, User $user, array $project, int $index): array
    {
        $proponent = self::PROPONENTS[$index % count(self::PROPONENTS)];

        return [
            'project_title' => $project['title'],
            'project_leader' => $user->name,
            'leader_title' => 'Mr.',
            'leader_email' => $user->email,
            'leader_contact' => '0917'.str_pad((string) ($index + 1), 7, '0', STR_PAD_LEFT),
            'research_agenda' => $project['agenda'],
            'sdgs' => $project['sdgs'],
            'staff' => [],
            'proponent_department' => $proponent['department'],
            'proponent_college' => $proponent['college'],
            'proponent_campus' => $proponent['campus'],
            'cooperating_agency' => '',
            'executive_brief' => 'This project will design, build, and evaluate '.$project['output'].' to improve how '.$project['domain'].' is carried out in partner communities and campus units.',
            'rationale' => 'Current practice in '.$project['domain'].' still depends on manual processes and fragmented records. This leads to slow turnaround, limited visibility, and inconsistent reporting. A structured and technology-enabled approach can shorten processing time, strengthen traceability, and make outcomes easier to measure while keeping stakeholders in control of decisions.',
            'general_objective' => 'Develop and evaluate '.$project['output'].'.',
            'specific_objectives' => collect($project['objectives'])->map(fn (string $objective): array => ['description' => $objective])->all(),
            'expected_outputs' => [
                'publication' => [['description' => 'One peer-reviewed research article']],
                'patent' => [],
                'product' => [['description' => ucfirst($project['output'])]],
                'people_service' => [['description' => 'A validated usability and acceptance report from pilot participants']],
                'place_partnership' => [['description' => 'A working partnership between the university and participating community sites']],
                'policy' => [['description' => 'An adoption guide for university and community stakeholders']],
                'social_impact' => [['description' => 'Improved access to timely and reliable services for the target community']],
                'economic_impact' => [['description' => 'More efficient use of institutional and community resources']],
            ],
            'introduction' => 'The project '.$project['title'].' responds to persistent gaps in '.$project['domain'].', where fragmented processes limit reliability and responsiveness. This study designs and evaluates a practical solution grounded in the day-to-day needs of its intended users.',
            'related_literature' => 'Recent studies associate well-designed interventions in '.$project['domain'].' with measurable efficiency, quality, and satisfaction gains. Adoption depends on usability, trust, accessibility, and the readiness of the people who will use the system.',
            'methodology' => [
                'research_design' => 'The study will use a developmental research design with mixed-method evaluation.',
                'specific_methods' => 'Researchers will review existing workflows, co-design the solution with stakeholders, implement it iteratively, and evaluate it through controlled pilot testing.',
                'data_analysis' => 'Quantitative results will be summarized using descriptive statistics, while qualitative feedback will undergo thematic analysis.',
            ],
            'responsibilities' => [
                [
                    'name' => $user->name,
                    'percentage' => 100,
                    'duties' => 'Leads project planning, implementation, quality assurance, data analysis, and reporting.',
                ],
            ],
            'checked_verified_by_name' => $draft->signatory_selections['checked_verified_by_name']['name'] ?? null,
            'recommending_approval_name' => $draft->signatory_selections['recommending_approval_name']['name'] ?? null,
            'approved_by_name' => $draft->signatory_selections['approved_by_name']['name'] ?? null,
            'references' => $project['reference'].' Santos, M. (2025). Applied research methods for community-centered technology. Philippine Journal of Science, 154(2), 101-115.',
        ];
    }

    /**
     * @param  array{title: string, domain: string, output: string, agenda: string, sdgs: list<int>, objectives: list<string>, activities: list<string>, outputs: list<string>, reference: string}  $project
     * @param  array<string, mixed>  $selections
     * @return array<string, mixed>
     */
    private function workPlanSource(ProposalDraft $draft, array $project, array $selections): array
    {
        $duration = (int) $draft->duration_months;
        $base = intdiv($duration, 3);
        $remainder = $duration % 3;
        $entries = [];
        $start = 1;

        foreach ($project['objectives'] as $entryIndex => $objective) {
            $size = $base + ($entryIndex < $remainder ? 1 : 0);
            $months = range($start, $start + $size - 1);
            $start += $size;

            $entries[] = [
                'objective' => $objective,
                'activity' => $project['activities'][$entryIndex],
                'expected_output' => $project['outputs'][$entryIndex],
                'months' => $months,
            ];
        }

        return [
            'project_title' => $draft->project_title,
            'total_duration_months' => $duration,
            'planned_start' => $draft->planned_start?->toDateString(),
            'planned_end' => $draft->planned_end?->toDateString(),
            'entries' => $entries,
            'prepared_by' => $draft->project_leader,
            'verified_by' => $selections['verified_by']['name'] ?? null,
            'verified_role' => $selections['verified_by']['position'] ?? null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function expenseItems(int $duration, int $userIndex, int $index): array
    {
        $telephoneProfiles = [500, 450, 600, 400, 550];
        $suppliesProfiles = [1800, 2200, 2500, 2000, 1500];
        $ictProfiles = [18500, 21000, 24000];

        $items = [
            [
                'category' => 'mooe',
                'account' => 'Communication Expenses',
                'sub_account' => 'Telephone Expenses',
                'particulars' => 'Mobile data and communication allowance',
                'details' => 'Monthly project coordination and data communication',
                'purpose' => 'Support project coordination, field communication, and secure data transfer',
                'unit' => 'month',
                'quantity' => $duration,
                'unit_cost' => $telephoneProfiles[$userIndex % 5],
            ],
            [
                'category' => 'mooe',
                'account' => 'Supplies and Materials Expenses',
                'sub_account' => 'Office Supplies Expenses',
                'particulars' => 'Office and field supplies',
                'details' => 'Bond paper, folders, printer ink, and recording supplies',
                'purpose' => 'Documentation and field data collection materials',
                'unit' => 'lot',
                'quantity' => 2,
                'unit_cost' => $suppliesProfiles[$index % 5],
            ],
        ];

        if ($index % 3 !== 2) {
            $items[] = [
                'category' => 'capital_outlay',
                'account' => 'Machinery and Equipment Outlay',
                'sub_account' => 'ICT Equipment',
                'particulars' => 'Field laptop for data collection',
                'details' => 'Core i5 laptop with 16GB memory for field deployment',
                'purpose' => 'Primary workstation for data collection and analysis',
                'unit' => 'unit',
                'quantity' => 1,
                'unit_cost' => $ictProfiles[$index % 3],
            ];
        }

        return $items;
    }

    private function cvPerson(User $user): array
    {
        $segments = explode(' ', trim($user->name));
        $lastName = array_pop($segments);
        $firstName = implode(' ', $segments);

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'middle_name' => '',
            'email' => $user->email,
            'cellphone' => '09171234567',
            'landline' => '',
            'agency' => 'Batangas State University',
            'gender' => '',
            'birthday' => '',
            'street' => '',
            'barangay' => '',
            'municipality' => '',
            'province' => '',
            'specializations' => [],
            'academic_background' => [],
            'scholarships' => [],
            'employment' => [],
            'awards' => [],
            'publications' => [],
            'presentations' => [],
            'projects' => [],
        ];
    }

    private function seedMembers(ProposalDraft $draft, int $userIndex, int $index): void
    {
        $pool = [
            ['name' => 'Prof. Elena R. Marquez', 'email' => '23-55011@g.batstate-u.edu.ph'],
            ['name' => 'Dr. Rafael T. Ong', 'email' => '23-55012@g.batstate-u.edu.ph'],
            ['name' => 'Ms. Karla J. Reyes', 'email' => '23-55013@g.batstate-u.edu.ph'],
        ];

        foreach ($pool as $poolIndex => $member) {
            if (($index + $poolIndex) % 2 === 0) {
                $draft->members()->create([
                    'user_id' => null,
                    'name' => $member['name'],
                    'email' => $member['email'],
                ]);
            }
        }
    }

    /**
     * @param  array{title: string, domain: string, output: string, agenda: string, sdgs: list<int>, objectives: list<string>, activities: list<string>, outputs: list<string>, reference: string}  $project
     */
    private function seedLiterature(ProposalDraft $draft, array $project): void
    {
        $entries = [
            [
                'title' => 'A systematic review of '.$project['domain'].' interventions',
                'authors' => 'Dela Cruz, J., & Bautista, R.',
                'publication_year' => 2024,
                'provider' => 'OpenAlex',
            ],
            [
                'title' => 'Practical guidance for community-based '.$project['domain'].' projects',
                'authors' => 'Santos, M., & Villareal, P.',
                'publication_year' => 2025,
                'provider' => 'Europe PMC',
            ],
        ];

        foreach ($entries as $entry) {
            $draft->literatureSources()->create([
                'saved_by' => $draft->user_id,
                'fingerprint' => hash('sha256', $entry['title'].$entry['authors']),
                'title' => $entry['title'],
                'authors' => $entry['authors'],
                'publication_year' => $entry['publication_year'],
                'provider' => $entry['provider'],
                'rrl_draft_status' => ProposalDraftLiteratureSource::DRAFT_SAVED,
            ]);
        }
    }
}
