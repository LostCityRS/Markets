<?php

use App\Models\FeaturedItem;
use App\Models\Item;
use App\Models\Listing;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Cache::flush();

    // Migration 2025_03_31_233629 imports thousands of real items with game_ids in [0, 1_000_050],
    // so we use a high non-colliding base for test items.
    $GLOBALS['__stats_test_game_id'] = 9_000_001;
});

function makeStatsItem(array $overrides = []): Item
{
    $gid = $GLOBALS['__stats_test_game_id']++;

    return Item::factory()->create(array_merge([
        'game_id' => $gid,
        'name' => "stats_item_{$gid}",
        'slug' => "stats_item_{$gid}",
    ], $overrides));
}

/**
 * Create a sold listing. By convention the legacy `listings.price` column is left null
 * and the coin price is encoded as a "For each item:" offer with a Coins offer-item
 * (matches how the production sale form persists trades).
 *
 * Pass `'price' => N` to set the coin price per unit (default 100).
 * Pass `'price' => null` to skip creating any offer (item-for-item set up by the caller).
 */
function makeSoldListing(Item $item, array $overrides = []): Listing
{
    $coinPrice = array_key_exists('price', $overrides) ? $overrides['price'] : 100;
    unset($overrides['price']);

    $listing = Listing::factory()->create(array_merge([
        'item_id' => $item->id,
        'sold_at' => now()->subHours(1),
        'price' => null,
        'quantity' => 1,
        'username' => 'tester',
    ], $overrides));

    if ($coinPrice !== null) {
        attachCoinOffer($listing, $coinPrice);
    }

    return $listing;
}

/**
 * Attach a single "For each item:" offer with a coin offer-item to an existing listing.
 */
function attachCoinOffer(Listing $listing, int $coinPricePerUnit): void
{
    $coins = Item::where('game_id', 995)->firstOrFail();
    $offer = $listing->offers()->create(['title' => 'For each item:']);
    $offer->items()->create([
        'item_id' => $coins->id,
        'quantity' => $coinPricePerUnit,
    ]);
}

/**
 * Create a sold listing with multiple alternative coin offers (joined by "OR" in the UI).
 *
 * @param  array<int, int>  $offerCoinAmounts
 */
function makeSoldListingWithCoinOffers(Item $item, array $offerCoinAmounts, array $overrides = []): Listing
{
    $listing = makeSoldListing($item, array_merge(['price' => null], $overrides));

    foreach ($offerCoinAmounts as $coinAmount) {
        attachCoinOffer($listing, $coinAmount);
    }

    return $listing;
}

/**
 * Attach an item-for-item offer to a listing. $offerItems is a list of [Item, quantity] pairs
 * that all belong to a single offer (so the buyer pays all of them per unit of the listing).
 *
 * @param  array<int, array{0: Item, 1: int|float}>  $offerItems
 */
function attachItemOffer(Listing $listing, array $offerItems): void
{
    $offer = $listing->offers()->create(['title' => 'For each item:']);
    foreach ($offerItems as [$offerItem, $quantity]) {
        $offer->items()->create([
            'item_id' => $offerItem->id,
            'quantity' => $quantity,
        ]);
    }
}

