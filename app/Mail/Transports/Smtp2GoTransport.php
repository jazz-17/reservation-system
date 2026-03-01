<?php

namespace App\Mail\Transports;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Arr;
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

            throw new TransportException(sprintf(
                'SMTP2GO request failed (HTTP %s): %s',
                $response->status(),
                is_string($error) ? $error : json_encode($error),
            ));
        }

        $json = $response->json();
        $failed = is_array($json) ? (int) Arr::get($json, 'data.failed', 0) : 0;

        if ($failed > 0) {
            $failures = is_array($json) ? Arr::get($json, 'data.failures', []) : [];

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
}
