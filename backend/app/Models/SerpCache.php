<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SerpCache extends Model
{
    protected $table = 'serp_cache';

    protected $fillable = [
        'keyword',
        'language',
        'country',
        'provider',
        'results',
        'expires_at',
    ];

    protected $casts = [
        'results' => 'array',
        'expires_at' => 'datetime',
    ];
}
