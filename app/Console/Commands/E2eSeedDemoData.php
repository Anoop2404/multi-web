<?php

namespace App\Console\Commands;

use App\Models\AcademicYearRecord;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestEventStaff;
use App\Models\FestJudgeAssignment;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\McqExam;
use App\Models\McqExamStaff;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Console\Command;

class E2eSeedDemoData extends Command
{
    protected $signature = 'e2e:seed-data';

    protected $description = 'Seed fest events, MCQ exams, training programs, and portal assignments for E2E deep-page tests';

    public function handle(): int
    {
        $sahodaya = Tenant::where('subdomain', 'malappuram')->where('type', 'sahodaya')->first();
        $school = Tenant::where('subdomain', 'amu-school')->where('type', 'school')->first();

        if (! $sahodaya || ! $school) {
            $this->error('Run DemoTenantsSeeder first.');

            return self::FAILURE;
        }

        $sahodaya->run(function () use ($sahodaya, $school) {
            $year = AcademicYearRecord::firstOrCreate(
                ['label' => '2025-26'],
                ['start_date' => '2025-04-01', 'end_date' => '2026-03-31', 'status' => 'active']
            );

            $event = FestEvent::updateOrCreate(
                ['tenant_id' => $sahodaya->id, 'title' => 'E2E Kalolsavam 2026'],
                [
                    'event_type'         => 'kalolsavam',
                    'conductor_level'    => 'sahodaya',
                    'level_round'        => 'sahodaya',
                    'conduct_levels'     => ['sahodaya'],
                    'academic_year_id'   => $year->id,
                    'registration_open'  => now()->subDays(7),
                    'registration_close' => now()->addMonths(2),
                    'event_start'        => now()->addMonth(),
                    'event_end'          => now()->addMonths(2),
                    'status'             => 'registration_open',
                    'fee_type'           => 'none',
                ]
            );

            $item = FestEventItem::firstOrCreate(
                ['event_id' => $event->id, 'title' => 'E2E Light Music'],
                [
                    'stage_type'       => 'on_stage',
                    'class_group'      => 'up',
                    'gender'           => 'open',
                    'participant_type' => 'individual',
                    'owner_level'      => 'sahodaya',
                ]
            );

            $exam = McqExam::updateOrCreate(
                ['tenant_id' => $sahodaya->id, 'title' => 'E2E MCQ Exam 2026'],
                [
                    'academic_year_id'  => $year->id,
                    'exam_type'         => 'assessment',
                    'conductor_level'   => 'sahodaya',
                    'scheduled_at'      => now()->addMonth(),
                    'duration_minutes'  => 60,
                    'total_questions'   => 50,
                    'pass_mark'         => 25,
                    'status'            => 'published',
                ]
            );

            TrainingProgram::updateOrCreate(
                ['tenant_id' => $sahodaya->id, 'title' => 'E2E Teacher Training 2026'],
                [
                    'academic_year_id'   => $year->id,
                    'description'        => 'Demo training for E2E tests',
                    'conductor_level'    => 'sahodaya',
                    'registration_open'  => now()->subDays(7),
                    'registration_close' => now()->addMonths(2),
                    'status'             => 'published',
                    'fee_type'           => 'none',
                ]
            );

            FestEvent::updateOrCreate(
                [
                    'tenant_id'            => $sahodaya->id,
                    'title'                => 'E2E School Kalotsav 2026',
                    'conducting_school_id' => $school->id,
                ],
                [
                    'event_type'         => 'kalolsavam',
                    'conductor_level'    => 'school',
                    'level_round'        => 'school',
                    'conduct_levels'     => ['school'],
                    'academic_year_id'   => $year->id,
                    'registration_open'  => now()->subDays(3),
                    'registration_close' => now()->addMonth(),
                    'event_start'        => now()->addWeeks(2),
                    'event_end'          => now()->addWeeks(3),
                    'status'             => 'registration_open',
                    'fee_type'           => 'none',
                ]
            );

            $this->seedPortalAssignments($sahodaya, $event, $item, $exam, $school);
        });

        $this->info('E2E demo data ready (fest event, MCQ exam, training program, portal assignments).');

        return self::SUCCESS;
    }

    private function seedPortalAssignments(
        Tenant $sahodaya,
        FestEvent $event,
        FestEventItem $item,
        McqExam $exam,
        Tenant $school,
    ): void {
        $judge = User::where('email', 'judge@e2e.test')->first();
        $festOps = User::where('email', 'festops@e2e.test')->first();
        $markCoord = User::where('email', 'mark_coordinator@e2e.test')->first();
        $examCtrl = User::where('email', 'exam@e2e.test')->first();

        if ($judge) {
            FestJudgeAssignment::firstOrCreate([
                'event_id' => $event->id,
                'item_id'  => $item->id,
                'user_id'  => $judge->id,
            ]);
        }

        if ($festOps) {
            foreach (['coordinator', 'stage', 'registration', 'attendance', 'food', 'appeals', 'certificates'] as $duty) {
                FestEventStaff::firstOrCreate([
                    'event_id' => $event->id,
                    'user_id'  => $festOps->id,
                    'duty'     => $duty,
                ]);
            }
        }

        if ($markCoord) {
            FestEventStaff::firstOrCreate([
                'event_id' => $event->id,
                'user_id'  => $markCoord->id,
                'duty'     => 'marks',
            ]);
        }

        if ($examCtrl) {
            McqExamStaff::firstOrCreate([
                'exam_id' => $exam->id,
                'user_id' => $examCtrl->id,
            ], [
                'role' => 'controller',
            ]);
        }

        $student = Student::where('tenant_id', $school->id)->active()->first();
        if ($student) {
            $registration = FestRegistration::firstOrCreate(
                [
                    'event_id'  => $event->id,
                    'school_id' => $school->id,
                    'item_id'   => $item->id,
                ],
                ['status' => 'approved'],
            );

            if ($registration->status !== 'approved') {
                $registration->update(['status' => 'approved']);
            }

            FestParticipant::firstOrCreate(
                [
                    'registration_id' => $registration->id,
                    'student_id'      => $student->id,
                ],
                [
                    'participant_type' => 'student',
                    'participant_role' => 'performer',
                ],
            );
        }
    }
}
