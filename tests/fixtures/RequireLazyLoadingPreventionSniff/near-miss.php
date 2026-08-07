<?php

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Model::preventLazyLoading(! $this->app->isProduction());
        $reminder = 'Model::shouldBeStrict();';
        $this->preventLazyLoading();
        preventLazyLoading();
        $flag = Model::preventLazyLoading;
    }
}
