<?php

namespace Tests\Feature\Events;

use App\Jobs\RenderContinuousDieIdCardsJob;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\SahodayaProfile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Services\Events\FestIdCardService;
use App\Support\TenantStorage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The continuous die run renders its card sheets in chunks, now streamed through
 * PdfGenerator::renderEach() (several chunks in flight against the converter at once)
 * instead of one after another. Chunks can finish in any order, so the merged master PDF
 * must still come out in chunk order, with every chunk's pages.
 */
class RenderContinuousDieIdCardsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_chunks_render_through_the_converter_and_merge_back_in_order(): void
    {
        Storage::fake(TenantStorage::SHARED_DISK);
        config(['services.pdf_converter.url' => 'https://pdf.example.test/render', 'services.pdf_converter.concurrency' => 3]);

        // Each chunk's PDF gets its own page size, so the merged file's page sizes reveal
        // which chunk each page came from — the first chunk's sheet starts with student 00.
        Http::fake(function ($request) {
            $firstChunk = str_contains($request['html'], 'Die Student 00');
            $pdf = Pdf::loadHTML('<p>chunk</p>')->setPaper($firstChunk ? 'a4' : 'a5', 'landscape')->output();

            return Http::response($pdf, 200);
        });

        $sahodaya = Tenant::create([
            'id' => (string) Str::uuid(), 'type' => 'sahodaya', 'name' => 'Die Sahodaya',
            'domain' => Str::uuid().'.test', 'is_active' => true,
        ]);
        SahodayaProfile::create(['tenant_id' => $sahodaya->id, 'prefix' => 'DI', 'student_data_mode' => 'counts_only']);
        $event = FestEvent::create([
            'tenant_id' => $sahodaya->id, 'title' => 'Die Kalotsav', 'event_type' => 'kalotsavam', 'status' => 'published',
        ]);
        $school = Tenant::create(['id' => (string) Str::uuid(), 'type' => 'school', 'name' => 'Die School', 'sahodaya_id' => $sahodaya->id]);
        $class = SchoolClass::create(['tenant_id' => $school->id, 'name' => 'Class 10']);
        $item = FestEventItem::create(['event_id' => $event->id, 'title' => 'Essay Writing', 'is_enabled' => true]);

        // 4 cards per sheet x 10 sheets = 40 cards per chunk, so 45 students make two chunks.
        foreach (range(0, 44) as $i) {
            $student = Student::create([
                'tenant_id' => $school->id, 'school_class_id' => $class->id,
                'name' => sprintf('Die Student %02d', $i), 'gender' => 'male', 'reg_no' => 'DIE/'.$i,
            ]);
            $registration = FestRegistration::create([
                'event_id' => $event->id, 'school_id' => $school->id, 'item_id' => $item->id, 'status' => 'approved',
            ]);
            FestParticipant::create(['registration_id' => $registration->id, 'student_id' => $student->id, 'participant_role' => 'performer']);
        }

        (new RenderContinuousDieIdCardsJob($sahodaya->id, $event->id))->handle(app(FestIdCardService::class));

        $status = TenantSetting::where('tenant_id', $sahodaya->id)->where('key', "fest_die_render_event_{$event->id}")->first()?->value;
        $this->assertSame('completed', $status['status'] ?? null, $status['error'] ?? 'no status');
        $this->assertSame(2, $status['total_chunks']);
        $this->assertMatchesRegularExpression(
            '#/id-cards/die/full-continuous-run-[a-f0-9]{16}\.pdf$#',
            $status['file_path'],
        );
        Http::assertSentCount(2);

        $merged = Storage::disk(TenantStorage::SHARED_DISK)->get($status['file_path']);
        preg_match_all('#/MediaBox\s*\[\s*0\s+0\s+([\d.]+)\s+([\d.]+)\s*\]#', $merged, $boxes, PREG_SET_ORDER);
        $widths = array_map(fn ($box) => (int) round((float) $box[1]), $boxes);
        // FPDI writes one MediaBox per page, in page order: chunk 1 (A4 landscape, ~842pt
        // wide) first, then chunk 2 (A5 landscape, ~595pt wide).
        $this->assertNotEmpty($widths);
        $this->assertSame(842, $widths[0]);
        $this->assertSame(595, end($widths));
    }
}
