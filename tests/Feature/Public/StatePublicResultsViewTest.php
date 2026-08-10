<?php

namespace Tests\Feature\Public;

use App\Models\State\StateFestEvent;
use App\Models\State\StateFestParticipant;
use App\Models\State\StateFestRegistration;
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
            'status'           => 'published',
        ]);

        $reg = StateFestRegistration::create([
            'state_event_id' => $event->id,
            'school_id'      => 'sch-101',
            'school_name'    => 'Model HSS',
            'item_code'      => 'LM01',
            'status'         => 'approved',
        ]);

        StateFestParticipant::create([
            'registration_id' => $reg->id,
            'student_name'    => 'Public Winner',
            'chest_number'    => '105',
            'meta'            => ['position' => 1, 'grade' => 'A'],
        ]);

        $response = $this->get(route('state.public-results'));

        $response->assertStatus(200);
        $response->assertSee('State Final Kalotsavam 2026');
        $response->assertSee('Public Winner');
        $response->assertSee('Model HSS');
        $response->assertSee('105');
    }
}
