<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DestinationAlias extends Model
{
    public $timestamps = false;

    protected $fillable = ['destination_id', 'alias'];

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }
}
