<?php

namespace App\Services\Mail;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ZeptoMailApiClient
{
    private const ENDPOINTS = [
        'in' => 'https://api.zeptomail.in/v1.1/email',
        'com'=> 'https://api.zeptomail.com/v1.1/email',
        'eu' => 'https://api.zeptomail.eu/v1.1/email',
    ];

    public function __construct(
        private readonly string $apiKey,
        private readonly string $region = 'in',
    ) {}

    /**
     * @param  list<array{address: string, name?: string|null}>  $to
     * @param  list<array{content: string, name: string, mime?: string}>  $attachments
     */
    public function send(
        string $fromAddress,
        ?string $fromName,
        array $to,
        string $subject,
        string $htmlBody,
        ?string $textBody = null,
        array $attachments = [],
    ): void {
        $endpoint = self::ENDPOINTS[$this->region] ?? self::ENDPOINTS['in'];

        $payload = [
            'from' => array_filter([
                'address' => $fromAddress,
                'name'    => $fromName,
            ]),
            'to' => collect($to)->map(fn (array $recipient) => [
                'email_address' => array_filter([
                    'address' => $recipient['address'],
                    'name'    => $recipient['name'] ?? null,
                ]),
            ])->values()->all(),
            'subject'  => $subject,
            'htmlbody' => $htmlBody,
        ];

        if ($textBody !== null && $textBody !== '') {
            $payload['textbody'] = $textBody;
        }

        if ($attachments !== []) {
            $payload['attachments'] = collect($attachments)->map(fn (array $file) => [
                'name'      => $file['name'],
                'content'   => base64_encode($file['content']),
                'mime_type' => $file['mime'] ?? 'application/octet-stream',
            ])->values()->all();
        }

        $response = Http::withHeaders([
            'accept'        => 'application/json',
            'content-type'  => 'application/json',
            'authorization' => $this->authorizationHeader(),
        ])->timeout(30)->post($endpoint, $payload);

        if (! $response->successful()) {
            $message = $response->json('message')
                ?? $response->json('error.message')
                ?? $response->body();

            throw new RuntimeException('ZeptoMail API error: '.$message);
        }
    }

    private function authorizationHeader(): string
    {
        $key = trim($this->apiKey);

        if (str_starts_with(strtolower($key), 'zoho-enczapikey')) {
            return $key;
        }

        return 'Zoho-enczapikey '.$key;
    }
}
