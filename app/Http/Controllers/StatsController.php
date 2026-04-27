<?php

namespace App\Http\Controllers;

use App\Services\StatsService;

class StatsController
{
    public function __invoke(StatsService $service)
    {
        return inertia('stats/index/page', $service->getStatsPage());
    }
}
