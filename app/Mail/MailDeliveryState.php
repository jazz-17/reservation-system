<?php

namespace App\Mail;

class MailDeliveryState
{
    private static bool $suppressed = false;

    public static function reset(): void
    {
        self::$suppressed = false;
    }

    public static function markSuppressed(): void
    {
        self::$suppressed = true;
    }

    public static function wasSuppressed(): bool
    {
        return self::$suppressed;
    }
}
