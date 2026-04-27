<?php

namespace App\Services;

use App\Data\Item\ItemData;
use App\Data\Stats\StatItemData;
use App\Data\Stats\StatTradeData;
use App\Models\FeaturedItem;
use App\Models\Item;
use App\Models\Listing;
use App\Pages\StatsPage;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelData\DataCollection;

class StatsService
{
    private const COINS_GAME_ID = 995;

    /** Oracle window: how many of an item's most recent coin-priced sales feed its average. */
    private const ORACLE_RECENT_TRADES = 7;

    public function getStatsPage(): StatsPage
    {
        $cacheKey = 'stats_index_'.now()->utc()->toDateString();

        return Cache::remember($cacheKey, now()->tomorrow(), fn () => $this->compute());
    }

    protected function compute(): StatsPage
    {
        return new StatsPage(
            topVolume: $this->topVolume(),
            topExpensiveItems: $this->topExpensiveItems(),
            topTraded: $this->topTraded(),
            topExpensiveTrades: $this->topExpensiveTrades(),
            featuredItem: $this->pickOrFetchFeaturedItem(),
        );
    }

    protected function topVolume(): DataCollection
    {
        $rows = $this->baseListingsWithValue()
            ->selectRaw('listings.item_id, SUM(vl.coin_price * listings.quantity) AS value')
            ->where('vl.coin_price', '>', 0)
            ->groupBy('listings.item_id')
            ->orderByDesc('value')
            ->limit(10)
            ->get();

        return $this->hydrateStatItems($rows);
    }

    protected function topExpensiveItems(): DataCollection
    {
        // No 24h cutoff here — quiet rares (e.g. partyhats) shouldn't drop off this
        // board just because nobody traded one today.
        $rows = DB::table('listings')
            ->join('items', 'items.id', '=', 'listings.item_id')
            ->joinSub($this->valuedListingsJoin(), 'vl', 'vl.listing_id', '=', 'listings.id')
            ->whereNull('listings.deleted_at')
            ->whereNotNull('listings.sold_at')
            ->where('items.is_active', 1)
            ->where('vl.coin_price', '>', 0)
            ->selectRaw('listings.item_id, MAX(vl.coin_price) AS value')
            ->groupBy('listings.item_id')
            ->orderByDesc('value')
            ->limit(10)
            ->get();

        return $this->hydrateStatItems($rows);
    }

    protected function topTraded(): DataCollection
    {
        $rows = DB::table('listings')
            ->join('items', 'items.id', '=', 'listings.item_id')
            ->selectRaw('listings.item_id, COUNT(*) AS value')
            ->whereNull('listings.deleted_at')
            ->where('listings.sold_at', '>=', now()->subDay())
            ->where('items.is_active', 1)
            ->groupBy('listings.item_id')
            ->orderByDesc('value')
            ->limit(10)
            ->get();

        return $this->hydrateStatItems($rows);
    }

    protected function topExpensiveTrades(): DataCollection
    {
        $listings = $this->soldListingsWithValueQuery()
            ->orderByRaw('vl.coin_price * listings.quantity DESC')
            ->limit(10)
            ->get();

        return new DataCollection(
            StatTradeData::class,
            $listings->map(fn (Listing $listing) => $this->makeTradeData($listing))->all(),
        );
    }

    protected function pickOrFetchFeaturedItem(): ?StatTradeData
    {
        $today = now()->utc()->toDateString();

        $existing = FeaturedItem::with('listing.item')
            ->where('featured_date', $today)
            ->first();

        if ($existing) {
            if (! $existing->listing) {
                return null;
            }

            return $this->makeTradeData($existing->listing);
        }

        $excludedItemIds = FeaturedItem::where('featured_at', '>=', now()->subDays(60))
            ->pluck('item_id');

        $candidate = $this->soldListingsWithValueQuery()
            ->whereNotIn('listings.item_id', $excludedItemIds)
            ->orderByRaw('vl.coin_price * listings.quantity DESC')
            ->first();

        if (! $candidate) {
            return null;
        }

        try {
            FeaturedItem::create([
                'item_id' => $candidate->item_id,
                'listing_id' => $candidate->id,
                'featured_at' => now(),
            ]);
        } catch (QueryException $e) {
            // Another concurrent request beat us to inserting today's row. Re-read the winning row.
            $winner = FeaturedItem::with('listing.item')
                ->where('featured_date', $today)
                ->first();

            if (! $winner || ! $winner->listing) {
                throw $e;
            }

            return $this->makeTradeData($winner->listing);
        }

        return $this->makeTradeData($candidate);
    }

    /**
     * Per-listing coin-only price: SUM of coin offer-items per offer, then MAX across offers.
     * Used to feed the oracle (only "real" coin sales contribute, no recursion).
     */
    protected function coinOnlyPricePerListing(): QueryBuilder
    {
        $perOffer = DB::table('listing_offers as lo')
            ->join('listing_offer_items as loi', 'loi.listing_offer_id', '=', 'lo.id')
            ->join('items as ci', 'ci.id', '=', 'loi.item_id')
            ->where('ci.game_id', self::COINS_GAME_ID)
            ->groupBy('lo.id', 'lo.listing_id')
            ->selectRaw('lo.listing_id, SUM(loi.quantity) AS coin_total');

        return DB::query()
            ->fromSub($perOffer, 'per_offer')
            ->groupBy('per_offer.listing_id')
            ->selectRaw('per_offer.listing_id, MAX(per_offer.coin_total) AS coin_price');
    }

