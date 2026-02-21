<?php

namespace App\Models\Enums;

enum ReservationArtifactKind: string
{
    case Pdf = 'pdf';
    case EmailAdmin = 'email_admin';
    case EmailStudent = 'email_student';
}

