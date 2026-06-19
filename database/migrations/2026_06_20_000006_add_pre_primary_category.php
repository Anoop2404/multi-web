<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        if (! DB::table('class_categories')->whereNull('sahodaya_id')->where('code', 'PRE')->exists()) {
            return;
        }

        $preId = DB::table('class_categories')->whereNull('sahodaya_id')->where('code', 'PRE')->orderBy('id')->value('id');

        if (! $preId) {
            return;
        }

        $order = (int) DB::table('master_classes')->whereNull('sahodaya_id')->max('display_order');
        $existingNames = DB::table('master_classes')->whereNull('sahodaya_id')->pluck('name')->all();

        foreach (['LKG', 'UKG'] as $name) {
            if (in_array($name, $existingNames, true)) {
                continue;
            }

            DB::table('master_classes')->insert([
                'sahodaya_id'       => null,
                'class_category_id' => $preId,
                'name'              => $name,
                'display_order'     => ++$order,
                'is_active'         => true,
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('master_classes')->whereNull('sahodaya_id')->whereIn('name', ['LKG', 'UKG'])->delete();
        DB::table('class_categories')->whereNull('sahodaya_id')->where('code', 'PRE')->delete();
    }
};
