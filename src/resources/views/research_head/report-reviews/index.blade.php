<x-app-layout>
    <x-slot name="header">
        <x-page-header class="rh-review-page-header [&_h1]:text-3xl [&_p]:text-base [&_p]:leading-6" title="Report reviews" subtitle="Accept checked Monitoring Tools and Progress Reports, or return concerns to the Research Office Secretary. Review Terminal Reports here as well." />
    </x-slot>
    @include('research_head.report-reviews.queue')
</x-app-layout>
