<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProposalSignatory extends Model
{
    public const FIELDS = [
        'detailed_proposal' => [
            'checked_verified_by_name' => 'Checked and verified by',
            'recommending_approval_name' => 'Recommending approval',
            'approved_by_name' => 'Final approval',
        ],
        'work_plan' => ['verified_by' => 'Work Plan — checked and verified by'],
        'line_item_budget' => ['certified_by' => 'Budget — certified correct by'],
        'gad_checklist' => ['verifier_name' => 'GAD Checklist — verified by'],
        'initial_screening_form' => [
            'screening_head' => 'Screening — Head of Research / Research and Extension',
            'screening_center' => 'Screening — Center Head / Assistant Director',
            'screening_verifier' => 'Screening — Director of Research / Vice Chancellor',
        ],
        'comment_response_form' => [
            'comment_response_head' => 'Comments Form — Research Head / RDES Head',
            'comment_response_vice_chancellor' => 'Comments Form — Vice Chancellor for Research, Development and Extension Services',
        ],
    ];

    protected $fillable = ['role_key', 'name', 'position', 'active', 'is_default'];

    protected $attributes = ['is_default' => false];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'is_default' => 'boolean'];
    }

    public static function roles(): array
    {
        return array_merge(...array_values(self::FIELDS));
    }

    /** @return array<string, array{id: null, name: string, position: string}> */
    public static function defaultSelections(): array
    {
        $head = [
            'id' => null,
            'name' => (string) config('work_plan.verifier.name'),
            'position' => (string) config('work_plan.verifier.role'),
        ];
        $viceChancellor = [
            'id' => null,
            'name' => (string) config('notice_to_proceed.issuing_officer.name'),
            'position' => (string) config('notice_to_proceed.issuing_officer.title'),
        ];

        return [
            'approved_by_name' => ['id' => null, 'name' => (string) config('notice_to_proceed.verifying_officer.name'), 'position' => (string) config('notice_to_proceed.verifying_officer.title')],
            'certified_by' => ['id' => null, 'name' => (string) config('line_item_budget.certifier.name'), 'position' => (string) config('line_item_budget.certifier.role')],
            'verifier_name' => ['id' => null, 'name' => (string) config('gad_checklist.verifier.name'), 'position' => (string) config('gad_checklist.verifier.role')],
            'screening_center' => ['id' => null, 'name' => (string) config('research_signatories.center_head'), 'position' => 'Center Head/ Assistant Director for Research'],
            'checked_verified_by_name' => [...$head, 'position' => 'Head, Research Office'],
            'verified_by' => $head,
            'screening_head' => [...$head, 'position' => 'Head, Research/ Head, Research and Extension'],
            'comment_response_head' => [...$head, 'position' => "Research Head/ RDES Head\nMember, LREC"],
            'recommending_approval_name' => $viceChancellor,
            'screening_verifier' => [...$viceChancellor, 'position' => 'Director, Research/ Vice Chancellor for RDES'],
            'comment_response_vice_chancellor' => [...$viceChancellor, 'position' => "Vice Chancellor for Research, Development and Extension Services\nMember, LREC"],
        ];
    }

    /** @return array<string, array{id: int|null, name: string, position: string}> */
    public static function managedDefaultSelections(): array
    {
        $selections = self::defaultSelections();

        foreach (self::query()->where('is_default', true)->where('active', true)->orderBy('id')->get() as $signatory) {
            $selections[$signatory->role_key] = ['id' => $signatory->id, 'name' => $signatory->name, 'position' => $signatory->position];
        }

        return $selections;
    }
}