it('orders top volume by SUM(price * quantity) descending', function () {
    $a = makeStatsItem();
    $b = makeStatsItem();
    $c = makeStatsItem();

    // Item A total = 1000 (price 100 × qty 10)
    makeSoldListing($a, ['price' => 100, 'quantity' => 10]);
    // Item B total = 500 (price 50 × qty 10)
    makeSoldListing($b, ['price' => 50, 'quantity' => 10]);
    // Item C total = 2000 (price 200 × qty 10)
    makeSoldListing($c, ['price' => 200, 'quantity' => 10]);

    $this->get(route('stats.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('stats/index/page')
            ->has('topVolume', 3)
            ->where('topVolume.0.item.id', $c->id)
            ->where('topVolume.0.value', 2000)
            ->where('topVolume.1.item.id', $a->id)
            ->where('topVolume.1.value', 1000)
            ->where('topVolume.2.item.id', $b->id)
            ->where('topVolume.2.value', 500)
        );
});

it('only counts listings sold within the last 24 hours', function () {
    $a = makeStatsItem();
    $b = makeStatsItem();

    makeSoldListing($a, ['sold_at' => now()->subHours(25), 'price' => 1000, 'quantity' => 1]);
    makeSoldListing($b, ['sold_at' => now()->subHours(1), 'price' => 1000, 'quantity' => 1]);

    $this->get(route('stats.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('topVolume', 1)
            ->where('topVolume.0.item.id', $b->id)
        );
});

it('excludes null-price listings from price-based stats but keeps them in topTraded', function () {
    $a = makeStatsItem();
    $b = makeStatsItem();

    // Item A: null price
    makeSoldListing($a, ['price' => null, 'quantity' => 10]);
    // Item B: priced
    makeSoldListing($b, ['price' => 100, 'quantity' => 1]);

    $this->get(route('stats.index'))
        ->assertInertia(function (AssertableInertia $page) use ($a, $b) {
            $page
                ->has('topVolume', 1)
                ->where('topVolume.0.item.id', $b->id)
                ->has('topExpensiveItems', 1)
                ->where('topExpensiveItems.0.item.id', $b->id)
                ->has('topExpensiveTrades', 1)
                ->where('topExpensiveTrades.0.item.id', $b->id)
                ->has('topTraded', 2);

            // topTraded should include A (since it's count-based)
            $topTradedItemIds = collect($page->toArray()['props']['topTraded'])->pluck('item.id')->all();
            expect($topTradedItemIds)->toContain($a->id, $b->id);
        });
});

it('orders topTraded by COUNT(*) descending', function () {
    $a = makeStatsItem();
    $b = makeStatsItem();
    $c = makeStatsItem();

    for ($i = 0; $i < 5; $i++) {
        makeSoldListing($a);
    }
    for ($i = 0; $i < 3; $i++) {
        makeSoldListing($b);
    }
    makeSoldListing($c);

    $this->get(route('stats.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('topTraded', 3)
            ->where('topTraded.0.item.id', $a->id)
            ->where('topTraded.0.value', 5)
            ->where('topTraded.1.item.id', $b->id)
            ->where('topTraded.1.value', 3)
            ->where('topTraded.2.item.id', $c->id)
            ->where('topTraded.2.value', 1)
        );
});

it('orders topExpensiveItems by MAX(price) descending', function () {
    $a = makeStatsItem();
    $b = makeStatsItem();

    // Item A max-price 100
    makeSoldListing($a, ['price' => 10, 'quantity' => 1]);
    makeSoldListing($a, ['price' => 100, 'quantity' => 1]);
    makeSoldListing($a, ['price' => 50, 'quantity' => 1]);
    // Item B max-price 200
    makeSoldListing($b, ['price' => 200, 'quantity' => 1]);

    $this->get(route('stats.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('topExpensiveItems', 2)
            ->where('topExpensiveItems.0.item.id', $b->id)
            ->where('topExpensiveItems.0.value', 200)
            ->where('topExpensiveItems.1.item.id', $a->id)
            ->where('topExpensiveItems.1.value', 100)
        );
});

it('orders topExpensiveTrades by price * quantity descending', function () {
    $a = makeStatsItem();
    $b = makeStatsItem();
    $c = makeStatsItem();

    // Total 5000
    makeSoldListing($a, ['price' => 500, 'quantity' => 10]);
    // Total 2000
    makeSoldListing($b, ['price' => 200, 'quantity' => 10]);
    // Total 1000
    makeSoldListing($c, ['price' => 100, 'quantity' => 10]);

    $this->get(route('stats.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('topExpensiveTrades', 3)
            ->where('topExpensiveTrades.0.item.id', $a->id)
            ->where('topExpensiveTrades.0.total', 5000)
            ->where('topExpensiveTrades.1.item.id', $b->id)
            ->where('topExpensiveTrades.1.total', 2000)
            ->where('topExpensiveTrades.2.item.id', $c->id)
            ->where('topExpensiveTrades.2.total', 1000)
        );
});

it('selects the top trade as the featured item and inserts a featured_items row', function () {
    $a = makeStatsItem();
    $b = makeStatsItem();
    $c = makeStatsItem();

    makeSoldListing($a, ['price' => 100, 'quantity' => 1]);
    makeSoldListing($b, ['price' => 200, 'quantity' => 1]);
    $top = makeSoldListing($c, ['price' => 1000, 'quantity' => 5]); // total 5000

    $this->get(route('stats.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('featuredItem.item.id', $c->id)
            ->where('featuredItem.total', 5000)
        );

    expect(FeaturedItem::count())->toBe(1);
    expect(FeaturedItem::first()->item_id)->toBe($c->id);
    expect(FeaturedItem::first()->listing_id)->toBe($top->id);
});

it('returns the same featured item on subsequent requests within the same UTC day', function () {
    $a = makeStatsItem();
    $b = makeStatsItem();

    $first = makeSoldListing($a, ['price' => 1000, 'quantity' => 1]); // total 1000

    $this->get(route('stats.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('featuredItem.item.id', $a->id)
        );

    expect(FeaturedItem::count())->toBe(1);

    // Bigger trade for B is created. Cache flushed to force recompute.
    makeSoldListing($b, ['price' => 9999, 'quantity' => 1]);
    Cache::flush();

    $this->get(route('stats.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('featuredItem.item.id', $a->id)
        );

    expect(FeaturedItem::count())->toBe(1);
});

it('excludes items featured within the last 60 days', function () {
    $a = makeStatsItem();
    $b = makeStatsItem();

    $listingA = makeSoldListing($a, ['price' => 1000, 'quantity' => 10]); // total 10000
    makeSoldListing($b, ['price' => 100, 'quantity' => 1]); // total 100

    // Pre-insert featured row for A 30 days ago
    FeaturedItem::create([
        'item_id' => $a->id,
        'listing_id' => $listingA->id,
        'featured_at' => now()->subDays(30),
    ]);

    $this->get(route('stats.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('featuredItem.item.id', $b->id)
        );
});

it('allows items featured more than 60 days ago to be re-featured', function () {
    $a = makeStatsItem();

    $listingA = makeSoldListing($a, ['price' => 1000, 'quantity' => 10]);

    FeaturedItem::create([
        'item_id' => $a->id,
        'listing_id' => $listingA->id,
        'featured_at' => now()->subDays(61),
    ]);

    $this->get(route('stats.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('featuredItem.item.id', $a->id)
        );
});

it('returns null featured item when every candidate item is excluded by 60-day rule', function () {
    $a = makeStatsItem();
    $b = makeStatsItem();
    $c = makeStatsItem();

    $listingA = makeSoldListing($a);
    $listingB = makeSoldListing($b);
    $listingC = makeSoldListing($c);

    // Spread the seeded featured rows across different days to avoid the unique-date constraint,
    // while keeping all of them within the 60-day exclusion window.
    $rows = [
        ['item' => $a, 'listing' => $listingA, 'days' => 10],
        ['item' => $b, 'listing' => $listingB, 'days' => 11],
        ['item' => $c, 'listing' => $listingC, 'days' => 12],
    ];
    foreach ($rows as $row) {
        FeaturedItem::create([
            'item_id' => $row['item']->id,
            'listing_id' => $row['listing']->id,
            'featured_at' => now()->subDays($row['days']),
        ]);
    }

    $featuredCountBefore = FeaturedItem::count();

    $this->get(route('stats.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('featuredItem', null)
        );

    expect(FeaturedItem::count())->toBe($featuredCountBefore);
});

it('returns empty leaderboards and null featured when there are no sold listings', function () {
    $this->get(route('stats.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('topVolume', 0)
            ->has('topExpensiveItems', 0)
            ->has('topTraded', 0)
            ->has('topExpensiveTrades', 0)
            ->where('featuredItem', null)
        );
});

it('excludes inactive items from every leaderboard and featured item', function () {
    $a = makeStatsItem(['is_active' => false]);
    $b = makeStatsItem(['is_active' => true]);

    makeSoldListing($a, ['price' => 9999, 'quantity' => 99]);
    makeSoldListing($b, ['price' => 100, 'quantity' => 1]);

    $this->get(route('stats.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('topVolume', 1)
            ->where('topVolume.0.item.id', $b->id)
            ->has('topExpensiveItems', 1)
            ->where('topExpensiveItems.0.item.id', $b->id)
            ->has('topTraded', 1)
            ->where('topTraded.0.item.id', $b->id)
            ->has('topExpensiveTrades', 1)
            ->where('topExpensiveTrades.0.item.id', $b->id)
            ->where('featuredItem.item.id', $b->id)
        );

    expect(FeaturedItem::where('item_id', $a->id)->exists())->toBeFalse();
});

it('keeps the cached response across new sales until cache rolls over', function () {
    $a = makeStatsItem();
    $b = makeStatsItem();

    makeSoldListing($a); // 1 sold

    $this->get(route('stats.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('topTraded', 1)
            ->where('topTraded.0.item.id', $a->id)
            ->where('topTraded.0.value', 1)
        );

    // Add more sold listings for B that should change leaderboard if recomputed.
    for ($i = 0; $i < 5; $i++) {
        makeSoldListing($b);
    }

    // Hit /stats again WITHOUT flushing cache.
    $this->get(route('stats.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('topTraded', 1)
            ->where('topTraded.0.item.id', $a->id)
            ->where('topTraded.0.value', 1)
        );
});

it('recomputes the response after the cache is flushed', function () {
    $a = makeStatsItem();
    $b = makeStatsItem();

    makeSoldListing($a);

    $this->get(route('stats.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('topTraded', 1)
        );

    for ($i = 0; $i < 5; $i++) {
        makeSoldListing($b);
    }

    Cache::flush();

    $this->get(route('stats.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('topTraded', 2)
            ->where('topTraded.0.item.id', $b->id)
            ->where('topTraded.0.value', 5)
        );
});

it('renders the stats inertia component', function () {
    $this->get(route('stats.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('stats/index/page')
        );
});

it('caps every leaderboard at 10 entries', function () {
    $items = collect(range(1, 15))->map(fn () => makeStatsItem());

    foreach ($items as $item) {
        makeSoldListing($item, ['price' => 100, 'quantity' => 1]);
    }

    $this->get(route('stats.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('topVolume', 10)
            ->has('topExpensiveItems', 10)
            ->has('topTraded', 10)
            ->has('topExpensiveTrades', 10)
        );
});

it('home page exposes a link to /stats', function () {
    // The MarketTabs Vue component is what wires the link. Inertia's HTML response
    // is just a JSON shell (component runs client-side), so verify by reading the
    // shared component source — it must reference the stats route.
    $marketTabsPath = resource_path('views/components/market-tabs.vue');
    expect(file_exists($marketTabsPath))->toBeTrue();
    expect(file_get_contents($marketTabsPath))->toContain("route(\"stats.index\")");

    // And the home page must use MarketTabs.
    $homePagePath = resource_path('views/pages/home/index/page.vue');
    expect(file_get_contents($homePagePath))->toContain('<MarketTabs');

    // Sanity-check that the home route still resolves and the stats.index route exists.
    $this->get(route('home'))->assertOk();
    expect(route('stats.index'))->toEndWith('/stats');
});

it('picks the MAX coin amount across alternative offers on the same listing', function () {
    $a = makeStatsItem();

    // Listing has two alternative coin offers (joined by "OR"): 50 gp/ea or 200 gp/ea.
    // We assume the seller realised the higher-value offer.
    makeSoldListingWithCoinOffers($a, [50, 200], ['quantity' => 10]);

    $this->get(route('stats.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('topExpensiveItems.0.item.id', $a->id)
            ->where('topExpensiveItems.0.value', 200)
            ->where('topVolume.0.value', 2000)
        );
});

it('values an item-for-item trade via the per-item price oracle', function () {
    // Yellow Phat has 1 prior coin sale at 600M gp/unit.
    // Ranger boots have 1 prior coin sale at 50M gp/unit.
    // Red Phat is sold for 1 Yellow Phat + 1 Ranger boots → valued at 650M.
    $yellowPhat = makeStatsItem();
    $rangerBoots = makeStatsItem();
    $redPhat = makeStatsItem();

    makeSoldListing($yellowPhat, ['price' => 600_000_000, 'sold_at' => now()->subHours(2)]);
    makeSoldListing($rangerBoots, ['price' => 50_000_000, 'sold_at' => now()->subHours(2)]);

    $itemForItem = makeSoldListing($redPhat, ['price' => null, 'quantity' => 1]);
    attachItemOffer($itemForItem, [[$yellowPhat, 1], [$rangerBoots, 1]]);

    $this->get(route('stats.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('topExpensiveTrades.0.item.id', $redPhat->id)
            ->where('topExpensiveTrades.0.total', 650_000_000)
        );
});

it('values hybrid offers (coins plus an item) as the sum of both contributions', function () {
    // Yellow Phat oracle = 1000 gp.
    // Red Phat is sold for "100 gp + 1 Yellow Phat per unit" → valued at 100 + 1000 = 1100.
    $yellowPhat = makeStatsItem();
    $redPhat = makeStatsItem();

    makeSoldListing($yellowPhat, ['price' => 1000, 'sold_at' => now()->subHours(2)]);

    $coins = Item::where('game_id', 995)->firstOrFail();
    $hybrid = makeSoldListing($redPhat, ['price' => null, 'quantity' => 1]);
    attachItemOffer($hybrid, [[$coins, 100], [$yellowPhat, 1]]);

    $this->get(route('stats.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('topExpensiveItems.0.item.id', $redPhat->id)
            ->where('topExpensiveItems.0.value', 1100)
        );
});

it('contributes 0 for offer items with no coin sale history (no recursion)', function () {
    // Cabbage has no coin sale history — its oracle entry is missing.
    // Red Phat sold for 1 Cabbage → valued at 0 → excluded from price-based stats.
    $cabbage = makeStatsItem();
    $redPhat = makeStatsItem();

    $sale = makeSoldListing($redPhat, ['price' => null, 'quantity' => 1]);
    attachItemOffer($sale, [[$cabbage, 1]]);

    $this->get(route('stats.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('topVolume', 0)
            ->has('topExpensiveItems', 0)
            ->has('topExpensiveTrades', 0)
            // The trade still counts in topTraded (count-based, no price filter).
            ->has('topTraded', 1)
            ->where('featuredItem', null)
        );
});

it('shows expensive items in topExpensiveItems even when last sold more than 24 hours ago', function () {
    // Partyhat: only sale ever was 7 days ago at 1B gp.
    // Cheap item: 1 sale today at 100 gp.
    $partyhat = makeStatsItem();
    $cheap = makeStatsItem();

    makeSoldListing($partyhat, ['price' => 1_000_000_000, 'sold_at' => now()->subDays(7)]);
    makeSoldListing($cheap, ['price' => 100, 'sold_at' => now()->subHours(1)]);

    $this->get(route('stats.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            // topExpensiveItems is all-time — partyhat ranks #1 despite being inactive today.
            ->has('topExpensiveItems', 2)
            ->where('topExpensiveItems.0.item.id', $partyhat->id)
            ->where('topExpensiveItems.0.value', 1_000_000_000)
            ->where('topExpensiveItems.1.item.id', $cheap->id)
            // Other leaderboards stay 24h-bound — partyhat absent.
            ->has('topVolume', 1)
            ->where('topVolume.0.item.id', $cheap->id)
            ->has('topTraded', 1)
            ->where('topTraded.0.item.id', $cheap->id)
            ->has('topExpensiveTrades', 1)
            ->where('topExpensiveTrades.0.item.id', $cheap->id)
        );
});

it('averages an item oracle over its 7 most recent coin-priced sales only', function () {
    // Yellow Phat history (all >24h ago so they don't show up in the leaderboard window —
    // they only feed the oracle):
    //   - 1 oldest sale at 999_999_999 gp (8 days ago) — should fall off the 7-recent window
    //   - 7 sales at 100 gp each (26..32 hours ago)
    // Oracle for Yellow Phat = AVG of latest 7 = 100 (outlier dropped).
    // A new Red-Phat-for-Yellow-Phat sale within 24h should value at 100.
    $yellowPhat = makeStatsItem();
    $redPhat = makeStatsItem();

    makeSoldListing($yellowPhat, ['price' => 999_999_999, 'sold_at' => now()->subDays(8)]);
    for ($i = 0; $i < 7; $i++) {
        makeSoldListing($yellowPhat, ['price' => 100, 'sold_at' => now()->subHours(26 + $i)]);
    }

    $itemForItem = makeSoldListing($redPhat, ['price' => null, 'quantity' => 1]);
    attachItemOffer($itemForItem, [[$yellowPhat, 1]]);

    $this->get(route('stats.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('topExpensiveTrades', 1)
            ->where('topExpensiveTrades.0.item.id', $redPhat->id)
            ->where('topExpensiveTrades.0.total', 100)
        );
});
