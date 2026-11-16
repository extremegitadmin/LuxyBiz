<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Objective extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    public function tours(): HasMany
    {
        return $this->hasMany(Tour::class);
    }
}