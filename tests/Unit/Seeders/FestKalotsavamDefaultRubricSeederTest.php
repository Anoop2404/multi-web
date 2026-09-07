<?php

namespace Tests\Unit\Seeders;

use App\Models\FestScoringRubricTemplate;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use Database\Seeders\FestKalotsavamDefaultRubricSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FestKalotsavamDefaultRubricSeederTest extends TestCase
{
    use RefreshDatabase;

    private function makeSahodaya(): Tenant
    {
        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Rubric Seed Test Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'RST', 'student_data_mode' => 'counts_only']);

        return $sahodaya;
    }

    public function test_seeds_every_manual_template_with_criteria_summing_to_100(): void
    {
        $sahodaya = $this->makeSahodaya();

        (new FestKalotsavamDefaultRubricSeeder)->run();

        $templates = FestScoringRubricTemplate::forTenant($sahodaya->id)->with('criteria')->get();

        $this->assertGreaterThanOrEqual(35, $templates->count(), 'expected the full State Kalotsavam Manual rubric catalog to be seeded');

        $essay = $templates->firstWhere('name', 'Essay Writing');
        $this->assertNotNull($essay);
        $this->assertSame(
            ['Suitable introduction and conclusion', 'Systematic and logical arrangement of points', 'In-depth knowledge of the topic', 'Flow of language', 'Grammatical correctness', 'Correct spelling'],
            $essay->criteria->pluck('label')->all(),
        );

        foreach ($templates as $template) {
            $sum = $template->criteria->sum('max_score');
            $this->assertEqualsWithDelta(100.0, (float) $sum, 0.01, "{$template->name} criteria must sum to 100, got {$sum}");
        }
    }

    public function test_seeding_twice_does_not_duplicate_templates_or_criteria(): void
    {
        $sahodaya = $this->makeSahodaya();

        (new FestKalotsavamDefaultRubricSeeder)->run();
        $firstCount = FestScoringRubricTemplate::forTenant($sahodaya->id)->count();

        (new FestKalotsavamDefaultRubricSeeder)->run();
        $secondCount = FestScoringRubricTemplate::forTenant($sahodaya->id)->count();

        $this->assertSame($firstCount, $secondCount);

        $lightMusic = FestScoringRubricTemplate::forTenant($sahodaya->id)->where('name', 'Light Music')->firstOrFail();
        $this->assertCount(5, $lightMusic->criteria);
    }

    public function test_seeding_preserves_an_admins_customization_of_an_already_seeded_template(): void
    {
        $sahodaya = $this->makeSahodaya();

        (new FestKalotsavamDefaultRubricSeeder)->run();

        $recitation = FestScoringRubricTemplate::forTenant($sahodaya->id)->where('name', 'Recitation')->firstOrFail();
        $customized = $recitation->criteria()->first();
        $customized->update(['max_score' => 40]);

        (new FestKalotsavamDefaultRubricSeeder)->run();

        $this->assertSame(40.0, (float) $customized->fresh()->max_score, 're-running the seeder must not overwrite a value an admin already customized');
    }

    public function test_seeds_across_multiple_sahodaya_tenants_independently(): void
    {
        $sahodayaA = $this->makeSahodaya();
        $sahodayaB = $this->makeSahodaya();

        (new FestKalotsavamDefaultRubricSeeder)->run();

        $this->assertTrue(FestScoringRubricTemplate::forTenant($sahodayaA->id)->where('name', 'Group Dance')->exists());
        $this->assertTrue(FestScoringRubricTemplate::forTenant($sahodayaB->id)->where('name', 'Group Dance')->exists());
    }
}
