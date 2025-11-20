<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Google_Client;
use Google_Service_Docs;
use Illuminate\Support\Facades\Log;

class GoogleController extends Controller
{
    public function createDoc(Request $request)
    {
        $request->validate([
            'access_token' => 'required|string',
            'group_id' => 'required',
        ]);

        $accessToken = $request->input('access_token');
        $groupId = $request->input('group_id');

        $client = new Google_Client();
        $client->setAccessToken($accessToken);
        $client->addScope(Google_Service_Docs::DOCUMENTS);
        $client->addScope('https://www.googleapis.com/auth/drive.file');

        if ($client->isAccessTokenExpired()) {
            return response()->json(['error' => 'Google access token expired or invalid. Please sign in again.'], 401);
        }

        $docs = new Google_Service_Docs($client);
        $docTitle = 'Group ' . $groupId . ' Collaboration Doc';
        try {
            $doc = $docs->documents->create([ 'title' => $docTitle ]);
            $docUrl = 'https://docs.google.com/document/d/' . $doc->documentId . '/edit';
            return response()->json(['docUrl' => $docUrl]);
        } catch (\Exception $e) {
            Log::error('Google Doc creation failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Google Doc creation failed: ' . $e->getMessage()], 500);
        }
    }

    public function getAccessToken(Request $request)
    {
        $request->validate(['auth_code' => 'required|string']);

        try {
            \Log::info('Google OAuth request', [
                'auth_code' => $request->input('auth_code'),
                'code_verifier' => $request->input('code_verifier')
            ]);
            $client = new \Google_Client();
            $client->setClientId(config('services.google.client_id'));
            $client->setClientSecret(config('services.google.client_secret'));
            $client->setRedirectUri(config('services.google.redirect_uri'));

            // Exchange auth code for access token
            $tokenData = $client->fetchAccessTokenWithAuthCode(
    $request->input('auth_code'),
    $request->input('code_verifier')
);

            if (isset($tokenData['error'])) {
                Log::error('Google Access Token Exchange Failed', $tokenData);
                return response()->json(['error' => $tokenData], 401);
            }

            $client->setAccessToken($tokenData['access_token']);

            // Get user profile from Google
            $oauth2 = new \Google_Service_Oauth2($client);
            $googleUser = $oauth2->userinfo->get();

            // Check email domain
            if (!str_ends_with($googleUser->email, '@g.batstate-u.edu.ph')) {
                Log::warning('Google Auth: Forbidden email domain.', ['email' => $googleUser->email]);
                return response()->json(['error' => 'Only @g.batstate-u.edu.ph emails are allowed'], 403);
            }

            // Find or create user
            $user = \App\Models\User::firstOrNew(['email' => $googleUser->email]);
            if (!$user->exists) {
                $user->name = $googleUser->name;
                $user->password = \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(24));
                $user->google_id = $googleUser->id;
                $user->email_verified_at = now();
                $user->save();

                // Create profile
                $user->profile()->create([
                    'name' => $googleUser->name,
                    'avatar' => $googleUser->picture,
                ]);
            }

            // Create API token for our application
            $apiToken = $user->createToken('auth_token')->plainTextToken;

            // Return all tokens and user info
            return response()->json([
                'api_token' => $apiToken,
                'user' => $user->load(['profile.modules']),
                'google_access_token' => $tokenData['access_token'],
                'google_refresh_token' => $tokenData['refresh_token'] ?? null,
                'google_expires_in' => $tokenData['expires_in'],
            ]);

        } catch (\Exception $e) {
            Log::error('Google getAccessToken exception', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'An unexpected error occurred during Google authentication.'], 500);
        }
    }
}
