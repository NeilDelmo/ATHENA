<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalSimilarityCheck extends Model
{
    protected $fillable = ['proposal_version_file_id', 'requested_by', 'handled_by', 'status', 'similarity_score', 'notes', 'report_path', 'completed_at'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime', 'similarity_score' => 'decimal:2'];
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(ProposalVersionFile::class, 'proposal_version_file_id');
    }
}
