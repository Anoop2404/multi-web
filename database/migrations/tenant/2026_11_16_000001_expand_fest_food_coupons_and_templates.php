<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Expand fest_food_coupons table
        if (Schema::hasTable('fest_food_coupons')) {
            Schema::table('fest_food_coupons', function (Blueprint $table) {
                if (! Schema::hasColumn('fest_food_coupons', 'qr_token')) {
                    $table->string('qr_token', 32)->nullable()->unique()->after('coupon_code');
                }
                if (! Schema::hasColumn('fest_food_coupons', 'sequence_no')) {
                    $table->unsignedInteger('sequence_no')->default(1)->after('coupon_code');
                }
                if (! Schema::hasColumn('fest_food_coupons', 'is_extra')) {
                    $table->boolean('is_extra')->default(false)->after('head_count');
                }
                if (! Schema::hasColumn('fest_food_coupons', 'batch_id')) {
                    $table->string('batch_id', 40)->nullable()->after('is_extra');
                }
            });

            // Allow coupon_code to be scoped per event_id rather than table-wide unique,
            // so each event can serialize starting from BF-0001, LN-0001, DN-0001 cleanly.
            try {
                Schema::table('fest_food_coupons', function (Blueprint $table) {
                    $table->dropUnique(['coupon_code']);
                });
            } catch (\Throwable $e) {
                try {
                    DB::statement('ALTER TABLE fest_food_coupons DROP CONSTRAINT IF EXISTS fest_food_coupons_coupon_code_unique');
                } catch (\Throwable $e2) {
                    // Ignore if already dropped
                }
            }

            try {
                Schema::table('fest_food_coupons', function (Blueprint $table) {
                    $table->unique(['event_id', 'coupon_code']);
                });
            } catch (\Throwable $e) {
                // Ignore if already exists
            }

            // Modify school_id to be nullable so admin can generate extra coupons not tied to a school.
            try {
                DB::statement('ALTER TABLE fest_food_coupons ALTER COLUMN school_id DROP NOT NULL');
            } catch (\Throwable $e) {
                // If already nullable or sqlite syntax differs, proceed gracefully
            }

            // Populate existing rows if any missing qr_token
            $existing = DB::table('fest_food_coupons')->whereNull('qr_token')->get();
            foreach ($existing as $row) {
                DB::table('fest_food_coupons')->where('id', $row->id)->update([
                    'qr_token' => strtoupper(\Illuminate\Support\Str::random(10)),
                ]);
            }
        }

        // 2. Add food_coupon_bg_image to fest_events table
        if (Schema::hasTable('fest_events') && ! Schema::hasColumn('fest_events', 'food_coupon_bg_image')) {
            Schema::table('fest_events', function (Blueprint $table) {
                $table->string('food_coupon_bg_image')->nullable()->after('food_host_school_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fest_food_coupons')) {
            Schema::table('fest_food_coupons', function (Blueprint $table) {
                $columns = ['qr_token', 'sequence_no', 'is_extra', 'batch_id'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('fest_food_coupons', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('fest_events') && Schema::hasColumn('fest_events', 'food_coupon_bg_image')) {
            Schema::table('fest_events', function (Blueprint $table) {
                $table->dropColumn('food_coupon_bg_image');
            });
        }
    }
};
