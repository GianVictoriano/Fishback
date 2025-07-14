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
use Google_Client;

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
        try {
            $token = $request->input('token');
            Log::info('Google Auth: Received token.', ['token' => $token ? 'Exists' : 'Missing']);

            if (!$token) {
                Log::error('Google Auth: No token provided in the request.');
                return response()->json(['error' => 'No token provided'], 400);
            }

            $clientId = config('services.google.client_id');
            Log::info('Google Auth: Using Client ID for verification.', ['client_id' => $clientId]);

            if (!$clientId) {
                Log::critical('Google Auth: GOOGLE_CLIENT_ID is not set in config/services.php or .env file.');
                return response()->json(['error' => 'Server configuration error.'], 500);
            }

            $client = new \Google_Client(['client_id' => $clientId]);
            $payload = $client->verifyIdToken($token);

            if (!$payload) {
                Log::error('Google Auth: Token verification failed. The token is invalid.', ['token' => $token]);
                return response()->json(['error' => 'Invalid token'], 401);
            }

            Log::info('Google Auth: Token successfully verified.', ['payload' => $payload]);

            // Check if email domain is allowed
            $email = $payload['email'] ?? null;
            if (!str_ends_with($email, '@g.batstate-u.edu.ph')) {
                Log::warning('Google Auth: Forbidden email domain.', ['email' => $email]);
                return response()->json(['error' => 'Only @g.batstate-u.edu.ph emails are allowed'], 403);
            }

            // Find or create user
            $user = User::firstOrNew(['email' => $email]);
            Log::info('Google Auth: User lookup.', ['email' => $email, 'user_exists' => $user->exists]);

            if (!$user->exists) {
                $user->name = $payload['name'] ?? $payload['email'];
                $user->password = Hash::make(Str::random(24));
                $user->google_id = $payload['sub'];
                $user->email_verified_at = now();
                $user->save();
                Log::info('Google Auth: New user created.', ['user_id' => $user->id]);

                // Create profile
                $user->profile()->create([
                    'name' => $payload['name'] ?? $payload['email'],
                    'avatar' => $payload['picture'] ?? null,
                ]);
                Log::info('Google Auth: New profile created for user.', ['user_id' => $user->id]);
            }

            // Create token
            $apiToken = $user->createToken('auth_token')->plainTextToken;
            Log::info('Google Auth: API token created for user.', ['user_id' => $user->id]);

            return response()->json([
                'token' => $apiToken,
                'token_type' => 'Bearer',
                'user' => $user->load('profile')
            ]);

        } catch (\Exception $e) {
            Log::error('Google Auth: An exception occurred.', [
                'error_message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json(['error' => 'Authentication failed: ' . $e->getMessage()], 500);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Successfully logged out']);
    }
}