<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

function labsHandshake(): array
{
    $verifier = bin2hex(random_bytes(32));

    return ['verifier' => $verifier, 'state' => bin2hex(random_bytes(32)), 'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=')];
}
function labsCode($test, array $h): string
{
    $response = $test->get(route('auth.labs', ['state' => $h['state'], 'code_challenge' => $h['code_challenge']]));
    $response->assertRedirect();
    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
    expect($query['state'])->toBe($h['state']);
    expect($response->headers->get('Cache-Control'))->toContain('no-store');

    return $query['code'];
}
beforeEach(function () {
    Http::fake(['*' => Http::response(['usernames' => ['zakum', 'pasta']], 200)]);
});
it('continues guest sign-in through the existing Discord flow', function () {
    $h = labsHandshake();
    $this->get(route('auth.labs', ['state' => $h['state'], 'code_challenge' => $h['code_challenge']]))
        ->assertRedirect(route('auth.discord'))->assertSessionHas('labs_auth.state', $h['state']);
});
it('exchanges a short-lived PKCE code once, exposing only linked character identity', function () {
    $user = User::create(['name' => 'Labs Test', 'email' => 'labs@example.test', 'discord_id' => '111111111111111111']);
    $this->actingAs($user);
    $h = labsHandshake();
    $code = labsCode($this, $h);
    $this->postJson(route('labs.token'), ['code' => $code, 'code_verifier' => str_repeat('x', 64)])->assertStatus(400);
    $this->postJson(route('labs.token'), ['code' => $code, 'code_verifier' => $h['verifier']])->assertOk()
        ->assertExactJson(['discord_id' => '111111111111111111', 'usernames' => ['pasta', 'zakum']]);
    $this->postJson(route('labs.token'), ['code' => $code, 'code_verifier' => $h['verifier']])->assertStatus(400);
});
it('does not mint a grant if authoritative character lookup fails', function () {
    Http::swap(new \Illuminate\Http\Client\Factory);
    Http::fake(['*' => Http::response([], 503)]);
    $user = User::create(['name' => 'Labs Test', 'email' => 'labs@example.test', 'discord_id' => '111111111111111111']);
    $this->actingAs($user);
    $h = labsHandshake();
    $this->withoutExceptionHandling();
    expect(fn () => $this->get(route('auth.labs', ['state' => $h['state'], 'code_challenge' => $h['code_challenge']])))
        ->toThrow(Exception::class, 'Failed to fetch usernames');
});
it('rejects expired grants and malformed handshakes', function () {
    $h = labsHandshake();
    $this->getJson(route('auth.labs', ['state' => 'invalid', 'code_challenge' => $h['code_challenge']]))->assertStatus(422);
    $this->postJson(route('labs.token'), ['code' => str_repeat('a', 64), 'code_verifier' => $h['verifier']])->assertStatus(400);
});
it('does not send email or Discord tokens to Labs and uses a fixed return URL', function () {
    $user = User::create(['name' => 'Labs Test', 'email' => 'labs@example.test', 'discord_id' => '111111111111111111']);
    $this->actingAs($user);
    $h = labsHandshake();
    $code = labsCode($this, $h);
    $this->postJson(route('labs.token'), ['code' => $code, 'code_verifier' => $h['verifier']])->assertJsonMissingPath('email')->assertJsonMissingPath('token');
    expect(config('services.labs.redirect'))->toBe('https://labs.lostcity.rs/login/callback');
});
it('returns a newly signed-in Discord user directly to Labs after one character lookup', function () {
    $h = labsHandshake();
    $this->get(route('auth.labs', ['state' => $h['state'], 'code_challenge' => $h['code_challenge']]));
    $identity = new \Laravel\Socialite\Two\User;
    $identity->id = '111111111111111111';
    $identity->email = 'labs@example.test';
    $identity->name = 'Labs Test';
    $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
    $provider->shouldReceive('user')->andReturn($identity);
    \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')->with('discord')->andReturn($provider);
    $response = $this->get(route('auth.discord.callback'));
    expect($response->headers->get('Location'))->toStartWith(config('services.labs.redirect') . '?');
    $response->assertSessionMissing('labs_auth');
    Http::assertSentCount(1);
});

it('refuses Market-banned users', function () {
    $user = User::create(['name' => 'Labs Test', 'email' => 'labs@example.test', 'discord_id' => '111111111111111111', 'is_banned' => true]);
    $this->actingAs($user);
    $h = labsHandshake();
    $this->get(route('auth.labs', ['state' => $h['state'], 'code_challenge' => $h['code_challenge']]))->assertForbidden();
});
