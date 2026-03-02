<?php

use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;

test('mail:test sends an email using the default mailer', function () {
    config()->set('mail.delivery_enabled', true);

    $this->artisan('mail:test jane@example.com --subject="Hello" --text="Plain"')
        ->assertExitCode(0);

    $transport = Mail::mailer('array')->getSymfonyTransport();
    expect($transport)->toBeInstanceOf(ArrayTransport::class);
    expect($transport->messages())->toHaveCount(1);
});

test('mail:test fails when mail delivery is disabled', function () {
    config()->set('mail.delivery_enabled', false);

    $this->artisan('mail:test jane@example.com')
        ->assertExitCode(1);
});
