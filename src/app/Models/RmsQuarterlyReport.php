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

    protected $fillable = ['reporting_year', 'reporting_quarter', 'campus', 'signatories', 'row_details', 'updated_by'];

    protected $attributes = ['campus' => 'ARASOF-Nasugbu'];

    protected function casts(): array
    {
        return ['reporting_year' => 'integer', 'reporting_quarter' => 'integer', 'signatories' => 'array', 'row_details' => 'array'];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
