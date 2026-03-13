<?php

use App\Data\Banner\BannerData;
use App\Models\Banner;
use Spatie\LaravelData\DataCollection;

it('serializes eventAt and detailsUrl in BannerData', function () {
    $banner = Banner::factory()->create([
        'is_active' => true,
        'event_at' => '2026-06-15 14:00:00',
        'details_url' => 'https://example.com/event',
    ]);

    $data = BannerData::from($banner);

    expect($data->eventAt)->not->toBeNull();
    expect($data->eventAt->format('Y-m-d H:i:s'))->toBe('2026-06-15 14:00:00');
    expect($data->detailsUrl)->toBe('https://example.com/event');
});

it('serializes null event fields when not set', function () {
    $banner = Banner::factory()->create([
        'is_active' => true,
        'event_at' => null,
        'details_url' => null,
    ]);

    $data = BannerData::from($banner);

    expect($data->eventAt)->toBeNull();
    expect($data->detailsUrl)->toBeNull();
});

it('includes new fields in active global banner query', function () {
    Banner::factory()->create([
        'is_active' => true,
        'event_at' => '2026-06-15 14:00:00',
        'details_url' => 'https://example.com/event',
    ]);

    $banners = BannerData::collect(Banner::active()->global()->get(), DataCollection::class);

    expect($banners)->toHaveCount(1);
    expect($banners[0]->eventAt)->not->toBeNull();
    expect($banners[0]->detailsUrl)->toBe('https://example.com/event');
});
