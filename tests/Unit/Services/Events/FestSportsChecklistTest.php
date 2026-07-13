<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestEvent;
use App\Models\FestItemHead;
use App\Models\Tenant;
use App\Services\Events\FestSportsChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestSportsChecklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_heads_step_requires_composite_fees_not_just_count(): void
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Sahodaya',
            'type' => 'sahodaya',
            'is_active' => true,
        ]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id,
            'title' => 'Sports Meet',
            'event_type' => 'sports',
            'status' => 'draft',
            'fee_settings' => ['fee_model' => 'sports_composite'],
        ]);

        FestItemHead::create([
            'tenant_id' => $sahodaya->id,
            'event_id' => $event->id,
            'event_type' => 'sports',
            'name' => 'Athletics',
            'slug' => 'athletics',
            'sort_order' => 1,
            'status' => 'draft',
        ]);

        $checklist = app(FestSportsChecklist::class)->forEvent($event);
        $heads = collect($checklist)->firstWhere('key', 'heads');

        $this->assertNotNull($heads);
        $this->assertFalse($heads['done'], 'Head without fees must not count as configured');

        FestItemHead::where('event_id', $event->id)->update([
            'school_registration_fee' => 500,
            'student_registration_fee' => 100,
        ]);

        $checklist = app(FestSportsChecklist::class)->forEvent($event->fresh());
        $heads = collect($checklist)->firstWhere('key', 'heads');
        $this->assertTrue($heads['done']);

        $this->assertNull(collect($checklist)->firstWhere('key', 'state_remittance'));
    }
}
