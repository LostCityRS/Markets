<?php

use App\Models\User;

it('can create a banner with event_at, timezone conversion, and computed end_at', function () {
    $admin = User::factory()->admin()->create();

    // 2pm Eastern = 6pm UTC (EDT offset is -4)
    $response = $this->actingAs($admin)->post(route('admin.banners.store'), [
        'type' => 'default',
        'message' => 'Test banner message',
        'is_active' => true,
        'event_at' => '2026-06-15 14:00',
        'details_url' => 'https://example.com/event',
        'timezone' => 'America/New_York',
        'display_scope' => 'global',
    ]);

    $response->assertRedirectToRoute('admin.banners.index');

    $this->assertDatabaseHas('banners', [
        'event_at' => '2026-06-15 18:00:00',
        'end_at' => '2026-06-15 19:00:00',
        'details_url' => 'https://example.com/event',
        'timezone' => 'America/New_York',
        'start_at' => null,
    ]);
});

it('auto-prepends https:// to details_url without scheme', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.banners.store'), [
        'type' => 'default',
        'message' => 'Test banner message',
        'is_active' => true,
        'details_url' => 'example.com/event',
        'display_scope' => 'global',
    ]);

    $response->assertRedirectToRoute('admin.banners.index');

    $this->assertDatabaseHas('banners', [
        'details_url' => 'https://example.com/event',
    ]);
});

it('validates event_at is a valid date', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.banners.store'), [
        'type' => 'default',
        'message' => 'Test banner message',
        'is_active' => true,
        'event_at' => 'garbage',
        'details_url' => null,
        'display_scope' => 'global',
    ]);

    $response->assertSessionHasErrors('event_at');
});

it('can create a banner without event_at and details_url', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.banners.store'), [
        'type' => 'default',
        'message' => 'Test banner message',
        'is_active' => true,
        'display_scope' => 'global',
    ]);

    $response->assertRedirectToRoute('admin.banners.index');

    $this->assertDatabaseHas('banners', [
        'message' => 'Test banner message',
        'event_at' => null,
        'details_url' => null,
        'end_at' => null,
        'start_at' => null,
    ]);
});
