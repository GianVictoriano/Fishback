<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UseXAuthorizationHeader
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // OPTIONS pass-through for CORS
        if ($request->isMethod('OPTIONS')) {
            return $next($request);
        }

        // If Authorization exists ensure Bearer format
        if ($request->hasHeader('Authorization')) {
            $token = trim($request->header('Authorization'));
            if ($token && !str_starts_with($token, 'Bearer ')) {
                $request->headers->set('Authorization', 'Bearer '.$token);
            }
        }

        // Fallback to X-Authorization
        if ((!$request->hasHeader('Authorization') || empty(trim($request->header('Authorization')))) && $request->hasHeader('X-Authorization')) {
            $token = trim($request->header('X-Authorization'));
            if (str_starts_with($token, 'Bearer ')) {
                $token = substr($token, 7);
            }
            $request->headers->set('Authorization', 'Bearer '.$token);
        }

        // If still no Authorization header, decide if this route is public
        if (!$request->hasHeader('Authorization') || empty(trim($request->header('Authorization')))) {
            $publicPaths = [
                'api/login',
                'api/auth/google',
                'api/ping',
                'api/forgot-password',
                'api/reset-password',
                'api/topics',
            ];
            // allow /api/topics/* (topic show)
            if (preg_match('#^api/topics/\d+$#', $request->path())) {
                return $next($request);
            }
            if (!in_array($request->path(), $publicPaths)) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                    'error' => 'No valid authentication token provided'
                ], 401);
            }
        }

        return $next($request);
    }
}
