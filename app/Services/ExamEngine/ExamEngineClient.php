<?php

namespace App\Services\ExamEngine;

use App\Models\McqExam;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Laravel → exam engine internal API (exam-engine/CONTRACT.md §2–3). Every method throws
 * ExamEngineException when the engine is unconfigured, unreachable or answers with an error.
 */
class ExamEngineClient
{
    public function isConfigured(): bool
    {
        return filled(config('exam_engine.internal_url')) && filled(config('exam_engine.internal_token'));
    }

    // ── run setup (bridge) ────────────────────────────────────────────────

    public function putMeta(string $run, array $meta): array
    {
        return $this->send('put', $this->runPath($run), $meta);
    }

    /** @param  list<array<string, mixed>>  $entries */
    public function putRoster(string $run, array $entries): array
    {
        return $this->send('post', $this->runPath($run).'/roster', ['entries' => $entries]);
    }

    public function putRosterEntry(string $run, int $regId, array $entry): array
    {
        return $this->send('put', $this->runPath($run).'/roster/'.$regId, $entry);
    }

    public function deleteRosterEntry(string $run, int $regId): void
    {
        $this->send('delete', $this->runPath($run).'/roster/'.$regId);
    }

    public function putBundle(string $run, array $bundle): array
    {
        return $this->send('put', $this->runPath($run).'/bundle', $bundle);
    }

    public function disarm(string $run): void
    {
        $this->send('post', $this->runPath($run).'/disarm');
    }

    /** @param  array<int|string, array{score: float, max_score: float, percentage: float, grade: ?string}>  $results */
    public function putResults(string $run, array $results): void
    {
        $this->send('post', $this->runPath($run).'/results', ['results' => (object) $results]);
    }

    // ── live operations (monitor UI) ──────────────────────────────────────

    public function summary(McqExam $exam): array
    {
        return $this->send('get', $this->runPath(ExamEngineRun::for($exam)).'/summary');
    }

    public function attempts(McqExam $exam, array $filters = []): array
    {
        $query = array_filter($filters, fn ($v) => $v !== null && $v !== '');

        return $this->send('get', $this->runPath(ExamEngineRun::for($exam)).'/attempts', $query);
    }

    public function action(McqExam $exam, int $regId, string $action, array $payload, string $actor): array
    {
        return $this->send('post', $this->runPath(ExamEngineRun::for($exam))."/attempts/{$regId}/actions", array_merge($payload, [
            'action' => $action,
            'actor'  => $actor,
        ]));
    }

    public function broadcast(McqExam $exam, string $message, string $actor): void
    {
        $this->send('post', $this->runPath(ExamEngineRun::for($exam)).'/broadcast', ['message' => $message, 'actor' => $actor]);
    }

    public function pace(McqExam $exam, ?int $seconds): void
    {
        $this->send('post', $this->runPath(ExamEngineRun::for($exam)).'/pace', ['seconds' => $seconds]);
    }

    /** Adds minutes for every candidate of the exam (incident tool; CONTRACT §3 run controls). */
    public function extendRun(McqExam $exam, int $minutes, string $actor): array
    {
        return $this->send('post', $this->runPath(ExamEngineRun::for($exam)).'/extend', ['minutes' => $minutes, 'actor' => $actor]);
    }

    /** Pauses (true) or resumes (false) automatic submission at the deadline for the whole exam. */
    public function holdRun(McqExam $exam, bool $on, string $actor): array
    {
        return $this->send('post', $this->runPath(ExamEngineRun::for($exam)).'/hold', ['on' => $on, 'actor' => $actor]);
    }

    public function close(McqExam $exam): array
    {
        return $this->send('post', $this->runPath(ExamEngineRun::for($exam)).'/close');
    }

    // ── events (consumer) ─────────────────────────────────────────────────

    /** @return list<array<string, mixed>> */
    public function claimEvents(string $consumer, int $count = 200, int $blockMs = 2000): array
    {
        return $this->send('post', '/internal/events/claim', [
            'consumer' => $consumer, 'count' => $count, 'block_ms' => $blockMs,
        ], timeout: max(5, (int) ceil($blockMs / 1000) + 10))['events'] ?? [];
    }

    /** @param  list<string>  $ids */
    public function ackEvents(array $ids): int
    {
        return $ids === [] ? 0 : (int) ($this->send('post', '/internal/events/ack', ['ids' => array_values($ids)])['acked'] ?? 0);
    }

    public function health(): array
    {
        return $this->send('get', '/health');
    }

    // ── plumbing ──────────────────────────────────────────────────────────

    private function runPath(string $run): string
    {
        return '/internal/runs/'.rawurlencode($run);
    }

    private function request(?int $timeout = null): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new ExamEngineException('The online exam engine is not configured (EXAM_ENGINE_INTERNAL_URL / EXAM_ENGINE_INTERNAL_TOKEN).');
        }

        return Http::baseUrl(rtrim((string) config('exam_engine.internal_url'), '/'))
            ->withToken((string) config('exam_engine.internal_token'))
            ->acceptJson()
            ->asJson()
            ->timeout($timeout ?? (int) config('exam_engine.timeout_seconds', 15));
    }

    private function send(string $method, string $path, array $data = [], ?int $timeout = null): array
    {
        try {
            /** @var Response $response */
            $response = match ($method) {
                'get'    => $this->request($timeout)->get($path, $data),
                'delete' => $this->request($timeout)->delete($path, $data),
                default  => $this->request($timeout)->{$method}($path, $data),
            };
        } catch (ConnectionException $e) {
            throw new ExamEngineException('The online exam engine could not be reached: '.$e->getMessage());
        }

        if ($response->failed()) {
            throw new ExamEngineException(
                (string) ($response->json('message') ?? 'The online exam engine returned an error ('.$response->status().').'),
                $response->status(),
                $response->json('error'),
            );
        }

        return $response->json() ?? [];
    }
}
