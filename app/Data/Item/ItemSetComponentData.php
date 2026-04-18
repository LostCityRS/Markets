<?php

namespace App\Data\Item;

use App\Models\ItemSetComponent;
use Spatie\LaravelData\Data;

class ItemSetComponentData extends Data
{
    public function __construct(
        public int $quantity,
        public ItemFormData $item,
    ) {}

    public static function fromModel(ItemSetComponent $component): self
    {
        return new self(
            quantity: $component->quantity,
            item: ItemFormData::from($component->item),
        );
    }
}
