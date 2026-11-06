<?php

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ConversationMessage extends Model
{
    use BelongsToWorkspace;
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'conversation_id',
        'workspace_id',
        'user_id',
        'role',
        'content',
        'tool_calls',
        'tool_results',
        'metadata',
        'action_type',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tool_calls' => 'array',
        'tool_results' => 'array',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (ConversationMessage $message): void {
            if (empty($message->uuid)) {
                $message->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Quan hệ Cuộc trò chuyện chứa tin nhắn này.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Quan hệ Không gian làm việc sở hữu tin nhắn.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Quan hệ Người dùng gửi tin nhắn (nếu có, null đối với AI/system).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
