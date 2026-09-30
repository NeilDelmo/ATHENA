<?php

namespace App\Models;

use Database\Factories\ResearchAnnualTargetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResearchAnnualTarget extends Model
{
    /** @use HasFactory<ResearchAnnualTargetFactory> */
    use HasFactory;

    protected $fillable = ['academic_year', 'starts_on', 'ends_on', 'projects_target', 'publications_target', 'faculty_target', 'updated_by'];

    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date', 'projects_target' => 'integer', 'publications_target' => 'integer', 'faculty_target' => 'integer'];
    }
}
