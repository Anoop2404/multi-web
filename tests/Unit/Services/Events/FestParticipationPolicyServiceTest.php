<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestEvent;
use App\Models\FestParticipationPolicy;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Services\Events\FestParticipationPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestParticipationPolicyServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regression: eventPolicy()'s ->when($classGroup, ...) skipped the class_group
     * filter entirely for a null $classGroup (the whole-event student-limits report's
     * own call shape), leaving every class-specific policy row in the result set
     * too — and orderByRaw('class_group IS NULL') sorts those non-null rows FIRST, so
     * ->first() silently returned an arbitrary class-specific policy instead of the
     * event's actual default. A Sahodaya admin's "Total Individual / Student" setting
     * (the default, class_group-less row) was replaced on the report by whichever
     * class-specific row happened to sort first.
     */
    public function test_resolving_with_no_class_group_returns_the_default_policy_not_an_arbitrary_class_specific_one(): void
    {
        [$event] = $this->fixture();

        FestParticipationPolicy::create([
            'tenant_id' => $event->tenant_id, 'scope' => 'event', 'event_id' => $event->id, 'is_active' => true,
            'class_group' => null, 'max_total_per_student' => 5, 'one_entry_per_item_per_school' => false,
            'count_submitted_registrations' => true,
        ]);
        FestParticipationPolicy::create([
            'tenant_id' => $event->tenant_id, 'scope' => 'event', 'event_id' => $event->id, 'is_active' => true,
            'class_group' => 'hs', 'max_total_per_student' => 3, 'one_entry_per_item_per_school' => false,
            'count_submitted_registrations' => true,
        ]);

        $limits = app(FestParticipationPolicyService::class)->resolveForEvent($event, null);

        $this->assertSame(5, $limits['max_total_per_student'], 'no class_group context must resolve the default (class_group IS NULL) policy, not the "hs" one');
    }

    public function test_resolving_with_a_class_group_prefers_its_specific_policy_over_the_default(): void
    {
        [$event] = $this->fixture();

        FestParticipationPolicy::create([
            'tenant_id' => $event->tenant_id, 'scope' => 'event', 'event_id' => $event->id, 'is_active' => true,
            'class_group' => null, 'max_total_per_student' => 5, 'one_entry_per_item_per_school' => false,
            'count_submitted_registrations' => true,
        ]);
        FestParticipationPolicy::create([
            'tenant_id' => $event->tenant_id, 'scope' => 'event', 'event_id' => $event->id, 'is_active' => true,
            'class_group' => 'hs', 'max_total_per_student' => 3, 'one_entry_per_item_per_school' => false,
            'count_submitted_registrations' => true,
        ]);

        $limits = app(FestParticipationPolicyService::class)->resolveForEvent($event, 'hs');

        $this->assertSame(3, $limits['max_total_per_student'], 'a matching class-specific policy must still win over the default');
    }

    public function test_resolving_with_a_class_group_that_has_no_specific_policy_falls_back_to_the_default(): void
    {
        [$event] = $this->fixture();

        FestParticipationPolicy::create([
            'tenant_id' => $event->tenant_id, 'scope' => 'event', 'event_id' => $event->id, 'is_active' => true,
            'class_group' => null, 'max_total_per_student' => 5, 'one_entry_per_item_per_school' => false,
            'count_submitted_registrations' => true,
        ]);
        FestParticipationPolicy::create([
            'tenant_id' => $event->tenant_id, 'scope' => 'event', 'event_id' => $event->id, 'is_active' => true,
            'class_group' => 'hs', 'max_total_per_student' => 3, 'one_entry_per_item_per_school' => false,
            'count_submitted_registrations' => true,
        ]);

        $limits = app(FestParticipationPolicyService::class)->resolveForEvent($event, 'lp');

        $this->assertSame(5, $limits['max_total_per_student'], 'a class group with no policy of its own falls back to the default');
    }

    private function fixture(): array
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Policy Test Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'PTS', 'student_data_mode' => 'counts_only']);
        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Kalotsav', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);

        return [$event];
    }
}
