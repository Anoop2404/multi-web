<?php

namespace App\Console\Commands;

use App\Models\CertificateTemplate;
use App\Models\FestEvent;
use Illuminate\Console\Command;

class SeedParticipationCertificateTemplate extends Command
{
    protected $signature = 'fest:seed-participation-template {event : Parent (root) event id} {--force : Replace an existing active participation template for this event}';

    protected $description = 'Create the default participation certificate template (wording, items box, signatories) for a parent event such as the MCS Kalotsav';

    public function handle(): int
    {
        $event = FestEvent::find($this->argument('event'));
        if (! $event) {
            $this->error('Event not found.');

            return self::FAILURE;
        }

        $event = $event->rootEvent();

        $existing = CertificateTemplate::where('tenant_id', $event->tenant_id)
            ->where('event_type', 'fest')
            ->where('certificate_type', 'participation')
            ->where('event_id', $event->id)
            ->whereNull('item_id')
            ->where('is_active', true);

        if ($existing->exists() && ! $this->option('force')) {
            $this->warn("An active participation template already exists for \"{$event->title}\". Use --force to replace it.");

            return self::FAILURE;
        }
        $existing->update(['is_active' => false]);

        $template = CertificateTemplate::create([
            'tenant_id' => $event->tenant_id,
            'event_type' => 'fest',
            'event_id' => $event->id,
            'item_id' => null,
            'certificate_type' => 'participation',
            'title' => 'Certificate of Participation',
            'body' => CertificateTemplate::defaultParticipationBody(),
            'signatories' => [
                ['name' => '', 'designation' => 'President', 'signature_path' => null],
                ['name' => '', 'designation' => 'General Secretary', 'signature_path' => null],
            ],
            'dynamic_fields_json' => [],
            'is_active' => true,
        ]);

        $this->info("Template #{$template->id} created for \"{$event->title}\". Add signatory names, signatures and a logo/background under Certificate templates.");

        return self::SUCCESS;
    }
}
