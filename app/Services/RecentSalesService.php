<?php

namespace App\Services;

use App\Models\Listing;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class RecentSalesService
{
    public function fetch(int $page = 1): LengthAwarePaginator
    {
        return Cache::tags('home_listings')->remember(
            "recent_sales_page_{$page}",
            now()->addMinutes(5),
            fn () => Listing::query()
                ->with('item', 'offers.items.item')
                ->whereNotNull('sold_at')
                ->where('sold_at', '>=', now()->subDays(1))
                ->orderBy('sold_at', 'desc')
                ->paginate(20, ['*'], 'page', $page)
        );
    }
}
