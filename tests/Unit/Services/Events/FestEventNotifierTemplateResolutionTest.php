<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestEvent;
use App\Models\NotificationTemplate;
use App\Services\Events\FestEventNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FestEventNotifierTemplateResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('notification_templates')) {
            $this->markTestSkipped('notification_templates not migrated.');
        }
    }

    public function test_falls_back_to_generic_fest_slug(): void
    {
        NotificationTemplate::updateOrCreate(
            ['slug' => 'fest.registration.open'],
            [
                'title' => 'Open',
                'body_template' => 'Open {{event_title}}',
                'is_active' => true,
                'channels_json' => ['in_app'],
            ]
        );

        $event = new FestEvent(['event_type' => 'custom', 'title' => 'Robotics']);
        $slug = app(FestEventNotifier::class)->resolveTemplateSlug($event, 'fest.registration.open');

        $this->assertSame('fest.registration.open', $slug);
    }

    public function test_prefers_type_specific_override(): void
    {
        NotificationTemplate::updateOrCreate(
            ['slug' => 'fest.registration.open'],
            [
                'title' => 'Open',
                'body_template' => 'Open {{event_title}}',
                'is_active' => true,
                'channels_json' => ['in_app'],
            ]
        );
        NotificationTemplate::updateOrCreate(
            ['slug' => 'fest.robotics.registration.open'],
            [
                'title' => 'Robotics open',
                'body_template' => 'Robotics {{event_title}}',
                'is_active' => true,
                'channels_json' => ['in_app'],
            ]
        );

        $event = new FestEvent(['event_type' => 'robotics', 'title' => 'Bot Cup']);
        $slug = app(FestEventNotifier::class)->resolveTemplateSlug($event, 'fest.registration.open');

        $this->assertSame('fest.robotics.registration.open', $slug);
    }
}
