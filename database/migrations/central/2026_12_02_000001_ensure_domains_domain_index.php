<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('domains') && ! Schema::hasIndex('domains', 'domains_domain_unique')) {
            Schema::table('domains', function (Blueprint $table) {
                $table->unique('domain', 'domains_domain_unique');
            });
        }
    }

    public function down(): void
    {
        // Domain uniqueness predates this repair migration. Preserve that invariant
        // on rollback, including databases where up() did not create the index.
    }
};
