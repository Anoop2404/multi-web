<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\FestEvent;
use App\Models\FestEventAdminAccount;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Events\FestEventAdminAccountService;
use App\Support\EventAdminFinancePolicy;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FestAutomaticEventAdminTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $tenant = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'sahodaya',
            'name' => 'Login Test', 'domain' => 'login-'.Str::random(8).'.test', 'is_active' => true]);
        SahodayaProfile::create(['tenant_id' => $tenant->id, 'prefix' => 'LT', 'student_data_mode' => 'counts_only']);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'email_verified_at' => now()]);
        $owner->assignRole('sahodaya_admin');
        $event = FestEvent::create(['tenant_id' => $tenant->id, 'title' => 'Science Expo',
            'event_type' => 'science_fest', 'level_round' => 'sahodaya', 'status' => 'registration_open']);

        return [$tenant, $owner, $event];
    }

    public function test_creation_provisions_an_idempotent_scoped_account_with_an_encrypted_temporary_password(): void
    {
        [$tenant, , $event] = $this->fixture();
        $service = app(FestEventAdminAccountService::class);
        $automatic = FestEventAdminAccount::where('event_id', $event->id)->firstOrFail();
        $account = $service->ensure($event);
        $this->assertSame($automatic->id, $account->id);
        $again = $service->ensure($event);
        $this->assertSame($account->id, $again->id);
        $this->assertSame(1, FestEventAdminAccount::where('event_id', $event->id)->count());
        $this->assertSame('event'.$event->id, $account->user->username);
        $this->assertTrue(Hash::check($account->temporary_password, $account->user->password));
        $this->assertNotSame($account->temporary_password, DB::table('fest_event_admin_accounts')->where('id', $account->id)->value('temporary_password'));
        $this->assertNull($account->user->plain_password);
        $this->assertTrue($account->user->can('fest.marks'));
        $this->assertTrue($account->user->can('fest.settings'));
        $this->assertFalse($account->user->can('fest.finance'));
        $account->user->givePermissionTo('fest.finance');
        $this->assertFalse($account->user->can('fest.finance'));
        $this->assertFalse($account->user->getAllPermissions()->pluck('name')->contains('fest.finance'));
        $this->assertDatabaseHas('fest_event_staff', ['event_id' => $event->id, 'user_id' => $account->user_id, 'duty' => 'event_admin']);
        $account->delete();
        $this->assertFalse($account->user->fresh()->can('fest.finance'));
    }

    public function test_regional_accounts_cannot_access_sibling_or_parent_events_or_finance(): void
    {
        [$tenant, , $hub] = $this->fixture();
        $one = FestEvent::create(['tenant_id' => $tenant->id, 'parent_event_id' => $hub->id,
            'title' => 'Region One', 'event_type' => 'science_fest', 'status' => 'registration_open']);
        $two = FestEvent::create(['tenant_id' => $tenant->id, 'parent_event_id' => $hub->id,
            'title' => 'Region Two', 'event_type' => 'science_fest', 'status' => 'registration_open']);
        $user = FestEventAdminAccount::where('event_id', $one->id)->firstOrFail()->user;
        $user->update(['must_change_password' => false]);
        $base = '/sahodaya-admin/'.$tenant->id.'/events/';
        $this->actingAs($user)->get($base.$one->id.'/event-staff')->assertOk();
        $this->get($base.$two->id.'/event-staff')->assertForbidden();
        $this->get($base.$hub->id.'/event-staff')->assertForbidden();
        $this->get($base.$one->id.'/fees')->assertForbidden();
        $this->post($base.$one->id.'/event-admin-login/reset')->assertForbidden();
    }

    public function test_only_the_owner_receives_the_copyable_credentials(): void
    {
        [$tenant, $owner, $event] = $this->fixture();
        $account = FestEventAdminAccount::where('event_id', $event->id)->firstOrFail();
        $url = '/sahodaya-admin/'.$tenant->id.'/events/'.$event->id.'/event-staff';
        $this->actingAs($owner)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('eventAdminAccounts.0.username', $account->user->username)
            ->where('eventAdminAccounts.0.password', $account->temporary_password)
            ->where('eventAdminAccounts.0.share_text', fn ($text) => str_contains($text, '*Science Expo*')
                && str_contains($text, 'Username: ') && str_contains($text, 'Password: ')));
        $account->user->update(['must_change_password' => false]);
        $this->actingAs($account->user)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('canManageEventLogins', false)->has('eventAdminAccounts', 0)->missing('pendingPaymentsCount'));
        $version = app(HandleInertiaRequests::class)->version(Request::create($url));
        $this->get($url, ['X-Inertia' => 'true', 'X-Inertia-Version' => $version])->assertOk()
            ->assertJsonMissingPath('props.pendingPaymentsCount');
    }

    public function test_portal_users_lists_shareable_event_credentials_only_for_the_owner(): void
    {
        [$tenant, $owner, $event] = $this->fixture();
        $account = FestEventAdminAccount::where('event_id', $event->id)->firstOrFail();
        $this->actingAs($owner)->get('/sahodaya-admin/'.$tenant->id.'/users')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('users', fn ($users) => collect($users)
                ->contains(fn ($user) => ($user['event_admin_credentials']['username'] ?? null) === $account->user->username)));
    }

    public function test_nonfinancial_edits_work_but_financial_fields_and_exports_are_forbidden(): void
    {
        [$tenant, , $event] = $this->fixture();
        $user = FestEventAdminAccount::where('event_id', $event->id)->firstOrFail()->user;
        $user->update(['must_change_password' => false]);
        $base = '/sahodaya-admin/'.$tenant->id.'/events/'.$event->id;
        $this->actingAs($user)->put($base, ['title' => 'Updated Expo', 'status' => 'registration_open'])->assertRedirect();
        $this->assertSame('Updated Expo', $event->fresh()->title);
        $this->put($base, ['title' => 'Changed Fee', 'status' => 'registration_open', 'fee_amount' => 999])->assertForbidden();
        $this->assertSame('Updated Expo', $event->fresh()->title);
        $this->get($base.'/reports/export/fee-breakdown')->assertForbidden();
        $csv = $this->get($base.'/reports/registration-register/export')->assertOk()->streamedContent();
        $this->assertStringNotContainsString('Item fee', $csv);
        $this->assertStringNotContainsString('School total due', $csv);
    }

    public function test_owner_can_reset_credentials_but_another_tenant_cannot(): void
    {
        [$tenant, $owner, $event] = $this->fixture();
        $account = FestEventAdminAccount::where('event_id', $event->id)->firstOrFail();
        $old = $account->temporary_password;
        $url = '/sahodaya-admin/'.$tenant->id.'/events/'.$event->id.'/event-admin-login/reset';
        $this->actingAs($owner)->post($url)->assertRedirect();
        $this->assertNotSame($old, $account->fresh()->temporary_password);
        $other = User::factory()->create(['tenant_id' => 'another-tenant', 'email_verified_at' => now()]);
        $other->assignRole('sahodaya_admin');
        $this->actingAs($other)->post($url)->assertForbidden();
    }

    public function test_changed_passwords_are_not_shared_and_reset_invalidates_the_previous_password(): void
    {
        [, , $event] = $this->fixture();
        $account = FestEventAdminAccount::where('event_id', $event->id)->firstOrFail();
        $service = app(FestEventAdminAccountService::class);
        $original = $account->temporary_password;
        $account->user->update(['password' => 'my-own-new-password', 'must_change_password' => false]);
        $account->unsetRelation('user');
        $this->assertNull($service->credentials($account)['share_text']);
        $account->user->update(['password' => 'reset-outside-event-login', 'must_change_password' => true]);
        $account->unsetRelation('user');
        $this->assertNull($service->credentials($account)['share_text']);
        $service->reset($account);
        $account->refresh();
        $this->assertNotSame($original, $account->temporary_password);
        $this->assertFalse(Hash::check($original, $account->user->password));
        $this->assertTrue(Hash::check($account->temporary_password, $account->user->password));
    }

    public function test_nested_financial_data_is_removed_without_removing_operational_data(): void
    {
        $data = EventAdminFinancePolicy::withoutFinance(['event' => ['title' => 'Expo', 'fee_amount' => 500],
            'pendingPaymentsCount' => 3, 'items' => [['title' => 'Quiz', 'price' => 100]], 'results' => [1, 2]]);
        $this->assertSame(['event' => ['title' => 'Expo'], 'items' => [['title' => 'Quiz']], 'results' => [1, 2]], $data);
    }

    public function test_api_event_data_excludes_financial_fields_and_rejects_other_events(): void
    {
        [$tenant, , $event] = $this->fixture();
        $event->update(['fee_amount' => 500, 'fee_type' => 'flat_school']);
        $user = FestEventAdminAccount::where('event_id', $event->id)->firstOrFail()->user;
        $user->update(['must_change_password' => false]);
        $other = FestEvent::create(['tenant_id' => $tenant->id, 'title' => 'Another Expo',
            'event_type' => 'science_fest', 'status' => 'registration_open']);
        $token = $user->createToken('event-admin-test')->plainTextToken;
        $base = '/api/v1/sahodaya/'.$tenant->id.'/events/';
        $this->withHeader('Authorization', 'Bearer '.$token)->getJson($base.$event->id)
            ->assertOk()->assertJsonPath('data.title', 'Science Expo')
            ->assertJsonMissingPath('data.fee_amount')->assertJsonMissingPath('data.fee_type');
        $this->getJson($base.$other->id)->assertForbidden();
    }

    public function test_portal_users_can_create_missing_legacy_event_and_regional_logins_and_share_them(): void
    {
        [$tenant, $owner, $existing] = $this->fixture();
        $legacy = FestEvent::withoutEvents(fn () => FestEvent::create([
            'tenant_id' => $tenant->id, 'title' => 'Legacy Expo', 'event_type' => 'science_fest', 'status' => 'registration_open',
        ]));
        $region = FestEvent::withoutEvents(fn () => FestEvent::create([
            'tenant_id' => $tenant->id, 'parent_event_id' => $legacy->id,
            'title' => 'Legacy Expo — Region One', 'event_type' => 'science_fest', 'status' => 'registration_open',
        ]));
        $url = '/sahodaya-admin/'.$tenant->id.'/users';
        $this->actingAs($owner)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('canCreateEventLogins', true)->has('eventLoginOptions', 2)
            ->where('eventLoginOptions', fn ($options) => ! collect($options)->pluck('id')->contains($existing->id)));

        $endpoint = '/sahodaya-admin/'.$tenant->id.'/events/'.$legacy->id.'/event-admin-login';
        $this->from($url)->post($endpoint)->assertRedirect($url);
        $rootAccount = FestEventAdminAccount::where('event_id', $legacy->id)->firstOrFail();
        $this->assertDatabaseHas('fest_event_admin_accounts', ['event_id' => $region->id]);
        $this->assertFalse($rootAccount->user->can('fest.finance'));
        $this->get($url)->assertInertia(fn (Assert $page) => $page->has('eventLoginOptions', 0)
            ->where('newEventAdminCredentials.event_name', 'Legacy Expo')
            ->where('newEventAdminCredentials.username', $rootAccount->user->username)
            ->where('newEventAdminCredentials.password', $rootAccount->temporary_password));
        $password = $rootAccount->user->password;
        $this->from($url)->post($endpoint)->assertRedirect();
        $this->assertSame($password, $rootAccount->user->fresh()->password);
        $this->assertSame(1, FestEventAdminAccount::where('event_id', $legacy->id)->count());
    }

    public function test_user_rows_offer_a_whatsapp_template_for_manual_accounts_with_current_temporary_passwords(): void
    {
        [$tenant, $owner, $event] = $this->fixture();
        $manual = User::factory()->create(['tenant_id' => $tenant->id, 'username' => 'tiruradmin']);
        $manual->assignRole('region_admin');
        $manual->forceFill(['password' => 'sharepw2345', 'plain_password' => 'sharepw2345', 'must_change_password' => true])->save();
        \App\Models\FestEventStaff::create(['event_id' => $event->id, 'user_id' => $manual->id, 'duty' => 'region_admin']);
        $this->actingAs($owner)->get('/sahodaya-admin/'.$tenant->id.'/users')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('users', fn ($users) => collect($users)->contains(fn ($row) =>
                $row['id'] === $manual->id && $row['login_credentials']['password'] === 'sharepw2345'
                && str_contains($row['login_credentials']['share_text'], '*Science Expo*')
                && str_contains($row['login_credentials']['share_text'], 'Username: tiruradmin'))));

        $manual->forceFill(['password' => 'my-private-new-password', 'must_change_password' => false])->save();
        $this->get('/sahodaya-admin/'.$tenant->id.'/users')->assertInertia(fn (Assert $page) => $page
            ->where('users', fn ($users) => collect($users)->contains(fn ($row) => $row['id'] === $manual->id
                && $row['login_credentials']['username'] === 'tiruradmin' && $row['login_credentials']['share_text'] === null)));
    }

    public function test_generic_reset_pw_produces_copyable_credentials_for_an_automatic_event_account(): void
    {
        [$tenant, $owner, $event] = $this->fixture();
        $account = FestEventAdminAccount::where('event_id', $event->id)->firstOrFail();
        $this->actingAs($owner)->post('/sahodaya-admin/'.$tenant->id.'/users/'.$account->user_id.'/reset-password')->assertRedirect();
        $newPassword = $account->user->fresh()->plain_password;
        $this->assertNotNull($newPassword);
        $this->get('/sahodaya-admin/'.$tenant->id.'/users')->assertInertia(fn (Assert $page) => $page
            ->where('users', fn ($users) => collect($users)->contains(fn ($row) => $row['id'] === $account->user_id
                && $row['login_credentials']['password'] === $newPassword
                && str_contains($row['login_credentials']['share_text'], 'Password: '.$newPassword))));
    }

    public function test_event_admins_cannot_read_other_users_copyable_passwords(): void
    {
        [$tenant, , $event] = $this->fixture();
        $user = FestEventAdminAccount::where('event_id', $event->id)->firstOrFail()->user;
        $user->update(['must_change_password' => false]);
        $this->actingAs($user)->get('/sahodaya-admin/'.$tenant->id.'/users')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('users', fn ($users) => collect($users)
                ->every(fn ($row) => $row['login_credentials'] === null)));
    }
}
