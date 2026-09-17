<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

// Sends via Brevo's HTTPS REST API instead of raw SMTP sockets, since
// Railway blocks outbound SMTP entirely (confirmed: 30s timeout on
// smtp.gmail.com:587/465), but plain HTTPS is never blocked.
class BrevoApiTransport extends AbstractTransport
{
    public function __construct(private readonly string $apiKey)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();

        if (! $email instanceof Email) {
            throw new TransportException('BrevoApiTransport only supports Email messages.');
        }

        $from = $email->getFrom();

        $payload = [
            'sender' => $this->formatAddress($from[0]),
            'to' => $this->formatAddresses($email->getTo()),
            'subject' => (string) $email->getSubject(),
        ];

        if ($cc = $email->getCc()) {
            $payload['cc'] = $this->formatAddresses($cc);
        }

        if ($bcc = $email->getBcc()) {
            $payload['bcc'] = $this->formatAddresses($bcc);
        }

        if ($replyTo = $email->getReplyTo()) {
            $payload['replyTo'] = $this->formatAddress($replyTo[0]);
        }

        if ($html = $email->getHtmlBody()) {
            $payload['htmlContent'] = $html;
        }

        if ($text = $email->getTextBody()) {
            $payload['textContent'] = $text;
        }

        $attachments = [];

        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $filename = $headers->getHeaderParameter('Content-Disposition', 'filename') ?? 'attachment';

            $attachments[] = [
                'name' => $filename,
                'content' => base64_encode($attachment->getBody()),
            ];
        }

        if ($attachments !== []) {
            $payload['attachment'] = $attachments;
        }

        $response = Http::withHeaders([
            'api-key' => $this->apiKey,
            'accept' => 'application/json',
        ])->post('https://api.brevo.com/v3/smtp/email', $payload);

        if ($response->failed()) {
            throw new TransportException(
                'Brevo API send failed: '.$response->status().' '.$response->body()
            );
        }
    }

    private function formatAddress(Address $address): array
    {
        $formatted = ['email' => $address->getAddress()];

        if ($name = $address->getName()) {
            $formatted['name'] = $name;
        }

        return $formatted;
    }

    private function formatAddresses(array $addresses): array
    {
        return array_map(fn (Address $address) => $this->formatAddress($address), $addresses);
    }

    public function __toString(): string
    {
        return 'brevo+api://';
    }
}
