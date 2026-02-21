<?php

namespace App\Models\Enums;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function blocksAvailability(): bool
    {
        return in_array($this, [self::Pending, self::Approved], true);
    }
}

