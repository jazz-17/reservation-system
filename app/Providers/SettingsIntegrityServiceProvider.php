<?php

namespace App\Providers;

use App\Settings\SettingsIntegrity;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class SettingsIntegrityServiceProvider extends ServiceProvider
{
    public function boot(SettingsIntegrity $integrity): void
    {
        if ($this->shouldBypassIntegrityCheck()) {
            return;
        }

        $integrity->assertReady();
    }

    private function shouldBypassIntegrityCheck(): bool
    {
        if ($this->app->runningUnitTests()) {
            return true;
        }

        if (! $this->app->runningInConsole()) {
            return false;
        }

        $command = $_SERVER['argv'][1] ?? null;

        if (! is_string($command) || $command === '') {
            return true;
        }

        $safePrefixes = [
            'help',
            'list',
            'make:',
            'migrate',
            'db:',
            'key:generate',
            'config:',
            'cache:',
            'route:',
            'view:',
            'event:',
            'optimize',
            'settings:sync',
            'wayfinder:',
            'test',
        ];

        foreach ($safePrefixes as $prefix) {
            if (Str::startsWith($command, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
