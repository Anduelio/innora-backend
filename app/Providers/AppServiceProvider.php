<?php

namespace App\Providers;

use App\Channels\Contracts\ChannelProvider;
use App\Channels\Providers\NullChannelProvider;
use App\Enums\UserRole;
use App\Models\User;
use App\Singletons\DataManager;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DataManager::class);
        $this->app->bind(ChannelProvider::class, NullChannelProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();

        Passport::enablePasswordGrant();
        Passport::tokensExpireIn(Carbon::now()->addDays(1));
        Passport::refreshTokensExpireIn(Carbon::now()->addDays(10));

        Gate::before(function (User $user) {
            return $user->role?->code === UserRole::Owner->value ? true : null;
        });
    }
}
