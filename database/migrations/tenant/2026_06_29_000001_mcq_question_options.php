<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mcq_questions', function (Blueprint $table) {
            if (! Schema::hasColumn('mcq_questions', 'options_json')) {
                $table->json('options_json')->nullable()->after('body_text');
            }
            if (! Schema::hasColumn('mcq_questions', 'correct_option_key')) {
                $table->string('correct_option_key', 10)->nullable()->after('options_json');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mcq_questions', function (Blueprint $table) {
            foreach (['correct_option_key', 'options_json'] as $col) {
                if (Schema::hasColumn('mcq_questions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
