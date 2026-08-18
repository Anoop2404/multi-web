<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\PlatformAnnouncement;
use App\Models\PlatformUser;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformAnnouncementControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingSuperadmin(): PlatformUser
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $superadmin = PlatformUser::query()->create([
            'name' => 'Announcement Super',
            'email' => 'announcement-super@example.com',
            'username' => 'announcement_super',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $superadmin->assignRole('superadmin');

        return $superadmin;
    }

    public function test_superadmin_can_create_an_announcement(): void
    {
        $superadmin = $this->actingSuperadmin();

        $this->actingAs($superadmin, 'platform')
            ->post('http://superadmin.test/admin/announcements', [
                'title' => 'Scheduled maintenance',
                'body' => 'The platform will be briefly unavailable tonight.',
                'type' => 'maintenance',
                'audience' => 'all',
            ])
            ->assertRedirect();

        $announcement = PlatformAnnouncement::where('title', 'Scheduled maintenance')->firstOrFail();
        $this->assertSame('maintenance', $announcement->type);
        $this->assertSame('all', $announcement->audience);
        $this->assertTrue($announcement->is_active);
        $this->assertSame($superadmin->id, $announcement->created_by_user_id);

        $log = AuditLog::where('action', 'announcement.created')->first();
        $this->assertNotNull($log);
    }

    public function test_announcement_requires_a_valid_type(): void
    {
        $superadmin = $this->actingSuperadmin();

        $this->actingAs($superadmin, 'platform')
            ->post('http://superadmin.test/admin/announcements', [
                'title' => 'Bad type',
                'body' => 'Body',
                'type' => 'not-a-real-type',
                'audience' => 'all',
            ])
            ->assertSessionHasErrors('type');
    }

    public function test_end_date_cannot_precede_start_date(): void
    {
        $superadmin = $this->actingSuperadmin();

        $this->actingAs($superadmin, 'platform')
            ->post('http://superadmin.test/admin/announcements', [
                'title' => 'Bad window',
                'body' => 'Body',
                'type' => 'info',
                'audience' => 'all',
                'starts_at' => now()->addDay()->toDateTimeString(),
                'ends_at' => now()->toDateTimeString(),
            ])
            ->assertSessionHasErrors('ends_at');
    }

    public function test_superadmin_can_update_an_announcement(): void
    {
        $superadmin = $this->actingSuperadmin();
        $announcement = PlatformAnnouncement::create([
            'title' => 'Original', 'body' => 'Body', 'type' => 'info', 'audience' => 'all', 'is_active' => true,
        ]);

        $this->actingAs($superadmin, 'platform')
            ->put("http://superadmin.test/admin/announcements/{$announcement->id}", [
                'title' => 'Updated title',
                'body' => 'Body',
                'type' => 'critical',
                'audience' => 'superadmin',
                'is_active' => false,
            ])
            ->assertRedirect();

        $announcement->refresh();
        $this->assertSame('Updated title', $announcement->title);
        $this->assertSame('critical', $announcement->type);
        $this->assertFalse($announcement->is_active);
    }

    public function test_superadmin_can_delete_an_announcement(): void
    {
        $superadmin = $this->actingSuperadmin();
        $announcement = PlatformAnnouncement::create([
            'title' => 'To delete', 'body' => 'Body', 'type' => 'info', 'audience' => 'all', 'is_active' => true,
        ]);

        $this->actingAs($superadmin, 'platform')
            ->delete("http://superadmin.test/admin/announcements/{$announcement->id}")
            ->assertRedirect();

        $this->assertNull(PlatformAnnouncement::find($announcement->id));
    }

    public function test_index_lists_announcements_with_creator_name(): void
    {
        $superadmin = $this->actingSuperadmin();
        PlatformAnnouncement::create([
            'title' => 'Listed', 'body' => 'Body', 'type' => 'info', 'audience' => 'all',
            'is_active' => true, 'created_by_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin, 'platform')
            ->get('http://superadmin.test/admin/announcements')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Announcements/Index', false)
                ->where('announcements.0.title', 'Listed')
                ->where('announcements.0.created_by.name', 'Announcement Super')
            );
    }

    public function test_active_announcement_reaches_the_shared_inertia_prop(): void
    {
        $superadmin = $this->actingSuperadmin();
        PlatformAnnouncement::create([
            'title' => 'Visible now', 'body' => 'Body', 'type' => 'info', 'audience' => 'all', 'is_active' => true,
        ]);
        PlatformAnnouncement::create([
            'title' => 'Disabled', 'body' => 'Body', 'type' => 'info', 'audience' => 'all', 'is_active' => false,
        ]);
        PlatformAnnouncement::create([
            'title' => 'Wrong audience', 'body' => 'Body', 'type' => 'info', 'audience' => 'school_admin', 'is_active' => true,
        ]);

        $this->actingAs($superadmin, 'platform')
            ->get('http://superadmin.test/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('announcements', fn ($announcements) => collect($announcements)->pluck('title')->all() === ['Visible now'])
            );
    }
}
