<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_registrations', function (Blueprint $table) {
            $table->string('rejection_reason', 500)->nullable()->after('status');
            $table->timestamp('rejected_at')->nullable()->after('rejection_reason');
            $table->foreignId('rejected_by_user_id')->nullable()->after('rejected_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fest_registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rejected_by_user_id');
            $table->dropColumn(['rejection_reason', 'rejected_at']);
        });
    }
};
