<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AiResponseReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChatMessage $message) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $this->message->loadMissing('chatSession');

        return [
            new PrivateChannel('chat.'.$this->message->chatSession->user_id),
        ];
    }

    /**
     * @return array{message: array{id: string, chat_session_id: string, role: string, content: string, created_at: ?string, updated_at: ?string}}
     */
    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => (string) $this->message->id,
                'chat_session_id' => (string) $this->message->chat_session_id,
                'role' => (string) $this->message->role->value,
                'content' => $this->message->content,
                'created_at' => $this->message->created_at?->toJSON(),
                'updated_at' => $this->message->updated_at?->toJSON(),
            ],
        ];
    }
}
