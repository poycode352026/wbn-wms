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
     * Send a notification to all active users with one or more roles.
     *
     * @param string|array $role  One role string or an array of roles
     */
    public static function sendToRole(
        string|array $role,
        string       $type,
        string       $title,
        string       $message,
        array        $data = []
    ): void {
        $roles = (array) $role;
        User::whereIn('role', $roles)
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

    /**
     * Send to all active users with any of the given roles, excluding specific user IDs.
     *
     * @param string|array $roles  One or more role strings
     * @param int|array    $except User ID(s) to exclude
     */
    public static function sendToRoleExcept(
        string|array $roles,
        int|array    $except,
        string       $type,
        string       $title,
        string       $message,
        array        $data = []
    ): void {
        $roles  = (array) $roles;
        $except = (array) $except;

        User::whereIn('role', $roles)
            ->where('is_active', true)
            ->whereNotIn('id', $except)
            ->pluck('id')
            ->each(fn ($uid) => static::send($uid, $type, $title, $message, $data));
    }
}
