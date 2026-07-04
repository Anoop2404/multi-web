<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sahodaya_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('sahodaya_profiles', 'setup_wizard_complete')) {
                $table->boolean('setup_wizard_complete')->default(false)->after('prefixes_locked');
            }
            if (! Schema::hasColumn('sahodaya_profiles', 'setup_wizard_dismissed_at')) {
                $table->timestamp('setup_wizard_dismissed_at')->nullable()->after('setup_wizard_complete');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sahodaya_profiles', function (Blueprint $table) {
            foreach (['setup_wizard_complete', 'setup_wizard_dismissed_at'] as $col) {
                if (Schema::hasColumn('sahodaya_profiles', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
