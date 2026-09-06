<?php

namespace App\Http\Requests;

use App\Support\ProposalRevisionTargetCatalog;
use Illuminate\Support\Arr;

class UpdateProposalFileAnnotationRequest extends StoreProposalFileAnnotationRequest
{
    /** @return array<string, mixed> */
    public function rules(ProposalRevisionTargetCatalog $revisionTargets): array
    {
        return Arr::only(parent::rules($revisionTargets), ['comment', 'editor_target']);
    }
}
