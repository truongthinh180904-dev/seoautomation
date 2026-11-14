<?php

namespace App\Models;

use App\Traits\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ChatPendingAction extends Model
{
    use BelongsToWorkspace;
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'chat_pending_actions';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'conversation_id',
        'message_id',
        'workspace_id',
        'user_id',
        'action_name',
        'target_entity_type',
        'target_entity_id',
        'estimated_credits',
        'action_payload',
        'status',
        'reviewed_at',
        'expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'action_payload' => 'array',
        'estimated_credits' => 'integer',
        'reviewed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ChatPendingAction $action): void {
            if (empty($action->uuid)) {
                $action->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Cuộc trò chuyện chứa hành động chờ duyệt.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Tin nhắn khởi tạo yêu cầu duyệt (nếu có).
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'message_id');
    }

    /**
     * Không gian làm việc (Workspace) sở hữu hành động.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Người dùng khởi tạo yêu cầu hành động.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Kiểm tra hành động có đang ở trạng thái chờ duyệt và chưa hết hạn.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending' && ! $this->isExpired();
    }

    /**
     * Kiểm tra hành động đã hết hạn hiệu lực hay chưa.
     */
    public function isExpired(): bool
    {
        if ($this->status === 'expired') {
            return true;
        }

        return $this->expires_at ? now()->greaterThan($this->expires_at) : false;
    }

    /**
     * Kiểm tra hành động đã được người dùng phê duyệt hay chưa.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Kiểm tra hành động đã bị người dùng từ chối/hủy bỏ hay chưa.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Scope lọc các hành động đang chờ duyệt còn hiệu lực.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending')->where('expires_at', '>', now());
    }

    /**
     * Scope lọc các hành động đã quá hạn thời gian 30 phút.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', 'pending')->where('expires_at', '<=', now());
    }
}
