<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('school_classes', 'class_category_id') && ! Schema::hasColumn('school_classes', 'school_class_group_id')) {
            return;
        }

        if (! Schema::hasTable('school_class_groups')) {
            if (! Schema::hasColumn('school_classes', 'class_category_id')) {
                Schema::table('school_classes', function (Blueprint $table) {
                    $table->unsignedBigInteger('class_category_id')->nullable()->after('tenant_id');
                });
            }

            return;
        }

        if (! Schema::hasColumn('school_classes', 'class_category_id')) {
            Schema::table('school_classes', function (Blueprint $table) {
                $table->unsignedBigInteger('class_category_id')->nullable()->after('tenant_id');
            });
        }

        $central = DB::connection(config('tenancy.database.central_connection', 'central'));

        $categoryByLabel = $central->table('class_categories')
            ->whereNull('sahodaya_id')
            ->get()
            ->keyBy(fn ($row) => strtolower($row->label));

        $classes = DB::table('school_classes')
            ->join('school_class_groups', 'school_classes.school_class_group_id', '=', 'school_class_groups.id')
            ->select('school_classes.id', 'school_class_groups.name', 'school_classes.tenant_id')
            ->get();

        $schoolParents = $central->table('tenants')
            ->whereIn('id', $classes->pluck('tenant_id')->unique())
            ->pluck('parent_id', 'id');

        foreach ($classes as $class) {
            $label = strtolower($class->name);
            $categoryId = $categoryByLabel[$label]->id ?? null;

            if (! $categoryId) {
                $sahodayaId = $schoolParents[$class->tenant_id] ?? null;
                if ($sahodayaId) {
                    $categoryId = $central->table('class_categories')
                        ->where('sahodaya_id', $sahodayaId)
                        ->whereRaw('LOWER(label) = ?', [$label])
                        ->value('id');
                }
            }

            if (! $categoryId) {
                $categoryId = $categoryByLabel['primary']->id
                    ?? $central->table('class_categories')->whereNull('sahodaya_id')->orderBy('sort_order')->value('id');
            }

            DB::table('school_classes')->where('id', $class->id)->update([
                'class_category_id' => $categoryId,
            ]);
        }

        Schema::table('school_classes', function (Blueprint $table) {
            $table->dropForeign(['school_class_group_id']);
            $table->dropColumn('school_class_group_id');
        });

        Schema::dropIfExists('school_class_groups');
    }

    public function down(): void
    {
        Schema::create('school_class_groups', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('name');
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::table('school_classes', function (Blueprint $table) {
            $table->unsignedBigInteger('school_class_group_id')->nullable()->after('tenant_id');
            $table->foreign('school_class_group_id')->references('id')->on('school_class_groups')->nullOnDelete();
        });

        $tenantIds = DB::table('school_classes')->distinct()->pluck('tenant_id');
        foreach ($tenantIds as $tenantId) {
            $groupId = DB::table('school_class_groups')->insertGetId([
                'tenant_id'     => $tenantId,
                'name'          => 'General',
                'display_order' => 0,
                'is_active'     => true,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            DB::table('school_classes')
                ->where('tenant_id', $tenantId)
                ->update(['school_class_group_id' => $groupId]);
        }

        Schema::table('school_classes', function (Blueprint $table) {
            $table->dropForeign(['class_category_id']);
            $table->dropColumn('class_category_id');
        });
    }
};
