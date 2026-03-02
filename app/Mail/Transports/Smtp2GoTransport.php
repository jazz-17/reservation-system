<?php

namespace App\Mail\Transports;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\Part\DataPart;
use Throwable;

class Smtp2GoTransport extends AbstractTransport
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $apiKey,
        private readonly string $endpoint,
        private readonly int $timeoutSeconds,
        private readonly bool $fastaccept,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $from = $email->getFrom();
        if (count($from) === 0) {
            throw new TransportException('SMTP2GO requires a sender address.');
        }

        $subject = $email->getSubject();
        if (! is_string($subject) || $subject === '') {
            throw new TransportException('SMTP2GO requires a subject.');
        }

        $url = rtrim($this->endpoint, '/').'/email/send';

        $payload = [
            'sender' => $from[0]->toString(),
            'to' => $this->formatAddresses($email->getTo()),
            'subject' => $subject,
            'fastaccept' => $this->fastaccept,
        ];

        if (count($payload['to']) === 0) {
            throw new TransportException('SMTP2GO requires at least one recipient.');
        }

        $cc = $this->formatAddresses($email->getCc());
        if (count($cc) > 0) {
            $payload['cc'] = $cc;
        }

        $bcc = $this->formatAddresses($email->getBcc());
        if (count($bcc) > 0) {
            $payload['bcc'] = $bcc;
        }

        $replyTo = $this->formatAddresses($email->getReplyTo());
        if (count($replyTo) > 0) {
            $payload['custom_headers'] = [
                [
                    'header' => 'Reply-To',
                    'value' => $replyTo[0],
                ],
            ];
        }

        $htmlBody = $this->readBody($email->getHtmlBody());
        if (is_string($htmlBody) && $htmlBody !== '') {
            $payload['html_body'] = $htmlBody;
        }

        $textBody = $this->readBody($email->getTextBody());
        if (is_string($textBody) && $textBody !== '') {
            $payload['text_body'] = $textBody;
        }

        $attachments = [];
        $inlines = [];

        foreach ($email->getAttachments() as $part) {
            if (! $part instanceof DataPart) {
                continue;
            }

            $item = [
                'filename' => $part->getDisposition() === 'inline'
                    ? $part->getContentId()
                    : ($part->getFilename() ?? 'attachment'),
                'mimetype' => $part->getContentType(),
                'fileblob' => base64_encode($part->getBody()),
            ];

            if ($part->getDisposition() === 'inline') {
                $inlines[] = $item;
            } else {
                $attachments[] = $item;
            }
        }

        if (count($attachments) > 0) {
            $payload['attachments'] = $attachments;
        }

        if (count($inlines) > 0) {
            $payload['inlines'] = $inlines;
        }

        $this->logRequest($url, $payload);

        try {
            $response = $this->http
                ->acceptJson()
                ->asJson()
                ->timeout($this->timeoutSeconds)
                ->withHeaders([
                    'X-Smtp2go-Api-Key' => $this->apiKey,
                ])
                ->post($url, $payload);
        } catch (Throwable $exception) {
            $this->logError(
                message: 'smtp2go.exception',
                context: [
                    'url' => $url,
                    'exception' => get_class($exception),
                    'error' => $exception->getMessage(),
                ],
            );

            throw new TransportException(
                message: 'SMTP2GO request failed: '.$exception->getMessage(),
                code: 0,
                previous: $exception,
            );
        }

        if (! $response->successful()) {
            $body = $response->json();

            $error = is_array($body)
                ? (Arr::get($body, 'data.error') ?? Arr::get($body, 'message') ?? $response->body())
                : $response->body();

            $this->logError(
                message: 'smtp2go.http_error',
                context: [
                    'url' => $url,
                    'status' => $response->status(),
                    'request_id' => is_array($body) ? Arr::get($body, 'request_id') : null,
                    'error' => is_string($error) ? $error : json_encode($error),
                    'body' => config('services.smtp2go.log_response', true) ? $this->stringifyBody($body, $response->body()) : null,
                ],
            );

            throw new TransportException(sprintf(
                'SMTP2GO request failed (HTTP %s): %s',
                $response->status(),
                is_string($error) ? $error : json_encode($error),
            ));
        }

        $json = $response->json();
        $failed = is_array($json) ? (int) Arr::get($json, 'data.failed', 0) : 0;

        $this->logResponse($url, $response->status(), $json);

        if ($failed > 0) {
            $failures = is_array($json) ? Arr::get($json, 'data.failures', []) : [];

            $this->logError(
                message: 'smtp2go.failed_recipients',
                context: [
                    'url' => $url,
                    'request_id' => is_array($json) ? Arr::get($json, 'request_id') : null,
                    'failed' => $failed,
                    'failures' => $failures,
                ],
            );

            throw new TransportException(sprintf(
                'SMTP2GO reported %d failed recipients: %s',
                $failed,
                is_string($failures) ? $failures : json_encode($failures),
            ));
        }

        $emailId = is_array($json) ? Arr::get($json, 'data.email_id') : null;
        if (is_string($emailId) && $emailId !== '') {
            $message->setMessageId($emailId);
        }
    }

    public function __toString(): string
    {
        return 'smtp2go';
    }

    /**
     * @param  array<int, Address>  $addresses
     * @return array<int, string>
     */
    private function formatAddresses(array $addresses): array
    {
        $formatted = [];

        foreach ($addresses as $address) {
            $formatted[] = $address->toString();
        }

        return $formatted;
    }

    /**
     * @param  resource|string|null  $body
     */
    private function readBody(mixed $body): ?string
    {
        if ($body === null) {
            return null;
        }

        if (is_resource($body)) {
            $contents = stream_get_contents($body);

            return $contents === false ? null : $contents;
        }

        if (is_string($body)) {
            return $body;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function logRequest(string $url, array $payload): void
    {
        if (! $this->smtp2goLoggingEnabled()) {
            return;
        }

        $context = [
            'url' => $url,
            'timeout_seconds' => $this->timeoutSeconds,
            'fastaccept' => $this->fastaccept,
            'sender' => $payload['sender'] ?? null,
            'subject' => $payload['subject'] ?? null,
            'to_count' => isset($payload['to']) && is_array($payload['to']) ? count($payload['to']) : 0,
            'cc_count' => isset($payload['cc']) && is_array($payload['cc']) ? count($payload['cc']) : 0,
            'bcc_count' => isset($payload['bcc']) && is_array($payload['bcc']) ? count($payload['bcc']) : 0,
            'has_html_body' => array_key_exists('html_body', $payload),
            'has_text_body' => array_key_exists('text_body', $payload),
            'attachments_count' => isset($payload['attachments']) && is_array($payload['attachments']) ? count($payload['attachments']) : 0,
            'inlines_count' => isset($payload['inlines']) && is_array($payload['inlines']) ? count($payload['inlines']) : 0,
        ];

        if (config('services.smtp2go.log_payload', false)) {
            $context['recipients'] = [
                'to' => $payload['to'] ?? [],
                'cc' => $payload['cc'] ?? [],
                'bcc' => $payload['bcc'] ?? [],
            ];

            $context['attachments'] = $this->redactAttachmentBlobs(is_array($payload['attachments'] ?? null) ? $payload['attachments'] : []);
            $context['inlines'] = $this->redactAttachmentBlobs(is_array($payload['inlines'] ?? null) ? $payload['inlines'] : []);
        }

        $this->log(config('services.smtp2go.log_level', 'debug'), 'smtp2go.request', $context);
    }

    private function logResponse(string $url, int $status, mixed $body): void
    {
        if (! $this->smtp2goLoggingEnabled()) {
            return;
        }

        $context = [
            'url' => $url,
            'status' => $status,
            'request_id' => is_array($body) ? Arr::get($body, 'request_id') : null,
            'email_id' => is_array($body) ? Arr::get($body, 'data.email_id') : null,
            'succeeded' => is_array($body) ? Arr::get($body, 'data.succeeded') : null,
            'failed' => is_array($body) ? Arr::get($body, 'data.failed') : null,
        ];

        if (config('services.smtp2go.log_response', true)) {
            $context['body'] = $this->stringifyBody($body, null);
        }

        $this->log(config('services.smtp2go.log_level', 'debug'), 'smtp2go.response', $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logError(string $message, array $context = []): void
    {
        if (! $this->smtp2goLoggingEnabled()) {
            return;
        }

        $this->log('error', $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        $channel = (string) config('services.smtp2go.log_channel', 'smtp2go');

        Log::channel($channel)->log($this->normalizeLogLevel($level), $message, $context);
    }

    private function smtp2goLoggingEnabled(): bool
    {
        return (bool) config('services.smtp2go.log', false);
    }

    private function normalizeLogLevel(string $level): string
    {
        $level = strtolower(trim($level));

        return in_array($level, [
            'debug',
            'info',
            'notice',
            'warning',
            'error',
            'critical',
            'alert',
            'emergency',
        ], true) ? $level : 'debug';
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function redactAttachmentBlobs(array $items): array
    {
        $redacted = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $copy = $item;
            if (array_key_exists('fileblob', $copy)) {
                $copy['fileblob'] = '[base64 redacted]';
            }

            $redacted[] = $copy;
        }

        return $redacted;
    }

    private function stringifyBody(mixed $json, ?string $rawBody): ?string
    {
        if (is_array($json)) {
            $encoded = json_encode($json);

            return is_string($encoded) ? $this->truncate($encoded) : null;
        }

        if (is_string($rawBody)) {
            return $this->truncate($rawBody);
        }

        return null;
    }

    private function truncate(string $value, int $max = 2000): string
    {
        if (strlen($value) <= $max) {
            return $value;
        }

        return substr($value, 0, $max).'…';
    }
}
