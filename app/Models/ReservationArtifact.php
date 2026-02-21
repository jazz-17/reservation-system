<?php

namespace App\Models;

use App\Models\Enums\ReservationArtifactKind;
use App\Models\Enums\ReservationArtifactStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationArtifact extends Model
{
    /** @use HasFactory<\Database\Factories\ReservationArtifactFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reservation_id',
        'kind',
        'status',
        'attempts',
        'last_attempt_at',
        'last_error',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'kind' => ReservationArtifactKind::class,
            'status' => ReservationArtifactStatus::class,
            'payload' => 'array',
            'last_attempt_at' => 'immutable_datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
