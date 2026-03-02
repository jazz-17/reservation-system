<?php

namespace App\Models\Enums;

enum ReservationArtifactKind: string
{
    case EmailAdmin = 'email_admin';
    case EmailStudent = 'email_student';
}
