<?php

namespace App\Actions\AllowList;

use Illuminate\Support\Str;

class StudentCodeParser
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = trim($value);
        if ($raw === '') {
            return null;
        }

        $raw = Str::of($raw)->replace(' ', '')->toString();

        if (preg_match('/^\d{2,32}$/', $raw) !== 1) {
            return null;
        }

        return $raw;
    }

    public static function baseYear(?string $studentCode): ?int
    {
        $normalized = self::normalize($studentCode);
        if ($normalized === null) {
            return null;
        }

        $yy = (int) substr($normalized, 0, 2);
        $year = 2000 + $yy;

        return $year >= 2000 && $year <= 2100 ? $year : null;
    }
}
