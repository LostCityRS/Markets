<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\UsernameService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class LabsController extends Controller
{
    public function authorize(Request $request)
    {
        $handshake = $request->validate([
            'state' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'code_challenge' => ['required', 'string', 'regex:/\A[A-Za-z0-9_-]{43}\z/'],
        ]);
        $handshake['at'] = time();
        if (! Auth::check()) {
            $request->session()->put('labs_auth', $handshake);

            return redirect()->route('auth.discord')->header('Cache-Control', 'no-store');
        }
        UsernameService::updateUsernamesForUser($request->user());

        return $this->complete($request, $handshake);
    }

    public function complete(Request $request, array $handshake)
    {
        abort_unless(isset($handshake['state'], $handshake['code_challenge'], $handshake['at']) && time() - $handshake['at'] <= 300, 400, 'Labs sign-in expired; please start again.');
        $user = $request->user();
        abort_unless($user && ! $user->is_banned && preg_match('/\A[0-9]{1,20}\z/', (string) $user->discord_id), 403);
        $code = bin2hex(random_bytes(32));
        $stored = Cache::store('redis')->put('labs-sso:' . hash('sha256', $code), [
            'challenge' => $handshake['code_challenge'],
            'discord_id' => (string) $user->discord_id,
            'usernames' => $user->usernames()->orderBy('username')->pluck('username')->all(),
        ], 60);
        if (! $stored) {
            throw new \RuntimeException('Could not store Labs authorization.');
        }
        $redirect = config('services.labs.redirect');
        if (! is_string($redirect) || $redirect === '') {
            throw new \RuntimeException('Labs callback is not configured.');
        }

        return redirect()->away($redirect . '?' . http_build_query(['code' => $code, 'state' => $handshake['state']]))
            ->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function token(Request $request)
    {
        $input = $request->validate([
            'code' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'code_verifier' => ['required', 'string', 'regex:/\A[A-Za-z0-9_-]{43,128}\z/'],
        ]);
        $cache = Cache::store('redis');
        $key = 'labs-sso:' . hash('sha256', $input['code']);

        return $cache->lock($key . ':lock', 10)->block(2, function () use ($cache, $key, $input) {
            $grant = $cache->get($key);
            $challenge = rtrim(strtr(base64_encode(hash('sha256', $input['code_verifier'], true)), '+/', '-_'), '=');
            abort_unless(is_array($grant) && hash_equals($grant['challenge'], $challenge), 400, 'Invalid or expired Labs authorization code.');
            $cache->forget($key);

            return response()->json(['discord_id' => $grant['discord_id'], 'usernames' => $grant['usernames']])
                ->header('Cache-Control', 'no-store');
        });
    }
}
