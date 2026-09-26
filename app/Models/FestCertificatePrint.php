<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A participation certificate that has been sent to print in a "Print complete students" run. */
class FestCertificatePrint extends Model
{
    protected $fillable = ['event_id', 'certificate_id', 'school_id', 'run_uuid', 'printed_by_user_id', 'printed_at'];

    protected $casts = ['printed_at' => 'datetime'];
}
