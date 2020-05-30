<?php

namespace App;

use Dotenv\Exception\InvalidPathException;
use Illuminate\Database\Capsule\Manager as Capsule;

trait Setup
{

    /**
     * Load environment variables from .env
     */
    protected function loadEnvironmentVariables()
    {
        try {
            $dotenv = new \Dotenv\Dotenv(base_path());
            $dotenv->load();
        } catch (\Exception $exception) {
            if ($exception instanceof InvalidPathException) {
                die('No .env file found');
            }
        }
    }

    /**
     * Load and boot Eloquent ORM
     */
    protected function loadEloquentORM()
    {
        $capsule = new Capsule;

        $capsule->addConnection([

            'driver' => 'sqlite',
            'database' => base_path('database/database.sqlite'),
            'prefix' => ''

        ]);

        //Make this Capsule instance available globally.
        $capsule->setAsGlobal();

        // Setup the Eloquent ORM.
        $capsule->bootEloquent();

        // Uncomment to add run database migrations

        //$this->runDatabaseMigrations();

    }

    protected function setCWD()
    {
        $root = getenv('root');

        chdir($root);
    }

}