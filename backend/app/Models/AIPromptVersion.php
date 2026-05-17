<?php

namespace App\Models;

use App\Enums\AgentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class AIPromptVersion extends Model
{
    protected $table = 'ai_prompt_versions';

    protected $fillable = [
        'tenant_id',
        'agent_type',
        'version',
        'name',
        'system_prompt',
        'user_prompt_template',
        'variables',
        'is_active',
        'is_default',
        'performance_score',
        'created_by',
    ];

    public $timestamps = false;
    const UPDATED_AT = null;

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'performance_score' => 'float',
        'created_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForAgent(Builder $query, AgentType $agent): Builder
    {
        return $query->where('agent_type', $agent->value);
    }
}
