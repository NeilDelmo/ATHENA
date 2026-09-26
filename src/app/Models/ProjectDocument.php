<?php

namespace App\Models;

use Database\Factories\ProjectDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectDocument extends Model
{
    public const CATEGORY_PROPOSAL_PAPERS = 'proposal_papers';

    public const CATEGORY_SIGNED_PAPERS = 'signed_papers';

    public const CATEGORY_REVIEWS_RESPONSES = 'reviews_responses';

    public const CATEGORY_NOTICE_TO_PROCEED = 'notice_to_proceed';

    public const CATEGORY_MONITORING_REPORTS = 'monitoring_reports';

    public const CATEGORY_SUPPORTING_FILES = 'supporting_files';

    public const CATEGORY_OTHER = 'other';

    /** @use HasFactory<ProjectDocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'topic_id',
        'uploaded_by',
        'category',
        'title',
        'note',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'checksum',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    /** @return array<string, string> */
    public static function categoryOptions(): array
    {
        return [
            self::CATEGORY_PROPOSAL_PAPERS => 'Proposal papers',
            self::CATEGORY_SIGNED_PAPERS => 'Signed papers',
            self::CATEGORY_REVIEWS_RESPONSES => 'Reviews & responses',
            self::CATEGORY_NOTICE_TO_PROCEED => 'Notice to Proceed',
            self::CATEGORY_MONITORING_REPORTS => 'Monitoring reports',
            self::CATEGORY_SUPPORTING_FILES => 'Supporting files',
            self::CATEGORY_OTHER => 'Other files',
        ];
    }

    /** @return array<string, string> */
    public static function uploadCategoryOptions(): array
    {
        return collect(self::categoryOptions())
            ->except(self::CATEGORY_NOTICE_TO_PROCEED)
            ->all();
    }

    public function categoryLabel(): string
    {
        return self::categoryOptions()[$this->category] ?? 'Other files';
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(TopicProposal::class, 'topic_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
