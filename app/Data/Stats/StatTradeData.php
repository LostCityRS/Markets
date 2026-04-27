<?php

namespace App\Data\Stats;

use App\Data\Item\ItemData;
use DateTime;
use Spatie\LaravelData\Data;

class StatTradeData extends Data
{
    public function __construct(
        public ItemData $item,
        public int $price,
        public int $quantity,
        public int $total,
        public string $username,
        public DateTime $soldAt,
    ) {}
}
