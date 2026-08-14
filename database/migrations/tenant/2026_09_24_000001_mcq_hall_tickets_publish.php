<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mcq_exams', function (Blueprint $table) {
            $table->boolean('hall_tickets_published')->default(false)->after('next_hall_ticket_no');
            $table->timestamp('hall_tickets_published_at')->nullable()->after('hall_tickets_published');
            $table->unsignedBigInteger('hall_tickets_published_by_user_id')->nullable()->after('hall_tickets_published_at');
        });
    }

    public function down(): void
    {
        Schema::table('mcq_exams', function (Blueprint $table) {
            $table->dropColumn(['hall_tickets_published', 'hall_tickets_published_at', 'hall_tickets_published_by_user_id']);
        });
    }
};
