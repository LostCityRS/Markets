<?php

use App\Models\Item;
use App\Models\Listing;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Cache::flush();

    // Migration 2025_03_31_233629 imports thousands of real items with game_ids in [0, 1_000_050],
    // so we use a high non-colliding base for test items.
    $GLOBALS['__recent_sales_test_game_id'] = 9_500_001;
});

function makeRecentSalesItem(array $overrides = []): Item
{
    $gid = $GLOBALS['__recent_sales_test_game_id']++;

    return Item::factory()->create(array_merge([
        'game_id' => $gid,
        'name' => "recent_sales_item_{$gid}",
        'slug' => "recent_sales_item_{$gid}",
    ], $overrides));
}

/**
 * Create a sold listing. By convention the legacy `listings.price` column is left null
 * and the coin price is encoded as a "For each item:" offer with a Coins offer-item
 * (matches how the production sale form persists trades).
 *
 * Pass `'price' => N` to set the coin price per unit (default 100).
 * Pass `'price' => null` to skip creating any offer.
 */
function makeRecentSalesSoldListing(Item $item, array $overrides = []): Listing
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
        $coins = Item::where('game_id', 995)->firstOrFail();
        $offer = $listing->offers()->create(['title' => 'For each item:']);
        $offer->items()->create([
            'item_id' => $coins->id,
            'quantity' => $coinPrice,
        ]);
    }

    return $listing;
}

it('renders the recent sales page', function () {
    $this->get(route('sales.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('sales/index/page')
            ->has('listings.data', 0)
        );
});

it('orders by sold_at descending', function () {
    $a = makeRecentSalesItem();
    $b = makeRecentSalesItem();
    $c = makeRecentSalesItem();

    $newest = makeRecentSalesSoldListing($a, ['sold_at' => now()->subMinutes(10)]);
    $middle = makeRecentSalesSoldListing($b, ['sold_at' => now()->subHours(2)]);
    $oldest = makeRecentSalesSoldListing($c, ['sold_at' => now()->subHours(22)]);

    $this->get(route('sales.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('sales/index/page')
            ->has('listings.data', 3)
            ->where('listings.data.0.id', $newest->id)
            ->where('listings.data.1.id', $middle->id)
            ->where('listings.data.2.id', $oldest->id)
        );
});

it('excludes listings sold more than 24 hours ago', function () {
    $a = makeRecentSalesItem();
    $b = makeRecentSalesItem();

    $tooOld = makeRecentSalesSoldListing($a, ['sold_at' => now()->subHours(25)]);
    $recent = makeRecentSalesSoldListing($b, ['sold_at' => now()->subHours(23)]);

    $this->get(route('sales.index'))
        ->assertInertia(function (AssertableInertia $page) use ($tooOld, $recent) {
            $page->has('listings.data', 1);

            $ids = collect($page->toArray()['props']['listings']['data'])->pluck('id')->all();
            expect($ids)->toContain($recent->id);
            expect($ids)->not->toContain($tooOld->id);
        });
});

it('excludes unsold listings', function () {
    $a = makeRecentSalesItem();
    $b = makeRecentSalesItem();

    $unsold = makeRecentSalesSoldListing($a, ['sold_at' => null]);
    $sold = makeRecentSalesSoldListing($b, ['sold_at' => now()->subMinutes(30)]);

    $this->get(route('sales.index'))
        ->assertInertia(function (AssertableInertia $page) use ($unsold, $sold) {
            $page->has('listings.data', 1);

            $ids = collect($page->toArray()['props']['listings']['data'])->pluck('id')->all();
            expect($ids)->toContain($sold->id);
            expect($ids)->not->toContain($unsold->id);
        });
});

it('excludes soft-deleted listings', function () {
    $a = makeRecentSalesItem();
    $b = makeRecentSalesItem();

    $deleted = makeRecentSalesSoldListing($a, ['sold_at' => now()->subMinutes(30)]);
    $deleted->delete();

    $kept = makeRecentSalesSoldListing($b, ['sold_at' => now()->subMinutes(45)]);

    $this->get(route('sales.index'))
        ->assertInertia(function (AssertableInertia $page) use ($deleted, $kept) {
            $page->has('listings.data', 1);

            $ids = collect($page->toArray()['props']['listings']['data'])->pluck('id')->all();
            expect($ids)->toContain($kept->id);
            expect($ids)->not->toContain($deleted->id);
        });
});

it('includes both buy and sell types', function () {
    $a = makeRecentSalesItem();
    $b = makeRecentSalesItem();
    $c = makeRecentSalesItem();
    $d = makeRecentSalesItem();

    $buy1 = makeRecentSalesSoldListing($a, ['type' => 'buy', 'sold_at' => now()->subMinutes(10)]);
    $buy2 = makeRecentSalesSoldListing($b, ['type' => 'buy', 'sold_at' => now()->subMinutes(20)]);
    $sell1 = makeRecentSalesSoldListing($c, ['type' => 'sell', 'sold_at' => now()->subMinutes(30)]);
    $sell2 = makeRecentSalesSoldListing($d, ['type' => 'sell', 'sold_at' => now()->subMinutes(40)]);

    $this->get(route('sales.index'))
        ->assertInertia(function (AssertableInertia $page) use ($buy1, $buy2, $sell1, $sell2) {
            $page->has('listings.data', 4);

            $ids = collect($page->toArray()['props']['listings']['data'])->pluck('id')->all();
            expect($ids)->toContain($buy1->id, $buy2->id, $sell1->id, $sell2->id);
        });
});

it('paginates at 20 per page', function () {
    for ($i = 1; $i <= 25; $i++) {
        $item = makeRecentSalesItem();
        makeRecentSalesSoldListing($item, ['sold_at' => now()->subMinutes($i)]);
    }

    $this->get(route('sales.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('listings.data', 20)
        );

    $this->get(route('sales.index', ['page' => 2]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('listings.data', 5)
        );
});

it('eager-loads offer and item data', function () {
    $item = makeRecentSalesItem();

    makeRecentSalesSoldListing($item, ['price' => 500, 'sold_at' => now()->subMinutes(15)]);

    $coins = Item::where('game_id', 995)->firstOrFail();

    $this->get(route('sales.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('listings.data', 1)
            ->where('listings.data.0.offers.0.items.0.item.id', $coins->id)
            ->where('listings.data.0.offers.0.items.0.quantity', 500)
        );
});
