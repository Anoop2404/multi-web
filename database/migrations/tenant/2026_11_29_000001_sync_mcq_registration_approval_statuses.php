<?php

use App\Models\McqExam;
use App\Services\Mcq\McqRegistrationApprovalService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mcq_exams') || ! Schema::hasTable('mcq_registrations')) {
            return;
        }

        $service = app(McqRegistrationApprovalService::class);
        McqExam::query()->each(function (McqExam $exam) use ($service) {
            $service->syncExamApprovalStatuses($exam);
        });
    }

    public function down(): void
    {
        // Data migration, non-reversible
    }
};
