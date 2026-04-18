<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemSetComponent extends Model
{
    public $guarded = [];

    public $timestamps = false;

    public function set(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'set_item_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
