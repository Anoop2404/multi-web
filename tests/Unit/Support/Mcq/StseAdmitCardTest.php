<?php

namespace Tests\Unit\Support\Mcq;

use App\Support\Mcq\McqHallTicketDesign;
use Tests\TestCase;

class StseAdmitCardTest extends TestCase
{
    public function test_school_centre_is_exam_specific_and_overrides_the_sahodaya_venue(): void
    {
        $exam = new \App\Models\McqExam(['venue' => 'Sahodaya Central Hall']);
        $exam->id = 42;
        $school = \Mockery::mock(\App\Models\Tenant::class)->makePartial();
        $school->shouldReceive('getSetting')->with('mcq_exam_centre_42')->once()->andReturn(' School Hall B ');
        $this->assertSame('School Hall B', McqHallTicketDesign::centreForSchool($exam, $school));
        $this->assertSame('Sahodaya Central Hall', $exam->venue);
    }

    public function test_blank_school_centre_uses_sahodaya_and_missing_centres_stay_unset(): void
    {
        $exam = new \App\Models\McqExam(['venue' => 'Sahodaya Central Hall']);
        $exam->id = 42;
        $school = \Mockery::mock(\App\Models\Tenant::class)->makePartial();
        $school->shouldReceive('getSetting')->with('mcq_exam_centre_42')->andReturn(' ');
        $this->assertSame('Sahodaya Central Hall', McqHallTicketDesign::centreForSchool($exam, $school));
        $this->assertSame('Sahodaya Central Hall', McqHallTicketDesign::centreForSchool($exam, null));
        $exam->venue = null;
        $this->assertNull(McqHallTicketDesign::centreForSchool($exam, $school));
    }

    public function test_text_formatting_is_saved_rendered_and_restricted_to_safe_styles(): void
    {
        $design = McqHallTicketDesign::normalize(['layout'=>'stse_three_up', 'field_positions'=>[
            'name'=>['font_family'=>'Georgia','font_weight'=>'bold','font_style'=>'italic','text_align'=>'center','size'=>32],
        ], 'custom_fields'=>[['id'=>'custom_note','text'=>'Test','font_family'=>'Courier New','font_weight'=>'bold']]]);
        $html = view('mcq.partials.hall-ticket-card', ['design'=>$design,'sample'=>['participant_name'=>'Test Student']])->render();
        $this->assertStringContainsString('font-family:Georgia;font-weight:bold;font-style:italic;text-align:center', $html);
        $this->assertStringContainsString('font-family:Courier New;font-weight:bold', $html);
        $this->assertEquals(32, $design['field_positions']['name']['size']);
        $this->assertSame('Arial', McqHallTicketDesign::textStyle(['font_family'=>'Arial; color:red'])['font_family']);
    }

