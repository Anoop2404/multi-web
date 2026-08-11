<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P-02 (docs/STATE_KALOTSAV_MASTER_IMPLEMENTATION_PLAN.md §23) — the external Sahodaya/school
 * portal at /state/external/* originally treated the access_code as a bearer credential:
 * anyone with the URL could act as that coordinator, no session, no second factor. This adds
 * an email+OTP checkpoint on top of the access code (belt-and-braces, not a replacement —
 * the code stays the "which record" identifier, the OTP proves the person holding it is also
 * the registered coordinator).
 *
 * external_schools never had a contact_email column at all — schools were added by their
 * Sahodaya coordinator with only a name/phone. Added here as nullable so ExternalPortalOtpService
 * can fall back to access-code-only for any school added before this migration ran, rather than
 * locking out an org that never captured an email.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_sahodayas', function (Blueprint $table) {
            $table->string('otp_code_hash')->nullable()->after('access_code');
            $table->timestamp('otp_expires_at')->nullable()->after('otp_code_hash');
            $table->timestamp('otp_last_sent_at')->nullable()->after('otp_expires_at');
            $table->unsignedTinyInteger('otp_attempts')->default(0)->after('otp_last_sent_at');
        });

        Schema::table('external_schools', function (Blueprint $table) {
            $table->string('contact_email')->nullable()->after('contact_phone');
            $table->string('otp_code_hash')->nullable()->after('access_code');
            $table->timestamp('otp_expires_at')->nullable()->after('otp_code_hash');
            $table->timestamp('otp_last_sent_at')->nullable()->after('otp_expires_at');
            $table->unsignedTinyInteger('otp_attempts')->default(0)->after('otp_last_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('external_sahodayas', function (Blueprint $table) {
            $table->dropColumn(['otp_code_hash', 'otp_expires_at', 'otp_last_sent_at', 'otp_attempts']);
        });

        Schema::table('external_schools', function (Blueprint $table) {
            $table->dropColumn(['contact_email', 'otp_code_hash', 'otp_expires_at', 'otp_last_sent_at', 'otp_attempts']);
        });
    }
};
