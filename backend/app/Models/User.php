<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use BelongsToTenant, HasApiTokens, HasFactory;

    protected $fillable = [
        'tenant_id',
        'default_workspace_id',
        'name',
        'email',
        'password',
        'role',
        'is_platform_admin',
        'zalo_user_id',
        'avatar_url',
        'last_login_at',
        'is_active',
        'email_verified_at',
        'remember_token',
    ];

    protected $attributes = [
        'role' => 'editor',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
        'role' => UserRole::class,
        'is_active' => 'boolean',
        'is_platform_admin' => 'boolean',
        'last_login_at' => 'datetime',
        'email_verified_at' => 'datetime',
    ];

    public function defaultWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'default_workspace_id');
    }

    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    public function workspaceMembers(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_members')
            ->withPivot(['role', 'joined_at', 'is_active'])
            ->withTimestamps();
    }

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
