<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.']
            ]);
        }

        return response()->json([
            'token' => $user->createToken('mobile-token')->plainTextToken,
            'user' => $user->load('profile'),
        ]);
    }

    public function handleGoogleCallback(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        try {
            $googleUser = Socialite::driver('google')->userFromToken($request->token);

            // Find user by email, or create them if they don't exist.
            $user = User::firstOrNew(['email' => $googleUser->getEmail()]);

            // This block only runs if the user is being created for the first time.
            if (!$user->exists) {
                $user->name = $googleUser->getName();
                $user->password = Hash::make(Str::random(24)); // Set a random password ONCE
                $user->google_id = $googleUser->getId();
                $user->save(); // Save the new user
            } else {
                // If user exists, just ensure their google_id is set if it's missing
                if (!$user->google_id) {
                    $user->google_id = $googleUser->getId();
                    $user->save();
                }
            }

            // Create a profile if one doesn't exist
            if (!$user->profile) {
                $user->profile()->create([
                    'name' => $googleUser->getName(),
                    'avatar' => $googleUser->getAvatar(),
                ]);
            }

            return response()->json([
                'token' => $user->createToken('google-token')->plainTextToken,
                'user' => $user->fresh()->load('profile'),
            ]);
        } catch (\Exception $e) {
            Log::error('Google Auth Error: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid credentials provided. Could not log in with Google.'], 401);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Successfully logged out']);
    }
}