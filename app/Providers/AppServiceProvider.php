<?php

namespace App\Providers;

use App\Models\FeeReceipt;
use App\Models\Tenant;
use App\Models\PersonalAccessToken;
use App\Observers\FeeReceiptObserver;
use App\Observers\TenantObserver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Stancl\Tenancy\DatabaseConfig;

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
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        ResetPassword::createUrlUsing(function (object $user, string $token) {
            return route('portal.password.reset', [
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);
        });

        Tenant::observe(TenantObserver::class);
        FeeReceipt::observe(FeeReceiptObserver::class);

        if ($this->app->environment('testing') && ! config('tenancy.database_per_sahodaya', true)) {
            $this->loadMigrationsFrom(database_path('migrations/tenant'));
        }

        DatabaseConfig::generateDatabaseNamesUsing(function (Tenant $tenant) {
            if ($tenant->type === 'school' && $tenant->parent_id) {
                $parent = Tenant::query()->find($tenant->parent_id);

                if ($parent?->type === 'sahodaya') {
                    $parent->database()->makeCredentials();

                    return $parent->database()->getName();
                }
            }

            $key = str_replace('-', '_', $tenant->getTenantKey());

            return config('tenancy.database.prefix').$key.config('tenancy.database.suffix');
        });
    }
}
