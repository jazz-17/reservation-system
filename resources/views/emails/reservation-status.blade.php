@php
    $event = $event ?? 'updated';
    $recipientKind = $recipientKind ?? 'student';
    $timezone = (string) config('app.timezone', 'America/Lima');
    $appName = config('app.name', 'Reservas');

    $startsAt = $reservation->starts_at->setTimezone($timezone);
    $endsAt = $reservation->ends_at->setTimezone($timezone);

    $studentName = trim(($reservation->user?->first_name ?? '') . ' ' . ($reservation->user?->last_name ?? ''));
    $studentEmail = $reservation->user?->email;
    $schoolName = $reservation->professionalSchool?->name;
    $facultyName = $reservation->professionalSchool?->faculty?->name;

    $statusLabel = match ($reservation->status?->value ?? $reservation->status) {
        'pending' => 'Pendiente',
        'approved' => 'Aprobada',
        'rejected' => 'Rechazada',
        'cancelled' => 'Cancelada',
        default => ucfirst((string) ($reservation->status?->value ?? $reservation->status)),
    };

    $isAdmin = $recipientKind === 'admin';
    $adminUrl = $isAdmin && $event === 'pending' ? route('admin.requests.index') : null;
@endphp
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="es">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta name="color-scheme" content="light" />
        <meta name="supported-color-schemes" content="light" />
        <meta name="format-detection" content="telephone=no,address=no,email=no,date=no" />
        <title>{{ $reservation->id }} - {{ $appName }}</title>
    </head>
    <body style="Margin:0; padding:0; background-color:#fafafa; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-text-size-adjust:none; color:#52525b; line-height:1.4;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#fafafa">
            {{-- Header --}}
            <tr>
                <td align="center" style="padding: 25px 16px 0;">
                    <table role="presentation" width="570" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td align="center" style="padding: 0 0 25px; font-size: 19px; line-height: 23px; font-weight: bold; color: #18181b;">
                                {{ $appName }}
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            {{-- Content card --}}
            <tr>
                <td align="center" style="padding: 0 16px;">
                    <table role="presentation" width="570" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="border: 1px solid #e4e4e7;">
                        {{-- Greeting --}}
                        <tr>
                            <td style="padding: 32px 32px 0;">
                                <h1 style="Margin:0 0 16px; font-size:18px; line-height:1.4; font-weight:bold; color:#18181b; text-align:left;">
                                    @if ($isAdmin)
                                        @if ($event === 'pending')
                                            Nueva solicitud de reserva
                                        @else
                                            Actualización de reserva #{{ $reservation->id }}
                                        @endif
                                    @else
                                        Hola{{ $studentName ? ' ' . explode(' ', $studentName)[0] : '' }},
                                    @endif
                                </h1>
                                <p style="Margin:0 0 16px; font-size:16px; line-height:1.5em; color:#52525b; text-align:left;">
                                    @if ($isAdmin && $event === 'pending')
                                        Se ha registrado una nueva solicitud de reserva que requiere su revisión.
                                    @elseif ($isAdmin)
                                        Hay una actualización en la reserva #{{ $reservation->id }}.
                                    @elseif ($event === 'approved')
                                        Tu reserva fue <strong>aprobada</strong>.
                                    @elseif ($event === 'rejected')
                                        Tu reserva fue <strong>rechazada</strong>.
                                    @elseif ($event === 'cancelled')
                                        Tu reserva fue <strong>cancelada</strong>.
                                    @elseif ($event === 'expired')
                                        Tu solicitud de reserva <strong>expiró</strong> por falta de aprobación.
                                    @elseif ($event === 'pending')
                                        Se ha registrado tu solicitud de reserva.
                                    @else
                                        Hay una actualización en tu reserva.
                                    @endif
                                </p>
                            </td>
                        </tr>
                        {{-- Details --}}
                        <tr>
                            <td style="padding: 0 32px 24px;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border: 1px solid #e4e4e7; border-radius: 4px;">
                                    <tr>
                                        <td style="padding: 12px 16px; border-bottom: 1px solid #e4e4e7; font-size: 14px; color: #71717a; width: 140px;">Reserva</td>
                                        <td style="padding: 12px 16px; border-bottom: 1px solid #e4e4e7; font-size: 14px; color: #18181b; font-weight: bold;">#{{ $reservation->id }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 12px 16px; border-bottom: 1px solid #e4e4e7; font-size: 14px; color: #71717a;">Fecha</td>
                                        <td style="padding: 12px 16px; border-bottom: 1px solid #e4e4e7; font-size: 14px; color: #18181b;">{{ ucfirst($startsAt->translatedFormat('l, d/m/Y')) }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 12px 16px; border-bottom: 1px solid #e4e4e7; font-size: 14px; color: #71717a;">Horario</td>
                                        <td style="padding: 12px 16px; border-bottom: 1px solid #e4e4e7; font-size: 14px; color: #18181b;">{{ $startsAt->format('H:i') }} – {{ $endsAt->format('H:i') }} ({{ $timezone }})</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 12px 16px;{{ $isAdmin ? ' border-bottom: 1px solid #e4e4e7;' : '' }} font-size: 14px; color: #71717a;">Estado</td>
                                        <td style="padding: 12px 16px;{{ $isAdmin ? ' border-bottom: 1px solid #e4e4e7;' : '' }} font-size: 14px; color: #18181b; font-weight: bold;">{{ $statusLabel }}</td>
                                    </tr>
                                    @if ($isAdmin)
                                        @if ($studentName)
                                            <tr>
                                                <td style="padding: 12px 16px; border-bottom: 1px solid #e4e4e7; font-size: 14px; color: #71717a;">Estudiante</td>
                                                <td style="padding: 12px 16px; border-bottom: 1px solid #e4e4e7; font-size: 14px; color: #18181b;">{{ $studentName }}@if ($studentEmail) <span style="color: #71717a;">({{ $studentEmail }})</span>@endif</td>
                                            </tr>
                                        @endif
                                        @if ($schoolName)
                                            <tr>
                                                <td style="padding: 12px 16px;{{ $facultyName ? ' border-bottom: 1px solid #e4e4e7;' : '' }} font-size: 14px; color: #71717a;">Escuela</td>
                                                <td style="padding: 12px 16px;{{ $facultyName ? ' border-bottom: 1px solid #e4e4e7;' : '' }} font-size: 14px; color: #18181b;">{{ $schoolName }}</td>
                                            </tr>
                                        @endif
                                        @if ($facultyName)
                                            <tr>
                                                <td style="padding: 12px 16px; font-size: 14px; color: #71717a;">Facultad</td>
                                                <td style="padding: 12px 16px; font-size: 14px; color: #18181b;">{{ $facultyName }}</td>
                                            </tr>
                                        @endif
                                    @endif
                                </table>
                            </td>
                        </tr>
                        {{-- Reason --}}
                        @if (!empty($reservation->decision_reason))
                            <tr>
                                <td style="padding: 0 32px 24px;">
                                    <p style="Margin:0; font-size:14px; line-height:1.5em; color:#52525b;">
                                        <strong>Motivo:</strong> {{ $reservation->decision_reason }}
                                    </p>
                                </td>
                            </tr>
                        @endif
                        @if (!empty($reservation->cancellation_reason))
                            <tr>
                                <td style="padding: 0 32px 24px;">
                                    <p style="Margin:0; font-size:14px; line-height:1.5em; color:#52525b;">
                                        <strong>Motivo de cancelación:</strong> {{ $reservation->cancellation_reason }}
                                    </p>
                                </td>
                            </tr>
                        @endif
                        {{-- CTA button (admin + pending only) --}}
                        @if ($adminUrl)
                            <tr>
                                <td align="center" style="padding: 0 32px 30px;">
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center">
                                        <tr>
                                            <td align="center" bgcolor="#112240" style="mso-padding-alt: 0;">
                                                <a href="{{ $adminUrl }}" style="font-size:15px; line-height:18px; font-weight:bold; color:#ffffff; text-decoration:none; display:inline-block; background-color:#112240; border-top:8px solid #112240; border-bottom:8px solid #112240; border-left:18px solid #112240; border-right:18px solid #112240;">
                                                    Revisar solicitud
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        @endif
                        {{-- Closing --}}
                        <tr>
                            <td style="padding: 0 32px 24px;">
                                <p style="Margin:0 0 4px; font-size:16px; line-height:1.5em; color:#52525b; text-align:left;">
                                    Saludos,<br />{{ $appName }}
                                </p>
                            </td>
                        </tr>
                        {{-- Divider --}}
                        <tr>
                            <td style="padding: 0 32px;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td style="border-top: 1px solid #e4e4e7; font-size: 0; line-height: 0;">&nbsp;</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        {{-- Fallback URL --}}
                        @if ($adminUrl)
                            <tr>
                                <td style="padding: 25px 32px 32px;">
                                    <p style="Margin:0 0 8px; font-size:14px; line-height:1.5em; color:#52525b;">
                                        Si tiene problemas haciendo clic en el botón, copie y pegue la siguiente URL en su navegador:
                                    </p>
                                    <p style="Margin:0; font-size:14px; line-height:1.5em; word-break:break-all;">
                                        <a href="{{ $adminUrl }}" style="color: #112240; text-decoration: underline;">{{ $adminUrl }}</a>
                                    </p>
                                </td>
                            </tr>
                        @endif
                    </table>
                </td>
            </tr>
            {{-- Footer --}}
            <tr>
                <td align="center" style="padding: 25px 32px;">
                    <table role="presentation" width="570" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td align="center" style="font-size: 12px; line-height: 1.5em; color: #a1a1aa;">
                                &copy; {{ date('Y') }} {{ $appName }}. Todos los derechos reservados.
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
