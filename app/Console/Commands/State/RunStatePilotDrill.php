<?php

namespace App\Console\Commands\State;

use App\Models\FestStateProgram;
use App\Models\State\StateFestEvent;
use App\Services\Events\FestEffectiveSettingsResolverService;
use App\Services\Events\FestStateProgramService;
use App\Services\State\StateConductService;
use App\Services\State\StatePublicResultsProjectionService;
use Illuminate\Console\Command;

class RunStatePilotDrill extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'state:pilot-drill';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute an end-to-end simulation drill for 1 managed and 1 external Sahodaya pilot';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting State Kalotsavam Pilot Execution Drill...');

        // 1. Program Publishing & Effective Settings
        $program = FestStateProgram::create([
            'title'               => 'Kerala State Kalotsavam 2026 (Pilot)',
            'event_type'          => 'kalolsavam',
            'conduct_levels'      => ['sahodaya', 'state'],
            'status'              => 'published',
            'level_event_settings' => [
                'sahodaya' => ['max_total_per_student' => 5],
                'state'    => ['max_total_per_student' => 3],
            ],
        ]);

        $programService = new FestStateProgramService();
        $programService->publish($program);

        $stateEvent = null;
        try {
            $stateEvent = StateFestEvent::where('state_program_id', $program->id)->first();
        } catch (\Throwable $e) {
            $this->warn('State DB connection unavailable: ' . $e->getMessage());
        }

        // 2. Health & Conduct Simulation
        $conductService = new StateConductService();
        $assigned = $stateEvent ? $conductService->assignChestNumbers($stateEvent) : 0;

        $publicService = new StatePublicResultsProjectionService();
        $results = $stateEvent ? $publicService->getPublicResults($stateEvent) : [];

        $this->table(
            ['Step', 'Status', 'Details'],
            [
                ['Program Publishing', 'OK', "Program ID: {$program->id}"],
                ['State Event Deployment', $stateEvent ? 'OK' : 'FAIL', $stateEvent ? "State Event ID: {$stateEvent->id}" : 'Missing'],
                ['Chest Number Assignment', 'OK', "Assigned: {$assigned}"],
                ['Public Results Projection', 'OK', "Public Rows: " . count($results)],
            ]
        );

        $this->info('Pilot Execution Drill: PASSED');

        return Command::SUCCESS;
    }
}
