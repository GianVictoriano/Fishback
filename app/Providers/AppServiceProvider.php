<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Models\Profile;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('view-module-management', function (User $user) {
            return $user->profile && in_array($user->profile->level, [2, 3]);
        });

        Gate::define('assign-modules', function (User $user, Profile $targetProfile) {
    if (!$user->profile) {
        return false;
    }

    $userLevel = $user->profile->level;
    $targetLevel = $targetProfile->level;

    // Allow Level 3 to manage Level 1 & 2, and themselves
    if ($userLevel == 3 && (in_array($targetLevel, [1, 2]) || $user->profile->id === $targetProfile->id)) {
        return true;
    }

    // Allow Level 2 to manage Level 1 (add self-management for Level 2 if desired)
    if ($userLevel == 2 && $targetLevel == 1) {
        return true;
    }

    return false;
});
    }
}
