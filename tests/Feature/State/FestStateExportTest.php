<?php

namespace Tests\Feature\State;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\State\FestStateExportService;
use App\Services\State\FestStateSlotCollectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class FestStateExportTest extends TestCase
{
    use RefreshDatabase;

    private FestStateProgram $program;

    private FestEvent $hubEvent;

    private FestEventItem $item;

    private FestStateProgramItem $stateItem;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('state:migrate');

        Tenant::create(['id' => 'sahodaya-1', 'name' => 'Sahodaya One', 'type' => 'sahodaya']);
        Tenant::create(['id' => 'school-1', 'name' => 'School One', 'type' => 'school', 'parent_id' => 'sahodaya-1']);
        Tenant::create(['id' => 'school-2', 'name' => 'School Two', 'type' => 'school', 'parent_id' => 'sahodaya-1']);

        $this->program = FestStateProgram::create([
            'title' => 'Kerala State Kalotsavam 2026',
            'event_type' => 'kalolsavam',
            'conduct_levels' => ['sahodaya', 'state'],
            'status' => 'published',
        ]);

        $this->stateItem = FestStateProgramItem::create([
            'state_program_id' => $this->program->id,
            'title' => 'Light Music',
            'item_code' => 'LM01',
            'category' => 'music',
            'class_group' => 'category_2',
            'qualify_count' => 2,
            'participant_type' => 'individual',
        ]);

        $this->hubEvent = FestEvent::create([
            'tenant_id' => 'sahodaya-1',
            'title' => 'Sahodaya Kalotsavam 2026',
            'event_type' => 'kalolsavam',
            'level_round' => 'sahodaya',
            'state_program_id' => $this->program->id,
            'results_published' => true,
            'status' => 'published',
        ]);

        $this->item = FestEventItem::create([
            'event_id' => $this->hubEvent->id,
            'state_program_item_id' => $this->stateItem->id,
            'title' => 'Light Music',
            'category' => 'music',
            'item_code' => 'LM01',
        ]);

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = User::firstOrCreate(
            ['email' => 'admin-export-test@sahodaya.test'],
            [
                'name' => 'Sahodaya Admin',
                'username' => 'sahodaya_export_admin',
                'password' => 'password',
                'tenant_id' => 'sahodaya-1',
                'email_verified_at' => now(),
            ]
        );
        $this->admin->syncRoles(['sahodaya_admin']);
    }

    private function winnerWithPhoto(string $name, int $position, float $score, string $schoolId, ?string $fakePhotoContent = null): FestMark
    {
        $class = SchoolClass::firstOrCreate(['tenant_id' => $schoolId, 'name' => 'Class 6']);

        $photoPath = null;
        if ($fakePhotoContent !== null) {
            $photoPath = "students/{$schoolId}/test_photo_".\Str::slug($name).".jpg";
            Storage::disk('public')->put($photoPath, $fakePhotoContent);
        }

        $student = Student::create([
            'name' => $name,
            'tenant_id' => $schoolId,
            'school_class_id' => $class->id,
            'roll_number' => '101',
            'admission_number' => 'ADM/2026/01',
            'photo' => $photoPath,
        ]);

        $registration = FestRegistration::create([
            'event_id' => $this->hubEvent->id,
            'item_id' => $this->item->id,
            'school_id' => $schoolId,
            'status' => 'approved',
        ]);

        $participant = FestParticipant::create([
            'registration_id' => $registration->id,
            'student_id' => $student->id,
        ]);

        return FestMark::create([
            'event_id' => $this->hubEvent->id,
            'item_id' => $this->item->id,
            'participant_id' => $participant->id,
            'position' => $position,
            'score' => $score,
            'grade' => 'A',
        ]);
    }

    public function test_export_service_gathers_data_with_matching_photo_names(): void
    {
        $this->winnerWithPhoto('Anoop S', 1, 95, 'school-1', 'fake-jpeg-binary-anoop');
        $this->winnerWithPhoto('Rahul M', 2, 90, 'school-2', null); // No photo

        app(FestStateSlotCollectionService::class)->open($this->program, $this->hubEvent, $this->admin);

        /** @var FestStateExportService $export */
        $export = app(FestStateExportService::class);
        $data = $export->gatherData($this->program, $this->hubEvent);

        $this->assertSame(2, $data['stats']['total_qualifiers']);
        $this->assertSame(1, $data['stats']['photos_count']);
        $this->assertSame(1, $data['stats']['missing_photos']);

        // First row (Anoop) has photo attached
        $row1 = $data['rows'][0];
        $this->assertSame('LM01', $row1[1]);
        $this->assertSame('Light Music', $row1[2]);
        $this->assertSame('Rank 1', $row1[7]);
        $this->assertSame('Anoop S', $row1[8]);
        $this->assertStringStartsWith('student_photos/', $row1[14]);
        $this->assertStringContainsString('anoop_s', strtolower($row1[14]));
        $this->assertSame('Attached', $row1[15]);

        // Second row (Rahul) has no photo
        $row2 = $data['rows'][1];
        $this->assertSame('Rahul M', $row2[8]);
        $this->assertSame('—', $row2[14]);
        $this->assertSame('No photo uploaded', $row2[15]);

        // Photo file in list
        $this->assertCount(1, $data['photos']);
        $this->assertSame('fake-jpeg-binary-anoop', $data['photos'][0]['bytes']);
        $this->assertSame($row1[14], $data['photos'][0]['filename']);
    }

    public function test_export_zip_generates_valid_zip_with_excel_and_photos(): void
    {
        $this->winnerWithPhoto('Anoop S', 1, 95, 'school-1', 'fake-jpeg-binary-anoop');
        $this->winnerWithPhoto('Rahul M', 2, 90, 'school-2', 'fake-jpeg-binary-rahul');

        app(FestStateSlotCollectionService::class)->open($this->program, $this->hubEvent, $this->admin);

        /** @var FestStateExportService $export */
        $export = app(FestStateExportService::class);
        $zipPath = $export->generateZip($this->program, $this->hubEvent);

        $this->assertFileExists($zipPath);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath));

        // Check Excel spreadsheet exists inside ZIP
        $foundXlsx = false;
        $foundReadme = false;
        $photoFiles = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_ends_with($name, '.xlsx')) {
                $foundXlsx = true;
            }
            if ($name === 'README.txt') {
                $foundReadme = true;
            }
            if (str_starts_with($name, 'student_photos/')) {
                $photoFiles[] = $name;
            }
        }

        $this->assertTrue($foundXlsx, 'Excel file must exist inside ZIP');
        $this->assertTrue($foundReadme, 'README.txt must exist inside ZIP');
        $this->assertCount(2, $photoFiles, 'Both student photos must be inside student_photos/ folder');

        $zip->close();
        @unlink($zipPath);
    }

    public function test_export_zip_http_endpoint_downloads_zip_archive(): void
    {
        $this->winnerWithPhoto('Anoop S', 1, 95, 'school-1', 'fake-jpeg-binary');
        app(FestStateSlotCollectionService::class)->open($this->program, $this->hubEvent, $this->admin);

        $response = $this->actingAs($this->admin)
            ->get("/sahodaya-admin/sahodaya-1/events/{$this->hubEvent->id}/state-slot-collection/export-zip");

        $response->assertOk();
        $this->assertSame('application/zip', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('state-nominations', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_export_excel_http_endpoint_downloads_xlsx_file(): void
    {
        $this->winnerWithPhoto('Anoop S', 1, 95, 'school-1', 'fake-jpeg-binary');
        app(FestStateSlotCollectionService::class)->open($this->program, $this->hubEvent, $this->admin);

        $response = $this->actingAs($this->admin)
            ->get("/sahodaya-admin/sahodaya-1/events/{$this->hubEvent->id}/state-slot-collection/export-excel");

        $response->assertOk();
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('Content-Disposition'));
    }
}
