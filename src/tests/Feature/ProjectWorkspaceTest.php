<?php

use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    Role::findOrCreate('research_head', 'web');
    Role::findOrCreate('faculty_researcher', 'web');
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->researcher = User::factory()->create(['name' => 'Elena Santos']);
    $this->researcher->assignRole('faculty_researcher');
    $this->project = TopicProposal::create([
        'user_id' => $this->researcher->id,
        'title' => 'Coastal communities research',
        'status' => 'approved',
        'project_status' => 'ongoing',
        'notice_to_proceed_issued_by' => $this->head->id,
        'notice_to_proceed_issued_at' => '2025-06-01',
    ]);
    $this->folderId = (string) Str::uuid();
    $this->workspace = [
        'revision' => 0,
        'folders' => [['id' => $this->folderId, 'name' => 'Field studies', 'parent_id' => null]],
        'assignments' => [['project_id' => $this->project->id, 'folder_id' => $this->folderId]],
        'view' => 'grid',
        'grouping' => 'year',
        'sort' => 'attention',
    ];
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
});

test('a project workspace persists privately without changing project status', function () {
    $otherHead = User::factory()->create();
    $otherHead->assignRole('research_head');
    $originalUpdatedAt = $this->project->updated_at;
    $this->workspace['folders'][0]['name'] = '  Field studies  ';
    $this->workspace['view'] = 'list';
    $this->workspace['grouping'] = 'none';
    $this->workspace['sort'] = 'title';
    $response = $this->putJson('/research-head/project-workspace', $this->workspace)
        ->assertSuccessful()->assertJsonPath('workspace.revision', 1)
        ->assertJsonPath('workspace.folders.0.name', 'Field studies');
    expect($this->head->fresh()->projectWorkspacePreferences())->toEqual($response->json('workspace'))
        ->and($otherHead->fresh()->project_workspace)->toBeNull()
        ->and($this->project->fresh()->project_status)->toBe('ongoing')
        ->and($this->project->fresh()->updated_at->equalTo($originalUpdatedAt))->toBeTrue()
        ->and($this->head->isFillable('project_workspace'))->toBeFalse()
        ->and($this->head->fresh()->toArray())->not->toHaveKey('project_workspace');
    $this->head->refresh();
    $this->get(route('research_head.projects.index'))->assertSuccessful()
        ->assertViewHas('workspacePreferences', $response->json('workspace'));
    $this->actingAs($otherHead)->get(route('research_head.projects.index'))->assertSuccessful()
        ->assertViewHas('workspacePreferences', [
            'revision' => 0, 'folders' => [], 'assignments' => [],
            'view' => 'grid', 'grouping' => 'year', 'sort' => 'attention',
        ]);
});

test('a stale workspace save preserves the newer folders', function () {
    $this->putJson(route('research_head.projects.workspace.update'), $this->workspace)->assertSuccessful();
    $stale = $this->workspace;
    $stale['folders'][0]['name'] = 'Outdated edit';
    $this->putJson(route('research_head.projects.workspace.update'), $stale)
        ->assertConflict()->assertJsonStructure(['message']);
    expect($this->head->fresh()->project_workspace['revision'])->toBe(1)
        ->and($this->head->fresh()->project_workspace['folders'][0]['name'])->toBe('Field studies');
});

test('empty folders and assignments can be saved', function () {
    $this->workspace['folders'] = [];
    $this->workspace['assignments'] = [];
    $this->putJson(route('research_head.projects.workspace.update'), $this->workspace)
        ->assertSuccessful()->assertJsonPath('workspace.folders', [])->assertJsonPath('workspace.assignments', []);
});

