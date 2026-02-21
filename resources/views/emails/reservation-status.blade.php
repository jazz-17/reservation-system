@php
    /** @var \App\Mail\ReservationStatusMail $message */
    $reservation = $reservation ?? $message->reservation;
    $timezone = $timezone ?? $message->timezone;

    $startsAt = \Carbon\CarbonImmutable::parse($reservation->starts_at)->setTimezone($timezone);
    $endsAt = \Carbon\CarbonImmutable::parse($reservation->ends_at)->setTimezone($timezone);
@endphp

<p>Hola,</p>

@if ($message->event === 'approved')
    <p>Tu reserva fue <strong>aprobada</strong>.</p>
@elseif ($message->event === 'rejected')
    <p>Tu reserva fue <strong>rechazada</strong>.</p>
@elseif ($message->event === 'cancelled')
    <p>Tu reserva fue <strong>cancelada</strong>.</p>
@elseif ($message->event === 'expired')
    <p>Tu solicitud de reserva <strong>expiró</strong> por falta de aprobación.</p>
@else
    <p>Hay una actualización en tu reserva.</p>
@endif

<ul>
    <li><strong>ID:</strong> {{ $reservation->id }}</li>
    <li><strong>Inicio:</strong> {{ $startsAt->format('d/m/Y H:i') }} ({{ $timezone }})</li>
    <li><strong>Fin:</strong> {{ $endsAt->format('d/m/Y H:i') }} ({{ $timezone }})</li>
    <li><strong>Estado:</strong> {{ is_object($reservation->status) ? $reservation->status->value : $reservation->status }}</li>
</ul>

@if (!empty($reservation->decision_reason))
    <p><strong>Motivo:</strong> {{ $reservation->decision_reason }}</p>
@endif

@if (!empty($reservation->cancellation_reason))
    <p><strong>Motivo de cancelación:</strong> {{ $reservation->cancellation_reason }}</p>
@endif

<p>Gracias.</p>
