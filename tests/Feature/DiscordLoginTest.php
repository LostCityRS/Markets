<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

function fakeDiscordUser(string $id, string $email, string $name = 'Test User'): void
{
    $socialiteUser = new SocialiteUser;
    $socialiteUser->id = $id;
    $socialiteUser->email = $email;
    $socialiteUser->name = $name;

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')->with('discord')->andReturn($provider);
}

beforeEach(function () {
    Http::fake([
        '*' => Http::response(['usernames' => []], 200),
    ]);
});

it('creates a new user when no match exists', function () {
    fakeDiscordUser('111111', 'newuser@example.com', 'NewUser');

    $this->get(route('auth.discord.callback'))->assertRedirect();

    expect(User::count())->toBe(1);
    $this->assertDatabaseHas('users', [
        'discord_id' => '111111',
        'email' => 'newuser@example.com',
        'name' => 'NewUser',
    ]);
});

it('updates the existing user when discord_id already matches', function () {
    $existing = User::create([
        'name' => 'OldName',
        'email' => 'old@example.com',
        'discord_id' => '222222',
    ]);

    fakeDiscordUser('222222', 'updated@example.com', 'UpdatedName');

    $this->get(route('auth.discord.callback'))->assertRedirect();

    expect(User::count())->toBe(1);
    $this->assertDatabaseHas('users', [
        'id' => $existing->id,
        'discord_id' => '222222',
        'email' => 'updated@example.com',
        'name' => 'UpdatedName',
    ]);
});

it('re-links the existing user by email when discord_id changes', function () {
    $existing = User::create([
        'name' => 'BigBadEth',
        'email' => 'yedolf@example.com',
        'discord_id' => 'old-discord-id',
    ]);

    fakeDiscordUser('new-discord-id', 'yedolf@example.com', 'vvvallah');

    $this->get(route('auth.discord.callback'))->assertRedirect();

    expect(User::count())->toBe(1);
    $this->assertDatabaseHas('users', [
        'id' => $existing->id,
        'email' => 'yedolf@example.com',
        'discord_id' => 'new-discord-id',
        'name' => 'vvvallah',
    ]);
});
