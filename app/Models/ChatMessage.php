<?php

namespace App\Models;

use App\Enums\MessageRole;
use Database\Factories\ChatMessageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    /** @use HasFactory<ChatMessageFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'chat_session_id',
        'role',
        'content',
    ];

    protected function casts(): array
    {
        return [
            'role' => MessageRole::class,
        ];
    }

    public function chatSession(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class);
    }
}
