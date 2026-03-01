<?php

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

test('smtp2go transport sends via API', function () {
    config()->set('services.smtp2go.key', 'api-test-key');
    config()->set('services.smtp2go.endpoint', 'https://api.smtp2go.com/v3');
    config()->set('services.smtp2go.timeout', 10);
    config()->set('services.smtp2go.fastaccept', false);

    config()->set('mail.from.address', 'from@example.com');
    config()->set('mail.from.name', 'ReservaFISI');

    Http::fake(function () {
        return Http::response([
            'request_id' => 'req_123',
            'data' => [
                'succeeded' => 1,
                'failed' => 0,
                'failures' => [],
                'email_id' => 'email_123',
            ],
        ]);
    });

    $mail = new class extends Mailable
    {
        public function envelope(): Envelope
        {
            return new Envelope(subject: 'Test Subject');
        }

        public function content(): Content
        {
            return new Content(htmlString: '<p>Hola</p>');
        }
    };

    Mail::mailer('smtp2go')
        ->to('jane@example.com')
        ->send($mail);

    Http::assertSent(function (Illuminate\Http\Client\Request $request): bool {
        if ($request->url() !== 'https://api.smtp2go.com/v3/email/send') {
            return false;
        }

        if ($request->header('X-Smtp2go-Api-Key')[0] !== 'api-test-key') {
            return false;
        }

        $data = $request->data();

        return $data['sender'] === '"ReservaFISI" <from@example.com>'
            && $data['to'] === ['jane@example.com']
            && $data['subject'] === 'Test Subject'
            && $data['fastaccept'] === false
            && str_contains((string) ($data['html_body'] ?? ''), 'Hola');
    });
});

test('smtp2go transport includes attachments', function () {
    config()->set('services.smtp2go.key', 'api-test-key');
    config()->set('services.smtp2go.endpoint', 'https://api.smtp2go.com/v3');

    Http::fake(function () {
        return Http::response([
            'request_id' => 'req_123',
            'data' => [
                'succeeded' => 1,
                'failed' => 0,
                'failures' => [],
                'email_id' => 'email_123',
            ],
        ]);
    });

    $mail = new class extends Mailable
    {
        public function envelope(): Envelope
        {
            return new Envelope(subject: 'Attachment Test');
        }

        public function content(): Content
        {
            return new Content(htmlString: '<p>Adjunto</p>');
        }
    };

    $mail->attachData('PDFDATA', 'reporte.pdf', ['mime' => 'application/pdf']);

    Mail::mailer('smtp2go')
        ->to('jane@example.com')
        ->send($mail);

    Http::assertSent(function (Illuminate\Http\Client\Request $request): bool {
        $data = $request->data();

        if (! isset($data['attachments'][0])) {
            return false;
        }

        return $data['attachments'][0]['filename'] === 'reporte.pdf'
            && $data['attachments'][0]['mimetype'] === 'application/pdf'
            && $data['attachments'][0]['fileblob'] === base64_encode('PDFDATA');
    });
});

test('smtp2go transport throws when API returns failure', function () {
    config()->set('services.smtp2go.key', 'api-test-key');
    config()->set('services.smtp2go.endpoint', 'https://api.smtp2go.com/v3');

    Http::fake(function () {
        return Http::response([
            'request_id' => 'req_123',
            'data' => [
                'succeeded' => 0,
                'failed' => 1,
                'failures' => [
                    ['email' => 'jane@example.com', 'error' => 'rejected'],
                ],
            ],
        ], 200);
    });

    $mail = new class extends Mailable
    {
        public function envelope(): Envelope
        {
            return new Envelope(subject: 'Test Subject');
        }

        public function content(): Content
        {
            return new Content(htmlString: '<p>Hola</p>');
        }
    };

    expect(fn () => Mail::mailer('smtp2go')->to('jane@example.com')->send($mail))
        ->toThrow(Symfony\Component\Mailer\Exception\TransportException::class);
});
