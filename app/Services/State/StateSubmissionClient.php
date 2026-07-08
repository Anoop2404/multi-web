<?php

namespace App\Services\State;

use App\Models\FestStateSubmissionOutbox;
use App\Models\StateDomain;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class StateSubmissionClient
{
    public function send(FestStateSubmissionOutbox $outbox, StateDomain $domain): FestStateSubmissionOutbox
    {
        $baseUrl = rtrim((string) $domain->api_base_url, '/');
        abort_if($baseUrl === '', 422, 'State domain API URL is not configured.');

        $outbox->increment('attempts');
        $outbox->update(['status' => 'sending', 'sent_at' => now()]);

        try {
            $response = Http::timeout(30)
                ->withHeaders($this->authHeaders($domain))
                ->post("{$baseUrl}/api/v1/state/qualifiers/intake", [
                    'idempotency_key' => $outbox->idempotency_key,
                    'payload'         => $outbox->payload,
                ]);

            if ($response->successful()) {
                $body = $response->json();
                $outbox->update([
                    'status'             => 'completed',
                    'state_response_id'  => $body['intake_id'] ?? null,
                    'state_response'     => $body,
                    'completed_at'       => now(),
                    'last_error'         => null,
                ]);
            } else {
                $outbox->update([
                    'status'     => 'failed',
                    'last_error' => Str::limit($response->body(), 2000),
                ]);
            }
        } catch (\Throwable $e) {
            $outbox->update([
                'status'     => 'failed',
                'last_error' => Str::limit($e->getMessage(), 2000),
            ]);
        }

        return $outbox->fresh();
    }

    /** @return array<string, string> */
    private function authHeaders(StateDomain $domain): array
    {
        return array_filter([
            'X-State-Client-Id'     => $domain->api_client_id,
            'X-State-Client-Secret' => $this->clientSecret($domain),
            'Accept'                => 'application/json',
        ]);
    }

    private function clientSecret(StateDomain $domain): ?string
    {
        $encrypted = $domain->meta['api_client_secret'] ?? null;
        if (! $encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }
}
