@php
    $workspaceDefinitions = \App\Models\User::workspaceDefinitions();
    $workspaces = [
        'faculty' => $workspaceDefinitions[\App\Models\User::WORKSPACE_FACULTY],
        'research_coordinator' => $workspaceDefinitions[\App\Models\User::WORKSPACE_RESEARCH_OFFICE],
    ];
    $currentWorkspace = match ($user->activeWorkspace()) {
        \App\Models\User::WORKSPACE_RESEARCH_OFFICE => 'research_coordinator',
        \App\Models\User::WORKSPACE_FACULTY => 'faculty',
        default => null,
    };
@endphp

<x-guest-layout>
    <x-workspace-selector
        :user="$user"
        :workspaces="$workspaces"
        :action="route('role-selection.store')"
        field="role"
        :current-workspace="$currentWorkspace"
    />
</x-guest-layout>
