<?php

namespace App\Traits;

use App\Models\ArticleVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasVersions
{
    public function createVersion(array $data, User $changedBy, string $reason): ArticleVersion
    {
        $versionNumber = $this->versionCount() + 1;
        
        return $this->versions()->create(array_merge($data, [
            'tenant_id' => $this->tenant_id,
            'version_number' => $versionNumber,
            'changed_by' => $changedBy->id,
            'change_reason' => $reason,
        ]));
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(ArticleVersion::class)->latestOfMany('version_number');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ArticleVersion::class);
    }

    public function versionCount(): int
    {
        return $this->versions()->count();
    }
}
