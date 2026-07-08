<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\FestEvent;
use App\Models\FestStateProgram;
use App\Models\StateDomain;
use App\Services\State\FestStateQualifierPayloadBuilder;
use App\Services\State\StateSubmissionClient;
use Illuminate\Http\Request;

class StateQualifierSubmissionController extends SahodayaAdminController
{
    public function store(
        Request $request,
        string $tenantId,
        FestEvent $event,
        FestStateQualifierPayloadBuilder $builder,
        StateSubmissionClient $client,
    ) {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if(! $event->state_program_id, 422, 'Event is not linked to a state program.');

        $program = FestStateProgram::findOrFail($event->state_program_id);
        abort_if(! $program->state_domain_id, 422, 'State program has no state domain configured.');

        $domain = StateDomain::findOrFail($program->state_domain_id);

        $outbox = $builder->enqueue($program, $event, $this->sahodaya->id, $request->user()?->id);
        $client->send($outbox, $domain);

        return back()->with(
            'success',
            $outbox->status === 'completed'
                ? 'Qualifiers submitted to State successfully.'
                : 'Qualifier submission queued; check outbox for status.'
        );
    }
}
