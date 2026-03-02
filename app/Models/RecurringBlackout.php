<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringBlackout extends Model
{
    /** @use HasFactory<\Database\Factories\RecurringBlackoutFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'weekday',
        'starts_time',
        'ends_time',
        'starts_on',
        'ends_on',
        'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date:Y-m-d',
            'ends_on' => 'immutable_date:Y-m-d',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
