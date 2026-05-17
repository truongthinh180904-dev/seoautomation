<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Traits\BelongsToTenant;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'role',
        'zalo_user_id',
        'avatar_url',
        'last_login_at',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'password' => 'hashed',
        'role' => UserRole::class,
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'user_id');
    }

    public function reviewedArticles(): HasMany
    {
        return $this->hasMany(Article::class, 'reviewed_by');
    }

    public function publishedArticles(): HasMany
    {
        return $this->hasMany(Article::class, 'published_by');
    }
}
