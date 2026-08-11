<?php

namespace Tests\Feature\Public;

use App\Models\State\StateFestEvent;
use App\Models\State\StateFestParticipant;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateFestMark;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class StatePublicResultsViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('state:migrate');
    }

    public function test_public_state_results_page_renders_successfully(): void
    {
        $event = StateFestEvent::create([
            'state_program_id' => '019fea66-9b8d-7361-9828-1f6bbacaf36e',
            'name'             => 'State Final Kalotsavam 2026',
            'status'           => 'completed',
            'results_published'=> true,
        ]);

        $reg = StateFestRegistration::create([
            'state_event_id' => $event->id,
            'school_id'      => 'sch-101',
            'school_name'    => 'Model HSS',
            'item_code'      => 'LM01',
            'status'         => 'approved',
        ]);

        $participant = StateFestParticipant::create([
            'state_event_id'  => $event->id,
            'registration_id' => $reg->id,
            'student_name'    => 'Public Winner',
            'chest_number'    => '105',
            'meta'            => ['qualifier_position' => 2, 'qualifier_grade' => 'B'],
        ]);

        StateFestMark::create([
            'state_event_id' => $event->id,
            'registration_id' => $reg->id,
            'participant_id' => $participant->id,
            'score' => 91,
            'grade' => 'A',
            'position' => 1,
            'status' => 'published',
        ]);

        $response = $this->get(route('state.public-results'));

        $response->assertStatus(200);
        $response->assertSee('State Final Kalotsavam 2026');
        $response->assertSee('Public Winner');
        $response->assertSee('Model HSS');
        $response->assertSee('105');
    }

    public function test_public_page_never_exposes_unpublished_state_results(): void
    {
        StateFestEvent::create([
            'state_program_id' => '019fea66-9b8d-7361-9828-1f6bbacaf36e',
            'name' => 'Unpublished State Final',
            'status' => 'published',
            'results_published' => false,
        ]);

        $this->get(route('state.public-results'))
            ->assertOk()
            ->assertDontSee('Unpublished State Final')
            ->assertSee('No certified public results published yet.');
    }
}
