@php
    /** @var \App\Models\Reservation $reservation */
    $user = $reservation->user;
    $startsAt = \Carbon\CarbonImmutable::parse($reservation->starts_at)->setTimezone($timezone);
    $endsAt = \Carbon\CarbonImmutable::parse($reservation->ends_at)->setTimezone($timezone);
@endphp

<!doctype html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <title>Reserva #{{ $reservation->id }}</title>
        <style>
            body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
            h1 { font-size: 18px; margin: 0 0 8px; }
            .muted { color: #6b7280; }
            .box { border: 1px solid #e5e7eb; padding: 12px; border-radius: 6px; }
            table { width: 100%; border-collapse: collapse; }
            td { padding: 6px 0; vertical-align: top; }
            .label { width: 160px; color: #374151; }
        </style>
    </head>
    <body>
        <h1>Constancia de Reserva</h1>
        <p class="muted">Sistema de Reservas de Cancha</p>

        <div class="box">
            <table>
                <tr>
                    <td class="label"><strong>ID de reserva</strong></td>
                    <td>#{{ $reservation->id }}</td>
                </tr>
                <tr>
                    <td class="label"><strong>Estado</strong></td>
                    <td>{{ is_object($reservation->status) ? $reservation->status->value : $reservation->status }}</td>
                </tr>
                <tr>
                    <td class="label"><strong>Inicio</strong></td>
                    <td>{{ $startsAt->format('d/m/Y H:i') }} ({{ $timezone }})</td>
                </tr>
                <tr>
                    <td class="label"><strong>Fin</strong></td>
                    <td>{{ $endsAt->format('d/m/Y H:i') }} ({{ $timezone }})</td>
                </tr>
            </table>
        </div>

        <h2 style="font-size: 14px; margin: 16px 0 8px;">Estudiante</h2>
        <div class="box">
            <table>
                <tr>
                    <td class="label"><strong>Nombre</strong></td>
                    <td>{{ $user?->name ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="label"><strong>Correo</strong></td>
                    <td>{{ $user?->email ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="label"><strong>Escuela / Base</strong></td>
                    <td>{{ $reservation->professionalSchool?->name ?? '—' }} / {{ $reservation->baseLabel() }}</td>
                </tr>
            </table>
        </div>

        <h2 style="font-size: 14px; margin: 16px 0 8px;">Condiciones de uso</h2>
        <div class="box">
            <ul style="margin: 0; padding-left: 16px;">
                <li>Presentarse puntualmente y respetar el horario asignado.</li>
                <li>Dejar el área limpia y en buen estado.</li>
                <li>La administración puede cancelar la reserva por motivos de fuerza mayor.</li>
            </ul>
        </div>
    </body>
</html>
