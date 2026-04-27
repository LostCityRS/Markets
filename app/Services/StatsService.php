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
        $rows = $this->baseListingsWithCoinPrice()
            ->selectRaw('listings.item_id, SUM(COALESCE(listings.price, lcp.coin_price) * listings.quantity) AS value')
            ->whereRaw('COALESCE(listings.price, lcp.coin_price) IS NOT NULL')
            ->groupBy('listings.item_id')
            ->orderByDesc('value')
            ->limit(10)
            ->get();

        return $this->hydrateStatItems($rows);
    }

    protected function topExpensiveItems(): DataCollection
    {
        $rows = $this->baseListingsWithCoinPrice()
            ->selectRaw('listings.item_id, MAX(COALESCE(listings.price, lcp.coin_price)) AS value')
            ->whereRaw('COALESCE(listings.price, lcp.coin_price) IS NOT NULL')
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
        $listings = $this->soldListingsWithCoinPriceQuery()
            ->orderByRaw('COALESCE(listings.price, lcp.coin_price) * listings.quantity DESC')
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

        $candidate = $this->soldListingsWithCoinPriceQuery()
            ->whereNotIn('listings.item_id', $excludedItemIds)
            ->orderByRaw('COALESCE(listings.price, lcp.coin_price) * listings.quantity DESC')
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
     * Per-listing coin price: the highest coin total across the listing's offers,
     * because alternative offers are joined by "OR" and the buyer/seller picked one.
     */
    protected function coinPriceJoin(): QueryBuilder
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

    protected function baseListingsWithCoinPrice(): QueryBuilder
    {
        return DB::table('listings')
            ->join('items', 'items.id', '=', 'listings.item_id')
            ->leftJoinSub($this->coinPriceJoin(), 'lcp', 'lcp.listing_id', '=', 'listings.id')
            ->whereNull('listings.deleted_at')
            ->where('listings.sold_at', '>=', now()->subDay())
            ->where('items.is_active', 1);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Listing>
     */
    protected function soldListingsWithCoinPriceQuery()
    {
        return Listing::query()
            ->with('item')
            ->leftJoinSub($this->coinPriceJoin(), 'lcp', 'lcp.listing_id', '=', 'listings.id')
            ->whereNotNull('listings.sold_at')
            ->where('listings.sold_at', '>=', now()->subDay())
            ->whereHas('item', fn ($q) => $q->where('is_active', true))
            ->whereRaw('COALESCE(listings.price, lcp.coin_price) IS NOT NULL')
            ->select('listings.*', DB::raw('COALESCE(listings.price, lcp.coin_price) AS coin_price'));
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
        $coinPrice = $listing->coin_price ?? $listing->price ?? $this->coinPriceForListing($listing);

        if ($coinPrice === null) {
            throw new \LogicException("Listing {$listing->id} has no coin price for trade-data hydration.");
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

    protected function coinPriceForListing(Listing $listing): ?int
    {
        $maxCoin = DB::table('listing_offers as lo')
            ->join('listing_offer_items as loi', 'loi.listing_offer_id', '=', 'lo.id')
            ->join('items as ci', 'ci.id', '=', 'loi.item_id')
            ->where('lo.listing_id', $listing->id)
            ->where('ci.game_id', self::COINS_GAME_ID)
            ->groupBy('lo.id')
            ->selectRaw('SUM(loi.quantity) AS offer_total')
            ->orderByDesc('offer_total')
            ->limit(1)
            ->value('offer_total');

        return $maxCoin !== null ? (int) $maxCoin : null;
    }
}