test('outdated assignments are removed without losing projects outside the current filters', function (string $condition) {
    $other = $this->project->replicate();
    $other->fill(['title' => 'Other valid project'])->save();
    $this->workspace['assignments'][] = ['project_id' => $other->id, 'folder_id' => $this->folderId];
    $this->putJson(route('research_head.projects.workspace.update'), $this->workspace)->assertSuccessful();
    if ($condition === 'deleted') {
        $this->project->delete();
    } elseif ($condition === 'unissued') {
        $this->project->update(['notice_to_proceed_issued_at' => null]);
    } else {
        $blockedId = $this->project->id;
        Gate::before(fn (User $user, string $ability, array $arguments): ?bool => $ability === 'view' && $arguments[0]->id === $blockedId ? false : null);
    }

    $this->head->refresh();
    $response = $this->get(route('research_head.projects.index', ['search' => 'No matching title']))
        ->assertSuccessful()->assertViewHas('projects', fn ($projects): bool => $projects->isEmpty())
        ->assertViewHas('workspaceProjects', fn (array $projects): bool => count($projects) === 1 && $projects[0]['id'] === $other->id);
    $workspace = $response->viewData('workspacePreferences');
    expect($workspace['assignments'])->toEqual([['project_id' => $other->id, 'folder_id' => $this->folderId]]);
    $workspace['folders'][0]['name'] = 'Updated folder';
    $this->putJson(route('research_head.projects.workspace.update'), $workspace)
        ->assertSuccessful()->assertJsonPath('workspace.revision', 2);
})->with(['deleted', 'unissued', 'inaccessible']);

test('nested folders permit matching names in separate parents', function () {
    $parentId = (string) Str::uuid();
    $this->workspace['folders'][] = ['id' => $parentId, 'name' => 'Another parent', 'parent_id' => null];
    $this->workspace['folders'][] = ['id' => (string) Str::uuid(), 'name' => 'Field studies', 'parent_id' => $parentId];
    $this->putJson(route('research_head.projects.workspace.update'), $this->workspace)
        ->assertSuccessful()->assertJsonCount(3, 'workspace.folders');
});

test('invalid folder structures and preferences are rejected', function (Closure $invalidate, string $field) {
    $this->putJson(route('research_head.projects.workspace.update'), $invalidate($this->workspace))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    expect($this->head->fresh()->project_workspace)->toBeNull();
})->with([
    'missing parent' => [fn (array $p): array => array_replace_recursive($p, ['folders' => [['parent_id' => (string) Str::uuid()]]]), 'folders.0.parent_id'],
    'self parent' => [fn (array $p): array => array_replace_recursive($p, ['folders' => [['parent_id' => $p['folders'][0]['id']]]]), 'folders'],
    'cycle' => [function (array $p): array {
        $childId = (string) Str::uuid();
        $p['folders'][0]['parent_id'] = $childId;
        $p['folders'][] = ['id' => $childId, 'name' => 'Child', 'parent_id' => $p['folders'][0]['id']];

        return $p;
    }, 'folders'],
    'duplicate siblings' => [function (array $p): array {
        $p['folders'][] = ['id' => (string) Str::uuid(), 'name' => '  FIELD studies  ', 'parent_id' => null];

        return $p;
    }, 'folders.1.name'],
    'duplicate folder ids' => [function (array $p): array {
        $p['folders'][] = ['id' => $p['folders'][0]['id'], 'name' => 'Other', 'parent_id' => null];

        return $p;
    }, 'folders.0.id'],
    'unknown assignment folder' => [fn (array $p): array => array_replace_recursive($p, ['assignments' => [['folder_id' => (string) Str::uuid()]]]), 'assignments.0.folder_id'],
    'duplicate assignments' => [function (array $p): array {
        $p['assignments'][] = $p['assignments'][0];

        return $p;
    }, 'assignments.0.project_id'],
    'unknown folder key' => [fn (array $p): array => array_replace_recursive($p, ['folders' => [['shared' => true]]]), 'folders.0'],
    'unknown assignment key' => [fn (array $p): array => array_replace_recursive($p, ['assignments' => [['status' => 'completed']]]), 'assignments.0'],
    'unknown top level key' => [fn (array $p): array => [...$p, 'user_id' => 999], 'user_id'],
    'empty name' => [fn (array $p): array => array_replace_recursive($p, ['folders' => [['name' => '  ']]]), 'folders.0.name'],
    'oversized name' => [fn (array $p): array => array_replace_recursive($p, ['folders' => [['name' => str_repeat('x', 81)]]]), 'folders.0.name'],
    'too many folders' => [function (array $p): array {
        $p['folders'] = array_map(fn (int $i): array => ['id' => (string) Str::uuid(), 'name' => 'Folder '.$i, 'parent_id' => null], range(1, 201));

        return $p;
    }, 'folders'],
    'invalid view' => [fn (array $p): array => [...$p, 'view' => 'board'], 'view'],
    'invalid grouping' => [fn (array $p): array => [...$p, 'grouping' => 'status'], 'grouping'],
    'invalid sort' => [fn (array $p): array => [...$p, 'sort' => 'budget'], 'sort'],
]);

