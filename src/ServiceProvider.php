<?php

namespace DaverDalas\LaravelPostDeployCommands;

use DaverDalas\LaravelPostDeployCommands\Console\InstallCommand;
use DaverDalas\LaravelPostDeployCommands\Console\MakeCommand;
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
                'command.deploy-commands.install',
                'command.deploy-commands.make',
                'command.deploy-commands.run'
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
            // Register commands
            $this->registerMigrateInstallCommand();

            $this->registerMakeCommand();

            $this->registerRunCommand();
        }
    }

    /**
     * Register the migration repository service.
     *
     * @return void
     */
    protected function registerRepository()
    {
        $this->app->singleton('deploy-commands.repository', function ($app) {
            $table = $app['config']['deploy-commands.table'];

            return new DatabaseMigrationRepository($app['db'], $table);
        });
    }

    /**
     * Register the migrator service.
     *
     * @return void
     */
    protected function registerRunner()
    {
        // The migrator is responsible for actually running and rollback the migration
        // files in the application. We'll pass in our database connection resolver
        // so the migrator can resolve any of these connections when it needs to.
        $this->app->singleton('deploy-commands.runner', function ($app) {
            $repository = $app['deploy-commands.repository'];

            return new CommandRunner($app, $repository, $app['db'], $app['files']);
        });
    }

    /**
     * Register the migration creator.
     *
     * @return void
     */
    protected function registerCreator()
    {
        $this->app->singleton('deploy-commands.creator', function ($app) {
            return new CommandCreator($app['files']);
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerMigrateInstallCommand()
    {
        $this->app->singleton('command.deploy-commands.install', function ($app) {
            return new InstallCommand($app['deploy-commands.repository']);
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerMakeCommand()
    {
        $this->app->singleton('command.deploy-commands.make', function ($app) {
            // Once we have the migration creator registered, we will create the command
            // and inject the creator. The creator is responsible for the actual file
            // creation of the migrations, and may be extended by these developers.
            $creator = $app['deploy-commands.creator'];

            $composer = $app['composer'];

            return new MakeCommand($creator, $composer);
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerRunCommand()
    {
        $this->app->singleton('command.deploy-commands.run', function ($app) {
            return new RunCommand($app['deploy-commands.runner']);
        });
    }


    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            'deploy-commands.repository',
            'deploy-commands.runner',
            'deploy-commands.creator',
        ];
    }
}
