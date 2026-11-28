<?php

namespace App\Providers;

use App\Repositories\JsonKurikulumRepository;
use App\Repositories\JsonMataKuliahRepository;
use App\Repositories\KurikulumRepository;
use App\Repositories\MataKuliahRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        if ($this->app->runningInConsole()) {
            $protectDatabase = static function (?string $name = null): void {
                $connection = DB::connection($name);
                $hosts = (array) $connection->getConfig('host');
                $cloud = array_filter($hosts, fn ($host) => str_ends_with(strtolower((string) $host), '.tidbcloud.com'));
                DB::prohibitDestructiveCommands($cloud || $connection->getDatabaseName() === 'sikap_kelompok3');
            };
            $protectDatabase();
            Event::listen(CommandStarting::class, static function (CommandStarting $event) use ($protectDatabase): void {
                if (in_array($event->command, ['db:wipe', 'migrate:fresh', 'migrate:refresh', 'migrate:reset', 'migrate:rollback'], true)) {
                    $protectDatabase($event->input->getParameterOption('--database', null));
                }
            });
        }

        RateLimiter::for('lupa-sandi-kirim', fn ($request) => Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('lupa-sandi', fn ($request) => Limit::perMinute(10)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
    }
}
