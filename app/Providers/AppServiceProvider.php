<?php

namespace App\Providers;

use App\Models\Company;
use App\Models\CompanyUpdate;
use App\Models\InventoryProduct;
use App\Observers\InventoryProductObserver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (! function_exists('fmt_num')) {
            require_once base_path('app/helpers.php');
        }

        $this->ensureStorageDirectories();
    }

    private function ensureStorageDirectories(): void
    {
        foreach ([
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
            storage_path('app/public'),
        ] as $dir) {
            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        InventoryProduct::observe(InventoryProductObserver::class);

        $this->applyProductionSecurity();

        Route::bind('update', function (string $value, $route) {
            $company = $route->parameter('company');
            if ($company instanceof Company) {
                return CompanyUpdate::query()
                    ->where('company_id', $company->id)
                    ->whereKey($value)
                    ->firstOrFail();
            }

            return CompanyUpdate::query()->whereKey($value)->firstOrFail();
        });

        if ($this->app->runningInConsole() || ! $this->app->environment('local')) {
            return;
        }

        $this->app->booted(function () {
            if ($this->app->runningInConsole()) {
                return;
            }

            $request = $this->app->make('request');
            if (! $request instanceof Request || ! $request->getHttpHost()) {
                return;
            }

            $host = $request->getHost();
            $isSignatureHost = $host === 'signature.test'
                || $host === 'www.signature.test'
                || str_ends_with($host, '.signature.test');

            if (! $isSignatureHost && ! $this->isLocalNetworkHost($host)) {
                return;
            }

            if (! $isSignatureHost && ! $this->app->environment('local')) {
                return;
            }

            $root = $request->getSchemeAndHttpHost().rtrim($request->getBaseUrl(), '/');
            URL::forceRootUrl($root);
        });
    }

    private function isLocalNetworkHost(string $host): bool
    {
        if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return ! filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
        }

        return str_ends_with($host, '.test') || str_ends_with($host, '.local');
    }

    private function applyProductionSecurity(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
            $this->removeInstallerWhenLocked();
        }
    }

    private function removeInstallerWhenLocked(): void
    {
        $lock = storage_path('app'.DIRECTORY_SEPARATOR.'installer.lock');
        $installer = public_path('install.php');

        if (is_file($lock) && is_file($installer)) {
            @unlink($installer);
        }
    }
}
