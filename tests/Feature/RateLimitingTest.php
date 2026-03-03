<?php

use Carbon\CarbonImmutable;

test('register is rate limited', function () {
    config()->set('rate-limiting.register.per_ip_per_minute', 3);

    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/register', [])->assertStatus(422);
    }

    $this->postJson('/register', [])->assertTooManyRequests();
});

test('register rate limiting cannot be bypassed with spoofed forwarded headers', function () {
    config()->set('rate-limiting.register.per_ip_per_minute', 1);
    config()->set('rate-limiting.register.per_email_per_minute', 1200);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->withHeader('X-Forwarded-For', '1.1.1.1')
        ->postJson('/register', [])
        ->assertStatus(422);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->withHeader('X-Forwarded-For', '2.2.2.2')
        ->postJson('/register', [])
        ->assertTooManyRequests();
});

test('forgot password is rate limited', function () {
    config()->set('rate-limiting.forgot_password.per_email_per_minute', 2);

    $payload = ['email' => 'rate.limit.test@unmsm.edu.pe'];

    for ($i = 0; $i < 2; $i++) {
        $this->postJson(route('password.email'), $payload)->assertStatus(422);
    }

    $this->postJson(route('password.email'), $payload)->assertTooManyRequests();
});

test('public availability range is capped', function () {
    config()->set('rate-limiting.public_availability_max_days', 60);

    $start = CarbonImmutable::parse('2026-03-01 00:00:00', 'America/Lima');
    $end = $start->addDays(61);

    $this->getJson(route('api.public.availability', [
        'start' => $start->toIso8601String(),
        'end' => $end->toIso8601String(),
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['end']);
});

test('public availability is rate limited per session', function () {
    config()->set('rate-limiting.public_availability.per_session_per_minute', 3);
    config()->set('rate-limiting.public_availability.per_ip_per_minute', 1200);

    $this->withCredentials();
    $this->withCookie((string) config('session.cookie'), str_repeat('a', 40));

    $start = CarbonImmutable::parse('2026-03-01 00:00:00', 'America/Lima');
    $end = $start->addDays(1);

    for ($i = 0; $i < 3; $i++) {
        $this->getJson(route('api.public.availability', [
            'start' => $start->toIso8601String(),
            'end' => $end->toIso8601String(),
        ]))->assertOk();
    }

    $this->getJson(route('api.public.availability', [
        'start' => $start->toIso8601String(),
        'end' => $end->toIso8601String(),
    ]))->assertTooManyRequests();
});
