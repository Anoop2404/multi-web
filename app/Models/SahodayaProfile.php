<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCentralTenant;
use App\Models\MembershipFeeSlab;
use App\Support\AcademicYear;
use Illuminate\Database\Eloquent\Model;

class SahodayaProfile extends Model
{
    use BelongsToCentralTenant;

    protected $fillable = [
        'tenant_id', 'slug', 'prefix', 'cbse_region', 'address',
        'contact_email', 'contact_phone',
        'student_data_mode', 'membership_fee_type', 'fixed_membership_fee_amount',
        'allow_non_affiliated_schools', 'non_affiliated_membership_fee_type', 'non_affiliated_fixed_membership_fee_amount',
        'teacher_registration_enabled', 'auto_approve_submissions', 'student_edit_lock_enabled', 'student_edit_lock_at',
        'require_student_verification', 'payment_instructions', 'prefixes_locked',
        'setup_wizard_complete', 'setup_wizard_dismissed_at',
        'payment_bank_name', 'payment_account_no', 'payment_ifsc', 'payment_upi',
        'application_form_config', 'active_academic_year', 'fest_class_group_scheme', 'nav_visibility',
        'sports_age_cutoff_date',
        'receipt_template_json', 'receipt_next_number',
        'mail_host', 'mail_port', 'mail_encryption', 'mail_username', 'mail_password',
        'mail_from_address', 'mail_from_name', 'mail_transport', 'zeptomail_region',
    ];

    protected $hidden = [
        'mail_password',
    ];

    protected $casts = [
        'fixed_membership_fee_amount'  => 'decimal:2',
        'allow_non_affiliated_schools' => 'boolean',
        'non_affiliated_fixed_membership_fee_amount' => 'decimal:2',
        'teacher_registration_enabled' => 'boolean',
        'auto_approve_submissions'     => 'boolean',
        'student_edit_lock_enabled'    => 'boolean',
        'student_edit_lock_at'         => 'datetime',
        'require_student_verification' => 'boolean',
        'prefixes_locked'              => 'boolean',
        'setup_wizard_complete'        => 'boolean',
        'setup_wizard_dismissed_at'    => 'datetime',
        'application_form_config'      => 'array',
        'nav_visibility'               => 'array',
        'receipt_template_json'        => 'array',
        'sports_age_cutoff_date'       => 'date:Y-m-d',
        'mail_password'                => 'encrypted',
        'mail_port'                    => 'integer',
    ];

    public function resolvedAcademicYear(): string
    {
        return $this->active_academic_year ?: AcademicYear::calendarCurrent();
    }

    public function tenant() { return $this->belongsToCentralTenant(); }

    public function feeSlabs() {
        return $this->hasMany(MembershipFeeSlab::class, 'sahodaya_id', 'tenant_id');
    }

    public function registrationWindows() {
        return $this->hasMany(SahodayaRegistrationWindow::class, 'sahodaya_id', 'tenant_id');
    }

    public function requiresStudentData(): bool
    {
        return $this->student_data_mode !== 'not_required';
    }

    public function requiresDataSubmission(): bool
    {
        return $this->requiresStudentData() || $this->teacher_registration_enabled;
    }

    public function requiresMembershipPayment(): bool
    {
        return match ($this->membership_fee_type) {
            'none' => false,
            'fixed' => (float) ($this->fixed_membership_fee_amount ?? 0) > 0,
            'variable_by_student_count' => true,
            default => false,
        };
    }

    public function requiresMembershipPaymentForSchool(?Tenant $school = null): bool
    {
        if ($school?->is_non_affiliated && $this->allow_non_affiliated_schools) {
            return match ($this->non_affiliated_membership_fee_type ?? 'fixed') {
                'none' => false,
                default => (float) ($this->non_affiliated_fixed_membership_fee_amount ?? 0) > 0,
            };
        }

        return $this->requiresMembershipPayment();
    }

    /** Whether Sahodaya has explicitly configured the membership fee model for the active year. */
    public function membershipFeeConfigured(?string $academicYear = null): bool
    {
        return match ($this->membership_fee_type) {
            'none' => true,
            'fixed' => $this->fixed_membership_fee_amount !== null
                && (float) $this->fixed_membership_fee_amount > 0,
            'variable_by_student_count' => MembershipFeeSlab::query()
                ->where('sahodaya_id', $this->tenant_id)
                ->where('academic_year', $academicYear ?? $this->resolvedAcademicYear())
                ->exists(),
            default => false,
        };
    }

    public function mailIsConfigured(): bool
    {
        if (! filled($this->mail_password)) {
            return false;
        }

        if ($this->usesZeptoMailApi()) {
            return filled($this->mail_from_address);
        }

        return filled($this->mail_username);
    }

    public function usesZeptoMailApi(): bool
    {
        return ($this->mail_transport ?? 'zeptomail_api') === 'zeptomail_api';
    }

    /** Formatted payment details for schools (payment step). */
    public function paymentDetailsText(): string
    {
        $lines = array_filter([
            $this->payment_bank_name ? "Bank: {$this->payment_bank_name}" : null,
            $this->payment_account_no ? "Account: {$this->payment_account_no}" : null,
            $this->payment_ifsc ? "IFSC: {$this->payment_ifsc}" : null,
            $this->payment_upi ? "UPI: {$this->payment_upi}" : null,
            $this->payment_instructions ?: null,
        ]);

        return implode("\n", $lines);
    }
}
