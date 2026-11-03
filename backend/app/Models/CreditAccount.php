<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'available_credits',
        'reserved_credits',
        'cycle_granted_credits',
        'cycle_used_credits',
        'period_start',
        'period_end',
        'version',
    ];

    protected $casts = [
        'available_credits' => 'integer',
        'reserved_credits' => 'integer',
        'cycle_granted_credits' => 'integer',
        'cycle_used_credits' => 'integer',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'version' => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function grants(): HasMany
    {
        return $this->hasMany(CreditGrant::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function getTotalCreditsAttribute(): int
    {
        return (int) ($this->available_credits + $this->reserved_credits);
    }

    public function hasSufficientCredits(int $amount): bool
    {
        return $this->available_credits >= $amount;
    }
}
