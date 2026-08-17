<?php

namespace App\Console\Commands;

use App\Models\DataChangeLog;
use App\Models\Tenant;
use Illuminate\Console\Command;

class AuditBoardResultDeletion extends Command
{
    protected $signature = 'board-results:audit-deletion {school : School Tenant ID, prefix, or name}';

    protected $description = 'Find exact timestamps and user details of when board result records were created, modified, or deleted.';

    public function handle(): int
    {
        $input = trim((string) $this->argument('school'));

        $school = Tenant::where('id', $input)
            ->orWhere('school_prefix', $input)
            ->orWhere('data->name', 'like', "%{$input}%")
            ->first();

        if (! $school) {
            $parentTenants = Tenant::whereNull('parent_id')->orWhere('type', 'sahodaya')->get();
            foreach ($parentTenants as $pt) {
                try {
                    tenancy()->initialize($pt);
                    $candidate = Tenant::where('id', $input)
                        ->orWhere('school_prefix', $input)
                        ->orWhere('name', 'like', "%{$input}%")
                        ->first();
                    if ($candidate) {
                        $school = $candidate;
                        break;
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        if (! $school) {
            $this->error("School matching '{$input}' could not be found.");
            return self::FAILURE;
        }

        $sahodayaId = $school->parent_id;
        if ($sahodayaId) {
            $sahodaya = Tenant::find($sahodayaId);
            if (! $sahodaya) {
                config(['database.default' => 'central']);
                $sahodaya = Tenant::find($sahodayaId);
            }
            if ($sahodaya) {
                try {
                    tenancy()->initialize($sahodaya);
                } catch (\Throwable $e) {
                    $this->warn("Tenancy initialization warning: " . $e->getMessage());
                }
            }
        }

        $schoolName = $school->data['name'] ?? $school->name ?? $school->id;
        $this->info("=================================================");
        $this->info("  AUDIT TIMELINE FOR: {$schoolName}");
        $this->info("  School ID: {$school->id}");
        $this->info("=================================================\n");

        $logs = DataChangeLog::where('school_id', $school->id)
            ->whereIn('log_name', ['board_result', 'topper', 'achievement'])
            ->orderBy('created_at')
            ->get();

        if ($logs->isEmpty()) {
            $this->warn("No board result logs found.");
            return self::SUCCESS;
        }

        $headers = ['Log ID', 'Date & Time', 'Action', 'Description', 'Subject ID', 'User ID', 'Properties'];
        $rows = $logs->map(fn ($l) => [
            $l->id,
            $l->created_at ? $l->created_at->format('Y-m-d H:i:s') : 'N/A',
            strtoupper($l->action),
            $l->description,
            $l->subject_id ?? 'N/A',
            $l->causer_user_id,
            json_encode($l->properties ?? []),
        ])->all();

        $this->table($headers, $rows);

        return self::SUCCESS;
    }
}
