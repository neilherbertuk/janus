<?php

namespace App;

use App\Models\Webhook;
use Illuminate\Database\Capsule\Manager as Capsule;

trait Migrations
{

    /**
     * Runs database migrations / creates required tables
     */
    public function runDatabaseMigrations()
    {
        $this->createWebhooksTable();
    }

    /**
     * Undoes database migrations / Drops tables
     */
    public function undoDatabaseMigrations()
    {
        $this->dropWebHooksTable();
    }

    public function seedDatabaseTables()
    {
        $this->seedWebhooksTable();
    }

    /**
     *
     */
    private function createWebhooksTable()
    {
        // Create Webhooks table
        Capsule::schema()->create('webhooks', function ($table) {
            $table->increments('id');
            $table->integer('dnid');
            $table->string('url');
        });


        $this->seedWebhooksTable();

    }

    private function dropWebHooksTable()
    {
        Capsule::schema()->drop('webhooks');
    }

    private function seedWebhooksTable()
    {
        $webhook = new Webhook();
        $webhook->dnid = '1000';
        $webhook->url = 'http://localhost:8000';
        $webhook->save();
    }
}