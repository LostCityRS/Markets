<?php

namespace App\Data\Stats;

use App\Data\Item\ItemData;
use Spatie\LaravelData\Data;

class StatItemData extends Data
{
    public function __construct(
        public ItemData $item,
        public int $value,
    ) {}
}
