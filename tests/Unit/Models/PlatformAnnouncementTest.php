<?php

namespace Tests\Unit\Models;

use App\Models\PlatformAnnouncement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private function make(array $overrides = []): PlatformAnnouncement
    {
        return PlatformAnnouncement::create(array_merge([
            'title' => 'Notice',
            'body' => 'Body text',
            'type' => 'info',
            'audience' => 'all',
            'is_active' => true,
        ], $overrides));
    }

    public function test_currently_active_excludes_disabled_announcements(): void
    {
        $this->make(['is_active' => false]);

        $this->assertSame(0, PlatformAnnouncement::currentlyActive()->count());
    }

    public function test_currently_active_excludes_a_future_window(): void
    {
        $this->make(['starts_at' => now()->addDay()]);

        $this->assertSame(0, PlatformAnnouncement::currentlyActive()->count());
    }

    public function test_currently_active_excludes_an_expired_window(): void
    {
        $this->make(['ends_at' => now()->subDay()]);

        $this->assertSame(0, PlatformAnnouncement::currentlyActive()->count());
    }

    public function test_currently_active_includes_an_open_ended_window(): void
    {
        $this->make(['starts_at' => null, 'ends_at' => null]);

        $this->assertSame(1, PlatformAnnouncement::currentlyActive()->count());
    }

    public function test_currently_active_includes_a_window_in_progress(): void
    {
        $this->make(['starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);

        $this->assertSame(1, PlatformAnnouncement::currentlyActive()->count());
    }

    public function test_for_audience_always_includes_all_audience_rows(): void
    {
        $this->make(['audience' => 'all']);

        $this->assertSame(1, PlatformAnnouncement::forAudience(['school_admin'])->count());
        $this->assertSame(1, PlatformAnnouncement::forAudience([])->count());
    }

    public function test_for_audience_excludes_rows_targeting_a_different_audience(): void
    {
        $this->make(['audience' => 'sahodaya_admin']);

        $this->assertSame(0, PlatformAnnouncement::forAudience(['school_admin'])->count());
        $this->assertSame(1, PlatformAnnouncement::forAudience(['sahodaya_admin'])->count());
    }
}
