<?php

namespace App\Data\Item;

use App\Data\Banner\BannerData;
use App\Models\Item;
use Illuminate\Support\Facades\Auth;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class ItemData extends Data
{
    #[Computed]
    public bool $isFavorite;

    public function __construct(
        public int $id,
        public int $game_id,
        public string $name,
        public string $slug,
        public int $cost,
        public bool $isSet,
        /** @var DataCollection<\App\Data\Banner\BannerData>|null */
        public ?DataCollection $banners,
        /** @var DataCollection<ItemSetComponentData>|null */
        public ?DataCollection $setComponents = null,
    ) {
        $this->isFavorite = self::determineIsFavorite($id);
    }

    public static function fromModel(Item $item): self
    {
        $components = null;

        if ($item->is_set) {
            $item->loadMissing('setComponents.item');
            $components = ItemSetComponentData::collect(
                $item->setComponents->all(),
                DataCollection::class,
            );
        }

        $banners = null;

        if ($item->relationLoaded('banners')) {
            $banners = BannerData::collect($item->banners, DataCollection::class);
        }

        return new self(
            id: $item->id,
            game_id: $item->game_id,
            name: $item->name,
            slug: $item->slug,
            cost: $item->cost,
            isSet: (bool) $item->is_set,
            banners: $banners,
            setComponents: $components,
        );
    }

    private static function determineIsFavorite(int $id): bool
    {
        return Auth::check() && Auth::user()->favorites()->pluck('id')->contains($id);
    }
}
