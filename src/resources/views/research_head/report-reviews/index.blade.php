<x-app-layout>
    <x-slot name="header">
        <x-page-header class="rh-review-page-header [&_h1]:text-3xl [&_p]:text-base [&_p]:leading-6" title="Report reviews" subtitle="Review submitted implementation reports and follow up on corrections." />
    </x-slot>
    @include('research_head.report-reviews.queue')
</x-app-layout>
