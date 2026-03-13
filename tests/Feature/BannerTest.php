<?php

use App\Models\User;

it('can create a banner with event_at and details_url', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.banners.store'), [
        'type' => 'default',
        'message' => 'Test banner message',
        'is_active' => true,
        'start_at' => null,
        'end_at' => null,
        'event_at' => '2026-06-15 14:00:00',
        'details_url' => 'https://example.com/event',
        'display_scope' => 'global',
    ]);

    $response->assertRedirectToRoute('admin.banners.index');

    $this->assertDatabaseHas('banners', [
        'event_at' => '2026-06-15 14:00:00',
        'details_url' => 'https://example.com/event',
    ]);
});

it('validates details_url is a valid url', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.banners.store'), [
        'type' => 'default',
        'message' => 'Test banner message',
        'is_active' => true,
        'start_at' => null,
        'end_at' => null,
        'event_at' => null,
        'details_url' => 'not-a-url',
        'display_scope' => 'global',
    ]);

    $response->assertSessionHasErrors('details_url');
});

it('validates event_at is a valid date', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('admin.banners.store'), [
        'type' => 'default',
        'message' => 'Test banner message',
        'is_active' => true,
        'start_at' => null,
        'end_at' => null,
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
        'start_at' => null,
        'end_at' => null,
        'display_scope' => 'global',
    ]);

    $response->assertRedirectToRoute('admin.banners.index');

    $this->assertDatabaseHas('banners', [
        'message' => 'Test banner message',
        'event_at' => null,
        'details_url' => null,
    ]);
});
