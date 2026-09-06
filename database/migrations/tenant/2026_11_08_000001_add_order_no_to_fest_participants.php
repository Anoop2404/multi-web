<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fest_participants', function (Blueprint $table) {
            // Distinct from chest_no: this is a purely display-order sequence for the Mark
            // Entry page, assigned from that page itself (not Chest Numbers), and scoped
            // strictly to the single item being marked -- not shared across a sports head's
            // items the way chest_no can be. Uniqueness is enforced in
            // FestMarkEntryController::setOrderNo() at the item scope, not via a DB
            // constraint, since fest_participants has no item_id column of its own (item
            // only exists via the registration relation).
            $table->unsignedSmallInteger('order_no')->nullable()->after('chest_no');
        });
    }

    public function down(): void
    {
        Schema::table('fest_participants', function (Blueprint $table) {
            $table->dropColumn('order_no');
        });
    }
};
