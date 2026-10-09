<?php

namespace App\Support\Monitoring;

use Illuminate\Database\Events\QueryExecuted;

class RequestTrace
{
    public int $queryCount = 0;

    public float $queryTimeMs = 0;

    public readonly string $id;

    public readonly int $started;

    public function __construct(public readonly string $method, public readonly string $url)
    {
        $this->id = bin2hex(random_bytes(16));
        $this->started = hrtime(true);
    }

    public function query(QueryExecuted $query): void
    {
        $this->queryCount++;
        $this->queryTimeMs += $query->time;
        // Never interpolate bindings. Also redact inline SQL literals and comments.
        $sql = preg_replace('/\/\*.*?\*\/|--[^\r\n]*|#[^\r\n]*/s', ' ', $query->sql);
        $sql = preg_replace('/\'(?:\'\'|\\\\.|[^\'\\\\])*\'|\b\d+(?:\.\d+)?\b/s', '?', $sql);
        if ($query->connection->getDriverName() !== 'pgsql') {
            $sql = preg_replace('/"(?:""|\\\\.|[^"\\\\])*"/s', '?', $sql);
        }
        self::write([
            'type' => 'query', 'request_id' => $this->id,
            'method' => $this->method, 'url' => $this->url,
            'connection' => $query->connectionName,
            'sql' => $sql, 'duration_ms' => $query->time,
        ]);
    }

    public static function statePath(): string
    {
        return storage_path('framework/monitoring-until');
    }

    public static function until(): int
    {
        return (int) @file_get_contents(self::statePath());
    }

    public static function write(array $data): void
    {
        // A monitoring disk failure must not fail a user request.
        try {
            $data = ['timestamp' => now()->toIso8601String()] + $data;
            @file_put_contents(storage_path('logs/monitoring-'.now()->format('Y-m-d').'.jsonl'),
                json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR)."\n", FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
            // Best-effort diagnostics only.
        }
    }
}
