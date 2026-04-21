<?php

use App\Models\Item;
use App\Models\Listing;
use App\Models\User;
use App\Models\Username;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    Item::factory()->create(['game_id' => 900101]);
});

function setUpdatedAt(Listing $listing, \Carbon\Carbon $when): void
{
    DB::table('listings')->where('id', $listing->id)->update(['updated_at' => $when]);
}

function createAuthenticatedUser(): User
{
    $user = User::factory()->create();
    Username::factory()->create(['user_id' => $user->id]);
    $user->load('usernames');

    return $user;
}

function makeListingFor(User $user, array $overrides = []): Listing
{
    $item = Item::first();

    return Listing::factory()->create(array_merge([
        'user_id' => $user->id,
        'item_id' => $item->id,
        'username' => $user->usernames->first()->username,
    ], $overrides));
}

it('bumps expired listings (between 7 days and 1 day old)', function () {
    $user = createAuthenticatedUser();
    $this->actingAs($user);

    $expired = makeListingFor($user);
    setUpdatedAt($expired, now()->subDays(3));

    $this->patch(route('listings.bump.expired'));

    expect($expired->fresh()->updated_at->diffInMinutes(now()))->toBeLessThan(1);
});

it('does not bump listings younger than 1 day', function () {
    $user = createAuthenticatedUser();
    $this->actingAs($user);

    $stale = makeListingFor($user);
    $when = now()->subHours(5)->startOfSecond();
    setUpdatedAt($stale, $when);

    $this->patch(route('listings.bump.expired'));

    expect($stale->fresh()->updated_at->timestamp)->toBe($when->timestamp);
});

it('does not bump listings older than 7 days', function () {
    $user = createAuthenticatedUser();
    $this->actingAs($user);

    $ancient = makeListingFor($user);
    $when = now()->subDays(10)->startOfSecond();
    setUpdatedAt($ancient, $when);

    $this->patch(route('listings.bump.expired'));

    expect($ancient->fresh()->updated_at->timestamp)->toBe($when->timestamp);
});

it('does not bump sold listings', function () {
    $user = createAuthenticatedUser();
    $this->actingAs($user);

    $sold = makeListingFor($user, ['sold_at' => now()->subDays(3)]);
    $when = now()->subDays(3)->startOfSecond();
    setUpdatedAt($sold, $when);

    $this->patch(route('listings.bump.expired'));

    expect($sold->fresh()->updated_at->timestamp)->toBe($when->timestamp);
});

it('does not bump paused listings', function () {
    $user = createAuthenticatedUser();
    $this->actingAs($user);

    $paused = makeListingFor($user, ['paused_at' => now()->subDays(3)]);
    $when = now()->subDays(3)->startOfSecond();
    setUpdatedAt($paused, $when);

    $this->patch(route('listings.bump.expired'));

    expect($paused->fresh()->updated_at->timestamp)->toBe($when->timestamp);
});

it('does not bump another user\'s expired listings', function () {
    $user = createAuthenticatedUser();
    $otherUser = createAuthenticatedUser();

    $this->actingAs($otherUser);
    $othersExpired = makeListingFor($otherUser);
    $when = now()->subDays(3)->startOfSecond();
    setUpdatedAt($othersExpired, $when);

    $this->actingAs($user)->patch(route('listings.bump.expired'));

    expect($othersExpired->fresh()->updated_at->timestamp)->toBe($when->timestamp);
});

it('redirects back when no expired listings found', function () {
    $user = createAuthenticatedUser();

    $response = $this->actingAs($user)
        ->from(route('listings.index'))
        ->patch(route('listings.bump.expired'));

    $response->assertRedirect(route('listings.index'));
});
