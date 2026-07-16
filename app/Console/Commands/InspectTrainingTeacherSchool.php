<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\Teacher;
use App\Models\TrainingRegistration;
use Illuminate\Console\Command;

/**
 * Read-only diagnostic: for a given email (or teacher name fragment), dumps every
 * Teacher row that matches plus every TrainingRegistration row tied to those
 * teachers, across every Sahodaya (or a specific one via --sahodaya). Used to
 * settle whether "school shows on QR reports but '—' on Registrations for the
 * same person" is caused by duplicate Teacher/registration rows, a stale
 * school_id, or something else — without guessing from the UI alone.
 */
class InspectTrainingTeacherSchool extends Command
{
    protected $signature = 'training:inspect-teacher
        {lookup : Email (exact) or name fragment (partial, case-insensitive)}
        {--sahodaya= : Sahodaya tenant id or subdomain to narrow the search}';

    protected $description = 'Dump raw Teacher + TrainingRegistration rows for an email/name, bypassing display helpers, to debug school-not-showing reports';

    public function handle(): int
    {
        $lookup = trim((string) $this->argument('lookup'));
        $sahodayaOpt = $this->option('sahodaya');

        $tenants = Tenant::query()
            ->where('type', 'sahodaya')
            ->when($sahodayaOpt, function ($q) use ($sahodayaOpt) {
                $q->where(function ($inner) use ($sahodayaOpt) {
                    $inner->where('id', $sahodayaOpt)->orWhere('subdomain', $sahodayaOpt);
                });
            })
            ->get();

        if ($tenants->isEmpty()) {
            $this->error('No matching Sahodaya tenant(s). Pass --sahodaya to narrow the search.');

            return self::FAILURE;
        }

        $isEmail = (bool) filter_var($lookup, FILTER_VALIDATE_EMAIL);

        foreach ($tenants as $tenant) {
            try {
                $tenant->run(function () use ($lookup, $isEmail, $tenant) {
                    $schoolIds = Tenant::query()
                        ->where('type', 'school')
                        ->where('parent_id', $tenant->id)
                        ->pluck('id')
                        ->all();
                    $schoolIds[] = $tenant->id; // holding tenant (unlinked pending-school teachers)

                    $teachers = Teacher::query()
                        ->whereIn('tenant_id', $schoolIds)
                        ->when($isEmail, fn ($q) => $q->whereRaw('LOWER(email) = ?', [strtolower($lookup)]))
                        ->when(! $isEmail, fn ($q) => $q->where('name', 'like', '%'.$lookup.'%'))
                        ->get();

                    if ($teachers->isEmpty()) {
                        return;
                    }

                    $this->info("Sahodaya: {$tenant->name} ({$tenant->id})");

                    foreach ($teachers as $teacher) {
                        $schoolName = Tenant::find($teacher->tenant_id)?->name ?? 'UNKNOWN';
                        $this->line("Teacher #{$teacher->id}: {$teacher->name} <{$teacher->email}> tenant_id={$teacher->tenant_id} ({$schoolName}) verified_at=".($teacher->verified_at ?? 'NULL'));

                        $regs = TrainingRegistration::where('teacher_id', $teacher->id)
                            ->with(['program:id,title', 'school:id,name', 'pendingSchool'])
                            ->get();

                        if ($regs->isEmpty()) {
                            $this->line('   (no training registrations)');

                            continue;
                        }

                        $this->table(
                            ['reg id', 'program', 'school_id', 'school name', 'pending_school_id', 'pending name', 'status', 'source'],
                            $regs->map(fn (TrainingRegistration $r) => [
                                $r->id,
                                $r->program?->title ?? "#{$r->program_id}",
                                $r->school_id ?? 'NULL',
                                $r->school?->name ?? '—',
                                $r->pending_school_id ?? 'NULL',
                                $r->pendingSchool?->school_name ?? '—',
                                $r->status,
                                $r->registration_source,
                            ])->all()
                        );
                    }
                });
            } catch (\Throwable $e) {
                $this->warn("  ✗ {$tenant->name}: {$e->getMessage()}");
            } finally {
                if (function_exists('tenancy') && tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        }

        return self::SUCCESS;
    }
}
