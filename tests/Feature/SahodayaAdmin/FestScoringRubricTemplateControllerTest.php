<?php

namespace Tests\Feature\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMarkCriterion;
use App\Models\FestScoringRubricTemplate;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestScoringRubricTemplateControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{sahodaya: Tenant, admin: User} */
    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(),
            'type' => 'sahodaya',
            'name' => 'Rubric Test Sahodaya',
            'domain' => Str::uuid().'.test',
            'is_active' => true,
        ]);
        SahodayaProfile::create([
            'tenant_id' => $sahodaya->id,
            'prefix' => 'RT',
            'student_data_mode' => 'counts_only',
        ]);

        $admin = User::factory()->create(['tenant_id' => $sahodaya->id]);
        $admin->assignRole('sahodaya_admin');

        return compact('sahodaya', 'admin');
    }

    public function test_admin_can_create_a_template_and_add_criteria_to_it(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin] = $this->fixture();

        $store = $this->actingAs($admin)->post(
            route('sahodaya.scoring-rubric-templates.store', ['tenantId' => $sahodaya->id]),
            ['name' => 'Standard On-Stage Solo', 'description' => 'Content / Presentation'],
        );
        $store->assertSessionHas('success');

        $template = FestScoringRubricTemplate::where('tenant_id', $sahodaya->id)->firstOrFail();
        $this->assertSame('Standard On-Stage Solo', $template->name);

        $this->actingAs($admin)->post(
            route('sahodaya.scoring-rubric-templates.criteria.store', ['tenantId' => $sahodaya->id, 'scoringRubricTemplate' => $template->id]),
            ['label' => 'Content', 'max_score' => 10],
        )->assertSessionHas('success');

        $this->actingAs($admin)->post(
            route('sahodaya.scoring-rubric-templates.criteria.store', ['tenantId' => $sahodaya->id, 'scoringRubricTemplate' => $template->id]),
            ['label' => 'Presentation', 'max_score' => 15],
        )->assertSessionHas('success');

        $this->assertSame(['Content', 'Presentation'], $template->criteria()->pluck('label')->all());
    }

    public function test_deleting_a_template_does_not_affect_items_that_already_applied_it(): void
    {
        ['sahodaya' => $sahodaya, 'admin' => $admin] = $this->fixture();

        $template = FestScoringRubricTemplate::create(['tenant_id' => $sahodaya->id, 'name' => 'Solo Rubric', 'sort_order' => 0]);
        $template->criteria()->create(['tenant_id' => $sahodaya->id, 'label' => 'Content', 'max_score' => 10, 'sort_order' => 0]);

        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Rubric Test Event', 'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya', 'status' => 'registration_open',
        ]);
        $item = FestEventItem::create([
            'event_id' => $event->id, 'title' => 'Item One', 'participant_type' => 'individual', 'is_enabled' => true,
        ]);

        $this->actingAs($admin)->post(
            route('sahodaya.events.items.mark-criteria.apply-template', ['tenantId' => $sahodaya->id, 'event' => $event->id, 'item' => $item->id]),
            ['template_id' => $template->id],
        )->assertSessionHas('success');

        $this->assertSame(['Content'], FestMarkCriterion::where('item_id', $item->id)->pluck('label')->all());

        $this->actingAs($admin)->delete(
            route('sahodaya.scoring-rubric-templates.destroy', ['tenantId' => $sahodaya->id, 'scoringRubricTemplate' => $template->id]),
        )->assertSessionHas('success');

        $this->assertNull(FestScoringRubricTemplate::find($template->id));
        // The item's own copied criterion is untouched by the template's deletion.
        $this->assertSame(['Content'], FestMarkCriterion::where('item_id', $item->id)->pluck('label')->all());
    }
}
