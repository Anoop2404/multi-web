<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A reusable food-item template per event — define "Idli" once with a name/
        // description/default price, then assign it onto as many date+meal-type slots as
        // needed (fest_food_menu_items rows) instead of re-typing the same item on every
        // form submission. Purely a template: editing or deleting a catalog item never
        // touches menu items already created from it (see catalog_item_id below) —
        // consistent with how fest_food_menu_items itself already treats price changes as
        // non-retroactive for placed orders.
        if (! Schema::hasTable('fest_food_catalog_items')) {
            Schema::create('fest_food_catalog_items', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->unsignedBigInteger('event_id');
                $table->string('name');
                $table->text('description')->nullable();
                $table->decimal('default_price', 10, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
                $table->index(['event_id', 'is_active']);
            });
        }

        // Traceability only — which catalog item a scheduled menu item was assigned from,
        // so the catalog list can show "already on: Sep 1 breakfast, Sep 2 breakfast".
        // Nullable and nullOnDelete: a menu item never depends on its catalog origin
        // continuing to exist, matching how menu_item_id already works on order items.
        if (! Schema::hasColumn('fest_food_menu_items', 'catalog_item_id')) {
            Schema::table('fest_food_menu_items', function (Blueprint $table) {
                $table->unsignedBigInteger('catalog_item_id')->nullable()->after('event_id');
                $table->foreign('catalog_item_id')->references('id')->on('fest_food_catalog_items')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('fest_food_menu_items', 'catalog_item_id')) {
            Schema::table('fest_food_menu_items', function (Blueprint $table) {
                $table->dropForeign(['catalog_item_id']);
                $table->dropColumn('catalog_item_id');
            });
        }

        Schema::dropIfExists('fest_food_catalog_items');
    }
};
