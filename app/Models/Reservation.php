<?php

namespace App\Models;

use App\Models\Enums\ReservationStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    /** @use HasFactory<\Database\Factories\ReservationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'status',
        'starts_at',
        'ends_at',
        'professional_school_id',
        'base_year',
        'decided_by',
        'decided_at',
        'decision_reason',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'decided_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'base_year' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function professionalSchool(): BelongsTo
    {
        return $this->belongsTo(ProfessionalSchool::class);
    }

    public function baseLabel(): string
    {
        $yy = str_pad((string) ($this->base_year % 100), 2, '0', STR_PAD_LEFT);

        return "B{$yy}";
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeBlocking(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ReservationStatus::Pending,
            ReservationStatus::Approved,
        ]);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', ReservationStatus::Approved);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $startUtc, CarbonInterface $endUtc): Builder
    {
        return $query
            ->where('starts_at', '<', $endUtc)
            ->where('ends_at', '>', $startUtc);
    }
}
