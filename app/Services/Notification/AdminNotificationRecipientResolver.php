<?php

namespace App\Services\Notification;

use App\Notifications\NotificationChannelName;
use Illuminate\Support\Facades\DB;

class AdminNotificationRecipientResolver
{
    /** @return array<int, array{id: int, destination: string}> */
    public function forChannel(string $channel): array
    {
        $column = $channel === NotificationChannelName::EMAIL ? 'users.email' : 'users.mobile';

        return DB::table('users')
            ->join('auth_user_roles', 'auth_user_roles.user_id', '=', 'users.id')
            ->join('auth_roles', 'auth_roles.id', '=', 'auth_user_roles.role_id')
            ->whereIn('auth_roles.slug', ['admin', 'super_admin'])
            ->where('auth_roles.status', 'active')
            ->whereNull('auth_roles.deleted_at')
            ->where('users.status', 'active')
            ->whereNull('users.deleted_at')
            ->whereNotNull($column)
            ->select('users.id', DB::raw("{$column} as destination"))
            ->distinct()
            ->get()
            ->map(fn ($row): array => ['id' => (int) $row->id, 'destination' => (string) $row->destination])
            ->all();
    }
}
