<?php

use App\Models\Item;
use App\Models\User;

it('search by alias returns item', function () {
    $item = Item::factory()->withAliases(['rune scim', 'r scimmy'])->create();

    $response = $this->getJson('/api/items?q=r scimmy');

    $response->assertOk();
    $response->assertJsonFragment(['id' => $item->id]);
});

it('search by name still works', function () {
    $item = Item::factory()->withAliases(['some alias'])->create();

    $response = $this->getJson('/api/items?q=' . urlencode($item->name));

    $response->assertOk();
    $response->assertJsonFragment(['id' => $item->id]);
});

it('search does not return unrelated items', function () {
    $item = Item::factory()->withAliases(['rune scim'])->create();

    $response = $this->getJson('/api/items?q=dragon dagger');

    $response->assertOk();
    $response->assertJsonMissing(['id' => $item->id]);
});

it('admin can save aliases', function () {
    $admin = User::factory()->admin()->create();
    $item = Item::factory()->create();

    $response = $this->actingAs($admin)->patch(route('admin.items.update', $item), [
        'id' => $item->id,
        'name' => $item->name,
        'slug' => $item->slug,
        'cost' => $item->cost,
        'is_active' => $item->is_active,
        'is_listable' => $item->is_listable,
        'search_aliases' => ['rune scim', 'r scimmy'],
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('items', [
        'id' => $item->id,
        'search_aliases' => json_encode(['rune scim', 'r scimmy']),
    ]);
});

it('admin can clear aliases', function () {
    $admin = User::factory()->admin()->create();
    $item = Item::factory()->withAliases(['rune scim', 'r scimmy'])->create();

    $response = $this->actingAs($admin)->patch(route('admin.items.update', $item), [
        'id' => $item->id,
        'name' => $item->name,
        'slug' => $item->slug,
        'cost' => $item->cost,
        'is_active' => $item->is_active,
        'is_listable' => $item->is_listable,
        'search_aliases' => [],
    ]);

    $response->assertRedirect();

    $item->refresh();
    expect($item->search_aliases)->toBeEmpty();
});
