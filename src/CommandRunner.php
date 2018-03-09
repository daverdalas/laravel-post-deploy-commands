<?php

namespace DaverDalas\LaravelPostDeployCommands;

use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Container\Container;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Database\ConnectionResolverInterface as Resolver;
use Symfony\Component\Console\Input\ArgvInput;

class CommandRunner
{
    /**
     * The Laravel application instance.
     *
     * @var Container
     */
    protected $laravel;

    /**
     * The migration repository implementation.
     *
     * @var DatabaseMigrationRepository
     */
    protected $repository;

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * The connection resolver instance.
     *
     * @var \Illuminate\Database\ConnectionResolverInterface
     */
    protected $resolver;

    /**
     * The name of the default connection.
     *
     * @var string
     */
    protected $connection;

    /**
     * The paths to all of the migration files.
     *
     * @var array
     */
    protected $paths = [];

    /**
     * The output interface implementation.
     *
     * @var OutputStyle
     */
    protected $output;

    /**
     * Create a new migrator instance.
     *
     * @param Container $laravel
     * @param DatabaseMigrationRepository $repository
     * @param  \Illuminate\Database\ConnectionResolverInterface $resolver
     * @param  \Illuminate\Filesystem\Filesystem $files
     */
    public function __construct(
        Container $laravel,
        DatabaseMigrationRepository $repository,
        Resolver $resolver,
        Filesystem $files)
    {
        $this->laravel = $laravel;
        $this->files = $files;
        $this->resolver = $resolver;
        $this->repository = $repository;
    }

    /**
     * @return OutputStyle
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * @param OutputStyle $output
     * @return CommandRunner
     */
    public function setOutput(OutputStyle $output)
    {
        $this->output = $output;
        return $this;
    }

    /**
     * Run the pending migrations at a given path.
     *
     * @param  array|string $paths
     * @param  array $options
     * @return array
     * @throws \Exception
     * @throws \Throwable
     */
    public function run($paths = [], array $options = [])
    {
        // Once we grab all of the migration files for the path, we will compare them
        // against the migrations that have already been run for this package then
        // run each of the outstanding migrations against a database connection.
        $files = $this->getCommandFiles($paths);

        $this->requireFiles($migrations = $this->pendingMigrations(
            $files, $this->repository->getRan()
        ));

        // Once we have all these migrations that are outstanding we are ready to run
        // we will go ahead and run them "up". This will execute each migration as
        // an operation against a database. Then we'll return this list of them.
        $this->runPending($migrations, $options);

        return $migrations;
    }

    /**
     * Get the migration files that have not yet run.
     *
     * @param  array  $files
     * @param  array  $ran
     * @return array
     */
    protected function pendingMigrations($files, $ran)
    {
        return Collection::make($files)
            ->reject(function ($file) use ($ran) {
                return in_array($this->getCommandName($file), $ran);
            })->values()->all();
    }

    /**
     * Run an array of migrations.
     *
     * @param  array $migrations
     * @param  array $options
     * @return void
     * @throws \Exception
     * @throws \Throwable
     */
    public function runPending(array $migrations, array $options = [])
    {
        // First we will just make sure that there are any migrations to run. If there
        // aren't, we will just make a note of it to the developer so they're aware
        // that all of the migrations have been run against this database system.
        if (count($migrations) == 0) {
            $this->note('<info>Nothing to run.</info>');

            return;
        }

        $pretend = $options['pretend'] ?? false;

        // Once we have the array of migrations, we will spin through them and run the
        // migrations "up" so the changes are made to the databases. We'll then log
        // that the migration was run so we don't repeat it next time we execute.
        foreach ($migrations as $file) {
            $this->runCommand($file, $pretend);
        }
    }

    /**
     * Run "up" a migration instance.
     *
     * @param  string $file
     * @param  bool $pretend
     * @return void
     * @throws \Exception
     * @throws \Throwable
     */
    protected function runCommand($file, $pretend)
    {
        // First we will resolve a "real" instance of the migration class from this
        // migration file name. Once we have the instances we can run the actual
        // command such as "up" or "down", or we can just simulate the action.
        $command = $this->resolve(
            $name = $this->getCommandName($file)
        );

        if ($pretend) {
            return $this->pretendToRun($command, 'handle');
        }

        $this->note("<comment>Running:</comment> {$name}");

        // Run command
        $command->run(new ArgvInput([]), $this->output);

        // Once we have run a migrations class, we will log that it was run in this
        // repository so that we don't try to run it next time we do a migration
        // in the application. A migration repository keeps the migrate order.
        $this->repository->log($name);

        $this->note("<info>Completed:</info>  {$name}");
    }

