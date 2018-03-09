# Laravel Post Deploy Commands
### TL;DR: Create command files that will be run once so that you don't have to remember about it.

This small package is designed to help you make easy changes in your production environment. In short: it is a slightly modified Laravel Migrator in order to run single commands files. Perhaps you are wondering for what purpose?

![](https://i.imgur.com/Br00TCn.gif)

Probably during the maintenance of an application written in Laravel, you encountered the need to make changes in your production environment. For example, copying files to a separate directory or adding new data to the database. How did you do it so far?

You probably used one of the two methods:  

- You or one of the teams created a command which then had to be executed on the server. However this raises one serious problem. You must remember to run them. It's easy if deploy is about to take place soon and there are few commands. But as you probably already know it is easy to forget about it when there are many commands to run and deploy will take place in some distant time.
- You have made changes in migration files. However this also raises problems. The code responsible for changes in the database is mixed with the code responsible for other changes. In addition it should be remembered that these changes will be started every time the database is refreshed which increase migration and development time.

The solution I created allows you to easily create commands that will run only once and exactly where you need them. You can easly integrate thmen to your Continuous Delivery process and forget about running changes yourself.

## Installation
1. Install the package via Composer:
```
composer require daverdalas/laravel-post-deploy-commands
```
The package will automatically register itself with Laravel 5.5+. For older versions of Laravel or Lumen register ServiceProvider:
```php
DaverDalas\LaravelPostDeployCommands\ServiceProvider::class
```

## Usage
Create new command file. The file will be saved in database/commands directory.
```
php artisan make:deploy-command your_command_name
```
Run new commands (Those which have not been started before).
```
php artisan deploy-commands:run
```

## TODO
- clean code
- write some tests

> **NOTE**: The code used to create this package was based on the Laravel code. That is why all the contributions for its creation belong to the creators of Laravel. It was only modified for the purpose of this package.
