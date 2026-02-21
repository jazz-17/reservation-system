<?php

namespace App\Models\Enums;

enum UserRole: string
{
    case Student = 'student';
    case Admin = 'admin';
}

