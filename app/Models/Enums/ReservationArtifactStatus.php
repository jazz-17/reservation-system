<?php

namespace App\Models\Enums;

enum ReservationArtifactStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
}

