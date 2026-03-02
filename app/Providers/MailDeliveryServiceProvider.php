<?php

namespace App\Providers;

use App\Mail\MailDeliveryState;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class MailDeliveryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(MessageSending::class, function (MessageSending $event): bool {
            if (! config('mail.delivery_enabled', true)) {
                MailDeliveryState::markSuppressed();

                return false;
            }

            return true;
        });

        Event::listen(NotificationSending::class, function (NotificationSending $event): bool {
            if ($event->channel !== 'mail') {
                return true;
            }

            if (! config('mail.delivery_enabled', true)) {
                MailDeliveryState::markSuppressed();

                return false;
            }

            return true;
        });
    }
}
