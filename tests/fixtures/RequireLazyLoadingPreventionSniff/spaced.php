<?php

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Model:: /* the method */ preventLazyLoading(! $this->app->isProduction());
    }
}

class ModelServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Model::preventLazyLoading /* the arguments */ (! $this->app->isProduction());
    }
}
