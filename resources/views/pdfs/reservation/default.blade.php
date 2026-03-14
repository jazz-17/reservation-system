@php
    /** @var \App\Models\Reservation $reservation */
    $timezone = (string) config('app.timezone', 'America/Lima');
    $user = $reservation->user;
    $school = $reservation->professionalSchool;
    $startsAt = $reservation->starts_at->setTimezone($timezone);
    $endsAt = $reservation->ends_at->setTimezone($timezone);

    // Spanish month names
    $months = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];

    // Spanish day names
    $days = [
        0 => 'domingo', 1 => 'lunes', 2 => 'martes', 3 => 'miércoles',
        4 => 'jueves', 5 => 'viernes', 6 => 'sábado',
    ];

    $dateFormatted = 'Lima, ' . $startsAt->day . ' de ' . $months[$startsAt->month] . ' del ' . $startsAt->year;
    $dayName = $days[$startsAt->dayOfWeek];
    $startTime = $startsAt->format('H:i');
    $endTime = $endsAt->format('H:i');
    $dayAndDate = $dayName . ' ' . $startsAt->day . ' de ' . $months[$startsAt->month];

    $userName = trim(($user?->last_name ?? '') . ', ' . ($user?->first_name ?? ''));
    $schoolName = $school?->name ?? '—';
    $baseLabel = $reservation->baseLabel();
    $requester = "{$userName} E.P {$schoolName} {$baseLabel}";

    // Signature images (base64-encoded for DomPDF)
    $signaturePath = resource_path('pdfs/signatures');
    $signatures = [];
    foreach (['signer_1', 'signer_2', 'signer_3'] as $signer) {
        $file = $signaturePath . '/' . $signer . '.png';
        if (file_exists($file)) {
            $signatures[$signer] = 'data:image/png;base64,' . base64_encode(file_get_contents($file));
        }
    }
@endphp

<!doctype html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <title>Solicitud - Reserva #{{ $reservation->id }}</title>
        <style>
            @page {
                margin: 2.5cm 2.5cm 2cm 2.5cm;
            }

            body {
                font-family: 'Times New Roman', Times, 'DejaVu Serif', serif;
                font-size: 12pt;
                color: #000;
                line-height: 1.5;
                margin: 0;
                padding: 0;
            }

            .title {
                text-align: center;
                font-size: 16pt;
                font-weight: bold;
                margin-bottom: 24pt;
                letter-spacing: 0.5pt;
            }

            .date {
                text-align: right;
                margin-bottom: 24pt;
            }

            .recipient {
                margin-bottom: 20pt;
            }

            .recipient p {
                margin: 0;
                line-height: 1.4;
            }

            .subject {
                text-align: center;
                margin-bottom: 20pt;
            }

            .body-text {
                text-align: justify;
                margin-bottom: 12pt;
                text-indent: 48pt;
            }

            .closing {
                margin-top: 20pt;
                text-indent: 48pt;
            }

            .signatures {
                margin-top: 24pt;
                width: 100%;
            }

            .signatures td {
                text-align: center;
                vertical-align: bottom;
                padding: 0 8pt;
            }

            .signature-img {
                max-height: 80pt;
                max-width: 160pt;
            }

            .signer-name {
                font-size: 11pt;
                margin-top: 4pt;
            }

            .signer-title {
                font-size: 11pt;
            }
        </style>
    </head>
    <body>
        <div class="title">SOLICITUD POR PARTE DEL TERCIO ESTUDIANTIL</div>

        <div class="date">{{ $dateFormatted }}</div>

        <div class="recipient">
            <p>Dra. Luzmila Elisa Pro Concepción</p>
            <p><strong>Decana</strong></p>
            <p><strong>Facultad de Ingeniería de Sistemas e Informática</strong></p>
            <p><strong>Con copia para Dirección Administrativa</strong></p>
        </div>

        <div class="subject">
            <strong>Asunto:</strong> Solicitud de uso del campo de pasto sintético de la facultad
        </div>

        <p class="body-text">
            Tengo el agrado de dirigirme a ustedes para saludarles cordialmente y, solicitarle autorice,
            <strong>{{ $requester }}</strong>, que se nos permita el uso del campo de pasto sintético de
            la facultad para este <strong>{{ $dayAndDate }} a partir de las {{ $startTime }} horas hasta
            las {{ $endTime }} horas</strong>
        </p>

        <p class="body-text">
            Sin otro particular, agradezco de antemano la atención brindada a la presente solicitud y
            quedamos atentos a su pronta respuesta.
        </p>

        <p class="closing">Atentamente,</p>

        {{-- Signatures: two side-by-side, one centered below --}}
        <table class="signatures">
            <tr>
                <td style="width: 50%;">
                    @if(isset($signatures['signer_1']))
                        <img src="{{ $signatures['signer_1'] }}" class="signature-img" /><br />
                    @endif
                    <div class="signer-name">Mattos Hilario, Yayir Flabio</div>
                    <div class="signer-title">Consejero de facultad</div>
                </td>
                <td style="width: 50%;">
                    @if(isset($signatures['signer_2']))
                        <img src="{{ $signatures['signer_2'] }}" class="signature-img" /><br />
                    @endif
                    <div class="signer-name">Cuba García, Gabriel Isaac</div>
                    <div class="signer-title">Consejero de facultad</div>
                </td>
            </tr>
        </table>

        <table class="signatures" style="margin-top: 12pt;">
            <tr>
                <td style="width: 100%;">
                    @if(isset($signatures['signer_3']))
                        <img src="{{ $signatures['signer_3'] }}" class="signature-img" /><br />
                    @endif
                    <div class="signer-name">Salazar Zapata, Alvaro Matias</div>
                    <div class="signer-title">Consejero de facultad</div>
                </td>
            </tr>
        </table>
    </body>
</html>
