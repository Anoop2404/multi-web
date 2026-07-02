<?php

namespace App\Console\Commands;

use App\Models\MembershipPayment;
use App\Models\Registration;
use App\Models\SahodayaRegistrationWindow;
use App\Models\Tenant;
use App\Services\Membership\MembershipNotifier;
use App\Support\AcademicYear;
use Illuminate\Console\Command;

class SendMembershipRemindersCommand extends Command
{
    protected $signature = 'membership:send-reminders';

    protected $description = 'Send membership window and payment due reminders to schools';

    public function handle(MembershipNotifier $notifier): int
    {
        $year = AcademicYear::current();
        $today = now()->startOfDay();

        SahodayaRegistrationWindow::query()
            ->where('academic_year', $year)
            ->whereNotNull('registration_ends_at')
            ->get()
            ->each(function (SahodayaRegistrationWindow $window) use ($notifier, $year, $today) {
                $ends = $window->registration_ends_at?->startOfDay();
                if (! $ends) {
                    return;
                }

                $daysLeft = $today->diffInDays($ends, false);
                if (! in_array($daysLeft, [7, 0], true)) {
                    return;
                }

                $schoolIds = Tenant::where('parent_id', $window->sahodaya_id)
                    ->where('type', 'school')
                    ->where('membership_status', 'approved')
                    ->pluck('id');

                foreach ($schoolIds as $schoolId) {
                    $started = Registration::where('school_id', $schoolId)
                        ->where('academic_year', $year)
                        ->exists();

                    if (! $started) {
                        $school = Tenant::find($schoolId);
                        if ($school) {
                            $notifier->reminderWindowClosing($school, $year, (int) $daysLeft);
                        }
                    }
                }
            });

        Registration::query()
            ->where('academic_year', $year)
            ->where('registration_status', 'payment_pending')
            ->with('school')
            ->chunkById(50, function ($regs) use ($notifier) {
                foreach ($regs as $reg) {
                    if ($reg->school) {
                        $notifier->reminderPaymentDue($reg->school, $reg->academic_year, (float) ($reg->membership_fee_amount ?? 0));
                    }
                }
            });

        $this->info('Membership reminders sent.');

        return self::SUCCESS;
    }
}
