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
    ];

    protected $fillable = ['role_key', 'name', 'position', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public static function roles(): array
    {
        return array_merge(...array_values(self::FIELDS));
    }
}
