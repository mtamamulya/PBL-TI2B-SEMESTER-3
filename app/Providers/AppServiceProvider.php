<?php

namespace App\Providers;

use App\Repositories\JsonKurikulumRepository;
use App\Repositories\JsonMataKuliahRepository;
use App\Repositories\KurikulumRepository;
use App\Repositories\MataKuliahRepository;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {

        // TODO-DB: ganti ke EloquentKurikulumRepository / EloquentMataKuliahRepository saat database dipakai
        $this->app->bind(
            KurikulumRepository::class,
            JsonKurikulumRepository::class
        );
        $this->app->bind(
            MataKuliahRepository::class,
            JsonMataKuliahRepository::class
        );

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('lupa-sandi-kirim', fn ($request) =>
            Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('lupa-sandi', fn ($request) =>
            Limit::perMinute(10)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
    }
}
