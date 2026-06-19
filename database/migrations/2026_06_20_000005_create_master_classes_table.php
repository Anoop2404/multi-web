<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_classes', function (Blueprint $table) {
            $table->id();
            $table->string('sahodaya_id')->nullable();
            $table->foreign('sahodaya_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('class_category_id');
            $table->foreign('class_category_id')->references('id')->on('class_categories')->cascadeOnDelete();
            $table->string('name', 50);
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['sahodaya_id', 'name']);
        });

        $this->seedDefaultTemplates();
    }

    public function down(): void
    {
        Schema::dropIfExists('master_classes');
    }

    private function seedDefaultTemplates(): void
    {
        if (DB::table('master_classes')->exists()) {
            return;
        }

        $categories = DB::table('class_categories')
            ->whereNull('sahodaya_id')
            ->pluck('id', 'code');

        if ($categories->isEmpty()) {
            return;
        }

        $now = now();
        $rows = [];
        $order = 0;

        $ranges = [
            'PRE'   => ['LKG', 'UKG'],
            'PRY'   => range(1, 5),
            'UP'    => range(6, 8),
            'SEC'   => range(9, 10),
            'SrSEC' => range(11, 12),
        ];

        foreach ($ranges as $code => $names) {
            $categoryId = $categories[$code] ?? null;
            if (! $categoryId) {
                continue;
            }

            foreach ($names as $name) {
                $rows[] = [
                    'sahodaya_id'         => null,
                    'class_category_id'   => $categoryId,
                    'name'                => (string) $name,
                    'display_order'       => ++$order,
                    'is_active'           => true,
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ];
            }
        }

        if ($rows) {
            DB::table('master_classes')->insert($rows);
        }
    }
};
