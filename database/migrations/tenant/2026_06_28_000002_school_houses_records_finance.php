<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_houses', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('name');
            $table->string('color', 20)->nullable();
            $table->string('motto')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('tenant_id');
        });

        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'school_house_id')) {
                $table->unsignedBigInteger('school_house_id')->nullable()->after('school_class_id');
            }
        });

        Schema::table('fest_events', function (Blueprint $table) {
            if (! Schema::hasColumn('fest_events', 'record_tracking_enabled')) {
                $table->boolean('record_tracking_enabled')->default(false)->after('registration_locked');
            }
            if (! Schema::hasColumn('fest_events', 'default_record_prize_label')) {
                $table->string('default_record_prize_label')->default('Record Break Prize')->after('record_tracking_enabled');
            }
        });

        Schema::create('fest_athletic_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('item_id');
            $table->enum('class_group', ['lp', 'up', 'hs', 'hss', 'open'])->default('open');
            $table->enum('gender', ['male', 'female', 'open'])->default('open');
            $table->enum('record_direction', ['lower_better', 'higher_better'])->default('lower_better');
            $table->decimal('record_value', 12, 4);
            $table->string('record_unit', 20)->nullable();
            $table->string('holder_name')->nullable();
            $table->string('holder_school_id')->nullable();
            $table->unsignedBigInteger('holder_participant_id')->nullable();
            $table->unsignedBigInteger('source_mark_id')->nullable();
            $table->date('record_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
            $table->foreign('item_id')->references('id')->on('fest_event_items')->cascadeOnDelete();
            $table->unique(['event_id', 'item_id', 'class_group', 'gender'], 'fest_athletic_record_unique');
        });

        Schema::create('fest_record_breaks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('athletic_record_id');
            $table->unsignedBigInteger('participant_id');
            $table->unsignedBigInteger('mark_id')->nullable();
            $table->decimal('previous_value', 12, 4);
            $table->decimal('new_value', 12, 4);
            $table->string('record_unit', 20)->nullable();
            $table->string('prize_label');
            $table->boolean('prize_awarded')->default(true);
            $table->timestamp('broken_at');
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
            $table->foreign('item_id')->references('id')->on('fest_event_items')->cascadeOnDelete();
            $table->foreign('athletic_record_id')->references('id')->on('fest_athletic_records')->cascadeOnDelete();
            $table->foreign('participant_id')->references('id')->on('fest_participants')->cascadeOnDelete();
        });

        Schema::create('fest_event_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->string('school_id');
            $table->string('invoice_number', 40)->unique();
            $table->decimal('school_registration_fee', 12, 2)->default(0);
            $table->decimal('participation_fee', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->unsignedSmallInteger('participation_item_count')->default(0);
            $table->json('breakdown_json')->nullable();
            $table->enum('status', ['draft', 'issued', 'paid', 'void'])->default('issued');
            $table->timestamp('issued_at')->nullable();
            $table->unsignedBigInteger('issued_by')->nullable();
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('fest_events')->cascadeOnDelete();
            $table->unique(['event_id', 'school_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fest_event_invoices');
        Schema::dropIfExists('fest_record_breaks');
        Schema::dropIfExists('fest_athletic_records');

        Schema::table('fest_events', function (Blueprint $table) {
            foreach (['record_tracking_enabled', 'default_record_prize_label'] as $col) {
                if (Schema::hasColumn('fest_events', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'school_house_id')) {
                $table->dropColumn('school_house_id');
            }
        });

        Schema::dropIfExists('school_houses');
    }
};
