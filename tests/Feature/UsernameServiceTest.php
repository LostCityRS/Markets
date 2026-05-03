<?php

use App\Models\Item;
use App\Models\Listing;
use App\Models\User;
use App\Models\Username;
use App\Services\UsernameService;
use Illuminate\Support\Facades\Http;

function fakeUsernameApi(array $usernames): void
{
    Http::fake([
        '*' => Http::response(['usernames' => $usernames], 200),
    ]);
}

it('transfers a username from another user when the API now attributes it to this user', function () {
    $previousOwner = User::factory()->create();
    $newOwner = User::factory()->create();

    Username::create(['user_id' => $previousOwner->id, 'username' => 'bigbadeth']);

    fakeUsernameApi(['bigbadeth']);

    UsernameService::updateUsernamesForUser($newOwner);

    $this->assertDatabaseHas('usernames', [
        'username' => 'bigbadeth',
        'user_id' => $newOwner->id,
    ]);
    expect(Username::where('username', 'bigbadeth')->count())->toBe(1);
    expect($previousOwner->fresh()->usernames)->toBeEmpty();
});

it('claims multiple usernames for one user', function () {
    $user = User::factory()->create();

    fakeUsernameApi(['acc1', 'acc2', 'acc3']);

    UsernameService::updateUsernamesForUser($user);

    expect($user->fresh()->usernames->pluck('username')->sort()->values()->all())
        ->toBe(['acc1', 'acc2', 'acc3']);
});

it('removes usernames the API no longer attributes to the user', function () {
    $user = User::factory()->create();
    Username::create(['user_id' => $user->id, 'username' => 'keeper']);
    Username::create(['user_id' => $user->id, 'username' => 'stale']);

    fakeUsernameApi(['keeper']);

    UsernameService::updateUsernamesForUser($user);

    expect($user->fresh()->usernames->pluck('username')->all())->toBe(['keeper']);
});

it('reassigns listings to the user when their username is claimed', function () {
    $item = Item::factory()->create();
    $previousOwner = User::factory()->create();
    $newOwner = User::factory()->create();

    Username::create(['user_id' => $previousOwner->id, 'username' => 'bigbadeth']);
    $listing = Listing::factory()->create([
        'user_id' => $previousOwner->id,
        'username' => 'BigBadEth',
        'item_id' => $item->id,
    ]);

    fakeUsernameApi(['bigbadeth']);

    UsernameService::updateUsernamesForUser($newOwner);

    expect($listing->fresh()->user_id)->toBe($newOwner->id);
    expect($listing->fresh()->username)->toBe('bigbadeth');
});
