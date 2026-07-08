<?php

namespace App\Console\Commands;

use App\Models\SchoolDocument;
use Illuminate\Console\Command;

class MarkSchoolDocumentsExpired extends Command
{
    protected $signature = 'erp:mark-school-documents-expired';

    protected $description = 'Mark approved school documents past valid_to as expired';

    public function handle(): int
    {
        $count = SchoolDocument::where('status', 'approved')
            ->whereNotNull('valid_to')
            ->whereDate('valid_to', '<', now()->toDateString())
            ->update(['status' => 'expired']);

        $this->info("Marked {$count} document(s) as expired.");

        return self::SUCCESS;
    }
}