test('assignments only accept accessible projects with issued notices', function (string $condition) {
    if ($condition === 'unissued') {
        $this->project->update(['notice_to_proceed_issued_at' => null]);
    } elseif ($condition === 'missing') {
        $this->workspace['assignments'][0]['project_id'] = $this->project->id + 999;
    } else {
        Gate::before(fn (User $user, string $ability): ?bool => $ability === 'view' ? false : null);
    }
    $this->putJson(route('research_head.projects.workspace.update'), $this->workspace)
        ->assertUnprocessable()->assertJsonValidationErrors('assignments.0.project_id');
})->with(['unissued', 'missing', 'inaccessible']);

test('workspace updates require the research head workspace', function () {
    $this->actingAs($this->researcher)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER])
        ->putJson(route('research_head.projects.workspace.update'), $this->workspace)->assertForbidden();
    $this->actingAs($this->head)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY])
        ->putJson(route('research_head.projects.workspace.update'), $this->workspace)->assertForbidden();
    auth()->logout();
    $this->putJson(route('research_head.projects.workspace.update'), $this->workspace)->assertUnauthorized();
});

test('exports project workspace with every matching project beyond the paginator page', function () {
    foreach (range(1, 17) as $number) {
        $project = $this->project->replicate();
        $project->fill([
            'title' => 'Community study '.$number,
            'project_status' => $number === 17 ? 'completed' : 'ongoing',
            'notice_to_proceed_issued_at' => $number > 8 ? '2024-03-01' : '2025-06-01',
        ])->save();
    }
    $this->project->update(['research_secretary_id' => $this->researcher->id]);
    ProjectProgressReport::create([
        'topic_id' => $this->project->id, 'submitted_by' => $this->researcher->id,
        'reporting_date' => '2026-09-30', 'progress_percentage' => 35,
        'accomplishments' => 'Survey data collected.',
    ]);
    $response = $this->get(route('research_head.projects.index'))->assertSuccessful()
        ->assertViewHas('projects', fn ($projects): bool => $projects->count() === 15 && $projects->total() === 18)
        ->assertViewHas('workspaceProjects', fn (array $projects): bool => count($projects) === 18);
    $data = collect($response->viewData('workspaceProjects'));
    $project = $data->firstWhere('id', $this->project->id);
    expect($project)->toMatchArray([
        'title' => $this->project->title, 'researcher' => 'Elena Santos', 'call' => 'Independent submission',
        'status' => 'ongoing', 'status_label' => 'Ongoing', 'year' => 2025, 'date' => '2025-06-01',
        'progress' => 35, 'latest_label' => 'Tool · Sep 30, 2026', 'secretary' => 'Elena Santos',
        'secretary_initials' => 'ES', 'reports' => 1, 'pending' => 1,
        'url' => route('topics.show', $this->project).'#project-monitoring', 'action_label' => 'Monitor project',
    ]);
    $completed = $data->firstWhere('status', 'completed');
    expect($completed['url'])->toBe(route('topics.show', $completed['id']))
        ->and($completed['action_label'])->toBe('View record');
    if (getenv('ATHENA_EXPORT_PROJECT_WORKSPACE') === '1') {
        $directory = storage_path('framework/testing');
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        file_put_contents($directory.'/project-workspace-monitoring.html', $response->getContent());
        ProjectProgressReport::query()->where('topic_id', $this->project->id)->update(['progress_percentage' => 100]);
        TopicProposal::query()->where('user_id', $this->researcher->id)->update(['project_status' => 'completed']);
        $response = $this->get(route('research_head.completed-projects.index'))->assertSuccessful()
            ->assertViewHas('workspaceProjects', fn (array $projects): bool => count($projects) === 18);
        file_put_contents($directory.'/project-workspace.html', $response->getContent());
    }
});

