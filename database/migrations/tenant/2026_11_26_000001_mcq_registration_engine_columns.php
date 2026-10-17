<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Fields the exam engine reports back per candidate (exam-engine/CONTRACT.md §6, §10). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mcq_registrations', function (Blueprint $table) {
            foreach ([
                'checked_in_at'      => fn () => $table->timestamp('checked_in_at')->nullable()->after('started_at'),
                'submit_mode'        => fn () => $table->string('submit_mode', 10)->nullable()->after('submitted_at'),
                'engine_receipt'     => fn () => $table->string('engine_receipt', 40)->nullable()->after('submit_mode'),
                'extra_time_minutes' => fn () => $table->unsignedSmallInteger('extra_time_minutes')->default(0)->after('engine_receipt'),
                'device_info'        => fn () => $table->json('device_info')->nullable()->after('extra_time_minutes'),
                'last_ip'            => fn () => $table->string('last_ip', 45)->nullable()->after('device_info'),
            ] as $column => $add) {
                if (! Schema::hasColumn('mcq_registrations', $column)) {
                    $add();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('mcq_registrations', function (Blueprint $table) {
            foreach (['last_ip', 'device_info', 'extra_time_minutes', 'engine_receipt', 'submit_mode', 'checked_in_at'] as $column) {
                if (Schema::hasColumn('mcq_registrations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
