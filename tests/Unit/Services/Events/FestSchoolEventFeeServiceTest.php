<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Events\FestEventFeeResolver;
use App\Services\Events\FestIdCardQrService;
use App\Services\Events\FestIdCardService;
use App\Services\Events\FestSchoolEventFeeService;
use Database\Seeders\SahodayaMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestSchoolEventFeeServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, school: Tenant, event: FestEvent, item: FestEventItem} */
    private function festContext(): array
    {
        $this->seed(SahodayaMasterDataSeeder::class);

        $sahodaya = Tenant::create([
            'id'        => (string) Str::uuid(),
            'type'      => 'sahodaya',
            'name'      => 'Fee Test Sahodaya',
            'domain'    => 'fee-test-sahodaya.test',
            'is_active' => true,
        ]);

        SahodayaProfile::create([
            'tenant_id'         => $sahodaya->id,
            'prefix'            => 'FTS',
            'student_data_mode' => 'full_records',
        ]);

        $school = Tenant::create([
            'id'            => (string) Str::uuid(),
            'type'          => 'school',
            'name'          => 'Fee Test School',
            'parent_id'     => $sahodaya->id,
            'school_prefix' => 'FT',
            'is_active'     => true,
        ]);

        $event = FestEvent::create([
            'tenant_id'   => $sahodaya->id,
            'title'       => 'Test Kalotsav',
            'event_type'  => 'kalolsavam',
            'level_round' => 'sahodaya',
            'status'      => 'registration_open',
            'fee_settings'=> [
                'fee_model' => 'cksc_tiered',
                'first_item' => 350,
                'additional_item' => 100,
                'charge_standbys' => false,
            ],
        ]);

        $item = FestEventItem::create([
            'event_id'         => $event->id,
            'title'            => 'Mono Act',
            'participant_type' => 'individual',
            'class_group'      => 'hs',
            'is_enabled'       => true,
        ]);

        return compact('sahodaya', 'school', 'event', 'item');
    }

    private function approvedRegistration(FestEvent $event, FestEventItem $item, Tenant $school, ?Student $student = null): FestRegistration
    {
        $registration = FestRegistration::create([
            'event_id'  => $event->id,
            'item_id'   => $item->id,
            'school_id' => $school->id,
            'status'    => 'approved',
        ]);

        FestParticipant::create([
            'registration_id' => $registration->id,
            'student_id'      => $student?->id,
            'participant_role'=> 'performer',
        ]);

        return $registration;
    }

    public function test_cksc_tiered_excludes_school_registration_by_default(): void
    {
        ['school' => $school, 'event' => $event, 'item' => $item] = $this->festContext();

        $event->update([
            'fee_settings' => [
                'fee_model' => 'cksc_tiered',
                'first_item' => 350,
                'additional_item' => 100,
            ],
        ]);

        $this->approvedRegistration($event->fresh(), $item, $school);
        $fee = app(FestSchoolEventFeeService::class)->recalculate($event->fresh(), $school->id);

        $this->assertSame(0.0, (float) $fee->school_registration_fee);
        $this->assertSame(350.0, (float) $fee->participation_fee);
        $this->assertSame(350.0, (float) $fee->total_due);
    }

    public function test_cksc_tiered_can_opt_in_school_registration_add_on(): void
    {
        ['school' => $school, 'event' => $event, 'item' => $item] = $this->festContext();

        $school->update([
            'application_payload' => array_merge($school->application_payload ?? [], [
                'institution_level' => 'secondary',
            ]),
        ]);

        $event->update([
            'fee_settings' => [
                'fee_model' => 'cksc_tiered',
                'include_school_registration' => true,
                'school_registration' => ['secondary' => 5000, 'senior_secondary' => 6000],
                'first_item' => 350,
                'additional_item' => 100,
            ],
        ]);

        $this->approvedRegistration($event->fresh(), $item, $school);
        $fee = app(FestSchoolEventFeeService::class)->recalculate($event->fresh(), $school->id);

        $this->assertSame(5000.0, (float) $fee->school_registration_fee);
        $this->assertSame(5350.0, (float) $fee->total_due);
    }

    public function test_cksc_tiered_counts_registrations_only_by_default(): void
    {
        ['school' => $school, 'event' => $event, 'item' => $item] = $this->festContext();
        $service = app(FestSchoolEventFeeService::class);

        $registration = $this->approvedRegistration($event, $item, $school);
        FestParticipant::create([
            'registration_id' => $registration->id,
            'participant_role'  => 'standby',
        ]);

        $fee = $service->recalculate($event, $school->id);

        $this->assertSame(1, $fee->participation_item_count);
        $this->assertSame(350.0, (float) $fee->participation_fee);
    }

    public function test_legacy_fee_type_none_without_fee_settings_does_not_require_fee(): void
    {
        ['school' => $school, 'event' => $event] = $this->festContext();

        $event->update(['fee_type' => 'none', 'fee_settings' => null]);

        $service = app(FestSchoolEventFeeService::class);

        $this->assertFalse($service->feeRequired($event->fresh()));
        $this->assertTrue($service->isPaid($event->fresh(), $school->id));

        $fee = $service->recalculate($event->fresh(), $school->id);
        $this->assertSame(0.0, (float) $fee->total_due);
        $this->assertSame('approved', $fee->status);
    }

    public function test_explicit_fee_settings_still_apply_cksc_tiered(): void
    {
        ['school' => $school, 'event' => $event, 'item' => $item] = $this->festContext();

        $event->update([
            'fee_type' => 'none',
            'fee_settings' => [
                'fee_model' => 'cksc_tiered',
                'first_item' => 350,
                'additional_item' => 100,
                'charge_standbys' => false,
            ],
        ]);

        $service = app(FestSchoolEventFeeService::class);
        $this->assertTrue($service->feeRequired($event->fresh()));

        $this->approvedRegistration($event->fresh(), $item, $school);
        $fee = $service->recalculate($event->fresh(), $school->id);

        $this->assertGreaterThan(0, (float) $fee->total_due);
    }

    public function test_charge_standbys_adds_standby_units_to_cksc_billing(): void
    {
        ['school' => $school, 'event' => $event, 'item' => $item] = $this->festContext();

        $event->update([
            'fee_settings' => [
                'fee_model' => 'cksc_tiered',
                'first_item' => 350,
                'additional_item' => 100,
                'charge_standbys' => true,
            ],
        ]);

        $registration = $this->approvedRegistration($event, $item, $school);
        FestParticipant::create([
            'registration_id' => $registration->id,
            'participant_role'  => 'standby',
        ]);

        $fee = app(FestSchoolEventFeeService::class)->recalculate($event->fresh(), $school->id);

        $this->assertSame(2, $fee->participation_item_count);
        $this->assertSame(450.0, (float) $fee->participation_fee);
    }

    public function test_per_student_uses_unique_student_count(): void
    {
        ['school' => $school, 'event' => $event, 'item' => $item] = $this->festContext();

        $schoolClass = SchoolClass::where('tenant_id', $school->id)->first()
            ?? SchoolClass::create([
                'tenant_id'         => $school->id,
                'name'              => '10',
                'class_category_id' => 1,
                'is_active'         => true,
            ]);

        $student = Student::create([
            'tenant_id'       => $school->id,
            'school_class_id' => $schoolClass->id,
            'name'            => 'Same Student',
            'gender'          => 'male',
            'dob'             => '2012-01-01',
            'status'          => 'active',
        ]);

        $event->update([
            'fee_settings' => [
                'fee_model' => 'per_student',
                'per_student_amount' => 200,
            ],
        ]);

        $this->approvedRegistration($event, $item, $school, $student);

        $itemTwo = FestEventItem::create([
            'event_id'         => $event->id,
            'title'            => 'Dance',
            'participant_type' => 'individual',
            'class_group'      => 'hs',
            'is_enabled'       => true,
        ]);

        FestRegistration::create([
            'event_id'  => $event->id,
            'item_id'   => $itemTwo->id,
            'school_id' => $school->id,
            'status'    => 'approved',
        ])->participants()->create([
            'student_id'       => $student->id,
            'participant_role' => 'performer',
        ]);

        $fee = app(FestSchoolEventFeeService::class)->recalculate($event->fresh(), $school->id);

        $this->assertSame(1, $fee->participation_item_count);
        $this->assertSame(200.0, (float) $fee->participation_fee);
    }

    public function test_school_fee_cap_limits_total_due(): void
    {
        ['school' => $school, 'event' => $event, 'item' => $item] = $this->festContext();

        $event->update([
            'fee_settings' => [
                'fee_model' => 'per_item',
                'per_item_amount' => 500,
                'school_fee_cap' => 600,
            ],
        ]);

        $this->approvedRegistration($event, $item, $school);

        $itemTwo = FestEventItem::create([
            'event_id'         => $event->id,
            'title'            => 'Tabla',
            'participant_type' => 'individual',
            'class_group'      => 'hs',
            'is_enabled'       => true,
        ]);

        $this->approvedRegistration($event, $itemTwo, $school);

        $fee = app(FestSchoolEventFeeService::class)->recalculate($event->fresh(), $school->id);

        $this->assertSame(600.0, (float) $fee->total_due);
    }

    public function test_normalize_event_fee_settings_persists_cap_for_cksc(): void
    {
        $normalized = app(FestEventFeeResolver::class)->normalizeEventFeeSettings([
            'fee_model' => 'cksc_tiered',
            'first_item' => 350,
            'additional_item' => 100,
            'school_fee_cap' => 5000,
        ]);

        $this->assertSame('cksc_tiered', $normalized['fee_model']);
        $this->assertSame(5000.0, $normalized['school_fee_cap']);
    }

    public function test_id_cards_require_item_for_students(): void
    {
        $service = app(FestIdCardService::class);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Select a fest item before generating student ID cards.');

        $service->requireStudentItem('student', ['scope' => 'item']);
    }

    public function test_id_cards_event_scope_skips_item_requirement(): void
    {
        $service = app(FestIdCardService::class);

        $service->requireStudentItem('student', ['scope' => 'event']);
        $service->requireStudentItem('student', ['scope' => 'head_all']);

        $this->assertTrue(true);
    }

    public function test_id_cards_head_scope_requires_head_id(): void
    {
        $service = app(FestIdCardService::class);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Select an item head before generating student ID cards.');

        $service->requireStudentItem('student', ['scope' => 'head']);
    }

    public function test_cards_grouped_by_head_lists_items_on_one_card(): void
    {
        ['school' => $school, 'event' => $event, 'item' => $item] = $this->festContext();

        $head = \App\Models\FestItemHead::create([
            'tenant_id'  => $event->tenant_id,
            'event_id'   => $event->id,
            'event_type' => $event->event_type,
            'name'       => 'Literary',
            'slug'       => 'literary',
            'sort_order' => 1,
        ]);

        $item->update(['head_id' => $head->id]);

        $itemTwo = FestEventItem::create([
            'event_id'         => $event->id,
            'title'            => 'Mime',
            'participant_type' => 'individual',
            'class_group'      => 'hs',
            'is_enabled'       => true,
            'head_id'          => $head->id,
        ]);

        $schoolClass = SchoolClass::where('tenant_id', $school->id)->first()
            ?? SchoolClass::create([
                'tenant_id'         => $school->id,
                'name'              => '10',
                'class_category_id' => 1,
                'is_active'         => true,
            ]);

        $student = Student::create([
            'tenant_id'       => $school->id,
            'school_class_id' => $schoolClass->id,
            'name'            => 'Head Card Student',
            'gender'          => 'male',
            'dob'             => '2012-01-01',
            'status'          => 'active',
        ]);

        $this->approvedRegistration($event, $item, $school, $student);
        $this->approvedRegistration($event, $itemTwo, $school, $student);

        $sections = app(FestIdCardService::class)->cardsGroupedByHead($event, [
            'school_id' => $school->id,
        ]);

        $this->assertCount(1, $sections);
        $this->assertSame('Literary', $sections[0]['head_title']);
        $this->assertCount(1, $sections[0]['cards']);
        $this->assertSame('head_participant', $sections[0]['cards'][0]['card_type']);
        $this->assertSame(2, $sections[0]['cards'][0]['item_count']);
        $this->assertContains('Mono Act', $sections[0]['cards'][0]['items']);
        $this->assertContains('Mime', $sections[0]['cards'][0]['items']);
    }

    public function test_event_participant_cards_dedupe_by_student(): void
    {
        ['school' => $school, 'event' => $event, 'item' => $item] = $this->festContext();

        $itemTwo = FestEventItem::create([
            'event_id'         => $event->id,
            'title'            => 'Mime',
            'participant_type' => 'individual',
            'class_group'      => 'hs',
            'is_enabled'       => true,
        ]);

        $schoolClass = SchoolClass::where('tenant_id', $school->id)->first()
            ?? SchoolClass::create([
                'tenant_id'         => $school->id,
                'name'              => '10',
                'class_category_id' => 1,
                'is_active'         => true,
            ]);

        $student = Student::create([
            'tenant_id'       => $school->id,
            'school_class_id' => $schoolClass->id,
            'name'            => 'Dual Entry Student',
            'gender'          => 'male',
            'dob'             => '2012-01-01',
            'status'          => 'active',
        ]);

        $this->approvedRegistration($event, $item, $school, $student);
        $this->approvedRegistration($event, $itemTwo, $school, $student);

        $cards = app(FestIdCardService::class)->cards($event, 'student', [
            'scope'     => 'event',
            'school_id' => $school->id,
        ]);

        $this->assertCount(1, $cards);
        $this->assertSame('event_participant', $cards[0]['card_type']);
        $this->assertSame(2, $cards[0]['item_count']);
        $this->assertContains('Mono Act', $cards[0]['items']);
        $this->assertContains('Mime', $cards[0]['items']);
    }

    public function test_cards_grouped_by_item_returns_sections(): void
    {
        ['school' => $school, 'event' => $event, 'item' => $item] = $this->festContext();

        $itemTwo = FestEventItem::create([
            'event_id'         => $event->id,
            'title'            => 'Mime',
            'participant_type' => 'individual',
            'class_group'      => 'hs',
            'is_enabled'       => true,
        ]);

        $this->approvedRegistration($event, $item, $school);
        $this->approvedRegistration($event, $itemTwo, $school);

        $sections = app(FestIdCardService::class)->cardsGroupedByItem($event, [
            'school_id' => $school->id,
        ]);

        $this->assertCount(2, $sections);
        $titles = collect($sections)->pluck('item_title')->sort()->values()->all();
        $this->assertSame(['Mime', 'Mono Act'], $titles);
        $this->assertTrue(collect($sections)->every(fn ($section) => count($section['cards']) === 1));
    }

    public function test_id_cards_filter_by_item(): void
    {
        ['sahodaya' => $sahodaya, 'school' => $school, 'event' => $event, 'item' => $item] = $this->festContext();

        $itemTwo = FestEventItem::create([
            'event_id'         => $event->id,
            'title'            => 'Mime',
            'participant_type' => 'individual',
            'class_group'      => 'hs',
            'is_enabled'       => true,
        ]);

        $this->approvedRegistration($event, $item, $school);
        $this->approvedRegistration($event, $itemTwo, $school);

        $service = app(FestIdCardService::class);
        $cards = $service->cards($event, 'student', ['item_id' => $item->id]);

        $this->assertCount(1, $cards);
        $this->assertSame('Mono Act', $cards[0]['detail']);
        $this->assertNotEmpty($cards[0]['qr_src']);
    }

    public function test_head_window_inherited_for_item_registration(): void
    {
        ['school' => $school, 'event' => $event, 'item' => $item] = $this->festContext();

        $head = \App\Models\FestItemHead::create([
            'tenant_id'  => $event->tenant_id,
            'event_id'   => $event->id,
            'event_type' => $event->event_type,
            'name'       => 'Athletics',
            'slug'       => 'athletics',
            'sort_order' => 1,
            'reg_start'  => now()->subDay()->toDateString(),
            'reg_end'    => now()->addWeek()->toDateString(),
        ]);

        $item->update(['head_id' => $head->id, 'reg_start' => null, 'reg_end' => null]);

        $this->assertTrue(app(\App\Services\Events\FestItemRegistrationGate::class)->isOpen($item->fresh(['head'])));
    }

    public function test_team_cards_group_registrations(): void
    {
        ['school' => $school, 'event' => $event] = $this->festContext();

        $teamItem = FestEventItem::create([
            'event_id'         => $event->id,
            'title'            => 'Group Dance',
            'participant_type' => 'group',
            'class_group'      => 'hs',
            'is_enabled'       => true,
        ]);

        $registration = FestRegistration::create([
            'event_id'  => $event->id,
            'item_id'   => $teamItem->id,
            'school_id' => $school->id,
            'status'    => 'approved',
        ]);

        \App\Models\FestGroup::create([
            'registration_id' => $registration->id,
            'team_name'       => 'Sparklers',
        ]);

        FestParticipant::create([
            'registration_id'           => $registration->id,
            'participant_role'          => 'performer',
            'level_registration_number' => 'FEST-001',
        ]);
        FestParticipant::create([
            'registration_id'           => $registration->id,
            'participant_role'          => 'performer',
            'level_registration_number' => 'FEST-002',
        ]);

        $cards = app(FestIdCardService::class)->cards($event, 'student', [
            'item_id' => $teamItem->id,
            'layout'  => 'team',
        ]);

        $this->assertCount(1, $cards);
        $this->assertSame('team', $cards[0]['card_type']);
        $this->assertSame('Sparklers', $cards[0]['name']);
        $this->assertCount(2, $cards[0]['members']);
        $this->assertNotEmpty($cards[0]['qr_src']);
    }

    public function test_qr_service_generates_png_data_uri(): void
    {
        $uri = app(FestIdCardQrService::class)->dataUri('FEST|test|participant|1|FEST-001');

        $this->assertStringStartsWith('data:image/png;base64,', $uri);
    }
}
