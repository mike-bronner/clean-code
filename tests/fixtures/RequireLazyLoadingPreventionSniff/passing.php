<?php

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Clock::class, SystemClock::class);
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->routes();
    }
}

class User extends Model
{
    protected $with = ['profile'];
}
