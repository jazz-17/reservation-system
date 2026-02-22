<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProfessionalSchool extends Model
{
    /** @use HasFactory<\Database\Factories\ProfessionalSchoolFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'faculty_id',
        'code',
        'name',
        'base_year_min',
        'base_year_max',
        'active',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $school): void {
            $school->code = $school->code !== null ? Str::lower(trim($school->code)) : null;
        });
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }
}
