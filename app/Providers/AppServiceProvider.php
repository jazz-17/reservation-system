<?php

namespace App\Providers;

use App\Models\Reservation;
use App\Policies\ReservationPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Reservation::class, ReservationPolicy::class);

        $this->configureDefaults();
        $this->configureAuthNotifications();
        $this->configureRateLimiting();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Event::listen(function (ConnectionEstablished $event): void {
            if ($event->connection->getDriverName() !== 'pgsql') {
                return;
            }

            $event->connection->statement("set time zone 'UTC'");
        });

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }

    protected function configureAuthNotifications(): void
    {
        VerifyEmail::toMailUsing(function (object $notifiable, string $url): MailMessage {
            $firstName = data_get($notifiable, 'first_name');
            $lastName = data_get($notifiable, 'last_name');

            $displayName = trim(implode(' ', array_filter([
                is_string($firstName) ? $firstName : null,
                is_string($lastName) ? $lastName : null,
            ])));

            if ($displayName === '') {
                $name = data_get($notifiable, 'name');
                $displayName = is_string($name) ? $name : null;
            }

            return (new MailMessage)
                ->subject('Verifique su correo electrónico')
                ->view(['html' => 'emails.verify-email', 'text' => 'emails.verify-email-text'], [
                    'appName' => (string) config('app.name'),
                    'displayName' => $displayName,
                    'expiresMinutes' => (int) config('auth.verification.expire', 60),
                    'url' => $url,
                ]);
        });
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('public-availability', function (Request $request) {
            $sessionCookieName = (string) config('session.cookie');
            $sessionId = $sessionCookieName !== ''
                ? (string) $request->cookies->get($sessionCookieName, '')
                : '';

            $bucketKey = $sessionId !== ''
                ? 'session:'.$sessionId
                : 'ip-sessionless:'.$request->ip();

            return [
                Limit::perMinute((int) config('rate-limiting.public_availability.per_session_per_minute'))
                    ->by($bucketKey),
                Limit::perMinute((int) config('rate-limiting.public_availability.per_ip_per_minute'))
                    ->by('ip:'.$request->ip()),
            ];
        });
    }
}
