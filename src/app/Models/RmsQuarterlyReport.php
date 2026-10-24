<?php

namespace App\Models;

use Database\Factories\RmsQuarterlyReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RmsQuarterlyReport extends Model
{
    /** @use HasFactory<RmsQuarterlyReportFactory> */
    use HasFactory;

    protected $fillable = ['reporting_year', 'reporting_quarter', 'campus', 'signatories', 'row_details', 'updated_by', 'downloaded_at', 'downloaded_fingerprint'];

    protected $attributes = ['campus' => 'ARASOF-Nasugbu'];

    protected function casts(): array
    {
        return ['reporting_year' => 'integer', 'reporting_quarter' => 'integer', 'signatories' => 'array', 'row_details' => 'array', 'downloaded_at' => 'datetime'];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function draftToken(): string
    {
        return hash('sha256', json_encode([
            $this->campus, $this->signatories ?? [], $this->row_details ?? [],
        ], JSON_THROW_ON_ERROR));
    }
}
