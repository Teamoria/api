<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel(
    'App.Models.User.{id}',
    fn (User $user, string $id): bool => $user->id === $id,
);

Broadcast::channel(
    'chat.{userId}',
    fn (User $user, string $userId): bool => $user->id === $userId,
);
