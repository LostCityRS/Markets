<?php

namespace App\Services;

use App\Data\Item\ItemData;
use App\Data\Stats\StatItemData;
use App\Data\Stats\StatTradeData;
use App\Models\FeaturedItem;
use App\Models\Item;
use App\Models\Listing;
use App\Pages\StatsPage;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelData\DataCollection;

class StatsService
{
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
        $rows = DB::table('listings')
            ->join('items', 'items.id', '=', 'listings.item_id')
            ->selectRaw('listings.item_id, SUM(listings.price * listings.quantity) AS value')
            ->whereNull('listings.deleted_at')
            ->where('listings.sold_at', '>=', now()->subDay())
            ->whereNotNull('listings.price')
            ->where('items.is_active', 1)
            ->groupBy('listings.item_id')
            ->orderByDesc('value')
            ->limit(10)
            ->get();

        return $this->hydrateStatItems($rows);
    }

    protected function topExpensiveItems(): DataCollection
    {
        $rows = DB::table('listings')
            ->join('items', 'items.id', '=', 'listings.item_id')
            ->selectRaw('listings.item_id, MAX(listings.price) AS value')
            ->whereNull('listings.deleted_at')
            ->where('listings.sold_at', '>=', now()->subDay())
            ->whereNotNull('listings.price')
            ->where('items.is_active', 1)
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
        $listings = Listing::with('item')
            ->whereNotNull('sold_at')
            ->where('sold_at', '>=', now()->subDay())
            ->whereNotNull('price')
            ->whereHas('item', fn ($q) => $q->where('is_active', true))
            ->orderByRaw('price * quantity DESC')
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

        $candidate = Listing::with('item')
            ->whereNotNull('sold_at')
            ->where('sold_at', '>=', now()->subDay())
            ->whereNotNull('price')
            ->whereHas('item', fn ($q) => $q->where('is_active', true))
            ->whereNotIn('item_id', $excludedItemIds)
            ->orderByRaw('price * quantity DESC')
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
        $price = (int) $listing->price;
        $quantity = (int) $listing->quantity;

        return new StatTradeData(
            item: ItemData::fromModel($listing->item),
            price: $price,
            quantity: $quantity,
            total: $price * $quantity,
            username: (string) $listing->username,
            soldAt: $listing->sold_at,
        );
    }
}
