<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeaturedItem extends Model
{
    public $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'featured_at' => 'datetime',
        'featured_date' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function (FeaturedItem $featuredItem) {
            $featuredItem->featured_date = $featuredItem->featured_at?->utc()->toDateString();
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
