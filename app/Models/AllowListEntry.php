<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AllowListEntry extends Model
{
    /** @use HasFactory<\Database\Factories\AllowListEntryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'import_batch_id',
        'imported_by',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $entry): void {
            $entry->email = Str::lower($entry->email);
        });
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
