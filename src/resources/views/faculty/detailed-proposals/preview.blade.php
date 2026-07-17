<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Detailed Research Proposal Content Preview</title>
        @vite('resources/css/app.css')
    </head>
    <body class="bg-gray-100 p-4 text-gray-950 sm:p-8">
        <main class="mx-auto max-w-4xl space-y-5 bg-white p-6 shadow-sm sm:p-10">
            <header class="border-b-2 border-gray-950 pb-4 text-center"><p class="text-xs font-bold">BatStateU-FO-RES-02 Rev. 04</p><h1 class="mt-2 text-xl font-black">DETAILED RESEARCH PROPOSAL</h1></header>
            <section><h2 class="font-black">I. Research Project Title</h2><p class="mt-1 whitespace-pre-line">{{ $detailedProposal['project_title'] }}</p></section>
            <section><h2 class="font-black">II. BatStateU Research Agenda</h2><p class="mt-1 whitespace-pre-line">{{ $detailedProposal['research_agenda'] }}</p></section>
            <section><h2 class="font-black">III. Sustainable Development Goal</h2><p class="mt-1">{{ collect($detailedProposal['sdgs'])->map(fn ($sdg) => 'SDG'.$sdg.': '.config('detailed_proposal.sdgs.'.$sdg))->join('; ') }}</p></section>
            <section><h2 class="font-black">IV. Project Leader and Staff</h2><p class="mt-1"><strong>{{ $detailedProposal['project_leader'] }}</strong> · {{ $detailedProposal['leader_email'] }} · {{ $detailedProposal['leader_contact'] }}</p>@foreach ($detailedProposal['staff'] as $member)<p><strong>{{ $member['name'] }}</strong> · {{ $member['email'] }} · {{ $member['contact'] }}</p>@endforeach</section>
            <section><h2 class="font-black">V. Proponent Agency</h2><p class="mt-1">{{ $detailedProposal['proponent_agency'] }} · {{ $detailedProposal['proponent_department'] }} · {{ $detailedProposal['proponent_college'] }} · {{ $detailedProposal['proponent_campus'] }}</p></section>
            <section><h2 class="font-black">VI. Cooperating Agency</h2><p class="mt-1">{{ $detailedProposal['cooperating_agency'] ?: 'None' }}</p></section>
            @foreach (['executive_brief' => 'VII. Executive Brief', 'rationale' => 'VIII. Rationale', 'objectives' => 'IX. Objectives of the Project'] as $key => $heading)<section><h2 class="font-black">{{ $heading }}</h2><p class="mt-1 whitespace-pre-line text-justify leading-6">{{ $detailedProposal[$key] }}</p></section>@endforeach
            <section><h2 class="font-black">X. Expected Output of the Project</h2>@foreach (config('detailed_proposal.expected_outputs') as $key => $label)<p class="mt-1 whitespace-pre-line"><strong>{{ $label }}:</strong> {{ $detailedProposal['expected_outputs'][$key] }}</p>@endforeach</section>
            <section><h2 class="font-black">XI. Review of Related Literature</h2><p class="mt-1 whitespace-pre-line text-justify leading-6">{{ $detailedProposal['related_literature'] }}</p></section>
            <section><h2 class="font-black">XII. Methodology</h2>@foreach (config('detailed_proposal.methodology') as $key => $label)<h3 class="mt-3 font-bold">{{ $label }}</h3><p class="whitespace-pre-line text-justify leading-6">{{ $detailedProposal['methodology'][$key] }}</p>@endforeach</section>
            <section><h2 class="font-black">XIII. Duties and Responsibilities of Each Member</h2>@foreach ($detailedProposal['responsibilities'] as $responsibility)<h3 class="mt-3 font-bold">{{ $loop->iteration }}. {{ $responsibility['name'] }}</h3><p class="whitespace-pre-line text-justify leading-6">{{ $responsibility['duties'] }}</p>@endforeach</section>
            <section><h2 class="font-black">XIV–XV. Attachments and Budget</h2><p class="mt-1">Work Plan: See attached Form A</p><p>Maintenance and Operating Expenses: Php {{ number_format($detailedProposal['mooe_total'], 2) }}</p><p>Capital Outlay and Equipment: Php {{ number_format($detailedProposal['co_total'], 2) }}</p></section>
            <section><h2 class="font-black">XVI. References</h2><p class="mt-1 whitespace-pre-line leading-6">{{ $detailedProposal['references'] }}</p></section>
            <section><h2 class="font-black">XVII. Curriculum Vitae</h2><p class="mt-1">See attached Form C</p></section>
        </main>
    </body>
</html>
