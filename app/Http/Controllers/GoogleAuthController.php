<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')
            ->scopes(['https://www.googleapis.com/auth/gmail.readonly'])
            ->with([
                'access_type' => 'offline',
                'prompt' => 'consent',
            ])
            ->redirect();
    }

    public function callback()
    {
        $googleUser = Socialite::driver('google')->user();

        // Check whether this Google account has logged in before
        $existingUser = User::where('google_id', $googleUser->getId())->first();

        $userData = [
            'name' => $googleUser->getName(),
            'email' => $googleUser->getEmail(),
            'google_token' => $googleUser->token,
            'google_token_expires_at' => now()->addSeconds($googleUser->expiresIn),
        ];

        if ($googleUser->refreshToken) {
            $userData['google_refresh_token'] = $googleUser->refreshToken;
        }

        // Only fill a placeholder password when this is a brand-new user.
        // They'll never log in with it — Google is the only auth path here.
        if (! $existingUser) {
            $userData['password'] = Hash::make(Str::random(40));
        }

        $user = User::updateOrCreate(
            ['google_id' => $googleUser->getId()],
            $userData
        );

        Auth::login($user);

        return redirect()->route('dashboard');
    }
}