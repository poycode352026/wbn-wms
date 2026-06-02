<?php

namespace App\Services;

use App\Models\User;
use App\Models\WmsNotification;

class NotificationService
{
    /**
     * Send a notification to a single user.
     */
    public static function send(
        int    $userId,
        string $type,
        string $title,
        string $message,
        array  $data = []
    ): void {
        WmsNotification::create([
            'user_id' => $userId,
            'type'    => $type,
            'title'   => $title,
            'message' => $message,
            'data'    => $data,
        ]);
    }

    /**
     * Send a notification to all active users with a given role.
     */
    public static function sendToRole(
        string $role,
        string $type,
        string $title,
        string $message,
        array  $data = []
    ): void {
        User::where('role', $role)
            ->where('is_active', true)
            ->pluck('id')
            ->each(fn ($uid) => static::send($uid, $type, $title, $message, $data));
    }

    /**
     * Send a notification to all active users with a given role in a specific department.
     */
    public static function sendToDeptRole(
        string $role,
        int    $deptId,
        string $type,
        string $title,
        string $message,
        array  $data = []
    ): void {
        User::where('role', $role)
            ->where('department_id', $deptId)
            ->where('is_active', true)
            ->pluck('id')
            ->each(fn ($uid) => static::send($uid, $type, $title, $message, $data));
    }
}
