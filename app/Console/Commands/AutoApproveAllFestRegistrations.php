<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AutoApproveAllFestRegistrations extends Command
{
    protected $signature = 'fest:auto-approve-all {--sahodaya= : Optional Sahodaya tenant ID} {--school= : Optional school tenant ID}';

    protected $description = 'Set all events, heads, and areas to auto-approval and approve all existing pending registrations';

    public function handle(): int
    {
        return $this->call('fest:approve-registrations', [
            'event'       => 'all',
            '--sahodaya' => $this->option('sahodaya'),
            '--school'   => $this->option('school'),
            '--all'      => true,
            '--force'    => true,
        ]);
    }
}
