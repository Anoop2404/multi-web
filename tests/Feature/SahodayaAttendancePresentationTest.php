<?php

namespace Tests\Feature;

use App\Models\Student;
use Illuminate\Support\Collection;
use Tests\TestCase;

class SahodayaAttendancePresentationTest extends TestCase
{
    public function test_sahodaya_student_photo_url_uses_sahodaya_authorized_route(): void
    {
        $student = new Student([
            'tenant_id' => 'school-1',
            'photo' => 'students/school-1/photo.jpg',
        ]);
        $student->id = 42;

        $url = $student->sahodayaPhotoUrl('sahodaya-1');

        $this->assertStringContainsString('/sahodaya-admin/sahodaya-1/students/42/photo', $url);
        $this->assertStringNotContainsString('/school-admin/', $url);
    }

    public function test_age_based_attendance_sheet_shows_photo_and_dob(): void
    {
        $html = $this->renderAttendanceSheet([
            '_uses_age' => true,
            '_uses_class' => false,
            'dob' => '10 Jun 2012',
            'class' => '8 A',
        ]);

        $this->assertStringContainsString('>DOB<', $html);
        $this->assertStringNotContainsString('>Class<', $html);
        $this->assertStringContainsString('10 Jun 2012', $html);
        $this->assertStringContainsString('data:image/jpeg;base64,photo', $html);
    }

    public function test_class_category_attendance_sheet_shows_photo_and_class(): void
    {
        $html = $this->renderAttendanceSheet([
            '_uses_age' => false,
            '_uses_class' => true,
            'dob' => '10 Jun 2012',
            'class' => '8 A',
        ]);

        $this->assertStringContainsString('>Class<', $html);
        $this->assertStringNotContainsString('>DOB<', $html);
        $this->assertStringContainsString('8 A', $html);
        $this->assertStringContainsString('data:image/jpeg;base64,photo', $html);
    }

    public function test_dompdf_attendance_header_is_in_the_required_order_without_literal_page_tokens(): void
    {
        $html = $this->renderAttendanceSheet([
            '_uses_age' => true,
            '_uses_class' => false,
            'dob' => '10 Jun 2012',
            'class' => '8 A',
        ], true);

        $sahodayaPosition = strpos($html, 'Demo Sahodaya');
        $eventPosition = strpos($html, 'Demo Fest', $sahodayaPosition);
        $itemPosition = strpos($html, 'Sample Item', $eventPosition);

        $this->assertIsInt($sahodayaPosition);
        $this->assertIsInt($eventPosition);
        $this->assertIsInt($itemPosition);
        $this->assertLessThan($eventPosition, $sahodayaPosition);
        $this->assertLessThan($itemPosition, $eventPosition);
        $this->assertStringNotContainsString('{PAGE_NUM}', $html);
        $this->assertStringNotContainsString('{PAGE_COUNT}', $html);
    }

    public function test_sixteen_students_fit_on_one_dompdf_page_section(): void
    {
        $rows = collect(range(1, 16))->map(fn ($number) => [
            'reference' => (string) (400 + $number),
            'name' => 'Student '.$number,
            'school' => 'Demo School',
            'item' => 'Sample Item',
            'photo_url' => null,
            'team_name' => null,
            'group_id' => null,
            '_uses_age' => true,
            '_uses_class' => false,
            'dob' => '10 Jun 2012',
            'class' => '8 A',
        ])->all();

        $html = view('fest.reports.attendance-sheet', [
            'event' => (object) ['title' => 'Demo Fest', 'event_type' => 'sports'],
            'sahodaya' => (object) ['name' => 'Demo Sahodaya'],
            'logo' => null,
            'rowsByItem' => new Collection(['Sample Item' => $rows]),
            'audience' => 'staff',
            'isPreview' => false,
            'singleItemName' => 'Sample Item',
            'isDomPdf' => true,
        ])->render();

        $this->assertSame(1, substr_count($html, 'class="report-header"'));
        $this->assertStringContainsString('>16<', $html);
    }

    /** @param array<string, mixed> $eligibility */
    private function renderAttendanceSheet(array $eligibility, bool $isDomPdf = false): string
    {
        $row = array_merge([
            'reference' => '101',
            'name' => 'Anu Student',
            'school' => 'Demo School',
            'item' => 'Sample Item',
            'photo_url' => 'data:image/jpeg;base64,photo',
            'team_name' => null,
            'group_id' => null,
        ], $eligibility);

        return view('fest.reports.attendance-sheet', [
            'event' => (object) ['title' => 'Demo Fest', 'event_type' => 'arts'],
            'sahodaya' => (object) ['name' => 'Demo Sahodaya'],
            'logo' => null,
            'rowsByItem' => new Collection(['Sample Item' => [$row]]),
            'audience' => 'staff',
            'isPreview' => false,
            'singleItemName' => 'Sample Item',
            'isDomPdf' => $isDomPdf,
        ])->render();
    }
}