    /**
     * Pretend to run the migrations.
     *
     * @param  object  $migration
     * @param  string  $method
     * @return void
     */
    protected function pretendToRun($migration, $method)
    {
        foreach ($this->getQueries($migration, $method) as $query) {
            $name = get_class($migration);

            $this->note("<info>{$name}:</info> {$query['query']}");
        }
    }

    /**
     * Get all of the queries that would be run for a migration.
     *
     * @param  object  $migration
     * @param  string  $method
     * @return array
     */
    protected function getQueries($migration, $method)
    {
        // Now that we have the connections we can resolve it and pretend to run the
        // queries against the database returning the array of raw SQL statements
        // that would get fired against the database system for this migration.
        $db = $this->resolveConnection(
            $migration->getConnection()
        );

        return $db->pretend(function () use ($migration, $method) {
            if (method_exists($migration, $method)) {
                $migration->{$method}();
            }
        });
    }

    /**
     * Resolve a migration instance from a file.
     *
     * @param  string  $file
     * @return object|Command
     */
    public function resolve($file)
    {
        $class = Str::studly(implode('_', array_slice(explode('_', $file), 4)));

        /** @var Command $command */
        $command = new $class;
        $command->setLaravel($this->laravel);

        return $command;
    }

    /**
     * Get all of the migration files in a given path.
     *
     * @param  string|array  $paths
     * @return array
     */
    public function getCommandFiles($paths)
    {
        return Collection::make($paths)->flatMap(function ($path) {
            return $this->files->glob($path.'/*_*.php');
        })->filter()->sortBy(function ($file) {
            return $this->getCommandName($file);
        })->values()->keyBy(function ($file) {
            return $this->getCommandName($file);
        })->all();
    }

    /**
     * Require in all the migration files in a given path.
     *
     * @param  array   $files
     * @return void
     */
    public function requireFiles(array $files)
    {
        foreach ($files as $file) {
            $this->files->requireOnce($file);
        }
    }

    /**
     * Get the name of the migration.
     *
     * @param  string  $path
     * @return string
     */
    public function getCommandName($path)
    {
        return str_replace('.php', '', basename($path));
    }

    /**
     * Register a custom migration path.
     *
     * @param  string  $path
     * @return void
     */
    public function path($path)
    {
        $this->paths = array_unique(array_merge($this->paths, [$path]));
    }

    /**
     * Get all of the custom migration paths.
     *
     * @return array
     */
    public function paths()
    {
        return $this->paths;
    }

    /**
     * Set the default connection name.
     *
     * @param  string  $name
     * @return void
     */
    public function setConnection($name)
    {
        if (! is_null($name)) {
            $this->resolver->setDefaultConnection($name);
        }

        $this->repository->setSource($name);

        $this->connection = $name;
    }

    /**
     * Resolve the database connection instance.
     *
     * @param  string  $connection
     * @return \Illuminate\Database\Connection
     */
    public function resolveConnection($connection)
    {
        return $this->resolver->connection($connection ?: $this->connection);
    }

    /**
     * Get the schema grammar out of a migration connection.
     *
     * @param  \Illuminate\Database\Connection  $connection
     * @return \Illuminate\Database\Schema\Grammars\Grammar
     */
    protected function getSchemaGrammar($connection)
    {
        if (is_null($grammar = $connection->getSchemaGrammar())) {
            $connection->useDefaultSchemaGrammar();

            $grammar = $connection->getSchemaGrammar();
        }

        return $grammar;
    }

    /**
     * Get the migration repository instance.
     *
     * @return DatabaseMigrationRepository
     */
    public function getRepository()
    {
        return $this->repository;
    }

    /**
     * Determine if the migration repository exists.
     *
     * @return bool
     */
    public function repositoryExists()
    {
        return $this->repository->repositoryExists();
    }

    /**
     * Get the file system instance.
     *
     * @return \Illuminate\Filesystem\Filesystem
     */
    public function getFilesystem()
    {
        return $this->files;
    }

    /**
     * Raise a note event for the migrator.
     *
     * @param  string  $message
     * @return void
     */
    protected function note($message)
    {
        $this->output->writeln($message);
    }
}
