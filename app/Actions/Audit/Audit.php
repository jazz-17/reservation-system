<?php

namespace App\Actions\Audit;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Audit
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function record(string $eventType, ?User $actor = null, ?Model $subject = null, array $metadata = []): void
    {
        AuditEvent::query()->create([
            'event_type' => $eventType,
            'actor_id' => $actor?->id,
            'subject_type' => $subject !== null ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata,
        ]);
    }
}

