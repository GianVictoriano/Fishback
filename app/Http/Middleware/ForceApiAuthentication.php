<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

class ForceApiAuthentication
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if ($token) {
            Log::info('[ForceApiAuthentication] Bearer token found in request.');
            
            // Find the token in the database
            $accessToken = PersonalAccessToken::findToken($token);

            if ($accessToken) {
                Log::info('[ForceApiAuthentication] Token is valid. Authenticating user.', ['user_id' => $accessToken->tokenable_id]);
                // Manually authenticate the user for this request
                Auth::login($accessToken->tokenable);
            } else {
                Log::warning('[ForceApiAuthentication] Bearer token provided was invalid.');
            }
        } else {
            Log::info('[ForceApiAuthentication] No bearer token found in request.');
        }

        return $next($request);
    }
}
