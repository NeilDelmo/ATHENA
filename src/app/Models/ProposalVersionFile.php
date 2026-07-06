<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalVersionFile extends Model
{
    public const TYPE_DETAILED_PROPOSAL = 'detailed_proposal';

    public const TYPE_WORK_PLAN = 'work_plan';

    public const TYPE_LINE_ITEM_BUDGET = 'line_item_budget';

    public const TYPE_EXPENSE_BREAKDOWN = 'expense_breakdown';

    public const TYPE_CURRICULUM_VITAE = 'curriculum_vitae';

    public const TYPE_GAD_CHECKLIST = 'gad_checklist';

    public const TYPE_COMMENT_RESPONSE = 'comment_response';

    protected $fillable = [
        'source_version_file_id',
        'document_type',
        'position',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'checksum',
        'is_carried_forward',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'position' => 'integer',
            'is_carried_forward' => 'boolean',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ProposalVersion::class, 'proposal_version_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_version_file_id');
    }

    public function label(): string
    {
        return match ($this->document_type) {
            self::TYPE_DETAILED_PROPOSAL => 'Detailed Proposal',
            self::TYPE_WORK_PLAN => 'Work Plan',
            self::TYPE_LINE_ITEM_BUDGET => 'Line-Item Budget',
            self::TYPE_EXPENSE_BREAKDOWN => 'Expense Breakdown',
            self::TYPE_CURRICULUM_VITAE => 'Curriculum Vitae'.($this->position > 0 ? ' '.($this->position + 1) : ''),
            self::TYPE_GAD_CHECKLIST => 'GAD Checklist',
            self::TYPE_COMMENT_RESPONSE => 'Comment-Response Form',
            default => str($this->document_type)->replace('_', ' ')->title()->toString(),
        };
    }
}
