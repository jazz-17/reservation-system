<?php

use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;

test('sends verification notification', function () {
    config()->set('queue.default', 'database');
    Queue::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect(route('home'));

    Queue::assertPushed(SendQueuedNotifications::class, function (SendQueuedNotifications $job) use ($user): bool {
        if (! $job->notification instanceof QueuedVerifyEmail) {
            return false;
        }

        return $job->notifiables->contains(fn ($notifiable): bool => $notifiable instanceof User && $notifiable->is($user));
    });
});

test('does not send verification notification if email is verified', function () {
    config()->set('queue.default', 'database');
    Queue::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect(route('dashboard', absolute: false));

    Queue::assertNotPushed(SendQueuedNotifications::class);
});

test('verification email notification uses custom views', function () {
    $user = User::factory()->unverified()->create([
        'first_name' => 'Juan',
        'last_name' => 'Pérez',
    ]);

    $mailMessage = (new QueuedVerifyEmail)->toMail($user);

    expect($mailMessage)->toBeInstanceOf(MailMessage::class);
    expect($mailMessage->subject)->toBe('Verifique su correo electrónico');
    expect($mailMessage->markdown)->toBeNull();
    expect($mailMessage->view)->toBe(['html' => 'emails.verify-email', 'text' => 'emails.verify-email-text']);

    $url = $mailMessage->viewData['url'];
    expect($url)->toBeString()->toContain('/email/verify');

    $html = view($mailMessage->view['html'], $mailMessage->viewData)->render();
    expect($html)->toContain('Verificar correo electr&oacute;nico')
        ->toContain(e($url));

    $text = view($mailMessage->view['text'], $mailMessage->viewData)->render();
    expect($text)->toContain($url);
});
