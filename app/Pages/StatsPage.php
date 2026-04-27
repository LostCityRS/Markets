<?php

namespace App\Pages;

use App\Data\Stats\StatItemData;
use App\Data\Stats\StatTradeData;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class StatsPage extends Data
{
    public function __construct(
        /** @var DataCollection<StatItemData> */
        public DataCollection $topVolume,
        /** @var DataCollection<StatItemData> */
        public DataCollection $topExpensiveItems,
        /** @var DataCollection<StatItemData> */
        public DataCollection $topTraded,
        /** @var DataCollection<StatTradeData> */
        public DataCollection $topExpensiveTrades,
        public ?StatTradeData $featuredItem,
    ) {}
}
