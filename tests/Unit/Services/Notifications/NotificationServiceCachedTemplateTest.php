<?php

namespace Tests\Unit\Services\Notifications;

use App\Models\NotificationTemplate;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Regression coverage for a bug found 2026-08-30 while manually testing schedule
 * publishing: cachedTemplate() used to `Cache::remember()` the raw NotificationTemplate
 * model. config/cache.php sets `serializable_classes => false` (Laravel's own default —
 * "no PHP classes will be unserialized from your cache to prevent gadget chain attacks"),
 * which silently downgrades any cached object to __PHP_Incomplete_Class on every read
 * from a real serializing store (file/database/redis). That crashed every caller with a
 * TypeError, including SahodayaAdmin\FestScheduleController::publishSchedule() — which
 * had already saved schedule_published=true before the notification step blew up, so the
 * admin saw a 500 for an action that had, in fact, already succeeded.
 *
 * phpunit.xml runs the suite on CACHE_STORE=array, which never serializes anything and
 * so never reproduces this — these tests explicitly force the `file` driver to exercise
 * the real serialize/unserialize round trip.
 */
class NotificationServiceCachedTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        config(['cache.default' => 'file']);
        config(['erp.fcm_in_app_only' => true]);
    }

    public function test_notify_from_template_survives_a_cold_and_warm_file_cache_round_trip(): void
    {
        NotificationTemplate::create([
            'slug' => 'test.cache.roundtrip',
            'title' => 'Hello {{name}}',
            'body_template' => 'Body for {{name}}',
            'is_active' => true,
            'channels_json' => ['in_app'],
        ]);

        $user = User::factory()->create();

        Cache::forget('notif_template:test.cache.roundtrip');

        $service = app(NotificationService::class);

        // Cold: cachedTemplate() writes the cache entry for the first time.
        $first = $service->notifyFromTemplate($user, 'test.cache.roundtrip', ['name' => 'Asha']);
        $this->assertNotNull($first);
        $this->assertSame('Hello Asha', $first->title);
        $this->assertSame('Body for Asha', $first->body);

        // Warm: cachedTemplate() must now unserialize the cached value back into a real
        // NotificationTemplate, not __PHP_Incomplete_Class.
        $second = $service->notifyFromTemplate($user, 'test.cache.roundtrip', ['name' => 'Basil']);
        $this->assertNotNull($second);
        $this->assertSame('Hello Basil', $second->title);
        $this->assertSame('Body for Basil', $second->body);
    }

    public function test_cached_template_rehydrates_a_real_model_not_an_incomplete_class(): void
    {
        NotificationTemplate::create([
            'slug' => 'test.cache.rehydrate',
            'title' => 'Title',
            'body_template' => 'Body',
            'is_active' => true,
            'channels_json' => ['in_app', 'email'],
        ]);

        Cache::forget('notif_template:test.cache.rehydrate');

        $service = app(NotificationService::class);
        $method = new \ReflectionMethod($service, 'cachedTemplate');
        $method->setAccessible(true);

        $cold = $method->invoke($service, 'test.cache.rehydrate');
        $warm = $method->invoke($service, 'test.cache.rehydrate');

        foreach (['cold' => $cold, 'warm' => $warm] as $label => $template) {
            $this->assertInstanceOf(NotificationTemplate::class, $template, "{$label} lookup should be a real NotificationTemplate");
            $this->assertSame('Title', $template->title);
            $this->assertSame(['in_app', 'email'], $template->channels_json);
        }
    }
}
