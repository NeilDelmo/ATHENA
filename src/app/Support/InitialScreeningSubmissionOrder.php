<?php

namespace App\Support;

use App\Models\ProposalDraft;
use App\Models\ProposalVersionFile;

final class InitialScreeningSubmissionOrder
{
    public const FIRST_SUBMISSION = 'first_submission';

    public const REVISED_WITH_MINOR_CHANGES = 'revised_with_minor_changes';

    public const REVISED_WITH_MAJOR_CHANGES = 'revised_with_major_changes';

    public const FOR_ENDORSEMENT = 'for_endorsement';

    public const MINOR_REVISION = 'minor_revision';

    public const MAJOR_REVISION = 'major_revision';

    /** @return list<string> */
    public static function recommendations(): array
    {
        return [
            self::FOR_ENDORSEMENT,
            self::MINOR_REVISION,
            self::MAJOR_REVISION,
        ];
    }

    public static function recommendationLabel(?string $recommendation): ?string
    {
        return match ($recommendation) {
            self::FOR_ENDORSEMENT => 'For Endorsement',
            self::MINOR_REVISION => 'Minor Revision',
            self::MAJOR_REVISION => 'Major Revision',
            default => null,
        };
    }

    public function forDraft(ProposalDraft $draft): string
    {
        if ($draft->topic_id === null) {
            return self::FIRST_SUBMISSION;
        }

        $latestVersion = $draft->topic?->latestVersion()->first();

        if ($latestVersion === null) {
            return self::REVISED_WITH_MINOR_CHANGES;
        }

        $initialScreeningFormId = $latestVersion->files()
            ->where('document_type', ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM)
            ->value('id');

        $evaluation = $latestVersion->files()
            ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)
            ->where('source_version_file_id', $initialScreeningFormId)
            ->where('source_data->purpose', ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION)
            ->reorder()
            ->latest('id')
            ->first();

        return match ($evaluation?->source_data['recommended_action'] ?? null) {
            self::MAJOR_REVISION => self::REVISED_WITH_MAJOR_CHANGES,
            default => self::REVISED_WITH_MINOR_CHANGES,
        };
    }
}
