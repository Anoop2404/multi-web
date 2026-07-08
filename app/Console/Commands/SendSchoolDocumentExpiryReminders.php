<?php

namespace App\Console\Commands;

use App\Models\SchoolDocument;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Console\Command;

class SendSchoolDocumentExpiryReminders extends Command
{
    protected $signature = 'erp:school-document-expiry-reminders';

    protected $description = 'Email school admins when compliance documents expire in 30 or 7 days';

    public function handle(): int
    {
        $windows = [30, 7];
        $sent = 0;

        foreach ($windows as $days) {
            $target = now()->addDays($days)->toDateString();

            SchoolDocument::where('status', 'approved')
                ->whereDate('valid_to', $target)
                ->with('documentType')
                ->chunkById(100, function ($documents) use ($days, &$sent) {
                    foreach ($documents as $document) {
                        if ($this->notifySchool($document, $days)) {
                            $sent++;
                        }
                    }
                });
        }

        $this->info("Sent {$sent} document expiry reminder(s).");

        return self::SUCCESS;
    }

    private function notifySchool(SchoolDocument $document, int $days): bool
    {
        $school = Tenant::find($document->school_id);
        if (! $school) {
            return false;
        }

        $admin = User::query()
            ->where('tenant_id', $school->id)
            ->whereHas('roles', fn ($q) => $q->where('name', 'school_admin'))
            ->first();

        if (! $admin) {
            return false;
        }

        $typeName = $document->documentType?->name ?? 'Document';
        $title = "Document expiring in {$days} days";
        $body = "{$typeName} expires on {$document->valid_to->toDateString()}. Please upload a renewed copy.";

        app(NotificationService::class)->notify(
            $admin,
            $title,
            $body,
            "/school-admin/{$school->id}/documents",
            ['in_app', 'email'],
        );

        return true;
    }
}
