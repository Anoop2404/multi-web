<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_category_overrides', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->nullable()->after('is_hidden');
        });

        $this->deduplicateGlobalPrePrimary();
    }

    public function down(): void
    {
        Schema::table('class_category_overrides', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }

    private function deduplicateGlobalPrePrimary(): void
    {
        $preIds = DB::table('class_categories')
            ->whereNull('sahodaya_id')
            ->where('code', 'PRE')
            ->orderBy('id')
            ->pluck('id');

        if ($preIds->count() <= 1) {
            return;
        }

        $keepId = $preIds->first();
        $dropIds = $preIds->slice(1)->values()->all();

        DB::table('master_classes')->whereIn('class_category_id', $dropIds)->update([
            'class_category_id' => $keepId,
        ]);

        DB::table('class_category_overrides')->whereIn('class_category_id', $dropIds)->delete();
        DB::table('class_categories')->whereIn('id', $dropIds)->delete();
    }
};
