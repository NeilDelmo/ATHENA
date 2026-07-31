<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'google_id', 'avatar', 'college', 'contact_number', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
/**
 * A single `User` row represents one person regardless of how many roles they
 * hold. The `college` and `contact_number` columns are GLOBAL to the account:
 * they are the same value whether the person is acting as a faculty member, a
 * research head, a faculty researcher, or a research coordinator. Roles only
 * change which routes and notifications are visible, never the personal
 * identity columns. This guarantees that a dual-role user (e.g. faculty +
 * research head, faculty + research coordinator, or faculty + faculty
 * researcher) automatically uses the same college and contact number across
 * every workspace, so proposal forms, the CV, and the profile never show
 * conflicting personal information.
 */
class User extends Authenticatable
{
    public const ACTIVE_WORKSPACE_SESSION_KEY = 'active_workspace';

    public const WORKSPACE_FACULTY = 'faculty';

    public const WORKSPACE_FACULTY_RESEARCHER = 'faculty_researcher';

    public const WORKSPACE_RESEARCH_HEAD = 'research_head';

    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public const COLLEGES = [
        'CICS' => 'College of Informatics and Computing Sciences',
        'CTE' => 'College of Teacher Education',
        'CABEIHM' => 'College of Accountancy, Business, Economics, and International Hospitality Management',
        'CCJE' => 'College of Criminal Justice Education',
        'CAS' => 'College of Arts and Sciences',
        'CHS' => 'College of Health Sciences',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return array<string, array{label: string, description: string, route: string}>
     */
    public static function workspaceDefinitions(): array
    {
        return [
            self::WORKSPACE_RESEARCH_HEAD => [
                'label' => 'Research Head',
                'description' => 'Manage research calls, evaluate proposals, and oversee institutional research.',
                'route' => 'research_head.dashboard',
            ],
            self::WORKSPACE_FACULTY_RESEARCHER => [
                'label' => 'Faculty Researcher',
                'description' => 'Manage proposals and monitor approved institutional research projects.',
                'route' => 'faculty.dashboard',
            ],
            self::WORKSPACE_FACULTY => [
                'label' => 'Faculty',
                'description' => 'Create proposal packages and track your research submissions.',
                'route' => 'faculty.dashboard',
            ],
        ];
    }

    /**
     * Return every workspace this account may enter without changing its assigned roles.
     *
     * A user may carry several roles at once (e.g. `faculty` + `research_head`,
     * `faculty` + `faculty_researcher`, or `faculty` + `research_coordinator`).
     * Each role maps to one or two workspaces; the union of those workspaces
     * is what the user can switch into. The college and contact number are
     * NOT copied per workspace — they live on the `User` row itself, so
     * the same values follow the user into every workspace they enter.
     *
     * @return list<string>
     */
    public function availableWorkspaceKeys(): array
    {
        $assignedRoles = $this->getRoleNames();
        $available = [];

        if ($assignedRoles->contains(self::WORKSPACE_RESEARCH_HEAD)) {
            $available = [
                ...$available,
                self::WORKSPACE_RESEARCH_HEAD,
                self::WORKSPACE_FACULTY,
            ];
        }

        if ($assignedRoles->contains(self::WORKSPACE_FACULTY_RESEARCHER)) {
            $available = [...$available, self::WORKSPACE_FACULTY_RESEARCHER, self::WORKSPACE_FACULTY];
        }

        if ($assignedRoles->contains(self::WORKSPACE_FACULTY)) {
            $available[] = self::WORKSPACE_FACULTY;
        }

        $available = array_unique($available);

        return array_values(array_filter(
            array_keys(self::workspaceDefinitions()),
            fn (string $workspace): bool => in_array($workspace, $available, true),
        ));
    }

    /**
     * @return array<string, array{label: string, description: string, route: string}>
     */
    public function availableWorkspaces(): array
    {
        return array_intersect_key(
            self::workspaceDefinitions(),
            array_flip($this->availableWorkspaceKeys()),
        );
    }

    public function canUseWorkspace(string $workspace): bool
    {
        return in_array($workspace, $this->availableWorkspaceKeys(), true);
    }

    public function activeWorkspace(): ?string
    {
        $selected = session(self::ACTIVE_WORKSPACE_SESSION_KEY);

        if (is_string($selected) && $this->canUseWorkspace($selected)) {
            return $selected;
        }

        return $this->availableWorkspaceKeys()[0] ?? null;
    }

    /**
     * @param  string|list<string>  $workspaces
     */
    public function isUsingWorkspace(string|array $workspaces): bool
    {
        return in_array($this->activeWorkspace(), (array) $workspaces, true);
    }

    public function hasMultipleWorkspaces(): bool
    {
        return count($this->availableWorkspaceKeys()) > 1;
    }

    public function activeWorkspaceLabel(): string
    {
        $workspace = $this->activeWorkspace();

        return self::workspaceDefinitions()[$workspace]['label'] ?? 'ATHENA user';
    }

    public function dashboardRouteName(?string $workspace = null): ?string
    {
        $workspace ??= $this->activeWorkspace();

        return self::workspaceDefinitions()[$workspace]['route'] ?? null;
    }

    /**
     * @return Collection<int, DatabaseNotification>
     */
    public function visibleNotifications(?string $workspace = null): Collection
    {
        $workspace ??= $this->activeWorkspace();

        return $this->notifications()
            ->latest()
            ->get()
            ->filter(function (DatabaseNotification $notification) use ($workspace): bool {
                $targetWorkspace = $this->notificationWorkspace($notification->data);

                return $targetWorkspace === null
                    || $targetWorkspace === $workspace
                    || (is_array($targetWorkspace) && in_array($workspace, $targetWorkspace, true));
            })
            ->values();
    }

    private function notificationWorkspace(array $data): string|array|null
    {
        $targetWorkspace = $data['workspace'] ?? null;

        if ($targetWorkspace !== null) {
            return $targetWorkspace;
        }

        return in_array($data['title'] ?? null, [
            'New proposal submitted',
            'Proposal revision submitted',
        ], true) ? self::WORKSPACE_RESEARCH_HEAD : null;
    }

    // for the proposal
    public function proposals(): HasMany
    {
        return $this->hasMany(TopicProposal::class);
    }

    public function proposalDrafts(): HasMany
    {
        return $this->hasMany(ProposalDraft::class);
    }

    public function proposalDraftMemberships(): HasMany
    {
        return $this->hasMany(ProposalDraftMember::class);
    }

    public function researchAssistantConversations(): HasMany
    {
        return $this->hasMany(ResearchAssistantConversation::class);
    }

    public function topicReviews(): HasMany
    {
        return $this->hasMany(TopicReview::class, 'reviewer_id');
    }
}
