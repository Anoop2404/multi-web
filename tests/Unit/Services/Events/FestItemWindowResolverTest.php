<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestItemHead;
use App\Services\Events\FestItemRegistrationGate;
use App\Services\Events\FestItemWindowResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestItemWindowResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_event_ignores_head_registration_window_and_uses_event_dates(): void
    {
        $event = FestEvent::create([
            'tenant_id'           => (string) Str::uuid(),
            'title'               => 'GK Quiz',
            'event_type'          => 'custom',
            'level_round'         => 'sahodaya',
            'status'              => 'registration_open',
            'registration_open'   => now()->subDay()->toDateString(),
            'registration_close'  => now()->addWeek()->toDateString(),
        ]);

        $head = FestItemHead::create([
            'tenant_id'  => $event->tenant_id,
            'event_id'   => $event->id,
            'event_type' => 'custom',
            'name'       => 'Quiz',
            'slug'       => 'quiz',
            'sort_order' => 1,
            'reg_start'  => now()->subMonth()->toDateString(),
            'reg_end'    => now()->subWeek()->toDateString(),
        ]);

        $item = FestEventItem::create([
            'event_id'         => $event->id,
            'head_id'          => $head->id,
            'title'            => 'Team round',
            'participant_type' => 'team',
            'is_enabled'       => true,
        ]);

        $item->setRelation('event', $event);
        $item->setRelation('head', $head);

        $resolver = app(FestItemWindowResolver::class);

        $this->assertTrue($resolver->isRegistrationOpen($item));
        $this->assertTrue(app(FestItemRegistrationGate::class)->isOpen($item));
    }

    public function test_kalolsavam_still_inherits_head_registration_window(): void
    {
        $event = FestEvent::create([
            'tenant_id'   => (string) Str::uuid(),
            'title'       => 'Kalotsav',
            'event_type'  => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status'      => 'registration_open',
        ]);

        $head = FestItemHead::create([
            'tenant_id'  => $event->tenant_id,
            'event_id'   => $event->id,
            'event_type' => 'kalolsavam',
            'name'       => 'Stage',
            'slug'       => 'stage',
            'sort_order' => 1,
            'reg_start'  => now()->subMonth()->toDateString(),
            'reg_end'    => now()->subWeek()->toDateString(),
        ]);

        $item = FestEventItem::create([
            'event_id'         => $event->id,
            'head_id'          => $head->id,
            'title'            => 'Mono Act',
            'participant_type' => 'individual',
            'is_enabled'       => true,
        ]);

        $item->setRelation('event', $event);
        $item->setRelation('head', $head);

        $this->assertFalse(app(FestItemWindowResolver::class)->isRegistrationOpen($item));
    }
}