    /**
     * Per-item price oracle: AVG of an item's most recent N coin-priced sale prices.
     * Items with zero coin-priced sales get no row (LEFT JOIN -> NULL -> 0 contribution).
     */
    protected function priceOracle(): QueryBuilder
    {
        $ranked = DB::table('listings as l')
            ->joinSub($this->coinOnlyPricePerListing(), 'cpl', 'cpl.listing_id', '=', 'l.id')
            ->whereNotNull('l.sold_at')
            ->whereNull('l.deleted_at')
            ->select(
                'l.item_id',
                'cpl.coin_price',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY l.item_id ORDER BY l.sold_at DESC, l.id DESC) AS rn'),
            );

        return DB::query()
            ->fromSub($ranked, 'ranked')
            ->where('ranked.rn', '<=', self::ORACLE_RECENT_TRADES)
            ->groupBy('ranked.item_id')
            ->selectRaw('ranked.item_id, AVG(ranked.coin_price) AS avg_price');
    }

    /**
     * Per-listing valuation: each offer item is valued via the oracle (coins = 1 gp each;
     * other items = oracle avg, or 0 if the item has never sold for coins). Per offer
     * we sum valued contributions, then take MAX across alternative offers ("OR" branches).
     */
    protected function valuedListingsJoin(): QueryBuilder
    {
        $valuedOffers = DB::table('listing_offers as lo')
            ->join('listing_offer_items as loi', 'loi.listing_offer_id', '=', 'lo.id')
            ->join('items as ii', 'ii.id', '=', 'loi.item_id')
            ->leftJoinSub($this->priceOracle(), 'oracle', 'oracle.item_id', '=', 'loi.item_id')
            ->groupBy('lo.id', 'lo.listing_id')
            ->selectRaw('lo.listing_id, SUM(loi.quantity * CASE WHEN ii.game_id = '.self::COINS_GAME_ID.' THEN 1 ELSE COALESCE(oracle.avg_price, 0) END) AS offer_total');

        return DB::query()
            ->fromSub($valuedOffers, 'valued_offer')
            ->groupBy('valued_offer.listing_id')
            ->selectRaw('valued_offer.listing_id, MAX(valued_offer.offer_total) AS coin_price');
    }

    protected function baseListingsWithValue(): QueryBuilder
    {
        return DB::table('listings')
            ->join('items', 'items.id', '=', 'listings.item_id')
            ->joinSub($this->valuedListingsJoin(), 'vl', 'vl.listing_id', '=', 'listings.id')
            ->whereNull('listings.deleted_at')
            ->where('listings.sold_at', '>=', now()->subDay())
            ->where('items.is_active', 1);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Listing>
     */
    protected function soldListingsWithValueQuery()
    {
        return Listing::query()
            ->with('item')
            ->joinSub($this->valuedListingsJoin(), 'vl', 'vl.listing_id', '=', 'listings.id')
            ->whereNotNull('listings.sold_at')
            ->where('listings.sold_at', '>=', now()->subDay())
            ->whereHas('item', fn ($q) => $q->where('is_active', true))
            ->where('vl.coin_price', '>', 0)
            ->select('listings.*', DB::raw('vl.coin_price AS coin_price'));
    }

    protected function hydrateStatItems($rows): DataCollection
    {
        $itemIds = $rows->pluck('item_id')->all();
        $items = Item::whereIn('id', $itemIds)->get()->keyBy('id');

        $stats = $rows->map(function ($row) use ($items) {
            $item = $items->get($row->item_id);

            if (! $item) {
                return null;
            }

            return new StatItemData(
                item: ItemData::fromModel($item),
                value: (int) $row->value,
            );
        })->filter()->values()->all();

        return new DataCollection(StatItemData::class, $stats);
    }

    protected function makeTradeData(Listing $listing): StatTradeData
    {
        $coinPrice = $listing->coin_price ?? $this->valueForListing($listing);

        if ($coinPrice === null || $coinPrice <= 0) {
            throw new \LogicException("Listing {$listing->id} has no priceable offer for trade-data hydration.");
        }

        $coinPrice = (int) $coinPrice;
        $quantity = (int) $listing->quantity;

        return new StatTradeData(
            item: ItemData::fromModel($listing->item),
            price: $coinPrice,
            quantity: $quantity,
            total: $coinPrice * $quantity,
            username: (string) $listing->username,
            soldAt: $listing->sold_at,
        );
    }

    /**
     * Compute a single listing's valued price on demand (used when loading a featured-item
     * row's listing without going through the leaderboard query).
     */
    protected function valueForListing(Listing $listing): ?int
    {
        $row = DB::table('listing_offers as lo')
            ->join('listing_offer_items as loi', 'loi.listing_offer_id', '=', 'lo.id')
            ->join('items as ii', 'ii.id', '=', 'loi.item_id')
            ->leftJoinSub($this->priceOracle(), 'oracle', 'oracle.item_id', '=', 'loi.item_id')
            ->where('lo.listing_id', $listing->id)
            ->groupBy('lo.id')
            ->selectRaw('SUM(loi.quantity * CASE WHEN ii.game_id = '.self::COINS_GAME_ID.' THEN 1 ELSE COALESCE(oracle.avg_price, 0) END) AS offer_total')
            ->orderByDesc('offer_total')
            ->limit(1)
            ->first();

        if (! $row || $row->offer_total <= 0) {
            return null;
        }

        return (int) $row->offer_total;
    }
}
