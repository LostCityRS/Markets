<?php

use App\Models\Item;
use App\Models\ListingOfferItem;
use App\Models\User;
use App\Models\Username;

function createTestItems(): array
{
    return [
        Item::factory()->create(['game_id' => 900001]),
        Item::factory()->create(['game_id' => 900002]),
    ];
}

function makeListingPayload(User $user, Item $sellItem, Item $offerItem, float|int $offerQty): array
{
    return [
        'type' => 'sell',
        'quantity' => 100,
        'notes' => null,
        'username' => $user->usernames->first()->username,
        'item_id' => $sellItem->id,
        'offers' => [
            [
                'id' => null,
                'listingId' => null,
                'title' => 'For each item:',
                'items' => [
                    [
                        'id' => null,
                        'listingOfferId' => null,
                        'item_id' => $offerItem->id,
                        'quantity' => $offerQty,
                        'item' => [
                            'id' => $offerItem->id,
                            'game_id' => $offerItem->game_id,
                            'name' => $offerItem->name,
                            'slug' => $offerItem->slug,
                            'cost' => $offerItem->cost,
                        ],
                    ],
                ],
            ],
        ],
    ];
}

it('accepts decimal offer item quantity (1.75)', function () {
    $user = User::factory()->create();
    Username::factory()->create(['user_id' => $user->id]);
    $user->load('usernames');

    [$sellItem, $offerItem] = createTestItems();

    $payload = makeListingPayload($user, $sellItem, $offerItem, 1.75);

    $response = $this->actingAs($user)->post(route('listings.store'), $payload);

    $response->assertSessionHasNoErrors();
    expect(ListingOfferItem::where('item_id', $offerItem->id)->value('quantity'))
        ->toEqual(1.75);
});

it('accepts decimal offer item quantity (0.5)', function () {
    $user = User::factory()->create();
    Username::factory()->create(['user_id' => $user->id]);
    $user->load('usernames');

    [$sellItem, $offerItem] = createTestItems();

    $payload = makeListingPayload($user, $sellItem, $offerItem, 0.5);

    $response = $this->actingAs($user)->post(route('listings.store'), $payload);

    $response->assertSessionHasNoErrors();
    expect(ListingOfferItem::where('item_id', $offerItem->id)->value('quantity'))
        ->toEqual(0.5);
});

it('still accepts integer offer item quantity', function () {
    $user = User::factory()->create();
    Username::factory()->create(['user_id' => $user->id]);
    $user->load('usernames');

    [$sellItem, $offerItem] = createTestItems();

    $payload = makeListingPayload($user, $sellItem, $offerItem, 3);

    $response = $this->actingAs($user)->post(route('listings.store'), $payload);

    $response->assertSessionHasNoErrors();
    expect((float) ListingOfferItem::where('item_id', $offerItem->id)->value('quantity'))
        ->toEqual(3.0);
});

it('rejects offer item quantity of zero', function () {
    $user = User::factory()->create();
    Username::factory()->create(['user_id' => $user->id]);
    $user->load('usernames');

    [$sellItem, $offerItem] = createTestItems();

    $payload = makeListingPayload($user, $sellItem, $offerItem, 0);

    $response = $this->actingAs($user)->post(route('listings.store'), $payload);

    $response->assertSessionHasErrors();
    expect(ListingOfferItem::count())->toBe(0);
});

it('rejects negative offer item quantity', function () {
    $user = User::factory()->create();
    Username::factory()->create(['user_id' => $user->id]);
    $user->load('usernames');

    [$sellItem, $offerItem] = createTestItems();

    $payload = makeListingPayload($user, $sellItem, $offerItem, -1.5);

    $response = $this->actingAs($user)->post(route('listings.store'), $payload);

    $response->assertSessionHasErrors();
    expect(ListingOfferItem::count())->toBe(0);
});

it('keeps sell quantity as integer only', function () {
    $user = User::factory()->create();
    Username::factory()->create(['user_id' => $user->id]);
    $user->load('usernames');

    [$sellItem, $offerItem] = createTestItems();

    $payload = makeListingPayload($user, $sellItem, $offerItem, 2);
    $payload['quantity'] = 1.5;

    $response = $this->actingAs($user)->post(route('listings.store'), $payload);

    $response->assertSessionHasErrors('quantity');
});

it('rejects payload with missing offer item quantity', function () {
    $user = User::factory()->create();
    Username::factory()->create(['user_id' => $user->id]);
    $user->load('usernames');

    [$sellItem, $offerItem] = createTestItems();

    $payload = makeListingPayload($user, $sellItem, $offerItem, 1);
    unset($payload['offers'][0]['items'][0]['quantity']);

    $response = $this->actingAs($user)->post(route('listings.store'), $payload);

    $response->assertSessionHasErrors();
    expect(ListingOfferItem::count())->toBe(0);
});

it('reads offer item quantity as float via Eloquent model', function () {
    $user = User::factory()->create();
    Username::factory()->create(['user_id' => $user->id]);
    $user->load('usernames');

    [$sellItem, $offerItem] = createTestItems();

    $payload = makeListingPayload($user, $sellItem, $offerItem, 1.75);
    $this->actingAs($user)->post(route('listings.store'), $payload)->assertSessionHasNoErrors();

    $model = ListingOfferItem::where('item_id', $offerItem->id)->first();
    expect($model->quantity)->toBeFloat()->toEqual(1.75);
});
