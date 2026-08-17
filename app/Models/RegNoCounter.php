<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Last-issued student reg_no sequence number per (Sahodaya, academic-year suffix),
 * e.g. sahodaya_id=..., year_suffix="26", last_sequence=41 means the next student
 * ID for that Sahodaya/year is STU/26/0042. Lives on the central connection (see
 * the create_reg_no_counters_table migration) so it's reachable the same way
 * regardless of per-Sahodaya database mode. See
 * App\Services\Students\StudentRegistrationNumberGenerator::generate().
 */
class RegNoCounter extends Model
{
    use CentralConnection;

    protected $fillable = ['sahodaya_id', 'year_suffix', 'last_sequence'];

    protected $casts = ['last_sequence' => 'integer'];

    public function sahodaya(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'sahodaya_id');
    }
}
