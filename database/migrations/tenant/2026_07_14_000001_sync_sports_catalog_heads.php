<?php

use App\Services\Events\FestItemHeadService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Backfill CKSC catalog rows, master item heads, and head_key links on existing tenant DBs. */
return new class extends Migration
{
    public function up(): void
    {
        $tenantId = DB::table('sahodaya_profiles')->value('tenant_id')
            ?? DB::table('fest_catalog_items')->value('tenant_id');

        if (! $tenantId) {
            return;
        }

        app(FestItemHeadService::class)->backfillTenant($tenantId, [
            'sports',
            'kalolsavam',
            'kids_fest',
            'teacher_fest',
            'english_fest',
            'science_fest',
        ]);
    }

    public function down(): void
    {
        // Data backfill — no rollback.
    }
};
