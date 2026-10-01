<?php

namespace App\Services\Notification;

use App\Notifications\NotificationChannelName;
use Illuminate\Support\Facades\DB;

class AdminNotificationRecipientResolver
{
    public function __construct(private readonly EmailConfigurationService $email) {}

    /** @return array<int, array{id: ?int, destination: string}> */
    public function forChannel(string $channel): array
    {
        if ($channel === NotificationChannelName::EMAIL) {
            $destination = mb_strtolower(trim((string) ($this->email->values()['admin_notification_email'] ?? '')));

            if (filter_var($destination, FILTER_VALIDATE_EMAIL) !== false) {
                return [['id' => null, 'destination' => $destination]];
            }
        }

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
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'destination' => $channel === NotificationChannelName::EMAIL
                    ? mb_strtolower(trim((string) $row->destination))
                    : trim((string) $row->destination),
            ])
            ->filter(fn (array $recipient): bool => $channel !== NotificationChannelName::EMAIL
                || filter_var($recipient['destination'], FILTER_VALIDATE_EMAIL) !== false)
            ->unique('destination')
            ->values()
            ->all();
    }
}
