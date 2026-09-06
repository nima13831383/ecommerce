<?php

namespace App\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        Horizon::auth(function (Request $request): bool {
            if ($request->user() === null) {
                return false;
            }

            return Gate::forUser($request->user())->check('viewHorizon');
        });
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null): bool {
            return $user !== null && ! $user->trashed()
                && ($user->hasRole('super-admin') || $user->can('settings.update'));
        });
    }
}
