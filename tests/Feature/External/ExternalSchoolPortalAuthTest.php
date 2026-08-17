<?php

namespace Tests\Feature\External;

use App\Models\ExternalSahodaya;
use App\Models\ExternalSchool;
use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\State\StateQualifierEntry;
use App\Models\User;
use App\Services\State\ExternalIntakeService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ExternalSchoolPortalAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('state:migrate');
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeSchool(): ExternalSchool
    {
        $program = FestStateProgram::create([
            'title'          => 'Test State Program',
            'event_type'     => 'kalolsavam',
            'conduct_levels' => ['sahodaya', 'state'],
            'status'         => 'published',
        ]);

        $sahodaya = app(ExternalIntakeService::class)->createSahodaya($program, ['name' => 'Test Outside Sahodaya']);

        return app(ExternalIntakeService::class)->addSchool($sahodaya, ['name' => 'Test Outside School']);
    }

    public function test_school_can_log_in_with_generated_username_and_password(): void
    {
        $school = $this->makeSchool();

        $response = $this->post('/state/external/school/login', [
            'username' => $school->username,
            'password' => $school->plain_password,
        ]);

        $response->assertRedirect(route('state.external.school.show'));
        $this->assertEquals($school->id, session('external_school_id'));
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $school = $this->makeSchool();

        $response = $this->post('/state/external/school/login', [
            'username' => $school->username,
            'password' => 'definitely-wrong',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertNull(session('external_school_id'));
    }

    public function test_portal_page_requires_a_session(): void
    {
        $this->get('/state/external/school/portal')
            ->assertRedirect(route('state.external.school.login'));
    }

    public function test_authenticated_school_can_view_the_portal(): void
    {
        $school = $this->makeSchool();

        $this->withSession(['external_school_id' => $school->id])
            ->get('/state/external/school/portal')
            ->assertOk();
    }

    public function test_disabled_school_is_bounced_back_to_login(): void
    {
        $school = $this->makeSchool();
        $school->update(['status' => 'disabled']);

        $this->withSession(['external_school_id' => $school->id])
            ->get('/state/external/school/portal')
            ->assertRedirect(route('state.external.school.login'));
    }

    public function test_legacy_access_code_link_still_establishes_a_session(): void
    {
        $school = $this->makeSchool();

        $response = $this->get("/state/external/school/{$school->access_code}");

        $response->assertRedirect(route('state.external.school.show'));
        $this->assertEquals($school->id, session('external_school_id'));
    }

    public function test_logout_clears_the_session(): void
    {
        $school = $this->makeSchool();

        $response = $this->withSession(['external_school_id' => $school->id])
            ->post('/state/external/school/logout');

        $response->assertRedirect(route('state.external.school.login'));
        $this->assertNull(session('external_school_id'));
    }

    public function test_authenticated_school_can_add_and_remove_a_qualifier_entry(): void
    {
        $school = $this->makeSchool();
        $item = FestStateProgramItem::create([
            'state_program_id' => $school->sahodaya->state_program_id,
            'title'            => 'Light Music',
            'item_code'        => 'LM01',
            'qualify_count'    => 2,
            'display_order'    => 1,
        ]);

        $add = $this->withSession(['external_school_id' => $school->id])
            ->post('/state/external/school/portal/entries', [
                'item_code'    => 'LM01',
                'student_name' => 'Test Student',
            ]);

        $add->assertRedirect()->assertSessionHas('success');
        $entry = StateQualifierEntry::where('school_id', $school->id)->where('student_name', 'Test Student')->sole();

        $remove = $this->withSession(['external_school_id' => $school->id])
            ->delete("/state/external/school/portal/entries/{$entry->id}");

        $remove->assertRedirect()->assertSessionHas('success');
        $this->assertNull(StateQualifierEntry::find($entry->id));
    }

    public function test_state_admin_can_list_schools_and_reset_a_password(): void
    {
        $school = $this->makeSchool();
        $oldPassword = $school->plain_password;

        $admin = User::factory()->create(['tenant_id' => null, 'must_change_password' => false]);
        $admin->assignRole('state_admin');

        $this->actingAs($admin)
            ->get("/admin/state-programs/external-sahodayas/{$school->external_sahodaya_id}/schools")
            ->assertOk();

        $this->actingAs($admin)
            ->post("/admin/state-programs/external-schools/{$school->id}/reset-password")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNotEquals($oldPassword, $school->fresh()->plain_password);
    }
}
