<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A row exists only while a school's certificates of one type are ticked as downloaded /
 * handed over for an event; unticking deletes it.
 */
class FestCertificateSchoolMark extends Model
{
    protected $fillable = ['event_id', 'school_id', 'cert_type', 'marked_by_user_id', 'marked_at'];

    protected $casts = ['marked_at' => 'datetime'];
}
