<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

final class NotificationFeed
{
    /**
     * @var list<string>
     */
    public const OPERATIONAL = ['low_stock', 'customer_due', 'supplier_due'];

    /**
     * @return array{unread: int, operational: list<array<string, mixed>>, approvals: list<array<string, mixed>>}
     */
    public static function bell(User $user): array
    {
        return [
            'unread' => $user->unreadNotifications()->count(),
            'operational' => $user->unreadNotifications()
                ->whereIn('data->kind', self::OPERATIONAL)
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (DatabaseNotification $notification): array => self::present($notification))
                ->all(),
            'approvals' => $user->unreadNotifications()
                ->where(function ($query): void {
                    $query->whereNull('data->kind')
                        ->orWhereNotIn('data->kind', self::OPERATIONAL);
                })
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (DatabaseNotification $notification): array => self::present($notification))
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'kind' => $notification->data['kind'] ?? null,
            'title' => (string) ($notification->data['title'] ?? 'Notification'),
            'message' => (string) ($notification->data['message'] ?? ''),
            'url' => $notification->data['url'] ?? null,
            'read' => $notification->read_at !== null,
            'created_at' => $notification->created_at?->timezone((string) config('app.timezone'))->format('d-M-Y H:i'),
        ];
    }
}
