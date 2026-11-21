<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CmsSiteSetting extends Model
{
    protected $table = 'cms_site_settings';

    protected $fillable = [
        'key',
        'value',
    ];

    
}