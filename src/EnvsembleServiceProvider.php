<?php

declare(strict_types=1);

namespace JoeWare\Envsemble;

use Illuminate\Support\ServiceProvider;
use JoeWare\Envsemble\Commands\BuildEnvCommand;

class EnvsembleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EnvMerger::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                BuildEnvCommand::class,
            ]);
        }
    }
}
