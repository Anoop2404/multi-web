<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestFoodBill;
use App\Models\FestFoodOrderItem;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Regression tests for the day x meal-type x item food order report — previously there
 * was no aggregated view of ordered items across schools anywhere (only per-school bill
 * totals). See FestFoodOrderItem::dayMealReport().
 */
class FestFoodBillingReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeSahodayaAndSchools(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Report Sahodaya',
            'domain' => 'food-report-'.Str::random(8).'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'FR', 'student_data_mode' => 'counts_only']);

        $schoolA = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'School A', 'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true]);
        $schoolB = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'School B', 'parent_id' => $sahodaya->id, 'membership_status' => 'approved', 'is_active' => true]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $schoolBAdmin = User::factory()->create(['tenant_id' => $schoolB->id, 'email_verified_at' => now()]);
        $schoolBAdmin->assignRole('school_admin');

        return compact('sahodaya', 'schoolA', 'schoolB', 'admin', 'schoolBAdmin');
    }

    private function makeBill(string $tenantId, string $eventId, string $schoolId, array $items, string $status = FestFoodBill::STATUS_OPEN, string $payeeType = 'sahodaya', ?string $hostSchoolId = null): FestFoodBill
    {
        $bill = FestFoodBill::create([
            'tenant_id' => $tenantId, 'event_id' => $eventId, 'school_id' => $schoolId,
            'status' => $status, 'payment_mode' => 'prepaid',
            'payee_type' => $payeeType, 'host_school_id' => $hostSchoolId,
            'amount_total' => 0, 'amount_paid' => 0,
        ]);

        foreach ($items as $item) {
            FestFoodOrderItem::create([
                'bill_id' => $bill->id,
                'menu_date' => $item['date'],
                'meal_type' => $item['meal'],
                'item_name' => $item['name'],
                'unit_price' => $item['price'],
                'quantity' => $item['qty'],
                'line_total' => round($item['price'] * $item['qty'], 2),
            ]);
        }

        return $bill;
    }

    public function test_day_meal_report_aggregates_across_schools_grouped_and_ordered_correctly(): void
    {
        ['sahodaya' => $sahodaya, 'schoolA' => $schoolA, 'schoolB' => $schoolB] = $this->makeSahodayaAndSchools();

        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Report Event', 'event_type' => 'kalolsavam', 'level_round' => 'sahodaya', 'status' => 'ongoing']);

        // Two schools both order lunch on day 1; only school A orders breakfast and dinner.
        // Deliberately out of chronological order to prove sorting, not just grouping.
        $this->makeBill($sahodaya->id, $event->id, $schoolA->id, [
            ['date' => '2026-09-01', 'meal' => 'dinner', 'name' => 'Chapati', 'price' => 15, 'qty' => 10],
            ['date' => '2026-09-01', 'meal' => 'lunch', 'name' => 'Meals', 'price' => 50, 'qty' => 20],
            ['date' => '2026-09-01', 'meal' => 'breakfast', 'name' => 'Idli', 'price' => 10, 'qty' => 30],
        ]);
        $this->makeBill($sahodaya->id, $event->id, $schoolB->id, [
            ['date' => '2026-09-01', 'meal' => 'lunch', 'name' => 'Meals', 'price' => 50, 'qty' => 15],
        ]);

        $report = FestFoodOrderItem::dayMealReport($event->id);

        $this->assertCount(1, $report);
        $day = $report[0];
        $this->assertSame('2026-09-01', $day['date']);
        $this->assertSame(75, $day['day_total_quantity']); // 10 + 20 + 15 + 30
        // Chapati 15*10=150, Meals (A) 50*20=1000, Idli 10*30=300, Meals (B) 50*15=750 -> 2200
        $this->assertEqualsWithDelta(2200.00, $day['day_total_revenue'], 0.001);

        // Meals must appear in canonical chronological order, not insertion order.
        $this->assertSame(['breakfast', 'lunch', 'dinner'], array_column($day['meals'], 'meal_type'));

        $lunch = collect($day['meals'])->firstWhere('meal_type', 'lunch');
        $this->assertSame(35, $lunch['subtotal_quantity']); // 20 + 15 across both schools
        $this->assertEqualsWithDelta(1750.00, $lunch['subtotal_revenue'], 0.001);
        $this->assertSame(1, count($lunch['items'])); // both schools ordered the same item name -> one aggregated row
        $this->assertSame('Meals', $lunch['items'][0]['item_name']);
        $this->assertSame(35, $lunch['items'][0]['quantity']);
        $this->assertSame(2, $lunch['items'][0]['schools_count']);
    }

    public function test_day_meal_report_excludes_cancelled_bills(): void
    {
        ['sahodaya' => $sahodaya, 'schoolA' => $schoolA] = $this->makeSahodayaAndSchools();
        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Report Event', 'event_type' => 'kalolsavam', 'level_round' => 'sahodaya', 'status' => 'ongoing']);

        $this->makeBill($sahodaya->id, $event->id, $schoolA->id, [
            ['date' => '2026-09-01', 'meal' => 'lunch', 'name' => 'Meals', 'price' => 50, 'qty' => 20],
        ], status: FestFoodBill::STATUS_CANCELLED);

        $this->assertSame([], FestFoodOrderItem::dayMealReport($event->id));
    }

    public function test_day_meal_report_scoped_to_host_school_excludes_other_payees(): void
    {
        ['sahodaya' => $sahodaya, 'schoolA' => $schoolA, 'schoolB' => $schoolB] = $this->makeSahodayaAndSchools();
        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Report Event', 'event_type' => 'kalolsavam', 'level_round' => 'sahodaya', 'status' => 'ongoing']);

        // School A's bill is payable to the Sahodaya (not this host) — must be excluded.
        $this->makeBill($sahodaya->id, $event->id, $schoolA->id, [
            ['date' => '2026-09-01', 'meal' => 'lunch', 'name' => 'Meals', 'price' => 50, 'qty' => 20],
        ], payeeType: 'sahodaya');

        // School B's bill is payable to School B itself (the host) — must be included.
        $this->makeBill($sahodaya->id, $event->id, $schoolB->id, [
            ['date' => '2026-09-01', 'meal' => 'lunch', 'name' => 'Meals', 'price' => 50, 'qty' => 5],
        ], payeeType: 'host_school', hostSchoolId: $schoolB->id);

        $report = FestFoodOrderItem::dayMealReport($event->id, $schoolB->id);

        $this->assertCount(1, $report);
        $this->assertSame(5, $report[0]['day_total_quantity']);
    }

    public function test_sahodaya_report_endpoint_returns_expected_shape(): void
    {
        ['sahodaya' => $sahodaya, 'schoolA' => $schoolA, 'admin' => $admin] = $this->makeSahodayaAndSchools();
        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Report Event', 'event_type' => 'kalolsavam', 'level_round' => 'sahodaya', 'status' => 'ongoing']);
        $this->makeBill($sahodaya->id, $event->id, $schoolA->id, [
            ['date' => '2026-09-01', 'meal' => 'lunch', 'name' => 'Meals', 'price' => 50, 'qty' => 20],
        ]);

        $this->actingAs($admin)
            ->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/food-billing/report")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sahodaya/Events/FoodBillingReport', false)
                ->where('report.0.date', '2026-09-01')
                ->where('report.0.meals.0.items.0.item_name', 'Meals')
                ->where('report.0.meals.0.items.0.quantity', 20));
    }

    public function test_sahodaya_report_csv_export_downloads(): void
    {
        ['sahodaya' => $sahodaya, 'schoolA' => $schoolA, 'admin' => $admin] = $this->makeSahodayaAndSchools();
        $event = FestEvent::create(['tenant_id' => $sahodaya->id, 'title' => 'Report Event', 'event_type' => 'kalolsavam', 'level_round' => 'sahodaya', 'status' => 'ongoing']);
        $this->makeBill($sahodaya->id, $event->id, $schoolA->id, [
            ['date' => '2026-09-01', 'meal' => 'lunch', 'name' => 'Meals', 'price' => 50, 'qty' => 20],
        ]);

        $response = $this->actingAs($admin)->get("/sahodaya-admin/{$sahodaya->id}/events/{$event->id}/food-billing/report/export");

        $response->assertOk();
    }

    public function test_school_host_report_endpoint_is_scoped_to_host(): void
    {
        ['sahodaya' => $sahodaya, 'schoolA' => $schoolA, 'schoolB' => $schoolB, 'schoolBAdmin' => $schoolBAdmin] = $this->makeSahodayaAndSchools();
        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Report Event', 'event_type' => 'kalolsavam', 'level_round' => 'sahodaya', 'status' => 'ongoing',
            'food_payee_type' => 'host_school', 'food_host_school_id' => $schoolB->id,
        ]);

        $this->makeBill($sahodaya->id, $event->id, $schoolA->id, [
            ['date' => '2026-09-01', 'meal' => 'lunch', 'name' => 'Meals', 'price' => 50, 'qty' => 20],
        ], payeeType: 'host_school', hostSchoolId: $schoolB->id);

        $this->actingAs($schoolBAdmin)
            ->get("/school-admin/{$schoolB->id}/fest/{$event->id}/food-host-billing/report")
            ->assertInertia(fn (Assert $page) => $page
                ->component('School/Fest/FoodHostBillingReport', false)
                ->where('report.0.meals.0.items.0.quantity', 20));
    }
}
