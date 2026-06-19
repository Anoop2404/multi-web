<?php

namespace App\Services\Audit;

use App\Models\DataChangeLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DataChangeLogger
{
    public function __construct(private ?Request $request = null) {}

    public function created(
        Model $subject,
        string $description,
        ?string $schoolId = null,
        ?string $logName = null,
        array $properties = [],
    ): DataChangeLog {
        return $this->write('created', $subject, $description, $schoolId, $logName, null, $properties);
    }

    public function updated(
        Model $subject,
        string $description,
        array $changes,
        ?string $schoolId = null,
        ?string $logName = null,
        array $properties = [],
    ): DataChangeLog {
        return $this->write('updated', $subject, $description, $schoolId, $logName, $changes, $properties);
    }

    public function deleted(
        Model $subject,
        string $description,
        ?string $schoolId = null,
        ?string $logName = null,
        array $snapshot = [],
    ): DataChangeLog {
        return $this->write('deleted', $subject, $description, $schoolId, $logName, ['snapshot' => $snapshot]);
    }

    public function event(
        string $action,
        string $description,
        ?string $schoolId = null,
        ?string $logName = null,
        ?Model $subject = null,
        array $properties = [],
        ?array $changes = null,
    ): DataChangeLog {
        return $this->write($action, $subject, $description, $schoolId, $logName, $changes, $properties);
    }

    /** @param array<string, mixed> $before @param array<string, mixed> $after */
    public static function diff(array $before, array $after, array $only = []): array
    {
        $changes = [];
        $keys = $only !== [] ? $only : array_unique(array_merge(array_keys($before), array_keys($after)));

        foreach ($keys as $key) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;

            if ($old != $new) {
                $changes[$key] = ['old' => $old, 'new' => $new];
            }
        }

        return $changes;
    }

    private function write(
        string $action,
        ?Model $subject,
        string $description,
        ?string $schoolId,
        ?string $logName,
        ?array $changes,
        array $properties = [],
    ): DataChangeLog {
        $userId = auth()->id();

        return DataChangeLog::create([
            'school_id'       => $schoolId,
            'log_name'        => $logName ?? $this->inferLogName($subject),
            'action'          => $action,
            'description'     => $description,
            'subject_type'    => $subject?->getMorphClass(),
            'subject_id'      => $subject?->getKey(),
            'causer_user_id'  => $userId,
            'changes'         => $changes,
            'properties'      => $properties ?: null,
            'ip_address'      => $this->request?->ip(),
        ]);
    }

    private function inferLogName(?Model $subject): ?string
    {
        if (! $subject) {
            return null;
        }

        return Str::snake(class_basename($subject));
    }
}
