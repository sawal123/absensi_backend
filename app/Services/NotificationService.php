<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserNotification;

class NotificationService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function send(
        User $user,
        string $title,
        string $body,
        string $type = 'info',
        array $data = [],
    ): UserNotification {
        return UserNotification::query()->create([
            'user_id' => $user->id,
            'title' => $title,
            'body' => $body,
            'type' => $type,
            'data' => $data,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function notifyManagers(
        string $title,
        string $body,
        string $type = 'info',
        array $data = [],
        ?int $exceptUserId = null,
    ): void {
        $managers = User::query()
            ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPERVISOR])
            ->where('is_active', true)
            ->when($exceptUserId !== null, fn ($query) => $query->whereKeyNot($exceptUserId))
            ->get();

        foreach ($managers as $manager) {
            self::send($manager, $title, $body, $type, $data);
        }
    }
}