    public function test_optional_student_photo_and_custom_fields_render_and_escape_content(): void
    {
        $design = McqHallTicketDesign::normalize([
            'layout' => 'stse_three_up', 'show_student_photo' => true,
            'custom_fields' => [
                ['id'=>'custom_note','source'=>'text','label'=>'Note','text'=>'<script>bad()</script>'],
                ['id'=>'custom_school','source'=>'school_name','label'=>'School'],
            ],
        ]);
        $sample = ['photo_src'=>'/students/photo.png','school_name'=>'Test Academy'];
        $html = view('mcq.partials.hall-ticket-card', compact('design','sample'))->render();
        $this->assertStringContainsString('/students/photo.png', $html);
        $this->assertStringContainsString('School: Test Academy', $html);
        $this->assertStringContainsString('&lt;script&gt;bad()', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $design['show_student_photo'] = false;
        $design['custom_fields'] = [];
        $html = view('mcq.partials.hall-ticket-card', compact('design','sample'))->render();
        $this->assertStringNotContainsString('/students/photo.png', $html);
        $this->assertStringNotContainsString('School: Test Academy', $html);
    }

    public function test_custom_field_geometry_is_bounded_and_unknown_sources_are_rejected(): void
    {
        $design = McqHallTicketDesign::normalize(['custom_fields'=>[
            ['id'=>'custom_safe','source'=>'secret','x'=>90,'width'=>80,'size'=>100],
            ['id'=>'invalid id','source'=>'text'],
        ]]);
        $this->assertCount(1, $design['custom_fields']);
        $this->assertSame('text', $design['custom_fields'][0]['source']);
        $this->assertEquals(10, $design['custom_fields'][0]['width']);
        $this->assertEquals(72, $design['custom_fields'][0]['size']);
    }

    public function test_die_presets_fit_the_expected_number_of_cards_on_a4(): void
    {
        foreach ([[190,136,'portrait',2],[190,90,'portrait',3],[93,136,'portrait',4],[93,90,'portrait',6],[90,121.384615,'landscape',3]] as [$width,$height,$orientation,$count]) {
            $geometry = McqHallTicketDesign::printGeometry(McqHallTicketDesign::normalize([
                'card_width_mm' => $width, 'card_height_mm' => $height, 'page_orientation' => $orientation,
            ]));
            $this->assertSame($count, $geometry['per_page']);
            $this->assertLessThanOrEqual($orientation === 'portrait' ? 190 : 277, $geometry['columns'] * $geometry['width'] + ($geometry['columns'] - 1) * $geometry['gap']);
            $this->assertLessThanOrEqual($orientation === 'portrait' ? 277 : 190, $geometry['rows'] * $geometry['height'] + ($geometry['rows'] - 1) * $geometry['gap']);
        }
    }

    public function test_full_image_fit_preserves_proportions_and_stretch_fills_the_die(): void
    {
        $design = McqHallTicketDesign::normalize(['layout' => 'stse_three_up', 'card_width_mm' => 190, 'card_height_mm' => 90, 'page_orientation' => 'portrait']);
        $box = McqHallTicketDesign::artworkBox($design);
        $this->assertEqualsWithDelta(780 / 1052, $box['width'] / $box['height'], 0.0001);
        $this->assertEqualsWithDelta(90, $box['height'], 0.0001);
        $this->assertGreaterThan(0, $box['x']);
        $design['background_fit'] = 'stretch';
        $this->assertEquals(['x'=>0,'y'=>0,'width'=>190,'height'=>90], McqHallTicketDesign::artworkBox($design));
    }

    public function test_exam_design_takes_priority_over_assigned_and_shared_templates(): void
    {
        $exam = new \App\Models\McqExam([
            'hall_ticket_template_id' => 999,
            'settings_json' => [
                'hall_ticket_design_scope' => 'exam',
                'hall_ticket' => ['layout' => 'stse_three_up', 'field_positions' => ['name' => ['x' => 47]]],
            ],
        ]);
        $design = McqHallTicketDesign::fromExam($exam);
        $this->assertSame('stse_three_up', $design['layout']);
        $this->assertEquals(47, $design['field_positions']['name']['x']);
    }

    public function test_fields_stay_inside_the_card_and_signature_uses_saved_position(): void
    {
        $design = McqHallTicketDesign::normalize([
            'layout' => 'stse_three_up',
            'field_positions' => ['principal_signature' => ['x' => 80, 'y' => 82, 'width' => 90]],
        ]);
        $this->assertEquals(20, $design['field_positions']['principal_signature']['width']);
        $html = view('mcq.partials.hall-ticket-card', [
            'design' => $design,
            'sample' => ['principal_signature_src' => '/signature.png'],
        ])->render();
        $this->assertStringContainsString('left:80%;top:82%;width:20%', $html);
    }

    public function test_existing_designs_keep_their_layout_and_custom_positions_are_bounded(): void
    {
        $this->assertSame('standard', McqHallTicketDesign::normalize(null)['layout']);
        $this->assertSame('compact', McqHallTicketDesign::normalize(['layout' => 'compact'])['layout']);
        $design = McqHallTicketDesign::normalize([
            'layout' => 'custom_background',
            'field_positions' => ['name' => ['x' => -10, 'y' => 150, 'size' => 100]],
        ]);
        $this->assertSame('custom_background', $design['layout']);
        $this->assertEquals(0, $design['field_positions']['name']['x']);
        $this->assertEquals(95, $design['field_positions']['name']['y']);
        $this->assertEquals(72, $design['field_positions']['name']['size']);
    }

    public function test_stse_artwork_renders_student_fields_and_the_school_signature(): void
    {
        $html = view('mcq.partials.hall-ticket-card', [
            'design' => McqHallTicketDesign::normalize(['layout' => 'stse_three_up']),
            'sample' => [
                'participant_name' => '<script>bad()</script>',
                'hall_ticket_no' => '12345',
                'principal_signature_src' => '/school-signatures/school-a/signature.png',
            ],
        ])->render();

        $this->assertStringContainsString('stse-preliminary-background.png', $html);
        $this->assertStringContainsString('12345', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('/school-signatures/school-a/signature.png', $html);
    }

    public function test_signature_can_be_hidden_on_the_stse_template(): void
    {
        $html = view('mcq.partials.hall-ticket-card', [
            'design' => McqHallTicketDesign::normalize(['layout' => 'stse_three_up', 'show_signature' => false]),
            'sample' => ['principal_signature_src' => '/signature.png'],
        ])->render();

        $this->assertStringNotContainsString('/signature.png', $html);
    }
}
