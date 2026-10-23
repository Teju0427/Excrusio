<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Amenity extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'slug', 'icon', 'applies_to'];

    public function listings(): BelongsToMany
    {
        return $this->belongsToMany(Listing::class);
    }
}
