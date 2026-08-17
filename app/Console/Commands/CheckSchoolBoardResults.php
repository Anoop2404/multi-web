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

        // Discover all tenant databases
        $tenantDbs = ['sahodaya'];
        try {
            $databases = DB::select("SELECT datname FROM pg_database WHERE datname LIKE 'sahodaya%'");
            foreach ($databases as $d) {
                if (! in_array($d->datname, $tenantDbs, true)) {
                    $tenantDbs[] = $d->datname;
                }
            }
        } catch (\Throwable) {
            // MySQL or non-pg fallback
        }

        $foundSchool = null;
        $foundDb = null;
        $foundSahodaya = null;

        foreach ($tenantDbs as $dbName) {
            try {
                config(['database.connections.dynamic.driver' => 'pgsql']);
                config(['database.connections.dynamic.host' => '127.0.0.1']);
                config(['database.connections.dynamic.port' => 5432]);
                config(['database.connections.dynamic.database' => $dbName]);
                config(['database.connections.dynamic.username' => env('DB_USERNAME', 'postgres')]);
                config(['database.connections.dynamic.password' => env('DB_PASSWORD', '')]);
                DB::purge('dynamic');

                if (Schema::connection('dynamic')->hasTable('tenants')) {
                    $schoolRow = DB::connection('dynamic')->table('tenants')
                        ->where('id', $input)
                        ->orWhere('school_prefix', $input)
                        ->orWhere('data', 'like', "%{$input}%")
                        ->first();

                    if ($schoolRow) {
                        $foundSchool = $schoolRow;
                        $foundDb = $dbName;
                        $foundSahodaya = $schoolRow->parent_id ? DB::connection('dynamic')->table('tenants')->where('id', $schoolRow->parent_id)->first() : null;
                        break;
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        if (! $foundSchool) {
            // Check central database
            $schoolRow = Tenant::where('id', $input)
                ->orWhere('school_prefix', $input)
                ->first();
            if ($schoolRow) {
                $foundSchool = (object) [
                    'id' => $schoolRow->id,
                    'school_prefix' => $schoolRow->school_prefix,
                    'parent_id' => $schoolRow->parent_id,
                    'data' => json_encode(['name' => $schoolRow->name]),
                ];
                $foundSahodaya = $schoolRow->parent_id ? Tenant::find($schoolRow->parent_id) : null;
                if ($foundSahodaya) {
                    try {
                        tenancy()->initialize($foundSahodaya);
                        $foundDb = config('database.connections.tenant.database');
                    } catch (\Throwable) {
                        // ignore
                    }
                }
            }
        }

        if (! $foundSchool) {
            $this->error("School matching '{$input}' could not be found across any tenant database.");
            return self::FAILURE;
        }

        $schoolData = is_string($foundSchool->data ?? null) ? json_decode($foundSchool->data, true) : (array) ($foundSchool->data ?? []);
        $schoolName = $schoolData['name'] ?? $foundSchool->name ?? $foundSchool->id;

        $this->info("=================================================");
        $this->info("  School: {$schoolName}");
        $this->info("  ID: {$foundSchool->id}");
        $this->info("  Prefix: " . ($foundSchool->school_prefix ?? 'None'));
        $this->info("  Database: " . ($foundDb ?? 'Central'));
        $this->info("=================================================\n");

        // Query BoardResult records
        $queryConn = $foundDb ? DB::connection('dynamic') : DB::connection();
        
        $results = collect();
        if (Schema::connection($foundDb ? 'dynamic' : null)->hasTable('board_results')) {
            $results = $queryConn->table('board_results')
                ->where('tenant_id', $foundSchool->id)
                ->orderByDesc('academic_year')
                ->orderByDesc('class')
                ->get();
        }

        $this->info("1. BOARD RESULTS SUMMARY (" . $results->count() . " saved rows):");
        if ($results->isEmpty()) {
            $this->warn("   No BoardResult records found in database for this school.");
        } else {
            $headers = ['ID', 'Year', 'Class', 'Status', 'Appeared', 'Pass %', 'Pdf File', 'Updated At'];
            $rows = $results->map(fn ($r) => [
                $r->id,
                $r->academic_year,
                "Class {$r->class}",
                strtoupper($r->status),
                $r->total_appeared ?? 0,
                ($r->pass_percent ?? 0) . '%',
                ! empty($r->result_pdf_path) ? 'Yes (Uploaded)' : 'No',
                $r->updated_at ?? 'N/A',
            ])->all();
            $this->table($headers, $rows);
        }

        // Query Toppers records
        $toppers = collect();
        if (Schema::connection($foundDb ? 'dynamic' : null)->hasTable('toppers')) {
            $toppers = $queryConn->table('toppers')->where('tenant_id', $foundSchool->id)->get();
        }

        $this->info("\n2. TOPPERS LIST (" . $toppers->count() . " records):");
        if ($toppers->isEmpty()) {
            $this->warn("   No Topper records found.");
        } else {
            $headers = ['ID', 'Name', 'Entry Type', 'Stream', 'Marks', 'Percentage', 'Rank'];
            $rows = $toppers->map(fn ($t) => [
                $t->id,
                $t->name ?? $t->student_name ?? 'N/A',
                $t->entry_type ?? 'overall',
                $t->stream ?? 'N/A',
                ($t->marks_obtained ?? '-') . '/' . ($t->total_marks ?? '-'),
                ! empty($t->percentage) ? $t->percentage . '%' : '-',
                $t->rank ?? '-',
            ])->all();
            $this->table($headers, $rows);
        }

        // Query Subject Toppers
        $subjectToppers = collect();
        if (Schema::connection($foundDb ? 'dynamic' : null)->hasTable('board_result_subject_toppers')) {
            $subjectToppers = $queryConn->table('board_result_subject_toppers')->where('school_id', $foundSchool->id)->get();
        }
        $this->info("\n3. SUBJECT TOPPERS (" . $subjectToppers->count() . " records):");
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

        // Query Full A1 Achievers
        $fullA1 = collect();
        if (Schema::connection($foundDb ? 'dynamic' : null)->hasTable('board_result_full_a1_achievers')) {
            $fullA1 = $queryConn->table('board_result_full_a1_achievers')->where('school_id', $foundSchool->id)->get();
        }
        $this->info("\n4. FULL A1 ACHIEVERS (" . $fullA1->count() . " records):");
        if ($fullA1->isNotEmpty()) {
            $headers = ['ID', 'Student Name', 'Year', 'Class'];
            $rows = $fullA1->map(fn ($fa1) => [
                $fa1->id,
                $fa1->student_name,
                $fa1->academic_year,
                "Class {$fa1->class}",
            ])->all();
            $this->table($headers, $rows);
        } else {
            $this->warn("   No Full A1 Achiever records found.");
        }

        // Query Data Change Audit Logs
        $logs = collect();
        if (Schema::connection($foundDb ? 'dynamic' : null)->hasTable('data_change_logs')) {
            $logs = $queryConn->table('data_change_logs')
                ->where('school_id', $foundSchool->id)
                ->whereIn('log_name', ['board_result', 'topper', 'achievement'])
                ->latest()
                ->limit(10)
                ->get();
        }

        $this->info("\n5. RECENT AUDIT LOGS (" . $logs->count() . " recent entries):");
        if ($logs->isNotEmpty()) {
            $headers = ['Date', 'Action', 'Description', 'User ID'];
            $rows = $logs->map(fn ($l) => [
                $l->created_at ?? 'N/A',
                $l->action,
                $l->description,
                $l->causer_user_id,
            ])->all();
            $this->table($headers, $rows);
        } else {
            $this->warn("   No recent audit logs found.");
        }

        return self::SUCCESS;
    }
}
