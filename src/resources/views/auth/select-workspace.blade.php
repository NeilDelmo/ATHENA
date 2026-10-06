<x-guest-layout>
    <x-workspace-selector
        :user="Auth::user()"
        :workspaces="$workspaces"
        :action="route('workspace.store')"
        :current-workspace="Auth::user()->activeWorkspace()"
    />
</x-guest-layout>
