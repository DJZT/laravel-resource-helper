<?php

declare(strict_types=1);

namespace Djzt\ResourceHelper;

use Djzt\ResourceHelper\Support\Formatter;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\ServiceProvider;

class ResourceHelperServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom($this->configPath(), 'resource-helper');

        $this->app->singleton(Formatter::class, static fn ($app) => new Formatter($app->make(ConfigRepository::class)));

        $this->app->alias(Formatter::class, 'resource-helper');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->configPath() => $this->app->configPath('resource-helper.php'),
            ], 'resource-helper-config');
        }
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [Formatter::class, 'resource-helper'];
    }

    protected function configPath(): string
    {
        return __DIR__.'/../config/resource-helper.php';
    }
}
