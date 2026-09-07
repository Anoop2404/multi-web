<?php

namespace Database\Seeders;

use App\Models\FestScoringRubricTemplate;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the State Kalotsavam Manual 2026's "Items and Judgement Criteria" (Section IV)
 * as reusable FestScoringRubricTemplate rows, one per item type (Pencil Drawing, Essay
 * Writing, Light Music, Group Dance, ...), so a Sahodaya conducting Kalotsav can apply the
 * exact state-prescribed judging sheet to its items via the per-item "Apply Template" action
 * (FestMarkCriteriaService::applyTemplateToItem()) or the bulk assignment panel on
 * Mark Settings > Bulk (FestMarkEntryController::bulkApplyTemplate()) — including syncing
 * the applied criteria out to matching items on region/phase partition copies.
 *
 * Runs against every Sahodaya tenant database (one DB per tenant) and is idempotent:
 * re-running it only fills in templates/criteria that don't already exist for that tenant,
 * it never overwrites a template an admin has since customized.
 *
 * Usage: php artisan db:seed --class=FestKalotsavamDefaultRubricSeeder
 */
class FestKalotsavamDefaultRubricSeeder extends Seeder
{
    /**
     * @var array<string, list<array{label: string, max_score: float}>>
     *      template name => ordered list of {label, max_score}, each row's own script
     */
    private const TEMPLATES = [
        'Pencil Drawing' => [
            ['label' => 'Proportion', 'max_score' => 25],
            ['label' => 'Neatness', 'max_score' => 25],
            ['label' => 'Imagination and beauty of the strokes', 'max_score' => 25],
            ['label' => 'Clarity of the theme', 'max_score' => 25],
        ],
        'Painting - Water Color & Oil Color' => [
            ['label' => 'Ability in using the colours', 'max_score' => 20],
            ['label' => 'Composition', 'max_score' => 20],
            ['label' => 'Style', 'max_score' => 20],
            ['label' => 'Clarity of the theme', 'max_score' => 20],
            ['label' => 'Overall completion of work', 'max_score' => 20],
        ],
        'Cartoon' => [
            ['label' => 'Clarity of the theme', 'max_score' => 20],
            ['label' => 'Sense of humour', 'max_score' => 20],
            ['label' => 'Uniqueness', 'max_score' => 20],
            ['label' => 'Beauty of the strokes', 'max_score' => 20],
            ['label' => 'Composition', 'max_score' => 20],
        ],
        'Digital Painting' => [
            ['label' => 'Clarity of theme', 'max_score' => 20],
            ['label' => 'Optimization of images', 'max_score' => 20],
            ['label' => 'Synchronization of colour settings', 'max_score' => 20],
            ['label' => 'Perception', 'max_score' => 20],
            ['label' => 'Uniqueness', 'max_score' => 20],
        ],
        'Poster Designing' => [
            ['label' => 'Subject composition', 'max_score' => 20],
            ['label' => 'Creativity', 'max_score' => 20],
            ['label' => 'Visualization', 'max_score' => 20],
            ['label' => 'Approach of emblem and logo', 'max_score' => 20],
            ['label' => 'Colour scheme', 'max_score' => 20],
        ],
        'Collage' => [
            ['label' => 'Approach to subject', 'max_score' => 20],
            ['label' => 'Recycled materials used', 'max_score' => 20],
            ['label' => 'Neatness', 'max_score' => 20],
            ['label' => 'Creativity', 'max_score' => 20],
            ['label' => 'Colour scheme', 'max_score' => 20],
        ],
        'Essay Writing' => [
            ['label' => 'Suitable introduction and conclusion', 'max_score' => 20],
            ['label' => 'Systematic and logical arrangement of points', 'max_score' => 20],
            ['label' => 'In-depth knowledge of the topic', 'max_score' => 20],
            ['label' => 'Flow of language', 'max_score' => 20],
            ['label' => 'Grammatical correctness', 'max_score' => 10],
            ['label' => 'Correct spelling', 'max_score' => 10],
        ],
        'Story Writing' => [
            ['label' => 'Correct understanding of the theme', 'max_score' => 25],
            ['label' => 'Flow of language', 'max_score' => 25],
            ['label' => 'Correct spelling', 'max_score' => 25],
            ['label' => 'Imagination and creativity', 'max_score' => 25],
        ],
        'Versification' => [
            ['label' => 'Rhythmic arrangement of words in poems', 'max_score' => 25],
            ['label' => 'Imagination', 'max_score' => 25],
            ['label' => 'Flow of language', 'max_score' => 25],
            ['label' => 'Correct spelling', 'max_score' => 25],
        ],
        'Extempore / Elocution' => [
            ['label' => 'In-depth knowledge of the topic', 'max_score' => 20],
            ['label' => 'Logical arrangement of points', 'max_score' => 20],
            ['label' => 'Flow of language', 'max_score' => 20],
            ['label' => 'Grammatical correctness', 'max_score' => 20],
            ['label' => 'Appropriate pose, gestures, facial expressions, voice modulation, confidence', 'max_score' => 20],
        ],
        'Recitation' => [
            ['label' => 'Suitable facial expressions', 'max_score' => 25],
            ['label' => 'Imbibing the meaning and message of the poem', 'max_score' => 25],
            ['label' => 'Clarity and correct pronunciation', 'max_score' => 25],
            ['label' => 'Appropriate introduction and voice modulation', 'max_score' => 25],
        ],
        'Light Music' => [
            ['label' => 'Shareeram', 'max_score' => 20],
            ['label' => 'Sruthilayam', 'max_score' => 20],
            ['label' => 'Jnana bhavam', 'max_score' => 20],
            ['label' => 'Shabda Shudhi', 'max_score' => 20],
            ['label' => 'Thalam', 'max_score' => 20],
        ],
        'Classical Music' => [
            ['label' => 'Shareeram', 'max_score' => 15],
            ['label' => 'Sruthilayam', 'max_score' => 15],
            ['label' => 'Jnana bhavam', 'max_score' => 20],
            ['label' => 'Bhava Shudhi', 'max_score' => 15],
            ['label' => 'Thalam', 'max_score' => 20],
            ['label' => 'Manodharmam', 'max_score' => 15],
        ],
        'Mappilapattu' => [
            ['label' => 'Sahithyam', 'max_score' => 25],
            ['label' => 'Sruthilayam', 'max_score' => 25],
            ['label' => 'Mappilla Thanima', 'max_score' => 25],
            ['label' => 'Thaalam', 'max_score' => 25],
        ],
        'Group Song' => [
            ['label' => 'Harmony', 'max_score' => 20],
            ['label' => 'Group singing', 'max_score' => 20],
            ['label' => 'Sruthilayam', 'max_score' => 20],
            ['label' => 'Shabda Shudhi', 'max_score' => 20],
            ['label' => 'Thalam', 'max_score' => 20],
        ],
        'Group Patriotic Song' => [
            ['label' => 'Harmony', 'max_score' => 20],
            ['label' => 'Group singing', 'max_score' => 20],
            ['label' => 'Sruthilayam', 'max_score' => 20],
            ['label' => 'Shabda Shudhi', 'max_score' => 20],
            ['label' => 'Thalam', 'max_score' => 20],
        ],
        'Instrumental - Thabala' => [
            ['label' => 'Fingering', 'max_score' => 25],
            ['label' => 'Chollu', 'max_score' => 25],
            ['label' => 'Gamagam', 'max_score' => 25],
            ['label' => 'Thalam', 'max_score' => 25],
        ],
        'Instrumental - Mridangam' => [
            ['label' => 'Fingering', 'max_score' => 25],
            ['label' => 'Chollu', 'max_score' => 25],
            ['label' => 'Nadham', 'max_score' => 25],
            ['label' => 'Thalam', 'max_score' => 25],
        ],
        'Instrumental - Violin (Eastern)' => [
            ['label' => 'Fingering', 'max_score' => 25],
            ['label' => 'Swarasthanam', 'max_score' => 25],
            ['label' => 'Nadham', 'max_score' => 25],
            ['label' => 'Thalam', 'max_score' => 25],
        ],
        'Instrumental - Flute' => [
            ['label' => 'Swarasthanam', 'max_score' => 25],
            ['label' => 'Thalam', 'max_score' => 25],
            ['label' => 'Nadham', 'max_score' => 25],
            ['label' => 'Manodharmam', 'max_score' => 25],
        ],
        'Instrumental - Guitar (Western)' => [
            ['label' => 'Nadham', 'max_score' => 25],
            ['label' => 'Thalam', 'max_score' => 25],
            ['label' => 'Fingering', 'max_score' => 25],
            ['label' => 'Notation', 'max_score' => 25],
        ],
        'Oppana' => [
            ['label' => 'Rhythm of singing', 'max_score' => 20],
            ['label' => 'Expressions', 'max_score' => 20],
            ['label' => 'Dress and movements', 'max_score' => 20],
            ['label' => 'Thalam and combination of clapping', 'max_score' => 20],
            ['label' => 'Originality of presentation', 'max_score' => 20],
        ],
        'Thiruvathirakali' => [
            ['label' => 'Costume', 'max_score' => 20],
            ['label' => 'Originality of the item', 'max_score' => 20],
            ['label' => 'Chalanam', 'max_score' => 20],
            ['label' => 'Thalam', 'max_score' => 20],
            ['label' => 'Steps', 'max_score' => 20],
        ],
        'Group Dance' => [
            ['label' => 'Clarity, expressions and theme', 'max_score' => 20],
            ['label' => 'Harmony between the performers', 'max_score' => 20],
            ['label' => 'Movement', 'max_score' => 20],
            ['label' => 'Rhythm', 'max_score' => 20],
            ['label' => 'Presentation', 'max_score' => 20],
        ],
        'Margham Kali' => [
            ['label' => 'Veshathanima', 'max_score' => 20],
            ['label' => 'Thalam', 'max_score' => 20],
            ['label' => 'Steps', 'max_score' => 20],
            ['label' => 'Music and clarity of singing', 'max_score' => 20],
            ['label' => 'Presentation', 'max_score' => 20],
        ],
        'Folk Dance' => [
            ['label' => 'Akara Sushama', 'max_score' => 15],
            ['label' => 'Prakadanam', 'max_score' => 20],
            ['label' => 'Thalam', 'max_score' => 20],
            ['label' => 'Mudhra', 'max_score' => 15],
            ['label' => 'Chalana bhangi', 'max_score' => 15],
            ['label' => 'Costume', 'max_score' => 15],
        ],
        'Classical Dance (Bharatanatyam / Mohiniyattam / Kuchipudi)' => [
            ['label' => 'Vesham', 'max_score' => 15],
            ['label' => 'Akara Sushama', 'max_score' => 15],
            ['label' => 'Bhava Prakadanam', 'max_score' => 20],
            ['label' => 'Mudra', 'max_score' => 15],
            ['label' => 'Thalam', 'max_score' => 20],
            ['label' => 'Steps', 'max_score' => 15],
        ],
        'Ottamthullal' => [
            ['label' => 'Vesham', 'max_score' => 20],
            ['label' => 'Sangeetham and Sahithyam', 'max_score' => 20],
            ['label' => 'Uchaaranam', 'max_score' => 20],
            ['label' => 'Abhinayam', 'max_score' => 20],
            ['label' => 'Thalam', 'max_score' => 20],
        ],
        'Kolkali' => [
            ['label' => 'Originality of song', 'max_score' => 20],
            ['label' => 'Thalavum Koladakkavum', 'max_score' => 25],
            ['label' => 'Meythayam', 'max_score' => 15],
            ['label' => 'Steps', 'max_score' => 25],
            ['label' => 'Presentation', 'max_score' => 15],
        ],
        'Duff Mutt' => [
            ['label' => 'Correct rhythmic singing', 'max_score' => 25],
            ['label' => 'Expressions', 'max_score' => 25],
            ['label' => 'Presentation', 'max_score' => 25],
            ['label' => 'Byth, Mahath', 'max_score' => 25],
        ],
        'Mono Act' => [
            ['label' => 'Clarity of speech', 'max_score' => 20],
            ['label' => 'Clarity in ideas used', 'max_score' => 20],
            ['label' => 'Imitative skill', 'max_score' => 20],
            ['label' => 'Conversational skills', 'max_score' => 20],
            ['label' => 'Theme and character presentation', 'max_score' => 20],
        ],
        'Mimicry' => [
            ['label' => 'Relation to theme', 'max_score' => 25],
            ['label' => 'Acting', 'max_score' => 25],
            ['label' => 'Imitative skill', 'max_score' => 25],
            ['label' => 'Originality', 'max_score' => 25],
        ],
        'Mime' => [
            ['label' => 'Contemporary value of the theme', 'max_score' => 25],
            ['label' => 'Expression', 'max_score' => 25],
            ['label' => 'Originality', 'max_score' => 25],
            ['label' => 'Clarity', 'max_score' => 25],
        ],
        'PowerPoint Presentation' => [
            ['label' => 'Clarity of theme', 'max_score' => 20],
            ['label' => 'In-depth knowledge of the topic given', 'max_score' => 20],
            ['label' => 'Appropriate animation, colour code, transition and images used', 'max_score' => 20],
            ['label' => 'Suitable introduction and conclusion', 'max_score' => 20],
            ['label' => 'Verbal presentation', 'max_score' => 20],
        ],
        'English One Act Play' => [
            ['label' => 'Plot / Narrative', 'max_score' => 20],
            ['label' => 'Flow of language', 'max_score' => 20],
            ['label' => 'Acting', 'max_score' => 20],
            ['label' => 'Stage use', 'max_score' => 20],
            ['label' => 'Costume', 'max_score' => 20],
        ],
        'Anchoring' => [
            ['label' => 'Presentation (body language, cheerfulness)', 'max_score' => 20],
            ['label' => 'Aptness of dress (style of dressing, colour and pattern)', 'max_score' => 20],
            ['label' => 'Flow of language (voice modulation, accent, language)', 'max_score' => 20],
            ['label' => 'Expression (emotions, actions)', 'max_score' => 20],
            ['label' => 'Subject knowledge (in-depth knowledge of the event, clarity)', 'max_score' => 20],
        ],
        'Western Music Concert' => [
            ['label' => 'Rhythmic control', 'max_score' => 20],
            ['label' => 'Interpretation of style', 'max_score' => 20],
            ['label' => 'Technique', 'max_score' => 20],
            ['label' => 'Band dynamics', 'max_score' => 20],
            ['label' => 'Voice modulation and pronunciation', 'max_score' => 20],
        ],
        'Band Display' => [
            ['label' => 'Marching and formation', 'max_score' => 20],
            ['label' => 'Standing and playing', 'max_score' => 20],
            ['label' => 'Neatness and functioning of instruments', 'max_score' => 20],
            ['label' => 'Turn out', 'max_score' => 20],
            ['label' => 'National Anthem playing', 'max_score' => 20],
        ],
    ];

