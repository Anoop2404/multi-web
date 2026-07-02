<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('audit_logs', 'category')) {
                $table->string('category', 32)->default('system')->after('user_id')->index();
            }
        });

        if (Schema::hasColumn('audit_logs', 'category')) {
            DB::table('audit_logs')->orderBy('id')->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    if ($row->category && $row->category !== 'system') {
                        continue;
                    }
                    DB::table('audit_logs')->where('id', $row->id)->update([
                        'category' => \App\Support\AuditLogCatalog::categoryForAction($row->action),
                    ]);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            if (Schema::hasColumn('audit_logs', 'category')) {
                $table->dropColumn('category');
            }
        });
    }
};
