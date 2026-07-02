<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sahodaya_profiles', 'mail_transport')) {
            return;
        }

        DB::table('sahodaya_profiles')
            ->whereNull('mail_transport')
            ->orWhere('mail_transport', 'smtp')
            ->update(['mail_transport' => 'zeptomail_api']);
    }

    public function down(): void
    {
        // no-op — cannot reliably restore prior transport choice
    }
};
