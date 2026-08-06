<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-event default for who food payments are payable to. Most events collect
        // centrally to the Sahodaya; some are run by a host school that caters the event
        // itself and should be paid directly instead.
        if (! Schema::hasColumn('fest_events', 'food_payee_type')) {
            Schema::table('fest_events', function (Blueprint $table) {
                $table->string('food_payee_type', 20)->default('sahodaya')->after('fee_amount');
                $table->string('food_host_school_id')->nullable()->after('food_payee_type');
            });
        }

        // Per-event, per-day food menu. Distinct from the older headcount-only
        // fest_catering_orders / fest_food_coupons flow (App\Models\FestCateringOrder,
        // FestFoodCoupon) which has no itemized menu or pricing. This is a new, separate
        // module — schools order specific priced items instead of just a headcount.
        if (! Schema::hasTable('fest_food_menu_items')) {
            Schema::create('fest_food_menu_items', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->unsignedBigInteger('event_id');
                $table->date('menu_date');
                $table->string('meal_type', 30); // breakfast, lunch, dinner, snacks, tea, other
                $table->string('name');
                $table->text('description')->nullable();
                $table->decimal('price', 10, 2)->default(0);
                $table->boolean('is_available')->default(true);
                // Optional per-school cap on quantity for this item (e.g. limited dessert stock).
                $table->unsignedInteger('max_per_school')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
                $table->index(['event_id', 'menu_date', 'meal_type']);
            });
        }

        // One running bill per school per event — a tab that accumulates order-item lines
        // and payments, rather than a single point-in-time invoice. Deliberately kept
        // separate from fest_event_invoices (registration/participation fee billing).
        if (! Schema::hasTable('fest_food_bills')) {
            Schema::create('fest_food_bills', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id');
                $table->unsignedBigInteger('event_id');
                $table->string('school_id');
                $table->string('status', 20)->default('open'); // open, settled, cancelled
                // Informational only — payment can be recorded at any time regardless of
                // this value; both prepaid-in-advance and cash-on-site are supported.
                $table->string('payment_mode', 20)->default('prepaid'); // prepaid, cash_on_site, mixed
                // Who the food payment is actually payable to for this event. Most Sahodayas
                // collect centrally, but some fests are run by a host school that handles its
                // own catering and should receive the money directly. Snapshotted from the
                // event's food_payee_type/food_host_school_id at bill-creation time so a later
                // change to the event setting doesn't rewrite who an already-open bill is
                // payable to.
                $table->string('payee_type', 20)->default('sahodaya'); // sahodaya, host_school
                $table->string('host_school_id')->nullable();
                $table->decimal('amount_total', 12, 2)->default(0);
                $table->decimal('amount_paid', 12, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamp('settled_at')->nullable();
                $table->unsignedBigInteger('settled_by_user_id')->nullable();
                $table->timestamps();

                $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
                $table->unique(['event_id', 'school_id']);
            });
        }

        // Order line items — quantity of a menu item a school has ordered. Name/price are
        // snapshotted at order time so later menu price edits don't retroactively change
        // an already-placed order.
        if (! Schema::hasTable('fest_food_order_items')) {
            Schema::create('fest_food_order_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('bill_id');
                $table->unsignedBigInteger('menu_item_id')->nullable();
                $table->date('menu_date');
                $table->string('meal_type', 30);
                $table->string('item_name');
                $table->decimal('unit_price', 10, 2);
                $table->unsignedInteger('quantity');
                $table->decimal('line_total', 12, 2);
                $table->unsignedBigInteger('ordered_by_user_id')->nullable();
                $table->timestamps();

                $table->foreign('bill_id')->references('id')->on('fest_food_bills')->cascadeOnDelete();
                $table->foreign('menu_item_id')->references('id')->on('fest_food_menu_items')->nullOnDelete();
                $table->index(['bill_id', 'menu_date', 'meal_type']);
            });
        }

        // Payments recorded against a bill — supports partial/multiple payments so both a
        // single prepaid settlement and several cash-on-site collections work the same way.
        if (! Schema::hasTable('fest_food_payments')) {
            Schema::create('fest_food_payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('bill_id');
                $table->decimal('amount', 12, 2);
                $table->string('payment_mode', 20)->default('cash'); // cash, upi, bank_transfer, other
                $table->string('receipt_number')->nullable();
                $table->unsignedBigInteger('received_by_user_id')->nullable();
                $table->timestamp('received_at')->useCurrent();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('bill_id')->references('id')->on('fest_food_bills')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_food_payments');
        Schema::dropIfExists('fest_food_order_items');
        Schema::dropIfExists('fest_food_bills');
        Schema::dropIfExists('fest_food_menu_items');

        if (Schema::hasColumn('fest_events', 'food_payee_type')) {
            Schema::table('fest_events', function (Blueprint $table) {
                $table->dropColumn(['food_payee_type', 'food_host_school_id']);
            });
        }
    }
};
