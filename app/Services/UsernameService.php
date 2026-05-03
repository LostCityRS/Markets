<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\User;
use App\Models\Username;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tymon\JWTAuth\Facades\JWTAuth;

class UsernameService
{
    public static function updateUsernamesForUser(User $user): void
    {
        Cache::tags('usernames')->forget('usernames_' . $user->id);

        $jwtToken = JWTAuth::fromUser($user);

        $response = Http::withToken($jwtToken)
            ->get(config('services.username_api.url'), [
                'email' => $user->email
            ]);

        if ($response->failed()) {
            throw new \Exception('Failed to fetch usernames');
        }
        $usernames = $response->json('usernames');

        Username::where('user_id', $user->id)
            ->whereNotIn('username', $usernames)
            ->delete();

        foreach ($usernames as $username) {
            Username::updateOrCreate(
                ['username' => $username],
                ['user_id' => $user->id],
            );

            Listing::withoutTimestamps(function () use ($username, $user) {
                Listing::whereRaw("LOWER(REPLACE(username, ' ', '_')) = LOWER(?)", [$username])
                    ->update(['user_id' => $user->id, 'username' => $username]);
            });
        }
    }

    public static function getAuthenticatedUsernames(): array
    {
        return Cache::tags('usernames')->rememberForever('usernames_' . Auth::id(), function () {
            return Auth::user()?->usernames->pluck('username')->toArray() ?? [];
        });
    }
}
