<?php

namespace Shapperize\Activation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Shapperize\Activation\Contracts\StepGate;
use Shapperize\Activation\Gates\AllowAllStepGate;
use Shapperize\Activation\Support\StepRegistry;

class ActivationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/activation.php', 'activation');

        $this->app->singleton(StepRegistry::class);

        if (! $this->app->bound(StepGate::class)) {
            $this->app->singleton(StepGate::class, AllowAllStepGate::class);
        }
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/activation.php' => $this->app->configPath('activation.php'),
            ], 'activation-config');

            $this->publishes([
                __DIR__.'/../database/migrations/create_activation_tour_progress_table.php.stub' => $this->app->databasePath('migrations/'.date('Y_m_d_His', time()).'_create_activation_tour_progress_table.php'),
                __DIR__.'/../database/migrations/add_activation_columns_to_subject_table.php.stub' => $this->app->databasePath('migrations/'.date('Y_m_d_His', time() + 1).'_add_activation_columns_to_subject_table.php'),
            ], 'activation-migrations');
        }

        $this->watchModels();
        $this->registerRoutes();
    }

    /**
     * Drop the cached step set as soon as anything it was derived from changes,
     * so a finished step is acknowledged on the very next response rather than
     * whenever the short TTL happens to lapse.
     */
    private function watchModels(): void
    {
        $forget = function (Model $record): void {
            $key = Activation::subjectKeyFor($record);

            if ($key !== null && $key !== '') {
                ActivationProgress::invalidate($key);
            }
        };

        foreach (Config::watched() as $model) {
            $model::saved($forget);
            $model::deleted($forget);
        }
    }

    private function registerRoutes(): void
    {
        if (! Config::routesEnabled()) {
            return;
        }

        Route::prefix(Config::routePrefix())
            ->middleware(Config::routeMiddleware())
            ->name('activation.')
            ->group(__DIR__.'/../routes/activation.php');
    }
}
