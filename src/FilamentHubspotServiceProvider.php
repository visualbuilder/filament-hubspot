<?php

namespace Visualbuilder\FilamentHubspot;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportTesting\Testable;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Visualbuilder\FilamentHubspot\Commands\FilamentHubspotCommand;
use Visualbuilder\FilamentHubspot\Events\HubspotWebhookReceived;
use Visualbuilder\FilamentHubspot\Http\Controllers\HubspotWebhookController;
use Visualbuilder\FilamentHubspot\Providers\HubspotApiServiceProvider;
use Visualbuilder\FilamentHubspot\Testing\TestsFilamentHubspot;

class FilamentHubspotServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-hubspot';

    public static string $viewNamespace = 'filament-hubspot';

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package->name(static::$name)
            ->hasCommands($this->getCommands())
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('visualbuilder/filament-hubspot');
            });

        $configFileName = $package->shortName();

        if (file_exists($package->basePath("/../config/{$configFileName}.php"))) {
            $package->hasConfigFile();
        }

        if (file_exists($package->basePath('/../database/migrations'))) {
            $package->hasMigrations($this->getMigrations());
        }

        if (file_exists($package->basePath('/../resources/lang'))) {
            $package->hasTranslations();
        }

        if (file_exists($package->basePath('/../resources/views'))) {
            $package->hasViews(static::$viewNamespace);
        }
    }

    public function packageRegistered(): void
    {
        $this->app->register(HubspotApiServiceProvider::class);
    }

    public function packageBooted(): void
    {
        //Init user defined listeners
        foreach (config('filament-hubspot.webhook.listeners', []) as $event => $eventListeners) {
            foreach ((array) $eventListeners as $listener) {
                Event::listen($event, $listener);
            }
        }


        // Handle Stubs
        if (app()->runningInConsole()) {
            foreach (app(Filesystem::class)->files(__DIR__ . '/../stubs/') as $file) {
                $this->publishes([
                    $file->getRealPath() => base_path("stubs/filament-hubspot/{$file->getFilename()}"),
                ], 'filament-hubspot-stubs');
            }
        }

        if (config('hubspot.webhook.enabled', true)) {
            $this->registerWebhookRoute();
        }

        // Testing
        Testable::mixin(new TestsFilamentHubspot());
    }

    protected function registerWebhookRoute(): void
    {
        $slug = config('hubspot.webhook.slug', 'api/hubspot/webhook');
        Route::post($slug, HubspotWebhookController::class)
            ->name('filament-hubspot.webhook');
    }


    protected function getAssetPackageName(): ?string
    {
        return 'visualbuilder/filament-hubspot';
    }

    /**
     * @return array<class-string>
     */
    protected function getCommands(): array
    {
        return [
            FilamentHubspotCommand::class,
        ];
    }


    /**
     * @return array<string>
     */
    protected function getMigrations(): array
    {
        return [
            'create_leads_table',
        ];
    }
}
