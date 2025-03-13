<?php

namespace Visualbuilder\FilamentHubspot;

use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Asset;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportTesting\Testable;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Visualbuilder\FilamentHubspot\Commands\FilamentHubspotCommand;
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
        // Asset Registration
        FilamentAsset::register(
            $this->getAssets(),
            $this->getAssetPackageName()
        );

        FilamentAsset::registerScriptData(
            $this->getScriptData(),
            $this->getAssetPackageName()
        );

        // Icon Registration
        FilamentIcon::register($this->getIcons());

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
        Testable::mixin(new TestsFilamentHubspot);
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
     * @return array<Asset>
     */
    protected function getAssets(): array
    {
        return [
            // AlpineComponent::make('filament-hubspot', __DIR__ . '/../resources/dist/components/filament-hubspot.js'),
//            Css::make('filament-hubspot-styles', __DIR__ . '/../resources/dist/filament-hubspot.css'),
//            Js::make('filament-hubspot-scripts', __DIR__ . '/../resources/dist/filament-hubspot.js'),
        ];
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
    protected function getIcons(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    protected function getRoutes(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getScriptData(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    protected function getMigrations(): array
    {
        return [
            'create_filament-hubspot_table',
        ];
    }
}
