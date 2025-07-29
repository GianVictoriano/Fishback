<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogRequestHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only log requests to the API
        if ($request->is('api/*')) {
            Log::channel('stderr')->info('--- New API Request ---');
            Log::channel('stderr')->info('Path: ' . $request->path());
            Log::channel('stderr')->info('Method: ' . $request->method());
            
            // Log all headers, paying special attention to Authorization
            Log::channel('stderr')->info('Headers: ', $request->headers->all());

            // Check if the user is already authenticated at this point
            Log::channel('stderr')->info('Authenticated User ID (before Sanctum): ' . auth()->id());
        }

        return $next($request);
    }
}