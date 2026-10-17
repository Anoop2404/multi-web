<?php

namespace Tests\Feature\ExamEngine;

use App\Models\ExamEngineBooking;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Super admin → Online exam bookings: bookings by day, per-slot load vs capacity and scale-out. */
class BookingsCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['exam_engine.capacity' => 1000, 'exam_engine.scale_out_threshold' => 600]);
    }

    private function sahodaya(string $name): Tenant
    {
        return Tenant::create(['id' => (string) Str::uuid(), 'name' => $name, 'type' => 'sahodaya', 'is_active' => true]);
    }

    private function book(Tenant $sahodaya, int $examId, string $start, string $end, int $count, string $status = 'booked'): ExamEngineBooking
    {
        $tz = config('app.timezone');

        return ExamEngineBooking::create([
            'tenant_id' => $sahodaya->id, 'exam_id' => $examId, 'title' => "Exam {$examId}",
            'live_starts_at' => CarbonImmutable::parse($start, $tz), 'live_ends_at' => CarbonImmutable::parse($end, $tz),
            'expected_count' => $count, 'entry_mode' => 'either', 'status' => $status,
        ]);
    }

    private function superadmin(): User
    {
        return tap(User::factory()->create(['tenant_id' => null, 'email_verified_at' => now()]), fn (User $u) => $u->assignRole('superadmin'));
    }

    public function test_bookings_are_grouped_by_day_with_slot_totals_and_flags(): void
    {
        $mlp = $this->sahodaya('Malappuram Sahodaya');
        $tsr = $this->sahodaya('Thrissur Sahodaya');

        // Day 1 morning: two overlapping exams → 1,100 live at once (over the 1,000 capacity).
        $this->book($mlp, 1, '2026-11-20 09:00', '2026-11-20 11:00', 500, 'armed');
        $this->book($tsr, 7, '2026-11-20 10:00', '2026-11-20 12:00', 600);
        // Day 1 afternoon: 700 → within capacity but above the 600 scale-out threshold.
        $this->book($mlp, 2, '2026-11-20 15:00', '2026-11-20 16:30', 700);
        // A cancelled booking never counts toward load.
        $this->book($tsr, 8, '2026-11-20 15:00', '2026-11-20 16:00', 5000, 'cancelled');
        // Day 2: small exam, fine.
        $this->book($tsr, 9, '2026-11-21 10:00', '2026-11-21 11:00', 100);
        // Outside the requested range.
        $this->book($tsr, 10, '2026-11-25 10:00', '2026-11-25 11:00', 100);

        $this->actingAs($this->superadmin())
            ->get('/admin/exam-engine/bookings?from=2026-11-20&days=2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ExamEngine/Bookings', false)
                ->where('capacity', 1000)
                ->where('scaleOutThreshold', 600)
                ->where('range.from', '2026-11-20')
                ->where('range.to', '2026-11-21')
                ->has('days', 2)
                ->where('days.0.date', '2026-11-20')
                ->has('days.0.bookings', 4)
                ->where('days.0.bookings.0.sahodaya', 'Malappuram Sahodaya')
                ->where('days.0.bookings.0.window', '9:00 AM – 11:00 AM')
                ->where('days.0.bookings.0.expected_count', 500)
                ->where('days.0.bookings.0.monitor_url', "/sahodaya-admin/{$mlp->id}/mcq-exams/1/live")
                ->has('days.0.slots', 2)
                ->where('days.0.slots.0.students', 1100)
                ->where('days.0.slots.0.overbooked', true)
                ->where('days.0.slots.0.needs_scale_out', true)
                ->where('days.0.slots.0.window', '9:00 AM – 12:00 PM')
                ->where('days.0.slots.1.students', 700)
                ->where('days.0.slots.1.overbooked', false)
                ->where('days.0.slots.1.needs_scale_out', true)
                ->where('days.0.peak', 1100)
                ->where('days.0.flagged', true)
                ->where('days.1.date', '2026-11-21')
                ->where('days.1.slots.0.students', 100)
                ->where('days.1.slots.0.overbooked', false)
                ->where('days.1.slots.0.needs_scale_out', false)
                ->where('days.1.flagged', false)
                ->where('totals.bookings', 5)
                ->where('totals.active', 4)
                ->where('totals.students', 1900)
                ->where('totals.overbooked_slots', 1)
                ->where('totals.scale_out_slots', 2));
    }

    public function test_only_super_admins_can_see_the_calendar(): void
    {
        $sahodaya = $this->sahodaya('Some Sahodaya');
        $admin = User::factory()->create(['tenant_id' => $sahodaya->id, 'email_verified_at' => now()]);
        $admin->assignRole('sahodaya_admin');

        $this->actingAs($admin)->get('/admin/exam-engine/bookings')->assertForbidden();
        $this->actingAs($this->superadmin())->get('/admin/exam-engine/bookings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('days', 0)->where('totals.overbooked_slots', 0));
    }
}
