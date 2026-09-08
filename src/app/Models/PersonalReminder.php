<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonalReminder extends Model
{
    protected $fillable = ['title', 'notes', 'starts_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime'];
    }
}
