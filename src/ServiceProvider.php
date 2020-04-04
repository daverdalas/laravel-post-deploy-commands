<?php

namespace DaverDalas\LaravelPostDeployCommands;

use DaverDalas\LaravelPostDeployCommands\Console\InstallCommand;
use DaverDalas\LaravelPostDeployCommands\Console\MakeCommand;
use DaverDalas\LaravelPostDeployCommands\Console\MarkCommand;
use DaverDalas\LaravelPostDeployCommands\Console\RunCommand;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/config/deploy-commands.php' => config_path('deploy-commands.php'),
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                MakeCommand::class,
                RunCommand::class,
                MarkCommand::class,
            ]);
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/config/deploy-commands.php', 'deploy-commands'
        );

        $this->registerRepository();
        $this->registerRunner();
        $this->registerCreator();

        if ($this->app->runningInConsole()) {
            $this->registerMigrateInstallCommand();
            $this->registerMakeCommand();
            $this->registerRunCommand();
            $this->registerMarkCommand();
        }
    }

    /**
     * Register the migration repository service.
     */
    protected function registerRepository(): void
    {
        $this->app->singleton(DatabaseMigrationRepository::class, function ($app) {
            $table = config('deploy-commands.table');

            return new DatabaseMigrationRepository($app['db'], $table);
        });
    }

    /**
     * Register the migrator service.
     */
    protected function registerRunner(): void
    {
        // The migrator is responsible for actually running and rollback the migration
        // files in the application. We'll pass in our database connection resolver
        // so the migrator can resolve any of these connections when it needs to.
        $this->app->singleton(CommandRunner::class, function ($app) {
            $repository = $app->make(DatabaseMigrationRepository::class);

            return new CommandRunner($app, $repository, $app['db'], $app['files']);
        });
    }

    /**
     * Register the migration creator.
     */
    protected function registerCreator(): void
    {
        $this->app->singleton(CommandCreator::class, function ($app) {
            return new CommandCreator($app['files']);
        });
    }

    /**
     * Register the command.
     */
    protected function registerMigrateInstallCommand(): void
    {
        $this->app->singleton(InstallCommand::class, function ($app) {
            return new InstallCommand($app->make(DatabaseMigrationRepository::class));
        });
    }

    /**
     * Register the command.
     */
    protected function registerMakeCommand(): void
    {
        $this->app->singleton(MakeCommand::class, function ($app) {
            // Once we have the migration creator registered, we will create the command
            // and inject the creator. The creator is responsible for the actual file
            // creation of the migrations, and may be extended by these developers.
            $creator = $app->make(CommandCreator::class);
            $composer = $app['composer'];

            return new MakeCommand($creator, $composer);
        });
    }

    /**
     * Register the command.
     */
    protected function registerRunCommand(): void
    {
        $this->app->singleton(RunCommand::class, function ($app) {
            return new RunCommand($app->make(CommandRunner::class));
        });
    }

    /**
     * Register the command.
     */
    protected function registerMarkCommand(): void
    {
        $this->app->singleton(MarkCommand::class, function ($app) {
            return new MarkCommand($app->make(CommandRunner::class));
        });
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            DatabaseMigrationRepository::class,
            CommandRunner::class,
            CommandCreator::class,
        ];
    }
}
