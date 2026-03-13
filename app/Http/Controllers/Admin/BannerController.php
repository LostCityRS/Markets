<?php

namespace App\Http\Controllers\Admin;

use App\Data\Banner\BannerData;
use App\Data\Banner\BannerFormData;
use App\Data\Item\ItemData;
use App\Enums\BannerType;
use App\Http\Traits\HandlesQuerySort;
use App\Models\Banner;
use App\Pages\Admin\BannersCreatePage;
use App\Pages\Admin\BannersIndexPage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\PaginatedDataCollection;

class BannerController
{
    use HandlesQuerySort;

    public function index(Request $request)
    {
        $allowedSorts = ['id', 'message', 'event_at', 'end_at', 'type'];

        $banners = $this->applyFilterSort($request, Banner::query(), $allowedSorts, defaultSort: '-id')
            ->with('items')
            ->paginate(20);

        return inertia('admin/banners/index/page', new BannersIndexPage(
            banners: BannerData::collect($banners, PaginatedDataCollection::class),
        ));
    }

    public function create()
    {
        return inertia('admin/banners/create/page', new BannersCreatePage(
            bannerForm: new BannerFormData(
                id: null,
                type: BannerType::Default,
                message: '',
                is_active: true,
                start_at: null,
                end_at: null,
                event_at: null,
                details_url: null,
                timezone: null,
                items: null,
            )
        ));
    }

    private function prepareBannerData(BannerFormData $data): array
    {
        $bannerData = collect($data->toArray())->except(['items', 'display_scope'])->toArray();

        // Remove start_at — banners without end_at are always visible
        $bannerData['start_at'] = null;

        // Normalize details_url: prepend https:// if missing
        if (!empty($bannerData['details_url'])) {
            $url = $bannerData['details_url'];
            if (!preg_match('#^https?://#i', $url)) {
                $url = 'https://' . $url;
            }
            $bannerData['details_url'] = $url;
        }

        // Convert event_at from selected timezone to UTC, compute end_at = event_at + 1h
        if (!empty($bannerData['event_at']) && !empty($bannerData['timezone'])) {
            $eventInTz = Carbon::parse($bannerData['event_at'], $bannerData['timezone']);
            $eventUtc = $eventInTz->copy()->utc();
            $bannerData['event_at'] = $eventUtc->toDateTimeString();
            $bannerData['end_at'] = $eventUtc->copy()->addHour()->toDateTimeString();
        } elseif (!empty($bannerData['event_at'])) {
            $eventUtc = Carbon::parse($bannerData['event_at'], 'UTC');
            $bannerData['event_at'] = $eventUtc->toDateTimeString();
            $bannerData['end_at'] = $eventUtc->copy()->addHour()->toDateTimeString();
        } else {
            $bannerData['end_at'] = null;
        }

        return $bannerData;
    }

    public function store(BannerFormData $data, Request $request)
    {
        $bannerData = $this->prepareBannerData($data);
        $banner = Banner::create($bannerData);

        if ($data->items) {
            $itemIds = collect($data->items)->pluck('id')->toArray();
            $banner->items()->sync(collect($data->items)->pluck('id')->toArray());

            activity()
                ->performedOn($banner)
                ->causedBy($request->user())
                ->withProperties([
                    'added_items' => $itemIds,
                ])
                ->log('create banner items');
        }

        return to_route('admin.banners.index')->success('Banner created successfully.');
    }

    public function edit(Banner $banner)
    {
        $eventAt = $banner->event_at;
        $timezone = $banner->timezone;

        // Convert event_at from UTC back to the stored timezone for the form
        if ($eventAt && $timezone) {
            $eventAt = Carbon::parse($eventAt)->timezone($timezone)->format('Y-m-d\TH:i');
        } elseif ($eventAt) {
            $eventAt = Carbon::parse($eventAt)->format('Y-m-d\TH:i');
        }

        return inertia('admin/banners/create/page', new BannersCreatePage(
            bannerForm: new BannerFormData(
                id: $banner->id,
                type: BannerType::from($banner->type),
                message: $banner->message,
                is_active: $banner->is_active,
                start_at: $banner->start_at,
                end_at: $banner->end_at,
                event_at: $eventAt,
                details_url: $banner->details_url,
                timezone: $timezone,
                items: ItemData::collect($banner->items, DataCollection::class),
            )
        ));
    }

    public function update(BannerFormData $data, Banner $banner, Request $request)
    {
        $bannerData = $this->prepareBannerData($data);
        $banner->update($bannerData);

        $originalItemIds = $banner->items()->pluck('items.id')->toArray();

        if ($data->items) {
            $newItemIds = collect($data->items)->pluck('id')->toArray();
            $banner->items()->sync(collect($data->items)->pluck('id')->toArray());
        } else {
            $banner->items()->detach();
            $newItemIds = [];
        }

        $added = array_values(array_diff($newItemIds, $originalItemIds));
        $removed = array_values(array_diff($originalItemIds, $newItemIds));

        if (!empty($added) || !empty($removed)) {
            activity()
                ->performedOn($banner)
                ->causedBy($request->user())
                ->withProperties([
                    'added_items' => $added,
                    'removed_items' => $removed,
                ])
                ->log('update banner items');
        }

        return to_route('admin.banners.index')->success('Banner updated successfully.');
    }

    public function destroy(Banner $banner)
    {
        $banner->delete();

        return to_route('admin.banners.index')->success('Banner deleted successfully.');
    }
}
