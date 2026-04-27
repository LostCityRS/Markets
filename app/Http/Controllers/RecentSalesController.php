<?php

namespace App\Http\Controllers;

use App\Data\Listing\ListingData;
use App\Pages\RecentSalesPage;
use App\Services\RecentSalesService;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

class RecentSalesController
{
    public function __invoke(Request $request, RecentSalesService $service)
    {
        $page = (int) $request->query('page', 1);

        $paginator = $service->fetch($page);

        return inertia('sales/index/page', new RecentSalesPage(
            listings: ListingData::collect($paginator, PaginatedDataCollection::class),
        ));
    }
}
