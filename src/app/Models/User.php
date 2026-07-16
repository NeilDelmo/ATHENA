<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'google_id', 'avatar', 'college', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public const COLLEGES = [
        'CICS' => 'College of Informatics and Computing Sciences',
        'CTE' => 'College of Teacher Education',
        'CABEIHM' => 'College of Accountancy, Business, Economics, and International Hospitality Management',
        'CCJE' => 'College of Criminal Justice Education',
        'CAS' => 'College of Arts and Sciences',
        'CHS' => 'College of Health Sciences',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // for the proposal
    public function proposals(): HasMany
    {
        return $this->hasMany(TopicProposal::class);
    }

    public function topicReviews(): HasMany
    {
        return $this->hasMany(TopicReview::class, 'reviewer_id');
    }

    public function expertAssignments(): HasMany
    {
        return $this->hasMany(TopicExpertAssignment::class, 'expert_id');
    }
}
