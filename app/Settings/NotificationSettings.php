<?php

namespace App\Settings;

use App\Models\Enums\ReservationEmailEvent;

final class NotificationSettings
{
    /**
     * @return list<string>
     */
    public static function recipientRoles(): array
    {
        return ['admin', 'student'];
    }

    /**
     * @return list<string>
     */
    public static function emailEventKeys(): array
    {
        return ReservationEmailEvent::values();
    }

    /**
     * @return array{admin: array<string, bool>, student: array<string, bool>}
     */
    public static function defaultNotifyEmailEvents(): array
    {
        return [
            'admin' => [
                'pending' => true,
                'approved' => false,
                'rejected' => false,
                'cancelled' => true,
                'expired' => true,
            ],
            'student' => [
                'pending' => false,
                'approved' => true,
                'rejected' => true,
                'cancelled' => true,
                'expired' => true,
            ],
        ];
    }
}
