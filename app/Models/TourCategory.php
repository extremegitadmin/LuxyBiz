<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class TourCategory extends Model
{
    use HasFactory;
    protected $table = 'tour_categories';

    protected $fillable = [
        'name',
        'slug',
        // 'description',
        'status',
        'sort_order',
    ];
}