test('monitoring filters do not restrict the file library', function () {
    $this->project->update(['project_status' => 'delayed']);
    $other = $this->project->replicate();
    $other->fill(['title' => 'Inland research', 'project_status' => 'ongoing'])->save();
    $this->get(route('research_head.projects.index', ['search' => 'Coastal', 'status' => 'delayed', 'attention' => 'needs_attention']))
        ->assertSuccessful()->assertViewHas('projects', fn ($projects): bool => $projects->total() === 1 && $projects->first()->id === $this->project->id)
        ->assertViewHas('workspaceProjects', fn (array $projects): bool => count($projects) === 2)
        ->assertViewHas('initialRecordsView', 'monitoring');
    $this->get(route('research_head.projects.index', ['attention' => 'pending_reports']))
        ->assertSuccessful()->assertViewHas('projects', fn ($projects): bool => $projects->isEmpty())
        ->assertViewHas('workspaceProjects', fn (array $projects): bool => count($projects) === 2);
});

test('project libraries expose a view context menu and wheel sizing for folders and files', function (string $routeName) {
    $this->project->update(['project_status' => 'completed']);
    $response = $this->get(route($routeName))->assertSuccessful()
        ->assertSee('@wheel="resizeWithWheel($event)"', false)
        ->assertSee('@contextmenu="openViewMenu($event)"', false)
        ->assertDontSee('aria-label="Item size"', false);
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $surfaces = $xpath->query('//*[@data-project-workspace]//*[@data-library-view-surface]');
    expect($surfaces->length)->toBeGreaterThan(0);
    foreach ($surfaces as $surface) {
        expect($surface->getAttribute('tabindex'))->toBe('0');
    }
    $menu = $xpath->query('//*[@data-project-workspace]//*[@role="menu" and @aria-label="View"]')->item(0);
    expect($menu)->not->toBeNull();
    expect($menu->hasAttribute('x-cloak'))->toBeTrue()
        ->and($menu->hasAttribute('x-show'))->toBeTrue()
        ->and($xpath->query('.//button[@role="menuitemradio"]', $menu)->length)->toBe(4)
        ->and($xpath->query('//*[@data-project-workspace]//button[not(@role="menuitemradio") and (normalize-space(.)="Icons" or normalize-space(.)="Details")]')->length)->toBe(0);
    foreach (['small' => 'Small icons', 'medium' => 'Medium icons', 'large' => 'Large icons', 'list' => 'Details'] as $option => $label) {
        $button = $xpath->query('.//button[@type="button" and @role="menuitemradio" and @data-view-option="'.$option.'"]', $menu)->item(0);
        expect($button)->not->toBeNull();
        expect(trim($button->textContent))->toBe($label)
            ->and($button->hasAttribute('aria-checked') || $button->hasAttribute(':aria-checked') || $button->hasAttribute('x-bind:aria-checked'))->toBeTrue();
    }
})->with(['research_head.projects.index', 'research_head.completed-projects.index']);

test('completed project controls are scoped to monitoring and preserve the completed library', function () {
    $this->project->update(['project_status' => 'completed']);
    $ongoing = $this->project->replicate();
    $ongoing->fill(['title' => 'Ongoing study', 'project_status' => 'ongoing'])->save();

    $response = $this->get(route('research_head.completed-projects.index'))
        ->assertSuccessful()->assertViewHas('initialRecordsView', 'files')
        ->assertDontSee('id="project-status"', false)
        ->assertDontSee('All project statuses')
        ->assertDontSee('data-kpi-strip', false)
        ->assertDontSee('>Clear</a>', false)
        ->assertViewHas('workspaceProjects', fn (array $projects): bool => count($projects) === 1 && $projects[0]['id'] === $this->project->id);

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@data-project-monitoring-controls]//form[@method="GET"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-project-monitoring-controls]//*[@id="project-search"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="project-files-panel"]//*[@id="project-search"]')->length)->toBe(0)
        ->and($response->viewData('projects')->url(2))->toContain('view=monitoring');

    $this->get(route('research_head.completed-projects.index', ['search' => 'No matching study', 'view' => 'monitoring']))
        ->assertSuccessful()->assertViewHas('initialRecordsView', 'monitoring')
        ->assertViewHas('projects', fn ($projects): bool => $projects->isEmpty())
        ->assertViewHas('workspaceProjects', fn (array $projects): bool => count($projects) === 1 && $projects[0]['id'] === $this->project->id)
        ->assertSee('name="view" value="monitoring"', false)
        ->assertSee('href="'.route('research_head.completed-projects.index', ['view' => 'monitoring']).'"', false);

    $this->get(route('research_head.completed-projects.index', ['view' => 'monitoring']))
        ->assertSuccessful()->assertViewHas('initialRecordsView', 'monitoring');
});
