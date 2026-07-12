<?php

namespace App\Console\Commands;

use App\Models\Registration;
use App\Models\SahodayaRegistrationWindow;
use App\Models\Tenant;
use App\Services\Membership\MembershipNotifier;
use App\Support\AcademicYear;
use App\Support\ReminderDedupGuard;
use App\Support\TenancyDatabase;
use Illuminate\Console\Command;

class SendMembershipRemindersCommand extends Command
{
    protected $signature = 'membership:send-reminders';

    protected $description = 'Send membership window and payment due reminders to schools';

    public function handle(MembershipNotifier $notifier): int
    {
        $year = AcademicYear::current();
        $today = now()->startOfDay();
        $sent = 0;

        $sahodayas = Tenant::query()->sahodayas()->where('is_active', true)->get();

        foreach ($sahodayas as $sahodaya) {
            TenancyDatabase::runWhenDatabaseReady($sahodaya, function () use ($notifier, $sahodaya, $year, $today, &$sent) {
                SahodayaRegistrationWindow::query()
                    ->where('sahodaya_id', $sahodaya->id)
                    ->where('academic_year', $year)
                    ->whereNotNull('registration_ends_at')
                    ->get()
                    ->each(function (SahodayaRegistrationWindow $window) use ($notifier, $sahodaya, $year, $today, &$sent) {
                        $ends = $window->registration_ends_at?->startOfDay();
                        if (! $ends) {
                            return;
                        }

                        $daysLeft = $today->diffInDays($ends, false);
                        if (! in_array($daysLeft, [7, 0], true)) {
                            return;
                        }

                        $schoolIds = Tenant::where('parent_id', $sahodaya->id)
                            ->where('type', 'school')
                            ->where('membership_status', 'approved')
                            ->pluck('id');

                        foreach ($schoolIds as $schoolId) {
                            $started = Registration::where('school_id', $schoolId)
                                ->where('academic_year', $year)
                                ->exists();

                            if ($started) {
                                continue;
                            }

                            if (! ReminderDedupGuard::claim('membership:window', $sahodaya->id, $schoolId, $daysLeft)) {
                                continue;
                            }

                            $school = Tenant::find($schoolId);
                            if ($school) {
                                $notifier->reminderWindowClosing($school, $year, (int) $daysLeft);
                                $sent++;
                            }
                        }
                    });

                Registration::query()
                    ->where('academic_year', $year)
                    ->where('registration_status', 'payment_pending')
                    ->whereIn('school_id', Tenant::where('parent_id', $sahodaya->id)->where('type', 'school')->pluck('id'))
                    ->with('school')
                    ->chunkById(50, function ($regs) use ($notifier, $sahodaya, &$sent) {
                        foreach ($regs as $reg) {
                            if (! $reg->school) {
                                continue;
                            }
                            if (! ReminderDedupGuard::claim('membership:payment-due', $sahodaya->id, $reg->id)) {
                                continue;
                            }
                            $notifier->reminderPaymentDue(
                                $reg->school,
                                $reg->academic_year,
                                (float) ($reg->membership_fee_amount ?? 0)
                            );
                            $sent++;
                        }
                    });
            });
        }

        $this->info("Sent {$sent} membership reminder(s).");

        return self::SUCCESS;
    }
}
