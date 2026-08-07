<?php

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureModels();
    }

    private function configureModels(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
