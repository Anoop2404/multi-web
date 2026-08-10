<?php

namespace App\Models\State;

use Illuminate\Database\Eloquent\Model;

abstract class StateModel extends Model
{
    /**
     * The database connection name for all State operational models.
     *
     * @var string|null
     */
    protected $connection = 'state';
}
