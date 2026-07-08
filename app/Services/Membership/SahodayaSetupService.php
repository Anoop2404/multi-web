<?php

namespace App\Services\Membership;

use App\Models\MasterClass;
use App\Models\MembershipFeeSlab;
use App\Models\SahodayaProfile;
use App\Models\SahodayaRegistrationWindow;
use App\Models\Tenant;
use App\Support\AcademicYear;

class SahodayaSetupService
{
    /** @return list<array{key: string, label: string, tab: string, tabLabel: string, done: bool, href: string}> */
    public function checklist(Tenant $sahodaya): array
    {
        $profile = SahodayaProfile::where('tenant_id', $sahodaya->id)->first();
        $year = AcademicYear::forSahodaya($sahodaya->id);
        $window = $profile
            ? SahodayaRegistrationWindow::where('sahodaya_id', $sahodaya->id)->where('academic_year', $year)->first()
            : null;

        $base = "/sahodaya-admin/{$sahodaya->id}";
        $settings = "{$base}/membership/settings";

        $feeOk = $profile && $profile->membershipFeeConfigured($year);
        $windowOk = $window && $window->registration_starts_at && $window->registration_ends_at;
        $hasPayment = $profile && (
            filled($profile->payment_bank_name)
            || filled($profile->payment_account_no)
            || filled($profile->payment_upi)
        );
        $hasClasses = MasterClass::where('sahodaya_id', $sahodaya->id)->where('is_active', true)->exists();

        return [
            [
                'key'      => 'prefix',
                'label'    => 'Set Sahodaya registration prefix',
                'tab'      => 'profile',
                'tabLabel' => 'Profile & Rules',
                'done'     => filled($profile?->prefix),
                'href'     => "{$settings}?tab=profile",
            ],
            [
                'key'      => 'academic_year',
                'label'    => 'Activate an academic year',
                'tab'      => 'profile',
                'tabLabel' => 'Profile & Rules',
                'done'     => (bool) AcademicYear::activeRecord(),
                'href'     => "{$base}/academic-years",
            ],
            [
                'key'      => 'fee',
                'label'    => 'Configure membership fees',
                'tab'      => 'fees',
                'tabLabel' => 'Membership Fees',
                'done'     => $feeOk,
                'href'     => "{$settings}?tab=fees",
            ],
            [
                'key'      => 'window',
                'label'    => 'Set registration window dates',
                'tab'      => 'window',
                'tabLabel' => 'Registration Window',
                'done'     => $windowOk,
                'href'     => "{$settings}?tab=window",
            ],
            [
                'key'      => 'payment',
                'label'    => 'Add bank / UPI payment details',
                'tab'      => 'payment',
                'tabLabel' => 'Payment Details',
                'done'     => $hasPayment,
                'href'     => "{$settings}?tab=payment",
            ],
            [
                'key'      => 'classes',
                'label'    => 'Add classes to Class Master',
                'tab'      => 'categories',
                'tabLabel' => 'Class Master',
                'done'     => $hasClasses,
                'href'     => "{$settings}?tab=categories",
            ],
            [
                'key'      => 'mail',
                'label'    => 'Configure ZeptoMail notifications',
                'tab'      => 'email',
                'tabLabel' => 'ZeptoMail API',
                'done'     => (bool) $profile?->mailIsConfigured(),
                'href'     => "{$settings}?tab=email",
            ],
        ];
    }

    public function isComplete(Tenant $sahodaya): bool
    {
        $profile = SahodayaProfile::where('tenant_id', $sahodaya->id)->first();
        if ($profile?->setup_wizard_complete) {
            return true;
        }

        return collect($this->checklist($sahodaya))->every(fn (array $item) => $item['done']);
    }

    public function completedCount(Tenant $sahodaya): int
    {
        return collect($this->checklist($sahodaya))->where('done', true)->count();
    }

    public function markComplete(Tenant $sahodaya): void
    {
        SahodayaProfile::updateOrCreate(
            ['tenant_id' => $sahodaya->id],
            ['setup_wizard_complete' => true],
        );
    }

    public function dismiss(Tenant $sahodaya): void
    {
        SahodayaProfile::updateOrCreate(
            ['tenant_id' => $sahodaya->id],
            ['setup_wizard_dismissed_at' => now()],
        );
    }

    public function shouldPromptWizard(Tenant $sahodaya): bool
    {
        $profile = SahodayaProfile::where('tenant_id', $sahodaya->id)->first();

        if ($profile?->setup_wizard_complete || $profile?->setup_wizard_dismissed_at) {
            return false;
        }

        return ! $this->isComplete($sahodaya);
    }
};
