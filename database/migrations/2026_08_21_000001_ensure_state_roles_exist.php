<?php

use App\Models\PlatformRole;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        PlatformRole::firstOrCreate(['name' => 'state_admin', 'guard_name' => 'web']);
        PlatformRole::firstOrCreate(['name' => 'state_staff', 'guard_name' => 'web']);
    }

    public function down(): void
    {
        // Keep roles intact
    }
};
