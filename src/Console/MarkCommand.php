<?php

namespace DaverDalas\LaravelPostDeployCommands\Console;

use DaverDalas\LaravelPostDeployCommands\CommandRunner;

class MarkCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deploy-commands:mark-all-completed {--database= : The database connection to use.}
                {--path= : The path of commands files to be executed.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark all post deploy commands as completed';

    /**
     * The CommandRunner instance.
     *
     * @var \DaverDalas\LaravelPostDeployCommands\CommandRunner
     */
    protected $commandRunner;

    /**
     * Create a new migration command instance.
     *
     * @param  \DaverDalas\LaravelPostDeployCommands\CommandRunner  $commandRunner
     * @return void
     */
    public function __construct(CommandRunner $commandRunner)
    {
        parent::__construct();

        $this->commandRunner = $commandRunner;
    }

    /**
     * Execute the console command.
     *
     * @return void
     * @throws \Exception
     * @throws \Throwable
     */
    public function handle()
    {
        $this->prepareDatabase();

        $this->commandRunner->setOutput($this->output);

        // Next, we will check to see if a path option has been defined. If it has
        // we will use the path relative to the root of this installation folder
        // so that migrations may be run for any path within the applications.
        $this->commandRunner->markAllCompleted($this->getCommandPaths());
    }
}
