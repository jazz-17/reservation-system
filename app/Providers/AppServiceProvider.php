<?php

namespace App\Providers;

use App\Mail\Transports\Smtp2GoTransport;
use App\Models\Reservation;
use App\Policies\ReservationPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
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

        $this->configureMailTransports();
        $this->configureDefaults();
    }

    protected function configureMailTransports(): void
    {
        Mail::extend('smtp2go', function (): Smtp2GoTransport {
            return new Smtp2GoTransport(
                http: $this->app->make(HttpFactory::class),
                apiKey: (string) config('services.smtp2go.key'),
                endpoint: (string) config('services.smtp2go.endpoint', 'https://api.smtp2go.com/v3'),
                timeoutSeconds: (int) config('services.smtp2go.timeout', 10),
                fastaccept: (bool) config('services.smtp2go.fastaccept', false),
            );
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

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
}
