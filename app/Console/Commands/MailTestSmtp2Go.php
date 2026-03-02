<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Symfony\Component\Mailer\Exception\TransportException;
use Throwable;

class MailTestSmtp2Go extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:test-smtp2go
        {to : Recipient email address}
        {--subject=SMTP2GO test : Email subject}
        {--text=This is a test email. : Plain-text body}
        {--html= : Optional HTML body}
        {--mailer=smtp2go : Mailer name (default: smtp2go)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test email using the configured mailer (default: smtp2go)';

    public function handle(): int
    {
        $to = (string) $this->argument('to');
        $subject = (string) $this->option('subject');
        $text = (string) $this->option('text');
        $mailer = (string) $this->option('mailer');

        $html = $this->option('html');
        $html = is_string($html) && trim($html) !== '' ? $html : null;

        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error("Invalid recipient: {$to}");

            return self::FAILURE;
        }

        $deliveryEnabled = (bool) config('mail.delivery_enabled', true);
        $mailDefault = (string) config('mail.default', '');
        $fromAddress = (string) config('mail.from.address', '');
        $fromName = (string) config('mail.from.name', '');

        $this->line('=== Mail diagnostics ===');
        $this->line('MAIL_DELIVERY_ENABLED: '.($deliveryEnabled ? 'true' : 'false'));
        $this->line("mail.default: {$mailDefault}");
        $this->line("mailer: {$mailer}");
        $this->line("from: {$fromName} <{$fromAddress}>");

        if (! $deliveryEnabled) {
            $this->warn('Outbound email delivery is disabled (MAIL_DELIVERY_ENABLED=false).');
            $this->warn('Enable it to confirm real delivery.');

            return self::FAILURE;
        }

        if ($mailer === 'smtp2go') {
            $endpoint = (string) config('services.smtp2go.endpoint', '');
            $key = (string) config('services.smtp2go.key', '');

            $this->line('=== SMTP2GO diagnostics ===');
            $this->line("endpoint: {$endpoint}");
            $this->line('key_set: '.($key !== '' ? 'true' : 'false'));

            if ($key === '') {
                $this->error('Missing SMTP2GO API key (SMTP2GO_API_KEY).');

                return self::FAILURE;
            }
        }

        $view = [
            'raw' => $text,
        ];

        if (is_string($html) && $html !== '') {
            $view['html'] = new HtmlString($html);
        }

        try {
            Mail::mailer($mailer)->send($view, [], function (Message $message) use ($to, $subject): void {
                $message->to($to)->subject($subject);
            });
        } catch (TransportException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error(get_class($exception).': '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Mail send attempted successfully.');

        return self::SUCCESS;
    }
}
