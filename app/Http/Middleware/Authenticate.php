<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Support\Facades\Log;

class Authenticate
{
    protected $auth;

    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    public function handle(Request $request, Closure $next, ...$guards)
    {
        if ($this->auth->guard()->guest()) {
            throw new AuthenticationException('Unauthenticated.', $guards);
        }

        return $next($request);
    }
    protected function redirectTo($request)
{
    if (! $request->expectsJson()) {
        Log::info('Authentication failed', [
            'token' => $request->bearerToken(),
            'headers' => $request->headers->all(),
            'ip' => $request->ip(),
        ]);
        return route('login');
    }
}
}
