<?php

namespace App\Models;

use Database\Factories\ResearchAnnualTargetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResearchAnnualTarget extends Model
{
    public const INDICATORS = [
        'publications' => ['label' => 'Publication', 'unit' => 'count'],
        'students_ylp' => ['label' => 'Students engaged in YLP', 'unit' => 'count'],
        'funded_collaborations' => ['label' => 'Research collaboration funded', 'unit' => 'count'],
        'external_funding' => ['label' => 'Externally funded research', 'unit' => 'count'],
        'completed_research' => ['label' => 'Completed research', 'unit' => 'count'],
        'approved_research' => ['label' => 'Approved research (<₱150K)', 'unit' => 'count'],
        'utilized_outputs' => ['label' => 'Research output utilized', 'unit' => 'count'],
        'faculty_engaged' => ['label' => 'Faculty engaged in research', 'unit' => 'percent'],
        'new_faculty' => ['label' => 'New faculty engaged', 'unit' => 'count'],
        'faculty_osi' => ['label' => 'Faculty engaged in the OSI', 'unit' => 'count'],
        'industry_collaborations' => ['label' => 'Implemented with collaboration from industries', 'unit' => 'count'],
    ];

    /** @use HasFactory<ResearchAnnualTargetFactory> */
    use HasFactory;

    protected $fillable = ['academic_year', 'starts_on', 'ends_on', 'projects_target', 'publications_target', 'faculty_target', 'indicators', 'updated_by'];

    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date', 'projects_target' => 'integer', 'publications_target' => 'integer', 'faculty_target' => 'integer', 'indicators' => 'array'];
    }
}