    /** Source: Keralam State Sahodaya Kalotsavam Manual 2026, Section IV — Items and Judgement Criteria. */
    private const DESCRIPTION_SUFFIX = ' (State Kalotsavam Manual 2026, Sec. IV)';

    public function run(): void
    {
        $sahodayas = Tenant::query()->where('type', 'sahodaya')->get();
        $succeeded = 0;
        $failed = [];

        foreach ($sahodayas as $sahodaya) {
            try {
                $sahodaya->run(function () use ($sahodaya): void {
                    $this->seedForTenant($sahodaya->id);
                });
                $succeeded++;
            } catch (\Throwable $e) {
                // Mirrors sahodaya:provision-databases' own convention (see docs/erp/24-LIVE_SERVER_DEPLOYMENT.md):
                // one Sahodaya with a broken/missing/unreachable tenant database must not abort seeding for
                // every other tenant — report it and move on.
                $failed[] = "{$sahodaya->name} ({$sahodaya->id}): {$e->getMessage()}";
            }
        }

        $this->command?->info("Seeded {$succeeded}/{$sahodayas->count()} Sahodaya tenant(s) with ".count(self::TEMPLATES).' default Kalotsavam rubric templates.');
        foreach ($failed as $line) {
            $this->command?->warn("  Skipped: {$line}");
        }
    }

    private function seedForTenant(string $tenantId): void
    {
        DB::transaction(function () use ($tenantId): void {
            $sortOrder = 0;

            foreach (self::TEMPLATES as $name => $criteria) {
                $template = FestScoringRubricTemplate::updateOrCreate(
                    ['tenant_id' => $tenantId, 'name' => $name],
                    [
                        'description' => $name.self::DESCRIPTION_SUFFIX,
                        'sort_order'  => $sortOrder,
                    ],
                );

                // Idempotent: only fill in criteria the template doesn't already have (by
                // label), so re-running this seeder never clobbers an admin's own edits to
                // an already-seeded template (e.g. a tweaked max_score).
                $existingLabels = $template->criteria()->pluck('label')->all();

                foreach ($criteria as $i => $row) {
                    if (in_array($row['label'], $existingLabels, true)) {
                        continue;
                    }

                    $template->criteria()->create([
                        'tenant_id'  => $tenantId,
                        'label'      => $row['label'],
                        'max_score'  => $row['max_score'],
                        'sort_order' => $i,
                    ]);
                }

                $sortOrder++;
            }
        });
    }
}
