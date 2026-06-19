<?php

namespace App\Models;

use App\Support\AcademicYear;
use Illuminate\Database\Eloquent\Model;

class SahodayaProfile extends Model
{
    protected $fillable = [
        'tenant_id', 'slug', 'prefix', 'cbse_region', 'address',
        'contact_email', 'contact_phone',
        'student_data_mode', 'membership_fee_type', 'fixed_membership_fee_amount',
        'teacher_registration_enabled', 'payment_instructions', 'prefixes_locked',
        'payment_bank_name', 'payment_account_no', 'payment_ifsc', 'payment_upi',
        'application_form_config', 'active_academic_year',
        'mail_host', 'mail_port', 'mail_encryption', 'mail_username', 'mail_password',
        'mail_from_address', 'mail_from_name',
    ];

    protected $hidden = [
        'mail_password',
    ];

    protected $casts = [
        'fixed_membership_fee_amount'  => 'decimal:2',
        'teacher_registration_enabled' => 'boolean',
        'prefixes_locked'              => 'boolean',
        'application_form_config'      => 'array',
        'mail_password'                => 'encrypted',
        'mail_port'                    => 'integer',
    ];

    public function resolvedAcademicYear(): string
    {
        return $this->active_academic_year ?: AcademicYear::calendarCurrent();
    }

    public function tenant() { return $this->belongsTo(Tenant::class); }

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

    public function mailIsConfigured(): bool
    {
        return filled($this->mail_username) && filled($this->mail_password);
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
