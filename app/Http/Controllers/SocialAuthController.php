<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ConnectedAccount;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

final class SocialAuthController extends Controller
{
    public function connect($provider)
    {
        $driver = Socialite::driver($provider);

        // Add specific scopes and parameters if needed
        if ($provider === 'google') {
            $driver->scopes(['https://www.googleapis.com/auth/calendar'])
                ->with(['access_type' => 'offline', 'prompt' => 'consent select_account']);
        }

        return $driver->redirect();
    }

    public function callback($provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
            $user = Auth::user();

            ConnectedAccount::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'provider' => $provider,
                ],
                [
                    'provider_id' => $socialUser->getId(),
                    'name' => $socialUser->getName(),
                    'nickname' => $socialUser->getNickname(),
                    'email' => $socialUser->getEmail(),
                    'avatar_path' => $socialUser->getAvatar(),
                    'token' => $socialUser->token,
                    'refresh_token' => $socialUser->refreshToken,
                    'expires_at' => property_exists($socialUser, 'expiresIn') ? now()->addSeconds($socialUser->expiresIn) : null,
                ]
            );

            return redirect('/profile')->with('success', ucfirst((string) $provider).' connected successfully!');
        } catch (Exception $e) {
            Log::error(ucfirst((string) $provider).' OAuth Error: '.$e->getMessage());

            return redirect('/profile')->with('error', 'Failed to connect '.ucfirst((string) $provider).'.');
        }
    }

    public function disconnect(Request $request, $provider)
    {
        $user = Auth::user();
        ConnectedAccount::where('user_id', $user->id)->where('provider', $provider)->delete();

        return redirect('/profile')->with('success', ucfirst((string) $provider).' disconnected.');
    }
}
