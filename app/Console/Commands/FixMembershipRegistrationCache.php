<?php

namespace App\Console\Commands;

use App\Models\MembershipPayment;
use App\Models\Registration;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * One-off repair for rows created by the pre-6a59241a MemberSchoolsController::uploadPaymentProof()
 * bug: it could create a 'verified' MembershipPayment without linking registration_id or syncing
 * Registration.amount_paid/registration_status, leaving the school stuck showing as unpaid on the
 * Membership Fees tabs despite having a verified payment on file.
 */
class FixMembershipRegistrationCache extends Command
{
    protected $signature = 'membership:fix-registration-cache
        {reg_no : The Registration.reg_no to repair, e.g. MALCS/27/13}
        {--sahodaya= : Sahodaya UUID or subdomain that owns this registration}
        {--dry-run : Show what would change without saving}';

    protected $description = 'Re-link a verified MembershipPayment to its Registration and resync amount_paid/registration_status';

    public function handle(): int
    {
        $regNo = $this->argument('reg_no');
        $sahodayaOption = $this->option('sahodaya');

        $sahodaya = Tenant::query()
            ->where('type', 'sahodaya')
            ->when($sahodayaOption, fn ($q) => $q->where(fn ($inner) => $inner->where('id', $sahodayaOption)->orWhere('subdomain', $sahodayaOption)))
            ->first();

        if (! $sahodaya) {
            $this->error($sahodayaOption
                ? "No Sahodaya tenant matches '{$sahodayaOption}'."
                : 'Pass --sahodaya=<uuid-or-subdomain> to select which Sahodaya database to look in.');

            return self::FAILURE;
        }

        $result = $sahodaya->run(function () use ($regNo) {
            $registration = Registration::where('reg_no', $regNo)->first();
            if (! $registration) {
                return ['error' => "No registration found with reg_no '{$regNo}' in this Sahodaya's database."];
            }

            $unlinked = MembershipPayment::where('school_id', $registration->school_id)
                ->where('academic_year', $registration->academic_year)
                ->where('status', 'verified')
                ->whereNull('registration_id')
                ->get();

            $verifiedTotal = MembershipPayment::where('school_id', $registration->school_id)
                ->where('academic_year', $registration->academic_year)
                ->where('status', 'verified')
                ->where(fn ($q) => $q->where('registration_id', $registration->id)->orWhereNull('registration_id'))
                ->sum('amount');

            $newStatus = ($registration->membership_fee_amount !== null && (float) $verifiedTotal < (float) $registration->membership_fee_amount)
                ? 'payment_pending'
                : 'completed';

            return [
                'registration'    => $registration,
                'unlinked_ids'    => $unlinked->pluck('id')->all(),
                'before'          => ['amount_paid' => (float) $registration->amount_paid, 'registration_status' => $registration->registration_status],
                'after'           => ['amount_paid' => (float) $verifiedTotal, 'registration_status' => $newStatus],
            ];
        });

        if (isset($result['error'])) {
            $this->error($result['error']);

            return self::FAILURE;
        }

        $this->line("Registration {$regNo} (school {$result['registration']->school_id}):");
        $this->line('  Unlinked verified payments to attach: '.(count($result['unlinked_ids']) ?: 'none'));
        $this->line("  amount_paid: {$result['before']['amount_paid']} -> {$result['after']['amount_paid']}");
        $this->line("  registration_status: {$result['before']['registration_status']} -> {$result['after']['registration_status']}");

        if ($this->option('dry-run')) {
            $this->comment('Dry run — nothing was saved.');

            return self::SUCCESS;
        }

        if ($result['before'] === $result['after'] && $result['unlinked_ids'] === []) {
            $this->info('Already in sync — nothing to do.');

            return self::SUCCESS;
        }

        if (! $this->option('no-interaction') && ! $this->confirm('Apply this change?', true)) {
            $this->comment('Aborted — nothing was saved.');

            return self::SUCCESS;
        }

        $sahodaya->run(function () use ($regNo, $result) {
            $registration = Registration::where('reg_no', $regNo)->firstOrFail();

            MembershipPayment::where('school_id', $registration->school_id)
                ->where('academic_year', $registration->academic_year)
                ->where('status', 'verified')
                ->whereNull('registration_id')
                ->update(['registration_id' => $registration->id]);

            $registration->update([
                'amount_paid'         => $result['after']['amount_paid'],
                'registration_status' => $result['after']['registration_status'],
            ]);
        });

        $this->info('Saved.');

        return self::SUCCESS;
    }
}
