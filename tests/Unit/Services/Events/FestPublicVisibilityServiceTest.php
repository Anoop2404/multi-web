<?php

namespace Tests\Unit\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Services\Events\FestPublicVisibilityService;
use Tests\TestCase;

class FestPublicVisibilityServiceTest extends TestCase
{
    private FestPublicVisibilityService $visibility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->visibility = app(FestPublicVisibilityService::class);
    }

    public function test_district_on_stage_hides_name_until_results_published(): void
    {
        $participant = $this->makeParticipant('district', 'on_stage', false);

        $this->assertFalse($this->visibility->showParticipantName($participant->registration->event, $participant));
        $this->assertFalse($this->visibility->allowNameSearch($participant->registration->event));

        $participant->registration->event->results_published = true;

        $this->assertTrue($this->visibility->showParticipantName($participant->registration->event, $participant));
    }

    public function test_school_level_shows_names_during_event(): void
    {
        $participant = $this->makeParticipant('school', 'on_stage', false);

        $this->assertTrue($this->visibility->showParticipantName($participant->registration->event, $participant));
        $this->assertTrue($this->visibility->allowNameSearch($participant->registration->event));
    }

    public function test_sports_shows_names_and_marks_before_publish(): void
    {
        $participant = $this->makeParticipant('district', 'on_stage', false, 'sports');

        $this->assertTrue($this->visibility->showParticipantName($participant->registration->event, $participant));
        $this->assertTrue($this->visibility->showIndividualMarks($participant->registration->event));
    }

    public function test_off_stage_uses_level_registration_reference(): void
    {
        $participant = $this->makeParticipant('district', 'off_stage', false);
        $participant->level_registration_number = 'D-0042';
        $participant->chest_no = 7;

        $this->assertSame('D-0042', $this->visibility->publicReference($participant->registration->event, $participant));
    }

    private function makeParticipant(
        string $levelRound,
        string $stageType,
        bool $resultsPublished,
        string $eventType = 'kalolsavam',
    ): FestParticipant {
        $event = new FestEvent([
            'level_round'       => $levelRound,
            'event_type'        => $eventType,
            'results_published' => $resultsPublished,
        ]);

        $item = new FestEventItem(['stage_type' => $stageType]);
        $registration = new FestRegistration();
        $registration->setRelation('event', $event);
        $registration->setRelation('item', $item);

        $participant = new FestParticipant(['chest_no' => 12]);
        $participant->setRelation('registration', $registration);

        return $participant;
    }
}
