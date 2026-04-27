<?php

namespace Database\Seeders;

use App\Models\FeaturedItem;
use App\Models\Item;
use App\Models\Listing;
use App\Models\User;
use App\Models\Username;
use Illuminate\Database\QueryException;
use Illuminate\Database\Seeder;

class StatsDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure at least one user/username exists
        $username = Username::query()->inRandomOrder()->first();
        if (!$username) {
            $user = User::factory()->create();
            $username = Username::factory()->create(['user_id' => $user->id]);
        }

        // Pick 30 random listable items
        $items = Item::query()
            ->where('is_active', true)
            ->where('is_listable', true)
            ->inRandomOrder()
            ->limit(30)
            ->get();

        // Track which (item, soldDate) sold-listing IDs we have so historical FeaturedItem can FK to them
        $soldListingsByItem = [];

        Listing::withoutEvents(function () use ($items, $username, &$soldListingsByItem) {
            foreach ($items as $item) {
                $count = random_int(5, 50);
                for ($i = 0; $i < $count; $i++) {
                    $hoursAgo = random_int(0, 100) < 60
                        ? random_int(0, 23)        // 60% in last 24h
                        : random_int(24, 24 * 7);  // 40% in days 1–7
                    $soldAt = now()->subHours($hoursAgo);

                    // ~5% null-price (item-for-item) trades
                    $price = random_int(1, 100) <= 5
                        ? null
                        : (int) round(max(1, $item->cost) * (0.7 + (random_int(0, 600) / 1000)));

                    $listing = Listing::factory()->create([
                        'item_id' => $item->id,
                        'user_id' => $username->user_id,
                        'username' => $username->username,
                        'type' => random_int(0, 1) ? 'buy' : 'sell',
                        'price' => $price,
                        'quantity' => random_int(1, 500),
                        'sold_at' => $soldAt,
                        'updated_at' => $soldAt,
                    ]);

                    $soldListingsByItem[$item->id][] = $listing->id;
                }
            }
        });

        // Seed 5–10 historical featured items spanning 1–90 days ago, each on a distinct day.
        $featuredCount = random_int(5, 10);
        // Distinct day offsets: mix some inside 60d window and some outside
        $availableOffsets = [1, 5, 15, 30, 45, 65, 75, 85, 90, 50];  // 10 options, days ago
        shuffle($availableOffsets);
        $featuredItems = $items->shuffle()->take($featuredCount)->values();

        for ($i = 0; $i < $featuredCount; $i++) {
            $item = $featuredItems[$i];
            $listingId = $soldListingsByItem[$item->id][0] ?? null;
            if (!$listingId) {
                continue;
            }
            $offset = $availableOffsets[$i];
            try {
                FeaturedItem::create([
                    'item_id' => $item->id,
                    'listing_id' => $listingId,
                    'featured_at' => now()->subDays($offset),
                ]);
            } catch (QueryException) {
                // Duplicate featured_date from an earlier seed run — acceptable for SEED DATA ONLY.
            }
        }
    }
}
