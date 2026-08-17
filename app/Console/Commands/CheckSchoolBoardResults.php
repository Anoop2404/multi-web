<?php

namespace App\Console\Commands;

use App\Models\BoardResult;
use App\Models\BoardResultFullA1Achiever;
use App\Models\BoardResultSubjectTopper;
use App\Models\BoardResultTopper;
use App\Models\DataChangeLog;
use App\Models\Tenant;
use App\Models\Topper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckSchoolBoardResults extends Command
{
    protected $signature = 'board-results:check {school : School Tenant ID, prefix, or name}';

    protected $description = 'Inspect all saved board result records, toppers, and audit history for a school across all academic years.';

    public function handle(): int
    {
        $input = trim((string) $this->argument('school'));

        // 1. Find school tenant in central DB
        $school = Tenant::where('id', $input)
            ->orWhere('school_prefix', $input)
            ->orWhere('data->name', 'like', "%{$input}%")
            ->first();

        // 2. If not found in central DB, search within initialized tenant environments
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

        $schoolName = $school->data['name'] ?? $school->name ?? $school->id;
        $sahodayaId = $school->parent_id;
        $sahodaya = null;

        if ($sahodayaId) {
            $sahodaya = Tenant::find($sahodayaId);
            if (! $sahodaya) {
                config(['database.default' => 'central']);
                $sahodaya = Tenant::find($sahodayaId);
            }
        }

        if ($sahodaya) {
            try {
                tenancy()->initialize($sahodaya);
            } catch (\Throwable $e) {
                $this->warn("Tenancy initialization warning: " . $e->getMessage());
            }
        }

        $dbName = config('database.connections.tenant.database') ?? config('database.connections.pgsql.database');

        $this->info("=================================================");
        $this->info("  School: {$schoolName}");
        $this->info("  ID: {$school->id}");
        $this->info("  Prefix: " . ($school->school_prefix ?? 'None'));
        $this->info("  Parent Sahodaya: " . ($sahodaya?->name ?? $sahodayaId ?? 'None'));
        $this->info("  Active Tenant DB: {$dbName}");
        $this->info("=================================================\n");

        // Preload all BoardResults for quick lookup
        $boardResultMap = BoardResult::where('tenant_id', $school->id)->get()->keyBy('id');

        // Query BoardResult records via Eloquent
        $results = $boardResultMap->values();

        $this->info("1. BOARD RESULTS SUMMARY (" . $results->count() . " saved rows):");
        if ($results->isEmpty()) {
            $this->warn("   No BoardResult records found in database for this school.");
        } else {
            $headers = ['ID', 'Year', 'Class', 'Status', 'Appeared', 'Pass %', 'Pdf File', 'Updated At'];
            $rows = $results->map(fn (BoardResult $r) => [
                $r->id,
                $r->academic_year,
                "Class {$r->class}",
                strtoupper((string) $r->status),
                $r->total_appeared ?? 0,
                ($r->pass_percent ?? 0) . '%',
                ! empty($r->result_pdf_path) ? 'Yes (Uploaded)' : 'No',
                $r->updated_at ? $r->updated_at->format('Y-m-d H:i') : 'N/A',
            ])->all();
            $this->table($headers, $rows);
        }

        // Query Overall & Stream Toppers
        $overallToppers = Topper::where('tenant_id', $school->id)->where('entry_type', '!=', 'full_a1')->get();

        $this->info("\n2. OVERALL & STREAM TOPPERS (" . $overallToppers->count() . " records):");
        if ($overallToppers->isEmpty()) {
            $this->warn("   No Overall Topper records found.");
        } else {
            $headers = ['ID', 'Name', 'Result ID', 'Year / Class', 'Entry Type', 'Stream', 'Marks', 'Percentage', 'Rank'];
            $rows = $overallToppers->map(function ($t) use ($boardResultMap) {
                $br = $boardResultMap->get($t->board_result_id);
                $yearClass = $br ? "{$br->academic_year} (Class {$br->class})" : 'Unknown';
                return [
                    $t->id,
                    $t->name ?? $t->student_name ?? 'N/A',
                    $t->board_result_id ?? 'N/A',
                    $yearClass,
                    $t->entry_type ?? 'overall',
                    $t->stream ?? 'N/A',
                    ($t->marks_obtained ?? '-') . '/' . ($t->total_marks ?? '-'),
                    ! empty($t->percentage) ? $t->percentage . '%' : '-',
                    $t->rank ?? '-',
                ];
            })->all();
            $this->table($headers, $rows);
        }

        // Query Full A1 Achievers from toppers table (entry_type = 'full_a1')
        $fullA1Toppers = Topper::where('tenant_id', $school->id)->where('entry_type', 'full_a1')->get();
        if ($fullA1Toppers->isEmpty() && Schema::hasTable('board_result_full_a1_achievers')) {
            $fullA1Toppers = BoardResultFullA1Achiever::where('school_id', $school->id)->get();
        }

        $this->info("\n3. FULL A1 ACHIEVERS (" . $fullA1Toppers->count() . " records):");
        if ($fullA1Toppers->isEmpty()) {
            $this->warn("   No Full A1 Achiever records found.");
        } else {
            $headers = ['ID', 'Student Name', 'Result ID', 'Year / Class', 'Roll No', 'Stream'];
            $rows = $fullA1Toppers->map(function ($t) use ($boardResultMap) {
                $br = $boardResultMap->get($t->board_result_id);
                $yearClass = $br ? "{$br->academic_year} (Class {$br->class})" : 'Unknown';
                return [
                    $t->id,
                    $t->name ?? $t->student_name ?? 'N/A',
                    $t->board_result_id ?? 'N/A',
                    $yearClass,
                    $t->roll_no ?? 'N/A',
                    $t->stream ?? 'N/A',
                ];
            })->all();
            $this->table($headers, $rows);
        }

        // Query Subject Toppers
        $subjectToppers = collect();
        if (Schema::hasTable('board_result_subject_toppers')) {
            $subjectToppers = BoardResultSubjectTopper::where('school_id', $school->id)->get();
        }
        $this->info("\n4. SUBJECT TOPPERS (" . $subjectToppers->count() . " records):");
        if ($subjectToppers->isNotEmpty()) {
            $headers = ['ID', 'Student Name', 'Subject', 'Score', 'Year', 'Class'];
            $rows = $subjectToppers->map(fn ($st) => [
                $st->id,
                $st->student_name,
                $st->subject_name,
                $st->score,
                $st->academic_year,
                "Class {$st->class}",
            ])->all();
            $this->table($headers, $rows);
        } else {
            $this->warn("   No Subject Topper records found.");
        }

        // Query Data Change Audit Logs
        $logs = DataChangeLog::where('school_id', $school->id)
            ->whereIn('log_name', ['board_result', 'topper', 'achievement'])
            ->latest()
            ->limit(10)
            ->get();

        $this->info("\n5. RECENT AUDIT LOGS (" . $logs->count() . " recent entries):");
        if ($logs->isNotEmpty()) {
            $headers = ['ID', 'Date', 'Action', 'Description', 'Board Result / Topper ID', 'User ID'];
            $rows = $logs->map(fn ($l) => [
                $l->id,
                $l->created_at ? $l->created_at->format('Y-m-d H:i') : 'N/A',
                $l->action,
                $l->description,
                $l->subject_id ?? 'N/A',
                $l->causer_user_id,
            ])->all();
            $this->table($headers, $rows);
        } else {
            $this->warn("   No recent audit logs found.");
        }

        return self::SUCCESS;
    }
}
