<?php

use Illuminate\Support\Facades\Http;

test('mail:test-smtp2go sends using smtp2go mailer', function () {
    config()->set('services.smtp2go.key', 'api-test-key');
    config()->set('services.smtp2go.endpoint', 'https://api.smtp2go.com/v3');

    config()->set('mail.from.address', 'from@example.com');
    config()->set('mail.from.name', 'ReservaFISI');
    config()->set('mail.delivery_enabled', true);

    Http::fake(function () {
        return Http::response([
            'request_id' => 'req_123',
            'data' => [
                'succeeded' => 1,
                'failed' => 0,
                'failures' => [],
                'email_id' => 'email_123',
            ],
        ], 200);
    });

    $this->artisan('mail:test-smtp2go jane@example.com --subject="Hello" --text="Plain"')
        ->assertExitCode(0);

    Http::assertSent(function (Illuminate\Http\Client\Request $request): bool {
        if ($request->url() !== 'https://api.smtp2go.com/v3/email/send') {
            return false;
        }

        $data = $request->data();

        return ($data['to'] ?? null) === ['jane@example.com']
            && ($data['subject'] ?? null) === 'Hello';
    });
});

test('mail:test-smtp2go fails when smtp2go returns http error', function () {
    config()->set('services.smtp2go.key', 'api-test-key');
    config()->set('services.smtp2go.endpoint', 'https://api.smtp2go.com/v3');

    config()->set('mail.from.address', 'from@example.com');
    config()->set('mail.from.name', 'ReservaFISI');
    config()->set('mail.delivery_enabled', true);

    Http::fake(function () {
        return Http::response([
            'request_id' => 'req_123',
            'data' => [
                'error' => 'Unauthorized',
            ],
        ], 401);
    });

    $this->artisan('mail:test-smtp2go jane@example.com')
        ->assertExitCode(1);
});
